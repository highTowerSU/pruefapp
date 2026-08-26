<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$layout = (string) file_get_contents($root . '/templates/layout.php');
$sources = [
    'Geräte' => (string) file_get_contents($root . '/templates/device_index.php'),
    'Prüfungen' => (string) file_get_contents($root . '/templates/inspection_index.php'),
    'Import' => (string) file_get_contents($root . '/templates/inspection_import.php'),
    'Abrechnung' => (string) file_get_contents($root . '/templates/billing_index.php'),
    'Struktur' => (string) file_get_contents($root . '/templates/structure_index.php'),
    'Audit' => (string) file_get_contents($root . '/templates/audit_log.php'),
    'Profil' => (string) file_get_contents($root . '/templates/profile.php'),
    'Downloads' => (string) file_get_contents($root . '/templates/downloads.php'),
    'Konfiguration' => (string) file_get_contents($root . '/templates/settings.php'),
];

if (!str_contains($layout, 'id="page-action-navigation"')
    || !str_contains($layout, 'data-action-nav')
    || !str_contains($layout, 'buildActionNavigation')
    || !str_contains($layout, 'unique.length < 2')) {
    throw new RuntimeException('Der gemeinsame Schnellzugriff für Aktionsbereiche fehlt.');
}
if (!str_contains($layout, 'htmx-auto-refresh-indicator')
    || !str_contains($layout, 'Aktualisiere …')
    || !str_contains($layout, 'htmx:beforeRequest')) {
    throw new RuntimeException('Automatische HTMX-Aktualisierungen geben kein sichtbares Feedback.');
}
if (!str_contains($sources['Import'], "every 10s") || !str_contains($sources['Audit'], "every 10s")) {
    throw new RuntimeException('Automatische Import- und Audit-Aktualisierungen laufen nicht im 10-Sekunden-Takt.');
}

foreach ($sources as $label => $source) {
    if (!str_contains($source, 'data-action-nav=')) {
        throw new RuntimeException("{$label} meldet keinen Aktionsbereich für den Schnellzugriff.");
    }
}

if (!str_contains($sources['Import'], 'id="report-regeneration" data-action-nav="Berichte neu erzeugen"')) {
    throw new RuntimeException('Die Berichtserzeugung ist nicht als auffindbarer Import-Aktionsbereich markiert.');
}
if (!str_contains($sources['Profil'], 'id="qualification-card" data-action-nav="Befähigungen &amp; Nachweise"')
    || !str_contains($sources['Profil'], 'id="derived-inspection-permissions" data-action-nav="Abgeleitete Prüfberechtigungen"')) {
    throw new RuntimeException('Befähigungen und abgeleitete Prüfberechtigungen fehlen im Profil-Schnellzugriff.');
}

echo "PASS: Gemeinsamer Schnellzugriff verlinkt die Aktionsbereiche\n";
