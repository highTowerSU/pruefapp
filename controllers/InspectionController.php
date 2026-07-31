<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class InspectionController
{
    public static function import(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();

        $message = null;
        $stats = null;
        $jobs = self::phoenixJobs();
        $importLogs = self::importLogs();
        $phoenixJob = trim((string) ($_GET['phoenix_job'] ?? ''));
        if ($phoenixJob !== '') {
            $job = self::readPhoenixJob($phoenixJob);
            if (($job['state'] ?? '') === 'done') { $stats = $job['stats'] ?? null; $message = 'Phoenix-Sync abgeschlossen.'; }
            elseif (($job['state'] ?? '') === 'error') $message = 'Phoenix-Sync fehlgeschlagen: ' . (string) ($job['error'] ?? 'Unbekannter Fehler');
            else $message = 'Phoenix-Sync läuft noch im Hintergrund. Diese Seite aktualisiert sich automatisch.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'phoenix_sync') {
                try {
                    $id = bin2hex(random_bytes(12)); $root = sys_get_temp_dir() . '/pruefapp-phoenix-jobs';
                    if (!is_dir($root)) mkdir($root, 0700, true);
                    file_put_contents($root . '/' . $id . '.json', json_encode(['customer_id' => trim((string) ($_POST['phoenix_customer_id'] ?? '')), 'token' => trim((string) ($_POST['phoenix_token'] ?? '')), 'api_url' => trim((string) ($_POST['phoenix_api_url'] ?? '')) ?: 'https://api.phoenix-arbeitswelt.de/phoenix'], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    file_put_contents($root . '/' . $id . '.status.json', json_encode(['state' => 'queued'], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    $worker = dirname(__DIR__) . '/bin/phoenix_sync_worker.php';
                    exec('nohup ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 &');
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                } catch (Throwable $exception) { $message = 'Phoenix-Sync konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            $directory = trim((string) ($_POST['directory'] ?? ''));
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
                return [200, [], render_template('layout.php', ['title' => 'Prüfungen importieren', 'content' => render_template('inspection_import.php', ['message' => $message, 'stats' => $stats, 'jobs' => self::phoenixJobs(), 'importLogs' => self::importLogs()])])];
            }
            try {
                $stats = (new ElectricalInspectionImportService())->importDirectory($directory);
                self::saveImportLog('CSV/ODS/Datei-Import', $stats);
                $message = ($message ? $message . ' ' : '') . sprintf('%d Prüfungen importiert, %d aktualisiert, %d Geräte neu angelegt.', $stats['imported'], $stats['updated'], $stats['devices']);
                if (!empty($stats['errors'])) $message .= ' Hinweis: ' . implode(' | ', array_slice($stats['errors'], 0, 3));
            } catch (Throwable $exception) {
                $message = 'Import nicht möglich: ' . $exception->getMessage();
            }
        }

        return [200, [], render_template('layout.php', [
            'title' => 'Prüfungen importieren',
            'content' => render_template('inspection_import.php', [
                'message' => $message,
                'stats' => $stats,
                'jobs' => $jobs,
                'importLogs' => $importLogs,
            ]),
        ])];
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
        $root = dirname(__DIR__) . '/data/' . app_storage_namespace() . '/import-logs'; $logs = [];
        foreach (array_reverse(glob($root . '/*.json') ?: []) as $path) { $log = json_decode((string) file_get_contents($path), true); if (is_array($log)) $logs[] = $log; if (count($logs) >= 20) break; }
        return $logs;
    }

    private static function saveImportLog(string $type, array $stats): void
    {
        $root = dirname(__DIR__) . '/data/' . app_storage_namespace() . '/import-logs';
        if (!is_dir($root)) mkdir($root, 0770, true);
        file_put_contents($root . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['created_at' => date(DATE_ATOM), 'type' => $type, 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
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
        $relative = trim((string) ($inspection->report_path ?? ''));
        $root = dirname(__DIR__) . '/data/' . app_storage_namespace();
        $path = $relative !== '' ? realpath($root . '/' . ltrim($relative, '/')) : false;
        $rootReal = realpath($root);
        if (!$inspection->id || $path === false || $rootReal === false || !str_starts_with($path, $rootReal . DIRECTORY_SEPARATOR) || !is_file($path)) return [404, [], 'Bericht nicht gefunden'];
        return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . basename($path) . '"'], (string) file_get_contents($path)];
    }
}
