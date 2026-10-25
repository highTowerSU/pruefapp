<?php
require_once __DIR__ . '/lib/lib.inc.php';
\Ceneos\PhpBase\Session\SessionRenewal::respond(
    current_user() !== null,
    static fn () => \Ceneos\PhpBase\Auth\OidcConfig::fromConfig()->createClient(),
    static function (): void { unset($_SESSION['user'], $_SESSION['auth_user_id'], $_SESSION['user_role']); }
);
