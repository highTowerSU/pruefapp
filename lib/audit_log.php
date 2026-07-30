<?php

declare(strict_types=1);

use Ceneos\PhpBase\Audit\AuditLogger;

/**
 * Compatibility facade for application code.
 *
 * @param array<string, mixed> $context
 */
function audit_log(string $action, array $context = []): void
{
    $user = $_SESSION['user'] ?? null;
    $actor = is_object($user) ? get_object_vars($user) : [];
    $actor['preferred_username'] = $actor['preferred_username']
        ?? $actor['email']
        ?? $actor['sub']
        ?? '';
    $actor['name'] = $actor['name']
        ?? trim((string) (($actor['given_name'] ?? '') . ' ' . ($actor['family_name'] ?? '')))
        ?: $actor['preferred_username'];

    (new AuditLogger())->log(
        $action,
        $context,
        $actor,
        AuditLogger::detectIp($_SERVER)
    );
}

function audit_log_mask_token(string $token, int $visibleCharacters = 4): string
{
    return AuditLogger::maskToken($token, $visibleCharacters);
}
