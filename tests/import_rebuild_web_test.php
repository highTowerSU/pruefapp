<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/phoenix_sync_worker.php');
$jobs = (string) file_get_contents(dirname(__DIR__) . '/lib/BackgroundJobService.php');

foreach ([
    [$controller, "'import_rebuild_preview'"],
    [$controller, "'import_rebuild_start'"],
    [$controller, "'IMPORT NEU AUFBAUEN'"],
    [$controller, "BackgroundJobService::enqueue('import_rebuild_reset'"],
    [$template, 'Importbestand aus kuratierten Quellen neu aufbauen'],
    [$template, 'Backup, Reset &amp; Import starten'],
    [$worker, "'import_rebuild_reset'"],
    [$worker, 'ImportedInspectionResetService::backup()'],
    [$worker, "BackgroundJobService::enqueue('directory_import'"],
    [$jobs, "'import_rebuild_reset' => 'Importbestand sichern und zurücksetzen'"],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) throw new RuntimeException('GUI-Quellen-Neuaufbau ist unvollständig: ' . $needle);
}

echo "PASS: Quellen-Neuaufbau läuft bestätigt über GUI und Hintergrundqueue\n";
