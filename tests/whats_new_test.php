<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/lib/WhatsNewService.php');
$downloads = (string) file_get_contents($root . '/templates/downloads.php');
$changelog = (string) file_get_contents($root . '/CHANGELOG.md');

foreach ([$service, $downloads, $changelog] as $source) {
    if (!str_contains($source, 'Was ist neu') && !str_contains($source, 'WhatsNew')) {
        throw new RuntimeException('Changelog oder sichtbare Was-ist-neu-Ansicht fehlt.');
    }
}
if (!str_contains($service, 'ReleaseNotePublisher::publishForUser') || !str_contains($downloads, 'id="whats-new"')) {
    throw new RuntimeException('Nutzerrelevante Änderungen werden nicht als persönliche Benachrichtigung und in der GUI angezeigt.');
}

echo "PASS: Changelog und Was-ist-neu-Benachrichtigungen sind eingebunden\n";
