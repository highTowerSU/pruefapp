<?php

declare(strict_types=1);

$root = sys_get_temp_dir() . '/pruefapp-cron-test-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
$config = $root . '/config.php';
$database = $root . '/db.sqlite';
$data = $root . '/data';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'cron_test_' . bin2hex(random_bytes(3)),
    'APP_DATABASE_PATH' => $database,
    'APP_DATA_ROOT' => $data,
    'APP_OIDC_ISSUER_URL' => 'https://login.example.test/realms/test',
    'APP_OIDC_CLIENT_ID' => 'test-client',
    'APP_OIDC_CLIENT_SECRET' => 'test-secret',
], true) . ';');

$environment = array_merge($_ENV, [
    'PRUEFAPP_CONFIG_FILE' => $config,
    'PRUEFAPP_CRON_TIME_BUDGET' => '30',
    'PRUEFAPP_CRON_JOB_SLICE' => '5',
]);
$pipes = [];
$process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/cron.php', '--debug'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
if (!is_resource($process)) throw new RuntimeException('Cron-Testprozess konnte nicht gestartet werden.');
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) throw new RuntimeException("Cron-Testprozess ist fehlgeschlagen:\n" . $stdout . $stderr);

$pdo = new PDO('sqlite:' . $database);
$pending = (int) $pdo->query("SELECT COUNT(*) FROM backgroundjob WHERE status IN ('queued','running','cancel_requested')")->fetchColumn();
$finished = (int) $pdo->query("SELECT COUNT(*) FROM backgroundjob WHERE status = 'done'")->fetchColumn();
$runs = (int) $pdo->query("SELECT COUNT(DISTINCT run_id) FROM cron_log WHERE run_id != ''")->fetchColumn();
if ($pending !== 0 || $finished < 2 || $runs !== 1) {
    throw new RuntimeException('Cron und Worker haben die isolierten Wartungsaufgaben nicht gemeinsam abgeschlossen.');
}
$pdo = null;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
rmdir($root);

echo "PASS: Cron und Worker verwenden dieselbe fortsetzbare Aufgabenqueue\n";
