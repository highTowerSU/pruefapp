<?php
require_once 'lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

$nutzer = R::findAll('teilnehmer', 'kurs_id = ? ORDER BY nachname, vorname', [$kurs->id]);

$content = render_template('druckliste.php', ['kurs' => $kurs, 'nutzer' => $nutzer]);
echo render_template('layout.php', ['title' => 'Druck – ' . $kurs->name, 'content' => $content]);
