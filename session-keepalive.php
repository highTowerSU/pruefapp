<?php
require_once __DIR__ . '/lib/lib.inc.php';
if (isset($_GET['ceneos-address'])) {
    $allowed = current_user() !== null && current_user_has_role('editor','inspector');
    \Ceneos\PhpBase\Customer\AddressLookup::respond($allowed ? (int)(get_branding()['company_id'] ?? 0) : 0, $allowed);
}
\Ceneos\PhpBase\Session\SessionRenewal::respond(
    current_user() !== null,
    static fn () => \Ceneos\PhpBase\Auth\OidcConfig::fromConfig(absolute_url_for('callback.php'))->createClient(),
    static function (): void { unset($_SESSION['user'], $_SESSION['auth_user_id'], $_SESSION['user_role']); }
);
