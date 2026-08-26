<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-import-reset-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'import_reset_test',
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
    foreach ([['csv', 'CSV-1'], ['json', 'JSON-1'], ['manual', 'MANUAL-1']] as $index => [$source, $number]) {
        $device = R::dispense('device');
        $device->room_id = 0;
        $device->name = $number;
        $device->external_number = $number;
        $device->warming_device = 0;
        $deviceId = (int) R::store($device);
        $inspection = R::dispense('inspection');
        $inspection->device_id = $deviceId;
        $inspection->dedupe_key = 'reset-' . $index;
        $inspection->public_id = 'prf-reset-' . $index;
        $inspection->source_type = $source;
        $inspection->external_number = $number;
        $inspection->test_date = '2026-08-01';
        $inspection->billing_eligibility = 'billable';
        $inspection->billing_status = 'exported';
        R::store($inspection);
    }
    $invoice = R::dispense('billinginvoice');
    $invoice->status = 'draft';
    R::store($invoice);

    $preview = ImportedInspectionResetService::preview();
    if ($preview['imported_inspections'] !== 2 || $preview['manual_inspections_kept'] !== 1 || $preview['billing_invoices_to_reset'] !== 1) {
        throw new RuntimeException('Der Reset-Vorlauf zählt Import-, manuelle oder Rechnungsdaten falsch.');
    }
    $backup = ImportedInspectionResetService::backup();
    $result = ImportedInspectionResetService::execute($backup);
    if (!is_file($backup) || !is_file($backup . '.sha256') || $result['imported_inspections'] !== 2) {
        throw new RuntimeException('Vor dem Reset wurde kein prüfbares Backup angelegt.');
    }
    if ((int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type IN ('csv', 'json')") !== 0
        || (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type='manual'") !== 1
        || (int) R::getCell('SELECT COUNT(*) FROM billinginvoice') !== 0
        || (int) R::getCell('SELECT COUNT(*) FROM device') !== 1
    ) {
        throw new RuntimeException('Der Reset hat Importdaten nicht entfernt oder manuelle Daten nicht erhalten.');
    }
    $manual = R::getRow("SELECT billing_eligibility, billing_status FROM inspection WHERE source_type='manual'");
    if (($manual['billing_eligibility'] ?? '') !== 'not_billable' || ($manual['billing_status'] ?? '') !== 'not_exported') {
        throw new RuntimeException('Der Rechnungsreset hat den manuellen Prüfstatus nicht zurückgesetzt.');
    }
    echo "PASS: Import-Reset sichert SQLite, leert Import/Rechnung und bewahrt manuelle Prüfungen\n";
} finally {
    R::close();
}
