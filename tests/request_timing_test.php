<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestTimingService.php';

RequestTimingService::start('bootstrap');
usleep(1000);
RequestTimingService::stop('bootstrap');
RequestTimingService::start('inspection_report');
$header = RequestTimingService::header();

if (!preg_match('/^total;dur=\d+\.\d, bootstrap;dur=\d+\.\d, inspection_report;dur=\d+\.\d$/', $header)) {
    throw new RuntimeException('Server-Timing muss abgeschlossene und laufende Phasen im Browserformat ausgeben.');
}

$htmx = (string) file_get_contents(dirname(__DIR__) . '/lib/htmx.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
if (!str_contains($htmx, "'Server-Timing'")
    || !str_contains($controller, "start('inspection_report')")
    || !str_contains($controller, "start('inspection_persist')")) {
    throw new RuntimeException('Prüfungsspeicherung und Berichterstellung müssen im Response-Header messbar sein.');
}

echo "PASS: Server-Timing misst App-Start und Prüfungsschritte\n";
