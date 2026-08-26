<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class PhoenixSyncService
{
    /** @var array<string,array<string,int>> */
    private static array $auditIndexCache = [];
    /** @var array<int,array<string,mixed>> */
    private static array $auditDetailCache = [];
    /** @return array{token:string,customer_id:string,base_url:string} */
    public static function serverCredentials(): array
    {
        $read = static function (array $keys): string {
            foreach ($keys as $key) {
                $value = trim((string) (get_app_config(strtolower($key), '') ?: config_value($key) ?: getenv($key)));
                if ($value !== '') return $value;
            }
            return '';
        };
        return [
            'token' => $read(['PHOENIX_API_TOKEN', 'PRUEFAPP_PHOENIX_API_TOKEN', 'PHOENIX_TOKEN']),
            'customer_id' => $read(['PHOENIX_CUSTOMER_ID', 'PRUEFAPP_PHOENIX_CUSTOMER_ID']),
            'base_url' => rtrim($read(['PHOENIX_API_URL', 'PRUEFAPP_PHOENIX_API_URL']) ?: 'https://api.phoenix-arbeitswelt.de/phoenix', '/'),
        ];
    }

    /** @return array{configured:bool,token_configured:bool,customer_configured:bool,base_url:string} */
    public static function serverConfigurationStatus(): array
    {
        $credentials = self::serverCredentials();
        return [
            'configured' => $credentials['token'] !== '' && $credentials['customer_id'] !== '',
            'token_configured' => $credentials['token'] !== '',
            'customer_configured' => $credentials['customer_id'] !== '',
            'base_url' => $credentials['base_url'],
        ];
    }

    /** Verifies credentials without persisting or disclosing the token. */
    public function testConnection(string $customerId, string $token, string $baseUrl): int
    {
        $customerId = trim($customerId);
        $token = trim($token);
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (!preg_match('/^\d+$/', $customerId) || (int) $customerId < 1) throw new InvalidArgumentException('Bitte eine gültige numerische Phoenix-Kunden-ID angeben.');
        if ($token === '') throw new InvalidArgumentException('Bitte einen Phoenix-API-Token hinterlegen.');
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) throw new InvalidArgumentException('Bitte eine gültige Phoenix-API-URL angeben.');
        $query = http_build_query([
            'module' => 'audits',
            'pre_filters' => json_encode([['column' => 'customer.id', 'operator' => '=', 'value' => (int) $customerId]]),
            'results' => 1, 'page' => 1, 'filters' => '[[]]', 'sortField' => 'date', 'sortOrder' => 'desc',
            'columns' => json_encode(['number', 'date']),
        ]);
        $response = $this->request($baseUrl . '/table?' . $query, $token);
        $items = $response['resources']['data'] ?? null;
        if (!is_array($items)) throw new RuntimeException('Phoenix-Antwort enthält keine Prüfungen.');
        return count($items);
    }

    /**
     * Read-only evidence for the cut-over from Phoenix to native Prüfweb work.
     * No local inspection, device or source snapshot is changed.
     *
     * @return list<array<string,mixed>>
     */
    public function latestCreatedAudits(int $limit = 25): array
    {
        $credentials = self::serverCredentials();
        if ($credentials['token'] === '' || $credentials['customer_id'] === '') {
            throw new RuntimeException('Phoenix-Zugang ist nicht vollständig konfiguriert.');
        }
        $query = http_build_query([
            'module' => 'audits',
            'pre_filters' => json_encode([['column' => 'customer.id', 'operator' => '=', 'value' => (int) $credentials['customer_id']]]),
            'results' => min(100, max(1, $limit)), 'page' => 1, 'filters' => '[[]]', 'sortField' => 'created_at', 'sortOrder' => 'desc',
            'columns' => json_encode(['id', 'number', 'date', 'created_at', 'updated_at', 'created_by', 'type']),
        ]);
        $response = $this->request($credentials['base_url'] . '/table?' . $query, $credentials['token']);
        $items = $response['resources']['data'] ?? [];
        if (!is_array($items)) throw new RuntimeException('Phoenix-Antwort enthält keine Prüfungen.');
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $createdBy = $item['created_by'] ?? '';
            $rows[] = [
                'id' => (int) ($item['id'] ?? $item['audit_id'] ?? $item['resource_id'] ?? 0),
                'number' => trim((string) ($item['number'] ?? '')),
                'test_date' => trim((string) ($item['date'] ?? '')),
                'created_at' => trim((string) ($item['created_at'] ?? '')),
                'updated_at' => trim((string) ($item['updated_at'] ?? '')),
                'created_by' => $this->scalar($createdBy, 'brezel_name'),
                'type' => $this->scalar($item['type'] ?? '', 'brezel_name'),
            ];
        }
        return $rows;
    }

    /** Downloads and stores the authoritative original PDF for one inspection. */
    public function downloadOriginalReportForInspection(\RedBeanPHP\OODBBean $inspection, \RedBeanPHP\OODBBean $device): string
    {
        $credentials = self::serverCredentials();
        if ($credentials['token'] === '' || $credentials['customer_id'] === '') return '';
        $wanted = [];
        foreach ([(string) ($inspection->external_number ?? ''), (string) ($inspection->legacy_number ?? '')] as $number) {
            $number = trim($number);
            if ($number !== '') $wanted[$number] = true;
        }
        // A device may have several inspections.  Its current number is not
        // proof that an arbitrary imported inspection is the same Phoenix
        // audit, especially after a historical import repair.  Only use it
        // when the import lacks *any* inspection identifier.
        if ($wanted === []) {
            $number = trim((string) ($device->external_number ?? ''));
            if ($number !== '') $wanted[$number] = true;
        }
        if ($wanted === []) return '';

        $index = $this->serverAuditIndex($credentials);
        $auditId = 0;
        foreach (array_keys($wanted) as $number) {
            if (isset($index[$number])) { $auditId = $index[$number]; break; }
        }
        if ($auditId <= 0) return '';

        $pdf = $this->downloadReportBytes($credentials['base_url'] . '/webhook/good-parrot-49/audits/' . $auditId, $credentials['token']);
        if ($pdf === '') return '';
        $directory = app_data_root() . '/reports/phoenix-original';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Phoenix-Berichtsverzeichnis konnte nicht angelegt werden.');
        $path = $directory . '/' . (int) $inspection->id . '-' . $auditId . '.pdf';
        if (file_put_contents($path, $pdf, LOCK_EX) === false) throw new RuntimeException('Phoenix-Originalbericht konnte nicht gespeichert werden.');
        return $path;
    }

    /**
     * Stores Phoenix as supplementary source evidence and fills only genuinely
     * missing imported values. Existing/manual facts are never overwritten;
     * conflicts remain visible in the job result and in the source snapshot.
     * @return array{matched:bool,updated:int,conflicts:int}
     */
    public function reconcileImportedInspection(\RedBeanPHP\OODBBean $inspection, \RedBeanPHP\OODBBean $device): array
    {
        $credentials = self::serverCredentials();
        if ($credentials['token'] === '' || $credentials['customer_id'] === '') return ['matched' => false, 'updated' => 0, 'conflicts' => 0];
        if (!in_array((string) ($inspection->source_type ?? ''), ['csv', 'json'], true)) return ['matched' => false, 'updated' => 0, 'conflicts' => 0];
        $wanted = [];
        foreach ([(string) ($inspection->external_number ?? ''), (string) ($inspection->legacy_number ?? '')] as $number) {
            $number = trim($number);
            if ($number !== '') $wanted[$number] = true;
        }
        if ($wanted === []) {
            $number = trim((string) ($device->external_number ?? ''));
            if ($number !== '') $wanted[$number] = true;
        }
        $auditId = 0;
        foreach (array_keys($wanted) as $number) {
            $auditId = (int) ($this->serverAuditIndex($credentials)[$number] ?? 0);
            if ($auditId > 0) break;
        }
        if ($auditId <= 0) return ['matched' => false, 'updated' => 0, 'conflicts' => 0];
        $detail = self::$auditDetailCache[$auditId] ??= $this->request($credentials['base_url'] . '/modules/audits/resources/' . $auditId, $credentials['token']);
        $record = $this->record($detail, []);
        $snapshot = json_decode((string) R::getCell('SELECT source_row_json FROM inspection_source_snapshot WHERE inspection_id = ?', [(int) $inspection->id]), true);
        if (!is_array($snapshot)) $snapshot = [];
        $snapshot['_phoenix_evidence'] = ['audit_id' => $auditId, 'fetched_at' => date(DATE_ATOM), 'record' => $record];
        R::exec('UPDATE inspection_source_snapshot SET source_row_json = ? WHERE inspection_id = ?', [json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}', (int) $inspection->id]);

        $updated = 0;
        $conflicts = 0;
        $fill = static function (string $field, string $value) use ($inspection, &$updated, &$conflicts): void {
            $value = trim($value);
            if ($value === '') return;
            $existing = trim((string) ($inspection->$field ?? ''));
            if ($existing === '') { $inspection->$field = $value; $updated++; return; }
            if (strtolower($existing) !== strtolower($value)) $conflicts++;
        };
        $fill('room_snapshot', (string) ($record['room'] ?? ''));
        $fill('device_type', (string) ($record['device_type'] ?? ''));
        $fill('manufacturer', (string) ($record['manufacturer'] ?? ''));
        $fill('device_model', (string) ($record['device_model'] ?? ''));
        $auditOk = $record['audit_ok'] ?? '';
        $sourceStatus = is_bool($auditOk)
            ? ($auditOk ? InspectionEvaluationService::PASSED : InspectionEvaluationService::FAILED)
            : InspectionEvaluationService::normalizeStatus((string) $auditOk);
        if (in_array($sourceStatus, [InspectionEvaluationService::PASSED, InspectionEvaluationService::FAILED], true)
            && !in_array((string) ($inspection->result_status ?? ''), [InspectionEvaluationService::PASSED, InspectionEvaluationService::FAILED], true)
        ) {
            $inspection->result_status = $sourceStatus;
            $inspection->status = 'completed';
            $updated++;
        }
        $regie = (int) round((float) str_replace(',', '.', (string) ($record['total_cost_plus'] ?? '0')));
        if ((int) ($inspection->regie_minutes ?? 0) <= 0 && $regie > 0) { $inspection->regie_minutes = $regie; $updated++; }
        elseif ($regie > 0 && (int) ($inspection->regie_minutes ?? 0) !== $regie) $conflicts++;
        if ($updated > 0) { $inspection->updated_at = date(DATE_ATOM); R::store($inspection); }
        return ['matched' => true, 'updated' => $updated, 'conflicts' => $conflicts];
    }

    /**
     * Reads the authoritative Phoenix record for an imported inspection without
     * changing the inspection, device master data or source snapshot.  This is
     * intentionally separate from reconciliation so a historical mapping can
     * be reviewed before it is repaired.
     *
     * @return array{matched:bool,audit_id:int,record:array<string,mixed>}
     */
    public function lookupImportedInspectionEvidence(\RedBeanPHP\OODBBean $inspection, \RedBeanPHP\OODBBean $device): array
    {
        $credentials = self::serverCredentials();
        if ($credentials['token'] === '' || $credentials['customer_id'] === '') return ['matched' => false, 'audit_id' => 0, 'record' => []];
        if (!in_array((string) ($inspection->source_type ?? ''), ['csv', 'json'], true)) return ['matched' => false, 'audit_id' => 0, 'record' => []];
        $wanted = [];
        foreach ([(string) ($inspection->external_number ?? ''), (string) ($inspection->legacy_number ?? '')] as $number) {
            $number = trim($number);
            if ($number !== '') $wanted[$number] = true;
        }
        if ($wanted === []) {
            $number = trim((string) ($device->external_number ?? ''));
            if ($number !== '') $wanted[$number] = true;
        }
        $auditId = 0;
        $index = $this->serverAuditIndex($credentials);
        foreach (array_keys($wanted) as $number) {
            $auditId = (int) ($index[$number] ?? 0);
            if ($auditId > 0) break;
        }
        if ($auditId <= 0) return ['matched' => false, 'audit_id' => 0, 'record' => []];
        $detail = self::$auditDetailCache[$auditId] ??= $this->request($credentials['base_url'] . '/modules/audits/resources/' . $auditId, $credentials['token']);
        return ['matched' => true, 'audit_id' => $auditId, 'record' => $this->record($detail, [])];
    }

    /** @param array{token:string,customer_id:string,base_url:string} $credentials @return array<string,int> */
    private function serverAuditIndex(array $credentials): array
    {
        $cacheKey = hash('sha256', $credentials['base_url'] . '|' . $credentials['customer_id'] . '|' . $credentials['token']);
        if (isset(self::$auditIndexCache[$cacheKey])) return self::$auditIndexCache[$cacheKey];
        $cacheDir = app_data_root() . '/cache';
        $cachePath = $cacheDir . '/phoenix-audit-index-' . hash('sha256', $credentials['base_url'] . '|' . $credentials['customer_id']) . '.json';
        if (is_file($cachePath) && (filemtime($cachePath) ?: 0) >= time() - 21600) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached)) return self::$auditIndexCache[$cacheKey] = array_map('intval', $cached);
        }
        $index = [];
        for ($page = 1; $page <= 20; $page++) {
            $query = http_build_query([
                'module' => 'audits',
                'pre_filters' => json_encode([['column' => 'customer.id', 'operator' => '=', 'value' => (int) $credentials['customer_id']]]),
                'results' => 2000, 'page' => $page, 'filters' => '[[]]', 'sortField' => 'date', 'sortOrder' => 'desc',
                'columns' => json_encode(['id', 'number', 'inventory_number', 'date']),
            ]);
            $list = $this->request($credentials['base_url'] . '/table?' . $query, $credentials['token']);
            $items = $list['resources']['data'] ?? [];
            if (!is_array($items)) break;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $id = (int) ($item['id'] ?? $item['audit_id'] ?? $item['resource_id'] ?? 0);
                if ($id <= 0) continue;
                foreach (['number', 'inventory_number'] as $field) {
                    $value = trim((string) ($item[$field] ?? ''));
                    if ($value !== '') $index[$value] = $id;
                }
            }
            if (count($items) < 2000) break;
        }
        if ($index !== []) {
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0770, true);
            @file_put_contents($cachePath, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        return self::$auditIndexCache[$cacheKey] = $index;
    }

    public function sync(
        string $customerId,
        string $token,
        string $baseUrl = 'https://api.phoenix-arbeitswelt.de/phoenix',
        ?callable $progress = null,
        int $resumeStep = 0,
        string $workId = ''
    ): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $query = http_build_query([
            'module' => 'audits', 'pre_filters' => json_encode([['column' => 'customer.id', 'operator' => '=', 'value' => (int) $customerId]]),
            'results' => 2000, 'page' => 1, 'filters' => '[[]]', 'sortField' => 'date', 'sortOrder' => 'ascend',
            'columns' => json_encode(['number', 'employee', 'date', 'inventory_number', 'location', 'level', 'room', 'audit_ok', 'type', 'signature']),
        ]);
        $list = $this->request($baseUrl . '/table?' . $query, $token);
        $items = $list['resources']['data'] ?? [];
        if (!is_array($items)) throw new RuntimeException('Phoenix-Antwort enthält keine Prüfungen.');
        $workId = preg_replace('/[^a-f0-9]/', '', strtolower($workId)) ?: bin2hex(random_bytes(12));
        $workRoot = app_data_root() . '/imports/phoenix-work-' . $workId;
        $reportDir = $workRoot . '/reports';
        if (!is_dir($reportDir) && !mkdir($reportDir, 0770, true) && !is_dir($reportDir)) throw new RuntimeException('Phoenix-Arbeitsverzeichnis konnte nicht angelegt werden.');
        $jsonl = $workRoot . '/records.jsonl';
        $statePath = $workRoot . '/state.json';
        $state = is_file($statePath) ? json_decode((string) @file_get_contents($statePath), true) : [];
        if (!is_array($state)) $state = [];
        $skipped = (int) ($state['skipped_existing'] ?? 0);
        $total = count($items);
        $fetchStep = min(max((int) ($state['fetch_step'] ?? $resumeStep), 0), $total);
        foreach (array_slice($items, $fetchStep) as $item) {
            $fetchStep++;
            if (!is_array($item) || trim((string) ($item['number'] ?? '')) === '') continue;
            $number = trim((string) $item['number']);
            if (R::findOne('device', ' external_number = ? OR legacy_number = ? ', [$number, $number])) {
                $skipped++;
                $state['fetch_step'] = $fetchStep; $state['skipped_existing'] = $skipped;
                file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
                if ($progress !== null) $progress($fetchStep, max(1, $total * 2), $number, 'Bereits vorhandene Prüfung übersprungen');
                continue;
            }
            $auditId = (int) ($item['id'] ?? $item['audit_id'] ?? $item['resource_id'] ?? 0);
            $detail = $auditId > 0 ? $this->request($baseUrl . '/modules/audits/resources/' . $auditId, $token) : $item;
            file_put_contents($jsonl, json_encode($this->record(is_array($detail) ? $detail : [], $item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            if ($auditId > 0) $this->downloadReport($baseUrl . '/webhook/good-parrot-49/audits/' . $auditId, $number, $token, $reportDir);
            $state['fetch_step'] = $fetchStep; $state['skipped_existing'] = $skipped;
            file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
            if ($progress !== null) $progress($fetchStep, max(1, $total * 2), $number, 'Prüfung und Originalbericht geladen');
        }
        if (!is_file($jsonl) || filesize($jsonl) === 0) { $this->removeDirectory($reportDir); @unlink($statePath); @rmdir($workRoot); return ['fetched' => count($items), 'new' => 0, 'skipped_existing' => $skipped, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'errors' => []]; }
        $archiveRoot = app_data_root() . '/phoenix-imports';
        if (!is_dir($archiveRoot) && !mkdir($archiveRoot, 0770, true) && !is_dir($archiveRoot)) throw new RuntimeException('Phoenix-Archiv konnte nicht angelegt werden.');
        $byteOffset = max(0, (int) ($state['import_offset'] ?? 0));
        $importedRecords = max(0, (int) ($state['import_records'] ?? 0));
        $stats = is_array($state['stats'] ?? null) ? $state['stats'] : ['files' => 0, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'skipped' => 0, 'errors' => []];
        $merge = static function (array &$target, array $part): void {
            foreach ($part as $key => $value) {
                if (is_int($value)) $target[$key] = (int) ($target[$key] ?? 0) + $value;
                elseif (in_array($key, ['errors', 'new_devices', 'updated_devices', 'not_imported'], true) && is_array($value)) $target[$key] = array_merge((array) ($target[$key] ?? []), $value);
            }
        };
        $service = new ElectricalInspectionImportService();
        do {
            $chunk = $service->importJsonlChunk($jsonl, $byteOffset, 25, $reportDir, ['_audit_correlation_id' => 'job-' . $workId]);
            $merge($stats, (array) $chunk['stats']);
            $byteOffset = (int) $chunk['next_offset'];
            $importedRecords += (int) $chunk['processed'];
            $state = array_merge($state, ['fetch_step' => $total, 'import_offset' => $byteOffset, 'import_records' => $importedRecords, 'stats' => $stats, 'skipped_existing' => $skipped]);
            file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
            if ($progress !== null) $progress($total + $importedRecords, max(1, $total * 2), '', 'Geladene Prüfungen werden lokal importiert');
        } while (empty($chunk['eof']));
        $archiveJsonl = $archiveRoot . '/phoenix-sync-' . $workId . '.jsonl';
        if (!is_file($archiveJsonl)) @copy($jsonl, $archiveJsonl);
        $this->removeDirectory($reportDir); @unlink($jsonl); @unlink($statePath); @rmdir($workRoot);
        $stats['fetched'] = count($items); $stats['skipped_existing'] = $skipped; $stats['new'] = $importedRecords;
        return $stats;
    }

    private function request(string $url, string $token): array
    {
        $ch = curl_init($url);
        // A live Phoenix refresh is supplementary evidence.  Do not let a
        // stalled upstream hold a cron worker for a full minute per request.
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'X-User-Timezone: Europe/Berlin']]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($body === false || $status >= 400) throw new RuntimeException('Phoenix API Fehler ' . $status . ($error !== '' ? ': ' . $error : ''));
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) throw new RuntimeException('Phoenix API lieferte ungültiges JSON.');
        return $decoded;
    }

    private function record(array $detail, array $fallback): array
    {
        $s = $detail + $fallback; $type = $s['type'] ?? ''; $by = $s['created_by'] ?? ''; $checks = is_array($s['checklist'] ?? null) ? $s['checklist'] : [];
        $typeName = trim((string) $this->scalar($type, 'brezel_name'));
        $typeName = (string) (preg_replace('/\s*\(\s*\)\s*$/u', '', $typeName) ?: $typeName);
        $normalizedChecks = [];
        $measurements = [];
        $r = ['number' => (string) ($s['number'] ?? ''), 'total_cost_plus' => $s['total_cost_plus'] ?? 0, 'location' => (string) ($s['location'] ?? ''), 'inventory_number' => (string) ($s['inventory_number'] ?? ''), 'free_text' => (string) ($s['free_text'] ?? ''), 'audit_ok' => $s['audit_ok'] ?? null, 'level' => (string) ($s['level'] ?? ''), 'room' => (string) ($s['room'] ?? ''), 'device_type' => $this->scalar($s['device_type'] ?? ''), 'manufacturer' => (string) ($s['manufacturer'] ?? ''), 'device_model' => (string) ($s['device_model'] ?? ''), 'warming_device' => $s['warming_device'] ?? false, 'date' => (string) ($s['date'] ?? ''), 'next_audit' => (string) ($s['next_audit'] ?? ''), 'created_by' => $this->scalar($by, 'brezel_name'), 'type' => $typeName, '_legacy_source' => 'phoenix-sync'];
        foreach ($checks as $i => $c) if (is_array($c)) {
            $step = $c['step'] ?? null;
            $criterion = $c['criterion'] ?? null;
            $result = $c['result'] ?? ($c['answer'] ?? ($c['value'] ?? ($c['status'] ?? null)));
            if (is_string($result)) {
                $resultText = trim($result);
                if (strcasecmp($resultText, 'ok') === 0 || preg_match('/^ja(?:\b|\s*,)/iu', $resultText)) $result = 'ja';
                elseif (preg_match('/^nein(?:\b|\s*,)/iu', $resultText)) $result = 'nein';
            } elseif (isset($c['ok']) && is_bool($c['ok'])) {
                $result = $c['ok'] ? 'ja' : 'nein';
            }
            // Phoenix often stores the expected answer in `criterion` and
            // omits a separate answer for passed historical audits. Those
            // checks were completed successfully; retain explicit negatives
            // but mark otherwise unanswered checks as positive.
            if ($result === null || (is_string($result) && trim($result) === '')) {
                $criterionText = is_scalar($criterion) ? trim((string) $criterion) : '';
                $result = preg_match('/^nein(?:\b|\s*,)/iu', $criterionText)
                    ? 'nein'
                    : ((bool) ($s['audit_ok'] ?? true) ? 'ja' : 'nein');
            }
            $normalizedChecks[] = ['step' => $step, 'criterion' => $criterion, 'result' => $result, 'cost_plus' => $c['cost_plus'] ?? null];
            $r['step' . $i] = $step; $r['criterion' . $i] = $criterion; $r['result' . $i] = $result; $r['cost_plus' . $i] = $c['cost_plus'] ?? null;
            $stepText = is_scalar($step) ? (string) $step : '';
            if (is_scalar($result) && trim((string) $result) !== '' && (preg_match('/messung|widerstand|strom|spannung|wert|ergebnis/i', $stepText) || is_numeric(str_replace(',', '.', (string) $result)))) {
                $measurements[] = ['name' => $stepText !== '' ? $stepText : 'Messwert', 'value' => (string) $result, 'unit' => '', 'result' => ''];
            }
        }
        $r['checklist'] = $normalizedChecks;
        $r['measurements'] = $measurements;
        return $r;
    }

    private function scalar(mixed $value, string $key = 'name'): string
    { return is_array($value) ? (string) ($value[$key] ?? '') : (string) $value; }

    private function downloadReport(string $url, string $number, string $token, string $directory): void
    {
        $body = $this->downloadReportBytes($url, $token);
        if ($body !== '') file_put_contents($directory . '/' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $number) . '.pdf', $body);
    }

    private function downloadReportBytes(string $url, string $token): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/pdf, application/octet-stream', 'Origin: https://phoenix-arbeitswelt.de', 'X-Brezel-Frontend: https://phoenix-arbeitswelt.de']]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
        return $status < 400 && is_string($body) && (str_contains(strtolower($type), 'pdf') || str_starts_with(ltrim($body), '%PDF')) ? $body : '';
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
        @rmdir($directory);
    }
}
