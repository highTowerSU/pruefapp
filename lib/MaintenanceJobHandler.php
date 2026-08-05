<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Resumable handlers for automatic maintenance work executed by the shared queue. */
final class MaintenanceJobHandler
{
    /**
     * @param array<string,mixed> $job
     * @param callable(array<string,mixed>,int,int,string,string):void $tick
     * @return array<string,mixed>
     */
    public static function run(array $job, callable $tick): array
    {
        $type = (string) ($job['type'] ?? '');
        $checkpoint = (array) ($job['checkpoint'] ?? []);
        $current = max(0, (int) ($job['current'] ?? 0));
        $total = max(0, (int) ($job['total'] ?? 0));

        return match ($type) {
            'missing_reports' => self::missingReports($checkpoint, $current, $total, $tick),
            'report_migration' => self::reportMigration($checkpoint, $current, $total, $tick),
            'phoenix_pdf_restore' => self::restorePhoenixPdfs($checkpoint, $current, $total, $tick),
            'measurement_migration' => self::measurementMigration($checkpoint, $current, $total, $tick),
            default => throw new InvalidArgumentException('Unbekannte Wartungsaufgabe: ' . $type),
        };
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function missingReports(array $checkpoint, int $current, int $total, callable $tick): array
    {
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE result_status IN ('bestanden','durchgefallen','nicht bestanden') AND TRIM(COALESCE(report_path, '')) = '' AND NOT (COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%')");
        }
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        $errors = is_array($checkpoint['errors'] ?? null) ? $checkpoint['errors'] : [];

        while ($row = R::getRow("SELECT id, device_id, external_number FROM inspection WHERE id > ? AND result_status IN ('bestanden','durchgefallen','nicht bestanden') AND TRIM(COALESCE(report_path, '')) = '' AND NOT (COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%') ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            try {
                self::renderReport($lastId, false);
                $created++;
                $message = 'Prüfbericht wurde erstellt.';
            } catch (Throwable $exception) {
                $errors[] = ['inspection_id' => $lastId, 'error' => $exception->getMessage()];
                $errors = array_slice($errors, -25);
                $message = 'Bericht konnte nicht erstellt werden; er wird in einem späteren Lauf erneut versucht.';
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'created' => $created, 'errors' => $errors];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), $message);
        }

        return ['created' => $created, 'errors' => $errors, 'processed' => $current];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function reportMigration(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $marker = app_data_root() . '/migration/inspection-reports-v2.json';
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE result_status IN ('bestanden','durchgefallen','nicht bestanden') AND NOT (COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%')");
        }

        while ($row = R::getRow("SELECT id, external_number FROM inspection WHERE id > ? AND result_status IN ('bestanden','durchgefallen','nicht bestanden') AND NOT (COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%') ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            self::renderReport($lastId, true);
            $current++;
            $created++;
            $checkpoint = ['last_id' => $lastId, 'created' => $created];
            self::writeMarker($marker, ['version' => 2, 'last_id' => $lastId, 'completed' => false, 'updated_at' => date(DATE_ATOM)]);
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), 'Prüfbericht wurde mit dem aktuellen Layout neu erzeugt.');
        }

        self::writeMarker($marker, ['version' => 2, 'last_id' => $lastId, 'completed' => true, 'completed_at' => date(DATE_ATOM), 'created' => $created]);
        return ['created' => $created, 'processed' => $current];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function restorePhoenixPdfs(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $marker = app_data_root() . '/migration/inspection-reports-phoenix-restore-v4.json';
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $restored = max(0, (int) ($checkpoint['restored'] ?? 0));
        $unresolved = max(0, (int) ($checkpoint['unresolved'] ?? 0));
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE result_status IN ('bestanden','durchgefallen','nicht bestanden') AND COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%'");
        }
        $roots = self::phoenixRoots();
        $index = self::phoenixPdfIndex($roots);

        while ($row = R::getRow("SELECT id, external_number, legacy_number, report_path FROM inspection WHERE id > ? AND result_status IN ('bestanden','durchgefallen','nicht bestanden') AND COALESCE(source_type, '') = 'json' AND COALESCE(raw_json, '') LIKE '%phoenix-sync%' ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            $source = self::findPhoenixPdf($row, $index);
            if ($source !== '') {
                $target = app_data_root() . '/reports/current/' . $lastId . '.pdf';
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0770, true);
                if ($source === $target || copy($source, $target)) {
                    $inspection = R::load('inspection', $lastId);
                    $inspection->report_path = 'reports/current/' . $lastId . '.pdf';
                    $inspection->updated_at = date(DATE_ATOM);
                    R::store($inspection);
                    $restored++;
                }
                $message = 'Phoenix-Originalbericht wurde wiederhergestellt.';
            } else {
                $unresolved++;
                $message = 'Kein Phoenix-Originalbericht gefunden; vorhandene Datei bleibt unverändert.';
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'restored' => $restored, 'unresolved' => $unresolved, 'searched_roots' => $roots];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), $message);
        }

        self::writeMarker($marker, ['completed_at' => date(DATE_ATOM), 'restored' => $restored, 'unresolved' => $unresolved, 'searched_roots' => $roots]);
        return ['restored' => $restored, 'unresolved' => $unresolved, 'processed' => $current, 'searched_roots' => $roots];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function measurementMigration(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $marker = app_data_root() . '/migration/benning-measurements-v3.done';
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $repaired = max(0, (int) ($checkpoint['repaired'] ?? 0));
        if ($total <= 0) $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type = 'csv'");

        while ($row = R::getRow("SELECT id, external_number, measurements_json, result_status FROM inspection WHERE source_type = 'csv' AND id > ? ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            $measurements = json_decode((string) ($row['measurements_json'] ?? ''), true);
            if (is_array($measurements) && $measurements !== []) {
                $normalized = InspectionController::normalizeImportedMeasurements($measurements, (string) ($row['result_status'] ?? ''));
                if ($normalized !== $measurements) {
                    R::exec('UPDATE inspection SET measurements_json = ?, updated_at = ? WHERE id = ?', [json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date(DATE_ATOM), $lastId]);
                    $repaired++;
                }
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'repaired' => $repaired];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), 'Importierte Messwerte wurden geprüft.');
        }

        self::writeMarker($marker, ['completed_at' => date(DATE_ATOM), 'stats' => ['repaired' => $repaired, 'processed' => $current]]);
        return ['repaired' => $repaired, 'processed' => $current];
    }

    private static function renderReport(int $inspectionId, bool $overwrite): void
    {
        $inspection = R::load('inspection', $inspectionId);
        $device = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
        if (!$inspection->id || !$device || !$device->id) throw new RuntimeException('Prüfung oder Gerät wurde nicht gefunden.');
        $relative = 'reports/current/' . $inspectionId . '.pdf';
        $path = app_data_root() . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
        if ($overwrite || !is_file($path)) {
            $pdf = ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . (string) $inspection->external_number, function_exists('get_report_branding') ? get_report_branding() : null);
            if (file_put_contents($path, $pdf, LOCK_EX) === false) throw new RuntimeException('PDF konnte nicht gespeichert werden.');
        }
        $inspection->report_path = $relative;
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
    }

    /** @return list<string> */
    private static function phoenixRoots(): array
    {
        $configured = trim((string) (get_app_config('phoenix_reports_directory', '') ?: get_app_config('benning_reports_directory', '') ?: getenv('PRUEFAPP_PHOENIX_REPORTS_DIR')));
        $roots = [];
        foreach ([$configured, '/var/www/berichte'] as $candidate) {
            $resolved = $candidate !== '' ? realpath($candidate) : false;
            if ($resolved !== false && is_dir($resolved) && !in_array($resolved, $roots, true)) $roots[] = $resolved;
        }
        return $roots;
    }

    /** @param list<string> $roots @return array<string,string> */
    private static function phoenixPdfIndex(array $roots): array
    {
        $index = [];
        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'pdf') continue;
                if (preg_match('/^(\d+)/', $file->getFilename(), $match)) $index[$match[1]] ??= $file->getPathname();
            }
        }
        return $index;
    }

    /** @param array<string,mixed> $row @param array<string,string> $index */
    private static function findPhoenixPdf(array $row, array $index): string
    {
        $relative = trim((string) ($row['report_path'] ?? ''));
        if ($relative !== '' && !str_starts_with($relative, 'reports/current/')) {
            $candidate = str_starts_with($relative, '/') ? $relative : app_data_root() . '/' . $relative;
            if (is_file($candidate)) return $candidate;
        }
        foreach ([(string) ($row['external_number'] ?? ''), (string) ($row['legacy_number'] ?? '')] as $number) {
            if (preg_match('/^(\d+)/', trim($number), $match) && isset($index[$match[1]])) return $index[$match[1]];
        }
        return '';
    }

    /** @param array<string,mixed> $data */
    private static function writeMarker(string $path, array $data): void
    {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
        if (file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Migrationsstand konnte nicht gespeichert werden.');
        }
    }
}
