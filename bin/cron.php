#!/usr/bin/env php
<?php
declare(strict_types=1);

use RedBeanPHP\R as R;

// Run from cron as the same user that owns the application data (usually www-data).
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';
require_once dirname(__DIR__) . '/controllers/InspectionController.php';
require_once dirname(__DIR__) . '/lib/ElectricalInspectionImportService.php';

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
$cronStartedAt = microtime(true);
$cronDeadline = $cronStartedAt + 120.0;
$timeLeft = static fn(): float => max(0.0, $cronDeadline - microtime(true));
$logPath = app_data_root() . '/logs/cron.log';
if (!is_dir(dirname($logPath))) @mkdir(dirname($logPath), 0770, true);
$log = static function (string $message, string $level = 'info') use ($logPath, $debug): void { $timestamp = date(DATE_ATOM); $line = '[' . $timestamp . '] ' . strtoupper($level) . ' ' . $message . PHP_EOL; $written = @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX); if (PHP_SAPI === 'cli' || $debug) fwrite(STDERR, $line . ($written === false ? '[cron debug] Textlog konnte nicht geschrieben werden: ' . $logPath . PHP_EOL : '')); try { R::exec('INSERT INTO cron_log (run_at, level, message) VALUES (?, ?, ?)', [$timestamp, strtolower($level), $message]); } catch (Throwable $exception) { if (PHP_SAPI === 'cli' || $debug) fwrite(STDERR, '[cron_log database] ' . $exception->getMessage() . PHP_EOL); } };
$log('Cron gestartet, PID ' . getmypid() . ', Zeitbudget 120 Sekunden');
if ($debug) $log('Debug: verbleibendes Zeitbudget ' . number_format($timeLeft(), 1, ',', '.') . ' Sekunden');
file_put_contents($root . '/cron-heartbeat.json', json_encode(['last_run' => date(DATE_ATOM), 'pid' => getmypid(), 'time_limit_seconds' => 120], JSON_UNESCAPED_UNICODE), LOCK_EX);
$lock = fopen($root . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);

// Optional one-time repair/reimport for Benning CSV/ODS data. Set the
// directory only for the migration run; the marker prevents repeated imports.
$migrationDirectory = trim((string) (getenv('PRUEFAPP_BENNING_REIMPORT_DIR') ?: (function_exists('config_value') ? (config_value('APP_BENNING_REIMPORT_DIRECTORY') ?: '') : '') ?: (function_exists('get_app_config') ? (get_app_config('benning_reimport_directory', '') ?: '') : '')));
$migrationMarker = app_data_root() . '/migration/benning-measurements-v3.done';
if (!is_file($migrationMarker)) {
    try {
        if ($timeLeft() <= 1) throw new RuntimeException('Zeitbudget vor der Nachmigration erreicht.');
        if ($debug) $log('Debug: starte Benning-Nachmigration.');
        $stats = ['imported' => 0, 'updated' => 0, 'repaired' => 0, 'errors' => []];
        if ($migrationDirectory !== '' && is_dir($migrationDirectory)) {
            $migrationReports = trim((string) (getenv('PRUEFAPP_BENNING_REPORTS_DIR') ?: (function_exists('config_value') ? (config_value('APP_BENNING_REPORTS_DIRECTORY') ?: '') : '') ?: (function_exists('get_app_config') ? (get_app_config('benning_reports_directory', '') ?: '') : '')));
            $importStats = (new ElectricalInspectionImportService())->importDirectory($migrationDirectory, $migrationReports !== '' ? $migrationReports : null);
            $stats = array_merge($stats, ['imported' => $importStats['imported'] ?? 0, 'updated' => $importStats['updated'] ?? 0, 'errors' => $importStats['errors'] ?? []]);
        }
        foreach (R::findAll('inspection', " source_type = 'csv' ORDER BY id ") as $inspection) {
            if ($timeLeft() <= 2) { $log('Benning-Nachmigration wegen Zeitbudget auf nächste Iteration verschoben.', 'warning'); break; }
            $measurements = json_decode((string) ($inspection->measurements_json ?? ''), true);
            if (!is_array($measurements) || $measurements === []) continue;
            $normalized = InspectionController::normalizeImportedMeasurements($measurements, (string) ($inspection->result_status ?? ''));
            if ($normalized === $measurements) continue;
            $inspection->measurements_json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $inspection->updated_at = date(DATE_ATOM);
            R::store($inspection);
            $stats['repaired']++;
        }
        if (!is_dir(dirname($migrationMarker))) @mkdir(dirname($migrationMarker), 0770, true);
        if ($timeLeft() > 2) {
            file_put_contents($migrationMarker, json_encode(['completed_at' => date(DATE_ATOM), 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $log('Benning-Messdaten-Nachmigration abgeschlossen: ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $exception) {
        $log('Benning-Messdaten-Nachmigration fehlgeschlagen: ' . $exception->getMessage(), 'error');
    }
}

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
    $reportTotal = count($missingReports);
    if ($debug) $log('Debug: ' . $reportTotal . ' fehlende Prüfberichte gefunden.');
    foreach ($missingReports as $row) {
        if ($timeLeft() <= 3) { $log('Berichtserzeugung wegen Zeitbudget auf nächste Iteration verschoben.', 'warning'); break; }
        if ($debug) $log('Debug: Bericht ' . ((int) $row['id']) . ' wird verarbeitet (' . ($generatedReports + 1) . '/' . $reportTotal . ').');
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
    if ($timeLeft() <= 8) { $log('Weitere Hintergrundjobs wegen Zeitbudget auf nächste Cron-Iteration verschoben.', 'warning'); break; }
    $status = json_decode((string) file_get_contents($statusPath), true);
    if (!is_array($status) || ($status['state'] ?? '') !== 'queued') continue;
    $id = (string) ($status['id'] ?? basename($statusPath, '.status.json'));
    if (!preg_match('/^[a-f0-9]{24}$/', $id) || !is_file($root . '/' . $id . '.json')) continue;
    $log('Job gestartet: ' . $id . ' (verbleibend ' . number_format($timeLeft(), 1, ',', '.') . ' s)');
    $workerDeadline = (string) (microtime(true) + max(10.0, min(105.0, $timeLeft() - 3.0)));
    $workerDebug = $debug ? ' PRUEFAPP_CRON_DEBUG=1' : '';
    passthru('PRUEFAPP_CRON_DEADLINE=' . escapeshellarg($workerDeadline) . $workerDebug . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($id));
    $log('Job beendet: ' . $id . ' (verbleibend ' . number_format($timeLeft(), 1, ',', '.') . ' s)');
}
$log('Cron beendet, Dauer ' . number_format(microtime(true) - $cronStartedAt, 1, ',', '.') . ' Sekunden');
flock($lock, LOCK_UN);
fclose($lock);
