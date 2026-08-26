<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/PhoenixSyncService.php');

foreach ([
    [str_contains($controller, "summary === 'phoenix-latest'"), 'Der geschützte Phoenix-Stichtag-Endpunkt fehlt.'],
    [str_contains($service, 'function latestCreatedAudits'), 'Die Phoenix-Leseabfrage für neu angelegte Prüfungen fehlt.'],
    [str_contains($service, "'sortField' => 'created_at'") && str_contains($service, "'sortOrder' => 'desc'"), 'Phoenix-Prüfungen werden nicht nach Anlegezeit absteigend gelesen.'],
    [str_contains($service, "'created_by'") && str_contains($service, "'created_at'"), 'Ersteller oder Anlegezeit fehlen für die Stichtagsermittlung.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Phoenix-Stichtag liest die zuletzt angelegten Prüfungen nur lesend\n";
