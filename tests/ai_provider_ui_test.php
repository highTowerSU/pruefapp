<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/lib/AiProviderService.php');
$vocabularyService = (string) file_get_contents($root . '/lib/DeviceVocabularyService.php');
$template = (string) file_get_contents($root . '/templates/settings_ai_provider.php');
$device = (string) file_get_contents($root . '/templates/device_index.php');
$controller = (string) file_get_contents($root . '/controllers/DeviceController.php');
$vocabularyController = (string) file_get_contents($root . '/controllers/VocabularyController.php');
$vocabularyTemplate = (string) file_get_contents($root . '/templates/vocabulary.php');
$maintenance = (string) file_get_contents($root . '/lib/MaintenanceJobHandler.php');
$adminController = (string) file_get_contents($root . '/controllers/AdminController.php');
$routes = (string) file_get_contents($root . '/index.php');
$searchSelect = (string) file_get_contents($root . '/public/js/search-select.js');
$customCss = (string) file_get_contents($root . '/public/css/custom.css');

foreach ([
    [str_contains($service, "'aiprovider'") && str_contains($service, 'migrateLegacyProvider'), 'KI-Provider werden nicht zentral und migrationssicher verwaltet.'],
    [str_contains($template, 'hx-post=') && str_contains($template, 'OAuth 2.0 statt API-Token verwenden') && str_contains($template, 'ai-provider-model-options') && str_contains($template, 'KI-Testanfrage') && str_contains($service, 'function diagnose') && str_contains($template, "vocabulary_ai_model: 'smart'") && str_contains($template, "vocabulary_ai_model: 'mistral@latest'") && str_contains($template, 'data-no-search') && str_contains($template, 'OVHcloud AI Endpoints') && str_contains($template, 'IONOS AI Model Hub'), 'Die KI-Providerkarte ist nicht kompakt per HTMX, editierbarer Modell-Auswahl, Diagnose und Provider-Voreinstellungen umgesetzt.'],
    [str_contains($controller, 'vocabularyOptions') && str_contains($routes, '/geraete/stammdaten-optionen') && str_contains($device, 'vocabularyEndpoint'), 'Kontextbezogene Stammdatenoptionen werden nicht serverseitig geladen.'],
    [str_contains($device, 'exact') && str_contains($device, 'createOnBlur: false'), 'Bekannte Werte bleiben beim Verlassen nicht erhalten oder neue Werte werden nicht explizit bestätigt.'],
    [str_contains($searchSelect, 'htmx:load') && str_contains($customCss, 'select.form-select + .ts-wrapper'), 'TomSelect-Felder werden nach HTMX-Swaps nicht zuverlässig als einzelnes Feld dargestellt.'],
    [str_contains($vocabularyService, 'enqueueHistoricalReview') && str_contains($maintenance, 'vocabularyReviewScan') && str_contains($vocabularyController, '$action === \'scan\'') && str_contains($vocabularyTemplate, 'Prüfung starten') && str_contains($vocabularyTemplate, 'vocabulary-auto-refresh') && str_contains($vocabularyTemplate, 'htmx.ajax'), 'Der manuelle, fortsetzbare KI-Lauf über vorhandene Stammdaten mit Fortschrittsaktualisierung fehlt.'],
    [str_contains($adminController, "\$summary === 'ai'") && str_contains($adminController, 'temporary_model_override') && str_contains($adminController, 'aiProviderApiDebug') && str_contains($adminController, 'AiProviderService::diagnose'), 'Der token-geschützte KI-Diagnoseendpunkt fehlt.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: KI-Provider und kontextbezogene Stammdatenauswahl sind vorbereitet\n";
