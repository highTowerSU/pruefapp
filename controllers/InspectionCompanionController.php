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
        InspectionCompanionInboxService::addBarcode($session, $barcode);
        audit_log('pruef_companion_barcode', ['inspection_id' => (int) $session['inspection_id'], 'barcode' => mb_substr($barcode, 0, 255)]);
        return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Barcode an den Prüfplatz übertragen: <strong>' . htmlspecialchars($barcode) . '</strong></div>'];
    }

    /** Fallback for browsers without BarcodeDetector: decode an uploaded photo locally with zbarimg. */
    public static function barcodePhoto(array $params, bool $isHx): array
    {
        $session = self::usableSession((string) ($params['token'] ?? ''));
        if ($session === []) return [410, [], 'Verbindung abgelaufen.'];
        $upload = (array) ($_FILES['barcode_photo'] ?? []);
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string) ($upload['tmp_name'] ?? ''))) return [422, [], 'Bitte fotografiere oder wähle einen Barcode aus.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return [422, [], 'Bitte verwende ein JPEG-, PNG- oder WebP-Foto.'];
        $command = trim((string) @shell_exec('command -v zbarimg 2>/dev/null'));
        if ($command === '') return [422, [], 'Barcode-Fallback ist auf diesem Server noch nicht installiert. Bitte nutze die Kameraerkennung oder gib den Code ein.'];
        $process = proc_open([$command, '--raw', '--quiet', (string) $upload['tmp_name']], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return [422, [], 'Der Barcode konnte gerade nicht ausgewertet werden.'];
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($process);
        $barcode = trim((string) strtok((string) $output, "\n"));
        if ($barcode === '') return [422, [], 'Auf dem Foto wurde kein Barcode erkannt. Bitte näher und scharf fotografieren oder den Code eingeben.'];
        InspectionCompanionInboxService::addBarcode($session, $barcode);
        audit_log('pruef_companion_barcode_foto', ['inspection_id' => (int) $session['inspection_id'], 'barcode' => mb_substr($barcode, 0, 255)]);
        return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Barcode erkannt und an den Prüfplatz übertragen: <strong>' . htmlspecialchars($barcode) . '</strong></div>'];
    }

    public static function photo(array $params, bool $isHx): array
    {
        $session = self::usableSession((string) ($params['token'] ?? ''));
        if ($session === []) {
            $message = '<div class="alert alert-warning py-2 mb-0"><i class="fa-solid fa-link-slash me-1" aria-hidden="true"></i>Die Companion-Verbindung ist nicht mehr aktiv. Bitte am Prüfplatz neu verbinden.</div>';
            return $isHx ? [200, [], $message] : [410, [], 'Verbindung abgelaufen.'];
        }
        try {
            $id = InspectionCompanionInboxService::addPhoto($session, (array) ($_FILES['photo'] ?? []), (string) ($_POST['media_type'] ?? 'condition'), (string) ($_POST['caption'] ?? ''));
            audit_log('pruef_companion_foto', ['inspection_id' => (int) $session['inspection_id'], 'companion_item_id' => $id]);
            return [200, [], '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Foto an den Prüfplatz übertragen. Dort wird es bewusst erst nach deiner Auswahl gespeichert.</div>'];
        } catch (Throwable $e) {
            $message = '<div class="alert alert-danger py-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>' . htmlspecialchars($e->getMessage()) . '</div>';
            return $isHx ? [200, [], $message] : [422, [], $message];
        }
    }

    /** Compact shared desktop inbox; its content is refreshed by an SSE signal. */
    public static function inbox(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $ownerUserId = (int) current_user()->id;
        $items = InspectionCompanionInboxService::itemsForOwner($ownerUserId);
        if (trim((string) ($_GET['field'] ?? '')) !== '') {
            return [200, [], render_template('inspection_companion_choices.php', ['items' => $items, 'field' => (string) $_GET['field']])];
        }
        return [200, [], self::renderInbox($ownerUserId)];
    }

    public static function useItem(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $itemId = (int) ($params['id'] ?? 0);
        InspectionCompanionInboxService::markUsed($itemId, (int) current_user()->id, trim((string) ($_POST['target'] ?? 'übernommen')));
        return [200, [], self::renderInbox((int) current_user()->id)];
    }

    public static function adoptPhoto(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $itemId = (int) ($params['id'] ?? 0);
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        $inspectionId = (int) ($_POST['inspection_id'] ?? 0) ?: null;
        $device = R::load('device', $deviceId);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Gerät nicht gefunden.'];
        try {
            $mediaId = InspectionCompanionInboxService::adoptPhoto($itemId, (int) current_user()->id, $deviceId, $inspectionId, (int) current_user()->id);
            audit_log('pruef_companion_foto_uebernommen', ['companion_item_id' => $itemId, 'device_id' => $deviceId, 'inspection_id' => $inspectionId, 'media_id' => $mediaId]);
        } catch (Throwable $e) { return [422, [], '<div class="alert alert-danger py-2 mb-0">' . htmlspecialchars($e->getMessage()) . '</div>']; }
        return [200, [], self::renderInbox((int) current_user()->id)];
    }

    /** SSE carries only a changed item id. HTMX fetches the protected HTML fragment. */
    public static function events(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $ownerUserId = (int) current_user()->id;
        $last = max(0, (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? ($_GET['after'] ?? 0)));
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        echo "retry: 1500\n\n";
        @flush();
        $until = microtime(true) + 25;
        while (!connection_aborted() && microtime(true) < $until) {
            $current = InspectionCompanionInboxService::latestIdForOwner($ownerUserId);
            if ($current > $last) {
                echo "id: {$current}\nevent: companion-update\ndata: {\"id\":{$current}}\n\n";
                @flush();
                $last = $current;
            } else {
                echo ": keepalive\n\n";
                @flush();
            }
            usleep(1000000);
        }
        exit;
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

    private static function renderInbox(int $ownerUserId): string
    {
        $items = InspectionCompanionInboxService::itemsForOwner($ownerUserId);
        return render_template('inspection_companion_inbox.php', compact('items'));
    }
}
