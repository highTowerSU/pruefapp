<?php

declare(strict_types=1);

$template = (string) file_get_contents(dirname(__DIR__) . '/templates/device_index.php');
$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$downloads = (string) file_get_contents(dirname(__DIR__) . '/templates/downloads.php');
$filterRenderer = (string) file_get_contents(dirname(__DIR__) . '/lib/filter_renderer.php');
$searchSelect = (string) file_get_contents(dirname(__DIR__) . '/public/js/search-select.js');

$lookupPosition = strpos($template, 'id="device-inspection-lookup"');
$filterPosition = strpos($template, "render_common_filter_panel('device'");
$newInspectionPosition = strpos($navbar, 'Neue Prüfung</a>');
$deviceOverviewPosition = strpos($navbar, 'Geräteübersicht</a>');

$checks = [
    [$lookupPosition !== false && $filterPosition !== false && $lookupPosition < $filterPosition, 'Die Prüfungssuche steht nicht vor den Gerätefiltern.'],
    [str_contains($template, 'id="inspection-device-number"') && str_contains($template, 'autofocus'), 'Das Scannerfeld mit Autofokus fehlt.'],
    [str_contains($template, "window.addEventListener('hashchange', focusScanner)") && str_contains($template, "window.addEventListener('DOMContentLoaded', focusScanner)") && str_contains($template, "window.addEventListener('pageshow', focusScanner)") && str_contains($template, "event.preventDefault()") && str_contains($template, "getOrCreateInstance(toggle).hide()") && str_contains($template, "document.addEventListener('shown.bs.collapse', focusScanner)") && !str_contains($template, "document.addEventListener('shown.bs.dropdown', focusScanner)") && !str_contains($template, "document.addEventListener('hidden.bs.dropdown', focusScanner)"), 'Der Scanneranker wird nach Navigation oder Bootstrap-Initialisierung nicht fokussiert.'],
    [str_contains($template, 'class="row g-3 device-form"'), 'Das gemeinsame Geräteformular besitzt nicht das geordnete Raster.'],
    [substr_count($template, '$form(') >= 2, 'Neuanlage und Bearbeitung verwenden nicht denselben Formular-Renderer.'],
    [!str_contains($template, 'fieldOrder') && !str_contains($template, 'const order ='), 'Die Formularreihenfolge wird weiterhin nachträglich per JavaScript verändert.'],
    [$newInspectionPosition !== false && $deviceOverviewPosition !== false && $newInspectionPosition < $deviceOverviewPosition, 'Neue Prüfung ist nicht der erste Eintrag im Geräte-Dropdown.'],
    [str_contains($downloads, 'badge text-bg-secondary') && !str_contains($downloads, 'badge text-bg-light border text-body-secondary ms-1">Historie'), 'Das Historie-Badge hat keinen sicheren Dark-Mode-Kontrast.'],
    [str_contains($navbar, 'navbar-themed sticky-top'), 'Die Hauptnavigation bleibt beim Scrollen nicht sichtbar.'],
    [str_contains($filterRenderer, '$roomLabels') && str_contains($filterRenderer, "'number', " . '$roomLabels'), 'Der Raumfilter verwendet nicht den zusammengesetzten Raumnamen.'],
    [str_contains($searchSelect, 'if (select.tomselect)') && str_contains($searchSelect, 'externalLabel') && substr_count($template, 'data-search-select') >= 3 && !str_contains($template, 'data-no-search'), 'TomSelect-Felder mit externem Label behalten einen redundanten Platzhalter.'],
    [str_contains($template, '$latestInspection = $deviceInspections[0]') && !str_contains($template, 'foreach ($deviceInspections as $inspectionForBadge)'), 'Das Gerätebadge wertet nicht ausschließlich die letzte Prüfung aus.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

echo "PASS: Device entry workflow and download history badge remain accessible\n";
