<?php

declare(strict_types=1);

$bootstrap = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');

if (!str_contains($bootstrap, "HTTP_HX_REQUEST'] ?? '') === 'true'")
    || !str_contains($bootstrap, "header('HX-Redirect: ' . \$loginUrl)")
    || !str_contains($bootstrap, 'http_response_code(204)')) {
    throw new RuntimeException('Abgelaufene HTMX-Sitzungen werden nicht zuverlässig zur Anmeldung umgeleitet.');
}

echo "PASS: expired HTMX sessions use a top-level login redirect\n";
