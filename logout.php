<?php
require_once __DIR__ . '/lib/lib.inc.php';

session_destroy();

// Keycloak Logout-URL mit Redirect zurück zur aktuellen App-Instanz
$redirect = urlencode(absolute_url_for());
$realm = env_value('APP_KEYCLOAK_REALM') ?? 'koenigsbl.au';
$serverUrl = rtrim(env_value('APP_KEYCLOAK_SERVER_URL') ?? 'https://login.koenigsbl.au', '/');
$logoutUrl = $serverUrl . '/realms/' . rawurlencode($realm) . '/protocol/openid-connect/logout?redirect_uri=' . $redirect;

header('Location: ' . $logoutUrl);
exit;
