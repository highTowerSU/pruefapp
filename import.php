<?php
require_once 'lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['csv']['tmp_name'])) {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            if (!$row) continue;

            $n = R::dispense('nutzer');
            $n->vorname = trim($row['Vorname'] ?? '');
            $n->nachname = trim($row['Nachname'] ?? '');
            $n->geburtsdatum = trim($row['Geburtsdatum'] ?? '');
            $n->geburtsort = trim($row['Geburtsort'] ?? '');
            $n->benutzername = generate_username($n->vorname, $n->nachname);
            $n->passwort = generate_password();
            $n->email = generate_email($n->benutzername);
            $n->kurs = $kurs;
            R::store($n);
        }
        fclose($handle);
    }

    if (!empty($_POST['manuell'])) {
        foreach ($_POST['manuell'] as $eintrag) {
            if (empty($eintrag['vorname']) || empty($eintrag['nachname']) || empty($eintrag['geburtsdatum'])) {
                continue;
            }
            $n = R::dispense('nutzer');
            $n->vorname = trim($eintrag['vorname']);
            $n->nachname = trim($eintrag['nachname']);
            $n->geburtsdatum = trim($eintrag['geburtsdatum']);
            $n->geburtsort = trim($eintrag['geburtsort']);
            $n->benutzername = generate_username($n->vorname, $n->nachname);
            $n->passwort = generate_password();
            $n->email = generate_email($n->benutzername);
            $n->kurs = $kurs;
            R::store($n);
        }
    }

    header("Location: teilnehmer.php?kurs=" . $kurs->id);
    exit;
}

$content = render_template('import_form.php', ['kurs' => $kurs]);
echo render_template('layout.php', ['title' => 'Import – ' . $kurs->name, 'content' => $content]);
