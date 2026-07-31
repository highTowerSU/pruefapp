<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class PhoenixSyncService
{
    public function sync(string $customerId, string $token, string $baseUrl = 'https://api.phoenix-arbeitswelt.de/phoenix', ?callable $progress = null): array
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
        $records = []; $skipped = 0;
        $reportDir = sys_get_temp_dir() . '/phoenix-reports-' . bin2hex(random_bytes(6));
        mkdir($reportDir, 0700, true);
        $total = count($items); $step = 0;
        foreach ($items as $item) {
            $step++;
            if (!is_array($item) || trim((string) ($item['number'] ?? '')) === '') continue;
            $number = trim((string) $item['number']);
            if ($progress !== null) $progress($step, $total, $number, 'Prüfung laden');
            if (R::findOne('device', ' external_number = ? OR legacy_number = ? ', [$number, $number])) { $skipped++; continue; }
            $detail = !empty($item['id']) ? $this->request($baseUrl . '/modules/audits/resources/' . (int) $item['id'], $token) : $item;
            $records[] = $this->record(is_array($detail) ? $detail : [], $item);
            if (!empty($item['id'])) $this->downloadReport($baseUrl . '/webhook/good-parrot-49/audits/' . (int) $item['id'], $number, $token, $reportDir);
        }
        if ($progress !== null) $progress($total, $total, '', 'Lokalen Import ausführen');
        if ($records === []) { @rmdir($reportDir); return ['fetched' => count($items), 'new' => 0, 'skipped_existing' => $skipped, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'errors' => []]; }
        $tmp = tempnam(sys_get_temp_dir(), 'phoenix-sync-');
        if ($tmp === false) throw new RuntimeException('Temporäre Importdatei konnte nicht angelegt werden.');
        $jsonl = $tmp . '.jsonl';
        rename($tmp, $jsonl);
        $handle = fopen($jsonl, 'wb');
        foreach ($records as $record) fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        fclose($handle);
        try { $stats = (new ElectricalInspectionImportService())->importDirectory($jsonl, $reportDir); } finally { @unlink($jsonl); $this->removeDirectory($reportDir); }
        $stats['fetched'] = count($items); $stats['skipped_existing'] = $skipped; $stats['new'] = count($records);
        return $stats;
    }

    private function request(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'X-User-Timezone: Europe/Berlin']]);
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
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/pdf']]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
        if ($status < 400 && is_string($body) && str_contains(strtolower($type), 'pdf')) file_put_contents($directory . '/' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $number) . '.pdf', $body);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
        @rmdir($directory);
    }
}
