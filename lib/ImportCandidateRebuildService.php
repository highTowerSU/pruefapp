<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Builds a clean import inventory before any ambiguous source row is imported. */
final class ImportCandidateRebuildService
{
    private const REVIEW_FIELDS = ['inspection_number', 'device_number', 'test_date', 'inspection_type', 'room_snapshot', 'regie_minutes', 'result_status', 'manufacturer', 'device_model', 'storage_slot'];

    /** @return array<string,mixed> */
    public function prepare(string $directory, int $ownerUserId, callable $progress): array
    {
        $source = realpath($directory) ?: '';
        if ($source === '' || !is_dir($source)) throw new InvalidArgumentException('Kuratiertes Quellenverzeichnis wurde nicht gefunden.');
        $progress(1, 4, '', 'Datenbank-Backup wird erstellt.');
        $backup = ImportedInspectionResetService::backup();
        $progress(2, 4, '', 'Importbestand wird entfernt; Prüfweb-Prüfungen bleiben erhalten.');
        $reset = ImportedInspectionResetService::execute($backup);
        R::exec('DELETE FROM importcandidate');
        R::exec('DELETE FROM importrebuildrun');
        $run = R::dispense('importrebuildrun');
        $run->public_id = 'irb_' . bin2hex(random_bytes(12));
        $run->source_directory = $source;
        $run->backup_path = $backup;
        $run->state = 'staging';
        $run->created_by = $ownerUserId;
        $run->created_at = date(DATE_ATOM);
        $run->summary_json = '{}';
        $runId = (int) R::store($run);

        $progress(3, 4, '', 'Quellen und aktuelle Prüfweb-Prüfungen werden als Kandidaten erfasst.');
        $rows = (new ElectricalInspectionImportService())->candidateRecords($source);
        foreach ($rows as $row) $this->store($runId, (string) $row['source_kind'], (string) $row['source_path'], (int) $row['row_no'], (array) $row['record']);
        foreach (R::getAll("SELECT i.*, d.external_number AS device_number, d.legacy_number AS device_legacy_number, d.name AS device_name FROM inspection i JOIN device d ON d.id=i.device_id WHERE i.source_type='manual' ORDER BY i.id") as $manual) {
            $record = [
                'inspection_number' => (string) ($manual['external_number'] ?? ''), 'external_number' => (string) ($manual['device_number'] ?? ''),
                'legacy_number' => (string) ($manual['device_legacy_number'] ?? ''), 'test_date' => (string) ($manual['test_date'] ?? ''),
                'inspection_type' => (string) ($manual['inspection_type'] ?? ''), 'room_snapshot' => (string) ($manual['room_snapshot'] ?? ''),
                'regie_minutes' => (int) ($manual['regie_minutes'] ?? 0), 'result_status' => (string) ($manual['result_status'] ?? ''),
                'manufacturer' => (string) ($manual['manufacturer'] ?? ''), 'device_model' => (string) ($manual['device_model'] ?? ''),
                'storage_slot' => (string) ($manual['storage_slot'] ?? ''), 'device_type' => (string) ($manual['device_name'] ?? ''), 'manual_inspection_id' => (int) ($manual['id'] ?? 0),
            ];
            $this->store($runId, 'manual', 'Prüfweb', 0, $record, (int) $manual['id']);
        }

        $progress(4, 4, '', 'Eindeutige Kandidaten werden importiert, Konflikte zur Sichtung vorbereitet.');
        $summary = $this->classifyAndImport($runId);
        $run = R::load('importrebuildrun', $runId);
        $run->state = 'review';
        $run->summary_json = json_encode($summary + ['backup' => $backup, 'reset' => $reset], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        R::store($run);
        audit_log('importkandidaten_neuaufbau', ['run_id' => $runId, 'backup' => basename($backup), 'summary' => $summary]);
        return $summary + ['run_id' => $runId, 'backup' => $backup, 'reset' => $reset];
    }

    /** @return list<array<string,mixed>> */
    public function groups(int $runId, string $state = '', int $limit = 0): array
    {
        $where = 'run_id=?'; $params = [$runId];
        if ($state === 'unresolved') $where .= " AND state IN ('review', 'number_missing')";
        elseif ($state !== '') { $where .= ' AND state=?'; $params[] = $state; }
        $sql = "SELECT group_key, state, COUNT(*) AS source_count FROM importcandidate WHERE {$where} GROUP BY group_key, state ORDER BY CASE state WHEN 'review' THEN 0 WHEN 'number_missing' THEN 1 ELSE 2 END, group_key";
        if ($limit > 0) $sql .= ' LIMIT ' . max(1, min(200, $limit));
        $rows = R::getAll($sql, $params);
        foreach ($rows as &$row) {
            $row['candidates'] = R::getAll('SELECT * FROM importcandidate WHERE run_id=? AND group_key=? ORDER BY source_kind, source_path, source_row_no', [$runId, (string) $row['group_key']]);
            $row['conflicts'] = $this->conflicts($row['candidates']);
        }
        return $rows;
    }

    /** @param array<string,string> $fieldSources */
    public function decide(int $runId, string $groupKey, string $action, array $fieldSources, int $userId): array
    {
        $rows = R::getAll('SELECT * FROM importcandidate WHERE run_id=? AND group_key=?', [$runId, $groupKey]);
        if ($rows === []) throw new InvalidArgumentException('Kandidatengruppe wurde nicht gefunden.');
        if (!in_array($action, ['merge', 'separate', 'discard'], true)) throw new InvalidArgumentException('Ungültige Kandidatenentscheidung.');
        $records = array_map(fn(array $row): array => $this->decode((string) $row['raw_json']), $rows);
        $stats = ['imported' => 0, 'updated_manual' => 0, 'discarded' => 0];
        if ($action === 'discard') $stats['discarded'] = count($rows);
        elseif ($action === 'separate') {
            $importer = new ElectricalInspectionImportService();
            foreach ($rows as $index => $row) {
                if ((string) $row['source_kind'] === 'manual') continue;
                $importer->importCandidateRecord($this->merge([$row], [$records[$index]], $fieldSources), (string) $row['source_path']);
                $stats['imported']++;
            }
        } else {
            $merged = $this->merge($rows, $records, $fieldSources);
            $manual = array_values(array_filter($rows, static fn(array $row): bool => (string) $row['source_kind'] === 'manual'));
            if ($manual !== []) {
                $this->applyToManual((int) ($manual[0]['source_inspection_id'] ?? 0), $merged);
                $stats['updated_manual'] = 1;
            } else {
                (new ElectricalInspectionImportService())->importCandidateRecord($merged, (string) ($rows[0]['source_path'] ?? ''));
                $stats['imported'] = 1;
            }
        }
        $decision = json_encode(['action' => $action, 'fields' => $fieldSources, 'by' => $userId, 'at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        R::exec("UPDATE importcandidate SET state='resolved', decision_json=? WHERE run_id=? AND group_key=?", [$decision, $runId, $groupKey]);
        audit_log('importkandidat_entschieden', ['run_id' => $runId, 'group_key' => $groupKey, 'action' => $action, 'stats' => $stats]);
        return $stats;
    }

    /** @param array<string,mixed> $record */
    private function store(int $runId, string $kind, string $path, int $rowNo, array $record, int $inspectionId = 0): void
    {
        $identity = $this->identity($record);
        $candidate = R::dispense('importcandidate');
        $candidate->run_id = $runId; $candidate->group_key = $identity['group_key']; $candidate->source_kind = $kind;
        $candidate->source_path = $path; $candidate->source_row_no = $rowNo; $candidate->source_inspection_id = $inspectionId ?: null;
        $candidate->inspection_number = $identity['inspection_number']; $candidate->device_number = $identity['device_number']; $candidate->legacy_device_number = $identity['legacy_device_number'];
        $candidate->test_date = $identity['test_date']; $candidate->inspection_type = $identity['inspection_type']; $candidate->storage_slot = $identity['storage_slot'];
        $candidate->raw_json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $candidate->state = $identity['complete'] ? 'open' : 'number_missing'; $candidate->decision_json = '{}'; $candidate->created_at = date(DATE_ATOM);
        R::store($candidate);
    }

    /** @return array<string,mixed> */
    private function classifyAndImport(int $runId): array
    {
        $groups = $this->groups($runId);
        $summary = ['automatic' => 0, 'review' => 0, 'number_missing' => 0, 'manual_kept' => 0];
        foreach ($groups as $group) {
            $rows = $group['candidates']; $states = array_unique(array_column($rows, 'state'));
            if (in_array('number_missing', $states, true)) { $summary['number_missing']++; continue; }
            $conflicts = $this->conflicts($rows);
            if ($conflicts !== []) { R::exec("UPDATE importcandidate SET state='review' WHERE run_id=? AND group_key=?", [$runId, $group['group_key']]); $summary['review']++; continue; }
            $hasManual = in_array('manual', array_column($rows, 'source_kind'), true);
            if ($hasManual) { R::exec("UPDATE importcandidate SET state='accepted' WHERE run_id=? AND group_key=?", [$runId, $group['group_key']]); $summary['manual_kept']++; continue; }
            $records = array_map(fn(array $row): array => $this->decode((string) $row['raw_json']), $rows);
            $merged = $this->merge($rows, $records, []);
            (new ElectricalInspectionImportService())->importCandidateRecord($merged, (string) $rows[0]['source_path']);
            R::exec("UPDATE importcandidate SET state='accepted' WHERE run_id=? AND group_key=?", [$runId, $group['group_key']]);
            $summary['automatic']++;
        }
        return $summary;
    }

    /** @param list<array<string,mixed>> $rows @return array<string,list<string>> */
    private function conflicts(array $rows): array
    {
        $conflicts = [];
        foreach (self::REVIEW_FIELDS as $field) {
            $values = [];
            foreach ($rows as $row) {
                $record = isset($row['raw_json']) ? $this->decode((string) $row['raw_json']) : $row;
                $value = trim((string) ($this->identity($record)[$field] ?? $record[$field] ?? ''));
                if ($value !== '') $values[mb_strtolower($value)] = $value;
            }
            if (count($values) > 1) $conflicts[$field] = array_values($values);
        }
        return $conflicts;
    }

    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $records @param array<string,string> $selected */
    private function merge(array $rows, array $records, array $selected): array
    {
        $merged = [];
        foreach ($records as $index => $record) foreach ($record as $key => $value) if (!isset($merged[$key]) || $merged[$key] === '' || $merged[$key] === null) $merged[$key] = $value;
        foreach ($selected as $field => $candidateId) {
            if (str_starts_with($field, '__')) continue;
            foreach ($rows as $index => $row) if ((string) ($row['id'] ?? '') === (string) $candidateId) {
                $value = $this->identity($records[$index])[$field] ?? $records[$index][$field] ?? null;
                if ($value !== null) $merged[$this->recordField($field)] = $value;
            }
        }
        $inspectionNumber = trim((string) ($selected['__inspection_number'] ?? ''));
        $deviceNumber = trim((string) ($selected['__device_number'] ?? ''));
        $testDate = trim((string) ($selected['__test_date'] ?? ''));
        $type = trim((string) ($selected['__inspection_type'] ?? ''));
        if ($inspectionNumber !== '') { $merged['inspection_number'] = $inspectionNumber; $merged['number'] = $inspectionNumber; }
        if ($deviceNumber !== '') { $merged['device_number'] = $deviceNumber; $merged['external_number'] = $deviceNumber; }
        if ($testDate !== '') $merged['test_date'] = $testDate;
        if ($type !== '') $merged['inspection_type'] = $type;
        return $merged;
    }

    /** @return array<string,string|bool> */
    private function identity(array $record): array
    {
        $inspection = $this->clean((string) ($record['inspection_number'] ?? $record['number'] ?? ''));
        $device = $this->clean((string) ($record['device_number'] ?? $record['external_number'] ?? $record['inventory_number'] ?? ''));
        $legacy = $this->clean((string) ($record['legacy_device_number'] ?? $record['legacy_number'] ?? ''));
        $date = trim((string) ($record['test_date'] ?? $record['date'] ?? ''));
        $type = $this->clean((string) ($record['inspection_type'] ?? $record['type'] ?? ''));
        $slot = $this->clean((string) ($record['storage_slot'] ?? ''));
        $complete = $inspection !== '' && $device !== '' && $date !== '' && $type !== '';
        return ['inspection_number' => $inspection, 'device_number' => $device, 'legacy_device_number' => $legacy, 'test_date' => $date, 'inspection_type' => $type, 'storage_slot' => $slot, 'complete' => $complete, 'group_key' => $complete ? hash('sha256', implode('|', [$inspection, $device, $date, $type])) : 'missing-' . hash('sha256', json_encode($record) ?: '')];
    }

    private function clean(string $value): string { return mb_strtoupper(trim(preg_replace('/\s+/', '', $value) ?: '')); }
    /** @return array<string,mixed> */
    private function decode(string $json): array { $decoded = json_decode($json, true); return is_array($decoded) ? $decoded : []; }
    private function recordField(string $field): string { return $field === 'inspection_number' ? 'inspection_number' : $field; }

    /** @param array<string,mixed> $record */
    private function applyToManual(int $inspectionId, array $record): void
    {
        $inspection = R::load('inspection', $inspectionId);
        if (!$inspection->id || (string) $inspection->source_type !== 'manual') throw new RuntimeException('Die geschützte Prüfweb-Prüfung wurde nicht gefunden.');
        foreach (['room_snapshot', 'regie_minutes', 'result_status', 'manufacturer', 'device_model', 'storage_slot', 'inspection_type', 'test_date', 'external_number'] as $field) {
            if (array_key_exists($field, $record) && trim((string) $record[$field]) !== '') $inspection->$field = $record[$field];
        }
        $inspection->updated_at = date(DATE_ATOM); R::store($inspection);
    }
}
