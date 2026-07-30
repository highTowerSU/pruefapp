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

    private string $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->storageRoot = $storageRoot ?: dirname(__DIR__) . '/data/' . app_storage_namespace() . '/reports';
    }

    /**
     * @return array{files:int, imported:int, updated:int, devices:int, reports:int, skipped:int, errors:list<string>}
     */
    public function importDirectory(string $directory): array
    {
        $source = realpath($directory) ?: '';
        if ($source === '' || (!is_dir($source) && !is_file($source))) {
            throw new InvalidArgumentException('Importverzeichnis oder Importdatei wurde nicht gefunden.');
        }
        $root = is_dir($source) ? $source : dirname($source);
        $this->indexReports($root, is_dir($source));
        $stats = ['files' => 0, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'errors' => []];
        $files = is_file($source)
            ? [new SplFileInfo($source)]
            : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile()) continue;
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['json', 'jsonl', 'csv'], true)) continue;
            if (in_array(strtolower($file->getBasename()), ['pruefungen.json', 'result.csv.json'], true)) continue;
            $stats['files']++;
            try {
                $result = in_array($extension, ['json', 'jsonl'], true)
                    ? $this->importJsonFile($file->getPathname(), $root, $extension === 'jsonl')
                    : $this->importCsvFile($file->getPathname(), $root);
                foreach ($result as $key => $value) $stats[$key] += $value;
            } catch (Throwable $exception) {
                $stats['errors'][] = $file->getPathname() . ': ' . $exception->getMessage();
            }
        }

        return $stats;
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int,skipped:int} */
    private function importJsonFile(string $path, string $root, bool $jsonLines = false): array
    {
        if ($jsonLines) {
            $records = [];
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) $records[] = $decoded;
            }
            return $this->importJsonRecords($records, $path, $root);
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

        return $this->importJsonRecords($records, $path, $root);
    }

    private function importJsonRecords(array $records, string $path, string $root): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0];
        foreach ($records as $record) {
            if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') {
                $result['skipped']++;
                continue;
            }
            $one = $this->importRecord($record, 'json', $path, $root);
            foreach ($one as $key => $value) $result[$key] += $value;
        }

        return $result;
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int,skipped:int} */
    private function importCsvFile(string $path, string $root): array
    {
        $contents = (string) file_get_contents($path);
        $contents = str_replace("\0", '', $contents);
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        $delimiter = substr_count((string) strtok($contents, "\r\n"), ';') >= 3 ? ';' : ',';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, 0, $delimiter);
        if (!is_array($header)) return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1];
        $header = $this->uniqueHeaders($header);
        $hasBenning = $this->findColumn($header, ['speicher nr', 'speichernr', 'speicherplatz']) !== null;
        $hasLegacy = $this->findColumn($header, ['number', 'nummer', 'prüfungsnr']) !== null;
        if (!$hasBenning && !$hasLegacy) return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1];

        $ods = $this->readOds($this->matchingOdsPath($path));
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0];
        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            if (count(array_filter($row, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
            $record = $this->csvRecord($header, $row);
            $slot = trim((string) ($record['storage_slot'] ?? ''));
            if ($slot !== '' && isset($ods[$slot])) $record = array_merge($record, $ods[$slot]);
            if (trim((string) ($record['external_number'] ?? '')) === '-' || trim((string) ($record['external_number'] ?? '')) === '') {
                $record['external_number'] = trim((string) ($record['legacy_number'] ?? '')) ?: $slot;
            }
            if (($record['external_number'] ?? '') === '' && ($record['storage_slot'] ?? '') === '') {
                $result['skipped']++;
                continue;
            }
            $one = $this->importRecord($record, 'csv', $path, $root);
            foreach ($one as $key => $value) $result[$key] += $value;
        }
        fclose($stream);

        return $result;
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
            if ($value !== '' || $unit !== '' || $status !== '') $measurements[] = ['name' => $prefix, 'value' => $value, 'unit' => $unit, 'result' => $status];
        }
        foreach ($values as $key => $value) {
            if ($value === '' || in_array($key, $header, true) && (str_contains(strtolower($key), 'wert') || str_contains(strtolower($key), 'einheit') || str_contains(strtolower($key), 'ergebnis'))) continue;
            if (in_array($key, ['Speicher Nr', 'Speicherplatz', 'Prüfdatum', 'date', 'Datum', 'Prüfergebnis', 'number', 'Nummer', 'Prüfungsnr'], true)) continue;
            if (count($measurements) < 30) $measurements[] = ['name' => $key, 'value' => $value, 'unit' => '', 'result' => ''];
        }
        return [
            'external_number' => $number,
            'storage_slot' => $slot,
            'test_date' => $this->normalizeDate($date),
            'result_status' => $this->status($this->value($values, ['Prüfergebnis', 'OK', 'audit_ok', 'result'])),
            'device_type' => $this->value($values, ['Bezeichnung', 'Typ', 'device_type']),
            'manufacturer' => $this->value($values, ['Hersteller', 'manufacturer']),
            'device_model' => $this->value($values, ['Modell', 'device_model']),
            'room_snapshot' => $this->value($values, ['Raumnummer', 'Raum', 'room']),
            'measurements' => $measurements,
            'checklist' => [],
            'raw' => $values,
        ];
    }

    /** @return array{imported:int,updated:int,devices:int,reports:int} */
    private function importRecord(array $record, string $sourceType, string $sourcePath, string $root): array
    {
        $external = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        $date = $this->normalizeDate((string) ($record['test_date'] ?? $record['date'] ?? ''));
        $dedupe = hash('sha256', implode('|', [$sourceType, $external, $slot, $date, (string) ($record['result_status'] ?? '')]));
        $deviceResult = $this->findOrCreateDevice($record);
        $inspection = R::findOne('inspection', ' dedupe_key = ? ', [$dedupe]);
        $created = $inspection === null;
        $inspection ??= R::dispense('inspection');
        $inspection->device_id = (int) $deviceResult['device']->id;
        $inspection->dedupe_key = $dedupe;
        $inspection->source_type = $sourceType;
        $inspection->source_file = basename($sourcePath);
        $inspection->external_number = $external;
        $inspection->storage_slot = $slot;
        $inspection->test_date = $date;
        $inspection->next_due_date = $this->normalizeDate((string) ($record['next_due_date'] ?? $record['next_audit'] ?? ''));
        $inspection->result_status = (string) ($record['result_status'] ?? $this->status($record['audit_ok'] ?? null));
        $inspection->device_type = (string) ($record['device_type'] ?? '');
        $inspection->manufacturer = (string) ($record['manufacturer'] ?? '');
        $inspection->device_model = (string) ($record['device_model'] ?? '');
        $inspection->room_snapshot = (string) ($record['room_snapshot'] ?? $record['room'] ?? '');
        $inspection->measurements_json = json_encode($record['measurements'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inspection->checklist_json = json_encode($record['checklist'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inspection->raw_json = json_encode($record['raw'] ?? $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $report = $this->copyReport($external);
        if ($report !== null) $inspection->report_path = $report;
        $inspection->updated_at = date(DATE_ATOM);
        if (!$inspection->created_at) $inspection->created_at = $inspection->updated_at;
        R::store($inspection);
        return ['imported' => $created ? 1 : 0, 'updated' => $created ? 0 : 1, 'devices' => $deviceResult['created'] ? 1 : 0, 'reports' => $report !== null ? 1 : 0];
    }

    /** @return array{device:\RedBeanPHP\OODBBean,created:bool} */
    private function findOrCreateDevice(array $record): array
    {
        $external = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $legacy = trim((string) ($record['legacy_number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        $device = $external !== '' ? R::findOne('device', ' external_number = ? ', [$external]) : null;
        $device ??= $legacy !== '' && $legacy !== '-' ? R::findOne('device', ' legacy_number = ? ', [$legacy]) : null;
        $device ??= $slot !== '' ? R::findOne('device', ' storage_slot = ? ', [$slot]) : null;
        $created = $device === null;
        $device ??= R::dispense('device');
        $device->external_number = $external;
        $device->legacy_number = $legacy === '-' ? '' : $legacy;
        $device->storage_slot = $slot;
        $device->name = trim((string) ($device->name ?? '')) ?: trim((string) ($record['device_model'] ?? $record['device_type'] ?? '')) ?: ('Gerät ' . ($external ?: $slot));
        foreach (['device_model' => 'device_model', 'manufacturer' => 'manufacturer', 'serial_number' => 'serial_number', 'inventory_number' => 'inventory_number'] as $target => $source) {
            if (!empty($record[$source]) && empty($device->$target)) $device->$target = (string) $record[$source];
        }
        if (!empty($record['device_note']) && empty($device->description)) $device->description = mb_substr(trim((string) $record['device_note']), 0, 240);
        if (!empty($record['comment']) && empty($device->comment)) $device->comment = (string) $record['comment'];
        $room = trim((string) ($record['room_snapshot'] ?? $record['room'] ?? ''));
        if ($room !== '' && (int) ($device->room_id ?? 0) === 0) {
            $roomBean = R::findOne('room', ' number = ? OR name = ? ', [$room, $room]);
            if ($roomBean !== null) $device->room_id = (int) $roomBean->id;
        }
        if (!$device->room_id) $device->room_id = 0;
        $device->updated_at = date(DATE_ATOM);
        if (!$device->created_at) $device->created_at = $device->updated_at;
        R::store($device);
        return ['device' => $device, 'created' => $created];
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
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS, RecursiveDirectoryIterator::CATCH_GET_CHILD));
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

    /** @return array<string, array<string, string>> */
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
            if ($slot !== '') $result[$slot] = [
                'storage_slot' => $slot,
                'legacy_number' => $this->value($rowData, ['Nr. alt', 'Nr alt']),
                'external_number' => $this->value($rowData, ['Nr. neu', 'Nr neu']),
                'room_snapshot' => $this->value($rowData, ['Raumnummer']),
                'comment' => $this->value($rowData, ['Bemerkung/Kommentar']),
                'device_note' => $this->value($rowData, ['Notiz Gerät']),
            ];
        }
        return $result;
    }

    private function matchingOdsPath(string $csvPath): ?string
    {
        $candidate = preg_replace('/\.csv$/i', '.ods', $csvPath);
        return is_string($candidate) && is_file($candidate) ? $candidate : null;
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
}
