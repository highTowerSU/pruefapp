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
        $session = InspectionCompanionService::byToken((string) ($params['token'] ?? ''));
        if ($session === []) return [410, [], 'Diese Companion-Verbindung ist abgelaufen oder wurde getrennt.'];
        $session['token'] = (string) ($params['token'] ?? '');
        $viewer = current_user();
        $viewerId = $viewer ? (int) $viewer->id : (int) $session['owner_user_id'];
        try { InspectionCompanionService::connect($session, $viewerId); } catch (Throwable $e) { return [403, [], $e->getMessage()]; }
        if ((int) $session['inspection_id'] === 0) {
            return [200, [], render_template('layout.php', ['title' => 'Prüf-Companion', 'content' => render_template('inspection_companion_workspace.php', compact('session'))])];
        }
        $inspection = self::inspectionForCompanion((int) $session['inspection_id']);
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        $device = R::load('device', (int) $inspection->device_id);
        return [200, [], render_template('layout.php', ['title' => 'Prüf-Companion', 'content' => render_template('inspection_companion_mobile.php', compact('session', 'inspection', 'device'))])];
    }

    /** The pairing image is deliberately rendered server-side, not in a canvas. */
    public static function qr(array $params, bool $isHx): array
    {
        $token = (string) ($params['token'] ?? '');
        if (InspectionCompanionService::byToken($token) === []) {
            return [410, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'], 'Diese Companion-Verbindung ist abgelaufen oder wurde getrennt.'];
        }

        try {
            return [
                200,
                ['Content-Type' => 'image/svg+xml; charset=utf-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'],
                ServerQrCodeService::svg(absolute_url_for('companion/' . $token)),
            ];
        } catch (Throwable $e) {
            error_log('[pruefapp] Companion QR: ' . $e->getMessage());
            return [503, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'], 'QR-Code konnte gerade nicht erzeugt werden.'];
        }
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
        $inspection = self::inspectionForCompanion((int) $session['inspection_id']);
        if (!$inspection) return [404, [], 'Prüfung nicht gefunden'];
        try {
            $viewer = current_user();
            $actorId = $viewer ? (int) $viewer->id : (int) $session['owner_user_id'];
            $id = DeviceMediaService::storeUpload((int) $inspection->device_id, (int) $inspection->id, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''), $actorId);
            InspectionCompanionService::touch($session);
            audit_log('pruef_companion_foto', ['inspection_id' => (int) $inspection->id, 'media_id' => $id]);
            return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Foto gespeichert und am Prüfplatz sichtbar.</div>'];
        } catch (Throwable $e) { return [422, [], '<div class="alert alert-danger py-2 mb-0">' . htmlspecialchars($e->getMessage()) . '</div>']; }
    }

    private static function usableSession(string $token): array
    {
        $session = InspectionCompanionService::byToken($token);
        if ($session === []) return [];
        if (current_user() && (int) $session['owner_user_id'] !== (int) current_user()->id) return [];
        if ((int) $session['inspection_id'] !== 0 && self::inspectionForCompanion((int) $session['inspection_id']) === null) return [];
        $viewer = current_user();
        InspectionCompanionService::connect($session, $viewer ? (int) $viewer->id : (int) $session['owner_user_id']);
        return $session;
    }

    private static function inspection(int $id): ?object
    {
        $inspection = R::load('inspection', $id);
        if (!$inspection->id) return null;
        $device = R::load('device', (int) $inspection->device_id);
        return $device->id && current_user_can_access_customer(device_customer_id($device)) ? $inspection : null;
    }

    /** A valid random pairing token is the authorization for mobile requests. */
    private static function inspectionForCompanion(int $id): ?object
    {
        $inspection = R::load('inspection', $id);
        if (!$inspection->id) return null;
        return R::load('device', (int) $inspection->device_id)->id ? $inspection : null;
    }

    private static function renderPanel(object $inspection): string
    {
        $session = InspectionCompanionService::activeForInspection((int) $inspection->id, (int) current_user()->id);
        $token = (string) ($_SESSION['inspection_companion_tokens'][(int) $inspection->id] ?? '');
        if ($session !== [] && $token !== '') $session['token'] = $token;
        return render_template('inspection_companion_panel.php', compact('inspection', 'session'));
    }
}
