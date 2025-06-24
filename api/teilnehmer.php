<?php
require_once '../lib.inc.php';
header('Content-Type: application/json');

$kursId = isset($_GET['kurs']) ? (int)$_GET['kurs'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $teilnehmer = R::findAll('teilnehmer', 'kurs_id = ? AND deleted = 0', [$kursId]);
    echo json_encode(array_values(R::exportAll($teilnehmer)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !$kursId) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }

    $n = isset($input['id']) ? R::load('teilnehmer', $input['id']) : R::dispense('teilnehmer');
    if ($n->id === 0) {
        $n->kurs_id = $kursId;
        $n->deleted = 0;
    }

    foreach (['vorname', 'nachname', 'geburtsdatum', 'geburtsort', 'benutzername', 'email'] as $feld) {
        if (isset($input[$feld])) {
            $n->$feld = $input[$feld];
        }
    }

    $id = R::store($n);
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

if (isset($_GET['delete'])) {
    $n = R::load('teilnehmer', (int)$_GET['delete']);
    if ($n->id && $n->kurs_id == $kursId) {
        $n->deleted = 1;
        R::store($n);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nicht gefunden']);
    }
    exit;
}

