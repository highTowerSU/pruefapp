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
        $canConfirmQualifications = current_user_has_role('admin')
            && (!$adminView || (int) ($user->id ?? 0) !== (int) (current_user()->id ?? 0));
        $profileUrl = $adminView ? url_for('admin/nutzer/' . (int) $user->id . '/profil') : url_for('profil');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$canEdit) return forbidden_response();
            $action = trim((string) ($_POST['action'] ?? 'upload_signature'));
            if ($action === 'disconnect_companion') {
                InspectionCompanionService::disconnectSession((int) ($_POST['companion_session_id'] ?? 0), (int) $user->id);
                audit_log('pruef_companion_getrennt', ['oauthuser_id' => (int) $user->id, 'session_id' => (int) ($_POST['companion_session_id'] ?? 0)]);
                $_SESSION['meldung'] = 'Companion-Verbindung wurde beendet.';
                return [303, ['Location' => $profileUrl . '#companion-sessions'], ''];
            }
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
                $selectedRequirementCodes = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['certificate_requirement_codes'] ?? [])))));
                $availableRequirements = R::getAll('SELECT r.*, t.name AS inspection_type_name FROM inspection_type_requirement r JOIN inspection_type t ON t.code = r.inspection_type_code WHERE r.active = 1 ORDER BY t.sort_order, r.sort_order');
                $requirementsByCode = [];
                foreach ($availableRequirements as $requirement) $requirementsByCode[(string) $requirement['code']] = $requirement;
                $selectedRequirements = array_values(array_filter(array_map(static fn(string $code): ?array => $requirementsByCode[$code] ?? null, $selectedRequirementCodes)));
                // Compatibility with older forms: a selected inspection type means its
                // ordinary instruction requirement, never an additional VEFK requirement.
                if ($selectedRequirements === []) {
                    $selectedTypesLegacy = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['certificate_inspection_types'] ?? [])))));
                    foreach ($availableRequirements as $requirement) {
                        if (in_array((string) $requirement['inspection_type_code'], $selectedTypesLegacy, true) && str_ends_with((string) $requirement['code'], '_instruction')) $selectedRequirements[] = $requirement;
                    }
                }
                $selectedTypes = array_values(array_unique(array_map(static fn(array $requirement): string => (string) $requirement['inspection_type_code'], $selectedRequirements)));
                if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $date === false || $date === '') {
                    $_SESSION['fehlermeldung'] = 'Bitte ein PDF und ein gültiges Datum auswählen.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                if ($selectedRequirements === []) {
                    $_SESSION['fehlermeldung'] = 'Bitte mindestens eine konkrete Nachweisart und Prüfart auswählen.';
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
                $qualificationIds = [];
                foreach ($selectedRequirements as $requirement) {
                    $expiresAt = '';
                    if ((int) ($requirement['validity_days'] ?? 0) > 0) $expiresAt = date('Y-m-d', strtotime($date . ' +' . (int) $requirement['validity_days'] . ' days'));
                    R::exec('INSERT INTO user_qualification (oauthuser_id, requirement_code, issued_at, expires_at, proof_path, proof_name, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                        (int) $user->id, (string) $requirement['code'], $date, $expiresAt, $target,
                        mb_substr((string) ($upload['name'] ?? 'Unterweisungsnachweis.pdf'), 0, 240),
                        'Unterweisungsnachweis: ' . ($title !== '' ? $title : $kind), date(DATE_ATOM), date(DATE_ATOM),
                    ]);
                    $qualificationIds[] = (int) R::getInsertID();
                }
                $certificates = self::certificates($user);
                $certificates[] = ['id' => $certificateId, 'kind' => $kind !== '' ? $kind : 'Folgeunterweisung', 'date' => $date, 'title' => $title, 'path' => $target, 'name' => mb_substr((string) ($upload['name'] ?? 'Nachweis.pdf'), 0, 240), 'inspection_type_codes' => $selectedTypes, 'qualification_ids' => $qualificationIds, 'created_at' => date(DATE_ATOM)];
                $user->instruction_certificates_json = json_encode($certificates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $user->instruction_updated_at = date(DATE_ATOM);
                R::store($user);
                audit_log('unterweisungsnachweis_gespeichert', ['oauthuser_id' => (int) $user->id, 'art' => $kind, 'pruefarten' => $selectedTypes, 'nachweise' => $selectedRequirementCodes]);
                $queuedReports = self::queueMissingReportsForExaminer($user);
                $_SESSION['meldung'] = 'Unterweisungsnachweis gespeichert und den gewählten Prüfarten zugeordnet.' . ($queuedReports > 0 ? ' ' . $queuedReports . ' fertige Prüfberichte werden nun im Hintergrund erzeugt.' : '');
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'upload_followup') {
                $certificateId = trim((string) ($_POST['certificate_id'] ?? ''));
                $date = self::validDate((string) ($_POST['followup_date'] ?? ''));
                $upload = $_FILES['followup_certificate'] ?? null;
                if ($certificateId === '' || $date === false || $date === '' || !is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $_SESSION['fehlermeldung'] = 'Bitte Folgeunterweisung, Datum und PDF angeben.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $tmp = (string) ($upload['tmp_name'] ?? '');
                $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
                if ($mime !== 'application/pdf' || (int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                    $_SESSION['fehlermeldung'] = 'Der Folgeunterweisungsnachweis muss ein PDF bis 10 MB sein.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $certificates = self::certificates($user); $found = false;
                foreach ($certificates as &$certificate) {
                    if ((string) ($certificate['id'] ?? '') !== $certificateId) continue;
                    $directory = app_data_root() . '/user-instructions/' . (int) $user->id;
                    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Nachweisverzeichnis konnte nicht angelegt werden.');
                    $followupId = bin2hex(random_bytes(8)); $target = $directory . '/' . $followupId . '.pdf';
                    if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Die Folgeunterweisung konnte nicht gespeichert werden.');
                    @chmod($target, 0660);
                    $certificate['followups'][] = ['id' => $followupId, 'date' => $date, 'kind' => mb_substr(trim((string) ($_POST['followup_kind'] ?? 'Folgeunterweisung')), 0, 80), 'title' => mb_substr(trim((string) ($_POST['followup_title'] ?? '')), 0, 240), 'path' => $target, 'name' => mb_substr((string) ($upload['name'] ?? 'Folgeunterweisung.pdf'), 0, 240), 'created_at' => date(DATE_ATOM)];
                    // A current follow-up renews the linked qualification until the
                    // next configured training date; the original certificate remains in history.
                    foreach (array_values(array_filter(array_map('intval', (array) ($certificate['qualification_ids'] ?? [])))) as $qualificationId) {
                        $requirement = R::getRow('SELECT validity_days FROM inspection_type_requirement r JOIN user_qualification q ON q.requirement_code = r.code WHERE q.id = ? LIMIT 1', [$qualificationId]);
                        $expiresAt = (int) ($requirement['validity_days'] ?? 0) > 0 ? date('Y-m-d', strtotime($date . ' +' . (int) $requirement['validity_days'] . ' days')) : '';
                        R::exec('UPDATE user_qualification SET expires_at = ?, updated_at = ? WHERE id = ? AND oauthuser_id = ?', [$expiresAt, date(DATE_ATOM), $qualificationId, (int) $user->id]);
                    }
                    $found = true; break;
                }
                unset($certificate);
                if (!$found) { $_SESSION['fehlermeldung'] = 'Befähigung nicht gefunden.'; return [303, ['Location' => $profileUrl], '']; }
                $user->instruction_certificates_json = json_encode($certificates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); R::store($user);
                audit_log('folgeunterweisung_gespeichert', ['oauthuser_id' => (int) $user->id, 'certificate_id' => $certificateId]);
                $_SESSION['meldung'] = 'Folgeunterweisung zur Befähigung hinzugefügt.';
                return [303, ['Location' => $profileUrl . '#qualifications'], ''];
            }
            if ($action === 'delete_certificate') {
                $certificateId = trim((string) ($_POST['certificate_id'] ?? ''));
                $remaining = [];
                foreach (self::certificates($user) as $certificate) {
                    if ((string) ($certificate['id'] ?? '') === $certificateId) {
                        $qualificationIds = array_values(array_filter(array_map('intval', (array) ($certificate['qualification_ids'] ?? []))));
                        if ($qualificationIds !== []) {
                            $marks = implode(',', array_fill(0, count($qualificationIds), '?'));
                            R::exec("DELETE FROM user_qualification WHERE oauthuser_id = ? AND id IN ($marks)", array_merge([(int) $user->id], $qualificationIds));
                        } else {
                            R::exec('DELETE FROM user_qualification WHERE oauthuser_id = ? AND proof_path = ?', [(int) $user->id, (string) ($certificate['path'] ?? '')]);
                        }
                        if (is_file((string) ($certificate['path'] ?? ''))) @unlink((string) ($certificate['path'] ?? ''));
                        continue;
                    }
                    $remaining[] = $certificate;
                }
                $user->instruction_certificates_json = json_encode($remaining, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                R::store($user);
                audit_log('unterweisungsnachweis_geloescht', ['oauthuser_id' => (int) $user->id]);
                $_SESSION['meldung'] = 'Unterweisungsnachweis entfernt.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'save_qualification') {
                $requirementCode = trim((string) ($_POST['requirement_code'] ?? ''));
                $requirement = R::getRow('SELECT * FROM inspection_type_requirement WHERE code = ? LIMIT 1', [$requirementCode]);
                $issuedAt = self::validDate((string) ($_POST['qualification_issued_at'] ?? ''));
                $expiresAt = self::validDate((string) ($_POST['qualification_expires_at'] ?? ''));
                if ($requirement === [] || $issuedAt === false || $expiresAt === false || $issuedAt === '') {
                    $_SESSION['fehlermeldung'] = 'Bitte Nachweis und Ausstellungsdatum gültig angeben.';
                    return [303, ['Location' => $profileUrl], ''];
                }
                $proofPath = ''; $proofName = '';
                $upload = $_FILES['qualification_proof'] ?? null;
                if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = (string) ($upload['tmp_name'] ?? '');
                    $mime = $tmp !== '' && is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
                    if ($mime !== 'application/pdf' || (int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                        $_SESSION['fehlermeldung'] = 'Der Befähigungsnachweis muss ein PDF bis 10 MB sein.';
                        return [303, ['Location' => $profileUrl], ''];
                    }
                    $directory = app_data_root() . '/user-qualifications/' . (int) $user->id;
                    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Das Nachweisverzeichnis konnte nicht angelegt werden.');
                    $proofPath = $directory . '/' . bin2hex(random_bytes(8)) . '.pdf';
                    if (!move_uploaded_file($tmp, $proofPath)) throw new RuntimeException('Der Befähigungsnachweis konnte nicht gespeichert werden.');
                    @chmod($proofPath, 0660); $proofName = mb_substr((string) ($upload['name'] ?? 'Nachweis.pdf'), 0, 240);
                }
                R::exec('INSERT INTO user_qualification (oauthuser_id, requirement_code, issued_at, expires_at, proof_path, proof_name, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    (int) $user->id, $requirementCode, $issuedAt, $expiresAt, $proofPath, $proofName, mb_substr(trim((string) ($_POST['qualification_notes'] ?? '')), 0, 1000), date(DATE_ATOM), date(DATE_ATOM),
                ]);
                audit_log('befaehigungsnachweis_gespeichert', ['oauthuser_id' => (int) $user->id, 'requirement_code' => $requirementCode]);
                $_SESSION['meldung'] = 'Befähigungsnachweis gespeichert.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'confirm_qualification') {
                if (!current_user_has_role('admin')) return forbidden_response();
                $qualificationId = (int) ($_POST['qualification_id'] ?? 0);
                R::exec('UPDATE user_qualification SET confirmed_by = ?, confirmed_at = ?, updated_at = ? WHERE id = ? AND oauthuser_id = ?', [(int) current_user()->id, date(DATE_ATOM), date(DATE_ATOM), $qualificationId, (int) $user->id]);
                audit_log('befaehigungsnachweis_bestaetigt', ['oauthuser_id' => (int) $user->id, 'qualification_id' => $qualificationId]);
                $_SESSION['meldung'] = 'Befähigungsnachweis bestätigt.';
                return [303, ['Location' => $profileUrl], ''];
            }
            if ($action === 'confirm_certificate') {
                if (!current_user_has_role('admin')) return forbidden_response();
                $certificateId = trim((string) ($_POST['certificate_id'] ?? ''));
                $certificate = null;
                foreach (self::certificates($user) as $candidate) {
                    if ((string) ($candidate['id'] ?? '') === $certificateId) { $certificate = $candidate; break; }
                }
                if ($certificate === null) return [404, [], 'Unterweisungsnachweis nicht gefunden.'];
                $qualificationIds = array_values(array_filter(array_map('intval', (array) ($certificate['qualification_ids'] ?? []))));
                if ($qualificationIds !== []) {
                    $marks = implode(',', array_fill(0, count($qualificationIds), '?'));
                    R::exec("UPDATE user_qualification SET confirmed_by = ?, confirmed_at = ?, updated_at = ? WHERE oauthuser_id = ? AND id IN ($marks)", array_merge([(int) current_user()->id, date(DATE_ATOM), date(DATE_ATOM), (int) $user->id], $qualificationIds));
                }
                audit_log('unterweisungsnachweis_bestaetigt', ['oauthuser_id' => (int) $user->id, 'certificate_id' => $certificateId, 'qualification_ids' => $qualificationIds]);
                $_SESSION['meldung'] = 'Unterweisung geprüft und den ausgewählten Prüfarten freigegeben.';
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
        $inspectionTypes = InspectionTypeService::active();
        $activeCompanionSessions = InspectionCompanionService::activeForUser((int) $user->id);
        $inspectionPermissions = [];
        foreach ($inspectionTypes as $inspectionType) {
            $inspectionPermissions[(string) $inspectionType['code']] = InspectionTypeService::permissionForUser($user, (string) $inspectionType['code']);
        }
        $qualificationRequirements = R::getAll('SELECT r.*, t.name AS inspection_type_name FROM inspection_type_requirement r JOIN inspection_type t ON t.code = r.inspection_type_code WHERE r.active = 1 ORDER BY t.sort_order, r.sort_order');
        $qualifications = R::getAll('SELECT q.*, r.name AS requirement_name, t.name AS inspection_type_name FROM user_qualification q LEFT JOIN inspection_type_requirement r ON r.code=q.requirement_code LEFT JOIN inspection_type t ON t.code=r.inspection_type_code WHERE q.oauthuser_id = ? ORDER BY q.id DESC', [(int) $user->id]);
        $qualificationById = [];
        foreach ($qualifications as $qualification) $qualificationById[(int) ($qualification['id'] ?? 0)] = $qualification;
        foreach ($certificates as &$certificate) {
            $ids = array_values(array_filter(array_map('intval', (array) ($certificate['qualification_ids'] ?? []))));
            $linked = array_values(array_filter(array_map(static fn(int $id): ?array => $qualificationById[$id] ?? null, $ids)));
            $certificate['qualification_status'] = $linked !== [] && count(array_filter($linked, static fn(array $q): bool => empty($q['confirmed_at']))) === 0 ? 'confirmed' : 'pending';
            $certificate['qualification_expired'] = $linked !== [] && count(array_filter($linked, static fn(array $q): bool => !empty($q['expires_at']) && (string) $q['expires_at'] < date('Y-m-d'))) > 0;
        }
        unset($certificate);
        $content = render_template('profile.php', ['user' => $user, 'signature' => $signature, 'followups' => $followups, 'certificates' => $certificates, 'qualifications' => $qualifications, 'qualificationRequirements' => $qualificationRequirements, 'inspectionTypes' => $inspectionTypes, 'inspectionPermissions' => $inspectionPermissions, 'activeCompanionSessions' => $activeCompanionSessions, 'canEdit' => $canEdit, 'canConfirmQualifications' => $canConfirmQualifications, 'profileUrl' => $profileUrl, 'adminView' => $adminView]);
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
            $followupIndex = filter_input(INPUT_GET, 'followup', FILTER_VALIDATE_INT);
            if ($followupIndex !== null && $followupIndex !== false && isset($certificate['followups'][(int) $followupIndex]) && is_file((string) ($certificate['followups'][(int) $followupIndex]['path'] ?? ''))) {
                $certificate = $certificate['followups'][(int) $followupIndex];
            }
            $filename = str_replace('"', '', basename((string) ($certificate['name'] ?? 'unterweisungsnachweis.pdf')));
            header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $filename . '"'); header('Content-Length: ' . filesize((string) $certificate['path'])); readfile((string) $certificate['path']); exit;
        }
        return [404, [], 'Nachweis nicht gefunden'];
    }

    public static function qualificationProof(array $params, bool $isHx): array
    {
        $viewer = current_user();
        if (!$viewer) return [403, [], ''];
        $targetId = (int) ($params['userId'] ?? 0);
        if ($targetId > 0) {
            if (!current_user_has_role('admin')) return forbidden_response();
        } else {
            $targetId = (int) $viewer->id;
        }
        if ($targetId !== (int) $viewer->id && !current_user_has_role('admin')) return forbidden_response();
        $qualification = R::getRow('SELECT proof_path, proof_name FROM user_qualification WHERE id = ? AND oauthuser_id = ?', [(int) ($params['qualificationId'] ?? 0), $targetId]);
        $path = (string) ($qualification['proof_path'] ?? '');
        if ($qualification === [] || !is_file($path)) return [404, [], 'Nachweis nicht gefunden'];
        $filename = str_replace('"', '', basename((string) ($qualification['proof_name'] ?: 'befaehigungsnachweis.pdf')));
        header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $filename . '"'); header('Content-Length: ' . filesize($path)); readfile($path); exit;
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
