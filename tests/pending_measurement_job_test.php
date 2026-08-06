<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/phoenix_sync_worker.php');
$jobs = (string) file_get_contents(dirname(__DIR__) . '/lib/BackgroundJobService.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');

foreach ([
    [$controller, "BackgroundJobService::enqueue('pending_measurement_import'"],
    [$controller, "BackgroundJobService::enqueue('directory_import'"],
    [$controller, 'move_uploaded_file($tmp, $storedFile)'],
    [$worker, "'pending_measurement_import'"],
    [$worker, 'importPendingMeasurements($realCsvPath'],
    [$worker, 'Keine Importdateien gefunden.'],
    [$jobs, "'pending_measurement_import' => 'Messdaten importieren'"],
    [$template, 'Im Hintergrund importieren'],
    [$template, 'zentral als Hintergrundaufgaben verarbeitet'],
    [$template, 'pruefappImportAutoRefreshBound'],
    [$template, "event.target?.id !== 'import-auto-refresh'"],
    [$template, 'Benachrichtigungen'],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) throw new RuntimeException('Messdatenimport läuft nicht vollständig über Hintergrundjob, Cron und Benachrichtigung: ' . $needle);
}

echo "PASS: Messdatenimport wird als Hintergrundaufgabe verarbeitet\n";
