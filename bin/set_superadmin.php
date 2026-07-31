#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/lib.inc.php';

$email = trim((string) ($argv[1] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Verwendung: php bin/set_superadmin.php user@example.org\n");
    exit(2);
}

$user = R::findOne('oauthuser', ' LOWER(email) = LOWER(?) ', [$email]);
if (!$user || !$user->id) {
    fwrite(STDERR, "Kein synchronisierter Nutzer mit dieser E-Mail-Adresse gefunden.\n");
    exit(1);
}

$oldRole = (string) ($user->role ?? '');
$user->role = 'superadmin';
$user->updated_at = date(DATE_ATOM);
R::store($user);
audit_log('superadmin_gesetzt', ['oauthuser_id' => (int) $user->id, 'rolle_alt' => $oldRole]);
echo json_encode(['id' => (int) $user->id, 'email' => (string) $user->email, 'role' => 'superadmin'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
