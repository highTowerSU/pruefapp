#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/lib.inc.php';

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
    if (($payload['type'] ?? '') === 'pdf_zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive ist nicht verfügbar.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['device_ids'] ?? [])), static fn(int $value): bool => $value > 0)));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $all = !empty($payload['all_reports']);
        $sql = "SELECT i.id, i.device_id, i.external_number, i.test_date, i.report_path, d.external_number AS device_number, d.name AS device_name, c.name AS customer_name FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE i.device_id IN ($marks) AND TRIM(COALESCE(i.report_path,'')) <> '' ORDER BY c.name, d.external_number, i.test_date DESC, i.id DESC";
        $rows = $ids === [] ? [] : R::getAll($sql, $ids);
        if (!$all) { $seen = []; $rows = array_values(array_filter($rows, static function (array $row) use (&$seen): bool { $device = (int) ($row['device_id'] ?? 0); if (isset($seen[$device])) return false; $seen[$device] = true; return true; })); }
        $outDir = app_data_root() . '/exports'; if (!is_dir($outDir)) mkdir($outDir, 0770, true);
        $zipPath = $outDir . '/pruefberichte-' . date('Ymd-His') . '-' . $id . '.zip'; $zip = new ZipArchive(); if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
        $index = [['Gerät', 'Gerätenummer', 'Prüfnummer', 'Prüfdatum', 'Kunde', 'Datei', 'Quelle']]; $step = 0; $total = count($rows);
        foreach ($rows as $row) { $step++; $source = (string) $row['report_path']; $source = is_file($source) ? $source : (is_file('/var/www/berichte/' . basename($source)) ? '/var/www/berichte/' . basename($source) : $source); if (!is_file($source)) continue; $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['device_number'] . '-' . (string) $row['external_number'] . '.pdf'); $zip->addFile($source, 'berichte/' . $name); $index[] = [(string) $row['device_name'], (string) $row['device_number'], (string) $row['external_number'], (string) $row['test_date'], (string) $row['customer_name'], 'berichte/' . $name, $source]; $progress($step, $total, (string) $row['device_number'], 'PDF wird gepackt'); }
        $csv = ''; foreach ($index as $line) { $cells = []; foreach ($line as $value) $cells[] = '"' . str_replace('"', '""', (string) $value) . '"'; $csv .= implode(';', $cells) . "\r\n"; } $zip->addFromString('inhaltsverzeichnis.csv', "\xEF\xBB\xBF" . $csv); $zip->close();
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
