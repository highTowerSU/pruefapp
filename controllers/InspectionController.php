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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                return [200, [], render_template('layout.php', ['title' => 'Prüfungen importieren', 'content' => render_template('inspection_import.php', ['message' => $message, 'stats' => $stats])])];
            }
            try {
                $stats = (new ElectricalInspectionImportService())->importDirectory($directory);
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
            ]),
        ])];
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
