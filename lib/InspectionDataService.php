<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Persistence facade for the canonical inspection model. */
final class InspectionDataService
{
    /** @return list<array<string,mixed>> */
    public static function answers(int $inspectionId): array
    {
        return R::getAll(
            'SELECT * FROM inspection_answer WHERE inspection_id = ? ORDER BY sort_order, id',
            [$inspectionId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function measurements(int $inspectionId): array
    {
        return R::getAll(
            'SELECT * FROM inspection_measurement WHERE inspection_id = ? ORDER BY sort_order, id',
            [$inspectionId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function diagnostics(int $inspectionId): array
    {
        return R::getAll(
            'SELECT * FROM inspection_diagnostic WHERE inspection_id = ? ORDER BY id',
            [$inspectionId]
        );
    }

    /**
     * @param list<array<string,mixed>> $answers
     */
    public static function replaceAnswers(int $inspectionId, array $answers, int $catalogVersionId): void
    {
        R::exec('DELETE FROM inspection_answer WHERE inspection_id = ?', [$inspectionId]);
        foreach (array_values($answers) as $position => $answer) {
            R::exec(
                'INSERT INTO inspection_answer '
                . '(inspection_id, catalog_version_id, item_key, category, question_snapshot, criterion_snapshot, answer_value, outcome, remark, required, skip_reason, sort_order, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $inspectionId,
                    $catalogVersionId,
                    (string) ($answer['item_key'] ?? 'answer_' . $position),
                    (string) ($answer['category'] ?? ''),
                    (string) ($answer['question_snapshot'] ?? $answer['question'] ?? ''),
                    (string) ($answer['criterion_snapshot'] ?? $answer['criterion'] ?? ''),
                    (string) ($answer['answer_value'] ?? $answer['result'] ?? ''),
                    (string) ($answer['outcome'] ?? InspectionEvaluationService::normalizeOutcome((string) ($answer['answer_value'] ?? $answer['result'] ?? ''))),
                    (string) ($answer['remark'] ?? ''),
                    !empty($answer['required']) ? 1 : 0,
                    (string) ($answer['skip_reason'] ?? ''),
                    (int) ($answer['sort_order'] ?? $position),
                    date(DATE_ATOM),
                    date(DATE_ATOM),
                ]
            );
        }
    }

    /** @param list<array<string,mixed>> $measurements */
    public static function replaceMeasurements(int $inspectionId, array $measurements, array $inspection): void
    {
        R::exec('DELETE FROM inspection_measurement WHERE inspection_id = ?', [$inspectionId]);
        foreach (array_values($measurements) as $position => $measurement) {
            $key = InspectionEvaluationService::measurementKey((string) ($measurement['measurement_key'] ?? $measurement['name'] ?? ''));
            if (!InspectionEvaluationService::isSupportedMeasurementKey($key)) {
                continue;
            }
            $evaluated = InspectionEvaluationService::evaluateMeasurement($inspection, $measurement + ['measurement_key' => $key]);
            R::exec(
                'INSERT INTO inspection_measurement '
                . '(inspection_id, measurement_key, name_snapshot, numeric_value, text_value, unit, outcome, limit_value, limit_unit, rule_key, rule_version, voltage, raw_json, sort_order, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $inspectionId,
                    $key,
                    (string) ($measurement['name_snapshot'] ?? $measurement['name'] ?? $key),
                    self::numericValue($measurement['numeric_value'] ?? $measurement['value'] ?? null),
                    (string) ($measurement['text_value'] ?? $measurement['value'] ?? ''),
                    (string) ($measurement['unit'] ?? ''),
                    $evaluated['outcome'],
                    $evaluated['limit_value'],
                    $evaluated['limit_unit'],
                    $evaluated['rule_key'],
                    '1',
                    (string) ($measurement['voltage'] ?? ''),
                    json_encode($measurement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                    (int) ($measurement['sort_order'] ?? $position),
                    date(DATE_ATOM),
                    date(DATE_ATOM),
                ]
            );
        }
    }

    /** @param array<string,mixed> $details */
    public static function addDiagnostic(
        int $inspectionId,
        string $code,
        string $message,
        string $severity = 'warning',
        array $details = []
    ): void {
        R::exec(
            'INSERT INTO inspection_diagnostic (inspection_id, code, severity, message, details_json, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $inspectionId,
                $code,
                $severity,
                $message,
                json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                date(DATE_ATOM),
            ]
        );
    }

    public static function registerReportAsset(
        int $inspectionId,
        string $assetType,
        string $path,
        bool $active
    ): void {
        $checksum = is_file($path) ? (string) hash_file('sha256', $path) : '';
        $existingId = (int) R::getCell(
            'SELECT id FROM inspection_report_asset WHERE inspection_id = ? AND asset_type = ?',
            [$inspectionId, $assetType]
        );
        if ($active) {
            R::exec('UPDATE inspection_report_asset SET active = 0 WHERE inspection_id = ?', [$inspectionId]);
        }
        if ($existingId > 0) {
            R::exec(
                'UPDATE inspection_report_asset SET path = ?, checksum = ?, active = ?, created_at = ? WHERE id = ?',
                [$path, $checksum, $active ? 1 : 0, date(DATE_ATOM), $existingId]
            );
            return;
        }
        R::exec(
            'INSERT INTO inspection_report_asset (inspection_id, asset_type, path, checksum, active, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$inspectionId, $assetType, $path, $checksum, $active ? 1 : 0, date(DATE_ATOM)]
        );
    }

    /** Returns an existing authoritative source report, if the import supplied one. */
    public static function originalReportPath(int $inspectionId): string
    {
        if ($inspectionId < 1) return '';
        $paths = R::getCol(
            "SELECT path FROM inspection_report_asset WHERE inspection_id = ? AND asset_type IN ('legacy_original', 'import_original') ORDER BY active DESC, id DESC",
            [$inspectionId]
        );
        $snapshot = R::getCell('SELECT original_report_path FROM inspection_source_snapshot WHERE inspection_id = ?', [$inspectionId]);
        if (is_string($snapshot) && trim($snapshot) !== '') $paths[] = $snapshot;
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '') continue;
            $absolute = str_starts_with($path, '/') ? $path : app_data_root() . '/' . ltrim($path, '/');
            if (is_file($absolute)) return $absolute;
        }
        return '';
    }

    /**
     * Finds the original Phoenix source report for an imported inspection.
     * This is deliberately also used while opening a report, so an original
     * PDF wins immediately even before the asynchronous restore run reaches
     * that particular inspection.
     */
    public static function locateImportedOriginalReport(\RedBeanPHP\OODBBean $inspection, ?\RedBeanPHP\OODBBean $device = null): string
    {
        if (!(int) ($inspection->id ?? 0) || !in_array((string) ($inspection->source_type ?? ''), ['csv', 'json'], true)) return '';
        $device ??= R::load('device', (int) ($inspection->device_id ?? 0));
        $numbers = [];
        foreach ([(string) ($inspection->external_number ?? ''), (string) ($inspection->legacy_number ?? ''), (string) ($device->external_number ?? '')] as $value) {
            if (preg_match('/^(\d+)/', trim($value), $match)) $numbers[$match[1]] = true;
        }
        if ($numbers === []) return '';

        $configured = trim((string) (get_app_config('phoenix_reports_directory', '') ?: get_app_config('benning_reports_directory', '') ?: getenv('PRUEFAPP_PHOENIX_REPORTS_DIR')));
        foreach (array_unique(array_filter([$configured, '/var/www/berichte'])) as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false || !is_dir($realRoot)) continue;
            foreach (array_keys($numbers) as $number) {
                foreach (glob($realRoot . '/' . $number . '*.pdf') ?: [] as $candidate) {
                    if (is_file($candidate)) return $candidate;
                }
            }
        }
        return '';
    }

    /** Activates a located source PDF and returns its absolute path. */
    public static function activateImportedOriginalReport(\RedBeanPHP\OODBBean $inspection, ?\RedBeanPHP\OODBBean $device = null): string
    {
        $path = self::originalReportPath((int) ($inspection->id ?? 0));
        if ($path === '') $path = self::locateImportedOriginalReport($inspection, $device);
        if ($path === '' && class_exists(PhoenixSyncService::class)) {
            $device ??= R::load('device', (int) ($inspection->device_id ?? 0));
            if ((int) ($device->id ?? 0) > 0) $path = (new PhoenixSyncService())->downloadOriginalReportForInspection($inspection, $device);
        }
        if ($path === '') return '';
        self::registerReportAsset((int) $inspection->id, 'import_original', $path, true);
        if ((string) ($inspection->report_path ?? '') !== $path) {
            $inspection->report_path = $path;
            $inspection->updated_at = date(DATE_ATOM);
            R::store($inspection);
        }
        return $path;
    }

    /** @param mixed $value */
    private static function numericValue($value): ?float
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || preg_match('/[-+]?(?:\d+(?:\.\d+)?|\.\d+)/', $text, $match) !== 1) {
            return null;
        }
        return (float) $match[0];
    }
}
