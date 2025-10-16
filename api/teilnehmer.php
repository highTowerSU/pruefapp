<?php
require_once '../lib/lib.inc.php';

header('Content-Type: application/json');

$kurs_id = $_GET['kurs'] ?? null;
if (!$kurs_id || !R::load('kurs', $kurs_id)->id) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiger Kurs']);
    exit;
}

// Softdelete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $n = R::load('teilnehmer', $id);
    if ($n->id && $n->kurs_id == $kurs_id) {
        $n->deleted = 1;
        R::store($n);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nicht gefunden']);
    }
    exit;
}

// Bearbeiten oder Hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (isset($data['id'])) {
        $n = R::load('teilnehmer', (int)$data['id']);
        if (!$n->id || $n->kurs_id != $kurs_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Zugriff verweigert']);
            exit;
        }
    } else {
        $n = R::dispense('teilnehmer');
        $n->kurs_id = $kurs_id;
        $n->deleted = 0;
    }

    foreach (['vorname','nachname','geburtsdatum','geburtsort'] as $f) {
        if (isset($data[$f])) $n->$f = $data[$f];
    }

    // Nur neue TN erhalten Benutzername / E-Mail
    if (!$n->id && isset($n->vorname, $n->nachname)) {
        $n->benutzername = generate_username($n->vorname, $n->nachname);
        $n->email = generate_email($n->benutzername);
    }

    R::store($n);
    echo json_encode(['id' => $n->id]);
    exit;
}

// Ausgabe aller Teilnehmer (nicht gelöscht)
$nutzer = R::findAll('teilnehmer', 'kurs_id = ? ORDER BY nachname, vorname', [$kurs_id]);
echo json_encode(array_map(fn($n) => $n->export(), $nutzer));
