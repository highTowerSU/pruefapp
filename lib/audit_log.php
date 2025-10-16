<?php

declare(strict_types=1);

use RedBeanPHP\R as R;

/**
 * Writes an audit log entry for administrative actions.
 *
 * @param array<string, mixed> $context
 */
function audit_log(string $action, array $context = []): void
{
    if (!class_exists(R::class)) {
        return;
    }

    try {
        $entry = R::dispense('auditlog');
        $entry->aktion = $action;
        $entry->nutzername = audit_log_current_username();
        $entry->anzeige_name = audit_log_current_display_name();
        $entry->ip_adresse = audit_log_detect_ip();
        $entry->details_json = audit_log_encode_context($context);
        $entry->erstellt_am = audit_log_now();

        R::store($entry);
    } catch (\Throwable $exception) {
        error_log('Audit-Log konnte nicht gespeichert werden: ' . $exception->getMessage());
    }
}

function audit_log_mask_token(string $token, int $visibleCharacters = 4): string
{
    $token = trim($token);
    if ($token === '') {
        return '';
    }

    $visibleCharacters = max(1, $visibleCharacters);

    $length = mb_strlen($token);
    if ($length <= $visibleCharacters * 2) {
        return $token;
    }

    $start = mb_substr($token, 0, $visibleCharacters);
    $end = mb_substr($token, -$visibleCharacters);

    return $start . '…' . $end;
}

function audit_log_current_username(): string
{
    $user = $_SESSION['user'] ?? null;
    if (!is_object($user)) {
        return '';
    }

    foreach (['preferred_username', 'email', 'sub'] as $property) {
        if (isset($user->{$property}) && $user->{$property} !== '') {
            return (string) $user->{$property};
        }
    }

    return '';
}

function audit_log_current_display_name(): string
{
    $user = $_SESSION['user'] ?? null;
    if (!is_object($user)) {
        return '';
    }

    foreach (['name', 'given_name', 'family_name'] as $property) {
        if (isset($user->{$property}) && $user->{$property} !== '') {
            return (string) $user->{$property};
        }
    }

    $username = audit_log_current_username();

    return $username;
}

function audit_log_detect_ip(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (is_string($forwarded) && $forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        $ip = $parts[0] ?? '';
        if ($ip !== '') {
            return $ip;
        }
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($remote) ? $remote : '';
}

/**
 * @param array<string, mixed> $context
 */
function audit_log_encode_context(array $context): string
{
    $normalized = audit_log_normalize_context($context);

    try {
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $exception) {
        error_log('Audit-Log Details konnten nicht kodiert werden: ' . $exception->getMessage());
        return '[]';
    }

    if ($encoded === false) {
        return '[]';
    }

    return $encoded;
}

/**
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function audit_log_normalize_context(array $context): array
{
    $normalized = [];

    foreach ($context as $key => $value) {
        $normalized[(string) $key] = audit_log_normalize_value($value);
    }

    return $normalized;
}

/**
 * @return mixed
 */
function audit_log_normalize_value(mixed $value)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[(string) $k] = audit_log_normalize_value($v);
        }
        return $result;
    }

    if ($value instanceof \DateTimeInterface) {
        return $value->format(DATE_ATOM);
    }

    if (is_object($value)) {
        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        if ($value instanceof \JsonSerializable) {
            try {
                return $value->jsonSerialize();
            } catch (\Throwable) {
                return get_class($value);
            }
        }

        return get_class($value);
    }

    if (is_resource($value)) {
        return get_resource_type($value);
    }

    if (is_scalar($value) || $value === null) {
        return $value;
    }

    return (string) $value;
}

function audit_log_now(): string
{
    try {
        $timezone = new \DateTimeZone(date_default_timezone_get());
    } catch (\Throwable) {
        $timezone = new \DateTimeZone('UTC');
    }

    $now = new \DateTimeImmutable('now', $timezone);

    return $now->format(DATE_ATOM);
}
