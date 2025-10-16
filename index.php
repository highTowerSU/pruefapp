<?php
require_once 'lib/lib.inc.php';
require_once 'controllers/CourseController.php';

$routes = [
    ['GET', '/', fn($params, $isHx) => CourseController::index($params, $isHx)],
    ['GET', '/index.php', fn($params, $isHx) => CourseController::index($params, $isHx)],
    ['GET', '/kurse', fn($params, $isHx) => CourseController::table($params, $isHx)],
    ['POST', '/', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['POST', '/index.php', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['POST', '/kurse', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['DELETE', '/kurse/{id}', fn($params, $isHx) => CourseController::delete($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes) {
    return Router::dispatch($routes, $isHx);
});

$kernel();
