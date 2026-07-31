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
    public function importDirectory(string $directory, ?string $reportsDirectory = null): array
    {
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
            if (in_array(strtolower($file->getBasename()), ['pruefungen.json', 'result.csv.json'], true)) continue;
            $stats['files']++;
            try {
                $result = in_array($extension, ['json', 'jsonl'], true)
                    ? $this->importJsonFile($file->getPathname(), $root, $extension === 'jsonl')
                    : $this->importCsvFile($file->getPathname(), $root);
                foreach ($result as $key => $value) {
                    if ($key === 'reason') { $stats['errors'][] = $file->getPathname() . ': ' . $value; continue; }
                    if (in_array($key, ['new_devices', 'updated_devices', 'not_imported'], true) && is_array($value)) { $stats[$key] = array_merge($stats[$key] ?? [], $value); continue; }
                    if (array_key_exists($key, $stats) && is_int($value)) $stats[$key] += $value;
                }
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
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => []];
        $matchedSlots = [];
        foreach ($records as $record) {
            if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') {
                $result['skipped']++;
                continue;
            }
            $one = $this->importRecord($record, 'json', $path, $root);
            foreach ($one as $key => $value) { if (in_array($key, ['new_devices', 'updated_devices'], true)) $result[$key] = array_merge($result[$key] ?? [], $value); else $result[$key] += $value; }
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
        if (!is_array($header)) return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1, 'reason' => 'CSV enthält keine Kopfzeile.'];
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
            if ($headerlessRows === []) return ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 1, 'reason' => 'Kein Speicher-Nr.-Header und keine gültigen Benning-Datensätze erkannt.'];
            $header = $this->benningHeaders();
        }

        $odsPath = $this->matchingOdsPath($path);
        $ods = $this->readOds($odsPath);
        $result = ['imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'new_devices' => [], 'updated_devices' => [], 'not_imported' => []];
        $rows = $headerlessRows ?? null;
        while (true) {
            $row = $rows !== null ? array_shift($rows) : fgetcsv($stream, 0, $delimiter);
            if ($row === null || $row === false) break;
            if (count(array_filter($row, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
            $record = $this->csvRecord($header, $row);
            $slot = trim((string) ($record['storage_slot'] ?? ''));
            $odsRow = $slot !== '' ? ($ods[$slot] ?? $ods[ltrim($slot, '0')] ?? null) : null;
            if ($odsPath !== null && !is_array($odsRow)) {
                $result['skipped']++;
                $result['not_imported'][] = ['storage_slot' => $slot, 'source' => 'CSV', 'reason' => 'Speicherplatz fehlt in der ODS'];
                continue;
            }
            if (is_array($odsRow)) {
                $matchedSlots[ltrim($slot, '0')] = true;
                $record = array_merge($record, $odsRow);
                // In the ODS, “Notiz Gerät” is the actual device description;
                // the CSV's Bezeichnung is only the protection class.
                if (trim((string) ($record['device_note'] ?? '')) !== '') $record['device_type'] = trim((string) $record['device_note']);
            }
            if (trim((string) ($record['external_number'] ?? '')) === '-' || trim((string) ($record['external_number'] ?? '')) === '') {
                $record['external_number'] = trim((string) ($record['legacy_number'] ?? '')) ?: $slot;
            }
            if (($record['external_number'] ?? '') === '' && ($record['storage_slot'] ?? '') === '') {
                $result['skipped']++;
                continue;
            }
            $one = $this->importRecord($record, 'csv', $path, $root);
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
                    $matchedSlots[$key] = true;
                }
            }
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
        $rawExternal = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        $date = $this->normalizeDate((string) ($record['test_date'] ?? $record['date'] ?? ''));
        $external = $this->yearNumber($rawExternal, $date);
        $dedupe = hash('sha256', implode('|', [$sourceType, $external, $slot, $date, (string) ($record['result_status'] ?? '')]));
        $deviceResult = $this->findOrCreateDevice($record);
        $inspection = R::findOne('inspection', ' dedupe_key = ? ', [$dedupe]);
        if ($inspection === null && $rawExternal !== $external) {
            $legacyDedupe = hash('sha256', implode('|', [$sourceType, $rawExternal, $slot, $date, (string) ($record['result_status'] ?? '')]));
            $inspection = R::findOne('inspection', ' dedupe_key = ? ', [$legacyDedupe]);
        }
        $created = $inspection === null;
        $inspection ??= R::dispense('inspection');
        $inspection->device_id = (int) $deviceResult['device']->id;
        $inspection->dedupe_key = $dedupe;
        $inspection->source_type = $sourceType;
        $inspection->source_file = basename($sourcePath);
        $inspection->external_number = $external;
        $inspection->legacy_number = $this->yearNumber(trim((string) ($record['legacy_number'] ?? '')), $date);
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
        $report = $this->copyReport($rawExternal);
        if ($report !== null) $inspection->report_path = $report;
        $inspection->updated_at = date(DATE_ATOM);
        if (!$inspection->created_at) $inspection->created_at = $inspection->updated_at;
        R::store($inspection);
        $deviceInfo = ['id' => (int) $deviceResult['device']->id, 'number' => $external, 'name' => (string) $deviceResult['device']->name];
        return ['imported' => $created ? 1 : 0, 'updated' => $created ? 0 : 1, 'devices' => $deviceResult['created'] ? 1 : 0, 'reports' => $report !== null ? 1 : 0, 'new_devices' => $deviceResult['created'] ? [$deviceInfo] : [], 'updated_devices' => !$deviceResult['created'] ? [$deviceInfo] : []];
    }

    /** @return array{device:\RedBeanPHP\OODBBean,created:bool} */
    private function findOrCreateDevice(array $record): array
    {
        $external = trim((string) ($record['external_number'] ?? $record['number'] ?? ''));
        $legacy = trim((string) ($record['legacy_number'] ?? ''));
        $slot = trim((string) ($record['storage_slot'] ?? ''));
        $device = $legacy !== '' && $legacy !== '-' ? R::findOne('device', ' legacy_number = ? ', [$legacy]) : null;
        $device ??= $legacy !== '' && $legacy !== '-' ? R::findOne('device', ' external_number = ? ', [$legacy]) : null;
        $device ??= $external !== '' ? R::findOne('device', ' external_number = ? ', [$external]) : null;
        $device ??= $external !== '' ? R::findOne('device', ' legacy_number = ? ', [$external]) : null;
        $device ??= $slot !== '' ? R::findOne('device', ' storage_slot = ? ', [$slot]) : null;
        $created = $device === null;
        $device ??= R::dispense('device');
        $device->external_number = $external;
        $device->legacy_number = $legacy === '-' ? '' : $legacy;
        $device->storage_slot = $slot;
        if (array_key_exists('warming_device', $record)) $device->warming_device = filter_var($record['warming_device'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $room = trim((string) ($record['room_snapshot'] ?? $record['room'] ?? ''));
        if ($room !== '') $device->room_snapshot = $room;
        $roomBean = $this->ensureImportedRoom($record, $room);
        if ($roomBean !== null) $device->room_id = (int) $roomBean->id;
        $preferredName = trim((string) ($record['device_type'] ?? $record['device_model'] ?? ''));
        if ($this->isProtectionClass($preferredName)) $preferredName = '';
        $currentName = trim((string) ($device->name ?? ''));
        $legacyModelName = trim((string) ($record['device_model'] ?? ''));
        if ($preferredName !== '' && ($currentName === '' || $currentName === $external || $currentName === $legacyModelName || str_starts_with($currentName, 'Gerät '))) $device->name = $preferredName;
        if (trim((string) ($device->name ?? '')) === '' || $this->isProtectionClass((string) $device->name)) $device->name = 'Gerät ' . ($external ?: $slot);
        foreach (['device_model' => 'device_model', 'manufacturer' => 'manufacturer', 'serial_number' => 'serial_number', 'inventory_number' => 'inventory_number'] as $target => $source) {
            if (!empty($record[$source])) $device->$target = $this->importValue((string) $record[$source]);
        }
        $serial = trim((string) ($record['serial_number'] ?? $record['serial'] ?? ''));
        if ($serial !== '') $device->serial_number = $this->importValue($serial);
        $description = trim((string) ($record['free_text'] ?? $record['device_note'] ?? ''));
        if ($description !== '' && trim((string) ($device->comment ?? '')) === '') $device->comment = mb_substr($description, 0, 1000);
        if (!empty($record['comment'])) $device->comment = (string) $record['comment'];
        if ($room !== '') {
            $roomBean = $this->findRoomByIdentifier($room);
            if ($roomBean !== null) $device->room_id = (int) $roomBean->id;
        }
        if (!$device->room_id) $device->room_id = 0;
        $device->updated_at = date(DATE_ATOM);
        if (!$device->created_at) $device->created_at = $device->updated_at;
        R::store($device);
        return ['device' => $device, 'created' => $created];
    }

    private function findRoomByIdentifier(string $identifier): ?\RedBeanPHP\OODBBean
    {
        $identifier = trim($identifier);
        if ($identifier === '') return null;
        $roomBean = R::findOne('room', ' number = ? OR name = ? ', [$identifier, $identifier]);
        if ($roomBean !== null) return $roomBean;
        if (!class_exists('StructureController')) {
            $controller = dirname(__DIR__) . '/controllers/StructureController.php';
            if (is_file($controller)) require_once $controller;
        }
        foreach (R::findAll('room') as $candidate) {
            $floor = R::load('floor', (int) $candidate->floor_id);
            if (!$floor || !(int) $floor->id) continue;
            $candidateIdentifier = class_exists('StructureController')
                ? StructureController::roomIdentifier($candidate, $floor, null)
                : '';
            if (strcasecmp(trim($candidateIdentifier), $identifier) === 0) return $candidate;
        }
        return null;
    }

    private function ensureImportedRoom(array $record, string $room): ?\RedBeanPHP\OODBBean
    {
        if ($room === '') return null;
        $location = trim((string) ($record['location'] ?? ''));
        $locationKey = strtolower($location);
        $level = trim((string) ($record['level'] ?? ''));
        $source = strtolower((string) ($record['_legacy_source'] ?? ''));
        $known = ['antoniuskolleg' => ['AK', 'NKS', 'Antoniuskolleg'], 'ak' => ['AK', 'NKS', 'Antoniuskolleg'], 'berufskolleg' => ['AK', 'BB', 'Berufskolleg'], 'quickputz gmbh & co.kg' => ['QP', 'QP', 'Quickputz GmbH & Co.KG']];
        if (isset($known[$locationKey])) [$knownCustomer, $knownSiteCode, $knownSiteName] = $known[$locationKey];
        else [$knownCustomer, $knownSiteCode, $knownSiteName] = ['', '', ''];
        $isAk = str_contains($source, 'ak-elektro') || $knownCustomer === 'AK';
        $rawCustomer = trim((string) ($record['customer']['company'] ?? ''));
        if ($knownCustomer !== '') { $rawCustomer = $knownCustomer; $location = $knownSiteName; }
        if ($location === '' || $locationKey === strtolower($rawCustomer) || str_contains($locationKey, 'ceneos')) return null;
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
        if ($buildingCode === 'Import') return null;
        $building = R::findOne('building', ' site_id = ? AND code = ? ', [(int) $site->id, $buildingCode]);
        if ($building === null) { $building = R::dispense('building'); $building->site_id = (int) $site->id; $building->name = $buildingCode === 'AB' ? 'Altbau' : $buildingCode; $building->code = $buildingCode; $building->created_at = date(DATE_ATOM); R::store($building); }
        $floorCode = $specialKitchen ? 'U' : ($specialMensa ? '0' : $this->floorCode($level, $room));
        $floor = R::findOne('floor', ' building_id = ? AND code = ? ', [(int) $building->id, $floorCode]);
        if ($floor === null) { $floor = R::dispense('floor'); $floor->building_id = (int) $building->id; $floor->code = $floorCode; $floor->name = $buildingCode . $floorCode; $floor->sort_order = $floorCode === 'U' ? -100 : 0; $floor->room_code_pattern = ($specialKitchen || $specialMensa) ? '{building}{room}' : ''; $floor->created_at = date(DATE_ATOM); R::store($floor); }
        if ($specialKitchen || $specialMensa) { $floor->room_code_pattern = '{building}{room}'; R::store($floor); }
        if ($specialKitchen || $specialMensa) $room = $specialKitchen ? 'KU' : 'ME';
        $roomNumber = $this->roomPart($room, $buildingCode, $floorCode);
        $roomBean = R::findOne('room', ' floor_id = ? AND (number = ? OR name = ? OR number = ? OR name = ?)', [(int) $floor->id, $roomNumber, $roomNumber, $room, $room]);
        if ($roomBean !== null) { $roomBean->number = $roomNumber; $roomBean->name = $roomNumber; }
        if ($roomBean === null) { $roomBean = R::dispense('room'); $roomBean->floor_id = (int) $floor->id; $roomBean->number = $roomNumber; $roomBean->name = $roomNumber; $roomBean->created_at = date(DATE_ATOM); R::store($roomBean); }
        return $roomBean;
    }

    private function roomPart(string $room, string $buildingCode, string $floorCode): string
    {
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
            if ($slot !== '') {
                $rowData = [
                'storage_slot' => $slot,
                'legacy_number' => $this->value($rowData, ['Nr. alt', 'Nr alt']),
                'external_number' => $this->value($rowData, ['Nr. neu', 'Nr neu']),
                'room_snapshot' => $this->value($rowData, ['Raumnummer']),
                'comment' => $this->value($rowData, ['Bemerkung/Kommentar']),
                'device_note' => $this->value($rowData, ['Notiz Gerät']),
                ];
                $result[$slot] = $rowData;
                $result[ltrim($slot, '0')] = $rowData;
            }
        }
        return $result;
    }

    private function matchingOdsPath(string $csvPath): ?string
    {
        $candidate = preg_replace('/\.csv$/i', '.ods', $csvPath);
        return is_string($candidate) && is_file($candidate) ? $candidate : null;
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
