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
$log = static function (string $message, string $level = 'info') use ($logPath, $debug): void { $timestamp = date(DATE_ATOM); $line = '[' . $timestamp . '] ' . strtoupper($level) . ' ' . $message . PHP_EOL; $written = @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX); $isProblem = in_array(strtolower($level), ['warning', 'error', 'critical'], true); if ($debug || $isProblem) fwrite(STDERR, $line . ($written === false ? '[cron debug] Textlog konnte nicht geschrieben werden: ' . $logPath . PHP_EOL : '')); try { R::exec('INSERT INTO cron_log (run_at, level, message) VALUES (?, ?, ?)', [$timestamp, strtolower($level), $message]); } catch (Throwable $exception) { if ($debug || $isProblem) fwrite(STDERR, '[cron_log database] ' . $exception->getMessage() . PHP_EOL); } };
$lock = fopen($root . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    if ($debug) fwrite(STDERR, '[cron debug] Ein anderer Cron-Lauf ist noch aktiv; keine parallele Iteration gestartet.' . PHP_EOL);
    exit(0);
}
$log('Hintergrundlauf gestartet. Er wird automatisch innerhalb des verfügbaren Zeitfensters bearbeitet.');
$log('Debug: verbleibendes Zeitbudget ' . number_format($timeLeft(), 1, ',', '.') . ' Sekunden', 'debug');
file_put_contents($root . '/cron-heartbeat.json', json_encode(['last_run' => date(DATE_ATOM), 'pid' => getmypid(), 'time_limit_seconds' => 120], JSON_UNESCAPED_UNICODE), LOCK_EX);
$generatedReports = 0;
$migrationProcessedTotal = 0;
$phoenixRestoredTotal = 0;
$reportErrors = [];
$missingReportCount = 0;
$jobsStarted = 0;
$jobsRemaining = 0;
$cronFinished = false;
register_shutdown_function(static function () use (&$cronFinished, $log, $cronStartedAt, &$generatedReports, &$reportErrors, &$jobsStarted, &$jobsRemaining): void {
    if ($cronFinished) return;
    $lastError = error_get_last();
    if (is_array($lastError) && in_array((int) ($lastError['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log('Cron unerwartet beendet: ' . (string) ($lastError['message'] ?? 'unbekannter fataler Fehler'), 'error');
    }
    $log('Cron beendet (Shutdown), Dauer ' . number_format(microtime(true) - $cronStartedAt, 1, ',', '.') . ' Sekunden; Berichte ' . $generatedReports . ', Jobs gestartet ' . $jobsStarted . ', Jobs offen ' . $jobsRemaining . ', Fehler ' . count($reportErrors), 'warning');
});

// Optional one-time repair/reimport for Benning CSV/ODS data. Set the
// directory only for the migration run; the marker prevents repeated imports.
$migrationDirectory = trim((string) (getenv('PRUEFAPP_BENNING_REIMPORT_DIR') ?: (function_exists('config_value') ? (config_value('APP_BENNING_REIMPORT_DIRECTORY') ?: '') : '') ?: (function_exists('get_app_config') ? (get_app_config('benning_reimport_directory', '') ?: '') : '')));
$migrationMarker = app_data_root() . '/migration/benning-measurements-v3.done';
if (!is_file($migrationMarker)) {
    try {
        if ($timeLeft() <= 1) throw new RuntimeException('Zeitbudget vor der Nachmigration erreicht.');
        $log('Debug: starte Benning-Nachmigration.', 'debug');
        $stats = ['imported' => 0, 'updated' => 0, 'repaired' => 0, 'errors' => []];
        if ($migrationDirectory !== '' && is_dir($migrationDirectory)) {
            $migrationReports = trim((string) (getenv('PRUEFAPP_BENNING_REPORTS_DIR') ?: (function_exists('config_value') ? (config_value('APP_BENNING_REPORTS_DIRECTORY') ?: '') : '') ?: (function_exists('get_app_config') ? (get_app_config('benning_reports_directory', '') ?: '') : '')));
            $importStats = (new ElectricalInspectionImportService())->importDirectory($migrationDirectory, $migrationReports !== '' ? $migrationReports : null);
            $stats = array_merge($stats, ['imported' => $importStats['imported'] ?? 0, 'updated' => $importStats['updated'] ?? 0, 'errors' => $importStats['errors'] ?? []]);
        }
        foreach (R::findAll('inspection', " source_type = 'csv' ORDER BY id ") as $inspection) {
            if ($timeLeft() <= 2) { $log('Die Messdaten-Aufbereitung wird beim nächsten Hintergrundlauf fortgesetzt.', 'warning'); break; }
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

// Einmalige PDF-Migration: alle bereits abgeschlossenen Prüfungen werden mit
// dem aktuellen Einzelbericht-Layout neu gerendert. Der Cursor macht den Lauf
// über mehrere Cron-Iterationen hinweg sicher und wiederholbar.
$phoenixRestoreMarker = app_data_root() . '/migration/inspection-reports-phoenix-restore-v4.json';
if (!is_file($phoenixRestoreMarker)) {
    $phoenixRestored = 0;
    $phoenixUnresolved = 0;
    $legacyRoots = [];
    $configuredPhoenixReports = trim((string) (getenv('PRUEFAPP_PHOENIX_REPORTS_DIR') ?: (function_exists('config_value') ? (config_value('APP_PHOENIX_REPORTS_DIRECTORY') ?: '') : '') ?: (function_exists('get_app_config') ? (get_app_config('benning_reports_directory', '') ?: '') : '')));
    foreach ([$configuredPhoenixReports, '/var/www/berichte'] as $legacyCandidateRoot) {
        $resolvedRoot = $legacyCandidateRoot !== '' ? realpath($legacyCandidateRoot) : false;
        if ($resolvedRoot !== false && is_dir($resolvedRoot) && !in_array($resolvedRoot, $legacyRoots, true)) $legacyRoots[] = $resolvedRoot;
    }
    try {
        $phoenixRows = R::getAll("SELECT id, external_number, report_path FROM inspection
            WHERE result_status IN ('bestanden', 'durchgefallen', 'nicht bestanden')
              AND COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%'
            ORDER BY id ASC");
        foreach ($phoenixRows as $row) {
            if ($timeLeft() <= 4) { $log('Die Wiederherstellung der Original-PDFs wird beim nächsten Hintergrundlauf fortgesetzt.', 'warning'); break; }
            $target = app_data_root() . '/reports/current/' . (int) $row['id'] . '.pdf';
            $source = '';
            $relative = trim((string) ($row['report_path'] ?? ''));
            if ($relative !== '' && str_starts_with($relative, 'reports/') && !str_starts_with($relative, 'reports/current/')) {
                $candidate = app_data_root() . '/' . $relative;
                if (is_file($candidate)) $source = $candidate;
            }
            if ($source === '') {
                $number = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim((string) ($row['external_number'] ?? '')));
                foreach (glob(app_data_root() . '/reports/' . $number . '-*.pdf') ?: [] as $candidate) { if (is_file($candidate)) { $source = $candidate; break; } }
            }
            // Phoenix originals are also kept in the legacy report store. The
            // current-layout migration must never replace those PDFs.
            if ($source === '') {
                $number = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim((string) ($row['external_number'] ?? '')));
                $legacyNumber = preg_replace('/[-_]\d{2}$/', '', $number);
                foreach ($legacyRoots as $legacyRoot) {
                    if ($number === '') continue;
                    $legacyNames = array_values(array_unique([$number, $legacyNumber, explode('-', $number)[0]]));
                    $legacyCandidates = [];
                    foreach ($legacyNames as $legacyName) $legacyCandidates = array_merge($legacyCandidates, [$legacyRoot . '/' . $legacyName . '.pdf', $legacyRoot . '/' . $legacyName . '-24.pdf', $legacyRoot . '/' . $legacyName . '-25.pdf']);
                    foreach ($legacyCandidates as $candidate) {
                        if (is_file($candidate) && str_starts_with((string) @file_get_contents($candidate, false, null, 0, 4), '%PDF')) { $source = $candidate; break 2; }
                    }
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($legacyRoot, FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $candidateInfo) {
                        if (!$candidateInfo->isFile() || strtolower($candidateInfo->getExtension()) !== 'pdf') continue;
                        $candidateName = $candidateInfo->getFilename();
                        $matchesLegacyName = false;
                        foreach ($legacyNames as $legacyName) if ($candidateName === $legacyName . '.pdf' || str_starts_with($candidateName, $legacyName . '-')) { $matchesLegacyName = true; break; }
                        if ($matchesLegacyName && str_starts_with((string) @file_get_contents($candidateInfo->getPathname(), false, null, 0, 4), '%PDF')) { $source = $candidateInfo->getPathname(); break 2; }
                    }
                }
                if ($source === '' && $debug && $phoenixUnresolved < 3) {
                    $log('Debug: kein Phoenix-Original für Prüfnummer ' . (string) ($row['external_number'] ?? '—') . ' gefunden.', 'debug');
                }
            }
            if ($source === '') {
                $phoenixUnresolved++;
                continue;
            }
            if ($source !== $target) {
                if (!is_dir(dirname($target))) @mkdir(dirname($target), 0770, true);
                if (@copy($source, $target)) $phoenixRestored++;
            }
        }
        if ($timeLeft() > 4 && $phoenixUnresolved === 0 && count($phoenixRows) > 0) {
            if (!is_dir(dirname($phoenixRestoreMarker))) @mkdir(dirname($phoenixRestoreMarker), 0770, true);
            file_put_contents($phoenixRestoreMarker, json_encode(['completed_at' => date(DATE_ATOM), 'restored' => $phoenixRestored], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            if ($phoenixRestored > 0) $log('Phoenix-PDFs wiederhergestellt: ' . $phoenixRestored);
        } elseif ($phoenixUnresolved > 0) {
            $searched = $legacyRoots === [] ? 'kein konfiguriertes Quellverzeichnis vorhanden' : implode(', ', $legacyRoots);
            $log('Phoenix-Original-PDFs noch nicht vollständig gefunden (' . $phoenixUnresolved . ' offen). Gesucht wurde in: ' . $searched . '. Wiederherstellung bleibt vorgemerkt.', 'warning');
        }
        $phoenixRestoredTotal += $phoenixRestored;
    } catch (Throwable $exception) {
        $log('Phoenix-PDF-Wiederherstellung fehlgeschlagen: ' . $exception->getMessage(), 'error');
    }
}
$reportMigrationMarker = app_data_root() . '/migration/inspection-reports-v2.json';
if (!is_file($reportMigrationMarker) || (($reportMigrationState = json_decode((string) @file_get_contents($reportMigrationMarker), true))['completed'] ?? false) !== true) {
    try {
        $reportMigrationState = is_array($reportMigrationState ?? null) ? $reportMigrationState : [];
        $lastMigrationId = (int) ($reportMigrationState['last_id'] ?? 0);
        $migrationRows = R::getAll("SELECT i.id, i.external_number, i.device_id, c.id AS customer_id
            FROM inspection i
            JOIN device d ON d.id = i.device_id
            LEFT JOIN room r ON r.id = d.room_id
            LEFT JOIN floor f ON f.id = r.floor_id
            LEFT JOIN building b ON b.id = f.building_id
            LEFT JOIN site s ON s.id = b.site_id
            LEFT JOIN customer c ON c.id = s.customer_id
            WHERE i.id > ? AND i.result_status IN ('bestanden', 'durchgefallen', 'nicht bestanden')
              AND NOT (COALESCE(i.source_type, '') = 'json' AND COALESCE(i.raw_json, '') LIKE '%phoenix-sync%')
            ORDER BY i.id ASC LIMIT 250", [$lastMigrationId]);
        $migrationProcessed = 0;
        foreach ($migrationRows as $row) {
            if ($timeLeft() <= 4) { $log('Die PDF-Aufbereitung wird beim nächsten Hintergrundlauf fortgesetzt.', 'warning'); break; }
            try {
                $inspection = R::load('inspection', (int) $row['id']);
                $device = R::load('device', (int) $row['device_id']);
                if (!$inspection->id || !$device->id) throw new RuntimeException('Prüfung oder Gerät nicht gefunden.');
                $relative = 'reports/current/' . (int) $inspection->id . '.pdf';
                $path = app_data_root() . '/' . $relative;
                $pdf = ReportController::renderPdf(
                    ReportController::inspectionPdfRows($inspection, $device),
                    'Prüfbericht ' . (string) $inspection->external_number,
                    function_exists('get_report_branding') ? get_report_branding() : null
                );
                if (file_put_contents($path, $pdf, LOCK_EX) === false) throw new RuntimeException('PDF konnte nicht gespeichert werden.');
                if ((string) ($inspection->report_path ?? '') !== $relative) {
                    $inspection->report_path = $relative;
                    R::store($inspection);
                }
                $lastMigrationId = (int) $row['id']; $migrationProcessed++;
                $reportMigrationState = ['version' => 2, 'last_id' => $lastMigrationId, 'completed' => false, 'updated_at' => date(DATE_ATOM)];
                if (!is_dir(dirname($reportMigrationMarker))) @mkdir(dirname($reportMigrationMarker), 0770, true);
                file_put_contents($reportMigrationMarker, json_encode($reportMigrationState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            } catch (Throwable $exception) {
                $log('PDF-Migration Prüfung ' . ((int) ($row['id'] ?? 0)) . ' fehlgeschlagen: ' . $exception->getMessage(), 'error');
                break;
            }
        }
        if (count($migrationRows) === 0 || ($migrationProcessed === count($migrationRows) && count($migrationRows) < 250)) {
            $reportMigrationState = ['version' => 2, 'last_id' => $lastMigrationId, 'completed' => true, 'completed_at' => date(DATE_ATOM)];
            if (!is_dir(dirname($reportMigrationMarker))) @mkdir(dirname($reportMigrationMarker), 0770, true);
            file_put_contents($reportMigrationMarker, json_encode($reportMigrationState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $log('PDF-Aufbereitung abgeschlossen. Neu erzeugt: ' . $migrationProcessed . '.');
        } elseif ($migrationProcessed > 0) {
            $log('PDF-Aufbereitung fortgesetzt. In diesem Lauf neu erzeugt: ' . $migrationProcessed . '.');
        }
        $migrationProcessedTotal += $migrationProcessed;
    } catch (Throwable $exception) {
        $log('PDF-Migration fehlgeschlagen: ' . $exception->getMessage(), 'error');
    }
}

// Abgeschlossene Prüfungen bekommen auch dann automatisch einen Bericht,
// wenn sie nicht über das Webformular abgeschlossen wurden (z. B. Import).
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
    $missingReportCount = $reportTotal;
    $log('Debug: ' . $reportTotal . ' fehlende Prüfberichte gefunden.', 'debug');
    foreach ($missingReports as $row) {
        if ($timeLeft() <= 3) { $log('Die Erstellung weiterer Prüfberichte wird beim nächsten Hintergrundlauf fortgesetzt.', 'warning'); break; }
        $log('Debug: Bericht ' . ((int) $row['id']) . ' wird verarbeitet (' . ($generatedReports + 1) . '/' . $reportTotal . ').', 'debug');
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
                    function_exists('get_report_branding') ? get_report_branding() : null
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
$log('Berichtslauf: fehlende Prüfberichte ' . $generatedReports . '; Fehler ' . count($reportErrors) . '.');
foreach ($reportErrors as $error) $log('Berichtsfehler: ' . json_encode($error, JSON_UNESCAPED_UNICODE));

file_put_contents($root . '/report-heartbeat.json', json_encode([
    'last_run' => date(DATE_ATOM),
    'generated' => $generatedReports,
    'errors' => $reportErrors,
], JSON_UNESCAPED_UNICODE), LOCK_EX);

foreach (glob($root . '/*.status.json') ?: [] as $statusPath) {
    $candidate = json_decode((string) @file_get_contents($statusPath), true);
    if ($timeLeft() <= 8) { $log('Weitere Hintergrundaufgaben werden beim nächsten Hintergrundlauf fortgesetzt.', 'warning'); break; }
    $status = json_decode((string) file_get_contents($statusPath), true);
    if (!is_array($status) || ($status['state'] ?? '') !== 'queued') continue;
    $id = (string) ($status['id'] ?? basename($statusPath, '.status.json'));
    if (!preg_match('/^[a-f0-9]{24}$/', $id)) { $log('Debug: Hintergrundaufgabe übersprungen, ungültige ID ' . $id . '.', 'debug'); continue; }
    if (!is_file($root . '/' . $id . '.json')) { $log('Debug: Hintergrundaufgabe ' . substr($id, 0, 12) . ' übersprungen, Payload-Datei fehlt.', 'debug'); continue; }
    $log('Hintergrundaufgabe gestartet.');
    $jobsStarted++;
    // Give every queued job a slice so a large ZIP cannot monopolise the
    // complete Cron window. The worker persists its cursor and resumes later.
    $workerDeadline = (string) (microtime(true) + max(12.0, min(35.0, $timeLeft() - 3.0)));
    $workerDebug = $debug ? ' PRUEFAPP_CRON_DEBUG=1' : '';
    passthru('PRUEFAPP_CRON_DEADLINE=' . escapeshellarg($workerDeadline) . $workerDebug . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($id));
    $log('Hintergrundaufgabe beendet.');
}
$jobsRemaining = 0;
foreach (glob($root . '/*.status.json') ?: [] as $statusPath) {
    $status = json_decode((string) @file_get_contents($statusPath), true);
    if (is_array($status) && ($status['state'] ?? '') === 'queued') $jobsRemaining++;
}
$log('Zusammenfassung: fehlende Prüfberichte ' . $generatedReports . ', PDF-Aufbereitung ' . $migrationProcessedTotal . ', Original-PDFs wiederhergestellt ' . $phoenixRestoredTotal . ', Hintergrundaufgaben gestartet ' . $jobsStarted . ', noch offen ' . $jobsRemaining . ', Fehler ' . count($reportErrors) . '.');
if ($debug) {
    if ($generatedReports === 0 && $reportErrors === [] && $missingReportCount === 0 && $jobsStarted === 0 && $jobsRemaining === 0 && is_file($migrationMarker)) {
        fwrite(STDERR, '[cron debug] Keine offenen Aufgaben: keine fehlenden Prüfberichte, keine wartenden Hintergrundjobs, keine Nachmigration.' . PHP_EOL);
    } else {
        fwrite(STDERR, '[cron debug] Zusammenfassung: Berichte ' . $generatedReports . '/' . $missingReportCount . ', Jobs gestartet ' . $jobsStarted . ', Jobs noch offen ' . $jobsRemaining . ', Fehler ' . count($reportErrors) . '.' . PHP_EOL);
    }
}
$log('Cron beendet, Dauer ' . number_format(microtime(true) - $cronStartedAt, 1, ',', '.') . ' Sekunden');
$cronFinished = true;
flock($lock, LOCK_UN);
fclose($lock);
