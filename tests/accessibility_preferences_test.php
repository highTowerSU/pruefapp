<?php

declare(strict_types=1);

function accessibility_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$service = file_get_contents($root . '/lib/DisplayPreferenceService.php');
$schema = file_get_contents($root . '/lib/lib.inc.php');
$layout = file_get_contents($root . '/templates/layout.php');
$profile = file_get_contents($root . '/templates/profile.php');
$css = file_get_contents($root . '/public/css/custom.css');
$navbar = file_get_contents($root . '/templates/_navbar.php');

accessibility_assert(str_contains($schema, 'user_display_preference'), 'Die Darstellungseinstellungen benötigen eine persistente Tabelle.');
accessibility_assert(str_contains($service, "'yellow_black'") && str_contains($service, "'green_black'"), 'Die Kontrastpaletten müssen serverseitig validiert werden.');
accessibility_assert(str_contains($service, "R::findOne('user_display_preference'"), 'Darstellungseinstellungen müssen datenbankübergreifend gespeichert werden.');
accessibility_assert(str_contains($layout, 'id="main-content"') && str_contains($layout, 'class="skip-link"'), 'Das Layout benötigt eine Skip-Link-Navigation zum Hauptinhalt.');
accessibility_assert(str_contains($layout, 'data-display-preferences-form'), 'Darstellungsänderungen sollen vor dem Speichern direkt sichtbar sein.');
accessibility_assert(str_contains($profile, 'save_display_preferences') && str_contains($profile, 'Schwarz / Gelb'), 'Das Profil muss die Barrierefreiheitsoptionen anbieten.');
accessibility_assert(str_contains($css, ':focus-visible') && str_contains($css, 'forced-colors: active'), 'Die zentralen Fokus- und Hochkontrastregeln fehlen.');
accessibility_assert(str_contains($css, 'data-motion="reduce"') && str_contains($css, 'data-font-scale="large"'), 'Bewegungs- und Schriftoptionen fehlen.');
accessibility_assert(str_contains($navbar, 'Darstellung &amp; Barrierefreiheit'), 'Die Darstellungseinstellungen müssen aus der Navbar erreichbar sein.');

echo "OK: accessibility preferences\n";
