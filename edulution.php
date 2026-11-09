<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/lib.inc.php';

\Ceneos\PhpBase\Auth\EdulutionEntry::dispatch(
    $_SERVER,
    $_GET,
    isset($_SESSION['auth_user_id']),
    url_for('geraete'),
    static function (): void {
        require __DIR__ . '/login.php';
    }
);
