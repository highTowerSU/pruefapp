<?php
require_once 'lib/lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $handle = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        $row = array_combine($header, $data);
        if (!$row) continue;

        $n = R::dispense('teilnehmer');
        $n->vorname = trim($row['Vorname'] ?? '');
        $n->nachname = trim($row['Nachname'] ?? '');
        $n->geburtsdatum = trim($row['Geburtsdatum'] ?? '');
        $n->geburtsort = trim($row['Geburtsort'] ?? '');
        $n->benutzername = generate_username($n->vorname, $n->nachname);
        $n->passwort = generate_password();
        $n->email = generate_email($n->benutzername);
        $n->kurs = $kurs;
		$n->deleted = 0;
        R::store($n);
    }
    fclose($handle);

    header("Location: teilnehmer.php?kurs=" . $kurs->id);
    exit;
}

$content = render_template('import_csv_form.php', ['kurs' => $kurs]);
echo render_template('layout.php', ['title' => 'CSV-Import – ' . $kurs->name, 'content' => $content]);
