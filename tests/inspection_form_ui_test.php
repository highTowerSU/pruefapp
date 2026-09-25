<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$form = (string) file_get_contents($root . '/templates/inspection_edit.php');
$ladderForm = (string) file_get_contents($root . '/templates/inspection_ladder_edit.php');
$legacyForm = (string) file_get_contents($root . '/templates/inspection_legacy_edit.php');
$controller = (string) file_get_contents($root . '/controllers/InspectionController.php');

foreach (['SK I', 'SK II', 'SK III', 'SK Kabel', 'connector-grid', 'connector-example'] as $part) {
    if (!str_contains($form, $part)) {
        throw new RuntimeException('Die grafische Schutzklassen-Auswahl ist unvollständig: ' . $part);
    }
}

if (str_contains($form, 'min-height:760px') || str_contains($form, 'min-height:700px') || str_contains($form, 'height:400px')) {
    throw new RuntimeException('Die Schutzklassen-Karten dürfen keine großen festen Höhen erzwingen.');
}

foreach ([$form, $ladderForm, $legacyForm] as $template) {
    $html = (string) preg_replace('/<\?[\s\S]*?\?>/', '', $template);
    if (!preg_match('/<input\b[^>]*name="test_date"[^>]*>/', $html, $match) || !str_contains($match[0], 'required')) {
        throw new RuntimeException('Das aktuelle Prüfdatum muss in jedem Prüfungsformular Pflicht sein.');
    }
    if (!preg_match('/<input\b[^>]*name="next_due_date"[^>]*>/', $html, $match) || !str_contains($match[0], 'required')) {
        throw new RuntimeException('Auch das nächste Prüfdatum muss in jedem Prüfungsformular Pflicht sein.');
    }
}

if (substr_count($controller, "'Das Prüfdatum ist ein Pflichtfeld.'") < 3) {
    throw new RuntimeException('Elektro-, Leiter- und Legacy-Prüfungen müssen ein leeres Prüfdatum auch serverseitig ablehnen.');
}

if (substr_count($controller, "'Das nächste Prüfdatum ist ein Pflichtfeld.'") < 3
    || !str_contains($controller, "strtotime('+1 year')")
    || !str_contains($form, "date.addEventListener('change', () => update(365))")) {
    throw new RuntimeException('Das nächste Prüfdatum muss vorausgefüllt und serverseitig verpflichtend sein.');
}

echo "PASS: Schutzklassen-Karten und beide Pflichtdaten\n";
