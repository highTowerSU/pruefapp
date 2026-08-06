<?php

declare(strict_types=1);

$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/DownloadController.php');
$navbar = (string) file_get_contents(dirname(__DIR__) . '/templates/_navbar.php');
$dropdown = (string) file_get_contents(dirname(__DIR__) . '/templates/_notifications_dropdown.php');
$badge = (string) file_get_contents(dirname(__DIR__) . '/templates/_notification_badge.php');
$list = (string) file_get_contents(dirname(__DIR__) . '/templates/_notifications_list.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');

$checks = [
    [str_contains($routes, "'/downloads/benachrichtigungen'"), 'Der HTMX-Endpunkt für Benachrichtigungen fehlt.'],
    [str_contains($controller, 'notificationDropdownFragment') && str_contains($controller, 'downloads-notifications') && str_contains($dropdown, "'oob' => true"), 'Gelesen-Aktionen aktualisieren den Glockenzähler nicht mit.'],
    [str_contains($navbar, "render_template('_notifications_dropdown.php'"), 'Die Navigation verwendet den wiederverwendbaren Benachrichtigungs-Renderer nicht.'],
    [str_contains($dropdown, 'hx-trigger="shown.bs.dropdown from:#notificationsDropdown"') && str_contains($dropdown, 'hx-target="this"') && str_contains($dropdown, 'hx-swap="innerHTML"') && str_contains($dropdown, 'hx-post='), 'Das Benachrichtigungsmenü lädt oder aktualisiert nicht per HTMX.'],
    [str_contains($list, 'hx-target="#downloads-notifications"') && str_contains($list, 'hx-swap="outerHTML"') && str_contains($badge, 'hx-swap-oob="outerHTML"'), 'Die Download-Übersicht aktualisiert Benachrichtigungen nicht ohne Seitenreload.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Benachrichtigungen aktualisieren sich per HTMX\n";
