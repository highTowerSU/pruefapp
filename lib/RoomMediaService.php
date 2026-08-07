<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Photo storage for rooms; device and inspection media remain separate. */
final class RoomMediaService
{
    /** @var array<int,list<array<string,mixed>>>|null */
    private static ?array $byRoom = null;

    /** @return list<array<string,mixed>> */
    public static function forRoom(int $roomId): array
    {
        // The structure view renders many collapsed rooms.  Keep that view at
        // one query instead of one query per room; every HTTP request starts
        // with a fresh cache, so uploads/deletes are immediately reflected.
        if (self::$byRoom === null) {
            self::$byRoom = [];
            foreach (R::getAll('SELECT * FROM room_media ORDER BY room_id, id DESC') as $photo) {
                self::$byRoom[(int) $photo['room_id']][] = $photo;
            }
        }
        return self::$byRoom[$roomId] ?? [];
    }

    public static function storeUpload(int $roomId, array $upload, string $type, string $caption, int $userId): int
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Bitte wähle ein Foto aus.');
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || $dimensions === false || $size < 1 || $size > 12 * 1024 * 1024) throw new InvalidArgumentException('Erlaubt sind JPEG, PNG oder WebP bis 12 MB.');
        if (($dimensions[0] ?? 0) > 8000 || ($dimensions[1] ?? 0) > 8000) throw new InvalidArgumentException('Das Foto ist zu groß. Maximal 8000 × 8000 Pixel sind erlaubt.');
        $directory = app_data_root() . '/room-media/' . $roomId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Das Foto konnte nicht gespeichert werden.');
        @chmod($path, 0660);
        $type = in_array($type, ['condition', 'defect', 'disposal', 'other'], true) ? $type : 'condition';
        R::exec('INSERT INTO room_media (room_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$roomId, $type, mb_substr(trim($caption), 0, 1000), $path, mb_substr((string) ($upload['name'] ?? 'raumfoto.' . $extensions[$mime]), 0, 240), $mime, $size, $userId, date(DATE_ATOM)]);
        self::$byRoom = null;
        return (int) R::getInsertID();
    }

    public static function forgetCache(): void
    {
        self::$byRoom = null;
    }

    /** Remove private files before the corresponding rooms are deleted. */
    public static function deleteForRooms(array $roomIds): void
    {
        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds), static fn(int $id): bool => $id > 0)));
        if ($roomIds === []) return;
        $marks = implode(',', array_fill(0, count($roomIds), '?'));
        foreach (R::getAll("SELECT path FROM room_media WHERE room_id IN ($marks)", $roomIds) as $photo) {
            $path = (string) ($photo['path'] ?? '');
            if ($path !== '' && is_file($path)) @unlink($path);
        }
        R::exec("DELETE FROM room_media WHERE room_id IN ($marks)", $roomIds);
        self::$byRoom = null;
    }
}
