<?php

declare(strict_types=1);

$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/css/custom.css');

if (!str_contains($navbar, '<header class="sticky-top app-navbar-shell noprint">')
    || !str_contains($navbar, '<nav class="navbar navbar-expand-lg navbar-themed"')
    || !str_contains($css, '.app-navbar-shell { z-index: 1030; }')
    || !str_contains($css, '@media (min-width: 992px) and (hover: hover) and (pointer: fine)')
    || !str_contains($css, '.navbar-themed .navbar-nav > .nav-item.dropdown:hover > .dropdown-menu')
) {
    throw new RuntimeException('Die Kopfzeile verwendet keinen vollständigen Sticky-/Desktop-Dropdown-Standard.');
}

echo "PASS: Navbar uses Bootstrap sticky shell and pointer-only menu hover\n";
