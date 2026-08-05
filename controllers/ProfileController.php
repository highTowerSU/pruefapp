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
            if ($action === 'save_instruction') {
                $initialDate = self::validDate((string) ($_POST['instruction_initial_date'] ?? ''));
                if ($initialDate === false) {
                    $_SESSION['fehlermeldung'] = 'Das Datum der Erstunterweisung ist ungültig.';
                    return [303, ['Location' => url_for('profil')], ''];
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
                        return [303, ['Location' => url_for('profil')], ''];
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
                return [303, ['Location' => url_for('profil')], ''];
            }
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
        $followups = json_decode((string) ($user->instruction_followups_json ?? ''), true);
        $followups = is_array($followups) ? array_values(array_filter($followups, static fn($entry): bool => is_array($entry))) : [];
        $content = render_template('profile.php', ['user' => $user, 'signature' => $signature, 'followups' => $followups]);
        return [200, [], render_template('layout.php', ['title' => 'Mein Profil', 'content' => $content])];
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
