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

accessibility_assert(str_contains($schema, 'userdisplaypreference'), 'Die Darstellungseinstellungen benötigen eine persistente RedBean-Tabelle.');
accessibility_assert(str_contains($service, "'white_black'") && str_contains($service, "'yellow_black'") && str_contains($service, "'green_black'"), 'Die Kontrastpaletten müssen serverseitig validiert werden.');
accessibility_assert(str_contains($service, "R::dispense('userdisplaypreference'") && str_contains($service, "R::findOne('userdisplaypreference'"), 'Darstellungseinstellungen müssen als RedBean-Beans gespeichert werden.');
accessibility_assert(str_contains($layout, 'id="main-content"') && str_contains($layout, 'class="skip-link"'), 'Das Layout benötigt eine Skip-Link-Navigation zum Hauptinhalt.');
accessibility_assert(str_contains($layout, 'data-display-preferences-form'), 'Darstellungsänderungen sollen vor dem Speichern direkt sichtbar sein.');
accessibility_assert(str_contains($profile, 'save_display_preferences') && str_contains($profile, 'Schwarz / Gelb'), 'Das Profil muss die Barrierefreiheitsoptionen anbieten.');
accessibility_assert(str_contains($css, ':focus-visible') && str_contains($css, 'forced-colors: active'), 'Die zentralen Fokus- und Hochkontrastregeln fehlen.');
accessibility_assert(str_contains($css, 'data-motion="reduce"') && str_contains($css, 'data-font-scale="xxlarge"'), 'Bewegungs- und abgestufte Schriftoptionen fehlen.');
accessibility_assert(str_contains($service, "FONT_WEIGHTS = ['standard', 'bold']") && str_contains($css, 'data-font-weight="bold"] body'), 'Die gespeicherte stärkere Textdarstellung muss appweit greifen.');
accessibility_assert(str_contains($service, "FONT_FAMILIES = ['system', 'sans', 'serif', 'mono']") && str_contains($css, 'data-font-family="sans"'), 'Die gespeicherte Schriftarten-Auswahl fehlt.');
accessibility_assert(str_contains($css, '#page-action-navigation') && str_contains($css, '.dropdown-item'), 'Die stärkere Textdarstellung muss auch Aktionen und Buttons einschließen.');
accessibility_assert(str_contains($css, '--bs-emphasis-color') && str_contains($css, '.alert-danger'), 'Die Kontrastmodi müssen auch Bootstrap-Komponenten eindeutig einfärben.');
accessibility_assert(str_contains($css, ':root[data-contrast="yellow_black"]') && str_contains($css, '--bs-body-color: #ffdf00;'), 'Schwarz/Gelb muss Gelb als helle Kontrastfarbe verwenden.');
accessibility_assert(str_contains($navbar, 'Darstellung &amp; Barrierefreiheit'), 'Die Darstellungseinstellungen müssen aus der Navbar erreichbar sein.');
accessibility_assert(str_contains($profile, '<details class="card shadow-sm mb-4" id="display-preferences"'), 'Die Darstellungseinstellungen müssen im Profil einklappbar sein.');

echo "OK: accessibility preferences\n";
