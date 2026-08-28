<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');

foreach ([
    [str_contains($controller, "\$_GET['directory']"), 'Der Web-Diagnoseendpunkt nimmt keinen Importpfad an.'],
    [str_contains($controller, 'importDirectoryApiDebug'), 'Die sichere Importpfad-Diagnose fehlt.'],
    [str_contains($controller, "'/tmp/berichte'"), 'Der vorgesehene Importstamm ist nicht freigegeben.'],
    [str_contains($controller, 'RecursiveDirectoryIterator'), 'Importdateien werden nicht rekursiv geprüft.'],
    [str_contains($controller, 'csv_without_matching_ods_count'), 'CSV/ODS-Paarprüfung fehlt.'],
    [str_contains($controller, 'pending_import_jobs'), 'Wartende Importaufgaben werden nicht ausgegeben.'],
    [str_contains($controller, 'recent_import_jobs'), 'Abgeschlossene oder fehlgeschlagene Importaufgaben sind nicht diagnosierbar.'],
    [str_contains($controller, "summary === 'candidate-match'") && str_contains($controller, 'candidateMatchApiDebug'), 'Der Debug-Abgleich zwischen manuellen Prüfungen und Kandidatenquellen fehlt.'],
    [str_contains($controller, "summary === 'inspection-overview'") && str_contains($controller, 'devices_with_multiple_room_snapshots'), 'Die geschützte Übersicht für Status- und Raumhistorien fehlt.'],
    [!str_contains($controller, 'file_get_contents($file->getPathname())'), 'Der Diagnoseendpunkt darf keine Importdateiinhalte auslesen.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Token-geschützte Importpfad-Diagnose prüft nur Metadaten\n";
