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
        $payload = (array) ($job['payload'] ?? []);
        if ($type === 'inspection_data_migration'
            && !isset($checkpoint['inspection_ids'])
            && isset($payload['inspection_ids'])
        ) {
            $checkpoint['inspection_ids'] = (array) $payload['inspection_ids'];
        }
        $current = max(0, (int) ($job['current'] ?? 0));
        $total = max(0, (int) ($job['total'] ?? 0));

        return match ($type) {
            'missing_reports' => self::missingReports($checkpoint, $current, $total, $tick),
            'report_migration' => self::reportMigration($checkpoint, $current, $total, $tick),
            'all_report_regeneration' => self::allReportRegeneration($payload, $checkpoint, $current, $total, $tick),
            'phoenix_pdf_restore' => self::restorePhoenixPdfs($checkpoint, $current, $total, $tick),
            'measurement_migration' => self::measurementMigration($checkpoint, $current, $total, $tick),
            'inspection_data_migration' => self::inspectionDataMigration($checkpoint, $current, $total, $tick),
            'legacy_classification_migration' => self::legacyClassificationMigration($checkpoint, $current, $total, $tick),
            'import_result_reconciliation' => self::importResultReconciliation($checkpoint, $current, $total, $tick),
            'inspection_duplicate_audit' => self::inspectionDuplicateAudit($checkpoint, $current, $total, $tick),
            'inspection_duplicate_archive' => self::archiveExactImportDuplicates($checkpoint, $current, $total, $tick),
            'inspection_manual_csv_consolidation' => self::consolidateManualCsvDuplicates($checkpoint, $current, $total, $tick),
            'vocabulary_suggestion' => self::vocabularySuggestion($payload, $checkpoint, $current, $total, $tick),
            'vocabulary_review_scan' => self::vocabularyReviewScan($checkpoint, $current, $total, $tick),
            'vocabulary_normalization' => self::vocabularyNormalization($checkpoint, $current, $total, $tick),
            default => throw new InvalidArgumentException('Unbekannte Wartungsaufgabe: ' . $type),
        };
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function vocabularySuggestion(array $payload, array $checkpoint, int $current, int $total, callable $tick): array
    {
        $field = (string) ($payload['field'] ?? '');
        $value = trim((string) ($payload['value'] ?? ''));
        if (!in_array($field, DeviceVocabularyService::FIELDS, true) || $value === '') throw new InvalidArgumentException('Ungültige Stammdatenprüfung.');
        $proposal = DeviceVocabularyService::suggest($field, $value);
        $reviewId = DeviceVocabularyService::storeSuggestion($field, $value, $proposal);
        $tick(['review_id' => $reviewId], max(1, $current + 1), max(1, $total), $value, 'KI-Vorschlag wartet auf Freigabe.');
        return ['review_id' => $reviewId];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function vocabularyReviewScan(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $fields = DeviceVocabularyService::FIELDS;
        $fieldIndex = max(0, (int) ($checkpoint['field_index'] ?? 0));
        $afterKey = (string) ($checkpoint['after_key'] ?? '');
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        // A scan can resume days after it was queued, while device data has
        // changed in the meantime.  Never trust the original queue total for
        // progress: it made a resumed job report e.g. 1459 of 1194.  The
        // total is recomputed from the exact same distinct-value selection as
        // the cursor query below and is never allowed to become smaller than
        // work that was already checkpointed.
        $computedTotal = self::vocabularyReviewTotal($fields);
        $total = max(1, $current, $total, $computedTotal);
        while ($fieldIndex < count($fields)) {
            $field = $fields[$fieldIndex];
            $row = R::getRow("SELECT MIN({$field}) AS value, LOWER(TRIM({$field})) AS source_key FROM device WHERE TRIM(COALESCE({$field}, '')) <> '' AND LOWER(TRIM({$field})) > ? GROUP BY LOWER(TRIM({$field})) ORDER BY source_key LIMIT 1", [$afterKey]);
            if ($row === []) {
                $fieldIndex++; $afterKey = '';
                continue;
            }
            $value = trim((string) ($row['value'] ?? ''));
            $afterKey = (string) ($row['source_key'] ?? '');
            $current++;
            $known = $value === '' || DeviceVocabularyService::isNotRecognizable($value)
                || DeviceVocabularyService::aliasFor($field, $afterKey) !== null
                || DeviceVocabularyService::reviewFor($field, $afterKey) !== null;
            $message = 'Bereits entschiedener oder nicht relevanter Wert wurde übersprungen.';
            if (!$known) {
                $reviewId = DeviceVocabularyService::storeSuggestion($field, $value, DeviceVocabularyService::suggest($field, $value));
                $created++;
                $message = 'KI-Vorschlag wartet auf Freigabe.';
            }
            $tick(['field_index' => $fieldIndex, 'after_key' => $afterKey, 'created' => $created], $current, max(1, $total), $value, $message);
        }
        return ['processed' => $current, 'created' => $created];
    }

    /**
     * Counts precisely the source keys traversed by vocabularyReviewScan().
     *
     * @param list<string> $fields
     */
    private static function vocabularyReviewTotal(array $fields): int
    {
        $total = 0;
        foreach ($fields as $field) {
            // The field list is a closed service constant.  Retain this guard
            // nevertheless, as SQL identifiers cannot be supplied as query
            // parameters.
            if (!in_array($field, DeviceVocabularyService::FIELDS, true)) continue;
            $total += (int) R::getCell(
                "SELECT COUNT(*) FROM (\n"
                . " SELECT LOWER(TRIM({$field})) AS source_key\n"
                . " FROM device\n"
                . " WHERE TRIM(COALESCE({$field}, '')) <> ''\n"
                . " GROUP BY LOWER(TRIM({$field}))\n"
                . ') vocabulary_source_keys'
            );
        }
        return $total;
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function vocabularyNormalization(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        if ($total <= 0) $total = (int) R::getCell('SELECT COUNT(*) FROM device');
        while ($row = R::getRow('SELECT id, manufacturer, device_model, name FROM device WHERE id > ? ORDER BY id LIMIT 1', [$lastId])) {
            $lastId = (int) $row['id'];
            $values = DeviceVocabularyService::canonicalizeDeviceValues($row);
            R::exec('UPDATE device SET manufacturer = ?, device_model = ?, name = ?, updated_at = ? WHERE id = ?', [$values['manufacturer'], $values['device_model'], $values['name'], date(DATE_ATOM), $lastId]);
            $current++;
            $tick(['last_id' => $lastId], $current, $total, (string) $lastId, 'Stammdaten wurden vereinheitlicht.');
        }
        set_app_config('device_vocabulary_normalization_version', '1');
        return ['processed' => $current];
    }

    /**
     * Corrects rows which were imported before the canonical classification
     * existed. It deliberately only touches historical reports and resumes
     * from the last primary key after every worker slice.
     *
     * @param array<string,mixed> $checkpoint
     * @param callable $tick
     * @return array<string,mixed>
     */
    private static function legacyClassificationMigration(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $migrated = max(0, (int) ($checkpoint['migrated'] ?? 0));
        $errors = is_array($checkpoint['errors'] ?? null) ? $checkpoint['errors'] : [];
        $eligible = "COALESCE(test_date, '') <> '' AND test_date < '2025-01-01' AND COALESCE(classification, '') <> 'legacy'";
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligible}");
        }

        while ($row = R::getRow("SELECT id, external_number FROM inspection WHERE id > ? AND {$eligible} ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            try {
                $result = InspectionMigrationService::migrate($lastId);
                if (($result['classification'] ?? '') !== 'legacy') {
                    throw new RuntimeException('Historische Prüfung wurde nicht als Legacy klassifiziert.');
                }
                $migrated++;
                $message = 'Historischer Prüfbericht wurde dauerhaft als Legacy klassifiziert.';
            } catch (Throwable $exception) {
                $errors[] = ['inspection_id' => $lastId, 'error' => $exception->getMessage()];
                $errors = array_slice($errors, -50);
                $message = 'Legacy-Klassifikation fehlgeschlagen; der Datensatz bleibt unverändert und wird erneut versucht.';
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'migrated' => $migrated, 'errors' => $errors];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), $message);
        }

        set_app_config('legacy_classification_migration_version', '2');
        set_app_config('legacy_classification_migration_errors', json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        return ['migrated' => $migrated, 'errors' => $errors, 'processed' => $current];
    }

    /** Re-evaluates completed imports affected by the former checklist-only migration rule. */
    private static function importResultReconciliation(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $reconciled = max(0, (int) ($checkpoint['reconciled'] ?? 0));
        $errors = is_array($checkpoint['errors'] ?? null) ? $checkpoint['errors'] : [];
        // Reconcile inconclusive imports as before, but also revisit imported
        // duplicate numbers (for example 100012579-26-2) regardless of their
        // current result.  A completed import can still be the authoritative
        // replacement for an unfinished manual base row; restricting this to
        // data_missing left that duplicate visible forever.
        $eligible = "classification = 'migrated_import' AND (result_status = 'data_missing' OR external_number GLOB '*-[0-9][0-9]-[2-9]*')";
        if ($total <= 0) $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligible}");

        while ($row = R::getRow("SELECT id, external_number FROM inspection WHERE id > ? AND {$eligible} ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            try {
                $canonicalId = self::mergeOpenImportDuplicate($lastId);
                $result = InspectionMigrationService::migrate($canonicalId);
                $reconciled++;
                $message = $canonicalId !== $lastId
                    ? 'Importdaten wurden in die bereits offene Prüfung übernommen; keine zweite Prüfung angelegt.'
                    : (($result['status'] ?? '') === InspectionEvaluationService::DATA_MISSING
                    ? 'Importprüfung bleibt offen; das Quellergebnis ist nicht eindeutig bestätigt.'
                    : 'Überliefertes Quellergebnis wurde mit den Prüfdaten abgeglichen.');
            } catch (Throwable $exception) {
                $errors[] = ['inspection_id' => $lastId, 'error' => $exception->getMessage()];
                $errors = array_slice($errors, -50);
                $message = 'Abgleich der Importprüfung fehlgeschlagen; der Datensatz wird erneut versucht.';
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'reconciled' => $reconciled, 'errors' => $errors];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), $message);
        }

        set_app_config('import_result_reconciliation_version', '8');
        set_app_config('import_result_reconciliation_errors', json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        return ['reconciled' => $reconciled, 'errors' => $errors, 'processed' => $current];
    }

    /**
     * One-time, non-destructive audit for imported duplicate inspections.
     *
     * A repeated inspection can be legitimate (for example a repair retest),
     * so this job deliberately creates review findings rather than deleting,
     * merging or unbilling historical records. Exact duplicate numbers and
     * import suffixes are marked critical; any other repeat within 180 days
     * remains a manual review item.
     *
     * @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed>
     */
    private static function inspectionDuplicateAudit(array $checkpoint, int $current, int $total, callable $tick): array
    {
        if (empty($checkpoint['audit_v2_reset'])) {
            // V1 paired every record of a device history with every other
            // nearby record. Historic importer merges can make that a very
            // large and misleading matrix. These are generated findings, not
            // user decisions, so clear only open V1 findings before the
            // narrower V2 scan. Resolved findings are retained.
            R::exec("DELETE FROM inspectiondupreview WHERE status='open'");
            $checkpoint['audit_v2_reset'] = true;
        }
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $found = max(0, (int) ($checkpoint['found'] ?? 0));
        if ($total <= 0) $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE COALESCE(test_date, '') <> ''");
        while ($row = R::getRow("SELECT id, device_id, external_number, test_date FROM inspection WHERE id>? AND COALESCE(test_date, '')<>'' ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            $number = trim((string) $row['external_number']);
            $peers = R::getAll(
                "SELECT id, external_number, test_date, CAST(ABS(julianday(test_date)-julianday(?)) AS INTEGER) AS days_apart FROM inspection
                 WHERE device_id=? AND id<? AND COALESCE(test_date, '')<>''
                   AND ABS(julianday(test_date)-julianday(?))<=180
                 ORDER BY test_date, id",
                [(string) $row['test_date'], (int) $row['device_id'], $lastId, (string) $row['test_date']]
            );
            foreach ($peers as $peerIndex => $peer) {
                $peerNumber = trim((string) $peer['external_number']);
                $findingType = '';
                $severity = 'warning';
                if ($number !== '' && $number === $peerNumber) {
                    $findingType = 'same_inspection_number';
                    $severity = 'danger';
                } elseif (self::inspectionNumberBase($number) !== '' && self::inspectionNumberBase($number) === $peerNumber) {
                    $findingType = 'import_suffix';
                    $severity = 'danger';
                } elseif (count($peers) === 1 && $peerIndex === 0) {
                    $findingType = 'short_interval';
                } else {
                    // Several records in a short period usually indicate a
                    // historic device merge. Do not emit every pair: exact
                    // number duplicates above still remain visible.
                    continue;
                }
                $days = max(0, (int) ($peer['days_apart'] ?? 0));
                $reason = match ($findingType) {
                    'same_inspection_number' => 'Gleiche Prüfnummer am selben Gerät und Datum/Zeitraum: möglicher Importdoppelgänger.',
                    'import_suffix' => 'Nummer mit Import-Suffix am selben Gerät: möglicher künstlicher Doppelimport.',
                    default => 'Zwei Prüfungen desselben Geräts innerhalb von ' . $days . ' Tagen; Wiederholungsprüfung fachlich prüfen.',
                };
                $existing = R::findOne('inspectiondupreview', ' inspection_id=? AND peer_inspection_id=? AND finding_type=? ', [(int) $peer['id'], $lastId, $findingType]);
                if ($existing !== null) continue;
                $review = R::dispense('inspectiondupreview');
                $review->inspection_id = (int) $peer['id'];
                $review->peer_inspection_id = $lastId;
                $review->device_id = (int) $row['device_id'];
                $review->finding_type = $findingType;
                $review->severity = $severity;
                $review->reason = $reason;
                $review->status = 'open';
                $review->detected_at = date(DATE_ATOM);
                R::store($review);
                $found++;
            }
            $current++;
            $checkpoint = ['audit_v2_reset' => true, 'last_id' => $lastId, 'found' => $found];
            $tick($checkpoint, $current, $total, $number ?: (string) $lastId, $peers === [] ? 'Keine nahe Wiederholungsprüfung gefunden.' : 'Mögliche Wiederholungen wurden zur manuellen Prüfung vorgemerkt.');
        }
        set_app_config('inspection_duplicate_audit_version', '2');
        return ['processed' => $current, 'found' => $found];
    }

    private static function inspectionNumberBase(string $number): string
    {
        return preg_match('/^(.+-\\d{2})-[2-9][0-9]*$/', $number, $match) ? (string) $match[1] : '';
    }

    /** @return array{date:string,result_status:string} Original CSV facts, never derived values. */
    private static function csvSourceFacts(int $inspectionId): array
    {
        $source = json_decode((string) R::getCell('SELECT source_row_json FROM inspection_source_snapshot WHERE inspection_id=?', [$inspectionId]), true);
        if (!is_array($source)) return ['date' => '', 'result_status' => ''];
        $value = trim((string) ($source['Prüfdatum'] ?? $source['pruefdatum'] ?? $source['date'] ?? $source['Datum'] ?? ''));
        $dateValue = '';
        foreach (['!d/m/Y', '!d.m.Y', '!d.m.y', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) { $dateValue = $date->format('Y-m-d'); break; }
        }
        if ($dateValue === '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value) === 1) $dateValue = $value;
        $rawResult = trim((string) ($source['Prüfergebnis'] ?? $source['pruefergebnis'] ?? $source['result'] ?? ''));
        $result = InspectionEvaluationService::normalizeStatus($rawResult);
        return [
            'date' => $dateValue,
            'result_status' => in_array($result, [InspectionEvaluationService::PASSED, InspectionEvaluationService::FAILED], true) ? $result : '',
        ];
    }

    /**
     * Archives only unequivocal re-import copies.  This includes an import
     * suffix (-2, -3, ...) from the very same source file as well as a Phoenix
     * JSON export mirrored by its original Benning CSV record.  In both cases
     * the device, date and result must match.  CSV is the primary measurement
     * source, JSON the transport export.  Nothing is deleted.  If a
     * historical/SevDesk import had linked the duplicate to an invoice, the
     * invoice-item history is retained but its active allocation is released.
     * This makes a later reconciliation show the real discrepancy instead of
     * silently counting an import error as a billable device.
     *
     * @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed>
     */
    private static function archiveExactImportDuplicates(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $archived = max(0, (int) ($checkpoint['archived'] ?? 0));
        $released = max(0, (int) ($checkpoint['released'] ?? 0));
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection later
                WHERE (
                    (later.source_type='json' AND EXISTS (SELECT 1 FROM inspection canonical
                        WHERE canonical.device_id=later.device_id
                          AND canonical.source_type='csv'
                          AND canonical.external_number=later.external_number
                          AND canonical.test_date=later.test_date
                          AND COALESCE(canonical.result_status,'')=COALESCE(later.result_status,'')
                          AND COALESCE(canonical.archived_at,'')=''))
                    OR
                    (SUBSTRING_INDEX(later.external_number, '-', -1) REGEXP '^[2-9][0-9]*$'
                     AND EXISTS (SELECT 1 FROM inspection canonical
                        WHERE canonical.device_id=later.device_id
                          AND canonical.source_type=later.source_type
                          AND COALESCE(canonical.source_file,'')=COALESCE(later.source_file,'')
                          AND canonical.external_number=LEFT(later.external_number, LENGTH(later.external_number)-LENGTH(SUBSTRING_INDEX(later.external_number, '-', -1))-1)
                          AND canonical.test_date=later.test_date
                          AND COALESCE(canonical.result_status,'')=COALESCE(later.result_status,'')
                          AND COALESCE(canonical.archived_at,'')=''))
                )
                  AND TRIM(COALESCE(later.source_file,''))<>''
                  AND TRIM(COALESCE(later.external_number,''))<>''
                  AND TRIM(COALESCE(later.test_date,''))<>''
                  AND COALESCE(later.archived_at,'')='' ");
        }
        while ($row = R::getRow("SELECT later.id AS duplicate_id, MIN(canonical.id) AS canonical_id, later.external_number, later.test_date,
                CASE WHEN later.source_type='json' AND canonical.source_type='csv' THEN 'json_csv_mirror' ELSE 'import_suffix' END AS archive_kind
            FROM inspection later
            JOIN inspection canonical ON canonical.device_id=later.device_id
              AND canonical.test_date=later.test_date
              AND COALESCE(canonical.result_status,'')=COALESCE(later.result_status,'')
              AND COALESCE(canonical.archived_at,'')=''
              AND (
                (later.source_type='json' AND canonical.source_type='csv' AND canonical.external_number=later.external_number)
                OR
                (canonical.source_type=later.source_type
                 AND COALESCE(canonical.source_file,'')=COALESCE(later.source_file,'')
                 AND SUBSTRING_INDEX(later.external_number, '-', -1) REGEXP '^[2-9][0-9]*$'
                 AND canonical.external_number=LEFT(later.external_number, LENGTH(later.external_number)-LENGTH(SUBSTRING_INDEX(later.external_number, '-', -1))-1))
              )
            WHERE later.id>? AND later.source_type IN ('csv','json')
              AND TRIM(COALESCE(later.source_file,''))<>''
              AND TRIM(COALESCE(later.external_number,''))<>''
              AND TRIM(COALESCE(later.test_date,''))<>''
              AND COALESCE(later.archived_at,'')=''
            GROUP BY later.id, later.external_number, later.test_date, archive_kind
            ORDER BY later.id LIMIT 1", [$lastId])) {
            $duplicateId = (int) $row['duplicate_id'];
            $canonicalId = (int) $row['canonical_id'];
            $lastId = $duplicateId;
            R::begin();
            try {
                $duplicate = R::load('inspection', $duplicateId);
                if (!(int) $duplicate->id || trim((string) $duplicate->archived_at) !== '') { R::commit(); continue; }
                $now = date(DATE_ATOM);
                $reason = (string) $row['archive_kind'] === 'import_suffix'
                    ? 'Eindeutige Re-Importdublettenprüfung: künstliches Import-Suffix aus derselben Quelle (gleiches Gerät, Prüfdatum und Ergebnis); Originalprüfung #' . $canonicalId . '.'
                    : 'Eindeutige Re-Importdublettenprüfung: Phoenix-JSON-Spiegelung einer gleichlautenden Benning-CSV (gleiches Gerät, Prüfnummer, Prüfdatum und Ergebnis); Originalprüfung #' . $canonicalId . '.';
                $activeItems = R::findAll('billinginvoiceitem', ' inspection_id=? AND active=1 ', [$duplicateId]);
                foreach ($activeItems as $item) {
                    $item->active = 0;
                    $item->deactivated_at = $now;
                    $item->deactivation_reason = 'Importdublettenarchivierung; Originalprüfung #' . $canonicalId . ' bleibt maßgeblich.';
                    R::store($item);
                    $released++;
                }
                $duplicate->archived_at = $now;
                $duplicate->archived_reason = $reason;
                $duplicate->duplicate_of_inspection_id = $canonicalId;
                $duplicate->billable = 0;
                $duplicate->billing_eligibility = 'not_billable';
                $duplicate->billing_not_billable_reason = 'historisch_nicht_eindeutig';
                $duplicate->billing_not_billable_comment = $reason;
                $duplicate->billing_status = 'historisch_nicht_eindeutig';
                $duplicate->billing_active_invoice_item_id = null;
                $duplicate->updated_at = $now;
                R::store($duplicate);
                R::exec("UPDATE inspectiondupreview SET status='resolved', resolved_at=?, resolution=? WHERE (inspection_id=? OR peer_inspection_id=?) AND finding_type='same_inspection_number' AND status='open'", [$now, $reason, $duplicateId, $duplicateId]);
                R::commit();
                audit_log('import_pruefung_archiviert', ['_category' => 'import', 'inspection_id' => $duplicateId, 'canonical_inspection_id' => $canonicalId, 'released_invoice_items' => count($activeItems), 'reason' => $reason]);
                $archived++;
            } catch (Throwable $exception) {
                R::rollback();
                throw $exception;
            }
            $current++;
            $checkpoint = ['last_id' => $lastId, 'archived' => $archived, 'released' => $released];
            $tick($checkpoint, $current, max($total, $current), (string) $row['external_number'], 'Eindeutige Re-Importdublettenprüfung archiviert und aus aktiven Rechnungszuordnungen gelöst.');
        }
        set_app_config('inspection_duplicate_archive_version', '3');
        return compact('archived', 'released');
    }

    /**
     * Consolidates an abandoned manual draft with its later completed CSV
     * import.  The import remains the factual inspection: its CSV test date,
     * result and measurements stay untouched.  The manual record is archived
     * rather than deleted and its original number is restored on the CSV row.
     *
     * @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed>
     */
    private static function consolidateManualCsvDuplicates(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $consolidated = max(0, (int) ($checkpoint['consolidated'] ?? 0));
        $released = max(0, (int) ($checkpoint['released'] ?? 0));
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection
                WHERE source_type='manual' AND status='in_progress'
                  AND COALESCE(archived_at,'')='' AND TRIM(COALESCE(external_number,''))<>''");
        }
        while ($manualRow = R::getRow("SELECT id, device_id, external_number, test_date
            FROM inspection
            WHERE id>? AND source_type='manual' AND status='in_progress'
              AND COALESCE(archived_at,'')='' AND TRIM(COALESCE(external_number,''))<>''
            ORDER BY id LIMIT 1", [$lastId])) {
            $manualId = (int) $manualRow['id'];
            $lastId = $manualId;
            $manualNumber = trim((string) $manualRow['external_number']);
            $manualDate = trim((string) $manualRow['test_date']);
            $canonical = null;
            $canonicalSourceDate = '';
            foreach (R::findAll('inspection', " device_id=? AND source_type='csv' AND status='completed' AND COALESCE(archived_at,'')='' ORDER BY test_date, id ", [(int) $manualRow['device_id']]) as $candidate) {
                $candidateNumber = trim((string) $candidate->external_number);
                $candidateFacts = self::csvSourceFacts((int) $candidate->id);
                $candidateDate = $candidateFacts['date'] ?: trim((string) $candidate->test_date);
                if (self::inspectionNumberBase($candidateNumber) !== $manualNumber || $candidateDate === '' || $manualDate === '') continue;
                try {
                    $days = (int) (new DateTimeImmutable($manualDate))->diff(new DateTimeImmutable($candidateDate))->format('%r%a');
                } catch (Throwable) {
                    continue;
                }
                if ($days < 0 || $days > 7) continue;
                $canonical = $candidate;
                $canonicalSourceDate = $candidateDate;
                $canonicalSourceResult = $candidateFacts['result_status'];
                break;
            }
            $current++;
            if ($canonical === null) {
                $checkpoint = array_merge($checkpoint, ['last_id' => $lastId, 'consolidated' => $consolidated, 'released' => $released]);
                $tick($checkpoint, $current, max($total, $current), $manualNumber, 'Kein passender abgeschlossener CSV-Import innerhalb von sieben Tagen; manueller Entwurf bleibt unverändert.');
                continue;
            }
            R::begin();
            try {
                $manual = R::load('inspection', $manualId);
                $csv = R::load('inspection', (int) $canonical->id);
                if (!(int) $manual->id || !(int) $csv->id || trim((string) $manual->archived_at) !== '') { R::commit(); continue; }
                $collision = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE external_number=? AND COALESCE(archived_at,'')='' AND id NOT IN (?, ?)", [$manualNumber, $manualId, (int) $csv->id]);
                if ($collision > 0) { R::commit(); continue; }
                $now = date(DATE_ATOM);
                foreach (['metadata_notes', 'customer_hint'] as $field) {
                    if (trim((string) ($csv->$field ?? '')) === '' && trim((string) ($manual->$field ?? '')) !== '') $csv->$field = $manual->$field;
                }
                $previousCsvDate = trim((string) $csv->test_date);
                $previousCsvResult = trim((string) $csv->result_status);
                if ($canonicalSourceDate !== '' && $canonicalSourceDate !== $previousCsvDate) {
                    $previousDue = trim((string) $csv->next_due_date);
                    try {
                        if ($previousDue !== '' && $previousCsvDate !== '') {
                            $intervalDays = max(0, (int) (new DateTimeImmutable($previousCsvDate))->diff(new DateTimeImmutable($previousDue))->format('%r%a'));
                            if ($intervalDays > 0) $csv->next_due_date = (new DateTimeImmutable($canonicalSourceDate))->modify('+' . $intervalDays . ' days')->format('Y-m-d');
                        }
                    } catch (Throwable) {
                        // A malformed historic due date must never prevent the factual CSV date from being restored.
                    }
                    $csv->test_date = $canonicalSourceDate;
                }
                if ($canonicalSourceResult !== '' && $canonicalSourceResult !== $previousCsvResult) {
                    $csv->result_status = $canonicalSourceResult;
                }
                $csv->external_number = $manualNumber;
                $csv->updated_at = $now;
                R::store($csv);
                $reason = 'Manueller Entwurf wurde mit der abgeschlossenen CSV-Prüfung #' . (int) $csv->id . ' zusammengeführt; CSV-Datum ' . $canonicalSourceDate . ' und CSV-Ergebnis bleiben maßgeblich.';
                $activeItems = R::findAll('billinginvoiceitem', ' inspection_id=? AND active=1 ', [$manualId]);
                foreach ($activeItems as $item) {
                    $item->active = 0;
                    $item->deactivated_at = $now;
                    $item->deactivation_reason = $reason;
                    R::store($item);
                    $released++;
                }
                $manual->archived_at = $now;
                $manual->archived_reason = $reason;
                $manual->duplicate_of_inspection_id = (int) $csv->id;
                $manual->billable = 0;
                $manual->billing_eligibility = 'not_billable';
                $manual->billing_not_billable_reason = 'historisch_nicht_eindeutig';
                $manual->billing_not_billable_comment = $reason;
                $manual->billing_status = 'historisch_nicht_eindeutig';
                $manual->billing_active_invoice_item_id = null;
                $manual->updated_at = $now;
                R::store($manual);
                R::exec("UPDATE inspectiondupreview SET status='resolved', resolved_at=?, resolution=? WHERE (inspection_id=? AND peer_inspection_id=?) OR (inspection_id=? AND peer_inspection_id=?)", [$now, $reason, $manualId, (int) $csv->id, (int) $csv->id, $manualId]);
                R::commit();
                audit_log('manueller_pruefentwurf_zusammengefuehrt', ['_category' => 'import', 'manual_inspection_id' => $manualId, 'csv_inspection_id' => (int) $csv->id, 'csv_test_date' => (string) $csv->test_date, 'released_invoice_items' => count($activeItems)]);
                $consolidated++;
                $regenerated = is_array($checkpoint['report_inspection_ids'] ?? null) ? $checkpoint['report_inspection_ids'] : [];
                $regenerated[] = (int) $csv->id;
                $checkpoint['report_inspection_ids'] = array_values(array_unique(array_filter(array_map('intval', $regenerated))));
                $message = 'Manueller Entwurf archiviert; CSV-Prüfung übernimmt Originaldatum und -ergebnis aus der CSV-Zeile.';
            } catch (Throwable $exception) {
                R::rollback();
                throw $exception;
            }
            $checkpoint = array_merge($checkpoint, ['last_id' => $lastId, 'consolidated' => $consolidated, 'released' => $released]);
            $tick($checkpoint, $current, max($total, $current), $manualNumber, $message);
        }
        $reportInspectionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($checkpoint['report_inspection_ids'] ?? [])))));
        if ($reportInspectionIds !== []) {
            BackgroundJobService::enqueue('all_report_regeneration', ['type' => 'all_report_regeneration', 'inspection_ids' => $reportInspectionIds], [
                'total' => count($reportInspectionIds),
                'dedupe_key' => 'maintenance:manual-csv-report-regeneration:v2',
                'cancellable' => false,
            ]);
        }
        set_app_config('inspection_manual_csv_consolidation_version', '2');
        return compact('consolidated', 'released');
    }

    /**
     * Merge an import-created numerical suffix (for example -26-2) back into
     * the existing unfinished inspection with the canonical number. Completed
     * inspections, different devices and rows with reports are never touched.
     */
    private static function mergeOpenImportDuplicate(int $duplicateId): int
    {
        $duplicate = R::load('inspection', $duplicateId);
        if (!(int) $duplicate->id || !in_array((string) $duplicate->source_type, ['csv', 'json'], true)) return $duplicateId;
        $number = trim((string) $duplicate->external_number);
        // Inspection numbers conventionally end in a two digit year ("-26").
        // Only a second numeric part after that year is an artificial suffix
        // created by the duplicate-number allocator ("-26-2").
        if (!preg_match('/^(.*-\d{2})-([2-9][0-9]*)$/', $number, $match)) {
            return self::mergeManualSuffixIntoImport($duplicate);
        }
        $canonical = R::findOne('inspection', "device_id = ? AND external_number = ? AND id <> ?
            AND TRIM(COALESCE(report_path, '')) = ''
            AND (COALESCE(result_status, '') IN ('', 'in_progress', 'data_missing', 'pending')
                OR COALESCE(status, '') IN ('', 'in_progress', 'data_missing', 'pending', 'draft'))
            ORDER BY id ASC", [(int) $duplicate->device_id, $match[1], $duplicateId]);
        if ($canonical === null) return $duplicateId;

        R::begin();
        try {
            // Preserve the earlier manual/open state before replacing it with
            // the authoritative import data.
            InspectionMigrationService::migrate((int) $canonical->id);
            foreach ([
                'dedupe_key', 'source_type', 'source_file', 'legacy_number', 'storage_slot',
                'cable_length_m', 'rsl_limit_ohm', 'test_date', 'next_due_date',
                'result_status', 'inspection_type', 'examiner', 'protection_class',
                'device_type', 'manufacturer', 'device_model', 'room_snapshot',
                'measurements_json', 'checklist_json', 'raw_json', 'csv_row_json',
            ] as $field) {
                $canonical->{$field} = $duplicate->{$field};
            }
            $canonical->inspection_type = InspectionEvaluationService::canonicalInspectionType(
                (string) $canonical->inspection_type,
                (string) $canonical->protection_class
            );
            $canonical->updated_at = date(DATE_ATOM);
            foreach (['inspection_answer', 'inspection_measurement', 'inspection_diagnostic'] as $table) {
                R::exec("DELETE FROM {$table} WHERE inspection_id = ?", [(int) $canonical->id]);
                R::exec("DELETE FROM {$table} WHERE inspection_id = ?", [$duplicateId]);
            }
            // The immutable legacy snapshot retains the original manual row;
            // the active source snapshot must reflect the import that now
            // supplies the authoritative result and measurements.
            R::exec(
                'UPDATE inspection_source_snapshot SET source_type = ?, source_file = ?, source_row_json = ? WHERE inspection_id = ?',
                [
                    (string) $duplicate->source_type,
                    (string) $duplicate->source_file,
                    trim((string) $duplicate->csv_row_json) ?: (string) $duplicate->raw_json,
                    (int) $canonical->id,
                ]
            );
            R::trash($duplicate);
            // The imported dedupe key belongs to the duplicate until it has
            // been removed; only then can it safely become the canonical key.
            R::store($canonical);
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
        audit_log('import_pruefung_zusammengefuehrt', [
            '_category' => 'import',
            'canonical_inspection_id' => (int) $canonical->id,
            'canonical_inspection_number' => (string) $canonical->external_number,
            'duplicate_inspection_id' => $duplicateId,
            'duplicate_inspection_number' => $number,
        ]);
        return (int) $canonical->id;
    }

    /** Reverses the same mistake when an import occupied the base number first. */
    private static function mergeManualSuffixIntoImport(\RedBeanPHP\OODBBean $canonical): int
    {
        $number = trim((string) $canonical->external_number);
        if ($number === '') return (int) $canonical->id;
        $manual = null;
        foreach (R::findAll('inspection', ' device_id = ? AND source_type = ? AND external_number LIKE ? ORDER BY id ASC ', [(int) $canonical->device_id, 'manual', $number . '-%']) as $candidate) {
            if (!preg_match('/^' . preg_quote($number, '/') . '-([2-9][0-9]*)$/', (string) $candidate->external_number)) continue;
            if (trim((string) ($candidate->report_path ?? '')) !== '') continue;
            if (!in_array((string) ($candidate->result_status ?? ''), ['in_progress', 'data_missing', 'pending'], true)) continue;
            $manual = $candidate;
            break;
        }
        if ($manual === null) return (int) $canonical->id;

        R::begin();
        try {
            foreach (['test_date', 'next_due_date', 'examiner', 'protection_class', 'inspection_type', 'storage_slot', 'cable_length_m', 'rsl_limit_ohm', 'metadata_notes', 'regie_reason', 'regie_minutes'] as $field) {
                if (trim((string) ($manual->{$field} ?? '')) !== '') $canonical->{$field} = $manual->{$field};
            }
            foreach (['checklist_json', 'measurements_json', 'raw_json'] as $field) {
                $value = trim((string) ($manual->{$field} ?? ''));
                if ($value !== '' && $value !== '[]' && $value !== '{}') $canonical->{$field} = $value;
            }
            $canonical->result_status = (string) ($manual->result_status ?: InspectionEvaluationService::IN_PROGRESS);
            $canonical->status = (string) ($manual->status ?: InspectionEvaluationService::IN_PROGRESS);
            $canonical->updated_at = date(DATE_ATOM);
            foreach (['inspection_answer', 'inspection_measurement', 'inspection_diagnostic'] as $table) {
                R::exec("DELETE FROM {$table} WHERE inspection_id = ?", [(int) $canonical->id]);
                R::exec("DELETE FROM {$table} WHERE inspection_id = ?", [(int) $manual->id]);
            }
            R::trash($manual);
            R::store($canonical);
            R::commit();
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
        audit_log('import_pruefung_zusammengefuehrt', [
            '_category' => 'import',
            'canonical_inspection_id' => (int) $canonical->id,
            'canonical_inspection_number' => $number,
            'duplicate_inspection_id' => (int) $manual->id,
            'duplicate_inspection_number' => (string) $manual->external_number,
        ]);
        return (int) $canonical->id;
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function inspectionDataMigration(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $migrated = max(0, (int) ($checkpoint['migrated'] ?? 0));
        $errors = is_array($checkpoint['errors'] ?? null) ? $checkpoint['errors'] : [];
        $selected = array_values(array_unique(array_filter(
            array_map('intval', (array) ($checkpoint['inspection_ids'] ?? [])),
            static fn(int $id): bool => $id > 0
        )));
        if ($selected === [] && $total <= 0) {
            $total = (int) R::getCell('SELECT COUNT(*) FROM inspection');
        } elseif ($selected !== []) {
            $total = count($selected);
        }

        while (true) {
            if ($selected !== []) {
                $next = $selected[$current] ?? 0;
                $row = $next > 0 ? R::getRow('SELECT id, external_number FROM inspection WHERE id = ?', [$next]) : [];
            } else {
                $row = R::getRow('SELECT id, external_number FROM inspection WHERE id > ? ORDER BY id LIMIT 1', [$lastId]);
            }
            if ($row === []) break;
            $lastId = (int) $row['id'];
            try {
                $result = InspectionMigrationService::migrate($lastId);
                $migrated++;
                $message = 'Prüfung wurde gesichert und in das kanonische Datenmodell überführt: '
                    . InspectionEvaluationService::presentation((string) $result['status'])['label'] . '.';
            } catch (Throwable $exception) {
                $errors[] = ['inspection_id' => $lastId, 'error' => $exception->getMessage()];
                $errors = array_slice($errors, -50);
                $message = 'Migration fehlgeschlagen; der unveränderte Datensatz wird protokolliert.';
            }
            $current++;
            $checkpoint = [
                'last_id' => $lastId,
                'migrated' => $migrated,
                'errors' => $errors,
                'inspection_ids' => $selected,
            ];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), $message);
        }

        if ($selected === []) {
            set_app_config('inspection_data_migration_version', '1');
            set_app_config(
                'inspection_data_migration_errors',
                json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            );
        }
        return ['migrated' => $migrated, 'errors' => $errors, 'processed' => $current];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function missingReports(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $eligible = "result_status IN ('passed','failed') AND COALESCE(classification, '') <> 'legacy' AND " . inspection_report_signature_sql('inspection');
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligible} AND TRIM(COALESCE(report_path, '')) = ''");
        }
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        $errors = is_array($checkpoint['errors'] ?? null) ? $checkpoint['errors'] : [];

        while ($row = R::getRow("SELECT id, device_id, external_number FROM inspection WHERE id > ? AND {$eligible} AND TRIM(COALESCE(report_path, '')) = '' ORDER BY id LIMIT 1", [$lastId])) {
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
        $marker = app_data_root() . '/migration/inspection-reports-v3.json';
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE result_status IN ('passed','failed') AND classification = 'migrated_import' AND " . inspection_report_signature_sql('inspection'));
        }

        while ($row = R::getRow("SELECT id, external_number FROM inspection WHERE id > ? AND result_status IN ('passed','failed') AND classification = 'migrated_import' AND " . inspection_report_signature_sql('inspection') . ' ORDER BY id LIMIT 1', [$lastId])) {
            $lastId = (int) $row['id'];
            self::renderReport($lastId, true);
            $current++;
            $created++;
            $checkpoint = ['last_id' => $lastId, 'created' => $created];
            self::writeMarker($marker, ['version' => 3, 'last_id' => $lastId, 'completed' => false, 'updated_at' => date(DATE_ATOM)]);
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), 'Prüfbericht wurde mit dem aktuellen Layout neu erzeugt.');
        }

        self::writeMarker($marker, ['version' => 3, 'last_id' => $lastId, 'completed' => true, 'completed_at' => date(DATE_ATOM), 'created' => $created]);
        return ['created' => $created, 'processed' => $current];
    }

    /** Regenerates every report-capable current inspection after a data repair. */
    private static function allReportRegeneration(array $payload, array $checkpoint, int $current, int $total, callable $tick): array
    {
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $created = max(0, (int) ($checkpoint['created'] ?? 0));
        $eligible = "result_status IN ('passed','failed') AND COALESCE(classification, '') <> 'legacy' AND " . inspection_report_signature_sql('inspection');
        $inspectionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['inspection_ids'] ?? [])))));
        if ($inspectionIds !== []) $eligible .= ' AND id IN (' . implode(',', $inspectionIds) . ')';
        if ($total <= 0) $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligible}");

        while ($row = R::getRow("SELECT id, external_number FROM inspection WHERE id > ? AND {$eligible} ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            self::renderReport($lastId, true);
            $current++;
            $created++;
            $checkpoint = ['last_id' => $lastId, 'created' => $created];
            $tick($checkpoint, $current, $total, (string) ($row['external_number'] ?? $lastId), 'Prüfbericht wurde nach dem Datenabgleich neu erzeugt.');
        }

        $marker = trim((string) ($payload['completion_marker'] ?? ''));
        if ($marker !== '') self::writeMarker($marker, ['completed' => true, 'completed_at' => date(DATE_ATOM), 'created' => $created]);
        $completionConfigKey = trim((string) ($payload['completion_config_key'] ?? ''));
        if ($completionConfigKey !== '') {
            set_app_config($completionConfigKey, (string) ($payload['completion_config_value'] ?? '1'));
        }
        return ['created' => $created, 'processed' => $current];
    }

    /** @param array<string,mixed> $checkpoint @param callable $tick @return array<string,mixed> */
    private static function restorePhoenixPdfs(array $checkpoint, int $current, int $total, callable $tick): array
    {
        $marker = app_data_root() . '/migration/inspection-reports-legacy-restore-v5.json';
        $lastId = max(0, (int) ($checkpoint['last_id'] ?? 0));
        $restored = max(0, (int) ($checkpoint['restored'] ?? 0));
        $unresolved = max(0, (int) ($checkpoint['unresolved'] ?? 0));
        // Legacy is a classification, not a negative result. The shared
        // status projection intentionally emits `legacy` for it, therefore
        // restoration must use the canonical stored outcome directly.
        $eligible = "classification = 'legacy' AND result_status IN ('passed','failed')";
        if ($total <= 0) {
            $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE {$eligible}");
        }
        $roots = self::phoenixRoots();
        $index = self::phoenixPdfIndex($roots);

        while ($row = R::getRow("SELECT id, external_number, legacy_number, report_path FROM inspection WHERE id > ? AND {$eligible} ORDER BY id LIMIT 1", [$lastId])) {
            $lastId = (int) $row['id'];
            $source = self::findPhoenixPdf($row, $index);
            if ($source !== '') {
                InspectionDataService::registerReportAsset(
                    $lastId,
                    'legacy_original',
                    $source,
                    true
                );
                R::exec('UPDATE inspection SET report_path = ?, updated_at = ? WHERE id = ?', [$source, date(DATE_ATOM), $lastId]);
                $restored++;
                $message = 'Legacy-Originalbericht wurde als aktiver Bericht verknüpft.';
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
        // Phoenix exports have an authoritative source PDF.  Do not replace
        // it with a reconstructed current report; Benning imports do not have
        // such a source file and continue through the normal generator below.
        $existingReport = trim((string) ($inspection->report_path ?? ''));
        $sourcePdf = $existingReport !== '' && !str_starts_with(ltrim($existingReport, '/'), 'reports/current/')
            ? (str_starts_with($existingReport, '/') ? $existingReport : app_data_root() . '/' . $existingReport)
            : '';
        if ((string) ($inspection->source_type ?? '') === 'json' && $sourcePdf !== '' && is_file($sourcePdf)) {
            InspectionDataService::registerReportAsset($inspectionId, 'legacy_original', $sourcePdf, true);
            return;
        }
        if ((string) ($inspection->classification ?? '') === 'legacy') throw new RuntimeException('Legacy-Berichte werden nicht neu erzeugt.');
        if (!InspectionEvaluationService::reportAllowed((string) $inspection->result_status, (string) $inspection->classification)) throw new RuntimeException('Die Prüfung ist nicht für einen Bericht freigegeben.');
        if (!examiner_has_report_signature((string) $inspection->examiner)) throw new RuntimeException('Der eingetragene Prüfer hat keine hinterlegte Unterschrift.');
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
        InspectionDataService::registerReportAsset($inspectionId, 'generated', $path, true);
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
