<?php
require_once __DIR__ . '/lib/lib.inc.php';
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/CourseController.php';

$routes = [
    ['GET', '/', fn($params, $isHx) => HomeController::index($params, $isHx)],
    ['GET', '/kurse', fn($params, $isHx) => CourseController::index($params, $isHx)],
    ['GET', '/kurse/tabelle', fn($params, $isHx) => CourseController::table($params, $isHx)],
    ['POST', '/kurse', fn($params, $isHx) => CourseController::create($params, $isHx)],
    ['DELETE', '/kurse/{id}', fn($params, $isHx) => CourseController::delete($params, $isHx)],
];

$kernel = Htmx::handle(function ($isHx) use ($routes) {
    return Router::dispatch($routes, $isHx);
});

$kernel();
