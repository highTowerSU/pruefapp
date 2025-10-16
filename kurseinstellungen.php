<?php
require_once 'lib/lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Kurs nicht gefunden.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kurs->feld_email_aktiv = isset($_POST['feld_email_aktiv']) ? 1 : 0;
    $kurs->feld_geburtsort_aktiv = isset($_POST['feld_geburtsort_aktiv']) ? 1 : 0;
    R::store($kurs);
    header("Location: /kurse");
    exit;
}

$content = render_template('kurseinstellungen_form.php', ['kurs' => $kurs]);
echo render_template('layout.php', ['title' => 'Kurseinstellungen – ' . $kurs->name, 'content' => $content]);
