<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/controllers/DeviceController.php');
$template = (string) file_get_contents($root . '/templates/device_index.php');
$filters = (string) file_get_contents($root . '/lib/filter_renderer.php');
$listStart = strpos($template, '<div id="device-list-panel">');
$newDeviceStart = strpos($template, '<details id="device-new-panel"');
$newInspectionStart = strpos($template, '<section id="device-inspection-lookup"');

foreach ([
    [str_contains($controller, "'listOnly' => \$isHx"), 'HTMX muss eine reine Listenantwort anfordern.'],
    [str_contains($template, '<?php if (empty($listOnly)): ?><div id="device-page">')
        && str_contains($template, '<?php if (empty($listOnly)): ?></div><?php endif; ?>'), 'Die Schnellsuche darf nicht Teil der Listenantwort sein.'],
    [$listStart !== false && $newDeviceStart !== false && $newInspectionStart !== false
        && $newInspectionStart < $newDeviceStart && $newDeviceStart < $listStart
        && str_contains(substr($template, $newDeviceStart, $listStart - $newDeviceStart), '<?php endif; ?>'), 'Neue Prüfung und Neues Gerät müssen außerhalb des austauschbaren Listenbereichs stehen.'],
    [str_contains($filters, "\$hxSelect = ''")
        && str_contains($template, "{target: '#device-list-panel', swap: 'outerHTML', pushUrl: true}"), 'Filter und Seitennavigation müssen nur den Listenbereich tauschen.'],
    [str_contains($template, 'list="device-manufacturer-options"')
        && str_contains($template, 'list="device-model-options"')
        && str_contains($template, '<datalist id="device-manufacturer-options">')
        && str_contains($template, '<datalist id="device-model-options">'), 'Hersteller und Modell benötigen native Vorschlagslisten.'],
] as [$ok, $message]) {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

echo "PASS: Geräteliste wird isoliert aktualisiert und Stammdaten bleiben vervollständigbar\n";
