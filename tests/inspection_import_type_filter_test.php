<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-import-type-filter-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'import_type_filter_test',
    'APP_DATABASE_PATH' => $root . '/db.sqlite',
    'APP_DATA_ROOT' => $root . '/data',
    'APP_OIDC_ISSUER_URL' => 'https://login.example.test/realms/test',
    'APP_OIDC_CLIENT_ID' => 'test-client',
    'APP_OIDC_CLIENT_SECRET' => 'test-secret',
], true) . ';');
define('CENEOS_CONFIG_FILE', $config);
$_SERVER['SCRIPT_NAME'] = '/pruefapp/index.php';
$_SERVER['PHP_SELF'] = '/pruefapp/index.php';
$_SERVER['REQUEST_URI'] = '/pruefapp/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once dirname(__DIR__) . '/lib/lib.inc.php';

try {
    $source = $root . '/phoenix.jsonl';
    file_put_contents($source, implode("\n", [
        json_encode(['number' => 'ELEKTRO-1', 'date' => '2025-08-01', 'type' => 'Wiederholungsprüfung SK1', 'audit_ok' => true, 'room' => 'K016', 'device_type' => 'Beamer', 'manufacturer' => 'Epson', 'device_model' => 'EB-FH52', 'total_cost_plus' => 12, 'measurements' => [['name' => 'RPE', 'value' => '0,18', 'unit' => 'Ohm', 'result' => 'bestanden']]]),
        json_encode(['number' => 'UNT-1', 'date' => '2025-08-01', 'type' => 'Unterweisungsnachweis je Mitarbeiter', 'audit_ok' => true]),
        json_encode(['number' => 'UEB-1', 'date' => '2025-08-01', 'type' => 'Übergabe Messgerät', 'audit_ok' => true]),
    ]) . "\n");
    $stats = (new ElectricalInspectionImportService())->importDirectory($source);
    $inspection = R::getRow("SELECT i.*, d.name AS device_name, d.manufacturer AS device_manufacturer, d.device_model AS stored_model FROM inspection i JOIN device d ON d.id=i.device_id WHERE i.external_number='ELEKTRO-1-25'");
    $measurements = json_decode((string) ($inspection['measurements_json'] ?? ''), true);
    if ($stats['imported'] !== 1 || $stats['skipped'] !== 2 || (int) R::getCell('SELECT COUNT(*) FROM inspection') !== 1
        || ($inspection['test_date'] ?? '') !== '2025-08-01' || ($inspection['room_snapshot'] ?? '') !== 'K016'
        || (int) ($inspection['regie_minutes'] ?? -1) !== 12 || ($inspection['result_status'] ?? '') !== 'passed'
        || ($inspection['device_name'] ?? '') !== 'Beamer' || ($inspection['device_manufacturer'] ?? '') !== 'Epson'
        || ($inspection['stored_model'] ?? '') !== 'EB-FH52' || (string) ($measurements[0]['value'] ?? '') !== '0,18') {
        throw new RuntimeException('JSON-Importfelder oder der Filter nicht-elektrischer Phoenix-Datensätze sind fehlerhaft: ' . json_encode($stats));
    }
    echo "PASS: Unterweisungen und Messgeräte-Übergaben werden nicht als Elektroprüfung importiert\n";
} finally {
    R::close();
}
