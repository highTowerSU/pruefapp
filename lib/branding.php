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
        'bsw' => 'bsw',
        'ceneos' => 'ceneos',
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
        $company->app_title = $data['app_title'] ?? 'Prüf-Doku';
        $company->nav_brand = $data['nav_brand'] ?? 'Prüf-Doku';
        $company->home_headline = $data['home_headline'] ?? '';
        $company->home_intro = $data['home_intro'] ?? '';
        $company->home_details = $data['home_details'] ?? '';
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
        'app_title' => (string)($company->app_title ?? 'Prüf-Doku'),
        'nav_brand' => (string)($company->nav_brand ?? 'Prüf-Doku'),
        'home_headline' => (string)($company->home_headline ?? ''),
        'home_intro' => (string)($company->home_intro ?? ''),
        'home_details' => (string)($company->home_details ?? ''),
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

    $branding['project_owner'] = branding_project_owner();
    $branding['group_reference'] = trim((string)($branding['group_reference'] ?? '')) ?: branding_default_group_reference();

    return $branding;
}

function default_branding_definitions(): array
{
    return [
        'bsw' => [
            'company_name' => 'BSW Consult GmbH',
            'app_title' => 'Prüf-Doku',
            'nav_brand' => 'Prüf-Doku',
            'home_headline' => 'Willkommen in der Prüf-Doku der BSW Consult GmbH',
            'home_intro' => 'Dokumentiere Elektroprüfungen nach DGUV Vorschrift 3 zentral und nachvollziehbar.',
            'home_details' => 'Das Tool wurde als Softwareprojekt der CENEOS GmbH realisiert und ist für weitere Prüfkategorien wie Leitern erweiterbar.',
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
                    'url' => 'https://www.bsw-consult.de/impressum/',
                ],
                'privacy' => [
                    'label' => 'Datenschutz',
                    'url' => 'https://www.bsw-consult.de/datenschutz/',
                ],
            ],
        ],
        'ceneos' => [
            'company_name' => 'CENEOS GmbH',
            'app_title' => 'Prüf-Doku',
            'nav_brand' => 'Prüf-Doku',
            'home_headline' => 'Willkommen in der Prüf-Doku der CENEOS GmbH',
            'home_intro' => 'Erfasse Elektroprüfungen nach DGUV Vorschrift 3 in einer zentralen Plattform.',
            'home_details' => 'Als Teil der Firmengruppe Koenigsbl.au bleibt die Lösung mandantenfähig und kann später um Leitern- und weitere Prüfarten ergänzt werden.',
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
            'app_title' => 'Prüf-Doku',
            'nav_brand' => 'Prüf-Doku',
            'home_headline' => 'Willkommen in der Prüf-Doku von Koenigsbl.au',
            'home_intro' => 'Verwalte Elektroprüfungen nach DGUV Vorschrift 3 zentral mit bestehendem Login.Koenigsbl.au-Zugang.',
            'home_details' => 'Realisiert als Softwareprojekt der CENEOS GmbH und vorbereitet für zusätzliche Prüfdokumentationen wie Leitern, Tritte oder weitere Arbeitsmittel.',
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
