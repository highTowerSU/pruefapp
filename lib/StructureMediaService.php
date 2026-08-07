<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Shared private photo storage for every level of the customer structure. */
final class StructureMediaService
{
    /** @var array<string,list<array<string,mixed>>>|null */
    private static ?array $byEntity = null;

    /** @return list<array<string,mixed>> */
    public static function forEntity(string $type, int $entityId): array
    {
        if (self::$byEntity === null) {
            self::$byEntity = [];
            foreach (R::getAll('SELECT * FROM structure_media ORDER BY created_at DESC, id DESC') as $media) {
                $key = (string) $media['structure_type'] . ':' . (int) $media['structure_id'];
                self::$byEntity[$key][] = $media;
            }
        }
        return self::$byEntity[$type . ':' . $entityId] ?? [];
    }

    public static function storeUpload(string $type, int $entityId, array $upload, string $mediaType, string $caption, int $userId): int
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Bitte wähle ein Foto aus.');
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || $dimensions === false || $size < 1 || $size > 12 * 1024 * 1024) throw new InvalidArgumentException('Erlaubt sind JPEG, PNG oder WebP bis 12 MB.');
        if (($dimensions[0] ?? 0) > 8000 || ($dimensions[1] ?? 0) > 8000) throw new InvalidArgumentException('Das Foto ist zu groß. Maximal 8000 × 8000 Pixel sind erlaubt.');
        $directory = app_data_root() . '/structure-media/' . $type . '/' . $entityId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Fotoverzeichnis konnte nicht angelegt werden.');
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Das Foto konnte nicht gespeichert werden.');
        @chmod($path, 0660);
        $mediaType = in_array($mediaType, ['condition', 'defect', 'disposal', 'other'], true) ? $mediaType : 'condition';
        R::exec('INSERT INTO structure_media (structure_type, structure_id, media_type, caption, path, original_name, mime, bytes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$type, $entityId, $mediaType, mb_substr(trim($caption), 0, 1000), $path, mb_substr((string) ($upload['name'] ?? 'strukturfoto.' . $extensions[$mime]), 0, 240), $mime, $size, $userId, date(DATE_ATOM)]);
        self::forgetCache();
        return (int) R::getInsertID();
    }

    public static function forgetCache(): void { self::$byEntity = null; }

    /** @param list<int> $entityIds */
    public static function deleteEntities(string $type, array $entityIds): void
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn(int $id): bool => $id > 0)));
        if ($entityIds === []) return;
        $marks = implode(',', array_fill(0, count($entityIds), '?'));
        $args = array_merge([$type], $entityIds);
        foreach (R::getAll("SELECT path FROM structure_media WHERE structure_type = ? AND structure_id IN ($marks)", $args) as $photo) {
            if (is_file((string) ($photo['path'] ?? ''))) @unlink((string) $photo['path']);
        }
        R::exec("DELETE FROM structure_media WHERE structure_type = ? AND structure_id IN ($marks)", $args);
        self::forgetCache();
    }
}
