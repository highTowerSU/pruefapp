<?php
session_start();
require_once 'lib.inc.php';

if (!isset($_GET['kurs_id'])) {
    http_response_code(400);
    exit('Keine Kurs-ID angegeben.');
}

$kurs = R::load('kurs', (int)$_GET['kurs_id']);
if ($kurs->id) {
    $kurs->deleted = 1;
    R::store($kurs);
    $_SESSION['meldung'] = 'Kurs wurde als gelöscht markiert.';
} else {
    $_SESSION['meldung'] = 'Kurs nicht gefunden.';
}

header('Location: index.php');
exit;
