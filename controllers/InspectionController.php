<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class InspectionController
{
    public static function normalizeManualResult($inspection): void
    {
        $measurements = json_decode((string) ($inspection->measurements_json ?? ''), true);
        if ((string) ($inspection->source_type ?? '') !== 'manual' || (string) ($inspection->result_status ?? '') !== 'bestanden' || (is_array($measurements) && $measurements !== [])) return;
        $inspection->result_status = 'ausstehend';
        $inspection->status = 'measurement_pending';
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
    }

    private static function uniqueExternalNumber(string $base, int $ignoreId = 0): string
    {
        $candidate = $base;
        $suffix = 2;
        while (R::count('inspection', ' external_number = ? AND id != ? ', [$candidate, $ignoreId]) > 0) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    public static function create(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $device = R::load('device', (int) ($params['deviceId'] ?? 0));
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Gerät nicht gefunden'];
        $inspection = R::dispense('inspection');
        $inspection->device_id = (int) $device->id;
        $inspection->external_number = self::uniqueExternalNumber(trim((string) $device->external_number) . '-' . date('y'));
        $inspection->dedupe_key = hash('sha256', 'manual|' . $device->id . '|' . microtime(true) . '|' . bin2hex(random_bytes(8)));
        $inspection->source_type = 'manual';
        $inspection->source_file = null;
        $inspection->test_date = date('Y-m-d');
        $user = current_user();
        $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
        $inspection->next_due_date = date('Y-m-d', strtotime('+1 year'));
        $inspection->status = 'draft';
        $inspection->result_status = 'ausstehend';
        $inspection->raw_json = '{}';
        $inspection->checklist_json = '[]';
        $inspection->measurements_json = '[]';
        $inspection->created_at = date(DATE_ATOM);
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
        return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id . '/bearbeiten')], ''];
    }

    public static function edit(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        self::normalizeManualResult($inspection);
        $device = R::load('device', (int) $inspection->device_id);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Prüfung nicht gefunden'];
        if (trim((string) ($inspection->examiner ?? '')) === '') {
            $user = current_user();
            $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
        }
        if (trim((string) ($inspection->next_due_date ?? '')) === '' && trim((string) ($inspection->test_date ?? '')) !== '') {
            $inspection->next_due_date = date('Y-m-d', strtotime((string) $inspection->test_date . ' +1 year'));
        }
        $error = null;
        $correctionMode = current_user_has_role('admin', 'editor');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['protection_class', 'inspection_type', 'examiner', 'test_date', 'next_due_date', 'storage_slot', 'regie_reason', 'cable_length_m'] as $field) $inspection->$field = trim((string) ($_POST[$field] ?? ''));
            $submittedNumber = trim((string) ($_POST['external_number'] ?? $inspection->external_number ?? ''));
            $submittedNumber = (string) (preg_replace('/-(?:\d{2}|20\d{2})$/', '', $submittedNumber) ?: $submittedNumber);
            if ($submittedNumber === '') $submittedNumber = (string) $inspection->external_number;
            $testYear = $inspection->test_date !== '' ? date('y', strtotime((string) $inspection->test_date)) : date('y');
            $numberWithYear = $submittedNumber . '-' . $testYear;
            if (R::count('inspection', ' external_number = ? AND id != ? ', [$numberWithYear, (int) $inspection->id]) > 0) $error = 'Diese Prüfnummer ist bereits vergeben.';
            $inspection->external_number = $numberWithYear;
            $cableLength = (float) str_replace(',', '.', (string) ($inspection->cable_length_m ?? ''));
            $inspection->rsl_limit_ohm = $cableLength > 0 ? min(1, 0.3 + max(0, (int) ceil(($cableLength - 5) / 7.5)) * 0.1) : 0.3;
            $inspection->inspection_type = ['I' => 'Schutzklasse I', 'II' => 'Schutzklasse II', 'III' => 'Schutzklasse III', 'Kabel' => 'Kabelprüfung'][$inspection->protection_class] ?? $inspection->inspection_type;
            if (!current_user_has_role('admin')) {
                $user = current_user();
                $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
            }
            $inspection->regie_minutes = max(0, (int) ($_POST['regie_minutes'] ?? 0));
            $checklist = is_array($_POST['checklist'] ?? null) ? array_map(static fn($value): string => in_array((string) $value, ['ja', 'ok', 'nein'], true) ? ((string) $value === 'ok' ? 'ja' : (string) $value) : '', $_POST['checklist']) : [];
            $inspection->checklist_json = json_encode($checklist, JSON_UNESCAPED_UNICODE);
            $complete = ($_POST['complete'] ?? '') === '1';
            if ($error !== null) {
                // Keep submitted values visible in the correction form.
            } elseif ($complete && ($inspection->protection_class === '' || $inspection->inspection_type === '' || $inspection->examiner === '')) {
                $error = 'Für den Abschluss fehlen Schutzklasse oder Prüfer.';
            } elseif ($complete && count($checklist) < 5 || ($complete && in_array('', $checklist, true))) {
                $error = 'Bitte alle Sicht- und Funktionsprüfungen mit Ja oder Nein beantworten.';
            } elseif ($complete && (!is_array(json_decode((string) ($inspection->measurements_json ?? ''), true)) || json_decode((string) ($inspection->measurements_json ?? ''), true) === [])) {
                $error = 'Die Prüfung kann erst nach dem Import der Messwerte abgeschlossen werden.';
            } elseif ($correctionMode && !$complete) {
                // A superadmin may correct imported historical data without
                // reopening a completed inspection or changing its result.
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
                return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id)], ''];
            } else {
                $inspection->status = $complete ? 'completed' : ($inspection->storage_slot !== '' ? 'measurement_pending' : 'draft');
                $inspection->result_status = $complete ? (in_array('nein', $checklist, true) ? 'durchgefallen' : 'bestanden') : 'ausstehend';
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
                $target = $complete
                    ? 'admin/pruefungen/' . (int) $inspection->id
                    : 'admin/pruefungen/' . (int) $inspection->id . '/bearbeiten';
                return [303, ['Location' => url_for($target)], ''];
            }
        }
        $users = R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ');
        $canChooseOtherExaminer = current_user_has_role('admin');
        return [200, [], render_template('layout.php', ['title' => 'Prüfung bearbeiten', 'content' => render_template('inspection_edit.php', compact('inspection', 'device', 'users', 'error', 'canChooseOtherExaminer'))])];
    }
    private static function pendingMeasurementsByDate(): array
    {
        $pending = [];
        $inspections = R::getAll("SELECT i.id AS inspection_id, i.device_id, i.external_number AS inspection_number, i.storage_slot, i.test_date, i.measurements_json, d.external_number AS device_number, d.name AS device_name FROM inspection i LEFT JOIN device d ON d.id = i.device_id WHERE (i.result_status = ? OR i.status IN ('draft', 'measurement_pending')) ORDER BY i.test_date ASC, i.id ASC", ['ausstehend']);
        foreach ($inspections as $inspection) {
            if ((int) ($inspection['device_id'] ?? 0) <= 0) continue;
            $date = trim((string) ($inspection['test_date'] ?? '')) ?: 'ohne Datum';
            $pending[$date][] = [
                'inspection_id' => (int) $inspection['inspection_id'],
                'device_id' => (int) $inspection['device_id'],
                'number' => trim((string) ($inspection['device_number'] ?? '')) ?: trim((string) ($inspection['inspection_number'] ?? '')),
                'name' => trim((string) ($inspection['device_name'] ?? '')),
                'inspection_number' => trim((string) ($inspection['inspection_number'] ?? '')),
                'storage_slot' => trim((string) ($inspection['storage_slot'] ?? '')),
                'result_status' => trim((string) ($inspection['result_status'] ?? 'ausstehend')),
            ];
        }
        ksort($pending, SORT_NATURAL);
        return $pending;
    }

    public static function import(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();

        $message = null;
        $stats = null;
        $jobs = self::phoenixJobs();
        $importLogs = self::importLogs();
        $cron = self::cronStatus();
        $pendingMeasurementsByDate = self::pendingMeasurementsByDate();
        $examinerUsers = array_map(static fn($user): array => ['id' => (int) $user->id, 'label' => trim((string) ($user->name ?? '')) . (trim((string) ($user->email ?? '')) !== '' ? ' · ' . trim((string) $user->email) : ''), 'value' => trim((string) ($user->email ?? $user->name ?? ''))], R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id '));
        $phoenixJob = trim((string) ($_GET['phoenix_job'] ?? ''));
        if ($phoenixJob !== '') {
            $job = self::readPhoenixJob($phoenixJob);
            if (($job['state'] ?? '') === 'done') { $stats = $job['stats'] ?? null; $message = 'Phoenix-Sync abgeschlossen.'; }
            elseif (($job['state'] ?? '') === 'error') $message = 'Phoenix-Sync fehlgeschlagen: ' . (string) ($job['error'] ?? 'Unbekannter Fehler');
            elseif (($job['state'] ?? '') === 'cancelled' || ($job['state'] ?? '') === 'cancel_requested') $message = 'Phoenix-Sync wurde abgebrochen.';
            else $message = 'Phoenix-Sync läuft noch im Hintergrund. Diese Seite aktualisiert sich automatisch.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'pending_measurement_import' && isset($_FILES['measurement_csv']) && is_array($_FILES['measurement_csv'])) {
                try {
                    $date = trim((string) ($_POST['measurement_date'] ?? ''));
                    $tmp = (string) ($_FILES['measurement_csv']['tmp_name'] ?? '');
                    if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('CSV-Datei fehlt.');
                    $stats = (new ElectricalInspectionImportService())->importPendingMeasurements($tmp, $date);
                    $message = (int) $stats['updated'] . ' bestehende Prüfung(en) mit Messdaten aktualisiert; ' . (int) $stats['skipped'] . ' Zeile(n) ohne passende Prüfung übersprungen.';
                    if ((int) ($stats['cable_length_required'] ?? 0) > 0) $message .= ' Bei ' . (int) $stats['cable_length_required'] . ' Messung(en) wird wegen des Schutzleitergrenzwerts noch die Kabellänge benötigt.';
                } catch (Throwable $exception) { $message = 'Messdatenimport nicht möglich: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'directory_import_job') {
                try {
                    $directoryJob = trim((string) ($_POST['directory'] ?? ''));
                    if ($directoryJob === '') throw new InvalidArgumentException('Bitte ein Importverzeichnis angeben.');
                    $id = bin2hex(random_bytes(12)); $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
                    if (!is_dir($root)) mkdir($root, 0700, true);
                    $defaults = ['inspection_type' => trim((string) ($_POST['default_inspection_type'] ?? '')), 'examiner' => trim((string) ($_POST['default_examiner'] ?? '')), 'next_due_date' => trim((string) ($_POST['default_next_due_date'] ?? '')), 'next_due_offset_days' => (int) ($_POST['default_next_due_offset_days'] ?? 0), 'test_date' => trim((string) ($_POST['default_test_date'] ?? ''))];
                    $rules = json_decode((string) ($_POST['import_rules'] ?? '[]'), true); if (is_array($rules)) $defaults['import_rules'] = $rules;
                    file_put_contents($root . '/' . $id . '.json', json_encode(['type' => 'directory_import', 'directory' => $directoryJob, 'reports_directory' => trim((string) ($_POST['reports_directory'] ?? '')), 'defaults' => $defaults], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    file_put_contents($root . '/' . $id . '.status.json', json_encode(['id' => $id, 'state' => 'queued', 'created_at' => date(DATE_ATOM), 'message' => 'Import wartet auf den Prüfapp-Cron.'], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                } catch (Throwable $exception) { $message = 'Import-Job konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'phoenix_sync') {
                try {
                    $id = bin2hex(random_bytes(12)); $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
                    if (!is_dir($root)) mkdir($root, 0700, true);
                    file_put_contents($root . '/' . $id . '.json', json_encode(['customer_id' => trim((string) ($_POST['phoenix_customer_id'] ?? '')), 'token' => trim((string) ($_POST['phoenix_token'] ?? '')), 'api_url' => trim((string) ($_POST['phoenix_api_url'] ?? '')) ?: 'https://api.phoenix-arbeitswelt.de/phoenix'], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    file_put_contents($root . '/' . $id . '.status.json', json_encode(['state' => 'queued'], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                } catch (Throwable $exception) { $message = 'Phoenix-Sync konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            $directory = trim((string) ($_POST['directory'] ?? ''));
            $reportsDirectory = trim((string) ($_POST['reports_directory'] ?? ''));
            if (isset($_FILES['csv'], $_FILES['ods']) && is_array($_FILES['csv']) && is_array($_FILES['ods'])) {
                try {
                    $directory = self::savePairUpload($_FILES['csv'], $_FILES['ods']);
                    $message = 'CSV/ODS-Paar hochgeladen und wird importiert.';
                } catch (Throwable $exception) {
                    $message = 'Upload nicht möglich: ' . $exception->getMessage();
                    $directory = '';
                }
            }
            if ($directory === '') {
                return [200, [], render_template('layout.php', ['title' => 'Prüfungen importieren', 'content' => render_template('inspection_import.php', ['message' => $message, 'stats' => $stats, 'jobs' => self::phoenixJobs(), 'importLogs' => self::importLogs(), 'cron' => self::cronStatus(), 'examinerUsers' => $examinerUsers, 'pendingMeasurementsByDate' => self::pendingMeasurementsByDate()])])];
            }
            try {
                $defaults = ['inspection_type' => trim((string) ($_POST['default_inspection_type'] ?? '')), 'examiner' => trim((string) ($_POST['default_examiner'] ?? '')), 'next_due_date' => trim((string) ($_POST['default_next_due_date'] ?? '')), 'next_due_offset_days' => (int) ($_POST['default_next_due_offset_days'] ?? 0)];
                $rules = json_decode((string) ($_POST['import_rules'] ?? '[]'), true);
                if (is_array($rules)) $defaults['import_rules'] = $rules;
                $defaults = array_filter($defaults, static fn($value): bool => $value !== '' && $value !== 0);
                $stats = (new ElectricalInspectionImportService())->importDirectory($directory, $reportsDirectory !== '' ? $reportsDirectory : null, $defaults);
                $message = ($message ? $message . ' ' : '') . sprintf('%d Prüfungen importiert, %d aktualisiert, %d Geräte neu angelegt.', $stats['imported'], $stats['updated'], $stats['devices']);
                if (!empty($stats['errors'])) $message .= ' Hinweis: ' . implode(' | ', array_slice($stats['errors'], 0, 3));
            } catch (Throwable $exception) {
                $message = 'Import nicht möglich: ' . $exception->getMessage();
            }
        }

        $pendingMeasurementsByDate = self::pendingMeasurementsByDate();
        $importLogs = self::importLogs();

        return [200, [], render_template('layout.php', [
            'title' => 'Prüfungen importieren',
            'content' => render_template('inspection_import.php', [
                'message' => $message,
                'stats' => $stats,
                'jobs' => $jobs,
                'importLogs' => $importLogs,
                'cron' => $cron,
                'examinerUsers' => $examinerUsers,
                'pendingMeasurementsByDate' => $pendingMeasurementsByDate,
            ]),
        ])];
    }

    public static function cancelPhoenixJob(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $id = (string) ($params['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return [400, [], 'Ungültige Job-ID.'];
        $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
        $statusPath = $root . '/' . $id . '.status.json';
        if (is_file($statusPath)) {
            $status = json_decode((string) file_get_contents($statusPath), true) ?: [];
            $state = (string) ($status['state'] ?? 'queued');
            $status['state'] = $state === 'queued' ? 'cancelled' : 'cancel_requested';
            $status['finished_at'] = $state === 'queued' ? date(DATE_ATOM) : ($status['finished_at'] ?? null);
            file_put_contents($statusPath, json_encode($status, JSON_UNESCAPED_UNICODE), LOCK_EX);
            file_put_contents($root . '/' . $id . '.cancel', '1', LOCK_EX);
        }
        return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
    }

    public static function phoenixStatus(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return [403, ['Content-Type' => 'application/json'], '{}'];
        return [200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(self::readPhoenixJob((string) ($params['id'] ?? '')), JSON_UNESCAPED_UNICODE)];
    }

    public static function archivePhoenixJob(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $id = (string) ($params['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return [400, [], 'Ungültige Job-ID.'];
        $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
        $statusPath = $root . '/' . $id . '.status.json';
        if (is_file($statusPath)) {
            $status = json_decode((string) file_get_contents($statusPath), true) ?: [];
            if (in_array((string) ($status['state'] ?? ''), ['queued', 'running'], true)) return [409, [], 'Laufende Jobs können nicht archiviert werden.'];
            $archiveRoot = $root . '/archive';
            if (!is_dir($archiveRoot)) mkdir($archiveRoot, 0700, true);
            rename($statusPath, $archiveRoot . '/' . $id . '.status.json');
            if (is_file($root . '/' . $id . '.json')) rename($root . '/' . $id . '.json', $archiveRoot . '/' . $id . '.json');
        }
        return [303, ['Location' => url_for('admin/pruefungen/import')], ''];
    }

    private static function readPhoenixJob(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return ['state' => 'error', 'error' => 'Ungültige Job-ID.'];
        $path = sys_get_temp_dir() . '/pruefapp-phoenix-jobs/' . $id . '.status.json';
        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: ['state' => 'running']) : ['state' => 'running'];
    }

    private static function phoenixJobs(): array
    {
        $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs'; $jobs = [];
        foreach (glob($root . '/*.status.json') ?: [] as $path) {
            $job = json_decode((string) file_get_contents($path), true);
            if (is_array($job)) { $job['id'] ??= basename($path, '.status.json'); $jobs[] = $job; }
        }
        usort($jobs, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_slice($jobs, 0, 20);
    }

    private static function importLogs(): array
    {
        $root = app_data_root() . '/import-logs'; $logs = [];
        foreach (array_reverse(glob($root . '/*.json') ?: []) as $path) { $log = json_decode((string) file_get_contents($path), true); if (is_array($log)) $logs[] = $log; if (count($logs) >= 20) break; }
        return $logs;
    }

    private static function saveImportLog(string $type, array $stats): void
    {
        $root = dirname(__DIR__) . '/data/' . app_storage_namespace() . '/import-logs';
        if (!is_dir($root)) mkdir($root, 0770, true);
        file_put_contents($root . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['created_at' => date(DATE_ATOM), 'type' => $type, 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function cronStatus(): array
    {
        $path = sys_get_temp_dir() . '/pruefapp-phoenix-jobs/cron-heartbeat.json';
        $data = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $timestamp = isset($data['last_run']) ? strtotime((string) $data['last_run']) : (is_file($path) ? filemtime($path) : 0);
        $displayTime = $timestamp > 0 ? (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d H:i:s T') : null;
        return ['last_run' => $displayTime, 'age' => $timestamp > 0 ? max(0, time() - $timestamp) : null, 'healthy' => $timestamp > 0 && (time() - $timestamp) <= 300];
    }

    private static function savePairUpload(array $csv, array $ods): string
    {
        foreach ([$csv, $ods] as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Bitte genau eine CSV- und eine ODS-Datei auswählen.');
        }
        $csvName = basename((string) ($csv['name'] ?? ''));
        $odsName = basename((string) ($ods['name'] ?? ''));
        if (!preg_match('/\.csv$/i', $csvName) || !preg_match('/\.ods$/i', $odsName) || strcasecmp(pathinfo($csvName, PATHINFO_FILENAME), pathinfo($odsName, PATHINFO_FILENAME)) !== 0) {
            throw new RuntimeException('CSV und ODS müssen denselben Dateinamen (unterschiedliche Endung) haben.');
        }
        $directory = sys_get_temp_dir() . '/pruefapp-upload-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) throw new RuntimeException('Temporäres Upload-Verzeichnis konnte nicht angelegt werden.');
        if (!move_uploaded_file((string) $csv['tmp_name'], $directory . '/' . $csvName) || !move_uploaded_file((string) $ods['tmp_name'], $directory . '/' . $odsName)) throw new RuntimeException('Upload konnte nicht gespeichert werden.');
        return $directory;
    }

    public static function report(array $params, bool $isHx): array
    {
        if (!current_user()) return [403, [], ''];
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        $reportDevice = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
        if (!$inspection->id || !$reportDevice || !$reportDevice->id || !current_user_can_access_customer(device_customer_id($reportDevice))) return [404, [], 'Bericht nicht gefunden'];
        $relative = trim((string) ($inspection->report_path ?? ''));
        $root = app_data_root();
        $path = $relative !== '' ? realpath($root . '/' . ltrim($relative, '/')) : false;
        $rootReal = realpath($root);
        if (!$inspection->id || $path === false || $rootReal === false || !str_starts_with($path, $rootReal . DIRECTORY_SEPARATOR) || !is_file($path)) return [404, [], 'Bericht nicht gefunden'];
        return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . basename($path) . '"'], (string) file_get_contents($path)];
    }

    public static function detail(array $params, bool $isHx): array
    {
        if (!current_user()) return [403, [], ''];
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        self::normalizeManualResult($inspection);
        $device = R::load('device', (int) $inspection->device_id);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Prüfung nicht gefunden'];
        if ((int) ($device->room_id ?? 0) > 0) {
            $room = R::load('room', (int) $device->room_id);
            $floor = R::load('floor', (int) ($room->floor_id ?? 0));
            $area = R::load('area', (int) ($room->area_id ?? 0));
            if ($room->id) $inspection->room_snapshot = class_exists('StructureController') ? StructureController::roomIdentifier($room, $floor, $area) : (string) ($room->name ?: $room->number);
        }
        $raw = json_decode((string) ($inspection->raw_json ?? ''), true) ?: [];
        $measurements = json_decode((string) ($inspection->measurements_json ?? ''), true) ?: [];
        $checklist = json_decode((string) ($inspection->checklist_json ?? ''), true) ?: [];
        if ((string) ($inspection->source_type ?? '') === 'manual' && $measurements !== []) {
            $negative = false; $open = false;
            foreach ($measurements as $measurement) {
                if (!is_array($measurement)) continue;
                $result = strtolower(trim((string) ($measurement['result'] ?? '')));
                if (in_array($result, ['nicht bestanden', 'nein', 'failed', 'nok'], true)) $negative = true;
            }
            foreach ($checklist as $step) {
                $result = strtolower(trim((string) (is_array($step) ? ($step['result'] ?? '') : $step)));
                if ($result === 'nein') $negative = true;
                elseif ($result === '' || $result === 'offen') $open = true;
            }
            if (!$negative && !$open && (string) $inspection->result_status === 'durchgefallen') {
                $inspection->result_status = 'bestanden';
                $inspection->status = 'completed';
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
            }
        }
        return [200, [], render_template('layout.php', ['title' => 'Prüfung ' . (string) $inspection->external_number, 'content' => render_template('inspection_detail.php', compact('inspection', 'device', 'raw', 'measurements', 'checklist'))])];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        self::normalizeManualResult($inspection);
        if ((string) $inspection->result_status !== 'ausstehend') return [409, [], 'Nur Prüfungen mit ausstehendem Ergebnis können gelöscht werden.'];
        $deviceId = (int) $inspection->device_id;
        R::trash($inspection);
        audit_log('pruefung_geloescht', ['id' => (int) ($params['id'] ?? 0), 'device_id' => $deviceId]);
        return [303, ['Location' => url_for('geraete?device_id=' . $deviceId)], ''];
    }
}
