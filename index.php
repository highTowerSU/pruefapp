<?php
require_once __DIR__ . '/lib/lib.inc.php';

// Keep technical details in the server log and present users with a useful, safe error page.
$renderApplicationError = static function (string $requestId): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $safeId = htmlspecialchars($requestId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safePath = htmlspecialchars((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vorübergehend nicht verfügbar</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8f9fa;color:#212529;margin:0;padding:2rem}.box{max-width:42rem;margin:8vh auto;background:#fff;border:1px solid #dee2e6;border-radius:.75rem;padding:2rem;box-shadow:0 .25rem 1rem #0001}h1{margin-top:0;font-size:1.5rem}.muted{color:#6c757d;font-size:.9rem}.actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem}a{display:inline-block;padding:.6rem 1rem;border-radius:.4rem;text-decoration:none;background:#0d6efd;color:#fff}a.secondary{background:#6c757d}</style></head><body><main class="box"><h1>Diese Seite konnte gerade nicht geladen werden.</h1><p>Die Anwendung hat einen internen Fehler festgestellt. Deine Daten wurden nicht absichtlich verändert.</p><p>Bitte versuche es erneut. Wenn der Fehler bleibt, gib dem Support diese Vorgangs-ID:</p><p><strong>' . $safeId . '</strong></p><p class="muted">Betroffene Seite: ' . $safePath . '</p><div class="actions"><a href="javascript:location.reload()">Erneut versuchen</a><a class="secondary" href="' . htmlspecialchars(url_for('geraete'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Zur Geräteübersicht</a></div></main></body></html>';
};
$handleApplicationFailure = static function (Throwable $throwable) use ($renderApplicationError): void {
    $requestId = strtoupper(bin2hex(random_bytes(4)));
    error_log('[pruefapp][' . $requestId . '] ' . get_class($throwable) . ': ' . $throwable->getMessage() . ' in ' . $throwable->getFile() . ':' . $throwable->getLine() . PHP_EOL . $throwable->getTraceAsString());
    $renderApplicationError($requestId);
};
set_exception_handler($handleApplicationFailure);
register_shutdown_function(static function () use ($renderApplicationError): void {
    $last = error_get_last();
    if (!$last || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    $requestId = strtoupper(bin2hex(random_bytes(4)));
    error_log('[pruefapp][' . $requestId . '] Fatal error: ' . ($last['message'] ?? 'Unbekannter Fehler') . ' in ' . ($last['file'] ?? '?') . ':' . ($last['line'] ?? '?'));
    $renderApplicationError($requestId);
});
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/CourseController.php';
require_once __DIR__ . '/controllers/ParticipantController.php';
require_once __DIR__ . '/controllers/SubmissionController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/TenantController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/HelpController.php';
require_once __DIR__ . '/controllers/StructureController.php';
require_once __DIR__ . '/controllers/DeviceController.php';
require_once __DIR__ . '/controllers/InspectionController.php';

$routes = [
    ['GET', '/', fn($params, $isHx) => HomeController::index($params, $isHx)],
    ['GET', '/kurse', fn($params, $isHx) => [303, ['Location' => url_for('geraete')], '']],
    ['GET', '/kurse/tabelle', fn($params, $isHx) => CourseController::table($params, $isHx)],
    ['POST', '/kurse', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['DELETE', '/kurse/{id}', fn($params, $isHx) => CourseController::delete($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer', fn($params, $isHx) => ParticipantController::index($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/zeilen/neu', fn($params, $isHx) => ParticipantController::newRow($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/{participantId}/zeile', fn($params, $isHx) => ParticipantController::row($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/{participantId}/bearbeiten', fn($params, $isHx) => ParticipantController::edit($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer', fn($params, $isHx) => ParticipantController::store($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer/{participantId}', fn($params, $isHx) => ParticipantController::update($params, $isHx)],
    ['DELETE', '/kurse/{id}/teilnehmer/{participantId}', fn($params, $isHx) => ParticipantController::delete($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/import', fn($params, $isHx) => ParticipantController::import($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer/import', fn($params, $isHx) => ParticipantController::import($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/druck', fn($params, $isHx) => ParticipantController::print($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/export', fn($params, $isHx) => ParticipantController::export($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/api', fn($params, $isHx) => ParticipantController::api($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer/api', fn($params, $isHx) => ParticipantController::api($params, $isHx)],
    ['GET', '/kurse/{id}/einstellungen', fn($params, $isHx) => CourseController::showSettings($params, $isHx)],
    ['POST', '/kurse/{id}/einstellungen', fn($params, $isHx) => CourseController::showSettings($params, $isHx)],
    ['GET', '/kurse/{id}/link', fn($params, $isHx) => CourseController::linkSettings($params, $isHx)],
    ['POST', '/kurse/{id}/link', fn($params, $isHx) => CourseController::linkSettings($params, $isHx)],
    ['GET', '/hilfe', fn($params, $isHx) => HelpController::index($params, $isHx)],
    ['GET', '/struktur', fn($params, $isHx) => StructureController::index($params, $isHx)],
    ['POST', '/struktur/kunden', fn($params, $isHx) => StructureController::createCustomer($params, $isHx)],
    ['POST', '/struktur/standorte', fn($params, $isHx) => StructureController::createSite($params, $isHx)],
    ['POST', '/struktur/gebaeude', fn($params, $isHx) => StructureController::createBuilding($params, $isHx)],
    ['POST', '/struktur/etagen', fn($params, $isHx) => StructureController::createFloor($params, $isHx)],
    ['POST', '/struktur/bereiche', fn($params, $isHx) => StructureController::saveArea($params, $isHx)],
    ['POST', '/struktur/raeume', fn($params, $isHx) => StructureController::createRoom($params, $isHx)],
    ['POST', '/struktur/raeume/{id}/loeschen', fn($params, $isHx) => StructureController::deleteRoom($params, $isHx)],
    ['POST', '/struktur/raeume/{id}/geraete-verschieben', fn($params, $isHx) => StructureController::moveDevices($params, $isHx)],
    ['POST', '/struktur/{type}/{id}/loeschen', fn($params, $isHx) => StructureController::delete($params, $isHx)],
    ['GET', '/geraete', fn($params, $isHx) => DeviceController::index($params, $isHx)],
    ['GET', '/geraete/suche', fn($params, $isHx) => DeviceController::lookup($params, $isHx)],
    ['POST', '/geraete', fn($params, $isHx) => DeviceController::save($params, $isHx)],
    ['GET', '/geraete/{deviceId}/pruefungen/neu', fn($params, $isHx) => InspectionController::create($params, $isHx)],
    ['POST', '/geraete/{deviceId}/pruefungen/neu', fn($params, $isHx) => InspectionController::create($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/bearbeiten', fn($params, $isHx) => InspectionController::edit($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/bearbeiten', fn($params, $isHx) => InspectionController::edit($params, $isHx)],
    ['GET', '/admin/pruefungen/import', fn($params, $isHx) => InspectionController::import($params, $isHx)],
    ['POST', '/admin/pruefungen/import', fn($params, $isHx) => InspectionController::import($params, $isHx)],
    ['POST', '/admin/pruefungen/import/{id}/abbrechen', fn($params, $isHx) => InspectionController::cancelPhoenixJob($params, $isHx)],
    ['GET', '/admin/pruefungen/import/{id}/status', fn($params, $isHx) => InspectionController::phoenixStatus($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/bericht', fn($params, $isHx) => InspectionController::report($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}', fn($params, $isHx) => InspectionController::detail($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/loeschen', fn($params, $isHx) => InspectionController::delete($params, $isHx)],
    ['GET', '/admin/nutzer', fn($params, $isHx) => AdminController::users($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/rolle', fn($params, $isHx) => AdminController::updateUserRole($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/kunden', fn($params, $isHx) => AdminController::updateUserCustomers($params, $isHx)],
    ['GET', '/admin/audit-log', fn($params, $isHx) => AdminController::auditLog($params, $isHx)],
    ['GET', '/mandanten', fn($params, $isHx) => TenantController::index($params, $isHx)],
    ['GET', '/mandanten/neu', fn($params, $isHx) => TenantController::create($params, $isHx)],
    ['POST', '/mandanten/neu', fn($params, $isHx) => TenantController::store($params, $isHx)],
    ['GET', '/mandanten/{id}/bearbeiten', fn($params, $isHx) => TenantController::edit($params, $isHx)],
    ['POST', '/mandanten/{id}/bearbeiten', fn($params, $isHx) => TenantController::update($params, $isHx)],
    ['POST', '/mandanten/{id}/standard', fn($params, $isHx) => TenantController::makeDefault($params, $isHx)],
    ['POST', '/mandanten/{id}/loeschen', fn($params, $isHx) => TenantController::delete($params, $isHx)],
    ['GET', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['POST', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['GET', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
    ['POST', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes) {
    return Router::dispatch($routes, $isHx);
});

$kernel();
