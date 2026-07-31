#!/usr/bin/env php
<?php
declare(strict_types=1);

// Run from cron as the same user that owns the application data (usually www-data).
$root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
$lock = fopen($root . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);
foreach (glob($root . '/*.status.json') ?: [] as $statusPath) {
    $status = json_decode((string) file_get_contents($statusPath), true);
    if (!is_array($status) || ($status['state'] ?? '') !== 'queued') continue;
    $id = (string) ($status['id'] ?? basename($statusPath, '.status.json'));
    if (!preg_match('/^[a-f0-9]{24}$/', $id) || !is_file($root . '/' . $id . '.json')) continue;
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phoenix_sync_worker.php') . ' ' . escapeshellarg($id));
}
flock($lock, LOCK_UN);
fclose($lock);
