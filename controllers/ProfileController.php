<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class ProfileController
{
    public static function index(array $params, bool $isHx): array
    {
        $user = current_user();
        if ($user === null) return [303, ['Location' => url_for('login.php')], ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = trim((string) ($_POST['action'] ?? 'upload_signature'));
            if ($action === 'delete_signature') {
                $oldPath = trim((string) ($user->report_signature_path ?? ''));
                $user->report_signature_path = '';
                $user->report_signature_updated_at = date(DATE_ATOM);
                R::store($user);
                if ($oldPath !== '' && is_file($oldPath)) @unlink($oldPath);
                audit_log('profilsignatur_geloescht', ['oauthuser_id' => (int) $user->id]);
                $_SESSION['meldung'] = 'Die Unterschrift wurde entfernt.';
                return [303, ['Location' => url_for('profil')], ''];
            }

            $upload = $_FILES['report_signature'] ?? null;
            if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $_SESSION['fehlermeldung'] = 'Bitte eine PNG- oder JPEG-Datei auswählen.';
                return [303, ['Location' => url_for('profil')], ''];
            }
            if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $_SESSION['fehlermeldung'] = 'Die Unterschrift konnte nicht hochgeladen werden.';
                return [303, ['Location' => url_for('profil')], ''];
            }
            $tmp = (string) ($upload['tmp_name'] ?? '');
            $size = (int) ($upload['size'] ?? 0);
            $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
            $dimensions = $tmp !== '' && is_file($tmp) ? @getimagesize($tmp) : false;
            if ($size < 1 || $size > 2 * 1024 * 1024 || !in_array($mime, ['image/png', 'image/jpeg'], true) || $dimensions === false) {
                $_SESSION['fehlermeldung'] = 'Erlaubt sind PNG oder JPEG bis 2 MB.';
                return [303, ['Location' => url_for('profil')], ''];
            }
            if (($dimensions[0] ?? 0) > 4000 || ($dimensions[1] ?? 0) > 2000) {
                $_SESSION['fehlermeldung'] = 'Das Bild ist zu groß. Maximal 4000 × 2000 Pixel sind erlaubt.';
                return [303, ['Location' => url_for('profil')], ''];
            }

            $directory = app_data_root() . '/user-signatures';
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                $_SESSION['fehlermeldung'] = 'Das Signaturverzeichnis konnte nicht angelegt werden.';
                return [303, ['Location' => url_for('profil')], ''];
            }
            $extension = $mime === 'image/png' ? 'png' : 'jpg';
            $target = $directory . '/user-' . (int) $user->id . '.' . $extension;
            $oldPath = trim((string) ($user->report_signature_path ?? ''));
            if (!move_uploaded_file($tmp, $target)) {
                $_SESSION['fehlermeldung'] = 'Die Unterschrift konnte nicht gespeichert werden.';
                return [303, ['Location' => url_for('profil')], ''];
            }
            @chmod($target, 0660);
            if ($oldPath !== '' && $oldPath !== $target && is_file($oldPath)) @unlink($oldPath);
            $user->report_signature_path = $target;
            $user->report_signature_updated_at = date(DATE_ATOM);
            R::store($user);
            audit_log('profilsignatur_gespeichert', ['oauthuser_id' => (int) $user->id]);
            $_SESSION['meldung'] = 'Die Unterschrift wurde gespeichert und wird für neue Prüfberichte verwendet.';
            return [303, ['Location' => url_for('profil')], ''];
        }

        $signature = examiner_signature_data_uri((string) ($user->email ?: $user->name));
        $content = render_template('profile.php', ['user' => $user, 'signature' => $signature]);
        return [200, [], render_template('layout.php', ['title' => 'Mein Profil', 'content' => $content])];
    }
}
