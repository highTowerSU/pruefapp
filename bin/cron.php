#!/usr/bin/env php
<?php

declare(strict_types=1);

use Ceneos\PhpBase\Jobs\JobQueue;
use Ceneos\PhpBase\Notification\NotificationRepository;
use Ceneos\PhpBase\Audit\AuditTrailRepository;
use RedBeanPHP\R;

$configOverride = trim((string) getenv('PRUEFAPP_CONFIG_FILE'));
if ($configOverride !== '' && !defined('CENEOS_CONFIG_FILE')) define('CENEOS_CONFIG_FILE', $configOverride);
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';
require_once dirname(__DIR__) . '/controllers/InspectionController.php';

$debug = in_array('--debug', $argv ?? [], true) || in_array('-d', $argv ?? [], true);
if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    fwrite(STDERR, '[cron debug] Datenbank: ' . (function_exists('app_database_path') ? app_database_path() : 'unbekannt') . PHP_EOL);
}

try {
    R::exec("CREATE TABLE IF NOT EXISTS cron_log (id INTEGER PRIMARY KEY AUTOINCREMENT, run_at TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info', message TEXT NOT NULL DEFAULT '', run_id TEXT NOT NULL DEFAULT '', context_json TEXT NOT NULL DEFAULT '{}')");
    $cronColumns = R::inspect('cron_log');
    if (!isset($cronColumns['run_id'])) R::exec("ALTER TABLE cron_log ADD COLUMN run_id TEXT NOT NULL DEFAULT ''");
    if (!isset($cronColumns['context_json'])) R::exec("ALTER TABLE cron_log ADD COLUMN context_json TEXT NOT NULL DEFAULT '{}'");
} catch (Throwable $exception) {
    if ($debug) fwrite(STDERR, '[cron debug] cron_log konnte nicht angelegt werden: ' . $exception->getMessage() . PHP_EOL);
}

$runtimeRoot = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
if (!is_dir($runtimeRoot)) mkdir($runtimeRoot, 0700, true);
$startedAt = microtime(true);
$budget = max(30, min(900, (int) (get_app_config('cron_time_budget_seconds', (string) (getenv('PRUEFAPP_CRON_TIME_BUDGET') ?: 120)) ?? 120)));
$slice = max(5, min(120, (int) (get_app_config('cron_job_slice_seconds', (string) (getenv('PRUEFAPP_CRON_JOB_SLICE') ?: 30)) ?? 30)));
$lease = max(30, min(900, (int) (get_app_config('cron_job_lease_seconds', (string) (getenv('PRUEFAPP_CRON_JOB_LEASE') ?: 180)) ?? 180)));
$deadline = $startedAt + $budget;
$timeLeft = static fn(): float => max(0.0, $deadline - microtime(true));
$runId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logPath = app_data_root() . '/logs/cron.log';
if (!is_dir(dirname($logPath))) mkdir(dirname($logPath), 0770, true);
$log = static function (string $message, string $level = 'info', array $context = []) use ($logPath, $debug, $runId): void {
    $timestamp = date(DATE_ATOM);
    $line = '[' . $timestamp . '] ' . strtoupper($level) . ' ' . $message . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    if ($debug || in_array(strtolower($level), ['warning', 'error', 'critical'], true)) fwrite(STDERR, $line);
    try {
        R::exec('INSERT INTO cron_log (run_at, level, message, run_id, context_json) VALUES (?, ?, ?, ?, ?)', [$timestamp, strtolower($level), $message, $runId, json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}']);
    } catch (Throwable $exception) {
        if ($debug) fwrite(STDERR, '[cron debug] Datenbank-Log fehlgeschlagen: ' . $exception->getMessage() . PHP_EOL);
    }
};

$lock = fopen($runtimeRoot . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    if ($debug) fwrite(STDERR, '[cron debug] Ein anderer Cron-Lauf ist noch aktiv.' . PHP_EOL);
    exit(0);
}

$finished = false;
$jobsStarted = 0;
$jobsRemaining = 0;
register_shutdown_function(static function () use (&$finished, $log, $startedAt, &$jobsStarted, &$jobsRemaining): void {
    if ($finished) return;
    $error = error_get_last();
    $suffix = is_array($error) ? ' Letzter PHP-Fehler: ' . (string) ($error['message'] ?? 'unbekannt') : '';
    $log('Hintergrundlauf unerwartet beendet; Dauer ' . number_format(microtime(true) - $startedAt, 1, ',', '.') . ' Sekunden, Aufgaben gestartet ' . $jobsStarted . ', offen ' . $jobsRemaining . '.' . $suffix, 'error');
});

$prune = static function () use ($logPath, $debug): void {
    try {
        $maxRows = max(500, (int) (get_app_config('cron_log_max_rows', (string) (getenv('PRUEFAPP_CRON_LOG_MAX_ROWS') ?: 5000)) ?? 5000));
        $count = (int) R::getCell('SELECT COUNT(*) FROM cron_log');
        if ($count > $maxRows) {
            $cutoff = R::getCell('SELECT id FROM cron_log ORDER BY id DESC LIMIT 1 OFFSET ?', [$maxRows]);
            if ($cutoff !== null && $cutoff !== false) R::exec('DELETE FROM cron_log WHERE id <= ?', [(int) $cutoff]);
        }
        $historyDays = max(7, min(3650, (int) (get_app_config('background_history_days', '180') ?? 180)));
        NotificationRepository::pruneOlderThan($historyDays);
        JobQueue::pruneFinished($historyDays);
        $auditRows = max(1000, min(5000000, (int) (get_app_config('audit_log_max_rows', '250000') ?? 250000)));
        (new AuditTrailRepository())->pruneToMaxRows($auditRows);
    } catch (Throwable $exception) {
        if ($debug) fwrite(STDERR, '[cron debug] Aufbewahrung konnte nicht bereinigt werden: ' . $exception->getMessage() . PHP_EOL);
    }
    $maxBytes = max(262144, (int) (get_app_config('cron_log_max_bytes', (string) (getenv('PRUEFAPP_CRON_LOG_MAX_BYTES') ?: 5242880)) ?? 5242880));
    if (is_file($logPath) && (int) filesize($logPath) > $maxBytes) {
        $lines = explode("\n", (string) file_get_contents($logPath));
        file_put_contents($logPath, ltrim(implode("\n", array_slice($lines, -10000))), LOCK_EX);
    }
};
$prune();

$log('Hintergrundlauf gestartet. Offene Aufgaben werden nach Priorität am gespeicherten Stand fortgesetzt.');
$log('Zeitbudget ' . $budget . ' Sekunden; Abschnitt ' . $slice . ' Sekunden; Worker-Lease ' . $lease . ' Sekunden.', 'debug');
file_put_contents($runtimeRoot . '/cron-heartbeat.json', json_encode(['last_run' => date(DATE_ATOM), 'pid' => getmypid(), 'time_limit_seconds' => $budget, 'run_id' => $runId], JSON_UNESCAPED_UNICODE), LOCK_EX);

$legacyImported = BackgroundJobService::importLegacyJobs();
if ($legacyImported > 0) $log($legacyImported . ' ältere Hintergrundaufgabe(n) wurden in die gemeinsame Aufgabenliste übernommen.');
$legacyImportLogs = BackgroundJobService::importLegacyImportLogs();
if ($legacyImportLogs > 0) $log($legacyImportLogs . ' ältere Importprotokoll(e) wurden in das Ereignisprotokoll übernommen.');

// Register automatic maintenance only when it is actually needed. Dedupe keys
// guarantee that repeated Cron invocations do not create parallel copies.
try {
    $migrationRoot = app_data_root() . '/migration';
    // V2 correction for historical imports which predate the classification
    // column. Do not rely on the old all-data migration marker: it may have
    // been completed before these rows were imported or classified.
    $legacyClassificationVersion = trim((string) get_app_config('legacy_classification_migration_version', ''));
    $legacyUnclassified = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'");
    if ($legacyClassificationVersion !== '2' || $legacyUnclassified > 0) {
        if ($legacyUnclassified > 0) {
            BackgroundJobService::enqueue(
                'legacy_classification_migration',
                ['type' => 'legacy_classification_migration'],
                ['total' => $legacyUnclassified, 'dedupe_key' => 'maintenance:legacy-classification:v2', 'cancellable' => false]
            );
        } else {
            set_app_config('legacy_classification_migration_version', '2');
        }
    }
    $importReconciliationVersion = trim((string) get_app_config('import_result_reconciliation_version', ''));
    BackgroundJobService::deduplicateImportReconciliationNotifications();
    $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND result_status = 'data_missing'");
    // Version 2 intentionally leaves genuinely inconclusive imports open.
    // Re-queuing those rows after a successful run produced a fresh completed
    // job (and notification) on every cron tick without making progress.
    if ($importReconciliationVersion !== '7') {
        if ($importsToReconcile > 0) {
            BackgroundJobService::enqueue(
                'import_result_reconciliation',
                ['type' => 'import_result_reconciliation'],
                ['total' => $importsToReconcile, 'dedupe_key' => 'maintenance:import-result-reconciliation:v7', 'cancellable' => false]
            );
        } else {
            set_app_config('import_result_reconciliation_version', '7');
        }
    }
    $inspectionDataMigrationVersion = trim((string) get_app_config('inspection_data_migration_version', ''));
    if ($inspectionDataMigrationVersion !== '1') {
        $total = (int) R::getCell('SELECT COUNT(*) FROM inspection');
        if ($total > 0) {
            BackgroundJobService::enqueue(
                'inspection_data_migration',
                ['type' => 'inspection_data_migration'],
                ['total' => $total, 'dedupe_key' => 'maintenance:inspection-data:v1', 'cancellable' => false]
            );
        }
    }
    $benningDirectory = trim((string) (get_app_config('benning_reimport_directory', '') ?: getenv('PRUEFAPP_BENNING_REIMPORT_DIR')));
    $benningImportMarker = $migrationRoot . '/benning-import-v3.done';
    if ($benningDirectory !== '' && is_dir($benningDirectory) && !is_file($benningImportMarker)) {
        $reportsDirectory = trim((string) (get_app_config('benning_reports_directory', '') ?: getenv('PRUEFAPP_BENNING_REPORTS_DIR')));
        BackgroundJobService::enqueue('directory_import', [
            'type' => 'directory_import',
            'directory' => $benningDirectory,
            'reports_directory' => $reportsDirectory,
            'completion_marker' => $benningImportMarker,
        ], ['dedupe_key' => 'maintenance:benning-import:v3', 'cancellable' => false]);
    }
    // Report jobs must only see canonical data. This gate also prevents the
    // older JSON/measurement maintenance paths from racing the new migration.
    if ($inspectionDataMigrationVersion === '1') {
        $legacyRestoreMarker = $migrationRoot . '/inspection-reports-legacy-restore-v5.json';
        if (!is_file($legacyRestoreMarker)) {
            $legacyTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'legacy' AND result_status IN ('passed','failed')");
            if ($legacyTotal > 0) {
                BackgroundJobService::enqueue(
                    'phoenix_pdf_restore',
                    ['type' => 'phoenix_pdf_restore'],
                    ['total' => $legacyTotal, 'dedupe_key' => 'maintenance:legacy-originals:v5', 'cancellable' => false]
                );
            }
        }
        $reportMarker = $migrationRoot . '/inspection-reports-v3.json';
        $reportState = is_file($reportMarker) ? json_decode((string) file_get_contents($reportMarker), true) : [];
        if (!is_array($reportState)) $reportState = [];
        if (($reportState['completed'] ?? false) !== true) {
            $lastId = max(0, (int) ($reportState['last_id'] ?? 0));
            $eligibleSql = "result_status IN ('passed','failed') AND classification = 'migrated_import'";
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligibleSql}");
            $current = $lastId > 0
                ? (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE id <= ? AND {$eligibleSql}", [$lastId])
                : 0;
            if ($total > 0) {
                BackgroundJobService::enqueue(
                    'report_migration',
                    ['type' => 'report_migration'],
                    [
                        'current' => $current,
                        'total' => $total,
                        'checkpoint' => ['last_id' => $lastId],
                        'dedupe_key' => 'maintenance:reports:v3',
                        'cancellable' => false,
                    ]
                );
            }
        }

        $missing = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE result_status IN ('passed','failed') AND COALESCE(classification, '') <> 'legacy' AND TRIM(COALESCE(report_path, '')) = '' AND " . inspection_report_signature_sql('inspection'));
        if ($missing > 0) {
            BackgroundJobService::enqueue(
                'missing_reports',
                ['type' => 'missing_reports'],
                ['total' => $missing, 'dedupe_key' => 'automatic:missing-reports-v2', 'cancellable' => false]
            );
        }
    }
} catch (Throwable $exception) {
    $log('Automatische Aufgaben konnten nicht vollständig eingeplant werden: ' . $exception->getMessage(), 'error');
}

$recovered = JobQueue::recoverExpiredLeases();
if ($recovered > 0) $log($recovered . ' unterbrochene Aufgabe(n) wurden am letzten gespeicherten Stand wieder freigegeben.', 'warning');

while ($timeLeft() > 8) {
    $workerId = 'cron-' . getmypid() . '-' . bin2hex(random_bytes(4));
    $job = JobQueue::claim(null, $workerId, $lease);
    if ($job === null) break;
    $publicId = (string) $job['public_id'];
    $label = BackgroundJobService::label((string) $job['type']);
    $log($label . ' ' . substr($publicId, 0, 12) . ' startet bei ' . (int) $job['current'] . ' von ' . (int) $job['total'] . '.', 'info', ['job_id' => $publicId, 'job_type' => $job['type']]);
    $jobsStarted++;
    $workerDeadline = microtime(true) + max(5.0, min((float) $slice, $timeLeft() - 3.0));
    $environment = 'PRUEFAPP_CRON_DEADLINE=' . escapeshellarg((string) $workerDeadline) . ' PRUEFAPP_CRON_RUN_ID=' . escapeshellarg($runId) . ($debug ? ' PRUEFAPP_CRON_DEBUG=1' : '');
    passthru($environment . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($publicId), $exitCode);
    $after = BackgroundJobService::find($publicId);
    if ($after === null) {
        $log($label . ' ' . substr($publicId, 0, 12) . ' ist nach dem Arbeitsschritt nicht mehr auffindbar.', 'error');
        continue;
    }
    $level = $after['state'] === 'error' || $exitCode !== 0 ? 'error' : 'info';
    $log($label . ' ' . substr($publicId, 0, 12) . ': ' . (string) ($after['message'] ?: 'Arbeitsschritt beendet.') . ' (' . (int) $after['step'] . ' von ' . (int) $after['total'] . ').', $level, ['job_id' => $publicId, 'state' => $after['state'], 'current' => $after['step'], 'total' => $after['total']]);
}

$jobsRemaining = count(BackgroundJobService::pending(1000));
$log('Zusammenfassung: ' . $jobsStarted . ' Arbeitsabschnitt(e) ausgeführt, ' . $jobsRemaining . ' Aufgabe(n) noch offen, verbleibendes Zeitbudget ' . number_format($timeLeft(), 1, ',', '.') . ' Sekunden.');
$log('Cron beendet, Dauer ' . number_format(microtime(true) - $startedAt, 1, ',', '.') . ' Sekunden.');
$finished = true;
flock($lock, LOCK_UN);
fclose($lock);
