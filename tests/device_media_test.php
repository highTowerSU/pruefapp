<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/DeviceMediaService.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceMediaController.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$deviceTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/device_media_panel.php');
$inspectionTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_media_panel.php');

$checks = [
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS device_media') && str_contains($schema, 'device_media_analysis'), 'Fotodokumentation ist nicht persistierbar.'],
    [str_contains($service, 'storeUpload') && str_contains($service, 'analyseTypePlate') && str_contains($service, "'type_plate'") && str_contains($service, "'image_url'"), 'Upload oder serverseitige Typenschildanalyse fehlen.'],
    [str_contains($controller, 'uploadDevice') && str_contains($controller, 'uploadInspection') && str_contains($controller, 'current_user_can_access_customer'), 'Fotos sind nicht für Gerät und Prüfung sicher zugreifbar.'],
    [str_contains($routes, '/geraete/{id}/fotos') && str_contains($routes, '/pruefungen/{id}/fotos'), 'Foto-Routen fehlen.'],
    [str_contains($deviceTemplate, 'Typenschild erkennen') && str_contains($deviceTemplate, 'Vorschlag in Formular übernehmen') && str_contains($deviceTemplate, 'hx-post'), 'Geräte-Fotooberfläche oder direkte KI-Rückmeldung fehlen.'],
    [str_contains($inspectionTemplate, 'Fotodokumentation') && str_contains($inspectionTemplate, 'Mangel'), 'Prüfungsfotos können nicht als Mängeldokumentation erfasst werden.'],
];

foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Fotodokumentation und Typenschildanalyse\n";
