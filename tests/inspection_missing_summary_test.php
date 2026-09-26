<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$form = (string) file_get_contents($root . '/templates/inspection_edit.php');
$ladderForm = (string) file_get_contents($root . '/templates/inspection_ladder_edit.php');
$controller = (string) file_get_contents($root . '/controllers/InspectionController.php');
$lookup = (string) file_get_contents($root . '/templates/device_index.php');
$detail = (string) file_get_contents($root . '/templates/inspection_detail.php');

if (!str_contains($form, "render_template('inspection_device_summary.php'")
    || !str_contains($ladderForm, "render_template('inspection_device_summary.php'")
    || !str_contains($form, 'list-group-item-warning')
    || !str_contains($form, 'foreach ($missingRequirements as $missingRequirement)')
    || !str_contains($controller, 'missingRequirementsForEdit($inspection)')
    || !str_contains($controller, "input_type = 'boolean' AND required = 1")
    || !str_contains($lookup, 'data.manufacturer')
    || !str_contains($lookup, 'data.model')) {
    throw new RuntimeException('Gerätestammdaten oder konkrete fehlende Prüfpunkte fehlen in der Prüfmaske.');
}
if (!str_contains($detail, 'Kabellänge eintragen')
    || !str_contains($controller, "str_starts_with(\$assessment['reason'], 'Kabellänge fehlt')")
    || !str_contains($controller, "!== 'legacy'")) {
    throw new RuntimeException('Die Prüfungsdetailseite muss eine fehlende Kabellänge als Bewertungsgrund nennen.');
}

$device = (object) [
    'external_number' => '01234567',
    'name' => 'Testgerät',
    'manufacturer' => '<script>alert(1)</script>',
    'device_model' => 'Modell X',
    'inventory_number' => 'INV-1',
    'serial_number' => 'SER-1',
    'legacy_number' => 'ALT-1',
    'room_snapshot' => 'N112',
    'description' => '<b>Prüfgerät</b>',
];
ob_start();
require $root . '/templates/inspection_device_summary.php';
$summary = (string) ob_get_clean();
if (!str_contains($summary, 'Modell X')
    || !str_contains($summary, 'INV-1')
    || !str_contains($summary, 'SER-1')
    || !str_contains($summary, 'ALT-1')
    || !str_contains($summary, 'N112')
    || !str_contains($summary, '&lt;b&gt;Prüfgerät&lt;/b&gt;')
    || !str_contains($summary, '&lt;script&gt;')
    || str_contains($summary, '<script>')) {
    throw new RuntimeException('Die Stammdaten-Zusammenfassung ist unvollständig oder nicht HTML-sicher.');
}

$device->inventory_number = '';
$device->serial_number = '';
ob_start();
require $root . '/templates/inspection_device_summary.php';
$emptySummary = (string) ob_get_clean();
if (!str_contains($emptySummary, 'Inventarnummer: nicht hinterlegt')
    || !str_contains($emptySummary, 'Seriennummer: nicht hinterlegt')) {
    throw new RuntimeException('Fehlende Kennnummern müssen in der Prüfmaske sichtbar bleiben.');
}

echo "PASS: Prüfmaske zeigt Stammdaten und konkrete fehlende Prüfpunkte\n";
