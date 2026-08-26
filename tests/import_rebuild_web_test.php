<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/phoenix_sync_worker.php');
$jobs = (string) file_get_contents(dirname(__DIR__) . '/lib/BackgroundJobService.php');

foreach ([
    [$controller, "'import_candidate_start'"],
    [$controller, "'import_candidate_decide'"],
    [$controller, "BackgroundJobService::enqueue('import_candidate_rebuild'"],
    [$template, 'Importbestand als Kandidaten neu aufbauen'],
    [$template, 'KANDIDATEN NEU AUFBAUEN'],
    [$template, 'Feldweise zusammenführen'],
    [$worker, "'import_candidate_rebuild'"],
    [$worker, 'ImportCandidateRebuildService'],
    [$jobs, "'import_candidate_rebuild' => 'Importkandidaten vorbereiten'"],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) throw new RuntimeException('GUI-Quellen-Neuaufbau ist unvollständig: ' . $needle);
}

echo "PASS: Kandidaten-Neuaufbau läuft bestätigt über GUI und Hintergrundqueue\n";
