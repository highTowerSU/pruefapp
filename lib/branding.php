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
    $fallbackKey = $brandKey !== '' && isset($defaults[$brandKey]) ? $brandKey : 'koenigsblau';
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
        $company->slug = $key;
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
        $company->header_logo_alt = $data['header_logo']['alt'] ?? '';
        $company->footer_logos_json = json_encode($data['logos'] ?? [], JSON_UNESCAPED_UNICODE);
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
    $logos = [];
    $rawLogos = (string)($company->footer_logos_json ?? '');
    if ($rawLogos !== '') {
        $decoded = json_decode($rawLogos, true);
        if (is_array($decoded)) {
            foreach ($decoded as $logo) {
                if (!is_array($logo)) {
                    continue;
                }

                $path = trim((string)($logo['path'] ?? ''));
                if ($path === '') {
                    continue;
                }

                $logos[] = [
                    'path' => $path,
                    'alt' => (string)($logo['alt'] ?? ''),
                ];
            }
        }
    }

    return [
        'key' => strtolower((string)($company->slug ?? '')) ?: 'company_' . (int)$company->id,
        'company_name' => (string)($company->name ?? ''),
        'app_title' => (string)($company->app_title ?? 'Kursverwaltung'),
        'nav_brand' => (string)($company->nav_brand ?? 'Kursverwaltung'),
        'home_headline' => (string)($company->home_headline ?? ''),
        'home_intro' => (string)($company->home_intro ?? ''),
        'home_details' => (string)($company->home_details ?? ''),
        'primary_client' => (string)($company->primary_client ?? ''),
        'project_owner' => (string)($company->project_owner ?? ''),
        'group_reference' => (string)($company->group_reference ?? ''),
        'header_logo' => [
            'path' => (string)($company->header_logo_path ?? ''),
            'alt' => (string)($company->header_logo_alt ?? ''),
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
        'logos' => $logos,
    ];
}

function map_static_branding(string $key, array $data): array
{
    $branding = $data;
    $branding['key'] = $key;
    $branding['company_name'] = $data['company_name'] ?? ($data['primary_client'] ?? ucfirst($key));
    $branding['header_logo'] = $data['header_logo'] ?? ['path' => '', 'alt' => ''];

    return $branding;
}

function default_branding_definitions(): array
{
    return [
        'bse' => [
            'company_name' => 'BSE Consult GmbH',
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der BSE Consult GmbH',
            'home_intro' => 'Hier bündelst du das Kursmanagement der BSE Consult GmbH – von digitalen Schulungen bis zu Präsenzangeboten.',
            'home_details' => 'Das Tool wurde als Softwareprojekt der Zeniris GmbH entwickelt und lässt sich flexibel innerhalb der Firmengruppe Königsblau einsetzen.',
            'primary_client' => 'BSE Consult GmbH',
            'project_owner' => 'Zeniris GmbH',
            'group_reference' => 'Firmengruppe Königsblau',
            'header_logo' => [
                'path' => 'public/img/bse-consult-logo.svg',
                'alt' => 'BSE Consult GmbH',
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
            'logos' => [
                [
                    'path' => 'public/img/zeniris-logo.svg',
                    'alt' => 'Zeniris GmbH',
                ],
                [
                    'path' => 'public/img/bse-consult-logo.svg',
                    'alt' => 'BSE Consult GmbH',
                ],
                [
                    'path' => 'public/img/koenigsblau-gruppe-logo.svg',
                    'alt' => 'Firmengruppe Königsblau',
                ],
            ],
        ],
        'koenigsblau' => [
            'company_name' => 'Firmengruppe Königsblau',
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der Firmengruppe Königsblau',
            'home_intro' => 'Steuere Schulungen, Projekt-Trainings und Mandantenkurse zentral für die Unternehmen der Königsblau-Gruppe.',
            'home_details' => 'Realisiert als Softwareprojekt der Zeniris GmbH lässt sich das Modul für alle Gesellschaften der Firmengruppe anpassen – beispielsweise auch für die BSE Consult GmbH.',
            'primary_client' => 'Firmengruppe Königsblau',
            'project_owner' => 'Zeniris GmbH',
            'group_reference' => 'Firmengruppe Königsblau',
            'header_logo' => [
                'path' => 'public/img/koenigsblau-gruppe-logo.svg',
                'alt' => 'Firmengruppe Königsblau',
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
            'logos' => [
                [
                    'path' => 'public/img/zeniris-logo.svg',
                    'alt' => 'Zeniris GmbH',
                ],
                [
                    'path' => 'public/img/koenigsblau-gruppe-logo.svg',
                    'alt' => 'Firmengruppe Königsblau',
                ],
            ],
            'is_default' => true,
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
