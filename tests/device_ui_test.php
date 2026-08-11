<?php

declare(strict_types=1);

$template = (string) file_get_contents(dirname(__DIR__) . '/templates/device_index.php');
$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$downloads = (string) file_get_contents(dirname(__DIR__) . '/templates/downloads.php');
$filterRenderer = (string) file_get_contents(dirname(__DIR__) . '/lib/filter_renderer.php');
$searchSelect = (string) file_get_contents(dirname(__DIR__) . '/public/js/search-select.js');
$customCss = (string) file_get_contents(dirname(__DIR__) . '/public/css/custom.css');
$deviceController = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceController.php');
$inspectionController = (string) file_get_contents(dirname(__DIR__) . '/controllers/InspectionController.php');
$inspectionTemplate = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_edit.php');
$dailyExaminerMarkup = strstr($template, 'id="daily-examiner"') ?: '';

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
    [str_contains($navbar, 'sticky-top app-navbar-shell') && str_contains($navbar, 'navbar-themed'), 'Die Hauptnavigation bleibt beim Scrollen nicht sichtbar.'],
    [str_contains($filterRenderer, '$roomLabels') && str_contains($filterRenderer, "'number', " . '$roomLabels'), 'Der Raumfilter verwendet nicht den zusammengesetzten Raumnamen.'],
    [str_contains($searchSelect, "querySelectorAll('select:not([data-no-search])')") && str_contains($searchSelect, "plugins: ['dropdown_input']") && str_contains($searchSelect, 'if (select.tomselect)') && str_contains($searchSelect, 'externalLabel') && substr_count($template, 'plugins: [\'dropdown_input\']') >= 1 && !str_contains($template, 'createOnBlur: true') && str_contains($template, 'initializeDeviceVocabulary') && str_contains($template, "control_input.addEventListener('keydown'") && str_contains($template, "event.key !== 'Enter'"), 'Geräte-Stammdaten verwenden nicht die einheitliche Auswahl mit expliziter Anlage per Enter.'],
    [str_contains($template, '$latestInspection = $deviceInspections[0]') && !str_contains($template, 'foreach ($deviceInspections as $inspectionForBadge)'), 'Das Gerätebadge wertet nicht ausschließlich die letzte Prüfung aus.'],
    [str_contains($template, '$badgeResultStatus = $latestInspection ?') && str_contains($template, ": '';") && !str_contains($template, '$inspectionPending = !$latestInspection'), 'Geräte ohne Prüfung werden fälschlich als fehlende Daten angezeigt.'],
    [!str_contains($template, 'stammdaten-aus-letzter-pruefung') && !str_contains($template, 'Stammdaten aus letzter Prüfung übernehmen'), 'Die irreführende Übernahme aus einer Prüfung wird weiterhin bei Bestandsgeräten angeboten.'],
    [str_contains($template, 'data-copy-device-name-label') && str_contains($template, 'updateNameSuggestion'), 'Die passende Gerätebezeichnung wird nach der Modellauswahl nicht eindeutig benannt.'],
    [str_contains($template, 'dropdown-toggle-split') && str_contains($template, 'preferredInspectionType') && str_contains($template, 'Neue <?= htmlspecialchars((string) $preferred[\'name\']) ?>'), 'Neue Prüfungen verwenden nicht die zuletzt verwendete Prüfart als Split-Schaltfläche.'],
    [str_contains($customCss, '.device-form > .col-12.text-end > .btn-group') && str_contains($customCss, '.dropdown-toggle-split { flex: 0 0 auto; }'), 'Der Geräteaktions-Split-Button kann auf schmalen Ansichten auseinanderbrechen.'],
    [str_contains($template, 'data-metadata-editor') && str_contains($template, 'MAC-Adresse') && str_contains($template, 'data-metadata-json'), 'Der visuelle Editor für Geräte-Zusatzattribute fehlt.'],
    [str_contains($template, 'const normalizeMac') && str_contains($template, 'AA:BB:CC:DD:EE:FF') && str_contains($template, 'maxlength="80"') && str_contains($template, 'maxlength="500"'), 'MAC-Adressen und Zeichengrenzen werden im Zusatzattribut-Editor nicht benutzerfreundlich behandelt.'],
    [str_contains($filterRenderer, 'data-device-details-action="expand"') && str_contains($filterRenderer, 'data-device-details-action="collapse"') && str_contains($filterRenderer, "if (\$context === 'device')") && str_contains($template, 'details.device-card[id^="geraet-"]'), 'Die Geräteliste bietet keine direkten Ein- und Ausklapp-Schalter neben dem Filter-Reset.'],
    [str_contains($dailyExaminerMarkup, "\$examinerUser['label']") && !str_contains($dailyExaminerMarkup, "\$examinerUser['email']"), 'Prüfer werden in Auswahl und Massenaktionen noch mit E-Mail-Adresse angezeigt.'],
    [str_contains($template, 'data-action-nav="Geräteaktionen"') && str_contains($template, 'data-action-nav="Neues Gerät"'), 'Die Geräteaktionsbereiche sind nicht im gemeinsamen Schnellzugriff markiert.'],
    [str_contains($template, 'data-suggest-last-room') && str_contains($template, 'data-metadata-editor'), 'Der Raumvorschlag oder einklappbare Zusatzattribute fehlen.'],
    [str_contains($inspectionTemplate, 'name="metadata_notes"') && str_contains($inspectionController, "'metadata_notes'"), 'Die Prüfungsbemerkung wird nicht serverseitig gespeichert.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

echo "PASS: Device entry workflow and download history badge remain accessible\n";
