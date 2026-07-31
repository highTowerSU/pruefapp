#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/lib.inc.php';

$id = preg_replace('/[^a-f0-9]/', '', (string) ($argv[1] ?? ''));
$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
$payloadPath = $root . '/' . $id . '.json';
$statusPath = $root . '/' . $id . '.status.json';
if ($id === '' || !is_file($payloadPath)) exit(2);
$payload = json_decode((string) file_get_contents($payloadPath), true);
$writeStatus = static function (array $extra) use ($statusPath, $id, $payload): void {
    file_put_contents($statusPath, json_encode(array_merge(['id' => $id, 'state' => 'running', 'created_at' => date(DATE_ATOM), 'customer_id' => (string) ($payload['customer_id'] ?? '')], $extra), JSON_UNESCAPED_UNICODE), LOCK_EX);
};
$writeStatus([]);
try {
    $stats = (new PhoenixSyncService())->sync((string) ($payload['customer_id'] ?? ''), (string) ($payload['token'] ?? ''), (string) ($payload['api_url'] ?? ''), static function (int $step, int $total, string $number, string $message) use ($writeStatus): void {
        $writeStatus(['step' => $step, 'total' => $total, 'current_device' => $number, 'message' => $message]);
    });
    file_put_contents($statusPath, json_encode(['id' => $id, 'state' => 'done', 'finished_at' => date(DATE_ATOM), 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
} catch (Throwable $exception) {
    file_put_contents($statusPath, json_encode(['id' => $id, 'state' => 'error', 'finished_at' => date(DATE_ATOM), 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE), LOCK_EX);
}
@unlink($payloadPath);
