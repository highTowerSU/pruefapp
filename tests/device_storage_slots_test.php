<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/DeviceStorageSlotService.php';

$device = (object) [
    'storage_slots_json' => '["120","121"]',
    'storage_slot_notes_json' => '{"120":"PSU links","121":"PSU rechts"}',
];
$rows = DeviceStorageSlotService::fromDevice($device);
if ($rows !== [
    ['number' => '120', 'comment' => 'PSU links'],
    ['number' => '121', 'comment' => 'PSU rechts'],
]) {
    throw new RuntimeException('Vorhandene Speicherplätze und ihre Kommentare werden nicht gemeinsam geladen.');
}
if (DeviceStorageSlotService::fromDevice((object) ['storage_slot' => '045']) !== [['number' => '045', 'comment' => '']]) {
    throw new RuntimeException('Ein älterer einzelner Speicherplatz muss weiterhin sichtbar bleiben.');
}

$saved = DeviceStorageSlotService::fromPost([
    'storage_slot_numbers' => ['120', '121', ''],
    'storage_slot_comments' => ['PSU links', 'PSU rechts', ''],
]);
if ($saved !== $rows) {
    throw new RuntimeException('Leere Zusatzzeilen dürfen keine Speicherplätze erzeugen.');
}

foreach ([
    ['storage_slot_numbers' => ['', '121'], 'storage_slot_comments' => ['PSU links', 'PSU rechts']],
    ['storage_slot_numbers' => ['003', '3'], 'storage_slot_comments' => ['', '']],
    ['storage_slot_numbers' => range(1, 9), 'storage_slot_comments' => array_fill(0, 9, '')],
] as $invalid) {
    try {
        DeviceStorageSlotService::fromPost($invalid);
        throw new RuntimeException('Ungültige Speicherplatzangaben wurden akzeptiert.');
    } catch (InvalidArgumentException) {
    }
}

$component = (string) file_get_contents($root . '/templates/device_storage_slots.php');
$controller = (string) file_get_contents($root . '/controllers/DeviceController.php');
$inspectionForm = (string) file_get_contents($root . '/templates/inspection_edit.php');
$routes = (string) file_get_contents($root . '/index.php');
if (!str_contains($component, 'Weiterer Speicherplatz')
    || !str_contains($component, 'name="storage_slot_comments[]"')
    || !str_contains($component, 'hx-target="closest [data-storage-slots-panel]"')
    || !str_contains($component, 'hx-include="closest [data-storage-slots-panel]"')
    || !str_contains($component, 'hx-params="storage_form_key,storage_slot_numbers[],storage_slot_comments[],slot_action,slot_index"')
    || !str_contains($controller, 'storageSlotRows(')
    || !str_contains($inspectionForm, 'inspection-storage-slot-options')
    || !str_contains($inspectionForm, "DeviceStorageSlotService::fromDevice(")
    || !str_contains($routes, "'/geraete/speicherplaetze/form'")) {
    throw new RuntimeException('Der HTMX-Speicherplatzbereich ist nicht vollständig angebunden.');
}

function current_user_has_role(string ...$roles): bool
{
    return true;
}

function url_for(string $path): string
{
    return '/' . ltrim($path, '/');
}

function render_template(string $template, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/templates/' . $template;
    return (string) ob_get_clean();
}

require_once $root . '/controllers/DeviceController.php';
$_POST = [
    'slot_action' => 'add',
    'storage_form_key' => '42',
    'storage_slot_numbers' => ['120'],
    'storage_slot_comments' => ['PSU links'],
];
[$status, , $markup] = DeviceController::storageSlotRows([], true);
if ($status !== 200 || substr_count($markup, 'name="storage_slot_numbers[]"') !== 2
    || !str_contains($markup, 'value="PSU links"') || !str_contains($markup, 'id="device-storage-slots-42"')
    || str_contains($markup, '<form')) {
    throw new RuntimeException('Das Hinzufügen muss nur den Speicherplatzbereich mit unveränderten bisherigen Werten liefern.');
}

$_POST = [
    'slot_action' => 'remove',
    'slot_index' => '0',
    'storage_form_key' => '42',
    'storage_slot_numbers' => ['120', '121'],
    'storage_slot_comments' => ['PSU links', 'PSU rechts'],
];
[$status, , $markup] = DeviceController::storageSlotRows([], true);
if ($status !== 200 || substr_count($markup, 'name="storage_slot_numbers[]"') !== 1
    || !str_contains($markup, 'value="PSU rechts"') || str_contains($markup, 'value="PSU links"')) {
    throw new RuntimeException('Beim Entfernen muss der verbleibende Speicherplatz samt Kommentar erhalten bleiben.');
}

echo "PASS: mehrere kommentierte Speicherplätze und gezielter HTMX-Austausch\n";
