<?php

declare(strict_types=1);

use RedBeanPHP\R;

$root = sys_get_temp_dir() . '/pruefapp-inspection-migration-' . bin2hex(random_bytes(5));
mkdir($root, 0770, true);
mkdir($root . '/sessions', 0770, true);
session_save_path($root . '/sessions');
$config = $root . '/config.php';
file_put_contents($config, '<?php return ' . var_export([
    'APP_STORAGE_NAMESPACE' => 'inspection_migration_test',
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
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/lib/lib.inc.php';

try {
    if (!is_dir(app_data_root() . '/reports/original')) mkdir(app_data_root() . '/reports/original', 0770, true);
    file_put_contents(app_data_root() . '/reports/original/legacy.pdf', '%PDF-legacy');
    $device = R::dispense('device');
    $device->room_id = 0;
    $device->name = 'Testgerät';
    $device->external_number = 'D-1';
    $device->warming_device = 0;
    $deviceId = (int) R::store($device);

    $legacy = R::dispense('inspection');
    $legacy->device_id = $deviceId;
    $legacy->dedupe_key = 'legacy-test';
    $legacy->source_type = 'json';
    $legacy->external_number = '100011458-24';
    $legacy->test_date = '2024-12-31';
    $legacy->result_status = 'bestanden';
    $legacy->status = 'completed';
    $legacy->report_path = 'reports/original/legacy.pdf';
    $legacy->raw_json = '{"audit_ok":true}';
    $legacy->checklist_json = '[]';
    $legacy->measurements_json = '[]';
    $legacyId = (int) R::store($legacy);

    $imported = R::dispense('inspection');
    $imported->device_id = $deviceId;
    $imported->dedupe_key = 'import-test';
    $imported->source_type = 'json';
    $imported->source_file = 'phoenix.jsonl';
    $imported->external_number = 'NEU-25';
    $imported->test_date = '2025-01-01';
    $imported->examiner = '';
    $imported->protection_class = 'I';
    $imported->result_status = 'bestanden';
    $imported->status = 'completed';
    $imported->raw_json = '{"audit_ok":true,"step0":"Sind keine Schäden an der Beschriftung erkennbar?","result0":"Ja"}';
    $imported->csv_row_json = '{"source":"original"}';
    $imported->checklist_json = '[]';
    $imported->measurements_json = json_encode([
        ['name' => 'RPE', 'value' => '0.2', 'unit' => 'Ohm', 'result' => 'bestanden'],
        ['name' => 'RISO', 'value' => '20', 'unit' => 'MOhm', 'result' => 'bestanden'],
        ['name' => 'IPE', 'value' => '0.1', 'unit' => 'mA', 'result' => 'bestanden'],
    ], JSON_UNESCAPED_UNICODE);
    $importedId = (int) R::store($imported);

    InspectionMigrationService::migrate($legacyId);
    InspectionMigrationService::migrate($importedId);

    $legacyRow = R::getRow('SELECT * FROM inspection WHERE id = ?', [$legacyId]);
    if (($legacyRow['classification'] ?? '') !== 'legacy' || ($legacyRow['result_status'] ?? '') !== 'passed') {
        throw new RuntimeException('Legacy-Prüfung wurde nicht unverändert klassifiziert.');
    }
    if ((int) R::getCell('SELECT COUNT(*) FROM inspection_answer WHERE inspection_id = ?', [$legacyId]) !== 0) {
        throw new RuntimeException('Legacy-Prüfung darf keine erfundene HTML-Struktur erhalten.');
    }
    if ((int) R::getCell("SELECT COUNT(*) FROM inspection_report_asset WHERE inspection_id = ? AND asset_type = 'legacy_original' AND active = 1", [$legacyId]) !== 1) {
        throw new RuntimeException('Legacy-Original-PDF wurde nicht als aktives Asset gesichert.');
    }

    $importedRow = R::getRow('SELECT * FROM inspection WHERE id = ?', [$importedId]);
    if (($importedRow['classification'] ?? '') !== 'migrated_import' || ($importedRow['result_status'] ?? '') !== 'passed') {
        throw new RuntimeException('Importierte Prüfung wurde nicht kanonisch bewertet.');
    }
    if (($importedRow['examiner'] ?? '') !== 'edebertshaeuser@koenigsbl.au') {
        throw new RuntimeException('Importierte Prüfung ohne Prüfer wurde nicht nach der dokumentierten Regel zugeordnet.');
    }
    if (trim((string) ($importedRow['report_path'] ?? '')) !== '') {
        throw new RuntimeException('Import-Original wurde fälschlich als aktueller Prüfapp-Bericht aktiviert.');
    }
    if ((int) R::getCell('SELECT COUNT(*) FROM inspection_answer WHERE inspection_id = ?', [$importedId]) < 7
        || (int) R::getCell('SELECT COUNT(*) FROM inspection_measurement WHERE inspection_id = ?', [$importedId]) !== 3
    ) {
        throw new RuntimeException('Strukturierte Antworten oder Messwerte fehlen nach der Migration.');
    }
    $snapshot = R::getRow('SELECT * FROM inspection_source_snapshot WHERE inspection_id = ?', [$importedId]);
    if (($snapshot['source_row_json'] ?? '') !== '{"source":"original"}' || trim((string) ($snapshot['legacy_row_json'] ?? '')) === '') {
        throw new RuntimeException('Quelldaten wurden vor der Migration nicht vollständig gesichert.');
    }

    // A former migration could have stored data_missing although the imported
    // source explicitly recorded a completed result. A rerun must recover the
    // source result from the immutable snapshot instead of keeping it open.
    R::exec("UPDATE inspection SET result_status = 'data_missing', status = 'data_missing' WHERE id = ?", [$importedId]);
    InspectionMigrationService::migrate($importedId);
    if ((string) R::getCell('SELECT result_status FROM inspection WHERE id = ?', [$importedId]) !== 'passed') {
        throw new RuntimeException('Die erneute Importmigration hat das überlieferte bestandene Ergebnis nicht wiederhergestellt.');
    }

    InspectionMigrationService::migrate($importedId);
    if ((int) R::getCell('SELECT COUNT(*) FROM inspection_source_snapshot WHERE inspection_id = ?', [$importedId]) !== 1) {
        throw new RuntimeException('Wiederholte Migration hat doppelte Sicherungen erzeugt.');
    }

    $legacySourceRoot = $root . '/legacy-pdfs';
    mkdir($legacySourceRoot, 0770, true);
    $legacyOriginal = $legacySourceRoot . '/100011458-Ceneos GmbH.pdf';
    file_put_contents($legacyOriginal, '%PDF-original-legacy');
    set_app_config('phoenix_reports_directory', $legacySourceRoot);
    R::exec("UPDATE inspection SET report_path = 'reports/current/" . $legacyId . ".pdf' WHERE id = ?", [$legacyId]);
    MaintenanceJobHandler::run(
        ['type' => 'phoenix_pdf_restore', 'checkpoint' => [], 'current' => 0, 'total' => 1],
        static function (): void {
        }
    );
    $restoredLegacy = R::getRow('SELECT report_path FROM inspection WHERE id = ?', [$legacyId]);
    if (($restoredLegacy['report_path'] ?? '') !== $legacyOriginal
        || (int) R::getCell("SELECT COUNT(*) FROM inspection_report_asset WHERE inspection_id = ? AND asset_type = 'legacy_original' AND active = 1", [$legacyId]) !== 1
    ) {
        throw new RuntimeException('Legacy-Originalbericht wurde nicht wieder als maßgebliches Dokument verknüpft.');
    }

    // Simulate rows imported after the former all-data migration had already
    // completed: V2 must classify them by date, not leave them as missing data.
    foreach (['100016494-23', '100016495-24'] as $offset => $number) {
        $historical = R::dispense('inspection');
        $historical->device_id = $deviceId;
        $historical->dedupe_key = 'late-legacy-' . $offset;
        $historical->source_type = 'json';
        $historical->external_number = $number;
        $historical->test_date = $offset === 0 ? '2023-07-29' : '2024-07-29';
        $historical->result_status = 'bestanden';
        $historical->status = 'completed';
        $historical->raw_json = '{"audit_ok":true}';
        $historical->checklist_json = '[]';
        $historical->measurements_json = '[]';
        R::store($historical);
    }
    MaintenanceJobHandler::run(
        ['type' => 'legacy_classification_migration', 'checkpoint' => [], 'current' => 0, 'total' => 2],
        static function (): void {
        }
    );
    if ((int) R::getCell("SELECT COUNT(*) FROM inspection WHERE external_number IN ('100016494-23', '100016495-24') AND classification = 'legacy'") !== 2
        || get_app_config('legacy_classification_migration_version', '') !== '2'
    ) {
        throw new RuntimeException('Die gezielte V2-Legacy-Migration hat historische Prüfungen nicht dauerhaft klassifiziert.');
    }

    $savedCheckpoint = [];
    try {
        MaintenanceJobHandler::run(
            ['type' => 'inspection_data_migration', 'payload' => ['type' => 'inspection_data_migration'], 'checkpoint' => [], 'current' => 0, 'total' => 2],
            static function (array $checkpoint) use (&$savedCheckpoint): void {
                $savedCheckpoint = $checkpoint;
                throw new RuntimeException('Zeitabschnitt beendet');
            }
        );
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Zeitabschnitt beendet') throw $exception;
    }
    $firstAfterResume = '';
    try {
        MaintenanceJobHandler::run(
            ['type' => 'inspection_data_migration', 'payload' => ['type' => 'inspection_data_migration'], 'checkpoint' => $savedCheckpoint, 'current' => 1, 'total' => 2],
            static function (array $checkpoint, int $current, int $total, string $number) use (&$firstAfterResume): void {
                $firstAfterResume = $number;
                throw new RuntimeException('Fortsetzung geprüft');
            }
        );
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Fortsetzung geprüft') throw $exception;
    }
    if ($firstAfterResume !== 'NEU-25') throw new RuntimeException('Prüfungsdatenmigration begann nach dem Zeitabschnitt wieder von vorn.');
    MaintenanceJobHandler::run(
        ['type' => 'inspection_data_migration', 'payload' => ['type' => 'inspection_data_migration'], 'checkpoint' => [], 'current' => 0, 'total' => 2],
        static function (): void {
        }
    );
    if (get_app_config('inspection_data_migration_version', '') !== '1') {
        throw new RuntimeException('Abgeschlossene Gesamtmigration wurde nicht dauerhaft markiert.');
    }
    $sourceStatus = new ReflectionMethod(InspectionMigrationService::class, 'statusFromRawSource');
    $sourceStatus->setAccessible(true);
    if ($sourceStatus->invoke(null, ['Prüfergebnis' => 'bestanden']) !== InspectionEvaluationService::PASSED
        || $sourceStatus->invoke(null, ['audit_ok' => false]) !== InspectionEvaluationService::FAILED
    ) {
        throw new RuntimeException('Überlieferte CSV- oder Phoenix-Ergebnisse werden nicht wiederhergestellt.');
    }

    // A late CSV measurement import must enrich an existing unfinished annual
    // inspection, not leave a second artificial "-2" inspection behind.
    $open = R::dispense('inspection');
    $open->device_id = $deviceId;
    $open->dedupe_key = 'open-manual';
    $open->source_type = 'manual';
    $open->external_number = '100012579-26';
    $open->test_date = '2026-08-04';
    $open->result_status = 'in_progress';
    $open->status = 'in_progress';
    $open->raw_json = '{}';
    $open->checklist_json = '[]';
    $open->measurements_json = '[]';
    $openId = (int) R::store($open);
    $duplicate = R::dispense('inspection');
    $duplicate->device_id = $deviceId;
    $duplicate->dedupe_key = 'late-csv-import';
    $duplicate->source_type = 'csv';
    $duplicate->source_file = 'AK_Elektro-26_08_03.csv';
    $duplicate->external_number = '100012579-26-2';
    $duplicate->test_date = '2026-08-06';
    $duplicate->inspection_type = 'Klasse II';
    $duplicate->protection_class = 'II';
    $duplicate->result_status = 'data_missing';
    $duplicate->status = 'data_missing';
    $duplicate->classification = 'migrated_import';
    $duplicate->raw_json = '{"Prüfergebnis":"bestanden"}';
    $duplicate->csv_row_json = '{"Prüfergebnis":"bestanden"}';
    $duplicate->checklist_json = '[]';
    $duplicate->measurements_json = '[]';
    R::store($duplicate);
    $reconciliationMessages = [];
    MaintenanceJobHandler::run(
        ['type' => 'import_result_reconciliation', 'checkpoint' => [], 'current' => 0, 'total' => 1],
        static function (array $checkpoint, int $current, int $total, string $number, string $message) use (&$reconciliationMessages): void {
            $reconciliationMessages[] = $number . ': ' . $message . ' ' . json_encode($checkpoint);
        }
    );
    $remainingDuplicate = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE external_number = '100012579-26-2'");
    $mergedSource = (string) R::getCell('SELECT source_type FROM inspection WHERE id = ?', [$openId]);
    $mergedType = (string) R::getCell('SELECT inspection_type FROM inspection WHERE id = ?', [$openId]);
    if ($remainingDuplicate !== 0 || $mergedSource !== 'csv' || $mergedType !== 'Schutzklasse II') {
        throw new RuntimeException("Später Messdatenimport wurde nicht in die offene Ausgangsprüfung zusammengeführt ({$remainingDuplicate}, {$mergedSource}, {$mergedType}; " . implode(' | ', $reconciliationMessages) . ').');
    }
    $controllerSource = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
    $importTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');
    if (!str_contains($controllerSource, 'i.test_date DESC')
        || !str_contains($controllerSource, 'uksort($pending')
        || !str_contains($importTemplate, "format_datetime_for_display((string) \$pendingDate, 'd.m.Y')")
    ) {
        throw new RuntimeException('Offene Messdaten werden nicht mit aktuellem Datum zuerst und lokal formatiert angezeigt.');
    }
    echo "PASS: Legacy-Schutz, Quellsicherung und idempotente Importmigration\n";
} finally {
    R::close();
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($root);
    }
}
