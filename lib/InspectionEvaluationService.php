<?php

declare(strict_types=1);

/**
 * Single backend authority for inspection applicability, limits and results.
 *
 * Templates may display values returned by this service, but must never
 * reproduce the decisions contained here.
 */
final class InspectionEvaluationService
{
    public const DATA_MISSING = 'data_missing';
    public const IN_PROGRESS = 'in_progress';
    public const PASSED = 'passed';
    public const FAILED = 'failed';

    /** @return array<string,array{label:string,class:string,icon:string}> */
    public static function statuses(): array
    {
        return [
            self::DATA_MISSING => ['label' => 'Daten fehlen', 'class' => 'warning text-dark', 'icon' => 'fa-circle-exclamation'],
            self::IN_PROGRESS => ['label' => 'In Bearbeitung', 'class' => 'info text-dark', 'icon' => 'fa-hourglass-half'],
            self::PASSED => ['label' => 'Bestanden', 'class' => 'success', 'icon' => 'fa-circle-check'],
            self::FAILED => ['label' => 'Nicht bestanden', 'class' => 'danger', 'icon' => 'fa-circle-xmark'],
        ];
    }

    public static function normalizeStatus(?string $status, ?string $workflowStatus = null): string
    {
        $normalized = strtolower(trim((string) $status));
        if (in_array($normalized, [self::DATA_MISSING, self::IN_PROGRESS, self::PASSED, self::FAILED], true)) {
            return $normalized;
        }
        if (in_array($normalized, ['bestanden', 'ok', 'gut', 'success', 'successful'], true)) {
            return self::PASSED;
        }
        if (in_array($normalized, ['durchgefallen', 'nicht bestanden', 'failed', 'failed_test', 'nok', 'nein'], true)) {
            return self::FAILED;
        }
        $workflow = strtolower(trim((string) $workflowStatus));
        if (in_array($normalized, ['ausstehend', 'offen', 'pending'], true)
            || in_array($workflow, ['draft', 'measurement_pending', 'in_progress'], true)
        ) {
            return self::IN_PROGRESS;
        }
        return self::DATA_MISSING;
    }

    /** Transitional SQL projection used while the resumable migration is running. */
    public static function sqlStatusExpression(string $alias = 'i'): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alias) !== 1) {
            throw new InvalidArgumentException('Ungültiger SQL-Alias für den Prüfstatus.');
        }
        return "CASE "
            . "WHEN {$alias}.result_status IN ('passed','bestanden','ok','gut') THEN 'passed' "
            . "WHEN {$alias}.result_status IN ('failed','durchgefallen','nicht bestanden','failed_test','nok') THEN 'failed' "
            . "WHEN {$alias}.result_status IN ('in_progress','ausstehend','offen','pending') OR {$alias}.status IN ('draft','measurement_pending','in_progress') THEN 'in_progress' "
            . "ELSE 'data_missing' END";
    }

    /** @return array{label:string,class:string,icon:string,status:string} */
    public static function presentation(?string $status, ?string $workflowStatus = null): array
    {
        $canonical = self::normalizeStatus($status, $workflowStatus);
        return self::statuses()[$canonical] + ['status' => $canonical];
    }

    public static function isCompleted(?string $status): bool
    {
        return in_array(self::normalizeStatus($status), [self::PASSED, self::FAILED], true);
    }

    public static function reportAllowed(?string $status, string $classification = ''): bool
    {
        if (strtolower(trim($classification)) === 'legacy') {
            return self::isCompleted($status);
        }
        return self::isCompleted($status);
    }

    public static function reportPathAllowed(
        ?string $status,
        string $classification,
        string $path,
        bool $allowImportedOriginal = false
    ): bool {
        $path = trim($path);
        if ($path === '' || !self::reportAllowed($status, $classification)) return false;
        if (strtolower(trim($classification)) !== 'migrated_import') return true;
        return $allowImportedOriginal || str_starts_with(ltrim($path, '/'), 'reports/current/');
    }

    public static function rslLimit(?float $cableLength): float
    {
        if ($cableLength === null || $cableLength <= 5.0) {
            return 0.3;
        }
        $steps = (int) ceil(($cableLength - 5.0) / 7.5);
        return min(1.0, 0.3 + ($steps * 0.1));
    }

    /** @return list<string> */
    public static function requiredMeasurementKeys(string $protectionClass): array
    {
        return match (self::normalizeProtectionClass($protectionClass)) {
            'I', 'DREHSTROM' => ['RPE', 'RISO', 'IPE'],
            'II' => ['RISO', 'IBER'],
            'KABEL' => ['RPE', 'RISO'],
            'III' => [],
            default => [],
        };
    }

    public static function normalizeProtectionClass(string $protectionClass): string
    {
        $value = strtoupper(trim($protectionClass));
        if (str_contains($value, 'KABEL')) return 'KABEL';
        if (str_contains($value, 'DREHSTROM')) return 'DREHSTROM';
        if (preg_match('/(?:SCHUTZ)?KLASSE\s*III\b|\bSK\s*3\b/', $value) === 1) return 'III';
        if (preg_match('/(?:SCHUTZ)?KLASSE\s*II\b|\bSK\s*2\b/', $value) === 1) return 'II';
        if (preg_match('/(?:SCHUTZ)?KLASSE\s*I\b|\bSK\s*1\b/', $value) === 1) return 'I';
        return in_array($value, ['I', 'II', 'III'], true) ? $value : $value;
    }

    /**
     * @param array<string,mixed> $inspection
     * @param list<array<string,mixed>> $answers
     * @param list<array<string,mixed>> $measurements
     * @return array{status:string,reason_code:string,reason:string,missing:list<string>,failed:list<string>}
     */
    public static function evaluate(
        array $inspection,
        array $answers,
        array $measurements,
        bool $completionRequested
    ): array {
        if (!$completionRequested) {
            return self::result(self::IN_PROGRESS, 'editing', 'Die Prüfung ist noch in Bearbeitung.');
        }

        $failed = [];
        $missing = [];
        $requiredAnswerCount = 0;
        foreach ($answers as $answer) {
            $outcome = self::normalizeOutcome((string) ($answer['outcome'] ?? $answer['answer_value'] ?? ''));
            $label = trim((string) ($answer['question_snapshot'] ?? $answer['item_key'] ?? 'Prüffrage'));
            if (!empty($answer['required'])) {
                $requiredAnswerCount++;
            }
            if ($outcome === 'failed') {
                $failed[] = $label;
            } elseif (!empty($answer['required']) && !in_array($outcome, ['passed', 'skipped'], true)) {
                $missing[] = $label;
            }
        }
        if ($requiredAnswerCount === 0) {
            $missing[] = 'Erforderliche Prüffragen';
        }

        $measurementMap = [];
        foreach ($measurements as $measurement) {
            $key = self::measurementKey((string) ($measurement['measurement_key'] ?? $measurement['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $evaluated = self::evaluateMeasurement($inspection, $measurement + ['measurement_key' => $key]);
            $measurementMap[$key] = $evaluated;
            if ($evaluated['outcome'] === 'failed') {
                $failed[] = $evaluated['label'];
            }
        }

        if ($failed !== []) {
            return self::result(
                self::FAILED,
                'required_check_failed',
                'Mindestens eine erforderliche Prüfung wurde nicht bestanden.',
                $missing,
                array_values(array_unique($failed))
            );
        }

        foreach (self::requiredMeasurementKeys((string) ($inspection['protection_class'] ?? '')) as $requiredKey) {
            if (!isset($measurementMap[$requiredKey]) || $measurementMap[$requiredKey]['outcome'] !== 'passed') {
                $missing[] = 'Messung ' . self::measurementLabel($requiredKey);
            }
        }

        foreach (['protection_class' => 'Schutzklasse', 'examiner' => 'Prüfer', 'test_date' => 'Prüfdatum'] as $field => $label) {
            if (trim((string) ($inspection[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        $missing = array_values(array_unique($missing));
        if ($missing !== []) {
            return self::result(
                self::DATA_MISSING,
                'required_data_missing',
                'Erforderliche Prüfungsdaten fehlen oder sind nicht eindeutig auswertbar.',
                $missing
            );
        }

        return self::result(self::PASSED, 'all_required_checks_passed', 'Alle anwendbaren erforderlichen Prüfungen wurden bestanden.');
    }

    /**
     * @param array<string,mixed> $inspection
     * @param array<string,mixed> $measurement
     * @return array{key:string,label:string,outcome:string,limit_value:?float,limit_unit:string,rule_key:string,reason:string}
     */
    public static function evaluateMeasurement(array $inspection, array $measurement): array
    {
        $key = self::measurementKey((string) ($measurement['measurement_key'] ?? $measurement['name'] ?? ''));
        $label = self::measurementLabel($key);
        $explicit = self::normalizeOutcome((string) ($measurement['outcome'] ?? $measurement['result'] ?? ''));
        $value = self::numericValue($measurement['numeric_value'] ?? $measurement['value'] ?? null);
        $warming = !empty($inspection['warming_device_snapshot']) || !empty($inspection['warming_device']);
        $limit = null;
        $unit = '';
        $rule = '';
        $passedByValue = null;

        if (in_array($key, ['RPE', 'RSL'], true)) {
            $limit = self::rslLimit(self::numericValue($inspection['cable_length_m'] ?? null));
            $unit = 'Ω';
            $rule = 'rsl_by_cable_length_v1';
            $passedByValue = $value !== null ? $value <= $limit : null;
        } elseif ($key === 'RISO') {
            $limit = $warming ? 0.3 : 1.0;
            $unit = 'MΩ';
            $rule = 'riso_heating_v1';
            $passedByValue = $value !== null ? $value >= $limit : null;
        } elseif ($key === 'IPE') {
            $limit = 3.5;
            $unit = 'mA';
            $rule = 'ipe_v1';
            $passedByValue = $value !== null ? $value <= $limit : null;
        } elseif ($key === 'IBER') {
            $limit = 0.5;
            $unit = 'mA';
            $rule = 'iber_v1';
            $passedByValue = $value !== null ? $value <= $limit : null;
        }

        $outcome = $explicit;
        if ($passedByValue !== null) {
            $outcome = $passedByValue ? 'passed' : 'failed';
        }
        if (!in_array($outcome, ['passed', 'failed'], true)) {
            $outcome = 'missing';
        }

        return [
            'key' => $key,
            'label' => $label,
            'outcome' => $outcome,
            'limit_value' => $limit,
            'limit_unit' => $unit,
            'rule_key' => $rule,
            'reason' => $outcome === 'missing' ? 'Kein eindeutig auswertbarer Messwert vorhanden.' : '',
        ];
    }

    public static function normalizeOutcome(string $value): string
    {
        $value = strtolower(trim($value));
        return match (true) {
            in_array($value, ['passed', 'bestanden', 'ja', 'ok', 'gut', 'true', '1'], true) => 'passed',
            in_array($value, ['failed', 'durchgefallen', 'nicht bestanden', 'nein', 'nok', 'false', '0'], true) => 'failed',
            in_array($value, ['skipped', 'uebersprungen', 'übersprungen', 'nicht durchgeführt'], true) => 'skipped',
            default => 'missing',
        };
    }

    public static function measurementKey(string $name): string
    {
        $key = strtoupper(trim($name));
        $key = str_replace([' ', '-', '_'], '', $key);
        return match ($key) {
            'RPE', 'RSL', 'SCHUTZLEITERWIDERSTAND' => $key === 'RSL' ? 'RSL' : 'RPE',
            'RISO', 'ISOLATIONSWIDERSTAND' => 'RISO',
            'IPE', 'SCHUTZLEITERSTROM' => 'IPE',
            'IB', 'IBER', 'BERUEHRUNGSSTROM', 'BERÜHRUNGSSTROM' => 'IBER',
            'IEA', 'ERSATZABLEITSTROM' => 'IEA',
            'FI/RCD', 'FIRCD', 'RCD' => 'FI/RCD',
            default => $key,
        };
    }

    private static function measurementLabel(string $key): string
    {
        return match ($key) {
            'RPE', 'RSL' => 'Schutzleiterwiderstand ' . $key,
            'RISO' => 'Isolationswiderstand RISO',
            'IPE' => 'Schutzleiterstrom IPE',
            'IBER' => 'Berührungsstrom IB',
            default => $key !== '' ? $key : 'Messwert',
        };
    }

    /** @param mixed $value */
    private static function numericValue($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $text = str_replace(',', '.', $text);
        if (preg_match('/[-+]?(?:\d+(?:\.\d+)?|\.\d+)/', $text, $match) !== 1) {
            return null;
        }
        return (float) $match[0];
    }

    /**
     * @param list<string> $missing
     * @param list<string> $failed
     * @return array{status:string,reason_code:string,reason:string,missing:list<string>,failed:list<string>}
     */
    private static function result(
        string $status,
        string $code,
        string $reason,
        array $missing = [],
        array $failed = []
    ): array {
        return [
            'status' => $status,
            'reason_code' => $code,
            'reason' => $reason,
            'missing' => $missing,
            'failed' => $failed,
        ];
    }
}
