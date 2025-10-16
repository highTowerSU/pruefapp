<?php
require_once 'lib/lib.inc.php';

if (!isset($_GET['token']) || !($kurs = R::findOne('kurs', ' token = ? ', [$_GET['token']]))) {
if (!$kurs->uebermittlung_aktiv) { die("Dieser Übermittlungslink ist derzeit deaktiviert."); }
    die("Ungültiger Link");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['person'] as $eintrag) {
        if (empty($eintrag['vorname']) || empty($eintrag['nachname']) || empty($eintrag['geburtsdatum'])) {
            continue;
        }

        $n = R::dispense('teilnehmer');
        $n->vorname = trim($eintrag['vorname']);
        $n->nachname = trim($eintrag['nachname']);
        $n->geburtsdatum = trim($eintrag['geburtsdatum']);
        $n->geburtsort = $kurs->feld_geburtsort_aktiv ? trim($eintrag['geburtsort']) : '';
        $n->email = $kurs->feld_email_aktiv ? trim($eintrag['email']) : '';
        $n->benutzername = generate_username($n->vorname, $n->nachname);
        $n->passwort = generate_password();
        $n->quelle = 'extern';
        $n->kurs = $kurs;
        R::store($n);
    }
    header("Location: uebermitteln.php?token=" . $kurs->token . "&danke=1");
    exit;
}

if (isset($_GET['danke'])) {
    $content = '<div class="alert alert-success">Vielen Dank. Die Daten wurden übermittelt.</div>';
} else {
    $content = render_template('uebermitteln_form.php', ['kurs' => $kurs]);
}

echo render_template('layout.php', [
    'title' => 'Teilnehmerdaten übermitteln',
    'content' => $content
]);
