<?php
require_once 'lib.inc.php';

if (isset($_GET['delete_kurs'])) {
    $kurs = R::load('kurs', $_GET['delete_kurs']);
    if ($kurs->id) {
        $nutzer = R::count('nutzer', 'kurs_id = ?', [$kurs->id]);
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
    header("Location: index.php");
    exit;
}

$kurse = R::find('kurs', 'deleted = 0 ORDER BY name');


$content = render_template('kurs_liste.php', ['kurse' => $kurse]);
echo render_template('layout.php', ['title' => 'Kursverwaltung', 'content' => $content]);
