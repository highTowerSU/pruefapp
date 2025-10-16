<?php
require_once __DIR__ . '/lib/lib.inc.php';
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/CourseController.php';
require_once __DIR__ . '/controllers/ParticipantController.php';
require_once __DIR__ . '/controllers/SubmissionController.php';

$routes = [
    ['GET', '/', fn($params, $isHx) => HomeController::index($params, $isHx)],
    ['GET', '/kurse', fn($params, $isHx) => CourseController::index($params, $isHx)],
    ['GET', '/kurse/tabelle', fn($params, $isHx) => CourseController::table($params, $isHx)],
    ['POST', '/kurse', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['DELETE', '/kurse/{id}', fn($params, $isHx) => CourseController::delete($params, $isHx)],
    ['GET', '/kurse/{id}/teilnehmer', fn($params, $isHx) => ParticipantController::index($params, $isHx)],
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
    ['GET', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
    ['POST', '/uebermitteln/{token}', fn($params, $isHx) => SubmissionController::form($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes) {
    return Router::dispatch($routes, $isHx);
});

$kernel();
