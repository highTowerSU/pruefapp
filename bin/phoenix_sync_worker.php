#!/usr/bin/env php
<?php
declare(strict_types=1);

use RedBeanPHP\R as R;
use Ceneos\PhpBase\Jobs\JobQueue;
$configOverride = trim((string) getenv('PRUEFAPP_CONFIG_FILE'));
if ($configOverride !== '' && !defined('CENEOS_CONFIG_FILE')) define('CENEOS_CONFIG_FILE', $configOverride);
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';

$id = preg_replace('/[^a-f0-9]/', '', (string) ($argv[1] ?? ''));
$deadline = (float) (getenv('PRUEFAPP_CRON_DEADLINE') ?: 0);
$debug = getenv('PRUEFAPP_CRON_DEBUG') === '1';
$cronRunId = trim((string) getenv('PRUEFAPP_CRON_RUN_ID'));
$debugLog = static function (string $message, array $context = []) use ($cronRunId): void {
    try {
        R::exec('INSERT INTO cron_log (run_at, level, message, run_id, context_json) VALUES (?, ?, ?, ?, ?)', [date(DATE_ATOM), 'debug', $message, $cronRunId, json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}']);
    } catch (Throwable) {
        // Progress logging must never interrupt the actual unit of work.
    }
};
$job = $id !== '' ? JobQueue::getByPublicId($id) : null;
if ($job === null || !in_array((string) $job['status'], ['running', 'cancel_requested'], true)) exit(2);
$jobId = (int) $job['id'];
$workerId = (string) ($job['worker_id'] ?? '');
$payload = (array) ($job['payload'] ?? []);
$ownerUserId = (int) ($job['owner_user_id'] ?? 0);
$statusInitial = [
    'step' => (int) ($job['current'] ?? 0),
    'total' => (int) ($job['total'] ?? 0),
    'current_device' => (string) (($job['checkpoint']['current_device'] ?? '')),
    'message' => (string) ($job['message'] ?? ''),
];
$resolveReportSource = static function (string $storedPath): string {
    $storedPath = trim($storedPath);
    if ($storedPath === '') return '';
    $candidates = [$storedPath];
    if (!str_starts_with($storedPath, '/')) $candidates[] = app_data_root() . '/' . ltrim($storedPath, '/');
    $candidates[] = '/var/www/berichte/' . basename($storedPath);
    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) return $candidate;
    }
    return '';
};
$writeStatus = static function (array $extra) use ($jobId, $workerId, $job): void {
    $latest = JobQueue::get($jobId) ?? $job;
    $checkpoint = (array) ($latest['checkpoint'] ?? []);
    if (isset($extra['current_device'])) $checkpoint['current_device'] = (string) $extra['current_device'];
    $checkpoint['next_index'] = max(0, (int) ($extra['step'] ?? $latest['current'] ?? 0));
    JobQueue::checkpoint(
        $jobId,
        $checkpoint,
        max(0, (int) ($extra['step'] ?? $latest['current'] ?? 0)),
        max(0, (int) ($extra['total'] ?? $latest['total'] ?? 0)),
        (string) ($extra['message'] ?? $latest['message'] ?? ''),
        $workerId,
        180
    );
};
try {
    $progress = static function (int $step, int $total, string $number, string $message) use ($writeStatus, $jobId, $deadline, $debug, $debugLog): void {
        $writeStatus(['step' => $step, 'total' => $total, 'current_device' => $number, 'message' => $message]);
        $debugLog('Aufgabenfortschritt: ' . $step . ' von ' . $total . ($number !== '' ? ' · ' . $number : '') . ' · ' . $message, ['job_id' => $jobId, 'current' => $step, 'total' => $total, 'record' => $number]);
        if ($debug) fwrite(STDERR, '[worker debug] ' . $step . '/' . $total . ' ' . ($number !== '' ? $number . ' · ' : '') . $message . PHP_EOL);
        if (JobQueue::cancellationRequested($jobId)) throw new RuntimeException('__JOB_CANCELLED__');
        if ($deadline > 0 && microtime(true) >= $deadline) throw new RuntimeException('__CRON_TIME_LIMIT__');
    };
    $maintenanceTypes = ['missing_reports', 'report_migration', 'all_report_regeneration', 'phoenix_pdf_restore', 'measurement_migration', 'inspection_data_migration', 'legacy_classification_migration', 'import_result_reconciliation', 'inspection_duplicate_audit', 'inspection_duplicate_archive', 'inspection_manual_csv_consolidation', 'vocabulary_suggestion', 'vocabulary_review_scan', 'vocabulary_normalization'];
    if (in_array((string) ($payload['type'] ?? ''), $maintenanceTypes, true)) {
        $tick = static function (array $checkpoint, int $step, int $total, string $number, string $message) use ($jobId, $workerId, $deadline, $debug, $debugLog): void {
            JobQueue::checkpoint($jobId, $checkpoint + ['current_device' => $number, 'next_index' => $step], $step, $total, $message, $workerId, 180);
            $debugLog('Aufgabenfortschritt: ' . $step . ' von ' . $total . ($number !== '' ? ' · ' . $number : '') . ' · ' . $message, ['job_id' => $jobId, 'current' => $step, 'total' => $total, 'record' => $number]);
            if ($debug) fwrite(STDERR, '[worker debug] ' . $step . '/' . $total . ' ' . ($number !== '' ? $number . ' · ' : '') . $message . PHP_EOL);
            if (JobQueue::cancellationRequested($jobId)) throw new RuntimeException('__JOB_CANCELLED__');
            if ($deadline > 0 && microtime(true) >= $deadline) throw new RuntimeException('__CRON_TIME_LIMIT__');
        };
        $stats = MaintenanceJobHandler::run($job, $tick);
        BackgroundJobService::complete($jobId, ['stats' => $stats], BackgroundJobService::label((string) $payload['type']) . ' abgeschlossen.');
        exit(0);
    } elseif (($payload['type'] ?? '') === 'inspection_pdf_zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZIP-Export ist auf diesem Server nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($payload['inspection_ids'] ?? [])),
            static fn(int $value): bool => $value > 0
        )));
        if ($ids === []) throw new RuntimeException('Keine Prüfungen für den Export ausgewählt.');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getAll(
            "SELECT i.id, i.external_number, i.test_date, i.report_path, i.classification, i.result_status, d.external_number AS device_number, d.name AS device_name FROM inspection i JOIN device d ON d.id=i.device_id WHERE i.id IN ($marks) AND i.result_status IN ('passed','failed') ORDER BY i.test_date DESC, i.id DESC",
            $ids
        );
        if ($rows === []) throw new RuntimeException('Die Auswahl enthält keine freigegebenen Prüfberichte.');
        $outDir = app_data_root() . '/exports';
        if (!is_dir($outDir)) mkdir($outDir, 0770, true);
        $zipPath = $outDir . '/pruefberichte-auswahl-' . $id . '.zip';
        $step = min((int) ($statusInitial['step'] ?? 0), count($rows));
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ($step > 0 ? 0 : ZipArchive::OVERWRITE)) !== true) {
            throw new RuntimeException('ZIP-Datei konnte nicht angelegt werden.');
        }
        foreach (array_slice($rows, $step) as $row) {
            $source = trim((string) ($row['report_path'] ?? ''));
            if ($source !== '' && !str_starts_with($source, '/')) $source = app_data_root() . '/' . ltrim($source, '/');
            if (!is_file($source) && trim((string) ($row['report_path'] ?? '')) !== '') {
                $archive = '/var/www/berichte/' . basename((string) $row['report_path']);
                if (is_file($archive)) $source = $archive;
            }
            if (is_file($source)) {
                $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['device_number'] . '-' . (string) $row['external_number']) . '.pdf';
                $zip->addFile($source, 'Pruefberichte/' . $filename);
                if (method_exists($zip, 'setCompressionName')) $zip->setCompressionName('Pruefberichte/' . $filename, ZipArchive::CM_STORE);
                $message = 'Prüfbericht wurde dem Download hinzugefügt.';
            } else {
                $message = 'Prüfbericht fehlt und wurde übersprungen.';
            }
            $step++;
            $progress($step, count($rows), (string) ($row['external_number'] ?? ''), $message);
        }
        $zip->close();
        BackgroundJobService::complete(
            $jobId,
            ['stats' => ['selected' => count($rows), 'files' => $step], 'output' => $zipPath],
            'Die ausgewählten Prüfberichte stehen zum Download bereit.'
        );
        exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_regenerate') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['inspection_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $total = count($ids); $step = min((int) ($statusInitial['step'] ?? 0), $total);
        foreach (array_slice($ids, $step) as $inspectionId) {
            $inspection = R::load('inspection', $inspectionId); $device = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
            $eligible = $inspection->id
                && $device
                && $device->id
                && (string) ($inspection->classification ?? '') !== 'legacy'
                && InspectionEvaluationService::reportAllowed((string) $inspection->result_status, (string) $inspection->classification)
                && examiner_has_report_signature((string) $inspection->examiner);
            if ($eligible) {
                $relative = 'reports/current/' . $inspectionId . '.pdf'; $path = app_data_root() . '/' . $relative; if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
                file_put_contents($path, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_report_branding') ? get_report_branding() : null), LOCK_EX); $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection);
                InspectionDataService::registerReportAsset((int) $inspection->id, 'generated', $path, true);
            }
            $step++; $progress($step, $total, (string) ($device->external_number ?? ''), $eligible ? 'Prüfbericht wurde neu erzeugt.' : 'Prüfung ohne Freigabe oder Prüfer-Unterschrift wurde übersprungen.');
        }
        BackgroundJobService::complete($jobId, ['stats' => ['reports' => $step]], $step . ' Prüfberichte wurden neu erzeugt.'); exit(0);
    } elseif (($payload['type'] ?? '') === 'examiner_migration') {
        $rows = R::getAll("SELECT id, test_date, source_type FROM inspection WHERE test_date IS NOT NULL AND COALESCE(source_type, '') IN ('json', 'csv') ORDER BY id");
        $total = count($rows); $step = min((int) ($statusInitial['step'] ?? 0), $total);
        foreach (array_slice($rows, $step) as $row) {
            $year = (int) substr(trim((string) ($row['test_date'] ?? '')), 0, 4);
            $target = in_array($year, [2023, 2024], true) ? 'bdebertshaeuser@koenigsbl.au' : ($year >= 2025 ? 'edebertshaeuser@koenigsbl.au' : '');
            if ($target !== '') {
                $inspection = R::load('inspection', (int) $row['id']);
                if ($inspection->id) { $inspection->examiner = $target; if ((string) ($row['source_type'] ?? '') === 'csv') $inspection->report_path = ''; $inspection->updated_at = date(DATE_ATOM); R::store($inspection); }
            }
            $step++; $progress($step, $total, (string) ($row['id'] ?? ''), 'Prüferzuordnung wird korrigiert');
        }
        $bea = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE examiner = ? AND CAST(SUBSTR(test_date, 1, 4) AS INTEGER) IN (2023, 2024) AND COALESCE(source_type, '') IN ('json','csv')", ['bdebertshaeuser@koenigsbl.au']);
        $eandro = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE examiner = ? AND CAST(SUBSTR(test_date, 1, 4) AS INTEGER) >= 2025 AND COALESCE(source_type, '') IN ('json','csv')", ['edebertshaeuser@koenigsbl.au']);
        BackgroundJobService::complete($jobId, ['stats' => ['processed' => $step, 'bea_2023_2024' => $bea, 'eandro_ab_2025' => $eandro]], $step . ' Prüferzuordnungen wurden geprüft.'); exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_bundle') {
        if (!function_exists('shell_exec')) throw new RuntimeException('PDF-Zusammenführung ist auf diesem Server nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        if ($ids === []) throw new RuntimeException('Keine Geräte für die Sammel-PDF.');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statusExpression = InspectionEvaluationService::sqlStatusExpression('i');
        $rows = R::getAll("SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, i.classification, i.result_status, i.status, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) AND {$statusExpression} IN ('passed','failed') ORDER BY c.name, b.name, f.sort_order, f.name, r.number, d.external_number, i.test_date DESC, i.id DESC", $ids);
        $files = [];
        foreach ($rows as $row) {
            $source = $resolveReportSource((string) $row['report_path']);
            $pathAllowed = InspectionEvaluationService::reportPathAllowed(
                (string) $row['result_status'],
                (string) $row['classification'],
                (string) $row['report_path']
            );
            if (!$pathAllowed && (string) $row['classification'] !== 'legacy') {
                $inspection = R::load('inspection', (int) $row['id']);
                $device = R::load('device', (int) $row['device_id']);
                if (!examiner_has_report_signature((string) $inspection->examiner)) continue;
                $relative = 'reports/current/' . (int) $inspection->id . '.pdf';
                $source = app_data_root() . '/' . $relative;
                if (!is_dir(dirname($source))) mkdir(dirname($source), 0770, true);
                file_put_contents($source, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_report_branding') ? get_report_branding() : null), LOCK_EX);
                $inspection->report_path = $relative;
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
                InspectionDataService::registerReportAsset((int) $inspection->id, 'generated', $source, true);
            }
            if (!is_file($source)) continue;
            $info = shell_exec('pdfinfo ' . escapeshellarg($source) . ' 2>/dev/null'); preg_match('/^Pages:\s+(\d+)/mi', (string) $info, $match); $pages = max(1, (int) ($match[1] ?? 1)); $row['pages'] = $pages; $row['source'] = $source; $files[] = $row;
        }
        if ($files === []) throw new RuntimeException('Keine fertigen Prüfberichte gefunden.');
        $maxPages = max(10, (int) ($payload['max_pages'] ?? 500)); $parts = []; $current = []; $currentPages = 0;
        foreach ($files as $row) { if ($current !== [] && $currentPages + (int) $row['pages'] + 2 > $maxPages) { $parts[] = $current; $current = []; $currentPages = 0; } $current[] = $row; $currentPages += (int) $row['pages']; }
        if ($current !== []) $parts[] = $current;
        $outDir = app_data_root() . '/exports/sammelpdf-' . $id; if (!is_dir($outDir)) mkdir($outDir, 0770, true);
        $partStart = min((int) ($statusInitial['step'] ?? 0), count($parts));
        $outputs = [];
        for ($existingPart = 0; $existingPart < $partStart; $existingPart++) {
            $existingOutput = $outDir . '/Pruefberichte-' . sprintf('%03d', $existingPart + 1) . '.pdf';
            if (is_file($existingOutput)) $outputs[] = $existingOutput;
            else { $partStart = $existingPart; break; }
        }
        foreach (array_slice($parts, $partStart, null, true) as $partIndex => $part) {
            $toc = [['Inhaltsverzeichnis', 'Seite', 'Raum', 'Gerät', 'Prüfung']]; $page = 3;
            foreach ($part as $row) { $room = trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: 'ohne Raum'; $toc[] = [$room, (string) $page, (string) $row['device_number'] . ' · ' . (string) $row['device_name'], (string) $row['external_number'], (string) $row['test_date']]; $page += (int) $row['pages']; }
            $cover = $outDir . '/cover-' . $partIndex . '.pdf'; $tocPdf = $outDir . '/toc-' . $partIndex . '.pdf'; file_put_contents($cover, ReportController::renderPdf([['Sammelbericht', 'Wert'], ['Erstellt', date('d.m.Y H:i')], ['Teil', ($partIndex + 1) . ' von ' . count($parts)]], 'Sammelbericht Prüfungen')); file_put_contents($tocPdf, ReportController::renderPdf($toc, 'Inhaltsverzeichnis Prüfberichte'));
            $output = $outDir . '/Pruefberichte-' . sprintf('%03d', $partIndex + 1) . '.pdf'; $inputs = [$cover, $tocPdf]; foreach ($part as $row) $inputs[] = $row['source']; $command = 'pdfunite ' . implode(' ', array_map('escapeshellarg', $inputs)) . ' ' . escapeshellarg($output) . ' 2>&1'; $result = shell_exec($command); if (!is_file($output)) throw new RuntimeException('Sammel-PDF konnte nicht erstellt werden: ' . trim((string) $result)); $outputs[] = $output; @unlink($cover); @unlink($tocPdf); $progress($partIndex + 1, count($parts), (string) ($part[0]['device_number'] ?? ''), 'Sammel-PDF erstellt');
        }
        if (count($outputs) === 1) {
            BackgroundJobService::complete($jobId, ['stats' => ['parts' => 1, 'reports' => count($files), 'max_pages' => $maxPages], 'output' => $outputs[0], 'outputs' => $outputs, 'output_type' => 'pdf'], 'Das Sammel-PDF steht zum Download bereit.');
            exit(0);
        }
        $zipPath = $outDir . '/Sammelberichte.zip'; $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Sammel-PDF-ZIP konnte nicht erstellt werden.'); foreach ($outputs as $output) $zip->addFile($output, 'Pruefberichte/' . basename($output)); $zip->close(); BackgroundJobService::complete($jobId, ['stats' => ['parts' => count($outputs), 'reports' => count($files), 'max_pages' => $maxPages], 'output' => $zipPath, 'outputs' => $outputs], 'Die Sammelberichte stehen zum Download bereit.'); exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $all = !empty($payload['all_reports']);
        $statusExpression = InspectionEvaluationService::sqlStatusExpression('i');
        $sql = "SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, i.classification, i.result_status, i.status, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) AND {$statusExpression} IN ('passed','failed') ORDER BY c.name, d.external_number, i.test_date DESC, i.id DESC";
        $rows = $ids === [] ? [] : R::getAll($sql, $ids);
        if (!$all) { $seen = []; $rows = array_values(array_filter($rows, static function (array $row) use (&$seen): bool { $device = (int) ($row['device_id'] ?? 0); if (isset($seen[$device])) return false; $seen[$device] = true; return true; })); }
        $outDir = app_data_root() . '/exports'; if (!is_dir($outDir)) mkdir($outDir, 0770, true);
        $workDir = app_data_root() . '/exports'; if (!is_dir($workDir)) mkdir($workDir, 0770, true);
        $workPath = $workDir . '/.pdfzip-' . $id . '.work.json';
        $work = is_file($workPath) ? json_decode((string) @file_get_contents($workPath), true) : [];
        $resume = is_array($work) && !empty($work['zip_path']) && is_file((string) $work['zip_path']) && (int) ($work['step'] ?? 0) <= count($rows);
        $zipPath = $resume ? (string) $work['zip_path'] : $outDir . '/pruefberichte-' . date('Ymd-His') . '-' . $id . '.zip';
        $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ($resume ? 0 : ZipArchive::OVERWRITE)) !== true) throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
        $index = $resume && is_array($work['index'] ?? null) ? $work['index'] : [['Gerät', 'Gerätenummer', 'Prüfnummer', 'Prüfdatum', 'Kunde', 'Datei', 'Quelle']];
        $step = $resume ? min((int) ($work['step'] ?? 0), count($rows)) : 0; $total = count($rows);
        foreach (array_slice($rows, $step) as $row) { $step++; $source = InspectionEvaluationService::reportPathAllowed((string) $row['result_status'], (string) $row['classification'], (string) $row['report_path']) ? $resolveReportSource((string) $row['report_path']) : ''; if (!is_file($source)) { file_put_contents($workPath, json_encode(['zip_path' => $zipPath, 'step' => $step, 'total' => $total, 'index' => $index], JSON_UNESCAPED_UNICODE), LOCK_EX); $progress($step, $total, (string) $row['device_number'], 'Ein freigegebener Prüfbericht fehlt und wurde übersprungen.'); continue; } $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['device_number'] . '-' . (string) $row['external_number'] . '.pdf'); $zip->addFile($source, 'berichte/' . $name); if (method_exists($zip, 'setCompressionName')) $zip->setCompressionName('berichte/' . $name, ZipArchive::CM_STORE); $index[] = [(string) $row['device_name'], (string) $row['device_number'], (string) $row['external_number'], (string) $row['test_date'], (string) $row['customer_name'], 'berichte/' . $name, $source]; file_put_contents($workPath, json_encode(['zip_path' => $zipPath, 'step' => $step, 'total' => $total, 'index' => $index], JSON_UNESCAPED_UNICODE), LOCK_EX); $progress($step, $total, (string) $row['device_number'], 'Prüfbericht wird dem Download hinzugefügt.'); }
        if (!empty($payload['index_csv'])) { $csv = ''; foreach ($index as $line) { $cells = []; foreach ($line as $value) $cells[] = '"' . str_replace('"', '""', (string) $value) . '"'; $csv .= implode(';', $cells) . "\r\n"; } $zip->addFromString('inhaltsverzeichnis.csv', "\xEF\xBB\xBF" . $csv); }
        if (!empty($payload['index_pdf'])) $zip->addFromString('inhaltsverzeichnis.pdf', ReportController::renderPdf($index, 'Inhaltsverzeichnis Prüfberichte'));
        if (!empty($payload['index_ods'])) $zip->addFromString('inhaltsverzeichnis.ods', ReportController::renderOds($index, 'Inhaltsverzeichnis'));
        $zip->close();
        $stats = ['files' => max(0, count($index) - 1), 'output' => $zipPath, 'all_reports' => $all];
        BackgroundJobService::complete($jobId, ['stats' => $stats, 'output' => $zipPath], 'Der ZIP-Export steht zum Download bereit.');
        @unlink($workPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'pending_measurement_import') {
        $csvPath = trim((string) ($payload['csv_path'] ?? ''));
        $uploadRoot = realpath(app_data_root() . '/uploads/pending-measurements') ?: '';
        $realCsvPath = realpath($csvPath) ?: '';
        if ($uploadRoot === '' || $realCsvPath === '' || !str_starts_with($realCsvPath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($realCsvPath)) {
            throw new RuntimeException('Die hochgeladene Messdaten-CSV wurde nicht gefunden.');
        }
        $stats = (new ElectricalInspectionImportService())->importPendingMeasurements($realCsvPath, trim((string) ($payload['test_date'] ?? '')));
        $message = (int) ($stats['updated'] ?? 0) . ' bestehende Prüfung(en) mit Messdaten aktualisiert; ' . (int) ($stats['skipped'] ?? 0) . ' Zeile(n) ohne passende Prüfung übersprungen.';
        if ((int) ($stats['cable_length_required'] ?? 0) > 0) $message .= ' Bei ' . (int) $stats['cable_length_required'] . ' Messung(en) wird noch die Kabellänge benötigt.';
        $progress(1, 1, '', $message);
        @unlink($realCsvPath);
    } elseif (($payload['type'] ?? '') === 'directory_import') {
        $source = realpath((string) ($payload['directory'] ?? '')) ?: '';
        if ($source === '' || (!is_file($source) && !is_dir($source))) throw new RuntimeException('Importquelle wurde nicht gefunden.');
        $checkpoint = (array) ($job['checkpoint'] ?? []);
        $files = is_array($checkpoint['files'] ?? null) ? $checkpoint['files'] : [];
        if ($files === []) {
            if (is_file($source)) $files = [$source];
            else {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) if ($file->isFile() && in_array(strtolower($file->getExtension()), ['json', 'jsonl', 'csv'], true)) $files[] = $file->getPathname();
                sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            }
        }
        if ($files === []) {
            throw new RuntimeException('Keine Importdateien gefunden. Das Importverzeichnis benötigt JSON, JSONL oder CSV; PDF-Dateien gehören in „PDF-Quellverzeichnis“.');
        }
        $fileIndex = min((int) ($checkpoint['file_index'] ?? 0), count($files));
        $byteOffset = max(0, (int) ($checkpoint['byte_offset'] ?? 0));
        $stats = is_array($checkpoint['stats'] ?? null) ? $checkpoint['stats'] : ['files' => 0, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'errors' => []];
        $defaults = is_array($payload['defaults'] ?? null) ? $payload['defaults'] : [];
        $defaults['_audit_correlation_id'] = 'job-' . $id;
        $mergeStats = static function (array &$target, array $part): void {
            foreach ($part as $key => $value) {
                if (is_int($value)) $target[$key] = (int) ($target[$key] ?? 0) + $value;
                elseif (in_array($key, ['errors', 'new_devices', 'updated_devices', 'not_imported'], true) && is_array($value)) $target[$key] = array_merge((array) ($target[$key] ?? []), $value);
            }
        };
        $service = new ElectricalInspectionImportService();
        while ($fileIndex < count($files)) {
            $file = (string) $files[$fileIndex];
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'jsonl') {
                $chunk = $service->importJsonlChunk($file, $byteOffset, 25, trim((string) ($payload['reports_directory'] ?? '')) ?: null, $defaults);
                $mergeStats($stats, (array) $chunk['stats']);
                $byteOffset = (int) $chunk['next_offset'];
                if (!empty($chunk['eof'])) { $fileIndex++; $byteOffset = 0; }
            } else {
                $part = $service->importDirectory($file, trim((string) ($payload['reports_directory'] ?? '')) ?: null, $defaults);
                $mergeStats($stats, $part);
                $fileIndex++;
                $byteOffset = 0;
            }
            $checkpoint = ['files' => $files, 'file_index' => $fileIndex, 'byte_offset' => $byteOffset, 'stats' => $stats, 'current_device' => basename($file)];
            JobQueue::checkpoint($jobId, $checkpoint, $fileIndex, count($files), basename($file) . ' wird importiert', $workerId, 180);
            $progress($fileIndex, count($files), basename($file), (int) ($stats['imported'] ?? 0) . ' importiert, ' . (int) ($stats['updated'] ?? 0) . ' aktualisiert');
        }
        $completionMarker = trim((string) ($payload['completion_marker'] ?? ''));
        if ($completionMarker !== '') {
            if (!is_dir(dirname($completionMarker))) mkdir(dirname($completionMarker), 0770, true);
            file_put_contents($completionMarker, json_encode(['completed_at' => date(DATE_ATOM), 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        $completionConfigKey = trim((string) ($payload['completion_config_key'] ?? ''));
        if ($completionConfigKey !== '') {
            set_app_config($completionConfigKey, (string) ($payload['completion_config_value'] ?? '1'));
        }
        set_app_config('inspection_data_migration_version', '');
        if (JobQueue::cancellationRequested($jobId)) throw new RuntimeException('__JOB_CANCELLED__');
    } else {
        $stats = (new PhoenixSyncService())->sync((string) ($payload['customer_id'] ?? ''), (string) ($payload['token'] ?? ''), (string) ($payload['api_url'] ?? ''), $progress, (int) ($statusInitial['step'] ?? 0), $id);
        set_app_config('inspection_data_migration_version', '');
    }
    BackgroundJobService::complete($jobId, ['stats' => $stats], BackgroundJobService::label((string) ($payload['type'] ?? 'background')) . ' abgeschlossen.');
} catch (Throwable $exception) {
    $cancelled = $exception->getMessage() === '__JOB_CANCELLED__' || JobQueue::cancellationRequested($jobId);
    $timeLimited = $exception->getMessage() === '__CRON_TIME_LIMIT__';
    if ($cancelled) {
        JobQueue::finish($jobId, 'cancelled', [], 'Die Aufgabe wurde abgebrochen.');
        exit(0);
    }
    if ($timeLimited) {
        JobQueue::release($jobId, $workerId, 'Die Aufgabe wird im nächsten freien Arbeitsabschnitt am gespeicherten Stand fortgesetzt.', 0);
        exit(0);
    }
    BackgroundJobService::fail($jobId, $exception->getMessage());
}
