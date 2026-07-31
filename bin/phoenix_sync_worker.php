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
file_put_contents($statusPath, json_encode(['state' => 'running'], JSON_UNESCAPED_UNICODE), LOCK_EX);
try {
    $stats = (new PhoenixSyncService())->sync((string) ($payload['customer_id'] ?? ''), (string) ($payload['token'] ?? ''), (string) ($payload['api_url'] ?? ''));
    file_put_contents($statusPath, json_encode(['state' => 'done', 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
} catch (Throwable $exception) {
    file_put_contents($statusPath, json_encode(['state' => 'error', 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE), LOCK_EX);
}
@unlink($payloadPath);
