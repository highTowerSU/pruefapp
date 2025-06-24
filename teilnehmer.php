<?php
require_once 'lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

if (isset($_GET['delete_user'])) {
    $n = R::load('nutzer', $_GET['delete_user']);
    if ($n->id && $n->kurs_id == $kurs->id) {
        R::trash($n);
    }
    header("Location: teilnehmer.php?kurs=" . $kurs->id);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manuell'])) {
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
		$n->deleted = 0;
        R::store($n);
    }
    header("Location: teilnehmer.php?kurs=" . $kurs->id);
    exit;
}

$nutzer = R::findAll('nutzer', 'kurs_id = ? AND deleted = 0 ORDER BY nachname, vorname', [$kurs->id]);

$content = render_template('teilnehmer_table.php', [
    'kurs' => $kurs,
    'nutzer' => $nutzer
]);
$scripts = render_template('teilnehmer_scripts.php', ['kurs' => $kurs->id]);
//$content .= render_template('import_manual_form.php', ['kurs' => $kurs]);

echo render_template('layout.php', ['title' => 'Teilnehmer – ' . $kurs->name, 'content' => $content,
    'scripts' => $scripts, ]);

