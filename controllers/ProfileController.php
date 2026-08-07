<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class ProfileController
{
    public static function index(array $params, bool $isHx): array
    {
        $user = current_user();
        if ($user === null) return [303, ['Location' => url_for('login.php')], ''];
        $adminUserId = (int) ($params['userId'] ?? 0);
        $adminView = $adminUserId > 0;
        if ($adminView) {
            if (!current_user_has_role('admin')) return forbidden_response();
            $user = R::load('oauthuser', $adminUserId);
            if (!$user->id) return [404, [], 'Nutzer nicht gefunden'];
        }
        $canEdit = !$adminView || current_user_is_superadmin();
        $profileUrl = $adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil') : url_for('profil');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$canEdit) return forbidden_response();
            $action = trim((string) ($_POST['action'] ?? 'upload_signature'));
            if ($action === 'save_instruction') {
                $initialDate = self::validDate((string) ($_POST['instruction_initial_date'] ?? ''));
                if ($initialDate === false) {
                    $_SESSION['fehlermeldung'] = 'Das Datum der Erstunterweisung ist ungültig.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $followups = [];
                $dates = (array) ($_POST['followup_date'] ?? []);
                $topics = (array) ($_POST['followup_topic'] ?? []);
                foreach ($dates as $index => $rawDate) {
                    $date = self::validDate((string) $rawDate);
                    $topic = trim((string) ($topics[$index] ?? ''));
                    if ($date === '' && $topic === '') continue;
                    if ($date === false) {
                        $_SESSION['fehlermeldung'] = 'Mindestens ein Datum der Folgeunterweisung ist ungültig.';
                        return [303, ['Location' => $profileUrl], ''];
                    }
                    $followups[] = ['date' => $date, 'topic' => mb_substr($topic, 0, 240)];
                    if (count($followups) >= 50) break;
                }
                $user->instruction_initial_date = $initialDate;
                $user->instruction_followups_json = json_encode($followups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $user->instruction_notes = mb_substr(trim((string) ($_POST['instruction_notes'] ?? '')), 0, 1000);
                $user->instruction_updated_at = date(DATE_ATOM);
                R::store($user);
                audit_log('nutzerunterweisungen_aktualisiert', ['oauthuser_id' => (int) $user->id, 'folgeunterweisungen' => count($followups)]);
                $_SESSION['meldung'] = 'Erst- und Folgeunterweisungen wurden gespeichert.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'upload_certificate') {
                $upload = $_FILES['instruction_certificate'] ?? null;
                $date = self::validDate((string) ($_POST['certificate_date'] ?? ''));
                $kind = mb_substr(trim((string) ($_POST['certificate_kind'] ?? 'Folgeunterweisung')), 0, 80);
                $title = mb_substr(trim((string) ($_POST['certificate_title'] ?? '')), 0, 240);
                if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $date === false || $date === '') {
                    $_SESSION['fehlermeldung'] = 'Bitte ein PDF und ein gültiges Datum auswählen.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $tmp = (string) ($upload['tmp_name'] ?? '');
                $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
                $size = (int) ($upload['size'] ?? 0);
                if ($mime !== 'application/pdf' || $size < 1 || $size > 10 * 1024 * 1024) {
                    $_SESSION['fehlermeldung'] = 'Erlaubt sind PDF-Dateien bis 10 MB.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $directory = app_data_root() . '/user-instructions/' . (int) $user->id;
                if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                    $_SESSION['fehlermeldung'] = 'Das Nachweisverzeichnis konnte nicht angelegt werden.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $certificateId = bin2hex(random_bytes(8));
                $target = $directory . '/' . $certificateId . '.pdf';
                if (!move_uploaded_file($tmp, $target)) {
                    $_SESSION['fehlermeldung'] = 'Der Nachweis konnte nicht gespeichert werden.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                @chmod($target, 0660);
                $certificates = self::certificates($user);
                $certificates[] = ['id' => $certificateId, 'kind' => $kind !== '' ? $kind : 'Folgeunterweisung', 'date' => $date, 'title' => $title, 'path' => $target, 'name' => mb_substr((string) ($upload['name'] ?? 'Nachweis.pdf'), 0, 240), 'created_at' => date(DATE_ATOM)];
                $user->instruction_certificates_json = json_encode($certificates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $user->instruction_updated_at = date(DATE_ATOM);
                R::store($user);
                audit_log('unterweisungsnachweis_gespeichert', ['oauthuser_id' => (int) $user->id, 'art' => $kind]);
                $_SESSION['meldung'] = 'Unterweisungsnachweis gespeichert.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'delete_certificate') {
                $certificateId = trim((string) ($_POST['certificate_id'] ?? ''));
                $remaining = [];
                foreach (self::certificates($user) as $certificate) {
                    if ((string) ($certificate['id'] ?? '') === $certificateId) { if (is_file((string) ($certificate['path'] ?? ''))) @unlink((string) $certificate['path']); continue; }
                    $remaining[] = $certificate;
                }
                $user->instruction_certificates_json = json_encode($remaining, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                R::store($user);
                audit_log('unterweisungsnachweis_geloescht', ['oauthuser_id' => (int) $user->id]);
                $_SESSION['meldung'] = 'Unterweisungsnachweis entfernt.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'delete_signature') {
                $oldPath = trim((string) ($user->report_signature_path ?? ''));
                $user->report_signature_path = '';
                $user->report_signature_updated_at = date(DATE_ATOM);
                R::store($user);
                if ($oldPath !== '' && is_file($oldPath)) @unlink($oldPath);
                audit_log('profilsignatur_geloescht', ['oauthuser_id' => (int) $user->id]);
                $_SESSION['meldung'] = 'Die Unterschrift wurde entfernt.';
                return [303, ['Location' => $profileUrl], ''];
            }

            $upload = $_FILES['report_signature'] ?? null;
            $drawnSignature = trim((string) ($_POST['signature_drawing'] ?? ''));
            $signatureBytes = null;
            $tmp = '';
            $size = 0;
            $mime = '';
            $dimensions = false;
            if ($drawnSignature !== '') {
                if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $drawnSignature, $matches)) {
                    $_SESSION['fehlermeldung'] = 'Die gezeichnete Unterschrift ist ungültig.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $signatureBytes = base64_decode($matches[1], true);
                $size = is_string($signatureBytes) ? strlen($signatureBytes) : 0;
                $mime = is_string($signatureBytes) ? (new finfo(FILEINFO_MIME_TYPE))->buffer($signatureBytes) : '';
                $dimensions = is_string($signatureBytes) ? @getimagesizefromstring($signatureBytes) : false;
            } else {
                if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $_SESSION['fehlermeldung'] = 'Bitte zeichne eine Unterschrift oder wähle eine PNG- bzw. JPEG-Datei aus.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $_SESSION['fehlermeldung'] = 'Die Unterschrift konnte nicht hochgeladen werden.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $tmp = (string) ($upload['tmp_name'] ?? '');
                $size = (int) ($upload['size'] ?? 0);
                $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
                $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
            }
            if ($size < 1 || $size > 2 * 1024 * 1024 || !in_array($mime, ['image/png', 'image/jpeg'], true) || $dimensions === false) {
                $_SESSION['fehlermeldung'] = 'Erlaubt sind PNG oder JPEG bis 2 MB.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if (($dimensions[0] ?? 0) > 4000 || ($dimensions[1] ?? 0) > 2000) {
                $_SESSION['fehlermeldung'] = 'Das Bild ist zu groß. Maximal 4000 × 2000 Pixel sind erlaubt.';
                return [303, ['Location' => $profileUrl], ''];
            }

            $directory = app_data_root() . '/user-signatures';
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                $_SESSION['fehlermeldung'] = 'Das Signaturverzeichnis konnte nicht angelegt werden.';
                return [303, ['Location' => $profileUrl], ''];
            }
            $extension = $mime === 'image/png' ? 'png' : 'jpg';
            $target = $directory . '/user-' . (int) $user->id . '.' . $extension;
            $oldPath = trim((string) ($user->report_signature_path ?? ''));
            $stored = $signatureBytes !== null
                ? file_put_contents($target, $signatureBytes, LOCK_EX) !== false
                : move_uploaded_file($tmp, $target);
            if (!$stored) {
                $_SESSION['fehlermeldung'] = 'Die Unterschrift konnte nicht gespeichert werden.';
                return [303, ['Location' => $profileUrl], ''];
            }
            @chmod($target, 0660);
            if ($oldPath !== '' && $oldPath !== $target && is_file($oldPath)) @unlink($oldPath);
            $user->report_signature_path = $target;
            $user->report_signature_updated_at = date(DATE_ATOM);
            R::store($user);
            audit_log('profilsignatur_gespeichert', ['oauthuser_id' => (int) $user->id]);
            $queuedReports = self::queueMissingReportsForExaminer($user);
            $_SESSION['meldung'] = 'Die Unterschrift wurde gespeichert.' . ($queuedReports > 0 ? ' ' . $queuedReports . ' fertige Prüfung(en) werden nun im Hintergrund als Bericht erzeugt.' : ' Neue Prüfberichte werden nun freigegeben.');
            return [303, ['Location' => $profileUrl], ''];
        }

        $signature = examiner_signature_data_uri((string) ($user->email ?: $user->name));
        $followups = json_decode((string) ($user->instruction_followups_json ?? ''), true);
        $followups = is_array($followups) ? array_values(array_filter($followups, static fn($entry): bool => is_array($entry))) : [];
        $certificates = self::certificates($user);
        $content = render_template('profile.php', ['user' => $user, 'signature' => $signature, 'followups' => $followups, 'certificates' => $certificates, 'canEdit' => $canEdit, 'profileUrl' => $profileUrl, 'adminView' => $adminView]);
        return [200, [], render_template('layout.php', ['title' => $adminView ? 'Benutzerprofil' : 'Mein Profil', 'content' => $content])];
    }

    public static function certificate(array $params, bool $isHx): array
    {
        $viewer = current_user(); if (!$viewer) return [403, [], ''];
        $targetId = (int) ($params['userId'] ?? 0);
        if ($targetId > 0) { if (!current_user_has_role('admin')) return forbidden_response(); $viewer = R::load('oauthuser', $targetId); }
        if (!$viewer->id) return [404, [], 'Nachweis nicht gefunden'];
        $id = trim((string) ($params['certificateId'] ?? ''));
        foreach (self::certificates($viewer) as $certificate) {
            if ((string) ($certificate['id'] ?? '') !== $id || !is_file((string) ($certificate['path'] ?? ''))) continue;
            $filename = str_replace('"', '', basename((string) ($certificate['name'] ?? 'unterweisungsnachweis.pdf')));
            header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $filename . '"'); header('Content-Length: ' . filesize((string) $certificate['path'])); readfile((string) $certificate['path']); exit;
        }
        return [404, [], 'Nachweis nicht gefunden'];
    }

    private static function certificates($user): array
    {
        $items = json_decode((string) ($user->instruction_certificates_json ?? ''), true);
        return is_array($items) ? array_values(array_filter($items, static fn($item): bool => is_array($item) && trim((string) ($item['id'] ?? '')) !== '')) : [];
    }

    private static function queueMissingReportsForExaminer($user): int
    {
        $identifiers = array_values(array_unique(array_filter(array_map(
            static fn($value): string => mb_strtolower(trim((string) $value)),
            [(string) ($user->email ?? ''), (string) ($user->name ?? ''), (string) ($user->preferred_username ?? '')]
        ), static fn(string $value): bool => $value !== '')));
        if ($identifiers === []) return 0;
        $marks = implode(',', array_fill(0, count($identifiers), '?'));
        $ids = array_map('intval', R::getCol(
            "SELECT id FROM inspection WHERE LOWER(TRIM(COALESCE(examiner, ''))) IN ($marks) AND result_status IN ('passed','failed') AND COALESCE(classification, '') <> 'legacy' AND TRIM(COALESCE(report_path, '')) = '' ORDER BY id",
            $identifiers
        ));
        if ($ids === []) return 0;
        BackgroundJobService::enqueue('pdf_regenerate', [
            'type' => 'pdf_regenerate',
            'inspection_ids' => $ids,
            'owner_user_id' => (int) $user->id,
        ], [
            'total' => count($ids),
            'dedupe_key' => 'signature-reports:' . (int) $user->id . ':' . hash('sha256', implode(',', $ids) . '|' . (string) ($user->report_signature_updated_at ?? '')),
        ]);
        return count($ids);
    }

    private static function validDate(string $value): string|false
    {
        $value = trim($value);
        if ($value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] ?? 0) > 0) || ($errors !== false && ($errors['error_count'] ?? 0) > 0)) return false;
        return $date->format('Y-m-d');
    }
}
