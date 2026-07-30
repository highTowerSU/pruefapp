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
            try {
                $stats = (new ElectricalInspectionImportService())->importDirectory($directory);
                $message = sprintf('%d Prüfungen importiert, %d aktualisiert, %d Geräte neu angelegt.', $stats['imported'], $stats['updated'], $stats['devices']);
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
