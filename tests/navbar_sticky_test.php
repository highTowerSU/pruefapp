<?php

declare(strict_types=1);

$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$notifications = (string) file_get_contents(dirname(__DIR__) . '/templates/_notifications_dropdown.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/css/custom.css');

if (!str_contains($navbar, '<header class="sticky-top app-navbar-shell noprint">')
    || !str_contains($navbar, '<nav class="navbar navbar-expand-lg navbar-themed"')
    || !str_contains($css, '.app-navbar-shell { z-index: 1030; }')
    || !str_contains($css, '@media (min-width: 992px) and (hover: hover) and (pointer: fine)')
    || !str_contains($css, '.navbar-themed .navbar-nav > .nav-item.dropdown:hover > .dropdown-menu')
    || !str_contains($navbar, 'id="importNavigationDropdown"')
    || !str_contains($navbar, '#report-regeneration')
    || !str_contains($navbar, 'Import &amp; Sync')
    || substr_count($navbar, 'navbar-hover-dropdown') < 3
    || !str_contains($notifications, 'data-bs-auto-close="outside"')
) {
    throw new RuntimeException('Die Kopfzeile verwendet keinen vollständigen Sticky-/Desktop-Dropdown-Standard.');
}

echo "PASS: Navbar uses Bootstrap sticky shell and pointer-only menu hover\n";
