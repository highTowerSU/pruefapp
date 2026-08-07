<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class InspectionCompanionController
{
    public static function panel(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = self::inspection((int) ($params['id'] ?? 0));
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        $userId = (int) current_user()->id;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'disconnect') {
                InspectionCompanionService::disconnect((int) $inspection->id, $userId);
                unset($_SESSION['inspection_companion_tokens'][(int) $inspection->id]);
            } else {
                $created = InspectionCompanionService::create((int) $inspection->id, $userId);
                $_SESSION['inspection_companion_tokens'][(int) $inspection->id] = $created['token'];
            }
        }
        return [200, [], self::renderPanel($inspection)];
    }

    public static function status(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = self::inspection((int) ($params['id'] ?? 0));
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        return [200, [], self::renderPanel($inspection)];
    }

    public static function open(array $params, bool $isHx): array
    {
        if (!current_user()) return [303, ['Location' => url_for('login.php')], ''];
        $session = InspectionCompanionService::byToken((string) ($params['token'] ?? ''));
        if ($session === []) return [410, [], 'Diese Companion-Verbindung ist abgelaufen oder wurde getrennt.'];
        try { InspectionCompanionService::connect($session, (int) current_user()->id); } catch (Throwable $e) { return [403, [], $e->getMessage()]; }
        $inspection = self::inspection((int) $session['inspection_id']);
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        $device = R::load('device', (int) $inspection->device_id);
        return [200, [], render_template('layout.php', ['title' => 'Prüf-Companion', 'content' => render_template('inspection_companion_mobile.php', compact('session', 'inspection', 'device'))])];
    }

    public static function barcode(array $params, bool $isHx): array
    {
        $session = self::usableSession((string) ($params['token'] ?? ''));
        if ($session === []) return [410, [], 'Verbindung abgelaufen.'];
        $barcode = trim((string) ($_POST['barcode'] ?? ''));
        if ($barcode === '') return [422, [], 'Kein Barcode erkannt.'];
        InspectionCompanionService::touch($session, mb_substr($barcode, 0, 255));
        audit_log('pruef_companion_barcode', ['inspection_id' => (int) $session['inspection_id'], 'barcode' => mb_substr($barcode, 0, 255)]);
        return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Barcode an den Prüfplatz übertragen: <strong>' . htmlspecialchars($barcode) . '</strong></div>'];
    }

    public static function photo(array $params, bool $isHx): array
    {
        $session = self::usableSession((string) ($params['token'] ?? ''));
        if ($session === []) return [410, [], 'Verbindung abgelaufen.'];
        $inspection = self::inspection((int) $session['inspection_id']);
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        try {
            $id = DeviceMediaService::storeUpload((int) $inspection->device_id, (int) $inspection->id, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''), (int) current_user()->id);
            InspectionCompanionService::touch($session);
            audit_log('pruef_companion_foto', ['inspection_id' => (int) $inspection->id, 'media_id' => $id]);
            return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Foto gespeichert und am Prüfplatz sichtbar.</div>'];
        } catch (Throwable $e) { return [422, [], '<div class="alert alert-danger py-2 mb-0">' . htmlspecialchars($e->getMessage()) . '</div>']; }
    }

    private static function usableSession(string $token): array
    {
        if (!current_user()) return [];
        $session = InspectionCompanionService::byToken($token);
        if ($session === [] || (int) $session['owner_user_id'] !== (int) current_user()->id) return [];
        if (self::inspection((int) $session['inspection_id']) === null) return [];
        InspectionCompanionService::connect($session, (int) current_user()->id);
        return $session;
    }

    private static function inspection(int $id): ?object
    {
        $inspection = R::load('inspection', $id);
        if (!$inspection->id) return null;
        $device = R::load('device', (int) $inspection->device_id);
        return $device->id && current_user_can_access_customer(device_customer_id($device)) ? $inspection : null;
    }

    private static function renderPanel(object $inspection): string
    {
        $session = InspectionCompanionService::activeForInspection((int) $inspection->id, (int) current_user()->id);
        $token = (string) ($_SESSION['inspection_companion_tokens'][(int) $inspection->id] ?? '');
        if ($session !== [] && $token !== '') $session['token'] = $token;
        return render_template('inspection_companion_panel.php', compact('inspection', 'session'));
    }
}
