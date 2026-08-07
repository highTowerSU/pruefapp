<?php

declare(strict_types=1);

$service = (string) file_get_contents(dirname(__DIR__) . '/lib/VocabularyOAuthService.php');
$baseService = (string) file_get_contents(dirname(__DIR__, 2) . '/ceneos-php-base/src/Integration/OAuthAuthorizationCodePkce.php');
$settings = (string) file_get_contents(dirname(__DIR__) . '/controllers/SettingsController.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/settings.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');

foreach ([
    [str_contains($service, 'OAuthAuthorizationCodePkce::begin') && str_contains($service, 'OAuthAuthorizationCodePkce::refresh') && str_contains($baseService, "'code_challenge_method' => 'S256'") && str_contains($baseService, "'grant_type' => 'authorization_code'") && str_contains($baseService, "'grant_type' => 'refresh_token'"), 'OAuth verwendet nicht die gemeinsame Base-Implementierung mit PKCE und Refresh-Token.'],
    [str_contains($settings, 'vocabularyOAuthCallback') && str_contains($routes, '/admin/konfiguration/ki-oauth/callback'), 'OAuth-Callback ist nicht geroutet.'],
    [str_contains($template, 'OAuth verbinden') && str_contains($template, 'Callback-URL:'), 'OAuth-Konfiguration ist nicht in der Superadmin-GUI auffindbar.'],
] as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: OAuth-Anbindung für die KI-Stammdatenprüfung ist konfigurierbar\n";
