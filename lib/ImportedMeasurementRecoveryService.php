<?php

declare(strict_types=1);

require_once __DIR__ . '/ElectricalInspectionImportService.php';
require_once __DIR__ . '/InspectionEvaluationService.php';
require_once __DIR__ . '/InspectionDataService.php';

/** Reads preserved source facts; never changes an inspection's result or identity. */
final class ImportedMeasurementRecoveryService
{
    public static function plan(array $inspection): array
    {
        $raw = json_decode((string) ($inspection['raw_json'] ?? ''), true);
        if (!is_array($raw)) return ['status' => 'no_source'];
        $csv = (new ElectricalInspectionImportService(sys_get_temp_dir()))->measurementsFromStoredCsv($raw);
        if ($csv !== null) {
            $date = (string) $csv['test_date'];
            $slot = ltrim(trim((string) $csv['storage_slot']), '0');
            $targetSlot = ltrim(trim((string) ($inspection['storage_slot'] ?? '')), '0');
            if ($date !== (string) ($inspection['test_date'] ?? '') || self::date((string) ($csv['source_date_raw'] ?? '')) !== $date || $slot === '' || $slot !== $targetSlot) {
                return ['status' => 'identity_conflict'];
            }
            $readings = $csv['measurements'];
            $kind = 'benning_csv';
        } else {
            if (isset($raw['header'], $raw['row'])) return ['status' => 'unreadable_csv'];
            $sourceDate = (string) ($raw['date'] ?? $raw['test_date'] ?? '');
            $date = self::date($sourceDate);
            if ($date === '' || $date !== (string) ($inspection['test_date'] ?? '')) return ['status' => 'identity_conflict'];
            $readings = self::readingsFromPhoenix($raw);
            $kind = 'phoenix_questions';
        }
        return [
            'status' => $readings === [] ? 'no_readings' : 'ready',
            'source_kind' => $kind,
            'source_date' => $date,
            'raw_sha256' => hash('sha256', (string) $inspection['raw_json']),
            'measurements' => $readings,
        ];
    }

    /** Apply one reviewed plan after backing up its current fields. Existing readings win. */
    public static function apply(int $id, array $plan, callable $backup): array
    {
        \RedBeanPHP\R::begin();
        try {
            $inspection = \RedBeanPHP\R::load('inspection', $id);
            if (!$inspection->id) throw new RuntimeException('Prüfung fehlt: ' . $id);
            $before = $inspection->export();
            if (\RedBeanPHP\R::count('inspection_measurement', 'inspection_id=?', [$id]) > 0
                || !in_array(trim((string) ($before['measurements_json'] ?? '')), ['', '[]', '{}', 'null'], true)) {
                \RedBeanPHP\R::rollback();
                return ['id' => $id, 'status' => 'already_present'];
            }
            $fresh = self::plan($before);
            if (($fresh['status'] ?? '') !== 'ready' || ($plan['raw_sha256'] ?? '') !== $fresh['raw_sha256']
                || ($plan['measurements'] ?? []) !== $fresh['measurements']
                || !in_array((string) ($before['source_type'] ?? ''), ['reconciled', 'csv', 'json', 'phoenix'], true)
                || trim((string) ($before['archived_at'] ?? '')) !== '') throw new RuntimeException('Quell- oder Zuordnungsdaten geändert: ' . $id);
            $assets = \RedBeanPHP\R::getAll("SELECT id,path,active FROM inspection_report_asset WHERE inspection_id=? AND asset_type='generated'", [$id]);
            $backup($before, $assets);
            $path = (string) ($before['report_path'] ?? '');
            $newPath = str_starts_with($path, 'reports/current/') ? '' : $path;
            $changed = \RedBeanPHP\R::exec(
                "UPDATE inspection SET measurements_json=?,report_path=?,updated_at=? WHERE id=? AND raw_json=? AND COALESCE(measurements_json,'')=? AND COALESCE(updated_at,'')=?",
                [json_encode($fresh['measurements'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $newPath, date(DATE_ATOM), $id, (string) $before['raw_json'], (string) ($before['measurements_json'] ?? ''), (string) ($before['updated_at'] ?? '')]
            );
            if ($changed !== 1) throw new RuntimeException('Prüfung wurde parallel geändert: ' . $id);
            InspectionDataService::replaceMeasurements($id, $fresh['measurements'], $before);
            \RedBeanPHP\R::exec("UPDATE inspection_report_asset SET active=0 WHERE inspection_id=? AND asset_type='generated'", [$id]);
            InspectionDataService::addDiagnostic($id, 'source_measurements_recovered', 'Vorhandene Originalmesswerte in die Messwertfelder übernommen.', 'info', ['source_kind' => $fresh['source_kind'], 'raw_sha256' => $fresh['raw_sha256'], 'count' => count($fresh['measurements'])]);
            \RedBeanPHP\R::commit();
            return ['id' => $id, 'status' => 'restored', 'measurements' => count($fresh['measurements']), 'source_kind' => $fresh['source_kind']];
        } catch (Throwable $error) {
            \RedBeanPHP\R::rollback();
            throw $error;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function readingsFromPhoenix(array $raw): array
    {
        $steps = [];
        if (is_array($raw['checklist'] ?? null)) {
            foreach ($raw['checklist'] as $step) if (is_array($step)) $steps[] = $step;
        }
        if ($steps === []) {
            foreach ($raw as $key => $question) {
                if (preg_match('/^step(\d+)$/', (string) $key, $match) && is_scalar($question)) {
                    $index = $match[1];
                    $steps[] = ['step' => (string) $question, 'result' => $raw['result' . $index] ?? '', 'criterion' => $raw['criterion' . $index] ?? ''];
                }
            }
        }
        $readings = [];
        foreach ($steps as $step) {
            if (!is_scalar($step['step'] ?? null) || !is_scalar($step['result'] ?? null)) continue;
            $question = (string) $step['step'];
            $value = trim((string) $step['result']);
            if (preg_match('/^([<>≤≥=]*\s*[-+]?\d+(?:[.,]\d+)?)(?:\s*(mA|M?\s*(?:Ohm|Ω)))?$/iu', $value, $valueMatch) !== 1) continue;
            $sourceValue = $value;
            $value = trim($valueMatch[1]);
            $normalized = mb_strtoupper(preg_replace('/[\s_-]+/u', '', $question));
            $key = match (true) {
                str_contains($normalized, 'RISO'), str_contains($normalized, 'ISOLATIONSWIDERSTAND') => 'RISO',
                str_contains($normalized, 'RSL') => 'RSL',
                str_contains($normalized, 'RPE'), str_contains($normalized, 'SCHUTZLEITERWIDERSTAND') => 'RPE',
                str_contains($normalized, 'IEA'), str_contains($normalized, 'ERSATZABLEITSTROM') => 'IEA',
                str_contains($normalized, 'BERÜHRUNGSSTROM'), preg_match('/\bI\s*[_-]?\s*B(?:ER)?\b/iu', $question) === 1 => 'IBER',
                str_contains($normalized, 'IPE'), str_contains($normalized, 'SCHUTZLEITERSTROM') => 'IPE',
                default => '',
            };
            if ($key === '') continue;
            $criterion = is_scalar($step['criterion'] ?? '') ? (string) ($step['criterion'] ?? '') : '';
            $unit = trim((string) (($step['unit'] ?? '') ?: ($valueMatch[2] ?? '')));
            if ($unit === '') {
                $unitText = $question . ' ' . $criterion;
                if ($key === 'RISO' && preg_match('/M\s*(?:Ohm|Ω)/iu', $unitText)) $unit = 'MΩ';
                elseif (in_array($key, ['RPE','RSL'], true) && preg_match('/(?:Ohm|Ω)/iu', $unitText)) $unit = 'Ω';
                elseif (in_array($key, ['IPE','IBER','IEA'], true) && preg_match('/mA/iu', $unitText)) $unit = 'mA';
            }
            if ($unit === '') continue;
            $result = array_key_exists('check', $step) && is_bool($step['check']) ? ($step['check'] ? 'bestanden' : 'nicht bestanden') : '';
            $readings[] = ['name' => $key, 'value' => $value, 'unit' => $unit, 'result' => $result, 'source_question' => $question, 'source_criterion' => $criterion, 'source_value' => $sourceValue];
        }
        return $readings;
    }

    private static function date(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]|$)/', $value)) $value = substr($value, 0, 10);
        foreach (['!Y-m-d','!d/m/Y','!d.m.Y','!d.m.y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d');
        }
        return '';
    }
}
