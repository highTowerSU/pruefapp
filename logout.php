<?php
require_once __DIR__ . '/lib/lib.inc.php';

\Ceneos\PhpBase\Auth\KeycloakLogout::redirect(
    (string) config_value('APP_OIDC_ISSUER_URL'),
    (string) config_value('APP_OIDC_CLIENT_ID'),
    absolute_url_for('login.php'),
    (string) ($_SESSION['ceneos_id_token'] ?? '')
);
