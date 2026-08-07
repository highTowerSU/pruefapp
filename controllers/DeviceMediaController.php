<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class DeviceMediaController
{
    /** Upload and (for type plates) analyse a photo before a new device is persisted. */
    public static function stageNewDevice(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        try {
            $staged = DeviceDraftMediaService::stage((array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'type_plate'), (string) ($_POST['caption'] ?? ''), (int) current_user()->id);
            audit_log('geraetefoto_vorab_hochgeladen', ['type' => (string) ($_POST['media_type'] ?? 'type_plate')]);
            return [200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(['ok' => true, 'token' => $staged['token'], 'proposal' => $staged['proposal'], 'analysis_error' => $staged['analysis_error']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        } catch (Throwable $exception) {
            return [422, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE)];
        }
    }

    public static function uploadDevice(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $deviceId = (int) ($params['id'] ?? 0);
        return self::upload($deviceId, null, $isHx);
    }

    public static function uploadInspection(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        return self::upload((int) $inspection->device_id, (int) $inspection->id, $isHx);
    }

    private static function upload(int $deviceId, ?int $inspectionId, bool $isHx): array
    {
        $device = R::load('device', $deviceId);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Gerät nicht gefunden'];
        try {
            $id = DeviceMediaService::storeUpload($deviceId, $inspectionId, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''), (int) current_user()->id);
            audit_log('geraetefoto_gespeichert', ['device_id' => $deviceId, 'inspection_id' => $inspectionId, 'media_id' => $id]);
            if ((string) ($_POST['media_type'] ?? '') === 'type_plate' && !empty($_POST['analyse_typeplate'])) {
                try { DeviceMediaService::analyseTypePlate($id); } catch (Throwable $analysisError) { DeviceMediaService::recordAnalysisError($id, $analysisError->getMessage()); }
            }
            $_SESSION['meldung'] = 'Foto gespeichert.';
        } catch (Throwable $exception) {
            $_SESSION['fehlermeldung'] = $exception->getMessage();
        }
        $url = $inspectionId ? url_for('admin/pruefungen/' . $inspectionId . '/bearbeiten') : url_for('geraete?device_id=' . $deviceId . '#geraet-' . $deviceId);
        if ($isHx) return [200, [], self::panel($deviceId)];
        return [303, ['Location' => $url], ''];
    }

    public static function file(array $params, bool $isHx): array
    {
        $media = R::getRow('SELECT * FROM device_media WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        $device = $media === [] ? null : R::load('device', (int) $media['device_id']);
        if ($media === [] || !$device || !$device->id || !current_user_can_access_customer(device_customer_id($device)) || !is_file((string) $media['path'])) return [404, [], 'Foto nicht gefunden'];
        header('Content-Type: ' . (string) $media['mime']);
        header('Content-Length: ' . filesize((string) $media['path']));
        header('Cache-Control: private, max-age=3600');
        readfile((string) $media['path']);
        exit;
    }

    public static function analyseTypePlate(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $media = R::getRow('SELECT * FROM device_media WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        $device = $media === [] ? null : R::load('device', (int) $media['device_id']);
        if ($media === [] || !$device || !$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Foto nicht gefunden'];
        try {
            DeviceMediaService::analyseTypePlate((int) $media['id']);
            audit_log('typenschild_ki_ausgewertet', ['device_id' => (int) $device->id, 'media_id' => (int) $media['id']]);
        } catch (Throwable $exception) {
            DeviceMediaService::recordAnalysisError((int) $media['id'], $exception->getMessage());
        }
        return [200, [], self::panel((int) $device->id)];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $media = R::getRow('SELECT * FROM device_media WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        $device = $media === [] ? null : R::load('device', (int) $media['device_id']);
        if ($media === [] || !$device || !$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Foto nicht gefunden'];
        if (is_file((string) $media['path'])) @unlink((string) $media['path']);
        R::exec('DELETE FROM device_media_analysis WHERE media_id = ?', [(int) $media['id']]);
        R::exec('DELETE FROM device_media WHERE id = ?', [(int) $media['id']]);
        audit_log('geraetefoto_geloescht', ['device_id' => (int) $device->id, 'media_id' => (int) $media['id']]);
        return [200, [], self::panel((int) $device->id)];
    }

    public static function panel(int $deviceId): string
    {
        return render_template('device_media_panel.php', ['deviceId' => $deviceId, 'media' => DeviceMediaService::forDevice($deviceId), 'canManageMedia' => current_user_has_role('admin')]);
    }
}
