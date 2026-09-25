<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$form = (string) file_get_contents($root . '/templates/inspection_edit.php');
$ladderForm = (string) file_get_contents($root . '/templates/inspection_ladder_edit.php');
$legacyForm = (string) file_get_contents($root . '/templates/inspection_legacy_edit.php');
$controller = (string) file_get_contents($root . '/controllers/InspectionController.php');

foreach (["'I' => [", "'II' => [", "'III' => [", "'Kabel' => [", 'connector-grid', '<span class="connector-example">', 'connector-caption', "'Drehstrom'"] as $part) {
    if (!str_contains($form, $part)) {
        throw new RuntimeException('Die grafische Schutzklassen-Auswahl ist unvollständig: ' . $part);
    }
}

foreach (['schuko-deutsch.jpg', 'schuko.jpg', 'iec-c5.svg', 'iec-c13.svg', 'iec-c19.svg', 'euro-flach.jpg', 'konturenstecker.jpg', 'iec-c7-real.svg', 'dc-hohlstecker.jpg', 'usb.svg', 'batterie.svg', 'kabel-schuko.jpg', 'kabel-c13.svg', 'c5_power_cable.svg', 'cee-drehstrom-16a-verlaengerung.jpg'] as $example) {
    if (!str_contains($form, $example) || !is_file($root . '/public/img/stecker/' . $example)) {
        throw new RuntimeException('Ein Schutzklassen-Beispielbild fehlt: ' . $example);
    }
}
if (str_contains($form, 'schematicByClass') || str_contains($form, "document.createElement('figure')") || str_contains($form, 'plug-variant-gallery')) {
    throw new RuntimeException('Beispielbilder müssen direkt im HTML erscheinen, nicht erst durch JavaScript.');
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
