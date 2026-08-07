<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/lib/AiProviderService.php');
$template = (string) file_get_contents($root . '/templates/settings_ai_provider.php');
$device = (string) file_get_contents($root . '/templates/device_index.php');
$controller = (string) file_get_contents($root . '/controllers/DeviceController.php');
$routes = (string) file_get_contents($root . '/index.php');

foreach ([
    [str_contains($service, "'aiprovider'") && str_contains($service, 'migrateLegacyProvider'), 'KI-Provider werden nicht zentral und migrationssicher verwaltet.'],
    [str_contains($template, 'hx-post=') && str_contains($template, 'OAuth 2.0 statt API-Token verwenden') && str_contains($template, 'data-search-select') && str_contains($template, "vocabulary_ai_model: 'smart'") && str_contains($template, "vocabulary_ai_model: 'mistral@latest'") && str_contains($template, 'OVHcloud AI Endpoints') && str_contains($template, 'IONOS AI Model Hub'), 'Die KI-Providerkarte ist nicht kompakt per HTMX, Modell-Auswahl und Provider-Voreinstellungen umgesetzt.'],
    [str_contains($controller, 'vocabularyOptions') && str_contains($routes, '/geraete/stammdaten-optionen') && str_contains($device, 'vocabularyEndpoint'), 'Kontextbezogene Stammdatenoptionen werden nicht serverseitig geladen.'],
    [str_contains($device, 'exact') && str_contains($device, 'createOnBlur: false'), 'Bekannte Werte bleiben beim Verlassen nicht erhalten oder neue Werte werden nicht explizit bestätigt.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: KI-Provider und kontextbezogene Stammdatenauswahl sind vorbereitet\n";
