<?php

declare(strict_types=1);

$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/css/custom.css');

if (!str_contains($navbar, '<header class="sticky-top app-navbar-shell noprint">')
    || !str_contains($navbar, '<nav class="navbar navbar-expand-lg navbar-themed"')
    || !str_contains($css, '.app-navbar-shell { z-index: 1030; }')
) {
    throw new RuntimeException('Die gesamte Kopfzeile verwendet nicht den Bootstrap-Sticky-Header.');
}

echo "PASS: Navbar is wrapped in Bootstrap sticky header shell\n";
