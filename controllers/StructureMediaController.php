<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class StructureMediaController
{
    private const TYPES = ['customer', 'site', 'building', 'floor', 'area', 'room'];

    public static function upload(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        [$type, $entity] = self::entity($params);
        if ($entity === null) return [404, [], 'Struktureintrag nicht gefunden'];
        try {
            $mediaId = StructureMediaService::storeUpload($type, (int) $entity->id, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''), (int) current_user()->id);
            audit_log('strukturfoto_gespeichert', ['type' => $type, 'structure_id' => (int) $entity->id, 'media_id' => $mediaId]);
        } catch (Throwable $error) { $_SESSION['fehlermeldung'] = $error->getMessage(); }
        return $isHx ? [200, [], self::panel($type, (int) $entity->id)] : [303, ['Location' => url_for('struktur')], ''];
    }

    public static function file(array $params, bool $isHx): array
    {
        $media = R::getRow('SELECT * FROM structure_media WHERE id = ?', [(int) ($params['mediaId'] ?? 0)]);
        [$type, $entity] = $media === [] ? ['', null] : self::entity(['type' => $media['structure_type'], 'id' => $media['structure_id']]);
        if ($media === [] || $entity === null || !is_file((string) $media['path'])) return [404, [], 'Foto nicht gefunden'];
        header('Content-Type: ' . (string) $media['mime']); header('Content-Length: ' . filesize((string) $media['path'])); header('Cache-Control: private, max-age=3600'); readfile((string) $media['path']); exit;
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $media = R::getRow('SELECT * FROM structure_media WHERE id = ?', [(int) ($params['mediaId'] ?? 0)]);
        [$type, $entity] = $media === [] ? ['', null] : self::entity(['type' => $media['structure_type'], 'id' => $media['structure_id']]);
        if ($media === [] || $entity === null) return [404, [], 'Foto nicht gefunden'];
        if (is_file((string) $media['path'])) @unlink((string) $media['path']);
        R::exec('DELETE FROM structure_media WHERE id = ?', [(int) $media['id']]);
        StructureMediaService::forgetCache();
        audit_log('strukturfoto_geloescht', ['type' => $type, 'structure_id' => (int) $entity->id, 'media_id' => (int) $media['id']]);
        return [200, [], self::panel($type, (int) $entity->id)];
    }

    public static function panel(string $type, int $entityId): string
    {
        return render_template('structure_media_panel.php', ['type' => $type, 'entityId' => $entityId, 'media' => StructureMediaService::forEntity($type, $entityId), 'canManageMedia' => current_user_has_role('admin')]);
    }

    /** @return array{0:string,1:?object} */
    private static function entity(array $params): array
    {
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        if (!in_array($type, self::TYPES, true) || $id < 1) return ['', null];
        $entity = R::load($type, $id);
        if (!$entity->id) return [$type, null];
        $customerId = match ($type) {
            'customer' => $id,
            'site' => (int) $entity->customer_id,
            'building' => (int) R::getCell('SELECT s.customer_id FROM building b JOIN site s ON s.id=b.site_id WHERE b.id=?', [$id]),
            'floor' => (int) R::getCell('SELECT s.customer_id FROM floor f JOIN building b ON b.id=f.building_id JOIN site s ON s.id=b.site_id WHERE f.id=?', [$id]),
            'area' => (int) R::getCell('SELECT s.customer_id FROM area a JOIN floor f ON f.id=a.floor_id JOIN building b ON b.id=f.building_id JOIN site s ON s.id=b.site_id WHERE a.id=?', [$id]),
            'room' => (int) R::getCell('SELECT s.customer_id FROM room r JOIN floor f ON f.id=r.floor_id JOIN building b ON b.id=f.building_id JOIN site s ON s.id=b.site_id WHERE r.id=?', [$id]),
        };
        return current_user_can_access_customer($customerId) ? [$type, $entity] : [$type, null];
    }
}
