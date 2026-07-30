<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class PhoenixSyncService
{
    public function sync(string $customerId, string $token, string $baseUrl = 'https://api.phoenix-arbeitswelt.de/phoenix'): array
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
        foreach ($items as $item) {
            if (!is_array($item) || trim((string) ($item['number'] ?? '')) === '') continue;
            $number = trim((string) $item['number']);
            if (R::findOne('device', ' external_number = ? OR legacy_number = ? ', [$number, $number])) { $skipped++; continue; }
            $detail = !empty($item['id']) ? $this->request($baseUrl . '/modules/audits/resources/' . (int) $item['id'], $token) : $item;
            $records[] = $this->record(is_array($detail) ? $detail : [], $item);
        }
        if ($records === []) return ['fetched' => count($items), 'new' => 0, 'skipped_existing' => $skipped, 'imported' => 0, 'updated' => 0, 'devices' => 0, 'reports' => 0, 'errors' => []];
        $tmp = tempnam(sys_get_temp_dir(), 'phoenix-sync-');
        if ($tmp === false) throw new RuntimeException('Temporäre Importdatei konnte nicht angelegt werden.');
        $jsonl = $tmp . '.jsonl';
        rename($tmp, $jsonl);
        $handle = fopen($jsonl, 'wb');
        foreach ($records as $record) fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        fclose($handle);
        try { $stats = (new ElectricalInspectionImportService())->importDirectory($jsonl); } finally { @unlink($jsonl); }
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
        $r = ['number' => (string) ($s['number'] ?? ''), 'total_cost_plus' => $s['total_cost_plus'] ?? 0, 'location' => (string) ($s['location'] ?? ''), 'inventory_number' => (string) ($s['inventory_number'] ?? ''), 'free_text' => (string) ($s['free_text'] ?? ''), 'audit_ok' => $s['audit_ok'] ?? null, 'level' => (string) ($s['level'] ?? ''), 'room' => (string) ($s['room'] ?? ''), 'device_type' => $this->scalar($s['device_type'] ?? ''), 'manufacturer' => (string) ($s['manufacturer'] ?? ''), 'device_model' => (string) ($s['device_model'] ?? ''), 'warming_device' => $s['warming_device'] ?? false, 'date' => (string) ($s['date'] ?? ''), 'next_audit' => (string) ($s['next_audit'] ?? ''), 'created_by' => $this->scalar($by, 'brezel_name'), 'type' => $this->scalar($type, 'brezel_name'), '_legacy_source' => 'phoenix-sync'];
        foreach ($checks as $i => $c) if (is_array($c)) { $r['step' . $i] = $c['step'] ?? null; $r['criterion' . $i] = $c['criterion'] ?? null; $r['result' . $i] = $c['result'] ?? null; $r['cost_plus' . $i] = $c['cost_plus'] ?? null; }
        return $r;
    }

    private function scalar(mixed $value, string $key = 'name'): string
    { return is_array($value) ? (string) ($value[$key] ?? '') : (string) $value; }
}
