<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/lib/InspectionCompanionService.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionCompanionController.php');
$profileController = (string) file_get_contents(dirname(__DIR__) . '/controllers/ProfileController.php');
$mobile = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_companion_mobile.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.php');
$panel = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_companion_panel.php');
$profileTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/profile.php');
$qrService = (string) file_get_contents(dirname(__DIR__) . '/lib/ServerQrCodeService.php');
$qrRenderer = (string) file_get_contents(dirname(__DIR__) . '/bin/render-qrcode-svg.js');

$checks = [
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS inspection_companion_session') && str_contains($schema, 'token_hash'), 'Companion-Sitzungen werden nicht sicher und kurzlebig gespeichert.'],
    [str_contains($service, 'random_bytes(24)') && str_contains($service, "state IN ('pending', 'connected')") && str_contains($service, 'owner_user_id'), 'Die Kopplung besitzt keinen ausreichenden Token-, Ablauf- oder Nutzerbezug.'],
    [str_contains($service, 'time() + 28800') && str_contains($controller, 'inspectionForCompanion') && str_contains($controller, '$session[\'owner_user_id\']'), 'Die Companion-Kopplung bleibt nicht für die Arbeitsdauer ohne erneuten Login nutzbar.'],
    [str_contains($controller, 'InspectionCompanionService::connect') && str_contains($controller, 'DeviceMediaService::storeUpload') && str_contains($controller, 'pruef_companion_barcode') && str_contains($service, 'owner_user_id'), 'Companion-Scans oder Fotos werden nicht serverseitig abgesichert verarbeitet.'],
    [str_contains($mobile, 'BarcodeDetector') && str_contains($mobile, 'facingMode') && str_contains($mobile, 'capture="environment"'), 'Die mobile Companion-Ansicht unterstützt Scanner oder Kamera nicht.'],
    [str_contains($panel, 'absolute_url_for') && str_contains($profileTemplate, 'absolute_url_for') && str_contains($qrService, 'proc_open') && str_contains($qrRenderer, "type: 'svg'") && str_contains($controller, 'ServerQrCodeService::svg(absolute_url_for') && str_contains($controller, 'function qr'), 'Der Pairing-Link wird nicht als vollständiger serverseitiger QR-Code bereitgestellt.'],
    [str_contains($routes, '/companion/{token}/qr') && str_contains($routes, '/companion/{token}') && str_contains($routes, '/companion/{token}/barcode') && str_contains($schema, "#^/companion/[a-f0-9]{48}(/|$)#"), 'Companion-Routen oder der passwortfreie temporäre Arbeitszugang fehlen.'],
    [str_contains($layout, 'action-nav-empty') && str_contains($layout, 'min-height: 2.65rem'), 'Die Aktionsnavigation reserviert keinen Platz gegen Layout-Sprünge.'],
    [str_contains($service, 'activeForUser') && str_contains($service, 'disconnectSession') && str_contains($profileController, 'disconnect_companion') && str_contains($mobile, 'hx-encoding'), 'Companion-Sitzungen können nicht zentral im Profil verwaltet werden.'],
    [str_contains($profileController, 'if ($adminView) return forbidden_response();') && str_contains($profileTemplate, 'if (empty($adminView))'), 'Companion-QR-Codes dürfen nicht in fremden Benutzerprofilen sichtbar oder nutzbar sein.'],
];

foreach ($checks as [$ok, $message]) if (!$ok) throw new RuntimeException($message);
echo "PASS: Prüf-Companion und stabile Aktionsnavigation\n";
