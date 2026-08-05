<?php

declare(strict_types=1);

$template = (string) file_get_contents(dirname(__DIR__) . '/templates/device_index.php');
$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$downloads = (string) file_get_contents(dirname(__DIR__) . '/templates/downloads.php');

$lookupPosition = strpos($template, 'id="device-inspection-lookup"');
$filterPosition = strpos($template, "render_common_filter_panel('device'");
$newInspectionPosition = strpos($navbar, 'Neue Prüfung</a>');
$deviceOverviewPosition = strpos($navbar, 'Geräteübersicht</a>');

$checks = [
    [$lookupPosition !== false && $filterPosition !== false && $lookupPosition < $filterPosition, 'Die Prüfungssuche steht nicht vor den Gerätefiltern.'],
    [str_contains($template, 'id="inspection-device-number"') && str_contains($template, 'autofocus'), 'Das Scannerfeld mit Autofokus fehlt.'],
    [str_contains($template, "window.addEventListener('hashchange', focusScanner)") && str_contains($template, "event.preventDefault()") && str_contains($template, "getOrCreateInstance(toggle).hide()") && str_contains($template, "document.addEventListener('hidden.bs.dropdown', focusScanner)"), 'Der Scanneranker wird nicht ohne erneute Navigation fokussiert.'],
    [str_contains($template, 'class="row g-3 device-form"'), 'Das gemeinsame Geräteformular besitzt nicht das geordnete Raster.'],
    [substr_count($template, '$form(') >= 2, 'Neuanlage und Bearbeitung verwenden nicht denselben Formular-Renderer.'],
    [!str_contains($template, 'fieldOrder') && !str_contains($template, 'const order ='), 'Die Formularreihenfolge wird weiterhin nachträglich per JavaScript verändert.'],
    [$newInspectionPosition !== false && $deviceOverviewPosition !== false && $newInspectionPosition < $deviceOverviewPosition, 'Neue Prüfung ist nicht der erste Eintrag im Geräte-Dropdown.'],
    [str_contains($downloads, 'badge text-bg-secondary') && !str_contains($downloads, 'badge text-bg-light border text-body-secondary ms-1">Historie'), 'Das Historie-Badge hat keinen sicheren Dark-Mode-Kontrast.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

echo "PASS: Device entry workflow and download history badge remain accessible\n";
