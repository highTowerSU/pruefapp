<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Idempotent row-by-row migration from legacy JSON fields to canonical data. */
final class InspectionMigrationService
{
    /** @return array<string,mixed> */
    public static function migrate(int $inspectionId): array
    {
        $inspection = R::load('inspection', $inspectionId);
        if (!$inspection->id) {
            throw new InvalidArgumentException('Prüfung wurde nicht gefunden.');
        }
        $row = R::getRow('SELECT * FROM inspection WHERE id = ?', [$inspectionId]);
        $classification = self::classification($row);
        $attributedExaminer = self::attributedExaminer($row);
        if ($attributedExaminer !== '') {
            $row['examiner'] = $attributedExaminer;
        }

        R::begin();
        try {
            self::backup($row, $classification);
            self::registerOriginalReport($row, $classification);
            R::exec('DELETE FROM inspection_diagnostic WHERE inspection_id = ?', [$inspectionId]);

            $oldStatus = InspectionEvaluationService::normalizeStatus(
                (string) ($row['result_status'] ?? ''),
                (string) ($row['status'] ?? '')
            );
            $sourceStatus = self::sourceStatus($row, $classification);
            if ($classification === 'migrated_import'
                && $oldStatus === InspectionEvaluationService::DATA_MISSING
                && in_array($sourceStatus, [InspectionEvaluationService::PASSED, InspectionEvaluationService::FAILED], true)
            ) {
                // A previous migration may have marked a completed import as
                // incomplete solely because old source formats did not carry
                // every individual checklist answer. Preserve the verified
                // source result from the immutable snapshot during reruns.
                $oldStatus = $sourceStatus;
            }
            $catalogId = self::activeCatalogId();
            $result = [
                'status' => $oldStatus,
                'reason_code' => '',
                'reason' => '',
                'missing' => [],
                'failed' => [],
            ];

            if ($classification === 'legacy') {
                R::exec('DELETE FROM inspection_answer WHERE inspection_id = ?', [$inspectionId]);
                R::exec('DELETE FROM inspection_measurement WHERE inspection_id = ?', [$inspectionId]);
                if (!InspectionEvaluationService::isCompleted($oldStatus)) {
                    $result['status'] = InspectionEvaluationService::DATA_MISSING;
                    $result['reason_code'] = 'legacy_result_unknown';
                    $result['reason'] = 'Das Ergebnis des Legacy-Berichts ist nicht eindeutig hinterlegt.';
                } else {
                    $result['reason_code'] = 'legacy_original';
                    $result['reason'] = 'Ergebnis und Original-PDF werden unverändert aus dem Legacy-Bestand verwendet.';
                }
            } elseif (trim((string) ($row['test_date'] ?? '')) === '') {
                $result['status'] = InspectionEvaluationService::DATA_MISSING;
                $result['reason_code'] = 'test_date_missing';
                $result['reason'] = 'Das Prüfdatum fehlt; der Datensatz kann nicht als Legacy oder aktuelle Prüfung eingeordnet werden.';
                InspectionDataService::addDiagnostic($inspectionId, 'test_date_missing', $result['reason']);
            } else {
                $answers = self::answers($row, $catalogId, $oldStatus, $classification);
                $measurements = self::measurements($row);
                InspectionDataService::replaceAnswers($inspectionId, $answers, $catalogId);
                InspectionDataService::replaceMeasurements($inspectionId, $measurements, self::evaluationInput($row));
                $canonicalAnswers = InspectionDataService::answers($inspectionId);
                $canonicalMeasurements = InspectionDataService::measurements($inspectionId);
                $complete = !in_array($oldStatus, [InspectionEvaluationService::IN_PROGRESS], true);
                $result = InspectionEvaluationService::evaluate(
                    self::evaluationInput($row),
                    $canonicalAnswers,
                    $canonicalMeasurements,
                    $complete
                );
                foreach ($result['missing'] as $missing) {
                    InspectionDataService::addDiagnostic(
                        $inspectionId,
                        'required_data_missing',
                        (string) $missing,
                        'warning',
                        ['source_file' => (string) ($row['source_file'] ?? '')]
                    );
                }
            }

            $cableLength = self::numeric($row['cable_length_m'] ?? null);
            $warmingDevice = (int) ($row['warming_device_snapshot'] ?? 0);
            if ($classification === 'migrated_import' && $warmingDevice === 0) {
                $warmingDevice = self::deviceWarming((int) $row['device_id']);
            }
            $activeReportPath = trim((string) ($row['report_path'] ?? ''));
            if ($classification === 'migrated_import'
                && !InspectionEvaluationService::reportPathAllowed($oldStatus, $classification, $activeReportPath)
            ) {
                $activeReportPath = '';
            }
            R::exec(
                'UPDATE inspection SET classification = ?, catalog_version_id = ?, examiner = ?, result_status = ?, result_reason_code = ?, result_reason_text = ?, warming_device_snapshot = ?, cable_length_m = ?, rsl_limit_ohm = ?, report_path = ?, status = ?, updated_at = ? WHERE id = ?',
                [
                    $classification,
                    $classification === 'legacy' ? null : $catalogId,
                    (string) ($row['examiner'] ?? ''),
                    $result['status'],
                    $result['reason_code'],
                    $result['reason'],
                    $warmingDevice,
                    $cableLength,
                    InspectionEvaluationService::rslLimit($cableLength),
                    $activeReportPath,
                    InspectionEvaluationService::isCompleted($result['status']) ? 'completed' : $result['status'],
                    date(DATE_ATOM),
                    $inspectionId,
                ]
            );
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }

        return [
            'inspection_id' => $inspectionId,
            'classification' => $classification,
            'status' => $result['status'],
            'reason' => $result['reason'],
            'missing' => $result['missing'],
        ];
    }

    /**
     * Imported Benning/Phoenix rows sometimes lack the examiner field. Keep
     * the documented historic attribution in one backend migration rule so an
     * otherwise complete import does not become falsely incomplete.
     *
     * @param array<string,mixed> $row
     */
    private static function attributedExaminer(array $row): string
    {
        $examiner = trim((string) ($row['examiner'] ?? ''));
        $placeholder = strtolower($examiner);
        $needsAttribution = $examiner === '' || $examiner === '—' || in_array($placeholder, ['info@ceneos.net', 'info@ceneos.de'], true);
        if (!$needsAttribution) return $examiner;
        if (!in_array((string) ($row['source_type'] ?? ''), ['csv', 'json'], true)) return $examiner;
        $year = (int) substr(trim((string) ($row['test_date'] ?? '')), 0, 4);
        if (in_array($year, [2023, 2024], true)) return 'bdebertshaeuser@koenigsbl.au';
        return $year >= 2025 ? 'edebertshaeuser@koenigsbl.au' : $examiner;
    }

    /** @param array<string,mixed> $row */
    public static function classification(array $row): string
    {
        $date = trim((string) ($row['test_date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date <= '2024-12-31') {
            return 'legacy';
        }
        return strtolower(trim((string) ($row['source_type'] ?? ''))) === 'manual' ? 'native' : 'migrated_import';
    }

    /** @param array<string,mixed> $row */
    private static function sourceStatus(array $row, string $classification): string
    {
        if ($classification !== 'migrated_import') {
            return InspectionEvaluationService::normalizeStatus((string) ($row['result_status'] ?? ''), (string) ($row['status'] ?? ''));
        }
        $snapshot = R::getCell('SELECT legacy_row_json FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) ($row['id'] ?? 0)]);
        $original = json_decode((string) $snapshot, true);
        if (is_array($original)) {
            $status = InspectionEvaluationService::normalizeStatus(
                (string) ($original['result_status'] ?? ''),
                (string) ($original['status'] ?? '')
            );
            if ($status !== InspectionEvaluationService::DATA_MISSING) return $status;
            $fromSource = self::statusFromRawSource($original);
            if ($fromSource !== InspectionEvaluationService::DATA_MISSING) return $fromSource;
        }
        $status = InspectionEvaluationService::normalizeStatus((string) ($row['result_status'] ?? ''), (string) ($row['status'] ?? ''));
        return $status !== InspectionEvaluationService::DATA_MISSING ? $status : self::statusFromRawSource($row);
    }

    /** @param array<string,mixed> $source */
    private static function statusFromRawSource(array $source): string
    {
        if (array_key_exists('audit_ok', $source) && is_bool($source['audit_ok'])) {
            return $source['audit_ok'] ? InspectionEvaluationService::PASSED : InspectionEvaluationService::FAILED;
        }
        foreach (['Prüfergebnis', 'pruefergebnis', 'result', 'Ergebnis', 'ergebnis'] as $key) {
            if (!array_key_exists($key, $source) || is_array($source[$key])) continue;
            $status = InspectionEvaluationService::normalizeStatus((string) $source[$key]);
            if ($status !== InspectionEvaluationService::DATA_MISSING) return $status;
        }
        foreach (['raw_json', 'csv_row_json', 'source_row_json'] as $key) {
            $raw = json_decode((string) ($source[$key] ?? ''), true);
            if (!is_array($raw)) continue;
            $status = self::statusFromRawSource($raw);
            if ($status !== InspectionEvaluationService::DATA_MISSING) return $status;
        }
        return InspectionEvaluationService::DATA_MISSING;
    }

    /** @param array<string,mixed> $row */
    private static function backup(array $row, string $classification): void
    {
        if ((int) R::getCell('SELECT COUNT(*) FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) $row['id']]) > 0) {
            // A previous broad migration may have captured the source before
            // its historical classification was known. Keep the immutable
            // source snapshot, but correct its searchable classification.
            if ($classification === 'legacy') {
                R::exec(
                    "UPDATE inspection_source_snapshot SET classification = 'legacy' WHERE inspection_id = ?",
                    [(int) $row['id']]
                );
            }
            return;
        }
        $sourceRow = trim((string) ($row['csv_row_json'] ?? ''));
        if ($sourceRow === '') {
            $sourceRow = trim((string) ($row['raw_json'] ?? '')) ?: '{}';
        }
        $report = self::reportPath((string) ($row['report_path'] ?? ''));
        R::exec(
            'INSERT INTO inspection_source_snapshot (inspection_id, classification, source_type, source_file, source_row_json, legacy_row_json, original_report_path, original_report_checksum, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $row['id'],
                $classification,
                (string) ($row['source_type'] ?? ''),
                (string) ($row['source_file'] ?? ''),
                $sourceRow,
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                $report,
                $report !== '' && is_file($report) ? hash_file('sha256', $report) : '',
                date(DATE_ATOM),
            ]
        );
    }

    /** @param array<string,mixed> $row */
    private static function registerOriginalReport(array $row, string $classification): void
    {
        $path = self::reportPath((string) ($row['report_path'] ?? ''));
        if ($path === '') return;
        $type = $classification === 'legacy' ? 'legacy_original' : 'import_original';
        InspectionDataService::registerReportAsset((int) $row['id'], $type, $path, $classification === 'legacy');
    }

    /** @param array<string,mixed> $row @return list<array<string,mixed>> */
    private static function answers(array $row, int $catalogId, string $oldStatus, string $classification): array
    {
        $catalog = R::getAll("SELECT * FROM inspection_catalog_item WHERE version_id = ? AND input_type = 'boolean' ORDER BY sort_order, id", [$catalogId]);
        $checklist = json_decode((string) ($row['checklist_json'] ?? ''), true);
        $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
        if (!is_array($checklist)) $checklist = [];
        if (!is_array($raw)) $raw = [];
        $mapped = [];
        $manualMap = [
            'stecker' => 'identification',
            'label' => 'visual_label',
            'leitung' => 'visual_cable',
            'gehaeuse' => 'visual_housing',
            'funktion' => 'function',
            'safe_operation' => 'safe_operation',
            'customer_notice' => 'customer_notice',
        ];
        foreach ($manualMap as $source => $target) {
            if (array_key_exists($source, $checklist)) $mapped[$target] = (string) $checklist[$source];
        }
        if (array_is_list($checklist)) {
            foreach (array_values($manualMap) as $index => $target) {
                if (array_key_exists($index, $checklist) && !is_array($checklist[$index])) $mapped[$target] = (string) $checklist[$index];
            }
        }
        foreach ($raw as $key => $question) {
            if (preg_match('/^step(\d+)$/', (string) $key, $match) !== 1) continue;
            $itemKey = self::questionKey((string) $question);
            if ($itemKey !== '') $mapped[$itemKey] = (string) ($raw['result' . $match[1]] ?? '');
        }
        $sourcePassed = ($raw['audit_ok'] ?? null) === true;
        $answers = [];
        foreach ($catalog as $item) {
            $value = trim((string) ($mapped[$item['item_key']] ?? ''));
            $outcome = InspectionEvaluationService::normalizeOutcome($value);
            if ($outcome === 'missing'
                && $oldStatus === InspectionEvaluationService::PASSED
                && ($sourcePassed || $classification === 'migrated_import')
            ) {
                $value = 'Aus dem abgeschlossenen Quellsystem als bestanden überliefert';
                $outcome = 'passed';
            }
            $answers[] = [
                'item_key' => $item['item_key'],
                'category' => $item['category'],
                'question_snapshot' => $item['question'],
                'criterion_snapshot' => $item['criterion'],
                'answer_value' => $value,
                'outcome' => $outcome,
                'required' => (int) $item['required'],
                'sort_order' => (int) $item['sort_order'],
            ];
        }
        if ($oldStatus === InspectionEvaluationService::FAILED
            && !array_filter($answers, static fn(array $answer): bool => $answer['outcome'] === 'failed')
        ) {
            $answers[] = [
                'item_key' => 'imported_overall_failure',
                'category' => 'Import',
                'question_snapshot' => 'Im Quellsystem dokumentiertes Gesamtergebnis',
                'criterion_snapshot' => 'Das ursprüngliche negative Ergebnis bleibt erhalten.',
                'answer_value' => 'Nicht bestanden',
                'outcome' => 'failed',
                'required' => 1,
                'sort_order' => 999,
            ];
        }
        return $answers;
    }

    /** @param array<string,mixed> $row @return list<array<string,mixed>> */
    private static function measurements(array $row): array
    {
        $measurements = json_decode((string) ($row['measurements_json'] ?? ''), true);
        if (!is_array($measurements)) return [];
        $normalized = [];
        foreach ($measurements as $position => $measurement) {
            if (!is_array($measurement)) continue;
            $measurement['sort_order'] = $position;
            $normalized[] = $measurement;
        }
        return $normalized;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function evaluationInput(array $row): array
    {
        $sourceType = strtolower(trim((string) ($row['source_type'] ?? '')));
        if (!array_key_exists('warming_device_snapshot', $row)
            || $row['warming_device_snapshot'] === null
            || ($sourceType !== 'manual' && (int) $row['warming_device_snapshot'] === 0)
        ) {
            $row['warming_device_snapshot'] = self::deviceWarming((int) ($row['device_id'] ?? 0));
        }
        return $row;
    }

    private static function activeCatalogId(): int
    {
        $id = (int) R::getCell('SELECT id FROM inspection_catalog_version WHERE active = 1 ORDER BY id DESC LIMIT 1');
        if ($id <= 0) throw new RuntimeException('Kein aktiver Prüfungskatalog vorhanden.');
        return $id;
    }

    private static function questionKey(string $question): string
    {
        $question = mb_strtolower($question);
        return match (true) {
            str_contains($question, 'beschrift') => 'visual_label',
            str_contains($question, 'anschlu') || str_contains($question, 'leitung') => 'visual_cable',
            str_contains($question, 'gehäuse') || str_contains($question, 'gehause') => 'visual_housing',
            str_contains($question, 'sicherer betrieb') => 'safe_operation',
            str_contains($question, 'auftraggeber') || str_contains($question, 'mängel') => 'customer_notice',
            str_contains($question, 'funktion') => 'function',
            str_contains($question, 'stecker') || str_contains($question, 'eingabe des zu prüfenden') => 'identification',
            default => '',
        };
    }

    private static function deviceWarming(int $deviceId): int
    {
        return $deviceId > 0 ? (int) R::getCell('SELECT warming_device FROM device WHERE id = ?', [$deviceId]) : 0;
    }

    private static function reportPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if (str_starts_with($path, '/')) return $path;
        return app_data_root() . '/' . ltrim($path, '/');
    }

    /** @param mixed $value */
    private static function numeric($value): ?float
    {
        $text = str_replace(',', '.', trim((string) $value));
        return $text !== '' && is_numeric($text) ? (float) $text : null;
    }
}
