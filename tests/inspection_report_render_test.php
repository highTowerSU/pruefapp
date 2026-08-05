#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/controllers/ReportController.php';

$target = $argv[1] ?? sys_get_temp_dir() . '/pruefapp-inspection-report.pdf';
if (!str_starts_with($target, '/')) {
    $target = getcwd() . '/' . $target;
}
$statusVariant = mb_strtolower(trim((string) ($argv[2] ?? 'bestanden')));
if (!in_array($statusVariant, ['bestanden', 'nicht bestanden', 'ausstehend'], true)) {
    fwrite(STDERR, "Status muss 'bestanden', 'nicht bestanden' oder 'ausstehend' sein.\n");
    exit(1);
}

$signatureSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="120" viewBox="0 0 360 120">'
    . '<path d="M18 84 C45 10 42 108 80 38 S97 106 132 43 C150 15 134 101 184 55 S235 30 217 83 C252 60 282 57 338 64" fill="none" stroke="#111" stroke-width="4" stroke-linecap="round"/>'
    . '</svg>';
$signature = 'data:image/svg+xml;base64,' . base64_encode($signatureSvg);

$checklist = match ($statusVariant) {
    'nicht bestanden' => ['label' => 'ja', 'leitung' => 'nein', 'gehaeuse' => 'ja', 'stecker' => 'ja', 'funktion' => 'nein'],
    'ausstehend' => ['label' => 'offen', 'leitung' => 'offen', 'gehaeuse' => 'offen', 'stecker' => 'offen', 'funktion' => 'offen'],
    default => ['label' => 'ja', 'leitung' => 'ja', 'gehaeuse' => 'ja', 'stecker' => 'ja', 'funktion' => 'ja'],
};
$measurements = $statusVariant === 'ausstehend' ? [] : [
    [
        'name' => 'RPE',
        'value' => $statusVariant === 'nicht bestanden' ? '0,72' : '0,11',
        'unit' => 'Ω',
        'result' => $statusVariant,
    ],
    ['name' => 'RISO', 'value' => '> 20', 'unit' => 'MΩ', 'result' => 'bestanden'],
];

$raw = [
    'audit_ok' => $statusVariant === 'bestanden',
    'end_time' => '2026-08-05T02:35:00+02:00',
];

$rows = [
    ['Prüfung', 'Wert'],
    ['Prüfnummer', '100015971-26-2'],
    ['Datum', '2026-04-08'],
    ['Prüfart', 'Wiederholungsprüfung SK1 für euP unter der Leitung der VEFK'],
    ['Prüfer', 'Eandro Leon Debertshäuser'],
    ['Nächste Prüfung', '2027-04-08'],
    ['Gerät', '100015971 · Baustromverteiler'],
    ['Inventarnummer', 'AK-4711'],
    ['Geräteart', 'Baustromverteiler'],
    ['Hersteller', 'Brennenstuhl'],
    ['Typ', '4007123667604'],
    ['Wärmegerät', 'Nein'],
    ['Auftraggeber', 'Antoniuskolleg'],
    ['Liegenschaft', 'Neunkirchen'],
    ['Gebäude', 'Klassentrakt Johanneshaus'],
    ['Etage', 'K0'],
    ['Raum-Nr.', 'K009'],
    ['Ergebnis', $statusVariant],
    ['Regiezeit', '0 Minuten'],
    ['Prüfungsabschluss', '2026-08-05T02:35:00+02:00'],
    ['__checklist_json', json_encode($checklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ['__measurements_json', json_encode($measurements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ['__raw_json', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ['__profile_signature', $signature],
];

$branding = [
    'company_name' => 'CENEOS GmbH',
    'logos' => ['long' => dirname(__DIR__) . '/public/img/ceneos-logo.svg'],
    'theme_colors' => ['primary' => '#FED136', 'primary_text' => '#000000'],
    'nav_colors' => ['background' => '#FED136', 'text' => '#000000'],
];

$pdf = ReportController::renderPdf($rows, 'Prüfbericht-Test', $branding);
if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) < 10000) {
    fwrite(STDERR, "PDF-Erzeugung fehlgeschlagen oder Ergebnis unplausibel klein.\n");
    exit(1);
}
if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0770, true) && !is_dir(dirname($target))) {
    fwrite(STDERR, "Zielverzeichnis konnte nicht angelegt werden.\n");
    exit(1);
}
file_put_contents($target, $pdf, LOCK_EX);

$info = trim((string) shell_exec('pdfinfo ' . escapeshellarg($target) . ' 2>/dev/null'));
$text = trim((string) shell_exec('pdftotext ' . escapeshellarg($target) . ' - 2>/dev/null'));
$required = [
    'Prüfbericht',
    'Prüfberichts-Nr.',
    'Prüfergebnisse',
    'Fragentyp',
    'Prüffrage',
    'Kriterium',
    'Ergebnis',
    'Unterschrift',
    'Baustromverteiler',
    'Beschriftung vollständig',
    'sicherheitsrelevanten Funktionen',
    $statusVariant,
];
foreach ($required as $needle) {
    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "Erwarteter PDF-Inhalt fehlt: {$needle}\n");
        exit(1);
    }
}
if (!str_contains($info, 'A4') || !preg_match('/Pages:\s+1\b/', $info)) {
    fwrite(STDERR, "Der Testbericht ist nicht genau eine A4-Seite.\n{$info}\n");
    exit(1);
}

$pngBase = preg_replace('/\.pdf$/i', '', $target) ?: $target;
shell_exec('pdftoppm -f 1 -singlefile -png -r 130 ' . escapeshellarg($target) . ' ' . escapeshellarg($pngBase) . ' 2>/dev/null');
echo "OK: {$target}\n";
echo "Vorschau: {$pngBase}.png\n";
