<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class ReportController
{
    public static function export(array $params, bool $isHx): array
    {
        if (!current_user()) return [401, [], 'Nicht angemeldet'];
        $format = strtolower(trim((string) ($_POST['format'] ?? 'csv')));
        $report = ($_POST['report'] ?? '') === 'rooms';
        $scope = trim((string) ($_POST['scope'] ?? 'selection'));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['device_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'page') $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['page_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'all') $ids = self::filteredIds((string) ($_POST['filter_query'] ?? ''));
        if ($ids === []) return [422, [], 'Bitte mindestens ein Gerät auswählen.'];
        $devices = self::devices($ids);
        if ($report) $rows = self::roomRows($devices);
        else $rows = self::deviceRows($devices);
        if ($format === 'ods') return self::ods($rows, $report ? 'Raum-Ampelreport' : 'Geräteexport');
        if ($format === 'pdf') return self::pdf($rows, $report ? 'Raum-Ampelreport' : 'Geräteexport');
        return self::csv($rows, $report ? 'raum-ampelreport.csv' : 'geraete-export.csv');
    }

    private static function filteredIds(string $query): array
    {
        parse_str(ltrim($query, '?'), $q);
        $where = [];
        $args = [];
        $join = ' LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id ';
        if (!current_user_has_role('admin')) {
            $allowed = current_user_customer_ids();
            if ($allowed === []) return [];
            $where[] = 'c.id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
            array_push($args, ...$allowed);
        }
        foreach (['customer_id' => 'c.id', 'site_id' => 's.id', 'building_id' => 'b.id', 'floor_id' => 'f.id', 'room_id' => 'r.id'] as $key => $column) {
            $value = (int) ($q[$key] ?? 0);
            if ($value > 0) { $where[] = $column . ' = ?'; $args[] = $value; }
        }
        $term = trim((string) ($q['q'] ?? ''));
        if ($term !== '') { $where[] = '(LOWER(d.name) LIKE ? OR LOWER(d.external_number) LIKE ? OR LOWER(d.manufacturer) LIKE ? OR LOWER(d.description) LIKE ?)'; $like = '%' . strtolower($term) . '%'; array_push($args, $like, $like, $like, $like); }
        $where[] = "(d.archived_at IS NULL OR TRIM(d.archived_at) = '')";
        $sql = 'SELECT DISTINCT d.id FROM device d' . $join . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        return array_map('intval', R::getCol($sql, $args));
    }

    private static function devices(array $ids): array
    {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getAll("SELECT d.*, r.name AS room_name, r.number AS room_number, f.name AS floor_name, b.name AS building_name, s.name AS site_name, c.name AS customer_name FROM device d LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE d.id IN ($marks) ORDER BY c.name, s.name, b.name, f.sort_order, r.name, d.name", $ids);
        $result = [];
        foreach ($rows as $row) {
            $latest = R::getRow('SELECT test_date, next_due_date, result_status FROM inspection WHERE device_id = ? ORDER BY test_date DESC, id DESC LIMIT 1', [(int) $row['id']]);
            $row['test_date'] = $latest['test_date'] ?? '';
            $row['next_due_date'] = $latest['next_due_date'] ?? '';
            $row['result_status'] = $latest['result_status'] ?? '';
            $result[] = $row;
        }
        return $result;
    }

    private static function deviceRows(array $devices): array
    {
        $rows = [['Gerätenummer', 'Bezeichnung', 'Hersteller', 'Typ/Modell', 'Kunde', 'Standort', 'Gebäude', 'Etage', 'Raum', 'Letzte Prüfung', 'Nächste Prüfung', 'Ergebnis']];
        foreach ($devices as $d) $rows[] = [(string) $d['external_number'], (string) $d['name'], (string) $d['manufacturer'], (string) $d['device_model'], (string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) $d['floor_name'], (string) ($d['room_number'] ?: $d['room_name']), (string) $d['test_date'], (string) $d['next_due_date'], (string) $d['result_status']];
        return $rows;
    }

    private static function roomRows(array $devices): array
    {
        $groups = [];
        $today = new DateTimeImmutable('today');
        $yellowLimit = $today->modify('+2 months');
        foreach ($devices as $d) {
            $key = implode(' · ', array_filter([(string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) ($d['room_number'] ?: $d['room_name'])]));
            $groups[$key][] = $d;
        }
        $rows = [['Raum', 'Geräte', 'Fällig/überfällig', 'Quote', 'Ampel']];
        foreach ($groups as $room => $items) {
            $due = 0; $overdue = 0;
            foreach ($items as $d) {
                $status = strtolower((string) $d['result_status']);
                $date = trim((string) $d['next_due_date']);
                $isDue = in_array($status, ['durchgefallen', 'nicht bestanden', 'ausstehend'], true);
                if (in_array($status, ['durchgefallen', 'nicht bestanden'], true)) $overdue++;
                if ($date !== '') try { $dueDate = new DateTimeImmutable($date); $isDue = $isDue || $dueDate <= $yellowLimit; if ($dueDate < $today) $overdue++; } catch (Throwable) {}
                if ($isDue) $due++;
            }
            $percent = count($items) > 0 ? round($due * 100 / count($items), 1) : 0;
            $color = $overdue > 0 ? 'Rot' : ($due > 0 ? 'Gelb' : 'Grün');
            $rows[] = [$room, count($items), $due, number_format($percent, 1, ',', '') . ' %', $color];
        }
        return $rows;
    }

    private static function csv(array $rows, string $filename): array
    {
        $stream = fopen('php://temp', 'w+');
        foreach ($rows as $row) fputcsv($stream, $row, ';');
        rewind($stream);
        $body = "\xEF\xBB\xBF" . stream_get_contents($stream);
        return [200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'], $body];
    }

    private static function ods(array $rows, string $title): array
    {
        if (!class_exists('ZipArchive')) return [500, [], 'ODS-Export ist auf diesem Server nicht verfügbar.'];
        $tmp = tempnam(sys_get_temp_dir(), 'ods-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
        $renderRow = static fn(array $row): string => '<table:table-row>' . implode('', array_map(static fn($v): string => '<table:table-cell office:value-type="string"><text:p>' . htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text:p></table:table-cell>', $row)) . '</table:table-row>';
        $header = $renderRow($rows[0]);
        $bodyRows = implode('', array_map($renderRow, array_slice($rows, 1)));
        $columnCount = max(1, count($rows[0]));
        $content = '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:spreadsheet><table:table table:name="Export"><table:table-column table:number-columns-repeated="' . $columnCount . '"/><table:table-header-rows>' . $header . '</table:table-header-rows>' . $bodyRows . '<table:database-ranges><table:database-range table:target-range="Export.A1:' . chr(64 + min(26, $columnCount)) . count($rows) . '" table:display-filter-buttons="true"/></table:database-ranges></table:table></office:spreadsheet></office:body></office:document-content>';
        $zip->addFromString('content.xml', $content);
        $zip->addFromString('styles.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"/>');
        $zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"/>');
        $zip->addFromString('settings.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-settings xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"/>');
        $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"><manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/></manifest:manifest>');
        $zip->close();
        $body = file_get_contents($tmp); @unlink($tmp);
        return [200, ['Content-Type' => 'application/vnd.oasis.opendocument.spreadsheet', 'Content-Disposition' => 'attachment; filename="' . $title . '.ods"'], $body];
    }

    private static function pdf(array $rows, string $title): array
    {
        $lines = array_map(static fn(array $row): string => implode(' | ', array_map(static fn($v): string => preg_replace('/\s+/', ' ', (string) $v), $row)), $rows);
        $stream = "BT /F1 9 Tf 36 800 Td\n";
        foreach (array_slice($lines, 0, 55) as $line) $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_substr($line, 0, 140)) . ") Tj 0 -13 Td\n";
        $stream .= "ET";
        $objects = ["1 0 obj<< /Type /Catalog /Pages 2 0 R>>endobj", "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj", "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources<< /Font<< /F1 4 0 R>>>> /Contents 5 0 R>>endobj", "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica>>endobj", "5 0 obj<< /Length " . strlen($stream) . ">>stream\n" . $stream . "\nendstream endobj"];
        $pdf = "%PDF-1.4\n"; $offsets = [];
        foreach ($objects as $obj) { $offsets[] = strlen($pdf); $pdf .= $obj . "\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; foreach ($offsets as $o) $pdf .= sprintf("%010d 00000 n \n", $o); $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
        return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $pdf];
    }
}
