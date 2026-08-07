<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Short-lived, user-bound photo staging for a device that does not exist yet. */
final class DeviceDraftMediaService
{
    /** @return array{token:string,proposal:array<string,mixed>|null} */
    public static function stage(array $upload, string $type, string $caption, int $userId): array
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Bitte wähle ein Foto aus.');
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || $dimensions === false || $size < 1 || $size > 12 * 1024 * 1024) throw new InvalidArgumentException('Erlaubt sind JPEG, PNG oder WebP bis 12 MB.');
        if (($dimensions[0] ?? 0) > 8000 || ($dimensions[1] ?? 0) > 8000) throw new InvalidArgumentException('Das Foto ist zu groß. Maximal 8000 × 8000 Pixel sind erlaubt.');
        $type = in_array($type, ['type_plate', 'condition', 'defect', 'disposal', 'other'], true) ? $type : 'condition';
        $token = bin2hex(random_bytes(24));
        $directory = app_data_root() . '/device-drafts/' . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $path = $directory . '/' . $token . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Das Foto konnte nicht hochgeladen werden.');
        @chmod($path, 0660);
        $proposal = $type === 'type_plate' ? self::analyseStaged($path, $mime) : null;
        R::exec('INSERT INTO device_draft_media (token_hash, owner_user_id, media_type, caption, path, original_name, mime, bytes, proposal_json, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [hash('sha256', $token), $userId, $type, mb_substr(trim($caption), 0, 1000), $path, mb_substr((string) ($upload['name'] ?? 'foto.' . $extensions[$mime]), 0, 240), $mime, $size, $proposal ? json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '', date(DATE_ATOM), date(DATE_ATOM, time() + 86400)]);
        return ['token' => $token, 'proposal' => $proposal];
    }

    public static function consume(string $token, int $userId, int $deviceId): ?int
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        $draft = R::getRow('SELECT * FROM device_draft_media WHERE token_hash = ? AND owner_user_id = ? AND expires_at > ?', [hash('sha256', $token), $userId, date(DATE_ATOM)]);
        if ($draft === [] || !is_file((string) $draft['path'])) return null;
        $directory = app_data_root() . '/device-media/' . $deviceId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $target = $directory . '/' . bin2hex(random_bytes(12)) . '.' . (pathinfo((string) $draft['path'], PATHINFO_EXTENSION) ?: 'jpg');
        if (!@rename((string) $draft['path'], $target)) throw new RuntimeException('Das vorab hochgeladene Foto konnte nicht übernommen werden.');
        @chmod($target, 0660);
        R::exec('INSERT INTO device_media (device_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$deviceId, $draft['media_type'], $draft['caption'], $target, $draft['original_name'], $draft['mime'], (int) $draft['bytes'], $userId, date(DATE_ATOM)]);
        $mediaId = (int) R::getInsertID();
        R::exec('DELETE FROM device_draft_media WHERE token_hash = ?', [hash('sha256', $token)]);
        if ((string) $draft['media_type'] === 'type_plate') {
            $proposal = json_decode((string) ($draft['proposal_json'] ?? ''), true);
            if (is_array($proposal)) {
                R::exec('INSERT INTO device_media_analysis (media_id, status, proposal_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?)', [$mediaId, 'done', json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date(DATE_ATOM), date(DATE_ATOM)]);
            } else {
                try { DeviceMediaService::analyseTypePlate($mediaId); } catch (Throwable $error) { DeviceMediaService::recordAnalysisError($mediaId, $error->getMessage()); }
            }
        }
        return $mediaId;
    }

    /** Reuse the established type-plate pipeline without retaining a fake device media record. */
    private static function analyseStaged(string $path, string $mime): ?array
    {
        R::exec("INSERT INTO device_media (device_id, media_type, path, mime, created_at) VALUES (0, 'type_plate', ?, ?, ?)", [$path, $mime, date(DATE_ATOM)]);
        $mediaId = (int) R::getInsertID();
        try { return DeviceMediaService::analyseTypePlate($mediaId); }
        finally { R::exec('DELETE FROM device_media_analysis WHERE media_id = ?', [$mediaId]); R::exec('DELETE FROM device_media WHERE id = ?', [$mediaId]); }
    }
}
