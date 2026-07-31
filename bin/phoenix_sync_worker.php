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
    if (($payload['type'] ?? '') === 'directory_import') {
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
