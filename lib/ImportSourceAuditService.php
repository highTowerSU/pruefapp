<?php

declare(strict_types=1);

/** Read-only inventory for the three supported historical import sources. */
final class ImportSourceAuditService
{
    /** @return array<string,mixed> */
    public function inspect(string $directory): array
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) throw new InvalidArgumentException('Quellverzeichnis wurde nicht gefunden.');
        $result = [
            'root' => $root,
            'json' => ['files' => 0, 'records' => 0, 'with_regie' => 0, 'without_regie' => 0, 'inspection_types' => []],
            'jsonl' => ['files' => 0, 'records' => 0, 'with_regie' => 0, 'without_regie' => 0, 'inspection_types' => []],
            'csv_ods' => ['pairs' => 0, 'unpaired_csv' => [], 'csv_headers' => [], 'ods_headers' => [], 'ods_rows_with_regie' => 0, 'ods_rows_without_regie' => 0],
            'samples' => ['json_or_jsonl_with_regie' => [], 'json_or_jsonl_without_regie' => [], 'ods_with_regie' => [], 'ods_without_regie' => []],
        ];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
            $extension = strtolower($file->getExtension());
            $path = $file->getPathname();
            if ($extension === 'csv') {
                $this->inspectCsv($path, $root, $result);
                continue;
            }
            // Old exports contain aggregate JSON files (notably result.json)
            // next to the actual one-record-per-file source.  They are both
            // duplicates and can be larger than PHP's memory limit.
            if (!in_array($extension, ['json', 'jsonl'], true) || $this->isAggregateExport($file->getBasename())) continue;
            $this->inspectJson($path, $extension, $root, $result);
        }
        foreach (['json', 'jsonl'] as $source) arsort($result[$source]['inspection_types']);
        foreach (['csv_headers', 'ods_headers'] as $key) $result['csv_ods'][$key] = array_values(array_keys($result['csv_ods'][$key]));
        return $result;
    }

    /** @param array<string,mixed> $result */
    private function inspectJson(string $path, string $extension, string $root, array &$result): void
    {
        $source = $extension === 'jsonl' ? 'jsonl' : 'json';
        $result[$source]['files']++;
        $records = [];
        if ($extension === 'jsonl') {
            $handle = fopen($path, 'rb');
            if ($handle === false) return;
            while (($line = fgets($handle)) !== false) {
                $record = json_decode($line, true);
                if (is_array($record)) $records[] = $record;
            }
            fclose($handle);
        } else {
            $decoded = json_decode((string) file_get_contents($path), true);
            $records = is_array($decoded) && isset($decoded['number']) ? [$decoded]
                : (is_array($decoded['resources']['data'] ?? null) ? $decoded['resources']['data'] : (is_array($decoded) && array_is_list($decoded) ? $decoded : []));
        }
        foreach ($records as $record) {
            if (!is_array($record) || trim((string) ($record['number'] ?? '')) === '') continue;
            $result[$source]['records']++;
            $type = $this->scalar($record['type'] ?? '');
            if ($type !== '') $result[$source]['inspection_types'][$type] = (int) ($result[$source]['inspection_types'][$type] ?? 0) + 1;
            $regie = $this->regieField($record);
            $regieValue = str_contains($regie, '.') ? '' : (string) ($record[$regie] ?? '');
            $hasRegie = $regie !== '' && (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $regieValue) ?: '0') > 0;
            $key = $hasRegie ? 'with_regie' : 'without_regie';
            $result[$source][$key]++;
            $sampleKey = 'json_or_jsonl_' . ($hasRegie ? 'with_regie' : 'without_regie');
            if (count($result['samples'][$sampleKey]) < 5) {
                $result['samples'][$sampleKey][] = ['source' => $source, 'file' => $this->relative($path, $root), 'number' => (string) $record['number'], 'date' => (string) ($record['date'] ?? ''), 'regie_field' => $regie, 'regie_value' => $regieValue];
            }
        }
    }

    /** @param array<string,mixed> $result */
    private function inspectCsv(string $path, string $root, array &$result): void
    {
        $contents = str_replace("\0", '', (string) file_get_contents($path));
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        $delimiter = substr_count((string) strtok($contents, "\r\n"), ';') >= 3 ? ';' : ',';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents); rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter) ?: [];
        fclose($stream);
        foreach ($headers as $header) if (trim((string) $header) !== '') $result['csv_ods']['csv_headers'][trim((string) $header)] = true;
        $ods = substr($path, 0, -4) . '.ods';
        if (!is_file($ods)) {
            $result['csv_ods']['unpaired_csv'][] = $this->relative($path, $root);
            return;
        }
        $result['csv_ods']['pairs']++;
        $this->inspectOds($ods, $root, $result);
    }

    /** @param array<string,mixed> $result */
    private function inspectOds(string $path, string $root, array &$result): void
    {
        if (!class_exists(ZipArchive::class)) return;
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return;
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if (!is_string($xml)) return;
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) return;
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $header = null;
        foreach ($xpath->query('//table:table-row') ?: [] as $row) {
            $values = [];
            foreach ($xpath->query('./table:table-cell', $row) ?: [] as $cell) $values[] = trim($cell->textContent);
            if ($header === null && in_array('Speicherplatz', $values, true)) {
                $header = $values;
                foreach ($header as $name) if ($name !== '') $result['csv_ods']['ods_headers'][$name] = true;
                continue;
            }
            if ($header === null || $values === []) continue;
            $rowValues = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), '')) ?: [];
            $slot = trim((string) ($rowValues['Speicherplatz'] ?? ''));
            if ($slot === '') continue;
            $regie = trim((string) ($rowValues['Regiezeit'] ?? $rowValues['Regiezeit (Min.)'] ?? $rowValues['Regiezeit Minuten'] ?? ''));
            $key = $regie === '' ? 'ods_rows_without_regie' : 'ods_rows_with_regie';
            $result['csv_ods'][$key]++;
            $sampleKey = $regie === '' ? 'ods_without_regie' : 'ods_with_regie';
            if (count($result['samples'][$sampleKey]) < 5) $result['samples'][$sampleKey][] = ['file' => $this->relative($path, $root), 'storage_slot' => $slot, 'regie_value' => $regie];
        }
    }

    private function scalar(mixed $value): string
    {
        return is_array($value) ? trim((string) ($value['brezel_name'] ?? $value['name'] ?? '')) : trim((string) $value);
    }

    private function regieField(array $record): string
    {
        foreach ($record as $key => $value) {
            if (is_array($value)) {
                $nested = $this->regieField($value);
                if ($nested !== '') return $key . '.' . $nested;
                continue;
            }
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $key) ?: '');
            if ((str_contains($normalized, 'regie') || str_contains($normalized, 'mehraufwand') || str_contains($normalized, 'zusatzaufwand') || str_contains($normalized, 'additionalwork') || str_contains($normalized, 'costplus')) && trim((string) $value) !== '') return (string) $key;
        }
        return '';
    }

    private function relative(string $path, string $root): string
    {
        return ltrim(substr($path, strlen(rtrim($root, '/'))), '/');
    }

    private function isAggregateExport(string $basename): bool
    {
        return in_array(strtolower($basename), ['pruefungen.json', 'result.json', 'result.csv.json'], true);
    }
}
