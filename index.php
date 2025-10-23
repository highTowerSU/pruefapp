<?php
require_once __DIR__ . '/lib/lib.inc.php';
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/CourseController.php';
require_once __DIR__ . '/controllers/ParticipantController.php';
require_once __DIR__ . '/controllers/SubmissionController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/CompanyController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/HelpController.php';

$routes = [
    ['GET', '/', fn($params, $isHx) => HomeController::index($params, $isHx)],
    ['GET', '/kurse', fn($params, $isHx) => CourseController::index($params, $isHx)],
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
    ['GET', '/kurse/{id}/teilnehmer/moodle', fn($params, $isHx) => ParticipantController::moodleImport($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer/moodle', fn($params, $isHx) => ParticipantController::moodleImport($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer/api', fn($params, $isHx) => ParticipantController::api($params, $isHx)],
    ['POST', '/kurse/{id}/teilnehmer/api', fn($params, $isHx) => ParticipantController::api($params, $isHx)],
    ['GET', '/kurse/{id}/einstellungen', fn($params, $isHx) => CourseController::showSettings($params, $isHx)],
    ['POST', '/kurse/{id}/einstellungen', fn($params, $isHx) => CourseController::showSettings($params, $isHx)],
    ['GET', '/kurse/{id}/link', fn($params, $isHx) => CourseController::linkSettings($params, $isHx)],
    ['POST', '/kurse/{id}/link', fn($params, $isHx) => CourseController::linkSettings($params, $isHx)],
    ['GET', '/hilfe', fn($params, $isHx) => HelpController::index($params, $isHx)],
    ['GET', '/admin/nutzer', fn($params, $isHx) => AdminController::users($params, $isHx)],
    ['POST', '/admin/nutzer/{id}/rolle', fn($params, $isHx) => AdminController::updateUserRole($params, $isHx)],
    ['GET', '/admin/audit-log', fn($params, $isHx) => AdminController::auditLog($params, $isHx)],
    ['GET', '/firmen', fn($params, $isHx) => CompanyController::index($params, $isHx)],
    ['GET', '/firmen/neu', fn($params, $isHx) => CompanyController::create($params, $isHx)],
    ['POST', '/firmen/neu', fn($params, $isHx) => CompanyController::store($params, $isHx)],
    ['GET', '/firmen/{id}/bearbeiten', fn($params, $isHx) => CompanyController::edit($params, $isHx)],
    ['POST', '/firmen/{id}/bearbeiten', fn($params, $isHx) => CompanyController::update($params, $isHx)],
    ['POST', '/firmen/{id}/standard', fn($params, $isHx) => CompanyController::makeDefault($params, $isHx)],
    ['POST', '/firmen/{id}/loeschen', fn($params, $isHx) => CompanyController::delete($params, $isHx)],
    ['GET', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['POST', '/admin/konfiguration', fn($params, $isHx) => SettingsController::general($params, $isHx)],
    ['GET', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
    ['POST', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes) {
    return Router::dispatch($routes, $isHx);
});

$kernel();
