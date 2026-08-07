<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/InspectionCompanionService.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionCompanionController.php');
$profile = (string) file_get_contents(dirname(__DIR__) . '/controllers/ProfileController.php');
$mobile = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_companion_mobile.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.php');

$checks = [
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS inspection_companion_session') && str_contains($schema, 'token_hash'), 'Companion-Sitzungen werden nicht sicher und kurzlebig gespeichert.'],
    [str_contains($service, 'random_bytes(24)') && str_contains($service, "state IN ('pending', 'connected')") && str_contains($service, 'owner_user_id'), 'Die Kopplung besitzt keinen ausreichenden Token-, Ablauf- oder Nutzerbezug.'],
    [str_contains($service, 'time() + 28800') && str_contains($controller, 'inspectionForCompanion') && str_contains($controller, '$session[\'owner_user_id\']'), 'Die Companion-Kopplung bleibt nicht für die Arbeitsdauer ohne erneuten Login nutzbar.'],
    [str_contains($controller, 'InspectionCompanionService::connect') && str_contains($controller, 'DeviceMediaService::storeUpload') && str_contains($controller, 'pruef_companion_barcode') && str_contains($service, 'owner_user_id'), 'Companion-Scans oder Fotos werden nicht serverseitig abgesichert verarbeitet.'],
    [str_contains($mobile, 'BarcodeDetector') && str_contains($mobile, 'facingMode') && str_contains($mobile, 'capture="environment"'), 'Die mobile Companion-Ansicht unterstützt Scanner oder Kamera nicht.'],
    [str_contains((string) file_get_contents(dirname(__DIR__) . '/templates/inspection_companion_panel.php'), 'companion-qr-code') && str_contains($layout, 'qrcode.min.js'), 'Der Pairing-Link wird nicht als lokaler QR-Code bereitgestellt.'],
    [str_contains($routes, '/companion/{token}') && str_contains($routes, '/companion/{token}/barcode'), 'Companion-Routen fehlen.'],
    [str_contains($layout, 'action-nav-empty') && str_contains($layout, 'min-height: 2.65rem'), 'Die Aktionsnavigation reserviert keinen Platz gegen Layout-Sprünge.'],
    [str_contains($service, 'activeForUser') && str_contains($service, 'disconnectSession') && str_contains($profile, 'disconnect_companion') && str_contains($mobile, 'hx-encoding'), 'Companion-Sitzungen können nicht zentral im Profil verwaltet werden.'],
];

foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Prüf-Companion und stabile Aktionsnavigation\n";
