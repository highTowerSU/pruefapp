<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$form = (string) file_get_contents($root . '/templates/inspection_edit.php');
$ladderForm = (string) file_get_contents($root . '/templates/inspection_ladder_edit.php');
$controller = (string) file_get_contents($root . '/controllers/InspectionController.php');

foreach (['SK I', 'SK II', 'SK III', 'SK Kabel', 'connector-grid', 'connector-example'] as $part) {
    if (!str_contains($form, $part)) {
        throw new RuntimeException('Die grafische Schutzklassen-Auswahl ist unvollständig: ' . $part);
    }
}

if (str_contains($form, 'min-height:760px') || str_contains($form, 'min-height:700px') || str_contains($form, 'height:400px')) {
    throw new RuntimeException('Die Schutzklassen-Karten dürfen keine großen festen Höhen erzwingen.');
}

foreach ([$form, $ladderForm] as $template) {
    if (!preg_match('/<input\b[^>]*name="next_due_date"[^>]*>/', $template, $match) || str_contains($match[0], 'required')) {
        throw new RuntimeException('Das nächste Prüfdatum muss im Formular optional sein.');
    }
}

if (!str_contains($controller, '$inspection->next_due_date = \'\';')
    || str_contains($controller, "strtotime('+1 year')")
    || str_contains($form, "date.addEventListener('change', () => update(365))")) {
    throw new RuntimeException('Das nächste Prüfdatum darf nicht automatisch ausgefüllt werden.');
}

echo "PASS: Schutzklassen-Karten und optionales nächstes Prüfdatum\n";
