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
    $device = R::dispense('device'); $device->room_id = 0; $device->name = 'Aktuell'; $device->external_number = '1000000'; $device->warming_device = 0; $deviceId = (int) R::store($device);
    $manual = R::dispense('inspection'); $manual->device_id = $deviceId; $manual->dedupe_key = 'manual'; $manual->public_id = 'prf-manual'; $manual->source_type = 'manual'; $manual->external_number = 'P-M'; $manual->test_date = '2026-07-01'; $manual->inspection_type = 'SK1'; $manual->storage_slot = '22'; R::store($manual);
    $manualDevice = R::dispense('device'); $manualDevice->room_id = 0; $manualDevice->name = 'Prüfweb-Gerät'; $manualDevice->external_number = '1000001'; $manualDeviceId = (int) R::store($manualDevice);
    $matchingManual = R::dispense('inspection'); $matchingManual->device_id = $manualDeviceId; $matchingManual->dedupe_key = 'manual-match'; $matchingManual->public_id = 'prf-manual-match'; $matchingManual->source_type = 'manual'; $matchingManual->external_number = 'WEB-1'; $matchingManual->test_date = '2025-07-01'; $matchingManual->inspection_type = 'SK1'; $matchingManual->storage_slot = '17'; $matchingManual->result_status = 'in_progress'; R::store($matchingManual);
    $old = R::dispense('inspection'); $old->device_id = $deviceId; $old->dedupe_key = 'old'; $old->public_id = 'prf-old'; $old->source_type = 'reconciled'; $old->external_number = '001-23'; $old->test_date = '2024-07-01'; R::store($old);
    $pseudo = R::dispense('inspection'); $pseudo->device_id = 0; $pseudo->dedupe_key = 'pseudo'; $pseudo->public_id = 'prf-pseudo'; $pseudo->source_type = 'manual'; $pseudo->external_number = '001-23'; $pseudo->test_date = '2024-07-01'; R::store($pseudo);
    $passedDevice = R::dispense('device'); $passedDevice->room_id = 0; $passedDevice->name = 'Prüfweb-Gerät bestanden'; $passedDevice->external_number = '1000002'; $passedDevice->warming_device = 0; $passedDeviceId = (int) R::store($passedDevice);
    $passedManual = R::dispense('inspection'); $passedManual->device_id = $passedDeviceId; $passedManual->dedupe_key = 'manual-passed'; $passedManual->public_id = 'prf-manual-passed'; $passedManual->source_type = 'manual'; $passedManual->external_number = 'WEB-2'; $passedManual->test_date = '2026-08-11'; $passedManual->inspection_type = 'SCHUTZKLASSEI'; $passedManual->storage_slot = '034'; $passedManual->result_status = 'passed'; R::store($passedManual);
    file_put_contents($root . '/sources/records.json', json_encode([
        ['number' => 'P-1', 'external_number' => '1000001', 'date' => '2025-07-01', 'type' => 'Kabel', 'result_status' => 'passed', 'audit_ok' => true],
        ['number' => '', 'external_number' => '', 'date' => '2026-07-01', 'type' => 'SK1', 'storage_slot' => '22', 'audit_ok' => true],
        ['number' => '', 'external_number' => '', 'date' => '2025-07-01', 'type' => 'SK1'],
        ['number' => '', 'external_number' => '', 'date' => '2026-08-11', 'type' => 'KLASSEI', 'storage_slot' => '034', 'result_status' => 'bestanden'],
    ]));
    file_put_contents($root . '/sources/standalone-measurements.csv', "Speicher Nr;RPE Wert\n1;0,12\n");
    file_put_contents($root . '/sources/phoenix.json', json_encode([
        'module_scoped_id' => 10, 'id' => 253741, 'resource_id' => 253741, 'number' => '100018880', 'date' => '2024-03-17',
        'type' => ['brezel_name' => 'Wiederholungsprüfung SK1'], 'room' => 'N104', 'audit_ok' => true,
    ]));
    $result = (new ImportCandidateRebuildService())->prepare($root . '/sources', 1, static function (): void {});
    if (($result['automatic'] ?? 0) !== 1 || ($result['manual_kept'] ?? 0) !== 3 || ($result['number_missing'] ?? 0) !== 1) throw new RuntimeException('Gesammelte Kandidaten wurden nicht erst nach der quellenübergreifenden Gruppierung bewertet.');
    if ((int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='manual'") !== 3 || (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='reconciled'") !== 1 || (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE external_number='001-23'") !== 0) throw new RuntimeException('Der Neuaufbau bewahrt echte Prüfweb-Prüfungen nicht oder entfernt Altimporte ohne Gerät nicht.');
    $groupedSources = (int) R::getCell("SELECT COUNT(*) FROM importcandidate WHERE run_id=? AND group_key=(SELECT group_key FROM importcandidate WHERE source_kind='manual' AND source_inspection_id=? LIMIT 1)", [(int) $result['run_id'], (int) $matchingManual->id]);
    if ($groupedSources !== 2) throw new RuntimeException('Prüfweb- und JSON-Quelle wurden nicht erst nach dem vollständigen Sammeln zusammengeführt.');
    $slotGroupedSources = (int) R::getCell("SELECT COUNT(*) FROM importcandidate WHERE run_id=? AND group_key=(SELECT group_key FROM importcandidate WHERE source_kind='manual' AND source_inspection_id=? LIMIT 1)", [(int) $result['run_id'], (int) $manual->id]);
    if ($slotGroupedSources !== 2) throw new RuntimeException('Ein eindeutiger Speicherplatz-/Datums-Treffer wurde nicht mit der manuellen Prüfung zusammengeführt.');
    $passedGroupedSources = (int) R::getCell("SELECT COUNT(*) FROM importcandidate WHERE run_id=? AND group_key=(SELECT group_key FROM importcandidate WHERE source_kind='manual' AND source_inspection_id=? LIMIT 1)", [(int) $result['run_id'], (int) $passedManual->id]);
    if ($passedGroupedSources !== 2 || (string) R::getCell('SELECT result_status FROM inspection WHERE id=?', [(int) $passedManual->id]) !== 'bestanden') throw new RuntimeException('Gleichwertige Ergebnisse und SK1-Schreibweisen wurden nicht automatisch in die Prüfweb-Prüfung übernommen.');
    $phoenix = R::getRow("SELECT i.external_number AS inspection_number, d.external_number AS device_number FROM inspection i JOIN device d ON d.id=i.device_id WHERE i.external_number LIKE '%253741%'");
    if (!str_starts_with((string) ($phoenix['inspection_number'] ?? ''), '253741-') || ($phoenix['device_number'] ?? '') !== '100018880') throw new RuntimeException('Phoenix-Prüfungs-ID und Gerätenummer wurden nicht getrennt übernommen.');
    if ((int) R::getCell("SELECT COUNT(*) FROM importcandidate WHERE source_path LIKE '%standalone-measurements.csv'") !== 0) throw new RuntimeException('Einzelne Mess-CSV darf keinen historischen Kandidaten erzeugen.');
    echo "PASS: Kandidaten-Neuaufbau leert Altimporte, bewahrt Prüfweb und importiert nur klare Gruppen\n";
} finally { R::close(); }
