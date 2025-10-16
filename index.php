<?php
require_once 'lib/lib.inc.php';

use \RedBeanPHP\R as R;

if(false) {
// Routen
$routes = [
  ['GET',  '/',          fn($p,$hx)=>UsersController::index($p,$hx)],
  ['GET',  '/users',     fn($p,$hx)=>UsersController::index($p,$hx)],
  ['POST', '/users',     fn($p,$hx)=>UsersController::create($p,$hx)],
  ['DELETE','/users/{id}',fn($p,$hx)=>UsersController::delete($p,$hx)],
];

// Middleware-Entry
$kernel = Htmx::handle(function($isHx) use ($routes){
  return Router::dispatch($routes, $isHx);
});

$kernel(); // Run

exit();
}

if (isset($_GET['delete_kurs'])) {
    $kurs = R::load('kurs', $_GET['delete_kurs']);
    if ($kurs->id) {
        $nutzer = R::count('teilnehmer', 'kurs_id = ?', [$kurs->id]);
        if ($nutzer === 0) {
            R::trash($kurs);
        } else {
            $error = "Kurs enthält noch Teilnehmer und kann nicht gelöscht werden.";
        }
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kursname'])) {
    $kurs = R::dispense('kurs');
    $kurs->name = trim($_POST['kursname']);
    R::store($kurs);
}

$kurse = R::find('kurs', 'ORDER BY name');


$content = render_template('kurs_liste.php', ['kurse' => $kurse]);
echo render_template('layout.php', ['title' => 'Kursverwaltung', 'content' => $content]);
