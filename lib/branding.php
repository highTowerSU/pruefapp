<?php

use RedBeanPHP\R as R;

function get_branding(): array
{
    static $branding = null;

    if ($branding !== null) {
        return $branding;
    }

    $brandKey = getenv('APP_BRAND') ?: ($_ENV['APP_BRAND'] ?? '');
    $brandKey = strtolower(trim($brandKey));

    $brandAliases = [
        'bse' => 'bsw',
        'zeniris' => 'ceneos',
    ];

    if ($brandKey !== '' && isset($brandAliases[$brandKey])) {
        $brandKey = $brandAliases[$brandKey];
    }

    $defaults = default_branding_definitions();

    ensure_branding_seeded($defaults);

    $brandBean = null;

    if ($brandKey !== '') {
        $brandBean = R::findOne('company', ' LOWER(slug) = ? ', [$brandKey]);
    }

    if ($brandBean === null) {
        $brandBean = R::findOne('company', ' is_default = 1 ');
    }

    if ($brandBean === null) {
        $brandBean = R::findOne('company');
    }

    if ($brandBean !== null) {
        $branding = map_company_branding($brandBean);

        return $branding;
    }

    // Fallback auf statische Definition, falls keine Datenbankeinträge vorhanden sind.
    $fallbackKey = $brandKey !== '' && isset($defaults[$brandKey]) ? $brandKey : 'ceneos';
    $fallback = $defaults[$fallbackKey] ?? reset($defaults);

    return map_static_branding($fallbackKey, $fallback);
}

function ensure_branding_seeded(array $defaults): void
{
    static $seeded = false;

    if ($seeded) {
        return;
    }

    if (!R::testConnection()) {
        $seeded = true;

        return;
    }

    if (R::count('company') > 0) {
        $seeded = true;

        return;
    }

    foreach ($defaults as $key => $data) {
        $company = R::dispense('company');
        $slug = $data['slug'] ?? $key;
        $company->slug = $slug;
        $company->name = $data['company_name'] ?? ucfirst($key);
        $company->app_title = $data['app_title'] ?? 'Kursverwaltung';
        $company->nav_brand = $data['nav_brand'] ?? 'Kursverwaltung';
        $company->home_headline = $data['home_headline'] ?? '';
        $company->home_intro = $data['home_intro'] ?? '';
        $company->home_details = $data['home_details'] ?? '';
        $company->primary_client = $data['primary_client'] ?? '';
        $company->project_owner = $data['project_owner'] ?? '';
        $company->group_reference = $data['group_reference'] ?? '';
        $company->header_logo_path = $data['header_logo']['path'] ?? '';
        $company->header_logo_alt = $data['header_logo']['alt'] ?? ($company->name ?? '');
        $navColors = $data['nav_colors'] ?? [];
        $company->nav_background_color = $navColors['background'] ?? '';
        $company->nav_text_color = $navColors['text'] ?? '';
        $legal = $data['legal'] ?? [];
        $company->legal_impressum_label = $legal['impressum']['label'] ?? '';
        $company->legal_impressum_url = $legal['impressum']['url'] ?? '';
        $company->legal_privacy_label = $legal['privacy']['label'] ?? '';
        $company->legal_privacy_url = $legal['privacy']['url'] ?? '';
        $company->is_default = !empty($data['is_default']) ? 1 : 0;
        $company->created_at = date('c');
        $company->updated_at = date('c');
        R::store($company);
    }

    // Falls keine Standardfirma markiert wurde, die erste als Standard setzen.
    $defaultCount = R::count('company', ' is_default = 1 ');
    if ($defaultCount === 0) {
        $first = R::findOne('company');
        if ($first !== null) {
            $first->is_default = 1;
            R::store($first);
        }
    }

    $seeded = true;
}

function map_company_branding(\RedBeanPHP\OODBBean $company): array
{
    $normalizeColor = static function (string $value, string $fallback): string {
        $value = trim($value);
        if ($value === '') {
            return strtoupper($fallback);
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        $value = strtoupper($value);
        if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $value)) {
            return strtoupper($fallback);
        }

        return $value;
    };

    $companyName = (string)($company->name ?? '');
    $headerLogoPath = (string)($company->header_logo_path ?? '');
    $headerLogoAlt = trim((string)($company->header_logo_alt ?? '')) ?: $companyName;
    $navBackground = $normalizeColor((string)($company->nav_background_color ?? ''), '#0D6EFD');
    $navText = $normalizeColor((string)($company->nav_text_color ?? ''), '#FFFFFF');

    return [
        'key' => strtolower((string)($company->slug ?? '')) ?: 'company_' . (int)$company->id,
        'company_name' => $companyName,
        'app_title' => (string)($company->app_title ?? 'Kursverwaltung'),
        'nav_brand' => (string)($company->nav_brand ?? 'Kursverwaltung'),
        'home_headline' => (string)($company->home_headline ?? ''),
        'home_intro' => (string)($company->home_intro ?? ''),
        'home_details' => (string)($company->home_details ?? ''),
        'primary_client' => (string)($company->primary_client ?? ''),
        'project_owner' => (string)($company->project_owner ?? ''),
        'group_reference' => (string)($company->group_reference ?? ''),
        'header_logo' => [
            'path' => $headerLogoPath,
            'alt' => $headerLogoAlt,
        ],
        'nav_colors' => [
            'background' => $navBackground,
            'text' => $navText,
        ],
        'legal' => [
            'impressum' => [
                'label' => (string)($company->legal_impressum_label ?? ''),
                'url' => (string)($company->legal_impressum_url ?? ''),
            ],
            'privacy' => [
                'label' => (string)($company->legal_privacy_label ?? ''),
                'url' => (string)($company->legal_privacy_url ?? ''),
            ],
        ],
    ];
}

function map_static_branding(string $key, array $data): array
{
    $branding = $data;
    $branding['key'] = $key;
    $branding['company_name'] = $data['company_name'] ?? ($data['primary_client'] ?? ucfirst($key));

    $normalizeColor = static function (string $value, string $fallback): string {
        $value = trim($value);
        if ($value === '') {
            return strtoupper($fallback);
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        $value = strtoupper($value);
        if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $value)) {
            return strtoupper($fallback);
        }

        return $value;
    };

    $navColors = $data['nav_colors'] ?? [];
    $branding['nav_colors'] = [
        'background' => $normalizeColor((string)($navColors['background'] ?? ''), '#0D6EFD'),
        'text' => $normalizeColor((string)($navColors['text'] ?? ''), '#FFFFFF'),
    ];

    $branding['header_logo'] = $data['header_logo'] ?? ['path' => '', 'alt' => ''];
    if (empty($branding['header_logo']['alt'])) {
        $branding['header_logo']['alt'] = $branding['company_name'];
    }

    return $branding;
}

function default_branding_definitions(): array
{
    return [
        'bsw' => [
            'company_name' => 'BSW Consult GmbH',
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der BSW Consult GmbH',
            'home_intro' => 'Hier bündelst du das Kursmanagement der BSW Consult GmbH – von digitalen Schulungen bis zu Präsenzangeboten.',
            'home_details' => 'Das Tool wurde als Softwareprojekt der CENEOS GmbH entwickelt und lässt sich flexibel innerhalb des Koenigsbl.au Unternehmensverbunds einsetzen.',
            'primary_client' => 'BSW Consult GmbH',
            'project_owner' => 'CENEOS GmbH',
            'group_reference' => 'Koenigsbl.au Unternehmensverbund',
            'header_logo' => [
                'path' => 'public/img/bsw-consult-logo.svg',
                'alt' => 'BSW Consult GmbH',
            ],
            'nav_colors' => [
                'background' => '#000080',
                'text' => '#FFFFFF',
            ],
            'legal' => [
                'impressum' => [
                    'label' => 'Impressum',
                    'url' => 'https://www.bse-consult.de/impressum/',
                ],
                'privacy' => [
                    'label' => 'Datenschutz',
                    'url' => 'https://www.bse-consult.de/datenschutz/',
                ],
            ],
        ],
        'ceneos' => [
            'company_name' => 'CENEOS GmbH',
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der CENEOS GmbH',
            'home_intro' => 'Koordiniere interne und externe Schulungen zentral über die Plattform der CENEOS GmbH.',
            'home_details' => 'Als Teil des Koenigsbl.au Unternehmensverbunds kann die Lösung flexibel für weitere Gesellschaften angepasst werden.',
            'primary_client' => 'CENEOS GmbH',
            'project_owner' => 'CENEOS GmbH',
            'group_reference' => 'Koenigsbl.au Unternehmensverbund',
            'header_logo' => [
                'path' => 'public/img/ceneos-logo.svg',
                'alt' => 'CENEOS GmbH',
            ],
            'nav_colors' => [
                'background' => '#FED136',
                'text' => '#000000',
            ],
            'legal' => [],
            'is_default' => true,
        ],
        'koenigsblau' => [
            'company_name' => 'Koenigsbl.au',
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung von Koenigsbl.au',
            'home_intro' => 'Steuere Schulungen, Projekt-Trainings und Mandantenkurse zentral für die Unternehmen des Koenigsbl.au Verbunds.',
            'home_details' => 'Realisiert als Softwareprojekt der CENEOS GmbH lässt sich das Modul für alle Gesellschaften des Koenigsbl.au Unternehmensverbunds anpassen – beispielsweise auch für die BSW Consult GmbH.',
            'primary_client' => 'Koenigsbl.au',
            'project_owner' => 'CENEOS GmbH',
            'group_reference' => 'Koenigsbl.au Unternehmensverbund',
            'header_logo' => [
                'path' => 'public/img/koenigsblau-gruppe-logo.svg',
                'alt' => 'Koenigsbl.au',
            ],
            'nav_colors' => [
                'background' => '#FED136',
                'text' => '#000000',
            ],
            'legal' => [
                'impressum' => [
                    'label' => 'Impressum',
                    'url' => 'https://www.koenigsblau.com/impressum/',
                ],
                'privacy' => [
                    'label' => 'Datenschutz',
                    'url' => 'https://www.koenigsblau.com/datenschutz/',
                ],
            ],
        ],
    ];
}

/**
 * @param mixed $default
 * @return mixed
 */
function branding_value(string $key, $default = null)
{
    $branding = get_branding();

    return array_key_exists($key, $branding) ? $branding[$key] : $default;
}
