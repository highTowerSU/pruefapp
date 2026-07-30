<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use RedBeanPHP\R;

function config_value(string $name): ?string
{
    return null;
}

R::setup('sqlite::memory:');

require dirname(__DIR__) . '/lib/branding.php';

$loginBranding = get_login_branding();
if (($loginBranding['key'] ?? null) !== 'ceneos') {
    throw new RuntimeException('The seeded login branding is not CENEOS.');
}

if (($loginBranding['header_logo']['path'] ?? null) !== 'public/img/ceneos-logo.svg') {
    throw new RuntimeException('The CENEOS logo is not assigned to the login branding.');
}

if (($loginBranding['nav_colors']['background'] ?? null) !== '#FED136') {
    throw new RuntimeException('The CENEOS brand color is not assigned.');
}

$bsw = R::findOne('company', ' slug = ? ', ['bsw']);
$bswBranding = $bsw !== null ? get_company_branding((int) $bsw->id) : null;
if (($bswBranding['header_logo']['path'] ?? null) !== 'public/img/bsw-consult-logo.svg') {
    throw new RuntimeException('Link branding cannot resolve the selected company.');
}

$tenantLogin = get_login_branding('bsw');
if (($tenantLogin['key'] ?? null) !== 'bsw') {
    throw new RuntimeException('The tenant query cannot select the login branding.');
}
if (empty($tenantLogin['logos']['light']) || empty($tenantLogin['logos']['dark'])) {
    throw new RuntimeException('Light and dark logo variants are missing.');
}
if (($tenantLogin['theme_colors']['primary'] ?? null) !== '#000080') {
    throw new RuntimeException('Primary color is not independent and available.');
}

$columns = R::getColumns('company');
if (!array_key_exists('is_login_brand', $columns)) {
    throw new RuntimeException('The login branding column was not created.');
}

R::close();
