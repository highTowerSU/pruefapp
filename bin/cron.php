#!/usr/bin/env php
<?php
declare(strict_types=1);

use RedBeanPHP\R as R;

// Run from cron as the same user that owns the application data (usually www-data).
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';

$debug = in_array('--debug', $argv ?? [], true) || in_array('-d', $argv ?? [], true);
if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    fwrite(STDERR, '[cron debug] Datenbank: ' . (function_exists('app_database_path') ? app_database_path() : 'unbekannt') . PHP_EOL);
}
try {
    R::exec("CREATE TABLE IF NOT EXISTS cron_log (id INTEGER PRIMARY KEY AUTOINCREMENT, run_at TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info', message TEXT NOT NULL DEFAULT '')");
} catch (Throwable $exception) {
    if ($debug) fwrite(STDERR, '[cron debug] cron_log konnte nicht angelegt werden: ' . $exception->getMessage() . PHP_EOL);
}

$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
if (!is_dir($root)) mkdir($root, 0700, true);
$logPath = app_data_root() . '/logs/cron.log';
if (!is_dir(dirname($logPath))) @mkdir(dirname($logPath), 0770, true);
$log = static function (string $message, string $level = 'info') use ($logPath, $debug): void { $timestamp = date(DATE_ATOM); $line = '[' . $timestamp . '] ' . strtoupper($level) . ' ' . $message . PHP_EOL; $written = @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX); if (PHP_SAPI === 'cli' || $debug) fwrite(STDERR, $line . ($written === false ? '[cron debug] Textlog konnte nicht geschrieben werden: ' . $logPath . PHP_EOL : '')); try { $entry = R::dispense('cron_log'); $entry->run_at = $timestamp; $entry->level = $level; $entry->message = $message; R::store($entry); } catch (Throwable $exception) { if (PHP_SAPI === 'cli' || $debug) fwrite(STDERR, '[cron_log database] ' . $exception->getMessage() . PHP_EOL); } };
$log('Cron gestartet, PID ' . getmypid());
file_put_contents($root . '/cron-heartbeat.json', json_encode(['last_run' => date(DATE_ATOM), 'pid' => getmypid()], JSON_UNESCAPED_UNICODE), LOCK_EX);
$lock = fopen($root . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);

// Abgeschlossene Prüfungen bekommen auch dann automatisch einen Bericht,
// wenn sie nicht über das Webformular abgeschlossen wurden (z. B. Import).
$generatedReports = 0;
$reportErrors = [];
$reportDir = app_data_root() . '/reports/current';
if (!is_dir($reportDir)) mkdir($reportDir, 0770, true);
try {
    $missingReports = R::getAll("SELECT i.id, i.external_number, d.id AS device_id, c.id AS customer_id
        FROM inspection i
        JOIN device d ON d.id = i.device_id
        LEFT JOIN room r ON r.id = d.room_id
        LEFT JOIN floor f ON f.id = r.floor_id
        LEFT JOIN building b ON b.id = f.building_id
        LEFT JOIN site s ON s.id = b.site_id
        LEFT JOIN customer c ON c.id = s.customer_id
        WHERE i.result_status IN ('bestanden', 'durchgefallen', 'nicht bestanden')
          AND TRIM(COALESCE(i.report_path, '')) = ''
        ORDER BY i.id ASC
        LIMIT 500");
    foreach ($missingReports as $row) {
        try {
            $inspection = R::load('inspection', (int) $row['id']);
            $device = R::load('device', (int) $row['device_id']);
            if (!$inspection->id || !$device->id) continue;
            $relative = 'reports/current/' . (int) $inspection->id . '.pdf';
            $path = app_data_root() . '/' . $relative;
            if (!is_file($path)) {
                $pdf = ReportController::renderPdf(
                    ReportController::inspectionPdfRows($inspection, $device),
                    'Prüfbericht ' . (string) $inspection->external_number,
                    function_exists('get_company_branding') ? get_company_branding((int) ($row['customer_id'] ?? 0)) : null
                );
                if (file_put_contents($path, $pdf, LOCK_EX) === false) {
                    throw new RuntimeException('PDF konnte nicht gespeichert werden.');
                }
            }
            $inspection->report_path = $relative;
            $inspection->updated_at = date(DATE_ATOM);
            R::store($inspection);
            $generatedReports++;
        } catch (Throwable $exception) {
            $reportErrors[] = ['inspection_id' => (int) $row['id'], 'error' => $exception->getMessage()];
        }
    }
} catch (Throwable $exception) {
    $reportErrors[] = ['error' => $exception->getMessage()];
}
$log('Berichte erzeugt: ' . $generatedReports . ', Fehler: ' . count($reportErrors));
foreach ($reportErrors as $error) $log('Berichtsfehler: ' . json_encode($error, JSON_UNESCAPED_UNICODE));

file_put_contents($root . '/report-heartbeat.json', json_encode([
    'last_run' => date(DATE_ATOM),
    'generated' => $generatedReports,
    'errors' => $reportErrors,
], JSON_UNESCAPED_UNICODE), LOCK_EX);

foreach (glob($root . '/*.status.json') ?: [] as $statusPath) {
    $status = json_decode((string) file_get_contents($statusPath), true);
    if (!is_array($status) || ($status['state'] ?? '') !== 'queued') continue;
    $id = (string) ($status['id'] ?? basename($statusPath, '.status.json'));
    if (!preg_match('/^[a-f0-9]{24}$/', $id) || !is_file($root . '/' . $id . '.json')) continue;
    $log('Job gestartet: ' . $id);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($id));
    $log('Job beendet: ' . $id);
}
flock($lock, LOCK_UN);
fclose($lock);
