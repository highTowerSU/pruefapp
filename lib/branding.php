<?php

function get_branding(): array
{
    static $branding = null;

    if ($branding !== null) {
        return $branding;
    }

    $brandKey = getenv('APP_BRAND') ?: ($_ENV['APP_BRAND'] ?? 'bse');
    $brandKey = strtolower(trim($brandKey));

    $brands = [
        'bse' => [
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der BSE Consult GmbH',
            'home_intro' => 'Hier bündelst du das Kursmanagement der BSE Consult GmbH – von digitalen Schulungen bis zu Präsenzangeboten.',
            'home_details' => 'Das Tool wurde als Softwareprojekt der Zeniris GmbH entwickelt und lässt sich flexibel innerhalb der Firmengruppe Königsblau einsetzen.',
            'primary_client' => 'BSE Consult GmbH',
            'project_owner' => 'Zeniris GmbH',
            'group_reference' => 'Firmengruppe Königsblau',
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
            'app_title' => 'Kursverwaltung',
            'nav_brand' => 'Kursverwaltung',
            'home_headline' => 'Willkommen in der Kursverwaltung der Firmengruppe Königsblau',
            'home_intro' => 'Steuere Schulungen, Projekt-Trainings und Mandantenkurse zentral für die Unternehmen der Königsblau-Gruppe.',
            'home_details' => 'Realisiert als Softwareprojekt der Zeniris GmbH lässt sich das Modul für alle Gesellschaften der Firmengruppe anpassen – beispielsweise auch für die BSE Consult GmbH.',
            'primary_client' => 'Firmengruppe Königsblau',
            'project_owner' => 'Zeniris GmbH',
            'group_reference' => 'Firmengruppe Königsblau',
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
        ],
    ];

    if (!array_key_exists($brandKey, $brands)) {
        $brandKey = 'bse';
    }

    $branding = $brands[$brandKey];
    $branding['key'] = $brandKey;

    return $branding;
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
