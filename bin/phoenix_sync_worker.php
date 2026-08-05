#!/usr/bin/env php
<?php
declare(strict_types=1);

use RedBeanPHP\R as R;
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';

$id = preg_replace('/[^a-f0-9]/', '', (string) ($argv[1] ?? ''));
$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
$payloadPath = $root . '/' . $id . '.json';
$statusPath = $root . '/' . $id . '.status.json';
$cancelPath = $root . '/' . $id . '.cancel';
$deadline = (float) (getenv('PRUEFAPP_CRON_DEADLINE') ?: 0);
$debug = getenv('PRUEFAPP_CRON_DEBUG') === '1';
if ($id === '' || !is_file($payloadPath)) exit(2);
$payload = json_decode((string) file_get_contents($payloadPath), true);
$ownerUserId = (int) (is_array($payload) ? ($payload['owner_user_id'] ?? 0) : 0);
$statusInitial = is_file($statusPath) ? json_decode((string) @file_get_contents($statusPath), true) : [];
$statusCreatedAt = is_array($statusInitial) && !empty($statusInitial['created_at']) ? (string) $statusInitial['created_at'] : date(DATE_ATOM);
$archiveStatus = static function () use ($statusPath, $id): void {
    $status = json_decode((string) @file_get_contents($statusPath), true);
    if (!is_array($status) || !in_array((string) ($status['state'] ?? ''), ['done', 'error', 'cancelled'], true)) return;
    $archiveRoot = app_data_root() . '/logs/background-jobs';
    if (!is_dir($archiveRoot)) @mkdir($archiveRoot, 0770, true);
    @file_put_contents($archiveRoot . '/' . $id . '.status.json', json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
};
register_shutdown_function($archiveStatus);
$writeStatus = static function (array $extra) use ($statusPath, $id, $payload, $statusCreatedAt): void {
    file_put_contents($statusPath, json_encode(array_merge(['id' => $id, 'type' => (string) ($payload['type'] ?? 'background'), 'state' => 'running', 'owner_user_id' => (int) ($payload['owner_user_id'] ?? 0), 'created_at' => $statusCreatedAt, 'customer_id' => (string) ($payload['customer_id'] ?? '')], $extra), JSON_UNESCAPED_UNICODE), LOCK_EX);
};
$writeStatus([]);
try {
    $progress = static function (int $step, int $total, string $number, string $message) use ($writeStatus, $cancelPath, $deadline, $debug): void {
        if (is_file($cancelPath)) throw new RuntimeException('Job wurde abgebrochen.');
        if ($deadline > 0 && microtime(true) >= $deadline) throw new RuntimeException('__CRON_TIME_LIMIT__');
        $writeStatus(['step' => $step, 'total' => $total, 'current_device' => $number, 'message' => $message]);
        if ($debug) fwrite(STDERR, '[worker debug] ' . $step . '/' . $total . ' ' . ($number !== '' ? $number . ' · ' : '') . $message . PHP_EOL);
    };
    if (($payload['type'] ?? '') === 'pdf_regenerate') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['inspection_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $total = count($ids); $step = min((int) ($statusInitial['step'] ?? 0), $total);
        foreach (array_slice($ids, $step) as $inspectionId) {
            $inspection = R::load('inspection', $inspectionId); $device = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
            if ($inspection->id && $device && $device->id) {
                $relative = 'reports/current/' . $inspectionId . '.pdf'; $path = app_data_root() . '/' . $relative; if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
                file_put_contents($path, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_report_branding') ? get_report_branding() : null), LOCK_EX); $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection);
            }
            $step++; $progress($step, $total, (string) ($device->external_number ?? ''), 'Prüfbericht wird neu erzeugt');
        }
        file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_regenerate', 'state' => 'done', 'owner_user_id' => $ownerUserId, 'finished_at' => date(DATE_ATOM), 'stats' => ['reports' => $step]], JSON_UNESCAPED_UNICODE), LOCK_EX); @unlink($payloadPath); @unlink($cancelPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_bundle') {
        if (!function_exists('shell_exec')) throw new RuntimeException('PDF-Zusammenführung ist auf diesem Server nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        if ($ids === []) throw new RuntimeException('Keine Geräte für die Sammel-PDF.');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getAll("SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) AND i.result_status IN ('bestanden','durchgefallen','nicht bestanden') ORDER BY c.name, b.name, f.sort_order, f.name, r.number, d.external_number, i.test_date DESC, i.id DESC", $ids);
        $files = [];
        foreach ($rows as $row) {
            $source = (string) $row['report_path']; $source = is_file($source) ? $source : (is_file('/var/www/berichte/' . basename($source)) ? '/var/www/berichte/' . basename($source) : $source);
            if (!is_file($source)) { $inspection = R::load('inspection', (int) $row['id']); $device = R::load('device', (int) $row['device_id']); $relative = 'reports/current/' . (int) $inspection->id . '.pdf'; $source = app_data_root() . '/' . $relative; if (!is_dir(dirname($source))) mkdir(dirname($source), 0770, true); file_put_contents($source, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_report_branding') ? get_report_branding() : null)); $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection); }
            if (!is_file($source)) continue;
            $info = shell_exec('pdfinfo ' . escapeshellarg($source) . ' 2>/dev/null'); preg_match('/^Pages:\s+(\d+)/mi', (string) $info, $match); $pages = max(1, (int) ($match[1] ?? 1)); $row['pages'] = $pages; $row['source'] = $source; $files[] = $row;
        }
        if ($files === []) throw new RuntimeException('Keine fertigen Prüfberichte gefunden.');
        $maxPages = max(10, (int) ($payload['max_pages'] ?? 500)); $parts = []; $current = []; $currentPages = 0;
        foreach ($files as $row) { if ($current !== [] && $currentPages + (int) $row['pages'] + 2 > $maxPages) { $parts[] = $current; $current = []; $currentPages = 0; } $current[] = $row; $currentPages += (int) $row['pages']; }
        if ($current !== []) $parts[] = $current;
        $outDir = app_data_root() . '/exports/sammelpdf-' . date('Ymd-His') . '-' . $id; if (!is_dir($outDir)) mkdir($outDir, 0770, true); $outputs = [];
        foreach ($parts as $partIndex => $part) {
            $toc = [['Inhaltsverzeichnis', 'Seite', 'Raum', 'Gerät', 'Prüfung']]; $page = 3;
            foreach ($part as $row) { $room = trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: 'ohne Raum'; $toc[] = [$room, (string) $page, (string) $row['device_number'] . ' · ' . (string) $row['device_name'], (string) $row['external_number'], (string) $row['test_date']]; $page += (int) $row['pages']; }
            $cover = $outDir . '/cover-' . $partIndex . '.pdf'; $tocPdf = $outDir . '/toc-' . $partIndex . '.pdf'; file_put_contents($cover, ReportController::renderPdf([['Sammelbericht', 'Wert'], ['Erstellt', date('d.m.Y H:i')], ['Teil', ($partIndex + 1) . ' von ' . count($parts)]], 'Sammelbericht Prüfungen')); file_put_contents($tocPdf, ReportController::renderPdf($toc, 'Inhaltsverzeichnis Prüfberichte'));
            $output = $outDir . '/Pruefberichte-' . sprintf('%03d', $partIndex + 1) . '.pdf'; $inputs = [$cover, $tocPdf]; foreach ($part as $row) $inputs[] = $row['source']; $command = 'pdfunite ' . implode(' ', array_map('escapeshellarg', $inputs)) . ' ' . escapeshellarg($output) . ' 2>&1'; $result = shell_exec($command); if (!is_file($output)) throw new RuntimeException('Sammel-PDF konnte nicht erstellt werden: ' . trim((string) $result)); $outputs[] = $output; @unlink($cover); @unlink($tocPdf); $progress($partIndex + 1, count($parts), (string) ($part[0]['device_number'] ?? ''), 'Sammel-PDF erstellt');
        }
        if (count($outputs) === 1) {
            file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_bundle', 'state' => 'done', 'owner_user_id' => $ownerUserId, 'finished_at' => date(DATE_ATOM), 'stats' => ['parts' => 1, 'reports' => count($files), 'max_pages' => $maxPages], 'output' => $outputs[0], 'outputs' => $outputs, 'output_type' => 'pdf'], JSON_UNESCAPED_UNICODE), LOCK_EX);
            @unlink($payloadPath);
            exit(0);
        }
        $zipPath = $outDir . '/Sammelberichte.zip'; $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Sammel-PDF-ZIP konnte nicht erstellt werden.'); foreach ($outputs as $output) $zip->addFile($output, 'Pruefberichte/' . basename($output)); $zip->close(); file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_bundle', 'state' => 'done', 'owner_user_id' => $ownerUserId, 'finished_at' => date(DATE_ATOM), 'stats' => ['parts' => count($outputs), 'reports' => count($files), 'max_pages' => $maxPages], 'output' => $zipPath, 'outputs' => $outputs], JSON_UNESCAPED_UNICODE), LOCK_EX); @unlink($payloadPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $all = !empty($payload['all_reports']);
        $sql = "SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) ORDER BY c.name, d.external_number, i.test_date DESC, i.id DESC";
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
        foreach (array_slice($rows, $step) as $row) { $step++; $source = (string) $row['report_path']; $source = is_file($source) ? $source : (is_file('/var/www/berichte/' . basename($source)) ? '/var/www/berichte/' . basename($source) : $source); if (!is_file($source)) { file_put_contents($workPath, json_encode(['zip_path' => $zipPath, 'step' => $step, 'total' => $total, 'index' => $index], JSON_UNESCAPED_UNICODE), LOCK_EX); $progress($step, $total, (string) $row['device_number'], 'Ein PDF konnte nicht gefunden werden und wurde übersprungen.'); continue; } $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['device_number'] . '-' . (string) $row['external_number'] . '.pdf'); $zip->addFile($source, 'berichte/' . $name); if (method_exists($zip, 'setCompressionName')) $zip->setCompressionName('berichte/' . $name, ZipArchive::CM_STORE); $index[] = [(string) $row['device_name'], (string) $row['device_number'], (string) $row['external_number'], (string) $row['test_date'], (string) $row['customer_name'], 'berichte/' . $name, $source]; file_put_contents($workPath, json_encode(['zip_path' => $zipPath, 'step' => $step, 'total' => $total, 'index' => $index], JSON_UNESCAPED_UNICODE), LOCK_EX); $progress($step, $total, (string) $row['device_number'], 'PDF wird gepackt'); }
        if (!empty($payload['index_csv'])) { $csv = ''; foreach ($index as $line) { $cells = []; foreach ($line as $value) $cells[] = '"' . str_replace('"', '""', (string) $value) . '"'; $csv .= implode(';', $cells) . "\r\n"; } $zip->addFromString('inhaltsverzeichnis.csv', "\xEF\xBB\xBF" . $csv); }
        if (!empty($payload['index_pdf'])) $zip->addFromString('inhaltsverzeichnis.pdf', ReportController::renderPdf($index, 'Inhaltsverzeichnis Prüfberichte'));
        if (!empty($payload['index_ods'])) $zip->addFromString('inhaltsverzeichnis.ods', ReportController::renderOds($index, 'Inhaltsverzeichnis'));
        $zip->close();
        $stats = ['files' => max(0, count($index) - 1), 'output' => $zipPath, 'all_reports' => $all];
        file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_zip', 'state' => 'done', 'owner_user_id' => $ownerUserId, 'finished_at' => date(DATE_ATOM), 'stats' => $stats, 'output' => $zipPath], JSON_UNESCAPED_UNICODE), LOCK_EX);
        @unlink($workPath); @unlink($payloadPath); @unlink($cancelPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'directory_import') {
        $stats = (new ElectricalInspectionImportService())->importDirectory((string) ($payload['directory'] ?? ''), trim((string) ($payload['reports_directory'] ?? '')) ?: null, is_array($payload['defaults'] ?? null) ? $payload['defaults'] : []);
        if (is_file($cancelPath)) throw new RuntimeException('Job wurde nach dem aktuellen Importschritt abgebrochen.');
    } else {
        $stats = (new PhoenixSyncService())->sync((string) ($payload['customer_id'] ?? ''), (string) ($payload['token'] ?? ''), (string) ($payload['api_url'] ?? ''), $progress);
    }
    file_put_contents($statusPath, json_encode(['id' => $id, 'type' => (string) ($payload['type'] ?? 'background'), 'state' => 'done', 'owner_user_id' => $ownerUserId, 'finished_at' => date(DATE_ATOM), 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
} catch (Throwable $exception) {
    $cancelled = is_file($cancelPath);
    $timeLimited = $exception->getMessage() === '__CRON_TIME_LIMIT__';
    $lastStatus = is_file($statusPath) ? json_decode((string) @file_get_contents($statusPath), true) : [];
    $lastStatus = is_array($lastStatus) ? $lastStatus : [];
    $lastStatus['id'] = $id;
    $lastStatus['state'] = $timeLimited ? 'queued' : ($cancelled ? 'cancelled' : 'error');
    $lastStatus['finished_at'] = date(DATE_ATOM);
    $lastStatus['message'] = $timeLimited ? 'Der Export wird automatisch beim nächsten Hintergrundlauf fortgesetzt.' : '';
    $lastStatus['error'] = $timeLimited ? '' : $exception->getMessage();
    file_put_contents($statusPath, json_encode($lastStatus, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($timeLimited) exit(0);
}
if (!isset($timeLimited) || !$timeLimited) @unlink($payloadPath);
@unlink($cancelPath);
