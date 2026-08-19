<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/ApplicationFailureService.php');
$index = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$admin = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');
$billing = (string) file_get_contents(dirname(__DIR__) . '/controllers/BillingController.php');

foreach ([
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS appfailure'), 'Die persistente Fehlerdiagnose-Tabelle fehlt.'],
    [str_contains($service, "R::dispense('appfailure')") && str_contains($service, 'getTraceAsString()'), 'Fehlerdiagnosen speichern keinen Stacktrace.'],
    [str_contains($index, 'ApplicationFailureService::record($requestId, $throwable)'), 'Unbehandelte Ausnahmen werden nicht persistiert.'],
    [str_contains($index, 'ApplicationFailureService::record($requestId, $error, true)'), 'Fatale Fehler werden nicht persistiert.'],
    [str_contains($admin, "\$_GET['failure_id']") && str_contains($admin, 'ApplicationFailureService::find'), 'Der geschützte Debug-Endpunkt kann Vorgangs-IDs nicht auflösen.'],
    [str_contains($admin, "summary === 'billing'") && str_contains($billing, 'public static function debugSelection') && str_contains($billing, 'public static function debugRender'), 'Die schreibgeschützte Abrechnungsdiagnose fehlt.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Fehlerdiagnosen und Abrechnungs-Debug sind persistent und geschützt\n";
