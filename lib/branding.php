<?php

use RedBeanPHP\R as R;
use Ceneos\PhpBase\Tenant\TenantRepository;

function branding_request_host(): string
{
    return strtolower(trim(explode(':', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''), 2)[0]));
}

/** Returns the tenant configured for a hostname, without URL-prefix lookup. */
function branding_key_for_configured_host(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') return '';

    try {
        $tenant = (new TenantRepository())->findByPublicHost($host);
        if ($tenant !== null) return strtolower(trim((string) ($tenant->slug ?? '')));
    } catch (\Throwable $error) {
        // The static configuration below remains a safe startup fallback if a
        // tenant database migration has not run yet.
        error_log('Mandanten-Hostzuordnung konnte nicht gelesen werden: ' . $error->getMessage());
    }

    $hostBrands = \Ceneos\PhpBase\Config\Config::get('APP_BRAND_HOSTS');
    if (!is_array($hostBrands)) {
        $rawHostBrands = trim((string) (getenv('APP_BRAND_HOSTS') ?: ''));
        $hostBrands = $rawHostBrands !== '' ? json_decode($rawHostBrands, true) : [];
    }
    if (is_array($hostBrands) && !empty($hostBrands[$host])) {
        return strtolower(trim((string) $hostBrands[$host]));
    }

    // Safe defaults during first rollout and for reverse proxies that do not
    // yet pass APP_BRAND_HOSTS through to PHP.
    return match ($host) {
        'pruef.ceneos.net' => 'ceneos',
        'pruef.bsw-consult.gmbh' => 'bsw',
        'pruef.koenigsbl.au' => 'koenigsblau',
        default => '',
    };
}

/** @return string Empty for dedicated hosts and requests without a tenant prefix. */
function tenant_url_prefix_for_request(): string
{
    static $prefix = null;
    if ($prefix !== null) return $prefix;
    $prefix = '';
    if (PHP_SAPI === 'cli' || branding_key_for_configured_host(branding_request_host()) !== '') return $prefix;

    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/');
    $base = base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    try {
        $tenants = (new TenantRepository())->all();
        usort($tenants, static fn($left, $right): int => strlen((string) ($right->url_prefix ?? '')) <=> strlen((string) ($left->url_prefix ?? '')));
        foreach ($tenants as $tenant) {
            $candidate = rtrim('/' . trim((string) ($tenant->url_prefix ?? ''), '/'), '/');
            if ($candidate === '') $candidate = '/' . trim((string) ($tenant->slug ?? ''), '/');
            if ($candidate !== '/' && ($path === $candidate || str_starts_with($path, $candidate . '/'))) {
                return $prefix = $candidate;
            }
        }
    } catch (\Throwable $error) {
        error_log('Mandanten-URL-Präfix konnte nicht gelesen werden: ' . $error->getMessage());
    }
    return $prefix;
}

/** Returns the brand/tenant selected by the public hostname or URL prefix. */
function branding_key_for_request_host(): string
{
    $hostKey = branding_key_for_configured_host(branding_request_host());
    if ($hostKey !== '') return $hostKey;

    $prefix = tenant_url_prefix_for_request();
    if ($prefix !== '') {
        try {
            foreach ((new TenantRepository())->all() as $tenant) {
                $candidate = rtrim('/' . trim((string) ($tenant->url_prefix ?? ''), '/'), '/');
                if ($candidate === '') $candidate = '/' . trim((string) ($tenant->slug ?? ''), '/');
                if ($candidate === $prefix) return strtolower(trim((string) ($tenant->slug ?? '')));
            }
        } catch (\Throwable $error) {
            error_log('Mandanten-Präfixzuordnung konnte nicht gelesen werden: ' . $error->getMessage());
        }
    }
    return '';
}

function get_branding(): array
{
    static $branding = null;

    if ($branding !== null) {
        return $branding;
    }

    $brandKey = config_value('APP_BRAND') ?? '';
    // A shared deployment can serve several branded hosts. The hostname wins
    // for browser requests, while CLI/Cron continues to use APP_BRAND and
    // report generation explicitly selects the inspection tenant.
    $hostBrand = branding_key_for_request_host();
    if ($hostBrand !== '') $brandKey = $hostBrand;
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
    ensure_company_branding_schema();

    $brandBean = null;

    if ($brandKey !== '') {
        $brandBean = (new TenantRepository())->findBySlug($brandKey);
    }

    // CENEOS is the defined fallback tenant for unbranded/internal requests.
    // A legacy is_default flag may still exist in the database, but must not
    // make an unrelated tenant the public default.
    if ($brandBean === null) {
        $brandBean = (new TenantRepository())->findBySlug('ceneos');
    }
    if ($brandBean === null) {
        $brandBean = (new TenantRepository())->default();
    }

    if ($brandBean === null) {
        $brandBean = (new TenantRepository())->first();
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

function get_login_branding(?string $tenantSlug = null): array
{
    $defaults = default_branding_definitions();
    ensure_branding_seeded($defaults);
    ensure_company_branding_schema();

    $repository = new TenantRepository();
    // The host deliberately wins over query/form values. A login page at a
    // branded address must never accidentally present another tenant.
    $tenantSlug = branding_key_for_request_host() ?: strtolower(trim((string) $tenantSlug));
    if ($tenantSlug === '') $tenantSlug = 'ceneos';
    $company = $tenantSlug !== '' ? $repository->findBySlug($tenantSlug) : null;
    $company ??= $repository->findBySlug('ceneos');
    $company ??= $repository->login();

    return $company !== null ? map_company_branding($company) : get_branding();
}

function get_company_branding(int $companyId): ?array
{
    if ($companyId < 1) {
        return null;
    }

    ensure_branding_seeded(default_branding_definitions());
    ensure_company_branding_schema();

    $company = (new TenantRepository())->find($companyId);

    return $company !== null ? map_company_branding($company) : null;
}

function get_report_branding(?int $companyId = null): array
{
    ensure_branding_seeded(default_branding_definitions());
    ensure_company_branding_schema();
    if ($companyId !== null && $companyId > 0) {
        $company = (new TenantRepository())->find($companyId);
        if ($company !== null) return map_company_branding($company);
    }
    $company = (new TenantRepository())->findBySlug('ceneos');
    return $company !== null ? map_company_branding($company) : map_static_branding('ceneos', default_branding_definitions()['ceneos']);
}

/**
 * @return array<int, \RedBeanPHP\OODBBean>
 */
function get_branding_companies(): array
{
    ensure_branding_seeded(default_branding_definitions());
    ensure_company_branding_schema();

    return (new TenantRepository())->all();
}

function ensure_branding_seeded(array $defaults): void
{
    static $seeded = false;

    if ($seeded) {
        return;
    }

    (new TenantRepository())->seed($defaults);

    $seeded = true;
}

function ensure_company_branding_schema(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    (new TenantRepository())->ensureSchema();

    $ready = true;
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
        'company_id' => (int) $company->id,
        'key' => strtolower((string)($company->slug ?? '')) ?: 'company_' . (int)$company->id,
        'public_host' => strtolower(trim((string) ($company->public_host ?? ''))),
        'url_prefix' => rtrim('/' . trim((string) ($company->url_prefix ?? ''), '/'), '/') ?: ((string) ($company->slug ?? '') !== '' ? '/' . (string) $company->slug : ''),
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
        'logos' => [
            'light' => (string) ($company->logo_light_path ?? '') ?: $headerLogoPath,
            'dark' => (string) ($company->logo_dark_path ?? '') ?: $headerLogoPath,
            'long' => (string) ($company->logo_long_path ?? '') ?: ((string) ($company->logo_light_path ?? '') ?: $headerLogoPath),
            'alt' => $headerLogoAlt,
        ],
        'theme_colors' => [
            'primary' => $normalizeColor((string) ($company->primary_color ?? ''), $navBackground),
            'primary_text' => $normalizeColor((string) ($company->primary_text_color ?? ''), $navText),
            'light' => $normalizeColor((string) ($company->light_color ?? ''), '#F8F9FA'),
            'dark' => $normalizeColor((string) ($company->dark_color ?? ''), '#212529'),
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
    $branding['logos'] = $data['logos'] ?? [
        'light' => $branding['header_logo']['path'] ?? '',
        'dark' => $branding['header_logo']['path'] ?? '',
        'long' => $branding['header_logo']['path'] ?? '',
        'alt' => $branding['header_logo']['alt'],
    ];
    $branding['theme_colors'] = [
        'primary' => $normalizeColor((string) ($data['primary_color'] ?? ''), $branding['nav_colors']['background']),
        'primary_text' => $normalizeColor((string) ($data['primary_text_color'] ?? ''), $branding['nav_colors']['text']),
        'light' => $normalizeColor((string) ($data['light_color'] ?? ''), '#F8F9FA'),
        'dark' => $normalizeColor((string) ($data['dark_color'] ?? ''), '#212529'),
    ];

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
