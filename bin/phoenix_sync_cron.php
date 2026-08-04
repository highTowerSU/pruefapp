#!/usr/bin/env php
<?php
declare(strict_types=1);

// Run from cron as the same user that owns the application data (usually www-data).
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';

$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
if (!is_dir($root)) mkdir($root, 0700, true);
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
    $missingReports = R::getAll("SELECT i.id, i.external_number, d.id AS device_id
        FROM inspection i
        JOIN device d ON d.id = i.device_id
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
                    'Prüfbericht ' . (string) $inspection->external_number
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
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($id));
}
flock($lock, LOCK_UN);
fclose($lock);
