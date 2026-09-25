<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/phoenix_sync_worker.php');
$jobs = (string) file_get_contents(dirname(__DIR__) . '/lib/BackgroundJobService.php');
$importService = (string) file_get_contents(dirname(__DIR__) . '/lib/ElectricalInspectionImportService.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');
$measurementPage = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_measurement_import.php');
$measurementForm = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_measurement_import_form.php');
$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');

foreach ([
    [$controller, "BackgroundJobService::enqueue('pending_measurement_import'"],
    [$controller, "WHERE i.source_type = 'manual' AND"],
    [$controller, "BackgroundJobService::enqueue('directory_import'"],
    [$controller, 'move_uploaded_file($tmp, $storedFile)'],
    [$worker, "'pending_measurement_import'"],
    [$worker, 'importPendingMeasurements($realCsvPath'],
    [$worker, 'Keine Importdateien gefunden.'],
    [$jobs, "'pending_measurement_import' => 'Messdaten importieren'"],
    [$template, "render_template('inspection_measurement_import_form.php')"],
    [$measurementPage, "render_template('inspection_measurement_import_form.php')"],
    [$measurementForm, 'Im Hintergrund importieren'],
    [$measurementForm, 'Upload läuft …'],
    [$navbar, '?view=measurement'],
    [$controller, "'content' => render_template('inspection_measurement_import.php'"],
    [$template, 'laufen im Hintergrund'],
    [$template, 'pruefappImportAutoRefreshBound'],
    [$template, 'id="report-regeneration"'],
    [$template, 'Legacy-PDFs von 2024 und älter bleiben unverändert.'],
    [$template, "event.target?.id !== 'import-auto-refresh'"],
    [$template, 'Benachrichtigungen'],
    [$importService, 'if ($hasBenning && $odsPath === null)'],
    [$importService, 'importPendingMeasurements($path, trim((string) ($defaults[\'test_date\'] ?? \'\')))'],
    [$importService, "test_date = ? AND source_type = 'manual'"],
    [$measurementForm, 'ausschließlich in Prüfweb angelegte Prüfungen'],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) throw new RuntimeException('Messdatenimport läuft nicht vollständig über Hintergrundjob, Cron und Benachrichtigung: ' . $needle);
}

$importAction = strpos($controller, "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && (\$_POST['action'] ?? '') === 'pending_measurement_import')");
$overviewLoad = strpos($controller, '$jobs = self::phoenixJobs();');
if ($importAction === false || $overviewLoad === false || $importAction >= $overviewLoad) {
    throw new RuntimeException('Der Messdaten-Upload muss vor dem Aufbau der Importübersicht vorgemerkt werden.');
}
$quickView = strpos($controller, "if (\$_SERVER['REQUEST_METHOD'] === 'GET' && (\$_GET['view'] ?? '') === 'measurement')");
if ($quickView === false || $quickView >= $overviewLoad) {
    throw new RuntimeException('Der Messdaten-Einstieg darf die umfangreiche Importübersicht nicht laden.');
}
if (substr_count($controller, '$pendingMeasurementsByDate = self::pendingMeasurementsByDate();') !== 1) {
    throw new RuntimeException('Die offenen Prüfungen dürfen pro Seitenaufruf nur einmal geladen werden.');
}

echo "PASS: Messdatenimport wird als Hintergrundaufgabe verarbeitet\n";
