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
    ApplicationFailureService::record($requestId, $throwable);
    $renderApplicationError($requestId);
};
set_exception_handler($handleApplicationFailure);
register_shutdown_function(static function () use ($renderApplicationError): void {
    $last = error_get_last();
    if (!$last || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    $requestId = strtoupper(bin2hex(random_bytes(4)));
    $error = new ErrorException((string) ($last['message'] ?? 'Unbekannter Fehler'), 0, (int) ($last['type'] ?? E_ERROR), (string) ($last['file'] ?? '?'), (int) ($last['line'] ?? 0));
    error_log('[pruefapp][' . $requestId . '] Fatal error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
    ApplicationFailureService::record($requestId, $error, true);
    $renderApplicationError($requestId);
});
$renderNotFound = static function (): string {
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seite nicht gefunden</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8f9fa;color:#212529;margin:0;padding:2rem}.box{max-width:42rem;margin:8vh auto;background:#fff;border:1px solid #dee2e6;border-radius:.75rem;padding:2rem;box-shadow:0 .25rem 1rem #0001}h1{margin-top:0;font-size:1.5rem}.actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem}a{display:inline-block;padding:.6rem 1rem;border-radius:.4rem;text-decoration:none;background:#0d6efd;color:#fff}</style></head><body><main class="box"><h1>Diese Seite wurde nicht gefunden.</h1><p>Der Link ist möglicherweise veraltet oder die Adresse enthält einen Tippfehler.</p><div class="actions"><a href="' . htmlspecialchars(url_for('geraete'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Zur Geräteübersicht</a></div></main></body></html>';
};
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/CourseController.php';
require_once __DIR__ . '/controllers/ParticipantController.php';
require_once __DIR__ . '/controllers/SubmissionController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/TenantController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/HelpController.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/DownloadController.php';
require_once __DIR__ . '/controllers/StructureController.php';
require_once __DIR__ . '/controllers/DeviceController.php';
require_once __DIR__ . '/controllers/DeviceMediaController.php';
require_once __DIR__ . '/controllers/RoomMediaController.php';
require_once __DIR__ . '/controllers/StructureMediaController.php';
require_once __DIR__ . '/controllers/VocabularyController.php';
require_once __DIR__ . '/controllers/InspectionController.php';
require_once __DIR__ . '/controllers/InspectionCompanionController.php';
require_once __DIR__ . '/controllers/CustomerInfoController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/controllers/BillingController.php';
require_once __DIR__ . '/controllers/InspectionTypeController.php';
require_once __DIR__ . '/lib/SevDeskClient.php';

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
    ['GET', '/profil', fn($params, $isHx) => ProfileController::index($params, $isHx)],
    ['POST', '/profil', fn($params, $isHx) => ProfileController::index($params, $isHx)],
    ['GET', '/admin/nutzer/{userId}/profil', fn($params, $isHx) => ProfileController::index($params, $isHx)],
    ['POST', '/admin/nutzer/{userId}/profil', fn($params, $isHx) => ProfileController::index($params, $isHx)],
    ['GET', '/admin/nutzer/{userId}/profil/nachweis/{certificateId}', fn($params, $isHx) => ProfileController::certificate($params, $isHx)],
    ['GET', '/admin/nutzer/{userId}/profil/befaehigung/{qualificationId}', fn($params, $isHx) => ProfileController::qualificationProof($params, $isHx)],
    ['GET', '/profil/nachweis/{certificateId}', fn($params, $isHx) => ProfileController::certificate($params, $isHx)],
    ['GET', '/profil/befaehigung/{qualificationId}', fn($params, $isHx) => ProfileController::qualificationProof($params, $isHx)],
    ['GET', '/downloads', fn($params, $isHx) => DownloadController::index($params, $isHx)],
    ['GET', '/downloads/benachrichtigungen', fn($params, $isHx) => DownloadController::notifications($params, $isHx)],
    ['POST', '/downloads/{id}/gelesen', fn($params, $isHx) => DownloadController::markRead($params, $isHx)],
    ['POST', '/downloads/benachrichtigung/{id}/gelesen', fn($params, $isHx) => DownloadController::markNotificationRead($params, $isHx)],
    ['POST', '/downloads/gelesen', fn($params, $isHx) => DownloadController::markAllRead($params, $isHx)],
    ['GET', '/hilfe/dokument/{file}', fn($params, $isHx) => HelpController::document($params, $isHx)],
    ['GET', '/struktur', fn($params, $isHx) => StructureController::index($params, $isHx)],
    ['POST', '/struktur/massenaktion', fn($params, $isHx) => StructureController::bulkAction($params, $isHx)],
    ['GET', '/kunden/{id}/infos', fn($params, $isHx) => CustomerInfoController::index($params, $isHx)],
    ['POST', '/kunden/{id}/infos', fn($params, $isHx) => CustomerInfoController::save($params, $isHx)],
    ['POST', '/kunden/{id}/infos/upload', fn($params, $isHx) => CustomerInfoController::uploadMultiple($params, $isHx)],
    ['POST', '/kunden/{id}/infos/{infoId}/titel', fn($params, $isHx) => CustomerInfoController::updateTitle($params, $isHx)],
    ['GET', '/kunden/{id}/infos/{infoId}/bearbeiten', fn($params, $isHx) => CustomerInfoController::edit($params, $isHx)],
    ['POST', '/kunden/{id}/infos/{infoId}/loeschen', fn($params, $isHx) => CustomerInfoController::delete($params, $isHx)],
    ['GET', '/kundeninfos/{id}', fn($params, $isHx) => CustomerInfoController::view($params, $isHx)],
    ['GET', '/kundeninfos/{id}/datei', fn($params, $isHx) => CustomerInfoController::file($params, $isHx)],
    ['POST', '/struktur/kunden', fn($params, $isHx) => StructureController::createCustomer($params, $isHx)],
    ['GET', '/struktur/kunden/{id}/sevdesk-adressen', fn($params, $isHx) => StructureController::sevdeskCustomerAddresses($params, $isHx)],
    ['POST', '/struktur/standorte', fn($params, $isHx) => StructureController::createSite($params, $isHx)],
    ['POST', '/struktur/gebaeude', fn($params, $isHx) => StructureController::createBuilding($params, $isHx)],
    ['POST', '/struktur/etagen', fn($params, $isHx) => StructureController::createFloor($params, $isHx)],
    ['POST', '/struktur/bereiche', fn($params, $isHx) => StructureController::saveArea($params, $isHx)],
    ['POST', '/struktur/raeume', fn($params, $isHx) => StructureController::createRoom($params, $isHx)],
    ['POST', '/struktur/raeume/{id}/loeschen', fn($params, $isHx) => StructureController::deleteRoom($params, $isHx)],
    ['POST', '/struktur/raeume/{id}/geraete-verschieben', fn($params, $isHx) => StructureController::moveDevices($params, $isHx)],
    ['POST', '/struktur/raeume/{id}/fotos', fn($params, $isHx) => RoomMediaController::upload($params, $isHx)],
    ['GET', '/struktur/raeume/fotos/{id}', fn($params, $isHx) => RoomMediaController::file($params, $isHx)],
    ['POST', '/struktur/raeume/fotos/{id}/loeschen', fn($params, $isHx) => RoomMediaController::delete($params, $isHx)],
    ['POST', '/struktur/{type}/{id}/fotos', fn($params, $isHx) => StructureMediaController::upload($params, $isHx)],
    ['GET', '/struktur/fotos/{mediaId}', fn($params, $isHx) => StructureMediaController::file($params, $isHx)],
    ['POST', '/struktur/fotos/{mediaId}/aktualisieren', fn($params, $isHx) => StructureMediaController::update($params, $isHx)],
    ['POST', '/struktur/fotos/{mediaId}/loeschen', fn($params, $isHx) => StructureMediaController::delete($params, $isHx)],
    ['POST', '/struktur/{type}/{id}/loeschen', fn($params, $isHx) => StructureController::delete($params, $isHx)],
    ['GET', '/geraete', fn($params, $isHx) => DeviceController::index($params, $isHx)],
    ['GET', '/pruefungen', fn($params, $isHx) => InspectionController::index($params, $isHx)],
    ['POST', '/pruefungen/massenaktion', fn($params, $isHx) => InspectionController::bulkAction($params, $isHx)],
    ['GET', '/pruefungen/{id}', fn($params, $isHx) => InspectionController::detail($params, $isHx)],
    ['GET', '/pruefungen/{id}/bericht', fn($params, $isHx) => InspectionController::report($params, $isHx)],
    ['POST', '/geraete/auswahl', fn($params, $isHx) => DeviceController::selection($params, $isHx)],
    ['POST', '/geraete/massenaktion', fn($params, $isHx) => DeviceController::bulkAction($params, $isHx)],
    ['GET', '/geraete/suche', fn($params, $isHx) => DeviceController::lookup($params, $isHx)],
    ['GET', '/geraete/stammdaten-optionen', fn($params, $isHx) => DeviceController::vocabularyOptions($params, $isHx)],
    ['POST', '/geraete/{id}/stammdaten-aus-letzter-pruefung', fn($params, $isHx) => DeviceController::copyLatestInspectionData($params, $isHx)],
    ['POST', '/geraete/fotos/vorlaeufig', fn($params, $isHx) => DeviceMediaController::stageNewDevice($params, $isHx)],
    ['POST', '/geraete/{id}/fotos', fn($params, $isHx) => DeviceMediaController::uploadDevice($params, $isHx)],
    ['GET', '/geraete/fotos/{id}', fn($params, $isHx) => DeviceMediaController::file($params, $isHx)],
    ['POST', '/geraete/fotos/{id}/typenschild-analysieren', fn($params, $isHx) => DeviceMediaController::analyseTypePlate($params, $isHx)],
    ['POST', '/geraete/fotos/{id}/aktualisieren', fn($params, $isHx) => DeviceMediaController::update($params, $isHx)],
    ['POST', '/geraete/fotos/{id}/loeschen', fn($params, $isHx) => DeviceMediaController::delete($params, $isHx)],
    ['POST', '/pruefungen/{id}/fotos', fn($params, $isHx) => DeviceMediaController::uploadInspection($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/companion', fn($params, $isHx) => InspectionCompanionController::panel($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/companion', fn($params, $isHx) => InspectionCompanionController::panel($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/companion/status', fn($params, $isHx) => InspectionCompanionController::status($params, $isHx)],
    ['GET', '/companion/eingang', fn($params, $isHx) => InspectionCompanionController::inbox($params, $isHx)],
    ['GET', '/companion/ereignisse', fn($params, $isHx) => InspectionCompanionController::events($params, $isHx)],
    ['POST', '/companion/eingang/{id}/uebernehmen', fn($params, $isHx) => InspectionCompanionController::useItem($params, $isHx)],
    ['POST', '/companion/eingang/{id}/foto-uebernehmen', fn($params, $isHx) => InspectionCompanionController::adoptPhoto($params, $isHx)],
    ['POST', '/companion/eingang/{id}/foto-zu-entwurf', fn($params, $isHx) => InspectionCompanionController::adoptPhotoForDraft($params, $isHx)],
    ['GET', '/companion/eingang/{id}/foto', fn($params, $isHx) => InspectionCompanionController::photoFile($params, $isHx)],
    ['GET', '/companion/{token}/qr', fn($params, $isHx) => InspectionCompanionController::qr($params, $isHx)],
    ['GET', '/companion/{token}', fn($params, $isHx) => InspectionCompanionController::open($params, $isHx)],
    ['POST', '/companion/{token}/barcode', fn($params, $isHx) => InspectionCompanionController::barcode($params, $isHx)],
    ['POST', '/companion/{token}/barcode-foto', fn($params, $isHx) => InspectionCompanionController::barcodePhoto($params, $isHx)],
    ['POST', '/companion/{token}/fotos', fn($params, $isHx) => InspectionCompanionController::photo($params, $isHx)],
    ['POST', '/geraete', fn($params, $isHx) => DeviceController::save($params, $isHx)],
    ['GET', '/admin/stammdaten', fn($params, $isHx) => VocabularyController::index($params, $isHx)],
    ['POST', '/admin/stammdaten', fn($params, $isHx) => VocabularyController::index($params, $isHx)],
    ['POST', '/geraete/export', fn($params, $isHx) => ReportController::export($params, $isHx)],
    ['GET', '/geraete/zip/{id}/status', fn($params, $isHx) => ReportController::zipStatus($params, $isHx)],
    ['POST', '/geraete/zip/{id}/abbrechen', fn($params, $isHx) => ReportController::cancelPdfJob($params, $isHx)],
    ['POST', '/admin/audit-log/job/{id}/abbrechen', fn($params, $isHx) => ReportController::cancelCronJob($params, $isHx)],
    ['POST', '/admin/audit-log/export', fn($params, $isHx) => AdminController::exportAuditRuns($params, $isHx)],
    ['GET', '/geraete/zip/{id}/download', fn($params, $isHx) => ReportController::zipDownload($params, $isHx)],
    ['GET', '/admin/abrechnung', fn($params, $isHx) => BillingController::index($params, $isHx)],
    ['GET', '/admin/abrechnung/rechnung/{id}', fn($params, $isHx) => BillingController::invoice($params, $isHx)],
    ['POST', '/admin/abrechnung/rechnung/{id}/sevdesk-entwurf-loeschen', fn($params, $isHx) => BillingController::deleteDraftInvoice($params, $isHx)],
    ['POST', '/admin/abrechnung/export', fn($params, $isHx) => BillingController::export($params, $isHx)],
    ['POST', '/admin/abrechnung/pruefung/{id}/abrechenbarkeit', fn($params, $isHx) => BillingController::eligibility($params, $isHx)],
    ['POST', '/admin/abrechnung/pruefung/{id}/export-zuruecksetzen', fn($params, $isHx) => BillingController::resetExport($params, $isHx)],
    ['GET', '/geraete/{deviceId}/pruefungen/neu', fn($params, $isHx) => InspectionController::create($params, $isHx)],
    ['POST', '/geraete/{deviceId}/pruefungen/neu', fn($params, $isHx) => InspectionController::create($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/bearbeiten', fn($params, $isHx) => InspectionController::edit($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/bearbeiten', fn($params, $isHx) => InspectionController::edit($params, $isHx)],
    ['GET', '/admin/pruefungen/import', fn($params, $isHx) => InspectionController::import($params, $isHx)],
    ['POST', '/admin/pruefungen/import', fn($params, $isHx) => InspectionController::import($params, $isHx)],
    ['POST', '/admin/pruefungen/import/{id}/abbrechen', fn($params, $isHx) => InspectionController::cancelPhoenixJob($params, $isHx)],
    ['POST', '/admin/pruefungen/import/{id}/archivieren', fn($params, $isHx) => InspectionController::archivePhoenixJob($params, $isHx)],
    ['GET', '/admin/pruefungen/import/{id}/status', fn($params, $isHx) => InspectionController::phoenixStatus($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}/bericht', fn($params, $isHx) => InspectionController::report($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/bericht/neu-erzeugen', fn($params, $isHx) => InspectionController::regenerateReport($params, $isHx)],
    ['GET', '/admin/pruefungen/{id}', fn($params, $isHx) => InspectionController::detail($params, $isHx)],
    ['POST', '/admin/pruefungen/{id}/loeschen', fn($params, $isHx) => InspectionController::delete($params, $isHx)],
    ['GET', '/admin/nutzer', fn($params, $isHx) => AdminController::users($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/rolle', fn($params, $isHx) => AdminController::updateUserRole($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/login-as', fn($params, $isHx) => AdminController::loginAs($params, $isHx)],
    ['POST', '/admin/nutzer/login-as/stop', fn($params, $isHx) => AdminController::stopLoginAs($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/kunden', fn($params, $isHx) => AdminController::updateUserCustomers($params, $isHx)],
    ['GET', '/admin/audit-log', fn($params, $isHx) => AdminController::auditLog($params, $isHx)],
    ['GET', '/admin/debug/pruefungen', fn($params, $isHx) => AdminController::inspectionDebug($params, $isHx)],
    ['POST', '/admin/debug/pruefungen/legacy-migration', fn($params, $isHx) => AdminController::enqueueLegacyClassificationMigration($params, $isHx)],
    ['GET', '/api/debug/inspection', fn($params, $isHx) => AdminController::inspectionApiDebug($params, $isHx)],
    ['GET', '/mandanten', fn($params, $isHx) => TenantController::index($params, $isHx)],
    ['GET', '/mandanten/neu', fn($params, $isHx) => TenantController::create($params, $isHx)],
    ['POST', '/mandanten/neu', fn($params, $isHx) => TenantController::store($params, $isHx)],
    ['GET', '/mandanten/{id}/sevdesk-benutzer', fn($params, $isHx) => TenantController::sevDeskUsers($params, $isHx)],
    ['GET', '/mandanten/{id}/bearbeiten', fn($params, $isHx) => TenantController::edit($params, $isHx)],
    ['POST', '/mandanten/{id}/bearbeiten', fn($params, $isHx) => TenantController::update($params, $isHx)],
    ['POST', '/mandanten/{id}/standard', fn($params, $isHx) => TenantController::makeDefault($params, $isHx)],
    ['POST', '/mandanten/{id}/loeschen', fn($params, $isHx) => TenantController::delete($params, $isHx)],
    ['GET', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['GET', '/admin/pruefarten', fn($params, $isHx) => InspectionTypeController::index($params, $isHx)],
    ['POST', '/admin/pruefarten', fn($params, $isHx) => InspectionTypeController::index($params, $isHx)],
    ['POST', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['GET', '/admin/konfiguration/ki-provider', fn($params, $isHx) => SettingsController::aiProvider($params, $isHx)],
    ['POST', '/admin/konfiguration/ki-provider', fn($params, $isHx) => SettingsController::aiProvider($params, $isHx)],
    ['GET', '/admin/konfiguration/ki-oauth/callback', fn($params, $isHx) => SettingsController::vocabularyOAuthCallback($params, $isHx)],
    ['GET', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
    ['POST', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes, $renderNotFound) {
    $response = Router::dispatch($routes, $isHx);
    if (($response[0] ?? 0) === 404 && trim(strip_tags((string) ($response[2] ?? ''))) === '404 Not Found') {
        return [404, [], $renderNotFound()];
    }
    return $response;
});

$kernel();
