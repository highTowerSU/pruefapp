<?php

declare(strict_types=1);

$storage = sys_get_temp_dir() . '/pruefapp_config_test_' . bin2hex(random_bytes(4));
mkdir($storage, 0770, true);
mkdir($storage . '/sessions', 0770, true);
session_save_path($storage . '/sessions');

$configFile = $storage . '/config.php';
file_put_contents($configFile, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'pruefapp_config_test',
    'APP_DATABASE_PATH' => $storage . '/db.sqlite',
    'APP_OIDC_ISSUER_URL' => 'https://login.example.test/realms/test',
    'APP_OIDC_CLIENT_ID' => 'test-client',
    'APP_OIDC_CLIENT_SECRET' => 'test-secret',
], true) . ';');
define('CENEOS_CONFIG_FILE', $configFile);

$_SERVER['SCRIPT_NAME'] = '/pruefapp/index.php';
$_SERVER['PHP_SELF'] = '/pruefapp/login.php';
$_SERVER['REQUEST_URI'] = '/pruefapp/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../lib/lib.inc.php';

if (config_value('APP_STORAGE_NAMESPACE') !== 'pruefapp_config_test') {
    throw new \RuntimeException('Die externe Konfiguration wurde nicht geladen.');
}

if (!RedBeanPHP\R::testConnection()) {
    throw new \RuntimeException('Die konfigurierte SQLite-Datenbank ist nicht verbunden.');
}
if (!RedBeanPHP\R::getWriter()->tableExists('area')) {
    throw new \RuntimeException('Die Bereichstabelle wurde nicht angelegt.');
}
foreach ([
    'customer' => 'code',
    'site' => 'code',
    'building' => 'code',
    'floor' => 'room_code_pattern',
    'area' => 'description',
    'room' => 'number',
    'device' => 'metadata_json',
    'inspection' => 'classification',
    'inspection_answer' => 'outcome',
    'inspection_measurement' => 'rule_key',
    'inspection_source_snapshot' => 'legacy_row_json',
    'inspection_report_asset' => 'checksum',
] as $table => $column) {
    if (!array_key_exists($column, RedBeanPHP\R::getColumns($table))) {
        throw new \RuntimeException("Die Strukturspalte {$table}.{$column} fehlt.");
    }
}

RedBeanPHP\R::close();
