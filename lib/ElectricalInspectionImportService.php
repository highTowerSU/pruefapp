<?php

declare(strict_types=1);

use RedBeanPHP\R;

/**
 * Imports historical Prüf-Doku JSON exports and Benning CSV/ODS pairs.
 *
 * CSV files contain measurements, while the matching ODS file contains the
 * device master data. Both are joined by Speicher Nr. / Speicherplatz.
 */
final class ElectricalInspectionImportService
{
    /** @var array<string, string> */
    private array $reportsByNumber = [];

    /** @var array<string, true> */
    private array $indexedCandidateReportRoots = [];

    private string $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->storageRoot = $storageRoot ?: app_data_root() . '/reports';
    }

    /**
     * @return array{files:int, imported:int, updated:int, devices:int, reports:int, skipped:int, errors:list<string>}
     */
    public function importDirectory(string $directory, ?string $reportsDirectory = null, array $defaults = []): array
    {
        $defaults['_audit_correlation_id'] = trim((string) ($defaults['_audit_correlation_id'] ?? '')) ?: 'import-' . bin2hex(random_bytes(8));
        $source = realpath($directory) ?: '';
        if ($source === '' || (!is_dir($source) && !is_file($source))) {
            throw new InvalidArgumentException('Importverzeichnis oder Importdatei wurde nicht gefunden.');
        }
        $root = is_dir($source) ? $source : dirname($source);
        $reportRoot = $reportsDirectory !== null && is_dir($reportsDirectory) ? realpath($reportsDirectory) : $root;
        $this->indexReports($reportRoot ?: $root, $reportsDirectory !== null || is_dir($source));
        $stats = ['files' => 0, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => [], 'errors' => []];
        $files = is_file($source)
            ? [new SplFileInfo($source)]
            : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile()) continue;
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['json', 'jsonl', 'csv'], true)) continue;
            if ($this->isAggregateExport($file->getBasename())) continue;
                $stats['files']++;
            try {
                $result = in_array($extension, ['json', 'jsonl'], true)
                    ? $this->importJsonFile($file->getPathname(), $root, $extension === 'jsonl', $defaults)
                    : $this->importCsvFile($file->getPathname(), $root, $defaults);
                foreach ($result as $key => $value) {
                    if ($key === 'reason') { $stats['errors'][] = $file->getPathname() . ': ' . $value; continue; }
                    if (in_array($key, ['new_devices', 'updated_devices', 'not_imported'], true) && is_array($value)) { $stats[$key] = array_merge($stats[$key] ?? [], $value); continue; }
                    if (array_key_exists($key, $stats) && is_int($value)) $stats[$key] += $value;
                }
            } catch (Throwable $exception) {
                $stats['errors'][] = $file->getPathname() . ': ' . $exception->getMessage();
            }
        }

        if ((int) $stats['files'] === 0) {
            throw new InvalidArgumentException('Keine Importdateien gefunden. Erwartet werden JSON, JSONL oder CSV; PDF-Dateien bitte als PDF-Quellverzeichnis angeben.');
        }

        $this->persistImportLog($stats);
        audit_log('import_abgeschlossen', [
            '_correlation_id' => $defaults['_audit_correlation_id'],
            '_category' => 'import',
            'source' => $source,
            'stats' => $stats,
        ]);
        return $stats;
    }

    /**
     * Reads source rows without writing inspections.  CSV values are enriched
     * with their paired ODS device row exactly like the regular importer.
     * @return list<array{source_kind:string,source_path:string,row_no:int,record:array<string,mixed>}>
     */
    public function candidateRecords(string $directory): array
    {
        $root = realpath($directory) ?: '';
        if ($root === '' || !is_dir($root)) throw new InvalidArgumentException('Quellverzeichnis wurde nicht gefunden.');
        $result = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
            $extension = strtolower($file->getExtension());
            $path = $file->getPathname();
            if ($this->isAggregateExport($file->getBasename())) continue;
            if (in_array($extension, ['json', 'jsonl'], true)) {
                $lines = $extension === 'jsonl' ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] : [];
                $records = [];
                if ($extension === 'jsonl') foreach ($lines as $line) { $decoded = json_decode($line, true); if (is_array($decoded)) $records[] = $decoded; }
                else {
                    $decoded = json_decode((string) file_get_contents($path), true);
                    $records = is_array($decoded) && isset($decoded['number']) ? [$decoded] : (is_array($decoded['resources']['data'] ?? null) ? $decoded['resources']['data'] : (is_array($decoded) && array_is_list($decoded) ? $decoded : []));
                }
                foreach ($records as $rowNo => $record) {
                    if (!is_array($record) || $this->ignoredInspectionTypeReason($record) !== '') continue;
                    $record = $this->normalizePhoenixCandidateRecord($record);
                    $result[] = ['source_kind' => 'json', 'source_path' => $path, 'row_no' => $rowNo + 1, 'record' => $record];
                }
                continue;
            }
            if ($extension !== 'csv') continue;
            // A stand-alone measurement export belongs only to a manually
            // created Prüfweb inspection.  It is not historic master data,
            // hence it must not create an import candidate on its own.
            $odsPath = $this->matchingOdsPath($path);
            if ($odsPath === null) continue;
            $contents = str_replace("\0", '', (string) file_get_contents($path));
            if (!mb_check_encoding($contents, 'UTF-8')) $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
            $delimiter = substr_count((string) strtok($contents, "\r\n"), ';') >= 3 ? ';' : ',';
            $stream = fopen('php://memory', 'r+'); fwrite($stream, $contents); rewind($stream);
            $header = fgetcsv($stream, 0, $delimiter);
            if (!is_array($header)) { fclose($stream); continue; }
            $header = $this->uniqueHeaders($header);
            $ods = $this->readOds($odsPath);
            $rowNo = 1;
            while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
                $rowNo++;
                if (count(array_filter($row, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
                $record = $this->csvRecord($header, $this->repairDecimalColumns($header, $row));
                $slot = trim((string) ($record['storage_slot'] ?? ''));
                $odsRow = $slot !== '' ? ($ods[$slot] ?? $ods[ltrim($slot, '0')] ?? null) : null;
                if (is_array($odsRow)) $record = $this->mergeOdsRecord($record, $odsRow);
                // In Phoenix result.csv exports `number` identifies the
                // device.  CSV has no independent inspection resource ID.
                $deviceNumber = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
                if ($deviceNumber !== '') $record['device_number'] = $deviceNumber;
                $result[] = ['source_kind' => 'csv_ods', 'source_path' => $path, 'row_no' => $rowNo, 'record' => $record];
            }
            fclose($stream);
        }
        return $result;
    }

    /** Imports an already reviewed candidate as a consolidated inspection. */
    public function importCandidateRecord(array $record, string $sourcePath): array
    {
        $root = dirname($sourcePath);
        // PDFs are not independent inspection candidates. They are indexed
        // recursively beside the JSON/CSV source and attached by number when
        // a reviewed candidate becomes a real inspection.
        if (!isset($this->indexedCandidateReportRoots[$root])) {
            $this->indexReports($root, true);
            $this->indexedCandidateReportRoots[$root] = true;
        }
        // Candidate records distinguish Phoenix' inspection resource ID from
        // the actual device number. The historic importer predates that
        // distinction and uses external_number as the inspection number.
        if (trim((string) ($record['inspection_number'] ?? '')) !== '') {
            $record['external_number'] = trim((string) $record['inspection_number']);
        }
        return $this->importRecord($record, 'reconciled', $sourcePath, $root);
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private function normalizePhoenixCandidateRecord(array $record): array
    {
        // Phoenix inspection exports use id/resource_id for the inspection
        // resource and number for the device label. Their type is an object.
        if (!isset($record['module_scoped_id'], $record['resource_id']) && !is_array($record['type'] ?? null)) return $record;
        $inspectionId = trim((string) ($record['id'] ?? $record['resource_id'] ?? ''));
        $deviceNumber = trim((string) ($record['number'] ?? ''));
        if ($inspectionId !== '') $record['inspection_number'] = $inspectionId;
        if ($deviceNumber !== '') $record['device_number'] = $deviceNumber;
        if (is_array($record['type'] ?? null)) {
            $record['inspection_type'] = trim((string) ($record['type']['brezel_name'] ?? $record['type']['name'] ?? $record['type']['title'] ?? ''));
        }
        if (isset($record['date']) && !isset($record['test_date'])) $record['test_date'] = (string) $record['date'];
        return $record;
    }

    /**
     * Imports a bounded JSONL slice and returns a byte cursor for exact resume.
     *
     * @return array{next_offset:int,eof:bool,processed:int,stats:array<string,mixed>}
     */
    public function importJsonlChunk(
        string $path,
        int $byteOffset,
        int $maxRecords,
        ?string $reportsDirectory = null,
        array $defaults = []
    ): array {
        $real = realpath($path) ?: '';
        if ($real === '' || !is_file($real)) throw new InvalidArgumentException('JSONL-Datei wurde nicht gefunden.');
        $defaults['_audit_correlation_id'] = trim((string) ($defaults['_audit_correlation_id'] ?? '')) ?: 'import-' . bin2hex(random_bytes(8));
        $root = dirname($real);
        $reportRoot = $reportsDirectory !== null && is_dir($reportsDirectory) ? realpath($reportsDirectory) : $root;
        $this->indexReports($reportRoot ?: $root, true);
        $handle = fopen($real, 'rb');
        if ($handle === false) throw new RuntimeException('JSONL-Datei konnte nicht geöffnet werden.');
        if ($byteOffset > 0) fseek($handle, $byteOffset);
        $stats = ['files' => 1, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => [], 'errors' => []];
        $processed = 0;
        try {
            while ($processed < max(1, $maxRecords) && ($line = fgets($handle)) !== false) {
                if (trim($line) === '') continue;
                $processed++;
                try {
                    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') {
                        $stats['skipped']++;
                        $this->auditSkipped($defaults, $real, 'Datensatz enthält keine Prüfnummer.', ['line_offset' => $byteOffset]);
                        continue;
                    }
                    if (($reason = $this->ignoredInspectionTypeReason($record)) !== '') {
                        $stats['skipped']++;
                        $this->auditSkipped($defaults, $real, $reason, ['line_offset' => $byteOffset, 'inspection_number' => (string) $record['number']]);
                        continue;
                    }
                    $one = $this->importRecord(array_merge($defaults, $record), 'json', $real, $root);
                    $this->mergeStats($stats, $one);
                } catch (Throwable $exception) {
                    $stats['skipped']++;
                    $stats['errors'][] = $exception->getMessage();
                    $this->auditSkipped($defaults, $real, $exception->getMessage(), ['line_offset' => $byteOffset]);
                }
            }
            $nextOffset = (int) ftell($handle);
            $eof = feof($handle);
        } finally {
            fclose($handle);
        }
        return ['next_offset' => $nextOffset, 'eof' => $eof, 'processed' => $processed, 'stats' => $stats];
    }

    /** Import only measurement columns into already existing inspections. */
    public function importPendingMeasurements(string $csvPath, string $date): array
    {
        $contents = str_replace("\0", '', (string) file_get_contents($csvPath));
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        $delimiter = substr_count((string) strtok($contents, "\r\n"), ';') >= 3 ? ';' : ',';
        $stream = fopen('php://memory', 'r+'); fwrite($stream, $contents); rewind($stream);
        $header = $this->uniqueHeaders(fgetcsv($stream, 0, $delimiter) ?: []);
        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            if (count(array_filter($row, static fn($value): bool => trim((string) $value) !== '')) > 0) $rows[] = $row;
        }
        fclose($stream);
        $date = $this->normalizeDate($date);
        if ($date === '' && isset($rows[0])) {
            $date = $this->csvRecord($header, $rows[0])['test_date'] ?? '';
        }
        if ($date === '') throw new InvalidArgumentException('Die CSV enthält kein Prüfdatum.');
        $correlationId = 'measurement-import-' . bin2hex(random_bytes(8));
        $updated = 0; $skipped = 0; $needsCableLength = 0; $updatedInspections = [];
        $inspectionsBySlot = [];
        // A lone Benning CSV supplements a Prüfweb entry. It must never
        // attach its measurements to an imported history row merely because
        // that row has the same date and export-local storage slot.
        foreach (R::findAll('inspection', " test_date = ? AND source_type = 'manual' ORDER BY id DESC ", [$date]) as $candidate) {
            $candidateSlot = trim((string) ($candidate->storage_slot ?? ''));
            if ($candidateSlot === '') continue;
            $key = preg_match('/^\d+$/', $candidateSlot) ? (string) ((int) $candidateSlot) : $candidateSlot;
            if (!isset($inspectionsBySlot[$key])) $inspectionsBySlot[$key] = $candidate;
        }
        foreach ($rows as $row) {
            $record = $this->csvRecord($header, $this->repairDecimalColumns($header, $row));
            $slot = trim((string) ($record['storage_slot'] ?? ''));
            if ($slot === '') { $skipped++; $this->auditSkipped(['_audit_correlation_id' => $correlationId], $csvPath, 'Speicherplatz fehlt in der Messdatenzeile.'); continue; }
            $slotKey = preg_match('/^\d+$/', $slot) ? (string) ((int) $slot) : $slot;
            $inspection = $inspectionsBySlot[$slotKey] ?? null;
            if (!$inspection) { $skipped++; $this->auditSkipped(['_audit_correlation_id' => $correlationId], $csvPath, 'Keine bestehende Prüfung für den Speicherplatz gefunden.', ['storage_slot' => $slot]); continue; }
            $inspection->measurements_json = json_encode($record['measurements'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $inspection->csv_row_json = json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $inspection->storage_slot = $slot;
            $measurements = is_array($record['measurements'] ?? null) ? $record['measurements'] : [];
            $failed = false; $unclear = false; $evaluationReasons = [];
            if ($measurements === []) { $unclear = true; $evaluationReasons[] = 'keine Messwerte'; }
            if (str_contains(strtolower((string) ($record['device_type'] ?? '')), 'kabel') && trim((string) ($record['cable_length_m'] ?? '')) === '') $unclear = true;
            foreach ($measurements as $measurement) {
                $name = strtoupper(trim((string) ($measurement['name'] ?? '')));
                if (!in_array($name, ['RPE', 'RSL', 'IPE', 'IBER', 'IEA', 'RISO', 'FI/RCD', 'SICHTPRÜFUNG', 'KABEL'], true)) continue;
                $measurementStatus = strtolower(trim((string) ($measurement['result'] ?? '')));
                if (in_array($measurementStatus, ['nicht bestanden', 'failed', 'fail', 'nein', 'nicht_ok', 'nok'], true)) { $failed = true; break; }
                if ($measurementStatus === '' && trim((string) ($measurement['value'] ?? '')) !== '') { $unclear = true; $evaluationReasons[] = $name . '-Ergebnis fehlt'; }
                elseif ($measurementStatus !== '' && !in_array($measurementStatus, ['bestanden', 'ok', 'passed', 'ja', 'gut'], true)) { $unclear = true; $evaluationReasons[] = $name . '-Ergebnis unklar'; }
                $numeric = $this->measurementNumber((string) ($measurement['value'] ?? ''));
                if ($numeric === null) continue;
                if (in_array($name, ['RPE', 'RSL'], true)) {
                    $lengthRaw = trim((string) ($record['cable_length_m'] ?? $inspection->cable_length_m ?? ''));
                    if ($lengthRaw === '' && $numeric > 0.3) {
                        $unclear = true; $needsCableLength++; $evaluationReasons[] = 'Kabellänge fehlt für RPE-Grenzwert';
                    } else {
                        $length = (float) str_replace(',', '.', $lengthRaw);
                        $limit = $length > 0 ? min(1, 0.3 + max(0, (int) ceil(($length - 5) / 7.5)) * 0.1) : 0.3;
                        if ($numeric > $limit) { $failed = true; $evaluationReasons[] = 'RPE-Grenzwert überschritten'; }
                    }
                } elseif ($name === 'IPE' && $numeric > 3.5) { $failed = true; $evaluationReasons[] = 'IPE-Grenzwert überschritten'; }
                elseif ($name === 'RISO') {
                    $device = R::load('device', (int) ($inspection->device_id ?? 0));
                    $minimum = ((int) ($device->warming_device ?? 0) === 1) ? 0.3 : 1.0;
                    $rawMeasurementValue = trim((string) ($measurement['value'] ?? ''));
                    if (!str_starts_with($rawMeasurementValue, '>') && $numeric < $minimum) { $failed = true; $evaluationReasons[] = 'RISO-Grenzwert unterschritten'; }
                }
            }
            $checklist = json_decode((string) ($inspection->checklist_json ?? ''), true);
            if (is_array($checklist) && $checklist !== []) {
                if (in_array('nein', array_map(static fn($value): string => strtolower(trim((string) $value)), $checklist), true)) $failed = true;
                if (in_array('', $checklist, true)) $unclear = true;
            }
            $unclear = $evaluationReasons !== [];
            $inspection->status = $failed ? 'completed' : ($unclear ? InspectionEvaluationService::DATA_MISSING : 'completed');
            $inspection->result_status = $failed ? InspectionEvaluationService::FAILED : ($unclear ? InspectionEvaluationService::DATA_MISSING : InspectionEvaluationService::PASSED);
            $inspection->updated_at = date(DATE_ATOM);
            R::store($inspection); $updated++;
            audit_log('import_datensatz_aktualisiert', ['_correlation_id' => $correlationId, '_category' => 'import', '_status' => 'aktualisiert', 'source_file' => basename($csvPath), 'inspection_id' => (int) $inspection->id, 'inspection_number' => (string) ($inspection->external_number ?? ''), 'status' => 'aktualisiert']);
            $updatedInspections[] = ['id' => (int) $inspection->id, 'number' => (string) ($inspection->external_number ?? ''), 'status' => (string) $inspection->result_status, 'evaluation_reasons' => $evaluationReasons];
        }
        $stats = ['files' => 1, 'updated' => $updated, 'skipped' => $skipped, 'cable_length_required' => $needsCableLength, 'updated_inspections' => $updatedInspections, 'imported' => 0, 'devices' => 0, 'reports' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => [], 'errors' => []];
        $this->persistImportLog($stats + ['type' => 'Pending-Messdaten-CSV', 'date' => $date]);
        audit_log('import_abgeschlossen', ['_correlation_id' => $correlationId, '_category' => 'import', '_status' => 'abgeschlossen', 'source_file' => basename($csvPath), 'stats' => $stats]);
        return $stats;
    }

    private function persistImportLog(array $stats): void
    {
        $root = app_data_root() . '/import-logs';
        if (!is_dir($root)) @mkdir($root, 0770, true);
        if (is_dir($root)) @file_put_contents($root . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['created_at' => date(DATE_ATOM), 'type' => (string) ($stats['type'] ?? 'Import'), 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int,skipped:int} */
    private function importJsonFile(string $path, string $root, bool $jsonLines = false, array $defaults = []): array
    {
        if ($jsonLines) {
            $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => []];
            $handle = fopen($path, 'rb');
            if ($handle === false) throw new RuntimeException('JSONL-Datei konnte nicht geöffnet werden.');
            try {
                while (($line = fgets($handle)) !== false) {
                    if (trim($line) === '') continue;
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded) || trim((string) ($decoded['number'] ?? '')) === '') { $result['skipped']++; $this->auditSkipped($defaults, $path, 'Datensatz enthält keine Prüfnummer.'); continue; }
                    if (($reason = $this->ignoredInspectionTypeReason($decoded)) !== '') {
                        $result['skipped']++;
                        $this->auditSkipped($defaults, $path, $reason, ['inspection_number' => (string) $decoded['number']]);
                        continue;
                    }
                    $one = $this->importRecord(array_merge($defaults, $decoded), 'json', $path, $root);
                    foreach ($one as $key => $value) {
                        if (in_array($key, ['new_devices', 'updated_devices', 'not_imported'], true) && is_array($value)) $result[$key] = array_merge($result[$key] ?? [], $value);
                        elseif (array_key_exists($key, $result) && is_int($value)) $result[$key] += $value;
                    }
                }
            } finally { fclose($handle); }
            return $result;
        }
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $records = [];
        if (isset($data['number']) && is_array($data)) {
            $records[] = $data;
        } elseif (isset($data['resources']['data']) && is_array($data['resources']['data'])) {
            $records = $data['resources']['data'];
        } elseif (is_array($data) && array_is_list($data)) {
            $records = $data;
        }

        return $this->importJsonRecords($records, $path, $root, $defaults);
    }

    private function importJsonRecords(array $records, string $path, string $root, array $defaults = []): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => []];
        $matchedSlots = [];
        foreach ($records as $record) {
            if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') {
                $result['skipped']++;
                $this->auditSkipped($defaults, $path, 'Datensatz enthält keine Prüfnummer.');
                continue;
            }
            if (($reason = $this->ignoredInspectionTypeReason($record)) !== '') {
                $result['skipped']++;
                $this->auditSkipped($defaults, $path, $reason, ['inspection_number' => (string) $record['number']]);
                continue;
            }
            $one = $this->importRecord(array_merge($defaults, $record), 'json', $path, $root);
            foreach ($one as $key => $value) { if (in_array($key, ['new_devices', 'updated_devices'], true)) $result[$key] = array_merge($result[$key] ?? [], $value); else $result[$key] += $value; }
        }

        return $result;
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int,skipped:int} */
    private function importCsvFile(string $path, string $root, array $defaults = []): array
    {
        $contents = (string) file_get_contents($path);
        $contents = str_replace("\0", '', $contents);
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        $delimiter = substr_count((string) strtok($contents, "\r\n"), ';') >= 3 ? ';' : ',';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, 0, $delimiter);
        if (!is_array($header)) { $this->auditSkipped($defaults, $path, 'CSV enthält keine Kopfzeile.'); return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1, 'reason' => 'CSV enthält keine Kopfzeile.']; }
        $header = $this->uniqueHeaders($header);
        $hasBenning = $this->findColumn($header, ['speicher nr', 'speichernr', 'speicherplatz']) !== null;
        $hasLegacy = $this->findColumn($header, ['number', 'nummer', 'prüfungsnr']) !== null;
        $headerlessRows = null;
        if (!$hasBenning && !$hasLegacy) {
            // Benning exports can start with a binary marker and contain no
            // header at all.  In that format the first column is Speicher Nr.
            rewind($stream);
            $headerlessRows = [];
            while (($candidate = fgetcsv($stream, 0, $delimiter)) !== false) {
                if (preg_match('/^\s*\d+\s*$/', (string) ($candidate[0] ?? '')) && count($candidate) >= 3) {
                    $headerlessRows[] = $candidate;
                }
            }
            if ($headerlessRows === []) { $this->auditSkipped($defaults, $path, 'Kein Speicher-Nr.-Header und keine gültigen Benning-Datensätze erkannt.'); return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1, 'reason' => 'Kein Speicher-Nr.-Header und keine gültigen Benning-Datensätze erkannt.']; }
            $header = $this->benningHeaders();
        }

        $odsPath = $this->matchingOdsPath($path);
        // A Benning CSV can legitimately arrive without its companion ODS.
        // In that situation it contains measurement data, but no reliable
        // device master data.  Do not create guessed devices: attach it to
        // the already imported inspection via its stable test date and
        // Speicherplatz instead.
        if ($hasBenning && $odsPath === null) {
            return $this->importPendingMeasurements($path, trim((string) ($defaults['test_date'] ?? '')));
        }
        $ods = $this->readOds($odsPath);
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => []];
        $rows = $headerlessRows ?? null;
        while (true) {
            $row = $rows !== null ? array_shift($rows) : fgetcsv($stream, 0, $delimiter);
            if ($row === null || $row === false) break;
            if (count(array_filter($row, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
                $record = $this->csvRecord($header, $this->repairDecimalColumns($header, $row));
            if (trim((string) ($defaults['test_date'] ?? '')) !== '') $record['test_date'] = (string) $defaults['test_date'];
            $slot = trim((string) ($record['storage_slot'] ?? ''));
            $odsRow = $slot !== '' ? ($ods[$slot] ?? $ods[ltrim($slot, '0')] ?? null) : null;
            if ($odsPath !== null && !is_array($odsRow)) {
                $result['skipped']++;
                $result['not_imported'][] = ['storage_slot' => $slot, 'source' => 'CSV', 'reason' => 'Speicherplatz fehlt in der ODS'];
                $this->auditSkipped($defaults, $path, 'Speicherplatz fehlt in der ODS.', ['storage_slot' => $slot, 'source' => 'CSV']);
                continue;
            }
            if (is_array($odsRow)) {
                $matchedSlots[ltrim($slot, '0')] = true;
                $record = $this->mergeOdsRecord($record, $odsRow);
                if (is_array($record['raw'] ?? null) && trim((string) ($odsRow['regie_time_raw'] ?? '')) !== '') {
                    // Keep the original ODS value as evidence.  Older
                    // imports discarded it after joining CSV and ODS rows.
                    $record['raw']['ods_regiezeit'] = (string) $odsRow['regie_time_raw'];
                }
                // In the ODS, “Notiz Gerät” is the actual device description;
                // the CSV's Bezeichnung is only the protection class.
                if (trim((string) ($record['device_note'] ?? '')) !== '') $record['device_type'] = trim((string) $record['device_note']);
            }
            if (trim((string) ($record['external_number'] ?? '')) === '-' || trim((string) ($record['external_number'] ?? '')) === '') {
                $record['external_number'] = trim((string) ($record['legacy_number'] ?? '')) ?: $slot;
            }
            if (($record['external_number'] ?? '') === '' && ($record['storage_slot'] ?? '') === '') {
                $result['skipped']++;
                $this->auditSkipped($defaults, $path, 'Prüfnummer und Speicherplatz fehlen.', ['source' => 'CSV']);
                continue;
            }
            $one = $this->importRecord(array_merge($defaults, $record), 'csv', $path, $root);
            foreach ($one as $key => $value) { if (in_array($key, ['new_devices', 'updated_devices'], true)) $result[$key] = array_merge($result[$key] ?? [], $value); else $result[$key] += $value; }
        }
        if ($odsPath !== null && $ods !== []) {
            foreach ($ods as $odsRow) {
                if (!is_array($odsRow)) continue;
                $odsSlot = trim((string) ($odsRow['storage_slot'] ?? ''));
                $key = ltrim($odsSlot, '0');
                if ($odsSlot !== '' && !isset($matchedSlots[$key])) {
                    $result['skipped']++;
                    $result['not_imported'][] = ['storage_slot' => $odsSlot, 'source' => 'ODS', 'reason' => 'Speicherplatz fehlt in der CSV'];
                    $this->auditSkipped($defaults, $odsPath, 'Speicherplatz fehlt in der CSV.', ['storage_slot' => $odsSlot, 'source' => 'ODS']);
                    $matchedSlots[$key] = true;
                }
            }
        }
        fclose($stream);

        return $result;
    }

    /** Recombine decimal values exported as two semicolon-separated fields. */
    private function repairDecimalColumns(array $header, array $row): array
    {
        $valueColumns = [];
        foreach ($header as $index => $name) {
            if (str_ends_with(mb_strtolower(trim((string) $name)), 'wert')) $valueColumns[] = (int) $index;
        }
        rsort($valueColumns);
        while (count($row) > count($header)) {
            $merged = false;
            foreach ($valueColumns as $index) {
                $left = trim((string) ($row[$index] ?? ''));
                $right = trim((string) ($row[$index + 1] ?? ''));
                $unitColumn = trim((string) ($header[$index + 1] ?? ''));
                if ($unitColumn !== '' && preg_match('/^[<>]?\d+$/', $left) && preg_match('/^\d{1,2}$/', $right)) {
                    $row[$index] = $left . '.' . $right;
                    array_splice($row, $index + 1, 1);
                    $merged = true;
                    break;
                }
            }
            if (!$merged) break;
        }
        return $row;
    }

    private function measurementNumber(string $value): ?float
    {
        if (preg_match('/([<>]?\s*\d+(?:[.,]\d+)?)/', trim($value), $match) !== 1) return null;
        return (float) str_replace(',', '.', preg_replace('/\s+/', '', $match[1]));
    }

    /** @return array<string, string> */
    private function csvRecord(array $header, array $row): array
    {
        $values = [];
        foreach ($header as $index => $name) $values[$name] = trim((string) ($row[$index] ?? ''));
        $slot = $this->value($values, ['Speicher Nr', 'Speicherplatz', 'speichernr', 'memory number']);
        $number = $this->value($values, ['number', 'Nummer', 'Prüfungsnr']);
        $date = $this->value($values, ['Prüfdatum', 'date', 'Datum']);
        $measurements = [];
        $known = ['RPE', 'IPE', 'IBer', 'IEA', 'RISO', 'Kabel', 'FI/RCD', 'Sichtprüfung'];
        foreach ($known as $prefix) {
            $value = $this->value($values, [$prefix . ' Wert', strtolower($prefix) . ' value']);
            $unit = $this->value($values, [$prefix . ' Einheit', strtolower($prefix) . ' unit']);
            $status = $this->value($values, [$prefix . ' Ergebnis', strtolower($prefix) . ' result']);
            if ($value !== '' || $unit !== '' || $status !== '') {
                $measurement = ['name' => $prefix, 'value' => $value, 'unit' => $unit, 'result' => $status];
                if ($prefix === 'RISO') $measurement['voltage'] = $this->value($values, ['RISO Spannung', 'RISO voltage']);
                $measurements[] = $measurement;
            }
        }
        $metadataColumns = ['Speicher Nr', 'Speicherplatz', 'Prüfdatum', 'date', 'Datum', 'Prüfergebnis', 'number', 'Nummer', 'Prüfungsnr', 'Bezeichnung', 'Prüfart', 'Typ', 'Hersteller', 'Modell', 'Raumnummer', 'Raum'];
        foreach ($values as $key => $value) {
            if ($value === '' || $key === 'RISO Spannung' || in_array($key, $header, true) && (str_contains(strtolower($key), 'wert') || str_contains(strtolower($key), 'einheit') || str_contains(strtolower($key), 'ergebnis'))) continue;
            // “Bezeichnung” in a Benning export means protection class, not a
            // measurement. Keep it in raw_json, but never present it as one.
            if (in_array($key, $metadataColumns, true)) continue;
            if (count($measurements) < 30) $measurements[] = ['name' => $key, 'value' => $value, 'unit' => '', 'result' => ''];
        }
        $cableLength = $this->value($values, ['Kabellänge', 'Leitungslänge', 'cable_length_m', 'cable_length']);
        $regieRaw = $this->value($values, ['Regiezeit', 'Regiezeit (Min.)', 'Regiezeit Minuten', 'regie_minutes']);
        return [
            'external_number' => $number,
            'storage_slot' => $slot,
            'cable_length_m' => $cableLength,
            'test_date' => $this->normalizeDate($date),
            'result_status' => $this->status($this->value($values, ['Prüfergebnis', 'OK', 'audit_ok', 'result'])),
            'inspection_type' => $this->value($values, ['Bezeichnung', 'Prüfart', 'inspection_type', 'type']),
            'device_type' => $this->value($values, ['Bezeichnung', 'Typ', 'device_type']),
            'manufacturer' => $this->value($values, ['Hersteller', 'manufacturer']),
            'device_model' => $this->value($values, ['Modell', 'device_model']),
            'room_snapshot' => $this->value($values, ['Raumnummer', 'Raum', 'room']),
            'regie_minutes' => $this->normalizeRegieMinutes($regieRaw),
            'regie_time_raw' => $regieRaw,
            'measurements' => $measurements,
            'checklist' => [],
            'raw' => $values,
        ];
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int} */
    private function importRecord(array $record, string $sourceType, string $sourcePath, string $root): array
    {
        $record = $this->applyImportRules($record);
        $rawExternal = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        $date = $this->normalizeDate((string) ($record['test_date'] ?? $record['date'] ?? ''));
        $external = $this->yearNumber($rawExternal, $date);
        $dedupe = hash('sha256', implode('|', [$sourceType, $external, $slot, $date, (string) ($record['result_status'] ?? '')]));
        $inspection = R::findOne('inspection', ' dedupe_key = ? ', [$dedupe]);
        if ($inspection === null && $rawExternal !== $external) {
            $legacyDedupe = hash('sha256', implode('|', [$sourceType, $rawExternal, $slot, $date, (string) ($record['result_status'] ?? '')]));
            $inspection = R::findOne('inspection', ' dedupe_key = ? ', [$legacyDedupe]);
        }
        // An import is evidence for a particular inspection, not authority to
        // move the device master record.  In particular, re-importing a 2025
        // Phoenix/Benning export must not put a device back into its old room
        // or replace newer manufacturer/model data.
        $deviceResult = null;
        if ($inspection !== null && (int) ($inspection->device_id ?? 0) > 0) {
            $existingDevice = R::load('device', (int) $inspection->device_id);
            if ((int) $existingDevice->id > 0) $deviceResult = ['device' => $existingDevice, 'created' => false];
        }
        if ($deviceResult === null) {
            $deviceResult = $this->findOrCreateDevice($record);
            // The source's storage slot and result status are useful data, but
            // neither identifies a separate inspection.  Older Phoenix CSVs
            // occasionally change one of them between exports.  The former
            // dedupe key consequently created a second completed row with the
            // same device, inspection number and date on a re-import.  A
            // completed inspection number is immutable, so this narrower
            // fallback is safe: real re-tests receive their own number.
            if ($inspection === null && $external !== '' && $date !== '') {
                $inspection = R::findOne(
                    'inspection',
                    ' device_id = ? AND source_type = ? AND external_number = ? AND test_date = ? ORDER BY id ASC ',
                    [(int) $deviceResult['device']->id, $sourceType, $external, $date]
                );
            }
            // A measurement export can arrive shortly after the inspector opened
            // the same annual inspection manually. It supplements that unfinished
            // row; it must never create a misleading "-2" inspection.
            if ($inspection === null) {
                $inspection = $this->findOpenInspectionForImport((int) $deviceResult['device']->id, $external);
            }
        }
        $created = $inspection === null;
        $inspection ??= R::dispense('inspection');
        if (trim((string) ($inspection->public_id ?? '')) === '') $inspection->public_id = 'prf_' . bin2hex(random_bytes(16));
        if ($created || (int) ($inspection->device_id ?? 0) <= 0) $inspection->device_id = (int) $deviceResult['device']->id;
        $inspection->dedupe_key = $dedupe;
        $inspection->source_type = $sourceType;
        $inspection->source_file = basename($sourcePath);
        $inspection->external_number = $created ? $this->uniqueInspectionNumber($external) : $external;
        $inspection->legacy_number = $this->yearNumber(trim((string) ($record['legacy_number'] ?? '')), $date);
        $inspection->storage_slot = $slot;
        $inspection->cable_length_m = trim((string) ($record['cable_length_m'] ?? $record['cable_length'] ?? ''));
        $length = (float) str_replace(',', '.', (string) $inspection->cable_length_m);
        $inspection->rsl_limit_ohm = $length > 0 ? min(1, 0.3 + max(0, (int) ceil(($length - 5) / 7.5)) * 0.1) : 0.3;
        $inspection->test_date = $date;
        $started = $this->recordValueByNormalizedKeys($record, ['startedat', 'teststart', 'inspectionstart', 'pruefbeginn', 'pruefungsbeginn', 'starttime']);
        $finished = $this->recordValueByNormalizedKeys($record, ['finishedat', 'testend', 'inspectionend', 'pruefende', 'pruefungsende', 'endtime']);
        $startedAt = $started['found'] ? $this->normalizeDateTime($started['value'], $date) : '';
        $finishedAt = $finished['found'] ? $this->normalizeDateTime($finished['value'], $date) : '';
        if ($startedAt !== '') $inspection->started_at = $startedAt;
        if ($finishedAt !== '') $inspection->finished_at = $finishedAt;
        $duration = $this->recordValueByNormalizedKeys($record, ['durationminutes', 'testdurationminutes', 'inspectiondurationminutes', 'pruefdauerminuten', 'pruefdauerminuten', 'durationmin']);
        if ($duration['found']) $inspection->duration_minutes = max(0, $this->normalizeRegieMinutes($duration['value']));
        elseif ($startedAt !== '' && $finishedAt !== '') {
            try { $inspection->duration_minutes = max(0, (int) round((strtotime($finishedAt) - strtotime($startedAt)) / 60)); } catch (Throwable) { /* keep existing */ }
        }
        $nextDue = $this->normalizeDate((string) ($record['next_due_date'] ?? $record['next_audit'] ?? ''));
        // Phoenix occasionally exports the inspection date again as the next
        // due date. That is not a usable interval; use the configured
        // relative fallback (or one year for legacy syncs) instead.
        if ($nextDue !== '' && $date !== '' && $nextDue <= $date) {
            $fallbackDays = (int) ($record['next_due_offset_days'] ?? 365);
            $nextDue = date('Y-m-d', strtotime($date . ' +' . max(1, $fallbackDays) . ' days'));
        }
        if ($nextDue === '' && (int) ($record['next_due_offset_days'] ?? 0) > 0 && $date !== '') $nextDue = date('Y-m-d', strtotime($date . ' +' . (int) $record['next_due_offset_days'] . ' days'));
        $inspection->next_due_date = $nextDue;
        $derivedProtectionClass = $this->protectionClassFromRecord($record);
        if ($derivedProtectionClass !== '') $inspection->protection_class = $derivedProtectionClass;
        $inspection->inspection_type = InspectionEvaluationService::canonicalInspectionType(
            $this->scalarImportValue($record['inspection_type'] ?? $record['type'] ?? ''),
            $derivedProtectionClass
        );
        $inspection->examiner = $this->scalarImportValue($record['examiner'] ?? $record['created_by'] ?? '');
        $regie = $this->regieFromRecord($record);
        if ($regie['found']) {
            $inspection->regie_minutes = $this->normalizeRegieMinutes($regie['value']);
            if (is_array($record['raw'] ?? null)) $record['raw']['import_regie_field'] = $regie['field'];
        }
        $regieReason = $this->recordValueByNormalizedKeys($record, ['regiereason', 'regiegrund', 'mehraufwandgrund', 'additionalworkreason']);
        if ($regieReason['found']) $inspection->regie_reason = trim((string) $regieReason['value']);
        $sourceResult = InspectionEvaluationService::normalizeStatus(
            (string) ($record['result_status'] ?? $this->status($record['audit_ok'] ?? null))
        );
        $inspection->result_status = $sourceResult;
        $importVocabulary = DeviceVocabularyService::canonicalizeDeviceValues([
            'name' => (string) ($record['device_type'] ?? ''),
            'manufacturer' => (string) ($record['manufacturer'] ?? ''),
            'device_model' => (string) ($record['device_model'] ?? ''),
        ]);
        $inspection->device_type = $importVocabulary['name'];
        $inspection->manufacturer = $importVocabulary['manufacturer'];
        $inspection->device_model = $importVocabulary['device_model'];
        $inspection->room_snapshot = (string) ($record['room_snapshot'] ?? $record['room'] ?? '');
        $inspection->measurements_json = json_encode($record['measurements'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inspection->csv_row_json = json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $importMeasurements = is_array($record['measurements'] ?? null) ? $record['measurements'] : [];
        // The overall result in a Benning CSV is the authoritative source
        // decision.  A missing cable length must not turn an explicitly
        // "bestanden" source row into "nicht bestanden" merely because the
        // generic fallback limit happens to be 0.3 Ohm.  Derive a result from
        // RPE/RSL only when the source did not provide a usable overall one.
        if (!in_array($sourceResult, [InspectionEvaluationService::PASSED, InspectionEvaluationService::FAILED], true)) {
            foreach ($importMeasurements as $measurement) {
                if (!is_array($measurement) || !in_array(strtoupper(trim((string) ($measurement['name'] ?? ''))), ['RSL', 'RPE'], true)) continue;
                $measured = (float) str_replace(',', '.', (string) ($measurement['value'] ?? ''));
                if ($measured > 0 && $measured > (float) $inspection->rsl_limit_ohm) { $inspection->result_status = InspectionEvaluationService::FAILED; break; }
            }
        }
        $inspection->checklist_json = json_encode($record['checklist'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inspection->raw_json = json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $report = $this->copyReport($rawExternal);
        // A supplied source PDF is authoritative, regardless of whether the
        // matching row originated from Phoenix JSON or from a CSV export.
        // Benning imports simply do not provide one and therefore remain
        // eligible for a generated report.
        if ($report !== null) $inspection->report_path = $report;
        $inspection->updated_at = date(DATE_ATOM);
        if (!$inspection->created_at) $inspection->created_at = $inspection->updated_at;
        R::store($inspection);
        $this->persistSourceSnapshot($inspection, $sourceType, $sourcePath, $record, $report);
        if ($report !== null) {
            InspectionDataService::registerReportAsset((int) $inspection->id, 'import_original', app_data_root() . '/' . $report, true);
        }
        $deviceInfo = ['id' => (int) $deviceResult['device']->id, 'number' => $external, 'name' => (string) $deviceResult['device']->name];
        audit_log($created ? 'import_datensatz_importiert' : 'import_datensatz_aktualisiert', [
            '_correlation_id' => (string) ($record['_audit_correlation_id'] ?? ''),
            '_category' => 'import',
            'source_file' => basename($sourcePath),
            'source_type' => $sourceType,
            'inspection_id' => (int) $inspection->id,
            'inspection_number' => (string) $inspection->external_number,
            'device_id' => (int) $deviceResult['device']->id,
            'device_number' => (string) $deviceResult['device']->external_number,
            'status' => $created ? 'importiert' : 'aktualisiert',
        ]);
        return ['imported' => $created ? 1 : 0, 'updated' => $created ? 0 : 1, 'devices' => $deviceResult['created'] ? 1 : 0, 'reports' => $report !== null ? 1 : 0, 'new_devices' => $deviceResult['created'] ? [$deviceInfo] : [], 'updated_devices' => !$deviceResult['created'] ? [$deviceInfo] : []];
    }

    /** @param array<string,mixed> $target @param array<string,mixed> $source */
    private function mergeStats(array &$target, array $source): void
    {
        foreach ($source as $key => $value) {
            if (in_array($key, ['new_devices', 'updated_devices', 'not_imported', 'errors'], true) && is_array($value)) {
                $target[$key] = array_merge((array) ($target[$key] ?? []), $value);
            } elseif (is_int($value)) {
                $target[$key] = (int) ($target[$key] ?? 0) + $value;
            }
        }
    }

    /** @param array<string,mixed> $defaults @param array<string,mixed> $details */
    private function auditSkipped(array $defaults, string $source, string $reason, array $details = []): void
    {
        audit_log('import_datensatz_uebersprungen', array_merge($details, [
            '_correlation_id' => (string) ($defaults['_audit_correlation_id'] ?? ''),
            '_category' => 'import',
            'source_file' => basename($source),
            'status' => 'übersprungen',
            'reason' => $reason,
        ]));
    }

    /** @return array{device:\RedBeanPHP\OODBBean,created:bool} */
    private function findOrCreateDevice(array $record): array
    {
        $external = trim((string) ($record['device_number'] ?? $record['external_number'] ?? $record['number'] ?? ''));
        $legacy = trim((string) ($record['legacy_number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        // The new number is canonical. Prefer an existing new-number device;
        // only fall back to the old device when the new one does not exist yet.
        $device = $external !== '' ? R::findOne('device', ' external_number = ? ', [$external]) : null;
        $device ??= $legacy !== '' && $legacy !== '-' ? R::findOne('device', ' legacy_number = ? ', [$legacy]) : null;
        $device ??= $legacy !== '' && $legacy !== '-' ? R::findOne('device', ' external_number = ? ', [$legacy]) : null;
        $device ??= $external !== '' ? R::findOne('device', ' legacy_number = ? ', [$external]) : null;
        // Speicherplätze are export-local.  They are only a last resort when
        // an old row has no durable device number at all.
        $device ??= $external === '' && $slot !== '' ? R::findOne('device', ' storage_slot = ? ', [$slot]) : null;
        $created = $device === null;
        if (!$created && $legacy !== '' && $legacy !== '-' && $external !== '') {
            $oldDevices = R::findAll('device', ' (legacy_number = ? OR external_number = ?) AND id <> ? ', [$legacy, $legacy, (int) $device->id]);
            foreach ($oldDevices as $duplicate) {
                foreach (['name', 'inventory_number', 'device_model', 'manufacturer', 'serial_number', 'comment', 'room_id', 'room_snapshot'] as $field) {
                    if (trim((string) ($device->$field ?? '')) === '' && trim((string) ($duplicate->$field ?? '')) !== '') $device->$field = $duplicate->$field;
                }
                R::exec('UPDATE inspection SET device_id = ? WHERE device_id = ?', [(int) $device->id, (int) $duplicate->id]);
                R::trash($duplicate);
            }
        }
        $device ??= R::dispense('device');
        if ($created || trim((string) ($device->external_number ?? '')) === '') $device->external_number = $external;
        if ($created || trim((string) ($device->legacy_number ?? '')) === '') $device->legacy_number = $legacy === '-' ? '' : $legacy;
        if ($created || trim((string) ($device->storage_slot ?? '')) === '') $device->storage_slot = $slot;
        if (array_key_exists('warming_device', $record) && ($created || !isset($device->warming_device))) $device->warming_device = filter_var($record['warming_device'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $room = trim((string) ($record['room_snapshot'] ?? $record['room'] ?? ''));
        if ($room !== '' && ($created || trim((string) ($device->room_snapshot ?? '')) === '')) $device->room_snapshot = $room;
        $roomBean = ($created || !(int) ($device->room_id ?? 0)) ? $this->ensureImportedRoom($record, $room) : null;
        if ($roomBean !== null) $device->room_id = (int) $roomBean->id;
        $recordVocabulary = DeviceVocabularyService::canonicalizeDeviceValues([
            'name' => (string) ($record['device_type'] ?? $record['device_model'] ?? ''),
            'manufacturer' => (string) ($record['manufacturer'] ?? ''),
            'device_model' => (string) ($record['device_model'] ?? ''),
        ]);
        $preferredName = $recordVocabulary['name'];
        if ($this->isProtectionClass($preferredName)) $preferredName = '';
        $currentName = trim((string) ($device->name ?? ''));
        $legacyModelName = trim((string) ($record['device_model'] ?? ''));
        if ($preferredName !== '' && ($created || $currentName === '' || $currentName === $external || $currentName === $legacyModelName || str_starts_with($currentName, 'Gerät '))) $device->name = $preferredName;
        if (trim((string) ($device->name ?? '')) === '' || $this->isProtectionClass((string) $device->name)) $device->name = 'Gerät ' . ($external ?: $slot);
        foreach (['device_model' => 'device_model', 'manufacturer' => 'manufacturer', 'serial_number' => 'serial_number', 'inventory_number' => 'inventory_number'] as $target => $source) {
            if (!empty($record[$source]) && ($created || trim((string) ($device->$target ?? '')) === '')) $device->$target = isset($recordVocabulary[$target]) ? $recordVocabulary[$target] : $this->importValue((string) $record[$source]);
        }
        $serial = trim((string) ($record['serial_number'] ?? $record['serial'] ?? ''));
        if ($serial !== '' && ($created || trim((string) ($device->serial_number ?? '')) === '')) $device->serial_number = $this->importValue($serial);
        $description = trim((string) ($record['free_text'] ?? $record['device_note'] ?? ''));
        if ($description !== '' && trim((string) ($device->comment ?? '')) === '') $device->comment = mb_substr($description, 0, 1000);
        if (!empty($record['comment']) && ($created || trim((string) ($device->comment ?? '')) === '')) $device->comment = (string) $record['comment'];
        if ($room !== '' && ($created || !(int) ($device->room_id ?? 0))) {
            $roomBean = $this->findRoomByIdentifier($room);
            if ($roomBean !== null) $device->room_id = (int) $roomBean->id;
        }
        if (!$device->room_id) $device->room_id = 0;
        $device->updated_at = date(DATE_ATOM);
        if (!$device->created_at) $device->created_at = $device->updated_at;
        R::store($device);
        // Historical imports may contain thousands of existing spellings. They
        // are reviewed only through the explicit, resumable admin batch – not
        // as one network job per imported device value.
        return ['device' => $device, 'created' => $created];
    }

    private function findRoomByIdentifier(string $identifier): ?\RedBeanPHP\OODBBean
    {
        $identifier = trim($identifier);
        if ($identifier === '') return null;
        $identifier = preg_replace('/^historischer\s+raum\s+/iu', '', $identifier) ?: $identifier;
        $roomBean = R::findOne('room', ' LOWER(number) = LOWER(?) OR LOWER(name) = LOWER(?) ', [$identifier, $identifier]);
        if ($roomBean !== null) return $roomBean;
        if (!class_exists('StructureController')) {
            $controller = dirname(__DIR__) . '/controllers/StructureController.php';
            if (is_file($controller)) require_once $controller;
        }
        $dotted = preg_match('/^(\d+)\.(\d+)$/', $identifier, $parts) ? [$parts[1], ltrim($parts[2], '0') ?: '0'] : null;
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $identifier) ?: '');
        $best = null; $bestScore = -1;
        foreach (R::findAll('room') as $candidate) {
            $floor = R::load('floor', (int) $candidate->floor_id);
            if (!$floor || !(int) $floor->id) continue;
            $candidateIdentifier = class_exists('StructureController')
                ? StructureController::roomIdentifier($candidate, $floor, null)
                : '';
            $matches = strcasecmp(trim($candidateIdentifier), $identifier) === 0;
            if (!$matches && $normalized !== '') {
                $candidateNumber = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($candidate->number ?: $candidate->name)) ?: '');
                $matches = $candidateNumber === $normalized;
            }
            if (!$matches && $dotted !== null && (string) $floor->code === $dotted[0]) {
                $candidateNumber = ltrim(preg_replace('/^\D+/', '', (string) ($candidate->number ?: $candidate->name)), '0') ?: '0';
                $matches = $candidateNumber === $dotted[1] || str_ends_with($candidateIdentifier, $dotted[1]);
            }
            if (!$matches) continue;
            $building = R::load('building', (int) $floor->building_id);
            $site = $building && $building->id ? R::load('site', (int) $building->site_id) : null;
            $customer = $site && $site->id ? R::load('customer', (int) $site->customer_id) : null;
            $score = (string) ($customer->code ?? '') === 'AK' ? 20 : 0;
            $score += (string) ($site->code ?? '') === 'NKS' ? 10 : 0;
            if ($matches && $score > $bestScore) { $best = $candidate; $bestScore = $score; }
        }
        return $best;
    }

    /** Resolves an imported room without changing an inspection record. */
    public function resolveImportedRoom(array $record): ?\RedBeanPHP\OODBBean
    {
        $room = trim((string) ($record['room_snapshot'] ?? $record['room'] ?? ''));
        return $room === '' ? null : $this->ensureImportedRoom($record, $room);
    }

    private function ensureImportedRoom(array $record, string $room): ?\RedBeanPHP\OODBBean
    {
        if ($room === '') return null;
        $location = trim((string) ($record['location'] ?? ''));
        $locationKey = strtolower($location);
        $level = trim((string) ($record['level'] ?? ''));
        $source = strtolower((string) ($record['_legacy_source'] ?? ''));
        // The Phoenix/Benning AK CSVs often contain room codes such as K016
        // but no explicit location column.  Their source file is sufficient
        // to resolve the known Antoniuskolleg structure instead of leaving
        // the value as an unlinked room snapshot.
        if ($location === '' && str_contains($source, 'ak-elektro')) {
            $location = 'Antoniuskolleg';
            $locationKey = 'antoniuskolleg';
        }
        $known = ['antoniuskolleg' => ['AK', 'NKS', 'Antoniuskolleg'], 'ak' => ['AK', 'NKS', 'Antoniuskolleg'], 'berufskolleg' => ['AK', 'BB', 'Berufskolleg'], 'quickputz gmbh & co.kg' => ['QP', 'QP', 'Quickputz GmbH & Co.KG']];
        if (isset($known[$locationKey])) [$knownCustomer, $knownSiteCode, $knownSiteName] = $known[$locationKey];
        elseif (str_contains($locationKey, 'quickputz')) [$knownCustomer, $knownSiteCode, $knownSiteName] = ['QP', 'QP', 'Quickputz GmbH & Co.KG'];
        else [$knownCustomer, $knownSiteCode, $knownSiteName] = ['', '', ''];
        $isAk = str_contains($source, 'ak-elektro') || $knownCustomer === 'AK';
        $rawCustomer = trim((string) ($record['customer']['company'] ?? ''));
        if ($knownCustomer !== '') { $rawCustomer = $knownCustomer; $location = $knownSiteName; }
        if ($location === '' || str_contains($locationKey, 'ceneos')) return null;
        $isCeneos = str_contains(strtolower($rawCustomer), 'ceneos');
        $customerName = $knownCustomer !== '' ? $knownCustomer : ($isAk ? 'AK' : ($isCeneos ? 'CENEOS GmbH' : ($rawCustomer ?: $location)));
        $customerCode = $this->shortCode($customerName, $knownCustomer !== '' ? $knownCustomer : ($isAk ? 'AK' : ($isCeneos ? 'CNO' : '')));
        $customer = R::findOne('customer', ' code = ? OR name = ? ', [$customerCode, $customerName]);
        if ($customer === null) { $customer = R::dispense('customer'); $customer->name = $customerName; $customer->code = $customerCode; $customer->room_code_pattern = 'auto'; $customer->created_at = date(DATE_ATOM); }
        if ($customerCode === 'AK' && trim((string) ($customer->room_code_pattern ?? '')) === 'auto') $customer->room_code_pattern = '{building}{floor}{room}';
        R::store($customer);
        $siteName = $location ?: $customerName;
        $siteCode = $this->shortCode($siteName, $knownSiteCode ?: ($siteName === 'Antoniuskolleg' ? 'NKS' : ''));
        $site = R::findOne('site', ' customer_id = ? AND (code = ? OR name = ?)', [(int) $customer->id, $siteCode, $siteName]);
        if ($site === null) { $site = R::dispense('site'); $site->customer_id = (int) $customer->id; $site->name = $siteName; $site->code = $siteCode; $site->created_at = date(DATE_ATOM); R::store($site); }
        if ($room === '-' || $room === '') return null;
        $specialKitchen = $customerCode === 'AK' && strtolower($room) === 'küche';
        $specialMensa = $customerCode === 'AK' && strtolower($room) === 'mensa';
        $buildingCode = ($specialKitchen || $specialMensa) ? 'AB' : $this->buildingCode($room, $level);
        $unknownBuilding = $buildingCode === 'Import';
        if ($unknownBuilding) $buildingCode = 'NEU';
        $building = R::findOne('building', ' site_id = ? AND code = ? ', [(int) $site->id, $buildingCode]);
        if ($building === null) { $building = R::dispense('building'); $building->site_id = (int) $site->id; $building->name = $buildingCode === 'AB' ? 'Altbau' : ($unknownBuilding ? 'Neues Gebäude' : $buildingCode); $building->code = $buildingCode; $building->created_at = date(DATE_ATOM); R::store($building); }
        $floorCode = $specialKitchen ? 'U' : ($specialMensa ? '0' : $this->floorCode($level, $room));
        $floor = R::findOne('floor', ' building_id = ? AND code = ? ', [(int) $building->id, $floorCode]);
        if ($floor === null) { $floor = R::dispense('floor'); $floor->building_id = (int) $building->id; $floor->code = $floorCode; $floor->name = ($level === '' && !$specialKitchen && !$specialMensa) ? 'Neue Etage' : $buildingCode . $floorCode; $floor->sort_order = $floorCode === 'U' ? -100 : 0; $floor->room_code_pattern = ($specialKitchen || $specialMensa) ? '{building}{room}' : ''; $floor->created_at = date(DATE_ATOM); R::store($floor); }
        if ($level === '' && !$specialKitchen && !$specialMensa && trim((string) $floor->name) === $buildingCode . $floorCode) { $floor->name = 'Neue Etage'; R::store($floor); }
        if ($specialKitchen || $specialMensa) { $floor->room_code_pattern = '{building}{room}'; R::store($floor); }
        if ($specialKitchen || $specialMensa) $room = $specialKitchen ? 'KU' : 'ME';
        $roomNumber = $this->roomPart($room, $buildingCode, $floorCode);
        $shortUnderfloor = strcasecmp($floorCode, 'U') === 0 && strncasecmp($room, $buildingCode . 'U', 2) === 0 ? substr($room, 2) : '';
        $roomBean = R::findOne('room', ' floor_id = ? AND (number = ? OR name = ? OR number = ? OR name = ? OR number = ? OR name = ?)', [(int) $floor->id, $roomNumber, $roomNumber, $room, $room, $shortUnderfloor, $shortUnderfloor]);
        if ($roomBean !== null) { $roomBean->number = $roomNumber; $roomBean->name = $roomNumber; }
        if ($roomBean === null) { $roomBean = R::dispense('room'); $roomBean->floor_id = (int) $floor->id; $roomBean->number = $roomNumber; $roomBean->name = $roomNumber; $roomBean->created_at = date(DATE_ATOM); R::store($roomBean); }
        return $roomBean;
    }

    private function roomPart(string $room, string $buildingCode, string $floorCode): string
    {
        if (strcasecmp($floorCode, 'U') === 0 && strncasecmp($room, $buildingCode . 'U', 2) === 0) return $room;
        if (str_contains($room, '-')) {
            if (preg_match('/^(?:' . preg_quote($buildingCode, '/') . ')?(\d+)/i', $room, $range)) return $this->shortNumericRoom($range[1]);
            return $room;
        }
        $prefix = $buildingCode . $floorCode;
        if ($prefix !== '' && strncasecmp($room, $prefix, strlen($prefix)) === 0) {
            $part = trim(substr($room, strlen($prefix)));
            if ($part !== '' && ctype_digit($part)) return $part;
        }
        if (preg_match('/^' . preg_quote($buildingCode, '/') . '(\d+)$/i', $room, $match)) {
            return $this->shortNumericRoom($match[1]);
        }
        return $room;
    }

    private function shortNumericRoom(string $number): string
    {
        return str_pad(ltrim($number, '0') ?: '0', 2, '0', STR_PAD_LEFT);
    }

    private function buildingCode(string $room, string $level): string
    {
        if (preg_match('/^([A-Za-z]+)[-_]?(?:U|UG|K|\-?\d)/', $level, $m)) return strtoupper($m[1]);
        return preg_match('/^([A-Za-z]+)/', $room, $m) ? strtoupper($m[1]) : 'Import';
    }

    private function floorCode(string $level, string $room): string
    {
        if (preg_match('/(?:^|[A-Za-z])(-?\d+|U|UG|K)$/i', $level, $m)) return strtoupper($m[1]);
        if (preg_match('/^[A-Za-z]+(\d)/', $room, $m)) return $m[1];
        return '0';
    }

    private function shortCode(string $name, string $preferred = ''): string
    {
        if ($preferred !== '') return $preferred;
        $parts = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return strtoupper(implode('', array_map(static fn(string $part): string => $part[0], $parts))) ?: 'IMP';
    }

    private function importValue(string $value): string
    {
        return strtolower(trim($value)) === 'n.e.' ? 'nicht erkennbar' : trim($value);
    }

    private function scalarImportValue(mixed $value): string
    {
        if (is_array($value)) foreach (['brezel_name', 'name', 'email'] as $key) if (isset($value[$key]) && is_scalar($value[$key])) return (string) $value[$key];
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Reject Phoenix records that are not electrical equipment inspections. */
    private function ignoredInspectionTypeReason(array $record): string
    {
        $type = mb_strtolower($this->scalarImportValue($record['inspection_type'] ?? $record['type'] ?? ''), 'UTF-8');
        if (str_contains($type, 'unterweisungsnachweis')) return 'Nicht-elektrischer Unterweisungsnachweis wird nicht importiert.';
        if (str_contains($type, 'übergabe messgerät') || str_contains($type, 'uebergabe messgeraet')) return 'Messgeräte-Übergabe wird nicht als Geräteprüfung importiert.';
        return '';
    }

    private function protectionClassFromRecord(array $record): string
    {
        $values = [];
        foreach (['protection_class', 'inspection_type', 'type', 'device_type', 'device_model'] as $field) {
            $value = $this->scalarImportValue($record[$field] ?? '');
            if ($value !== '') $values[] = mb_strtolower($value);
        }
        $text = implode(' ', $values);
        if (preg_match('/\b(?:schutzklasse|klasse|sk)\s*(i{1,3}|[123])\b/u', $text, $match)) {
            $token = strtolower($match[1]);
            if ($token === '3' || $token === 'iii') return 'III';
            if ($token === '2' || $token === 'ii') return 'II';
            return 'I';
        }
        if (str_contains($text, 'drehstrom') || str_contains($text, 'cee')) return 'Drehstrom';
        if (str_contains($text, 'kabel')) return 'Kabel';
        return '';
    }

    private function uniqueInspectionNumber(string $base): string
    {
        $candidate = $base;
        $suffix = 2;
        while (R::count('inspection', ' external_number = ? ', [$candidate]) > 0) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    private function findOpenInspectionForImport(int $deviceId, string $external): ?\RedBeanPHP\OODBBean
    {
        if ($deviceId <= 0 || $external === '') return null;
        return R::findOne('inspection', "device_id = ? AND external_number = ?
            AND TRIM(COALESCE(report_path, '')) = ''
            AND (COALESCE(result_status, '') IN ('', 'in_progress', 'data_missing', 'pending')
                OR COALESCE(status, '') IN ('', 'in_progress', 'data_missing', 'pending', 'draft'))
            ORDER BY id DESC", [$deviceId, $external]);
    }

    private function applyImportRules(array $record): array
    {
        $rules = is_array($record['import_rules'] ?? null) ? $record['import_rules'] : [];
        $best = null; $bestScore = -1;
        $number = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $room = mb_strtolower(trim((string) ($record['room_snapshot'] ?? $record['room'] ?? '')));
        $device = mb_strtolower(trim((string) ($record['device_type'] ?? $record['device_model'] ?? '')));
        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            $score = 0; $matches = true;
            foreach ([['number', $number], ['room', $room], ['device', $device]] as [$key, $actual]) {
                $expected = mb_strtolower(trim((string) ($rule[$key] ?? '')));
                if ($expected !== '') { if ($expected !== $actual) { $matches = false; break; } $score++; }
            }
            if ($matches && $score > $bestScore) { $best = $rule; $bestScore = $score; }
        }
        if (is_array($best)) foreach (['inspection_type', 'examiner', 'next_due_date', 'next_due_offset_days'] as $field) if (trim((string) ($best[$field] ?? '')) !== '') $record[$field] = $best[$field];
        unset($record['import_rules']);
        return $record;
    }

    private function isProtectionClass(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['klasse i', 'klasse ii', 'kabel'], true);
    }

    private function indexReports(string $root, bool $recursive = true): void
    {
        $this->reportsByNumber = [];
        if (!$recursive) {
            foreach (glob($root . '/*.pdf') ?: [] as $path) {
                $file = new SplFileInfo($path);
                if (preg_match('/^(\d+)/', $file->getBasename('.pdf'), $match)) $this->reportsByNumber[$match[1]] = $file->getPathname();
            }
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'pdf') continue;
            if (preg_match('/^(\d+)/', $file->getBasename('.pdf'), $match)) $this->reportsByNumber[$match[1]] = $file->getPathname();
        }
    }

    private function copyReport(string $number): ?string
    {
        if ($number === '' || !isset($this->reportsByNumber[$number])) return null;
        if (!is_dir($this->storageRoot)) mkdir($this->storageRoot, 0770, true);
        $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $number . '-' . basename($this->reportsByNumber[$number])) ?: ($number . '.pdf');
        $target = $this->storageRoot . '/' . $name;
        if (!is_file($target)) copy($this->reportsByNumber[$number], $target);
        return 'reports/' . $name;
    }

    /** @param array<string,mixed> $record */
    private function persistSourceSnapshot(
        \RedBeanPHP\OODBBean $inspection,
        string $sourceType,
        string $sourcePath,
        array $record,
        ?string $report
    ): void {
        $inspectionId = (int) $inspection->id;
        if ($inspectionId <= 0) return;
        $sourceRow = json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $originalPath = $report !== null ? app_data_root() . '/' . $report : '';
        $existing = R::getRow('SELECT id, original_report_path, original_report_checksum FROM inspection_source_snapshot WHERE inspection_id = ?', [$inspectionId]);
        if ($existing !== []) {
            // source_row_json intentionally follows the latest import file;
            // legacy_row_json remains the immutable pre-migration snapshot.
            $keepPath = trim((string) ($existing['original_report_path'] ?? ''));
            $path = $originalPath !== '' ? $originalPath : $keepPath;
            $checksum = $path !== '' && is_file($path) ? (string) hash_file('sha256', $path) : (string) ($existing['original_report_checksum'] ?? '');
            R::exec(
                'UPDATE inspection_source_snapshot SET source_type = ?, source_file = ?, source_row_json = ?, original_report_path = ?, original_report_checksum = ? WHERE inspection_id = ?',
                [$sourceType, basename($sourcePath), $sourceRow, $path, $checksum, $inspectionId]
            );
            return;
        }
        $classification = trim((string) ($inspection->classification ?? ''));
        R::exec(
            'INSERT INTO inspection_source_snapshot (inspection_id, classification, source_type, source_file, source_row_json, legacy_row_json, original_report_path, original_report_checksum, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $inspectionId,
                $classification,
                $sourceType,
                basename($sourcePath),
                $sourceRow,
                json_encode($inspection->export(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}',
                $originalPath,
                $originalPath !== '' && is_file($originalPath) ? (string) hash_file('sha256', $originalPath) : '',
                date(DATE_ATOM),
            ]
        );
    }

    /** @return array<string, array<string, mixed>> */
    /**
     * The ODS is an enrichment source. Empty spreadsheet cells must never
     * erase a fact that was present in the paired CSV export.
     *
     * @param array<string,mixed> $record
     * @param array<string,mixed> $odsRow
     * @return array<string,mixed>
     */
    private function mergeOdsRecord(array $record, array $odsRow): array
    {
        foreach ($odsRow as $field => $value) {
            if (is_array($value)) {
                if ($value !== []) $record[$field] = $value;
                continue;
            }
            if (trim((string) $value) !== '') $record[$field] = $value;
        }
        return $record;
    }

    private function readOds(?string $path): array
    {
        if ($path === null || !class_exists('ZipArchive')) return [];
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if (!is_string($xml)) return [];
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) return [];
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $rows = $xpath->query('//table:table-row');
        $header = null; $result = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($xpath->query('./table:table-cell', $row) as $cell) {
                $value = trim($cell->textContent);
                $repeat = (int) ($cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'number-columns-repeated') ?: 1);
                for ($i = 0; $i < $repeat; $i++) $values[] = $value;
            }
            if ($header === null && $this->findColumn($values, ['Speicherplatz']) !== null) {
                $header = $this->uniqueHeaders($values);
                continue;
            }
            if ($header === null || count(array_filter($values, static fn($value): bool => $value !== '')) === 0) continue;
            $rowData = array_combine($header, array_pad($values, count($header), '')) ?: [];
            $slot = $this->value($rowData, ['Speicherplatz']);
            if ($slot !== '') {
                $rowData = [
                'storage_slot' => $slot,
                'legacy_number' => $this->value($rowData, ['Nr. alt', 'Nr alt']),
                'external_number' => $this->value($rowData, ['Nr. neu', 'Nr neu']),
                'room_snapshot' => $this->value($rowData, ['Raumnummer']),
                'comment' => $this->value($rowData, ['Bemerkung/Kommentar']),
                'device_note' => $this->value($rowData, ['Notiz Gerät']),
                // The paired ODS holds the per-device Regiezeit.  It is a
                // minute value in these Benning/Phoenix sheets (e.g. 6), not
                // a device master-data field, and must survive the join.
                'regie_time_raw' => $this->value($rowData, ['Regiezeit', 'Regiezeit (Min.)', 'Regiezeit Minuten']),
                'regie_minutes' => $this->normalizeRegieMinutes($this->value($rowData, ['Regiezeit', 'Regiezeit (Min.)', 'Regiezeit Minuten'])),
                ];
                $result[$slot] = $rowData;
                $result[ltrim($slot, '0')] = $rowData;
            }
        }
        return $result;
    }

    /** Converts known import variants to the canonical whole-minute value. */
    private function normalizeRegieMinutes(mixed $raw): int
    {
        if (is_int($raw)) return max(0, $raw);
        if (is_float($raw)) return max(0, (int) round($raw));
        $text = trim((string) $raw);
        if ($text === '') return 0;
        $hours = preg_match('/(?:\bh\b|stunde)/iu', $text) === 1;
        if (preg_match('/-?\d+(?:[.,]\d+)?/', $text, $match) !== 1) return 0;
        $numberText = str_replace(',', '.', $match[0]);
        $number = (float) $numberText;
        // A naked decimal such as 2,2 is an old decimal-hour export. Integer
        // values in the ODS "Regiezeit" column are already whole minutes.
        if ($hours || (str_contains($numberText, '.') && $number >= 0 && $number <= 24)) $number *= 60;
        return max(0, (int) round($number));
    }

    private function normalizeDateTime(mixed $raw, string $fallbackDate = ''): string
    {
        $text = trim((string) $raw);
        if ($text === '') return '';
        if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $text) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fallbackDate) === 1) $text = $fallbackDate . ' ' . $text;
        try { return (new DateTimeImmutable($text))->format('Y-m-d H:i:s'); }
        catch (Throwable) { return ''; }
    }

    /** @param array<string,mixed> $record @return array{found:bool,value:mixed,field:string} */
    private function regieFromRecord(array $record): array
    {
        $known = $this->recordValueByNormalizedKeys($record, [
            'regieminutes', 'regieminute', 'regiezeit', 'regiezeitminuten', 'regiezeitminute', 'regiezeitmin',
            'regietime', 'regietimeraw', 'regie', 'mehraufwand', 'mehraufwandminuten', 'mehraufwandmin',
            'zusatzaufwand', 'zusatzaufwandminuten', 'arbeitszeit', 'additionalwork', 'additionalworkminutes', 'additionalworktime', 'additionaltime',
            // Phoenix legacy JSONL calls the per-inspection surcharge
            // total_cost_plus / cost_plusN.  Treat it like the other source
            // variants; a zero remains an explicit zero, never an estimate.
            'totalcostplus', 'costplus', 'costplusminutes', 'costplustime',
        ]);
        if ($known['found']) return $known;
        return $this->recordValueByRegiePattern($record);
    }

    /** Finds a scalar import field by key, including one nested JSON object. */
    private function recordValueByNormalizedKeys(array $record, array $acceptedKeys): array
    {
        $wanted = array_fill_keys($acceptedKeys, true);
        $stack = [['data' => $record, 'prefix' => '', 'depth' => 0]];
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ((array) $current['data'] as $key => $value) {
                $field = trim((string) $key);
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $field) ?: '');
                $path = (string) $current['prefix'] . $field;
                if (isset($wanted[$normalized]) && !is_array($value) && trim((string) $value) !== '') {
                    return ['found' => true, 'value' => $value, 'field' => $path];
                }
                if (is_array($value) && (int) $current['depth'] < 8) {
                    $stack[] = ['data' => $value, 'prefix' => $path . '.', 'depth' => (int) $current['depth'] + 1];
                }
            }
        }
        return ['found' => false, 'value' => '', 'field' => ''];
    }

    /** Accept provider-specific JSON keys such as ZusatzaufwandZeit safely. */
    private function recordValueByRegiePattern(array $record): array
    {
        $stack = [['data' => $record, 'prefix' => '', 'depth' => 0]];
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ((array) $current['data'] as $key => $value) {
                $field = trim((string) $key);
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $field) ?: '');
                $path = (string) $current['prefix'] . $field;
                $lowerField = mb_strtolower($field, 'UTF-8');
                $isRegieField = str_contains($normalized, 'regie') || str_contains($normalized, 'mehraufwand') || str_contains($normalized, 'zusatzaufwand') || str_contains($normalized, 'additionalwork') || str_contains($normalized, 'costplus')
                    || preg_match('/zusätz|extra(?:work|aufwand)?|additional|arbeits(?:zeit|aufwand)/u', $lowerField) === 1;
                $isReason = str_contains($normalized, 'grund') || str_contains($normalized, 'reason') || str_contains($normalized, 'comment') || str_contains($normalized, 'note');
                if ($isRegieField && !$isReason && !is_array($value) && trim((string) $value) !== '') {
                    return ['found' => true, 'value' => $value, 'field' => $path];
                }
                if (is_array($value) && (int) $current['depth'] < 8) $stack[] = ['data' => $value, 'prefix' => $path . '.', 'depth' => (int) $current['depth'] + 1];
            }
        }
        return ['found' => false, 'value' => '', 'field' => ''];
    }

    private function matchingOdsPath(string $csvPath): ?string
    {
        $candidate = preg_replace('/\.csv$/i', '.ods', $csvPath);
        return is_string($candidate) && is_file($candidate) ? $candidate : null;
    }

    /** Aggregate exports duplicate the adjacent per-record JSON files. */
    private function isAggregateExport(string $basename): bool
    {
        return in_array(strtolower($basename), ['pruefungen.json', 'result.json', 'result.csv.json'], true);
    }

    /**
     * Returns the durable device facts from the ODS companion of a Benning
     * CSV.  Used by the resumable historical source repair; callers still
     * have to validate the CSV filename, slot and inspection identity.
     *
     * @return array<string,array<string,mixed>>
     */
    public function odsMappingsForCsv(string $csvPath): array
    {
        return $this->readOds($this->matchingOdsPath($csvPath));
    }

    /** @return list<string> */
    private function benningHeaders(): array
    {
        return ['Speicher Nr', 'Bezeichnung', 'Prüfdatum', 'Prüfergebnis', 'RPE Wert', 'RPE Einheit', 'RPE Ergebnis', 'IPE Wert', 'IPE Einheit', 'IPE Ergebnis', 'IBer Wert', 'IBer Einheit', 'IBer Ergebnis', 'IEA Wert', 'IEA Einheit', 'IEA Ergebnis', 'RISO Wert', 'RISO Einheit', 'RISO Ergebnis', 'RISO Spannung', 'Kabel Wert', 'Kabel Einheit', 'Kabel Ergebnis', 'Sichtprüfung Ergebnis', 'FI/RCD Test', 'FI/RCD Wert', 'FI/RCD Einheit', 'FI/RCD Ergebnis'];
    }

    /** @param list<string> $headers */
    private function uniqueHeaders(array $headers): array
    {
        $seen = []; $result = [];
        foreach ($headers as $header) {
            $header = trim((string) $header);
            $base = $header !== '' ? $header : 'Spalte';
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $result[] = $seen[$base] > 1 ? $base . ' ' . $seen[$base] : $base;
        }
        return $result;
    }

    private function findColumn(array $headers, array $wanted): ?string
    {
        $normalized = static fn(string $value): string => preg_replace('/[^a-z0-9]+/i', '', strtolower($value)) ?? '';
        $wanted = array_map($normalized, $wanted);
        foreach ($headers as $header) if (in_array($normalized((string) $header), $wanted, true)) return (string) $header;
        return null;
    }

    private function value(array $values, array $keys): string
    {
        $key = $this->findColumn(array_keys($values), $keys);
        return $key === null ? '' : trim((string) ($values[$key] ?? ''));
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        foreach (['!d/m/Y', '!d.m.y', '!d.m.Y', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) return $date->format('Y-m-d');
        }
        return $value;
    }

    private function status(mixed $value): string
    {
        if (is_bool($value)) return $value ? 'bestanden' : 'nicht bestanden';
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'null') return 'unbekannt';
        if (in_array($value, ['0', 'false', 'failed', 'nicht bestanden', 'nicht_ok'], true)) return 'nicht bestanden';
        return in_array($value, ['bestanden', 'ok', 'true', '1', 'passed'], true) ? 'bestanden' : $value;
    }

    private function yearNumber(string $number, string $date): string
    {
        if ($number === '' || !preg_match('/^(19|20)\d{2}-\d{2}-\d{2}$/', $date)) return $number;
        $suffix = '-' . substr($date, 2, 2);
        return str_ends_with($number, $suffix) ? $number : $number . $suffix;
    }
}
