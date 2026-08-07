<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$component = (string) file_get_contents(dirname(__DIR__) . '/templates/media_upload_component.php');
$roomService = (string) file_get_contents(dirname(__DIR__) . '/lib/RoomMediaService.php');
$roomController = (string) file_get_contents(dirname(__DIR__) . '/controllers/RoomMediaController.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$structure = (string) file_get_contents(dirname(__DIR__) . '/templates/structure_index.php');
$devicePanel = (string) file_get_contents(dirname(__DIR__) . '/templates/device_media_panel.php');
$inspectionPanel = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_media_panel.php');
$customerInfo = (string) file_get_contents(dirname(__DIR__) . '/templates/customer_info_index.php');
$customerController = (string) file_get_contents(dirname(__DIR__) . '/controllers/CustomerInfoController.php');
$inboxJs = (string) file_get_contents(dirname(__DIR__) . '/public/js/companion-inbox.js');

$checks = [
    [str_contains($component, 'data-media-upload-component') && str_contains($component, 'data-companion-upload-photo') && str_contains($component, 'data-media-paste') && str_contains($component, 'hx-encoding="multipart/form-data"'), 'Die wiederverwendbare Foto-Komponente hat keinen einheitlichen lokalen, Companion- und HTMX-Upload.'],
    [str_contains($devicePanel, "render_template('media_upload_component.php'") && str_contains($inspectionPanel, "render_template('media_upload_component.php'"), 'Geräte- und Prüfungsfotos verwenden nicht dieselbe Upload-Komponente.'],
    [str_contains($customerInfo, "render_template('media_upload_component.php'") && str_contains($customerInfo, 'inspection_companion_inbox.php') && str_contains($customerController, 'bool $isHx = false') && str_contains($customerController, 'if ($isHx)'), 'Kundeninfos unterstützen keine Companion-Fotos oder verschachteln bei HTMX das gesamte Layout.'],
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS room_media') && str_contains($roomService, 'storeUpload') && str_contains($roomController, 'current_user_can_access_customer'), 'Raumfotos werden nicht sicher gespeichert oder berechtigt ausgeliefert.'],
    [str_contains($routes, '/struktur/raeume/{id}/fotos') && str_contains($structure, 'RoomMediaController::panel') && str_contains($structure, 'inspection_companion_inbox.php'), 'Raumfotos oder Companion-Auswahl sind in der Raumansicht nicht erreichbar.'],
    [str_contains($inboxJs, 'bindMediaUploadComponents') && str_contains($inboxJs, 'data-media-upload-photo-choose') && str_contains($inboxJs, 'picker=upload'), 'Companion-Fotos können nicht in die wiederverwendbare Upload-Komponente übernommen werden.'],
];

foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Wiederverwendbare Foto-Uploads\n";
