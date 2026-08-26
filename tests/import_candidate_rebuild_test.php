<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-candidate-' . bin2hex(random_bytes(5));
mkdir($root . '/sources', 0770, true);
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'candidate_rebuild_test', 'APP_DATABASE_PATH' => $root . '/db.sqlite', 'APP_DATA_ROOT' => $root . '/data',
    'APP_OIDC_ISSUER_URL' => 'https://login.example.test/realms/test', 'APP_OIDC_CLIENT_ID' => 'test-client', 'APP_OIDC_CLIENT_SECRET' => 'test-secret',
], true) . ';');
define('CENEOS_CONFIG_FILE', $config);
$_SERVER['SCRIPT_NAME'] = '/pruefapp/index.php'; $_SERVER['PHP_SELF'] = '/pruefapp/index.php'; $_SERVER['REQUEST_URI'] = '/pruefapp/'; $_SERVER['REQUEST_METHOD'] = 'GET';
require_once dirname(__DIR__) . '/lib/lib.inc.php';

try {
    $device = R::dispense('device'); $device->room_id = 0; $device->name = 'Aktuell'; $device->external_number = 'M-1'; $device->warming_device = 0; $deviceId = (int) R::store($device);
    $manual = R::dispense('inspection'); $manual->device_id = $deviceId; $manual->dedupe_key = 'manual'; $manual->public_id = 'prf-manual'; $manual->source_type = 'manual'; $manual->external_number = 'P-M'; $manual->test_date = '2026-07-01'; $manual->inspection_type = 'SK1'; R::store($manual);
    $old = R::dispense('inspection'); $old->device_id = $deviceId; $old->dedupe_key = 'old'; $old->public_id = 'prf-old'; $old->source_type = 'json'; $old->external_number = 'P-O'; $old->test_date = '2024-07-01'; R::store($old);
    file_put_contents($root . '/sources/records.json', json_encode([
        ['number' => 'P-1', 'external_number' => 'G-1', 'date' => '2025-07-01', 'type' => 'SK1', 'audit_ok' => true],
        ['number' => '', 'external_number' => '', 'date' => '2025-07-01', 'type' => 'SK1'],
    ]));
    $result = (new ImportCandidateRebuildService())->prepare($root . '/sources', 1, static function (): void {});
    if (($result['automatic'] ?? 0) !== 1 || ($result['number_missing'] ?? 0) !== 1) throw new RuntimeException('Klare und nummernlose Kandidaten wurden nicht getrennt behandelt.');
    if ((int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='manual'") !== 1 || (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='json'") !== 0 || (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='reconciled'") !== 1) throw new RuntimeException('Der Neuaufbau bewahrt manuelle Prüfungen nicht oder importiert Kandidaten falsch.');
    echo "PASS: Kandidaten-Neuaufbau leert Altimporte, bewahrt Prüfweb und importiert nur klare Gruppen\n";
} finally { R::close(); }
