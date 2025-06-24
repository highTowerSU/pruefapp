<?php
session_start();
require_once 'lib.inc.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Keine Teilnehmer-ID angegeben.');
}

$tn = R::load('teilnehmer', (int)$_GET['id']);
if ($tn->id) {
    $tn->deleted = 1;
    R::store($tn);
    $_SESSION['meldung'] = 'Teilnehmer wurde als gelöscht markiert.';
} else {
    $_SESSION['meldung'] = 'Teilnehmer nicht gefunden.';
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
