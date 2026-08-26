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
        InspectionCompanionInboxService::cleanupExpired();
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
    $importsToReconcile = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE classification = 'migrated_import' AND (result_status = 'data_missing' OR external_number GLOB '*-[0-9][0-9]-[2-9]*')");
    // Version 8 also revisits imported duplicate numbers so a completed
    // import can replace an unfinished manual base row.  Once the pass has
    // completed, genuinely inconclusive imports are intentionally left open.
    if ($importReconciliationVersion !== '8') {
        if ($importsToReconcile > 0) {
            BackgroundJobService::enqueue(
                'import_result_reconciliation',
                ['type' => 'import_result_reconciliation'],
                ['total' => $importsToReconcile, 'dedupe_key' => 'maintenance:import-result-reconciliation:v8', 'cancellable' => false]
            );
        } else {
            set_app_config('import_result_reconciliation_version', '8');
        }
    }
    $duplicateAuditVersion = trim((string) get_app_config('inspection_duplicate_audit_version', ''));
    if ($duplicateAuditVersion !== '2') {
        $duplicateAuditTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> ''");
        if ($duplicateAuditTotal > 0) {
            BackgroundJobService::supersedePendingType('inspection_duplicate_audit', 'Eine präzisere Dublettenprüfung ersetzt diesen Lauf.');
            BackgroundJobService::enqueue(
                'inspection_duplicate_audit',
                ['type' => 'inspection_duplicate_audit'],
                ['total' => $duplicateAuditTotal, 'dedupe_key' => 'maintenance:inspection-duplicate-audit:v2', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_duplicate_audit_version', '2');
        }
    }
    $duplicateReviewCleanupVersion = trim((string) get_app_config('inspection_duplicate_review_cleanup_version', ''));
    if ($duplicateReviewCleanupVersion !== '1') {
        $duplicateReviewCleanupTotal = (int) R::getCell("SELECT COUNT(*) FROM inspectiondupreview review JOIN inspection earlier ON earlier.id=review.inspection_id JOIN inspection later ON later.id=review.peer_inspection_id WHERE review.status='open' AND (COALESCE(earlier.archived_at,'')<>'' OR COALESCE(later.archived_at,'')<>'')");
        if ($duplicateReviewCleanupTotal > 0) {
            BackgroundJobService::enqueue(
                'inspection_duplicate_review_cleanup',
                ['type' => 'inspection_duplicate_review_cleanup'],
                ['total' => $duplicateReviewCleanupTotal, 'dedupe_key' => 'maintenance:inspection-duplicate-review-cleanup:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_duplicate_review_cleanup_version', '1');
        }
    }
    // Explicit user confirmation: only this empty draft is eligible.  Future
    // confirmations must enqueue their own payload; similar numbers alone do
    // not authorize archival.
    $confirmedDraftArchiveVersion = trim((string) get_app_config('inspection_confirmed_draft_archive_100011436_version', ''));
    if ($confirmedDraftArchiveVersion !== '1') {
        $confirmedDraftExists = (int) R::getCell("SELECT COUNT(*) FROM inspection draft
            JOIN inspection canonical ON canonical.device_id=draft.device_id
              AND canonical.external_number='100011436-26-2-26' AND canonical.test_date='2026-08-12'
              AND canonical.source_type='manual' AND canonical.status='completed' AND COALESCE(canonical.archived_at,'')=''
            WHERE draft.external_number='100011436-26' AND draft.test_date='2026-08-12'
              AND draft.source_type='manual' AND draft.status='in_progress' AND COALESCE(draft.archived_at,'')='' ");
        if ($confirmedDraftExists > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_draft_archive',
                ['type' => 'inspection_confirmed_draft_archive', 'draft_number' => '100011436-26', 'canonical_number' => '100011436-26-2-26', 'test_date' => '2026-08-12'],
                ['total' => 1, 'dedupe_key' => 'maintenance:inspection-confirmed-draft-archive:100011436:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_draft_archive_100011436_version', '1');
        }
    }
    // Explicit user confirmation: retain the complete Phoenix originals for
    // these seven 2023 pairs and archive only their incomplete test2.csv rows.
    $confirmedLegacyCsvArchiveVersion = trim((string) get_app_config('inspection_confirmed_legacy_csv_archive_2023_version', ''));
    if ($confirmedLegacyCsvArchiveVersion !== '1') {
        $confirmedLegacyPairs = [
            ['csv_inspection_id' => 9621, 'phoenix_inspection_id' => 6943],
            ['csv_inspection_id' => 9619, 'phoenix_inspection_id' => 6946],
            ['csv_inspection_id' => 9616, 'phoenix_inspection_id' => 6947],
            ['csv_inspection_id' => 9614, 'phoenix_inspection_id' => 6938],
            ['csv_inspection_id' => 9612, 'phoenix_inspection_id' => 6935],
            ['csv_inspection_id' => 9608, 'phoenix_inspection_id' => 6921],
            ['csv_inspection_id' => 9607, 'phoenix_inspection_id' => 6920],
        ];
        $openConfirmedLegacyRows = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($confirmedLegacyPairs), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($confirmedLegacyPairs, 'csv_inspection_id'));
        if ($openConfirmedLegacyRows > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_legacy_csv_archive',
                ['type' => 'inspection_confirmed_legacy_csv_archive', 'pairs' => $confirmedLegacyPairs],
                ['total' => count($confirmedLegacyPairs), 'dedupe_key' => 'maintenance:inspection-confirmed-legacy-csv-archive:2023:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_legacy_csv_archive_2023_version', '1');
        }
    }
    // Explicit user confirmation: these records have the same device, source
    // file, test date, room and result. Keep the older import record only.
    $confirmedSameSourceArchiveVersion = trim((string) get_app_config('inspection_confirmed_same_source_archive_version', ''));
    if ($confirmedSameSourceArchiveVersion !== '1') {
        $confirmedSameSourcePairs = [
            ['canonical_inspection_id' => 9799, 'duplicate_inspection_id' => 9801],
            ['canonical_inspection_id' => 9003, 'duplicate_inspection_id' => 9036],
            ['canonical_inspection_id' => 8860, 'duplicate_inspection_id' => 8861],
            ['canonical_inspection_id' => 8538, 'duplicate_inspection_id' => 8542],
            ['canonical_inspection_id' => 7876, 'duplicate_inspection_id' => 7877],
        ];
        $openConfirmedSameSourceRows = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($confirmedSameSourcePairs), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($confirmedSameSourcePairs, 'duplicate_inspection_id'));
        if ($openConfirmedSameSourceRows > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_same_source_archive',
                ['type' => 'inspection_confirmed_same_source_archive', 'pairs' => $confirmedSameSourcePairs],
                ['total' => count($confirmedSameSourcePairs), 'dedupe_key' => 'maintenance:inspection-confirmed-same-source-archive:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_same_source_archive_version', '1');
        }
    }
    // Explicit user confirmation after source-row review: these older imports
    // share only an export-local Speicher Nr. with the newer record. Restore
    // their original device identity rather than archive a valid inspection.
    $historicalDeviceRepairVersion = trim((string) get_app_config('inspection_confirmed_historical_device_repair_version', ''));
    if ($historicalDeviceRepairVersion !== '1') {
        $historicalDeviceRepairs = [
            ['inspection_id' => 7984, 'inspection_number' => '100008899-26', 'source_device_number' => '100008899', 'source_type' => 'json', 'source_file' => 'phoenix-sync-56tuus1h0p1m6IFf6yc.jsonl', 'test_date' => '2026-03-28'],
            ['inspection_id' => 9148, 'inspection_number' => '100008898-26', 'source_device_number' => '100008898', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-28'],
            ['inspection_id' => 9147, 'inspection_number' => '100008897-26', 'source_device_number' => '100008897', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-28'],
            ['inspection_id' => 9146, 'inspection_number' => '100008896-26', 'source_device_number' => '100008896', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-28'],
            ['inspection_id' => 9144, 'inspection_number' => '100008894-26', 'source_device_number' => '100008894', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-28'],
            ['inspection_id' => 8885, 'inspection_number' => '100018988-26', 'source_device_number' => '100018988', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_04_02.csv', 'test_date' => '2026-04-02'],
            ['inspection_id' => 9285, 'inspection_number' => '100018936-26', 'source_device_number' => '100018936', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9272, 'inspection_number' => '100018923-26', 'source_device_number' => '100018923', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9271, 'inspection_number' => '100018922-26', 'source_device_number' => '100018922', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9268, 'inspection_number' => '100018919-26', 'source_device_number' => '100018919', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9265, 'inspection_number' => '100018916-26', 'source_device_number' => '100018916', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9261, 'inspection_number' => '100018912-26', 'source_device_number' => '100018912', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9237, 'inspection_number' => '100009489-26', 'source_device_number' => '100009489', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-30'],
            ['inspection_id' => 9162, 'inspection_number' => '100009412-26', 'source_device_number' => '100009412', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_03_28.csv', 'test_date' => '2026-03-28'],
            ['inspection_id' => 8769, 'inspection_number' => '100009273-25', 'source_device_number' => '100009273', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-25_08_14.csv', 'test_date' => '2025-08-14'],
        ];
        $openHistoricalDeviceRepairs = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($historicalDeviceRepairs), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($historicalDeviceRepairs, 'inspection_id'));
        if ($openHistoricalDeviceRepairs > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_historical_device_repair',
                ['type' => 'inspection_confirmed_historical_device_repair', 'repairs' => $historicalDeviceRepairs],
                ['total' => count($historicalDeviceRepairs), 'dedupe_key' => 'maintenance:inspection-confirmed-historical-device-repair:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_historical_device_repair_version', '1');
        }
    }
    // Explicit user confirmation: the two Heizgerät and two Bildschirm rows
    // below have distinct source slots and must not share a device. There is
    // no durable source number, so each receives an audit-labelled historical
    // identifier instead of overwriting a current device.
    $historicalDeviceSplitVersion = trim((string) get_app_config('inspection_confirmed_historical_device_split_version', ''));
    if ($historicalDeviceSplitVersion !== '1') {
        $historicalDeviceSplits = [
            ['inspection_id' => 8936, 'inspection_number' => '--26', 'source_device_number' => 'HIST-HEIZ-054-20260402', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_04_02.csv', 'test_date' => '2026-04-02'],
            ['inspection_id' => 10047, 'inspection_number' => '--26-2', 'source_device_number' => 'HIST-HEIZ-079-20260806', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_08_03.csv', 'test_date' => '2026-08-06'],
            ['inspection_id' => 9916, 'inspection_number' => '100012560-26', 'source_device_number' => 'HIST-100012560-S004', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_08_03.csv', 'test_date' => '2026-08-03'],
            ['inspection_id' => 9973, 'inspection_number' => '100012560-26-3', 'source_device_number' => 'HIST-100012560-S005', 'source_type' => 'csv', 'source_file' => 'AK_Elektro-26_08_03.csv', 'test_date' => '2026-08-03'],
        ];
        $openHistoricalDeviceSplits = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($historicalDeviceSplits), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($historicalDeviceSplits, 'inspection_id'));
        if ($openHistoricalDeviceSplits > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_historical_device_split',
                ['type' => 'inspection_confirmed_historical_device_split', 'repairs' => $historicalDeviceSplits, 'completion_config_key' => 'inspection_confirmed_historical_device_split_version'],
                ['total' => count($historicalDeviceSplits), 'dedupe_key' => 'maintenance:inspection-confirmed-historical-device-split:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_historical_device_split_version', '1');
        }
    }
    // Explicit user confirmation: retain the manual Lötkolben inspections
    // dated 04.08.2026 and merge only their later data-missing CSV mirrors.
    $confirmedCsvManualMergeVersion = trim((string) get_app_config('inspection_confirmed_csv_manual_merge_version', ''));
    if ($confirmedCsvManualMergeVersion !== '1') {
        $confirmedCsvManualPairs = [
            ['csv_inspection_id' => 9432, 'manual_inspection_id' => 9435, 'manual_test_date' => '2026-08-04'],
            ['csv_inspection_id' => 9433, 'manual_inspection_id' => 9434, 'manual_test_date' => '2026-08-04'],
        ];
        $openConfirmedCsvRows = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($confirmedCsvManualPairs), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($confirmedCsvManualPairs, 'csv_inspection_id'));
        if ($openConfirmedCsvRows > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_csv_manual_merge',
                ['type' => 'inspection_confirmed_csv_manual_merge', 'pairs' => $confirmedCsvManualPairs],
                ['total' => count($confirmedCsvManualPairs), 'dedupe_key' => 'maintenance:inspection-confirmed-csv-manual-merge:loetkolben:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_csv_manual_merge_version', '1');
        }
    }
    // Explicit user confirmation: the active manual Lötkolben records are
    // canonical after merging, so remove their import-collision suffix.
    $confirmedNumberRestoreVersion = trim((string) get_app_config('inspection_confirmed_number_restore_version', ''));
    if ($confirmedNumberRestoreVersion !== '1') {
        $confirmedNumberRestorePairs = [
            ['manual_inspection_id' => 9435, 'archived_csv_inspection_id' => 9432, 'current_number' => '100012587-26-2-26', 'canonical_number' => '100012587-26'],
            ['manual_inspection_id' => 9434, 'archived_csv_inspection_id' => 9433, 'current_number' => '100012586-26-2-26', 'canonical_number' => '100012586-26'],
        ];
        $openNumberRestoreRows = (int) R::getCell('SELECT COUNT(*) FROM inspection WHERE id IN (' . implode(',', array_fill(0, count($confirmedNumberRestorePairs), '?')) . ") AND COALESCE(archived_at,'')=''", array_column($confirmedNumberRestorePairs, 'manual_inspection_id'));
        if ($openNumberRestoreRows > 0) {
            BackgroundJobService::enqueue(
                'inspection_confirmed_number_restore',
                ['type' => 'inspection_confirmed_number_restore', 'pairs' => $confirmedNumberRestorePairs],
                ['total' => count($confirmedNumberRestorePairs), 'dedupe_key' => 'maintenance:inspection-confirmed-number-restore:loetkolben:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_confirmed_number_restore_version', '1');
        }
    }
    // Restore only facts explicitly present in the immutable CSV source rows
    // before identifying mirrors. Otherwise a former RPE fallback or a
    // shifted import date makes an identical CSV/JSON pair look different.
    $csvFactReconciliationVersion = trim((string) get_app_config('csv_source_fact_reconciliation_version', ''));
    if ($csvFactReconciliationVersion !== '1') {
        $csvFactTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='csv' AND COALESCE(archived_at,'')='' ");
        if ($csvFactTotal > 0) {
            BackgroundJobService::enqueue(
                'csv_source_fact_reconciliation',
                ['type' => 'csv_source_fact_reconciliation'],
                ['total' => $csvFactTotal, 'dedupe_key' => 'maintenance:csv-source-fact-reconciliation:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('csv_source_fact_reconciliation_version', '1');
        }
    }
    // Once the read-only audit and source-fact repair have completed,
    // unequivocal import copies are
    // archived: a Phoenix JSON mirror of a matching Benning CSV or an import
    // suffix from the same source file.  The inspection and invoice-item
    // history remain intact; only the active operational/billing allocation
    // is released.  Ambiguous short-interval repeats stay for manual review.
    $duplicateArchiveVersion = trim((string) get_app_config('inspection_duplicate_archive_version', ''));
    if ($duplicateArchiveVersion !== '5' && $duplicateAuditVersion === '2' && $csvFactReconciliationVersion === '1') {
        $duplicateArchiveTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection later
            WHERE (
                (later.source_type='json' AND EXISTS (SELECT 1 FROM inspection canonical
                    WHERE canonical.device_id=later.device_id
                      AND canonical.source_type='csv'
                      AND canonical.external_number=later.external_number
                      AND canonical.test_date=later.test_date
                      AND COALESCE(canonical.result_status,'')=COALESCE(later.result_status,'')
                      AND COALESCE(canonical.archived_at,'')=''))
                OR
                (SUBSTRING_INDEX(later.external_number, '-', -1) REGEXP '^[2-9][0-9]*$'
                 AND EXISTS (SELECT 1 FROM inspection canonical
                    WHERE canonical.device_id=later.device_id
                      AND canonical.source_type=later.source_type
                      AND COALESCE(canonical.source_file,'')=COALESCE(later.source_file,'')
                      AND canonical.external_number=LEFT(later.external_number, LENGTH(later.external_number)-LENGTH(SUBSTRING_INDEX(later.external_number, '-', -1))-1)
                      AND canonical.test_date=later.test_date
                      AND COALESCE(canonical.result_status,'')=COALESCE(later.result_status,'')
                      AND COALESCE(canonical.archived_at,'')=''))
            )
              AND TRIM(COALESCE(later.source_file,''))<>''
              AND TRIM(COALESCE(later.external_number,''))<>''
              AND TRIM(COALESCE(later.test_date,''))<>''
              AND COALESCE(later.archived_at,'')='' ");
        if ($duplicateArchiveTotal > 0) {
            BackgroundJobService::enqueue(
                'inspection_duplicate_archive',
                ['type' => 'inspection_duplicate_archive'],
                ['total' => $duplicateArchiveTotal, 'dedupe_key' => 'maintenance:inspection-duplicate-archive:v5', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_duplicate_archive_version', '5');
        }
    }
    // A separate, even narrower pass handles the old year-suffix defect:
    // only byte-identical CSV rows from the same source file/device qualify.
    $csvSourceDuplicateArchiveVersion = trim((string) get_app_config('inspection_csv_source_duplicate_archive_version', ''));
    if ($csvSourceDuplicateArchiveVersion !== '1' && trim((string) get_app_config('inspection_duplicate_archive_version', '')) === '5') {
        $csvSourceDuplicateTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection i JOIN inspection_source_snapshot s ON s.inspection_id=i.id WHERE i.source_type='csv' AND COALESCE(i.archived_at,'')='' AND TRIM(COALESCE(i.source_file,''))<>'' AND TRIM(COALESCE(s.source_row_json,''))<>''");
        if ($csvSourceDuplicateTotal > 0) {
            BackgroundJobService::enqueue(
                'inspection_csv_source_duplicate_archive',
                ['type' => 'inspection_csv_source_duplicate_archive'],
                ['total' => $csvSourceDuplicateTotal, 'dedupe_key' => 'maintenance:inspection-csv-source-duplicate-archive:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_csv_source_duplicate_archive_version', '1');
        }
    }
    // The original broad import-deduplication pass predates CSV fact repair.
    // Run this separate row-wise pass afterwards so an exactly matching
    // Phoenix JSON mirror cannot survive merely because its former import
    // date/result had been repaired in the meantime.
    $jsonCsvMirrorArchiveVersion = trim((string) get_app_config('inspection_json_csv_mirror_archive_version', ''));
    if ($jsonCsvMirrorArchiveVersion !== '1' && $csvFactReconciliationVersion === '1') {
        $jsonCsvMirrorTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='json' AND COALESCE(archived_at,'')='' ");
        if ($jsonCsvMirrorTotal > 0) {
            BackgroundJobService::enqueue(
                'inspection_json_csv_mirror_archive',
                ['type' => 'inspection_json_csv_mirror_archive'],
                ['total' => $jsonCsvMirrorTotal, 'dedupe_key' => 'maintenance:inspection-json-csv-mirror-archive:v1', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_json_csv_mirror_archive_version', '1');
        }
    }
    // A manual inspection left in progress can be an abandoned entry when a
    // completed CSV import with the same base number follows shortly after.
    // The CSV record is kept as the factual result and retains its CSV date.
    $manualCsvConsolidationVersion = trim((string) get_app_config('inspection_manual_csv_consolidation_version', ''));
    if ($manualCsvConsolidationVersion !== '3' && $csvFactReconciliationVersion === '1') {
        $manualCsvTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='manual' AND status IN ('in_progress','data_missing') AND COALESCE(archived_at,'')='' AND TRIM(COALESCE(external_number,''))<>''");
        if ($manualCsvTotal > 0) {
            BackgroundJobService::enqueue(
                'inspection_manual_csv_consolidation',
                ['type' => 'inspection_manual_csv_consolidation'],
                ['total' => $manualCsvTotal, 'dedupe_key' => 'maintenance:inspection-manual-csv-consolidation:v3', 'cancellable' => false]
            );
        } else {
            set_app_config('inspection_manual_csv_consolidation_version', '3');
        }
    }
    $inspectionDataMigrationVersion = trim((string) get_app_config('inspection_data_migration_version', ''));
    if (get_app_config('device_vocabulary_normalization_version', '') !== '1') {
        $total = (int) R::getCell('SELECT COUNT(*) FROM device');
        if ($total > 0) {
            BackgroundJobService::enqueue('vocabulary_normalization', ['type' => 'vocabulary_normalization'], [
                'total' => $total,
                'dedupe_key' => 'maintenance:device-vocabulary:v1',
                'cancellable' => false,
            ]);
        } else {
            set_app_config('device_vocabulary_normalization_version', '1');
        }
    }
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
    // The configured GUI path remains the only source; no hidden path is
    // introduced by the cron job. Version 7 is a complete, source-preserving
    // re-import: it records every CSV/JSONL row on its inspection, keeps the
    // current device master data intact and makes Phoenix source PDFs active.
    // It deliberately runs once after deployment even when no Regie field is
    // present, because the source/asset migration is independent of Regie.
    $benningImportVersion = '7';
    $benningImportCompleted = (string) get_app_config('benning_import_regie_reimport_version', '') === $benningImportVersion;
    if ($benningDirectory !== '' && is_dir($benningDirectory) && !$benningImportCompleted) {
        // A report generated before the source-preserving import may have
        // replaced a Phoenix original. Stop it once; a complete report run is
        // queued only after the import has finished.
        $supersededReports = BackgroundJobService::supersedePendingType('all_report_regeneration', 'Wartet auf den vollständigen Quellen-Reimport.');
        if ($supersededReports > 0) $log($supersededReports . ' Berichtslauf/-läufe warten auf den vollständigen Quellen-Reimport.', 'info');
        $reportsDirectory = trim((string) (get_app_config('benning_reports_directory', '') ?: getenv('PRUEFAPP_BENNING_REPORTS_DIR')));
        BackgroundJobService::enqueue('directory_import', [
            'type' => 'directory_import',
            'directory' => $benningDirectory,
            'reports_directory' => $reportsDirectory,
            'completion_config_key' => 'benning_import_regie_reimport_version',
            'completion_config_value' => $benningImportVersion,
        ], ['dedupe_key' => 'maintenance:inspection-source-reimport:v7', 'cancellable' => false]);
    } elseif ($benningDirectory === '' || !is_dir($benningDirectory)) {
        $log('Quellen-Reimport wartet: Das in der Importverwaltung hinterlegte Importverzeichnis ist nicht verfügbar.', 'warning', ['directory' => $benningDirectory]);
    }
    $allReportsVersion = '4';
    $allReportsCompleted = (string) get_app_config('benning_import_regie_reports_version', '') === $allReportsVersion;
    if ($benningImportCompleted && !$allReportsCompleted) {
        $eligibleReports = "result_status IN ('passed','failed') AND COALESCE(classification, '') <> 'legacy' AND " . inspection_report_signature_sql('inspection');
        $reportTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligibleReports}");
        if ($reportTotal > 0) {
            $replacementAlreadyRunning = false;
            foreach (BackgroundJobService::pending(1000) as $reportJob) {
                if ((string) ($reportJob['type'] ?? '') !== 'all_report_regeneration') continue;
                $payload = (array) ($reportJob['payload'] ?? []);
                if ((string) ($payload['completion_config_key'] ?? '') === 'benning_import_regie_reports_version'
                    && (string) ($payload['completion_config_value'] ?? '') === $allReportsVersion
                ) {
                    $replacementAlreadyRunning = true;
                    break;
                }
            }
            // Only replace an older run once. Otherwise every cron tick would
            // cancel the freshly queued successor before it can make progress.
            if (!$replacementAlreadyRunning) {
                $supersededReports = BackgroundJobService::supersedePendingType('all_report_regeneration', 'Wird durch einen neueren vollständigen Berichtslauf ersetzt.');
                if ($supersededReports > 0) $log($supersededReports . ' älterer Berichtslauf/-läufe werden durch den aktuellen Datenabgleich ersetzt.', 'info');
                BackgroundJobService::enqueue('all_report_regeneration', [
                    'type' => 'all_report_regeneration',
                    'completion_config_key' => 'benning_import_regie_reports_version',
                    'completion_config_value' => $allReportsVersion,
                ], [
                    'total' => $reportTotal,
                    'dedupe_key' => 'maintenance:all-reports-after-source-reimport:v4',
                    'cancellable' => true,
                ]);
            }
        } else {
            set_app_config('benning_import_regie_reports_version', $allReportsVersion);
        }
    }
    // Report jobs must only see canonical data. This gate also prevents the
    // older JSON/measurement maintenance paths from racing the new migration.
    if ($inspectionDataMigrationVersion === '1') {
        // v6 promotes every available Phoenix JSON source PDF, not only
        // records classified as legacy, to the authoritative active report.
        $legacyRestoreMarker = $migrationRoot . '/inspection-reports-original-restore-v6.json';
        if (!is_file($legacyRestoreMarker)) {
            $legacyTotal = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type = 'json' AND result_status IN ('passed','failed')");
            if ($legacyTotal > 0) {
                BackgroundJobService::enqueue(
                    'phoenix_pdf_restore',
                    ['type' => 'phoenix_pdf_restore'],
                    ['total' => $legacyTotal, 'dedupe_key' => 'maintenance:source-originals:v6', 'cancellable' => false]
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
