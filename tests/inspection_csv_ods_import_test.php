<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-csv-ods-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'csv_ods_import_test',
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
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZipArchive wird für den ODS-Integrationstest benötigt.');
    $csv = $root . '/AK_Elektro-25_08_15.csv';
    file_put_contents($csv, implode("\n", [
        'Speicher Nr;Prüfdatum;Prüfergebnis;RPE Wert;RPE Einheit;RPE Ergebnis',
        '1;15.08.2025;bestanden;0,18;Ohm;bestanden',
        '2;15.08.2025;nicht bestanden;0,65;Ohm;nicht bestanden',
    ]));
    $content = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:spreadsheet><table:table>'
        . '<table:table-row><table:table-cell><text:p>Nr. alt</text:p></table:table-cell><table:table-cell><text:p>Nr. neu</text:p></table:table-cell><table:table-cell><text:p>Raumnummer</text:p></table:table-cell><table:table-cell><text:p>Bemerkung/Kommentar</text:p></table:table-cell><table:table-cell><text:p>Notiz Gerät</text:p></table:table-cell><table:table-cell><text:p>Speicherplatz</text:p></table:table-cell><table:table-cell><text:p>Regiezeit</text:p></table:table-cell></table:table-row>'
        . '<table:table-row><table:table-cell><text:p>ALT-1</text:p></table:table-cell><table:table-cell><text:p>NEU-1</text:p></table:table-cell><table:table-cell><text:p>K016</text:p></table:table-cell><table:table-cell><text:p>Kommentar 1</text:p></table:table-cell><table:table-cell><text:p>Beamer</text:p></table:table-cell><table:table-cell><text:p>1</text:p></table:table-cell><table:table-cell><text:p>6</text:p></table:table-cell></table:table-row>'
        . '<table:table-row><table:table-cell><text:p>ALT-2</text:p></table:table-cell><table:table-cell><text:p>NEU-2</text:p></table:table-cell><table:table-cell><text:p>K017</text:p></table:table-cell><table:table-cell><text:p>Kommentar 2</text:p></table:table-cell><table:table-cell><text:p>Monitor</text:p></table:table-cell><table:table-cell><text:p>2</text:p></table:table-cell><table:table-cell><text:p></text:p></table:table-cell></table:table-row>'
        . '</table:table></office:spreadsheet></office:body></office:document-content>';
    $ods = new ZipArchive();
    if ($ods->open($root . '/AK_Elektro-25_08_15.ods', ZipArchive::CREATE) !== true) throw new RuntimeException('ODS-Testdatei konnte nicht erstellt werden.');
    $ods->addFromString('content.xml', $content);
    $ods->close();

    $stats = (new ElectricalInspectionImportService())->importDirectory($csv);
    if ($stats['imported'] !== 2 || $stats['skipped'] !== 0) throw new RuntimeException('CSV/ODS-Paar wurde nicht vollständig importiert.');
    $withRegie = R::getRow("SELECT i.*, d.external_number AS device_number, d.legacy_number, d.name AS device_name FROM inspection i JOIN device d ON d.id=i.device_id WHERE i.external_number='NEU-1-25'");
    $withoutRegie = R::getRow("SELECT regie_minutes, result_status FROM inspection WHERE external_number='NEU-2-25'");
    $raw = json_decode((string) ($withRegie['raw_json'] ?? ''), true);
    $measurements = json_decode((string) ($withRegie['measurements_json'] ?? ''), true);
    if (($withRegie['test_date'] ?? '') !== '2025-08-15' || (int) ($withRegie['regie_minutes'] ?? -1) !== 6
        || ($raw['ods_regiezeit'] ?? '') !== '6' || ($withRegie['device_name'] ?? '') !== 'Beamer'
        || ($withRegie['legacy_number'] ?? '') !== 'ALT-1' || ($withRegie['room_snapshot'] ?? '') !== 'K016'
        || ($withRegie['result_status'] ?? '') !== 'passed' || (string) ($measurements[0]['value'] ?? '') !== '0,18'
        || (int) ($withoutRegie['regie_minutes'] ?? -1) !== 0 || ($withoutRegie['result_status'] ?? '') !== 'failed') {
        throw new RuntimeException('CSV-/ODS-Felder oder Regiezeit wurden nicht vollständig und korrekt übernommen.');
    }
    echo "PASS: CSV/ODS übernimmt Geräte-, Mess-, Ergebnis-, Raum- und Regiedaten mit und ohne Regiezeit\n";
} finally {
    R::close();
}
