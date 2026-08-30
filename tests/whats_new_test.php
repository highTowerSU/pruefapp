<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/lib/WhatsNewService.php');
$downloads = (string) file_get_contents($root . '/templates/downloads.php');
$whatsNewTemplate = (string) file_get_contents($root . '/templates/_whats_new.php');
$changelog = (string) file_get_contents($root . '/CHANGELOG.md');

foreach ([$service, $whatsNewTemplate, $changelog] as $source) {
    if (!str_contains($source, 'Was ist neu') && !str_contains($source, 'WhatsNew')) {
        throw new RuntimeException('Changelog oder sichtbare Was-ist-neu-Ansicht fehlt.');
    }
}
if (!str_contains($service, 'ReleaseNotePublisher::publishForUser') || !str_contains($downloads, "render_template('_whats_new.php'") || !str_contains($whatsNewTemplate, 'WhatsNewChecklist::render')) {
    throw new RuntimeException('Nutzerrelevante Änderungen werden nicht als persönliche Benachrichtigung und in der GUI angezeigt.');
}
if (!str_contains($service, "return array_merge([[\n            'id' => '2026-08-30-candidate-ods-evidence'")) {
    throw new RuntimeException('Die neueste Prüfapp-Änderung muss vor gemeinsamen Base-Änderungen stehen.');
}

echo "PASS: Changelog und Was-ist-neu-Benachrichtigungen sind eingebunden\n";
