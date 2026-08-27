<?php

declare(strict_types=1);

$layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.php');
$import = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_import.php');

if (!str_contains($import, 'job-status-notice sticky-top')
    || !str_contains($layout, '--app-navbar-height: 0px;')
    || !str_contains($layout, '.job-status-notice.sticky-top { top: calc(var(--app-navbar-height) + .5rem);')
    || !str_contains($layout, 'new ResizeObserver(updateNavbarHeight).observe(navbarShell)')) {
    throw new RuntimeException('Statusleiste berücksichtigt die Höhe der sticky Menüleiste nicht.');
}

echo "PASS: Hintergrundstatus bleibt unter der sticky Menüleiste sichtbar\n";
