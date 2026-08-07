<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Workday pairing between an inspection workspace and a mobile browser. */
final class InspectionCompanionService
{
    public static function create(int $inspectionId, int $ownerUserId, bool $replaceExisting = true): array
    {
        // A concrete inspection has one current pairing. General workspaces,
        // however, deliberately support several phones or colleagues at once.
        if ($replaceExisting) R::exec("UPDATE inspection_companion_session SET state = 'disconnected', disconnected_at = ? WHERE inspection_id = ? AND owner_user_id = ? AND state IN ('pending', 'connected')", [date(DATE_ATOM), $inspectionId, $ownerUserId]);
        $token = bin2hex(random_bytes(24));
        $now = date(DATE_ATOM);
        // Pair once and keep the phone attached to the laptop workspace for
        // the working session; the token remains short-lived enough for a
        // workday but no longer forces repeated QR scans.
        R::exec('INSERT INTO inspection_companion_session (inspection_id, owner_user_id, token_hash, state, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)', [$inspectionId, $ownerUserId, hash('sha256', $token), 'pending', $now, date(DATE_ATOM, time() + 28800)]);
        return ['id' => (int) R::getInsertID(), 'token' => $token, 'expires_at' => date(DATE_ATOM, time() + 28800)];
    }

    /** Create a workday pairing before a concrete device or inspection exists. */
    public static function createWorkspace(int $ownerUserId): array
    {
        return self::create(0, $ownerUserId, false);
    }

    public static function activeForInspection(int $inspectionId, int $ownerUserId): array
    {
        $row = R::getRow("SELECT * FROM inspection_companion_session WHERE inspection_id = ? AND owner_user_id = ? AND state IN ('pending', 'connected') AND expires_at > ? ORDER BY id DESC LIMIT 1", [$inspectionId, $ownerUserId, date(DATE_ATOM)]);
        return $row === [] ? [] : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function activeForUser(int $ownerUserId): array
    {
        return R::getAll("SELECT s.*, i.external_number AS inspection_number, d.external_number AS device_number, d.name AS device_name
            FROM inspection_companion_session s
            LEFT JOIN inspection i ON i.id = s.inspection_id
            LEFT JOIN device d ON d.id = i.device_id
            WHERE s.owner_user_id = ? AND s.state IN ('pending','connected') AND s.expires_at > ?
            ORDER BY s.id DESC", [$ownerUserId, date(DATE_ATOM)]);
    }

    public static function disconnectSession(int $sessionId, int $ownerUserId): void
    {
        R::exec("UPDATE inspection_companion_session SET state = 'disconnected', disconnected_at = ? WHERE id = ? AND owner_user_id = ? AND state IN ('pending','connected')", [date(DATE_ATOM), $sessionId, $ownerUserId]);
    }

    public static function byToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return [];
        $row = R::getRow("SELECT * FROM inspection_companion_session WHERE token_hash = ? AND state IN ('pending', 'connected') AND expires_at > ? LIMIT 1", [hash('sha256', $token), date(DATE_ATOM)]);
        return $row === [] ? [] : $row;
    }

    public static function connect(array $session, int $userId): void
    {
        if ((int) $session['owner_user_id'] !== $userId) throw new RuntimeException('Der Companion muss mit demselben Nutzerkonto geöffnet werden.');
        R::exec("UPDATE inspection_companion_session SET state = 'connected', companion_user_id = ?, connected_at = COALESCE(connected_at, ?), last_activity_at = ? WHERE id = ?", [$userId, date(DATE_ATOM), date(DATE_ATOM), (int) $session['id']]);
    }

    public static function touch(array $session, ?string $barcode = null): void
    {
        R::exec('UPDATE inspection_companion_session SET latest_barcode = ?, last_activity_at = ? WHERE id = ?', [$barcode ?? (string) $session['latest_barcode'], date(DATE_ATOM), (int) $session['id']]);
    }

    public static function disconnect(int $inspectionId, int $ownerUserId): void
    {
        R::exec("UPDATE inspection_companion_session SET state = 'disconnected', disconnected_at = ? WHERE inspection_id = ? AND owner_user_id = ? AND state IN ('pending', 'connected')", [date(DATE_ATOM), $inspectionId, $ownerUserId]);
    }
}
