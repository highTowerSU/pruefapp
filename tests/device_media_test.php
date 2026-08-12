<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/DeviceMediaService.php');
$draftService = (string) file_get_contents(dirname(__DIR__) . '/lib/DeviceDraftMediaService.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceMediaController.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$deviceTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/device_media_panel.php');
$inspectionTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_media_panel.php');
$photoCard = (string) file_get_contents(dirname(__DIR__) . '/templates/media_photo_card.php');

$checks = [
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS device_media') && str_contains($schema, 'device_media_analysis'), 'Fotodokumentation ist nicht persistierbar.'],
    [str_contains($service, 'storeUpload') && str_contains($service, 'analyseTypePlate') && str_contains($service, "'type_plate'") && str_contains($service, "'image_url'") && str_contains($service, 'Auf dem Typenschild wurden keine eindeutigen Stammdaten erkannt.'), 'Upload, serverseitige Typenschildanalyse oder Prüfung auf leere Vorschläge fehlen.'],
    [str_contains($controller, 'uploadDevice') && str_contains($controller, 'uploadInspection') && str_contains($controller, 'function update') && str_contains($controller, 'current_user_can_access_customer'), 'Fotos sind nicht für Gerät und Prüfung sicher zugreifbar oder bearbeitbar.'],
    [str_contains($routes, '/geraete/{id}/fotos') && str_contains($routes, '/pruefungen/{id}/fotos') && str_contains($routes, '/geraete/fotos/{id}/aktualisieren'), 'Foto-Routen fehlen.'],
    [str_contains($deviceTemplate, 'Typenschild erkennen') && str_contains($deviceTemplate, 'Vorschlag in Formular übernehmen') && str_contains($deviceTemplate, 'hx-post'), 'Geräte-Fotooberfläche oder direkte KI-Rückmeldung fehlen.'],
    [str_contains($inspectionTemplate, 'Fotodokumentation') && str_contains($inspectionTemplate, 'Mangel') && str_contains($inspectionTemplate, '<details class="card mb-3"'), 'Prüfungsfotos können nicht als einklappbare Mängeldokumentation erfasst werden.'],
    [str_contains($deviceTemplate, "render_template('media_photo_card.php'") && str_contains($inspectionTemplate, "render_template('media_photo_card.php'") && str_contains($photoCard, 'name="media_type"') && str_contains($photoCard, 'name="caption"') && str_contains($photoCard, '/loeschen'), 'Fotoart, Bemerkung und Löschen sind nicht einheitlich direkt an jedem Foto möglich.'],
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS device_draft_media') && str_contains($draftService, 'function stage') && str_contains($draftService, 'function consume') && str_contains($controller, 'stageNewDevice') && str_contains($routes, '/geraete/fotos/vorlaeufig'), 'Neue Geräte unterstützen keinen sicheren Vorab-Upload mit späterer Übernahme.'],
];

foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Fotodokumentation und Typenschildanalyse\n";
