<?php

declare(strict_types=1);

use RedBeanPHP\R;

/**
 * Shared, server-side inbox between a paired phone and its desktop workspace.
 * Values stay pending until a user deliberately applies them to a field.
 */
final class InspectionCompanionInboxService
{
    /** @return list<array<string,mixed>> */
    public static function itemsForOwner(int $ownerUserId, int $limit = 30): array
    {
        return R::getAll("SELECT ci.*, s.inspection_id
            FROM inspection_companion_item ci
            JOIN inspection_companion_session s ON s.id = ci.session_id
            WHERE s.owner_user_id = ?
            ORDER BY CASE ci.status WHEN 'pending' THEN 0 ELSE 1 END, ci.id DESC
            LIMIT ?", [$ownerUserId, max(1, min(100, $limit))]);
    }

    public static function latestIdForOwner(int $ownerUserId): int
    {
        return (int) R::getCell('SELECT COALESCE(MAX(ci.id), 0) FROM inspection_companion_item ci JOIN inspection_companion_session s ON s.id = ci.session_id WHERE s.owner_user_id = ?', [$ownerUserId]);
    }

    public static function addBarcode(array $session, string $barcode): int
    {
        $barcode = mb_substr(trim($barcode), 0, 255);
        if ($barcode === '') throw new InvalidArgumentException('Kein Barcode erkannt.');
        return self::add($session, 'barcode', $barcode);
    }

    public static function addPhoto(array $session, array $upload, string $type, string $caption): int
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Bitte wähle ein Foto aus.');
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || $dimensions === false || $size < 1 || $size > 12 * 1024 * 1024) throw new InvalidArgumentException('Erlaubt sind JPEG, PNG oder WebP bis 12 MB.');
        if (($dimensions[0] ?? 0) > 8000 || ($dimensions[1] ?? 0) > 8000) throw new InvalidArgumentException('Das Foto ist zu groß. Maximal 8000 × 8000 Pixel sind erlaubt.');

        $directory = app_data_root() . '/companion-media/' . (int) $session['id'];
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Das Foto konnte nicht gespeichert werden.');
        @chmod($path, 0660);

        $type = in_array($type, ['type_plate', 'condition', 'defect', 'disposal', 'other'], true) ? $type : 'condition';
        return self::add($session, 'photo', '', $type, $caption, $path, (string) ($upload['name'] ?? 'foto.' . $extensions[$mime]), $mime, $size);
    }

    public static function markUsed(int $itemId, int $ownerUserId, string $target): bool
    {
        return R::exec("UPDATE inspection_companion_item SET status = 'used', used_target = ?, used_at = ? WHERE id = ? AND status = 'pending' AND session_id IN (SELECT id FROM inspection_companion_session WHERE owner_user_id = ?)", [mb_substr($target, 0, 120), date(DATE_ATOM), $itemId, $ownerUserId]) > 0;
    }

    /** Move a staged phone photo into ordinary device media only when explicitly attached. */
    public static function adoptPhoto(int $itemId, int $ownerUserId, int $deviceId, ?int $inspectionId, int $actorId): int
    {
        $item = R::getRow("SELECT ci.* FROM inspection_companion_item ci JOIN inspection_companion_session s ON s.id = ci.session_id WHERE ci.id = ? AND ci.kind = 'photo' AND s.owner_user_id = ?", [$itemId, $ownerUserId]);
        if ($item === [] || !is_file((string) $item['path'])) throw new RuntimeException('Dieses Companion-Foto ist nicht mehr verfügbar.');
        $directory = app_data_root() . '/device-media/' . $deviceId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $extension = pathinfo((string) $item['path'], PATHINFO_EXTENSION) ?: 'jpg';
        $targetPath = $directory . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!@rename((string) $item['path'], $targetPath)) throw new RuntimeException('Das Companion-Foto konnte nicht übernommen werden.');
        @chmod($targetPath, 0660);
        R::exec('INSERT INTO device_media (device_id, inspection_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$deviceId, $inspectionId, $item['media_type'], $item['caption'], $targetPath, $item['original_name'], $item['mime'], (int) $item['bytes'], $actorId, date(DATE_ATOM)]);
        $mediaId = (int) R::getInsertID();
        self::markUsed($itemId, $ownerUserId, 'Foto übernommen');
        return $mediaId;
    }

    public static function cleanupExpired(): int
    {
        $rows = R::getAll("SELECT ci.path FROM inspection_companion_item ci JOIN inspection_companion_session s ON s.id = ci.session_id WHERE s.expires_at < ? AND ci.status = 'pending' AND ci.kind = 'photo'", [date(DATE_ATOM, time() - 86400)]);
        foreach ($rows as $row) if (is_file((string) $row['path'])) @unlink((string) $row['path']);
        return R::exec("DELETE FROM inspection_companion_item WHERE session_id IN (SELECT id FROM inspection_companion_session WHERE expires_at < ?)", [date(DATE_ATOM, time() - 86400)]);
    }

    private static function add(array $session, string $kind, string $value, string $mediaType = '', string $caption = '', string $path = '', string $name = '', string $mime = '', int $bytes = 0): int
    {
        R::exec("INSERT INTO inspection_companion_item (session_id, kind, value, media_type, caption, path, original_name, mime, bytes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)", [(int) $session['id'], $kind, $value, $mediaType, mb_substr(trim($caption), 0, 1000), $path, mb_substr($name, 0, 240), $mime, $bytes, date(DATE_ATOM)]);
        InspectionCompanionService::touch($session, $kind === 'barcode' ? $value : null);
        return (int) R::getInsertID();
    }
}
