#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/ReportController.php';

$id = preg_replace('/[^a-f0-9]/', '', (string) ($argv[1] ?? ''));
$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
$payloadPath = $root . '/' . $id . '.json';
$statusPath = $root . '/' . $id . '.status.json';
$cancelPath = $root . '/' . $id . '.cancel';
if ($id === '' || !is_file($payloadPath)) exit(2);
$payload = json_decode((string) file_get_contents($payloadPath), true);
$writeStatus = static function (array $extra) use ($statusPath, $id, $payload): void {
    file_put_contents($statusPath, json_encode(array_merge(['id' => $id, 'state' => 'running', 'created_at' => date(DATE_ATOM), 'customer_id' => (string) ($payload['customer_id'] ?? '')], $extra), JSON_UNESCAPED_UNICODE), LOCK_EX);
};
$writeStatus([]);
try {
    $progress = static function (int $step, int $total, string $number, string $message) use ($writeStatus, $cancelPath): void {
        if (is_file($cancelPath)) throw new RuntimeException('Job wurde abgebrochen.');
        $writeStatus(['step' => $step, 'total' => $total, 'current_device' => $number, 'message' => $message]);
    };
    if (($payload['type'] ?? '') === 'pdf_bundle') {
        if (!function_exists('shell_exec')) throw new RuntimeException('PDF-Zusammenführung ist auf diesem Server nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        if ($ids === []) throw new RuntimeException('Keine Geräte für die Sammel-PDF.');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getAll("SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) AND i.result_status IN ('bestanden','durchgefallen','nicht bestanden') ORDER BY c.name, b.name, f.sort_order, f.name, r.number, d.external_number, i.test_date DESC, i.id DESC", $ids);
        $files = [];
        foreach ($rows as $row) {
            $source = (string) $row['report_path']; $source = is_file($source) ? $source : (is_file('/var/www/berichte/' . basename($source)) ? '/var/www/berichte/' . basename($source) : $source);
            if (!is_file($source)) { $inspection = R::load('inspection', (int) $row['id']); $device = R::load('device', (int) $row['device_id']); $relative = 'reports/current/' . (int) $inspection->id . '.pdf'; $source = app_data_root() . '/' . $relative; if (!is_dir(dirname($source))) mkdir(dirname($source), 0770, true); $customerId = (int) ($row['customer_id'] ?? 0); file_put_contents($source, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_company_branding') ? get_company_branding($customerId) : null)); $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection); }
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
        $zipPath = $outDir . '/Sammelberichte.zip'; $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Sammel-PDF-ZIP konnte nicht erstellt werden.'); foreach ($outputs as $output) $zip->addFile($output, 'Pruefberichte/' . basename($output)); $zip->close(); file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_bundle', 'state' => 'done', 'finished_at' => date(DATE_ATOM), 'stats' => ['parts' => count($outputs), 'reports' => count($files), 'max_pages' => $maxPages], 'output' => $zipPath, 'outputs' => $outputs], JSON_UNESCAPED_UNICODE), LOCK_EX); @unlink($payloadPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'pdf_zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $all = !empty($payload['all_reports']);
        $sql = "SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) ORDER BY c.name, d.external_number, i.test_date DESC, i.id DESC";
        $rows = $ids === [] ? [] : R::getAll($sql, $ids);
        if (!$all) { $seen = []; $rows = array_values(array_filter($rows, static function (array $row) use (&$seen): bool { $device = (int) ($row['device_id'] ?? 0); if (isset($seen[$device])) return false; $seen[$device] = true; return true; })); }
        $outDir = app_data_root() . '/exports'; if (!is_dir($outDir)) mkdir($outDir, 0770, true);
        $zipPath = $outDir . '/pruefberichte-' . date('Ymd-His') . '-' . $id . '.zip'; $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
        $index = [['Gerät', 'Gerätenummer', 'Prüfnummer', 'Prüfdatum', 'Kunde', 'Datei', 'Quelle']]; $step = 0; $total = count($rows);
        foreach ($rows as $row) { $step++; $source = (string) $row['report_path']; $source = is_file($source) ? $source : (is_file('/var/www/berichte/' . basename($source)) ? '/var/www/berichte/' . basename($source) : $source); if (!is_file($source)) { $inspection = R::load('inspection', (int) $row['id']); $device = R::load('device', (int) $row['device_id']); if ($inspection->id && $device->id) { $relative = 'reports/current/' . (int) $inspection->id . '.pdf'; $generatedPath = app_data_root() . '/' . $relative; if (!is_dir(dirname($generatedPath))) mkdir(dirname($generatedPath), 0770, true); file_put_contents($generatedPath, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . $inspection->external_number, function_exists('get_company_branding') ? get_company_branding((int) ($row['customer_id'] ?? 0)) : null)); $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection); $source = $generatedPath; } } if (!is_file($source)) continue; $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['device_number'] . '-' . (string) $row['external_number'] . '.pdf'); $zip->addFile($source, 'berichte/' . $name); $index[] = [(string) $row['device_name'], (string) $row['device_number'], (string) $row['external_number'], (string) $row['test_date'], (string) $row['customer_name'], 'berichte/' . $name, $source]; $progress($step, $total, (string) $row['device_number'], 'PDF wird gepackt'); }
        if (!empty($payload['index_csv'])) { $csv = ''; foreach ($index as $line) { $cells = []; foreach ($line as $value) $cells[] = '"' . str_replace('"', '""', (string) $value) . '"'; $csv .= implode(';', $cells) . "\r\n"; } $zip->addFromString('inhaltsverzeichnis.csv', "\xEF\xBB\xBF" . $csv); }
        if (!empty($payload['index_pdf'])) $zip->addFromString('inhaltsverzeichnis.pdf', ReportController::renderPdf($index, 'Inhaltsverzeichnis Prüfberichte'));
        if (!empty($payload['index_ods'])) $zip->addFromString('inhaltsverzeichnis.ods', ReportController::renderOds($index, 'Inhaltsverzeichnis'));
        $zip->close();
        $stats = ['files' => max(0, count($index) - 1), 'output' => $zipPath, 'all_reports' => $all];
        file_put_contents($statusPath, json_encode(['id' => $id, 'type' => 'pdf_zip', 'state' => 'done', 'finished_at' => date(DATE_ATOM), 'stats' => $stats, 'output' => $zipPath], JSON_UNESCAPED_UNICODE), LOCK_EX);
        @unlink($payloadPath); @unlink($cancelPath); exit(0);
    } elseif (($payload['type'] ?? '') === 'directory_import') {
        $stats = (new ElectricalInspectionImportService())->importDirectory((string) ($payload['directory'] ?? ''), trim((string) ($payload['reports_directory'] ?? '')) ?: null, is_array($payload['defaults'] ?? null) ? $payload['defaults'] : []);
    } else {
        $stats = (new PhoenixSyncService())->sync((string) ($payload['customer_id'] ?? ''), (string) ($payload['token'] ?? ''), (string) ($payload['api_url'] ?? ''), $progress);
    }
    file_put_contents($statusPath, json_encode(['id' => $id, 'state' => 'done', 'finished_at' => date(DATE_ATOM), 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
} catch (Throwable $exception) {
    $cancelled = is_file($cancelPath);
    file_put_contents($statusPath, json_encode(['id' => $id, 'state' => $cancelled ? 'cancelled' : 'error', 'finished_at' => date(DATE_ATOM), 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE), LOCK_EX);
}
@unlink($payloadPath);
@unlink($cancelPath);
