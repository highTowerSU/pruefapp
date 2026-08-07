<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class RoomMediaController
{
    public static function upload(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $room = self::room((int) ($params['id'] ?? 0));
        if (!$room) return [404, [], 'Raum nicht gefunden'];
        try {
            $mediaId = RoomMediaService::storeUpload((int) $room->id, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''), (int) current_user()->id);
            audit_log('raumfoto_gespeichert', ['room_id' => (int) $room->id, 'media_id' => $mediaId]);
        } catch (Throwable $error) { $_SESSION['fehlermeldung'] = $error->getMessage(); }
        if ($isHx) return [200, [], self::panel((int) $room->id)];
        return [303, ['Location' => url_for('struktur')], ''];
    }

    public static function file(array $params, bool $isHx): array
    {
        $media = R::getRow('SELECT * FROM room_media WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        $room = $media === [] ? null : self::room((int) $media['room_id']);
        if ($media === [] || !$room || !is_file((string) $media['path'])) return [404, [], 'Foto nicht gefunden'];
        header('Content-Type: ' . (string) $media['mime']); header('Content-Length: ' . filesize((string) $media['path'])); header('Cache-Control: private, max-age=3600'); readfile((string) $media['path']); exit;
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $media = R::getRow('SELECT * FROM room_media WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        $room = $media === [] ? null : self::room((int) $media['room_id']);
        if ($media === [] || !$room) return [404, [], 'Foto nicht gefunden'];
        if (is_file((string) $media['path'])) @unlink((string) $media['path']);
        R::exec('DELETE FROM room_media WHERE id = ?', [(int) $media['id']]);
        RoomMediaService::forgetCache();
        audit_log('raumfoto_geloescht', ['room_id' => (int) $room->id, 'media_id' => (int) $media['id']]);
        return [200, [], self::panel((int) $room->id)];
    }

    public static function panel(int $roomId): string
    {
        return render_template('room_media_panel.php', ['roomId' => $roomId, 'media' => RoomMediaService::forRoom($roomId), 'canManageMedia' => current_user_has_role('admin')]);
    }

    private static function room(int $roomId): ?object
    {
        $room = R::load('room', $roomId);
        if (!$room->id) return null;
        $customerId = (int) R::getCell('SELECT s.customer_id FROM room r JOIN floor f ON f.id = r.floor_id JOIN building b ON b.id = f.building_id JOIN site s ON s.id = b.site_id WHERE r.id = ?', [$roomId]);
        return $customerId > 0 && current_user_can_access_customer($customerId) ? $room : null;
    }
}
