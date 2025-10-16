<?php
require_once 'lib/lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

$nutzer = R::findAll('teilnehmer', 'kurs_id = ? ORDER BY nachname, vorname', [$kurs->id]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="moodle_export_kurs_' . $kurs->id . '.csv"');

$output = fopen('php://output', 'w');

// Spaltenmapping nach Moodle
$defaults = [
    'Benutzername' => 'username',
    'Passwort' => 'password',
    'Vorname' => 'firstname',
    'Nachname' => 'lastname',
    'E-Mail' => 'email',
    'Geburtsdatum' => 'profile_field_birthdate',
    'Geburtsort' => 'profile_field_birthplace',
];

// Schreibe Headerzeile
fputcsv($output, array_values($defaults));

// Schreibe alle Teilnehmer
foreach ($nutzer as $n) {
    $row = [
        $n->benutzername,
        $n->passwort,
        $n->vorname,
        $n->nachname,
        $n->email,
        $n->geburtsdatum,
        $n->geburtsort,
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
