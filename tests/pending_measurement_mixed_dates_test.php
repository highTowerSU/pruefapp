<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-mixed-measurements-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'mixed_measurement_import_test',
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
    $room = R::dispense('room');
    $room->floor_id = 1;
    $room->name = 'Testraum';
    $roomId = R::store($room);
    $device = R::dispense('device');
    $device->room_id = $roomId;
    $device->external_number = 'test-measurement-device';
    $device->name = 'Messdaten-Testgerät';
    $deviceId = R::store($device);
    $csvRows = ['Speicher Nr;Bezeichnung;Prüfdatum;Prüfergebnis;RPE Wert;RPE Einheit;RPE Ergebnis'];
    for ($slot = 1; $slot <= 76; $slot++) {
        $date = $slot <= 4 ? '2026-08-14' : ($slot <= 24 ? '2026-08-15' : '2026-09-25');
        $csvDate = date('d/m/Y', strtotime($date));
        $inspection = R::dispense('inspection');
        $inspection->external_number = 'test-' . $slot;
        $inspection->device_id = $deviceId;
        $inspection->dedupe_key = 'mixed-measurement-' . $slot;
        $inspection->public_id = 'mixed-measurement-' . $slot;
        $inspection->source_type = 'manual';
        $inspection->test_date = $date;
        $inspection->storage_slot = (string) $slot;
        $inspection->result_status = 'in_progress';
        R::store($inspection);
        $csvRows[] = sprintf('%03d;Klasse I;%s;bestanden;0;20;Ohm;bestanden', $slot, $csvDate);
    }
    $csv = $root . '/mixed-dates.csv';
    file_put_contents($csv, implode("\n", $csvRows) . "\n");

    $service = new ElectricalInspectionImportService();
    $stats = $service->importPendingMeasurements($csv, '');
    if ($stats['updated'] !== 76 || $stats['skipped'] !== 0) {
        throw new RuntimeException('Drei Datumsblöcke aus derselben CSV müssen vollständig zugeordnet werden.');
    }
    foreach ([1 => '2026-08-14', 5 => '2026-08-15', 25 => '2026-09-25', 76 => '2026-09-25'] as $slot => $date) {
        $row = R::getRow('SELECT test_date, result_status, csv_row_json FROM inspection WHERE external_number=?', ['test-' . $slot]);
        if (($row['test_date'] ?? '') !== $date || ($row['result_status'] ?? '') !== 'passed' || trim((string) ($row['csv_row_json'] ?? '')) === '') {
            throw new RuntimeException('Speicherplatz ' . $slot . ' wurde nicht mit seinem eigenen Prüfdatum aktualisiert.');
        }
    }

    foreach ([['test-aug15-slot1', '2026-08-15', '1'], ['test-fallback-slot77', '2026-09-25', '77']] as [$number, $date, $slot]) {
        $inspection = R::dispense('inspection');
        $inspection->external_number = $number;
        $inspection->device_id = $deviceId;
        $inspection->dedupe_key = 'mixed-measurement-' . $number;
        $inspection->public_id = 'mixed-measurement-' . $number;
        $inspection->source_type = 'manual';
        $inspection->test_date = $date;
        $inspection->storage_slot = $slot;
        $inspection->result_status = 'in_progress';
        R::store($inspection);
    }
    $fallbackCsv = $root . '/fallback-date.csv';
    file_put_contents($fallbackCsv, implode("\n", [
        $csvRows[0],
        '001;Klasse I;15/08/2026;bestanden;0;21;Ohm;bestanden',
        '077;Klasse I;;bestanden;0;22;Ohm;bestanden',
        '078;Klasse I;15/08/2026;bestanden;0;23;Ohm;bestanden',
    ]) . "\n");
    $fallbackStats = $service->importPendingMeasurements($fallbackCsv, '2026-09-25');
    if ($fallbackStats['updated'] !== 2 || $fallbackStats['skipped'] !== 1) {
        throw new RuntimeException('Ersatzdatum darf das vorhandene CSV-Prüfdatum nicht überschreiben.');
    }
    foreach (['test-aug15-slot1', 'test-fallback-slot77'] as $number) {
        if ((string) R::getCell('SELECT result_status FROM inspection WHERE external_number=?', [$number]) !== 'passed') {
            throw new RuntimeException('Prüfung ' . $number . ' wurde nicht zugeordnet.');
        }
    }
    if ((string) R::getCell('SELECT result_status FROM inspection WHERE external_number=?', ['test-1']) !== 'passed') {
        throw new RuntimeException('Gleicher Speicherplatz an anderem Datum wurde überschrieben.');
    }

    echo "PASS: Messdaten werden pro CSV-Zeile über Prüfdatum und Speicherplatz zugeordnet\n";
} finally {
    R::close();
}
