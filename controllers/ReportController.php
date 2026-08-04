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
        $year = trim((string) ($q['year'] ?? ''));
        if (preg_match('/^\d{4}$/', $year)) { $where[] = 'EXISTS (SELECT 1 FROM inspection iy WHERE iy.device_id=d.id AND iy.test_date >= ? AND iy.test_date < ?)'; $args[] = $year . '-01-01'; $args[] = ((int) $year + 1) . '-01-01'; }
        if (trim((string) ($q['from'] ?? '')) !== '') { $where[] = 'EXISTS (SELECT 1 FROM inspection ifr WHERE ifr.device_id=d.id AND ifr.test_date >= ?)'; $args[] = trim((string) $q['from']); }
        if (trim((string) ($q['to'] ?? '')) !== '') { $where[] = 'EXISTS (SELECT 1 FROM inspection ito WHERE ito.device_id=d.id AND ito.test_date <= ?)'; $args[] = trim((string) $q['to']); }
        $inspectionStatus = trim((string) ($q['inspection_status'] ?? ''));
        if ($inspectionStatus === 'failed') $where[] = "(SELECT i2.result_status FROM inspection i2 WHERE i2.device_id=d.id ORDER BY i2.test_date DESC, i2.id DESC LIMIT 1) = 'durchgefallen'";
        if ($inspectionStatus === 'passed') $where[] = "(SELECT i2.result_status FROM inspection i2 WHERE i2.device_id=d.id ORDER BY i2.test_date DESC, i2.id DESC LIMIT 1) = 'bestanden'";
        if ($inspectionStatus === 'pending') $where[] = "(SELECT CASE WHEN i2.result_status='ausstehend' OR i2.status IN ('draft','measurement_pending') THEN 1 ELSE 0 END FROM inspection i2 WHERE i2.device_id=d.id ORDER BY i2.test_date DESC, i2.id DESC LIMIT 1) = 1";
        if ($inspectionStatus === 'completed') $where[] = "(SELECT CASE WHEN i2.result_status IS NOT NULL AND i2.result_status <> 'ausstehend' AND COALESCE(i2.status,'') NOT IN ('draft','measurement_pending') THEN 1 ELSE 0 END FROM inspection i2 WHERE i2.device_id=d.id ORDER BY i2.test_date DESC, i2.id DESC LIMIT 1) = 1";
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
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [500, [], 'ODS-Datei konnte nicht erstellt werden.'];
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
        $headers = $rows[0] ?? [];
        $headerKeys = array_map(static fn($v): string => mb_strtolower((string) $v), $headers);
        $today = new DateTimeImmutable('today');
        $cell = static function ($value, int $index, bool $header) use ($headerKeys, $today): string {
            $text = (string) $value; $style = $header ? 'Header' : '';
            if (!$header && str_contains($headerKeys[$index] ?? '', 'ergebnis')) $style = str_contains(mb_strtolower($text), 'bestanden') ? 'Good' : (str_contains(mb_strtolower($text), 'nicht') || str_contains(mb_strtolower($text), 'durch') ? 'Bad' : 'Warn');
            if (!$header && str_contains($headerKeys[$index] ?? '', 'ampel')) $style = mb_strtolower($text) === 'rot' ? 'Bad' : (mb_strtolower($text) === 'gelb' ? 'Warn' : 'Good');
            if (!$header && str_contains($headerKeys[$index] ?? '', 'prüfung')) {
                try { $date = new DateTimeImmutable($text); $style = $date < $today ? 'Bad' : ($date <= $today->modify('+2 months') ? 'Warn' : 'Good'); }
                catch (Throwable) {}
            }
            $styleAttr = $style !== '' ? ' table:style-name="' . $style . '"' : '';
            return '<table:table-cell' . $styleAttr . ' office:value-type="string"><text:p>' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text:p></table:table-cell>';
        };
        $render = static function (array $row, bool $header) use ($cell): string {
            return '<table:table-row>' . implode('', array_map(static fn($v, $i): string => $cell($v, $i, $header), $row, array_keys($row))) . '</table:table-row>';
        };
        $headerXml = $render($headers, true);
        $bodyXml = '';
        foreach (array_slice($rows, 1) as $row) $bodyXml .= $render($row, false);
        $columnCount = max(1, count($headers));
        $lastColumn = '';
        $n = $columnCount;
        while ($n > 0) { $n--; $lastColumn = chr(65 + ($n % 26)) . $lastColumn; $n = intdiv($n, 26); }
        $content = '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" office:version="1.2"><office:automatic-styles><style:style style:name="Header" style:family="table-cell"><style:table-cell-properties fo:background-color="#1f4e78"/><style:text-properties fo:color="#ffffff" fo:font-weight="bold"/></style:style><style:style style:name="Good" style:family="table-cell"><style:table-cell-properties fo:background-color="#d1e7dd"/></style:style><style:style style:name="Warn" style:family="table-cell"><style:table-cell-properties fo:background-color="#fff3cd"/></style:style><style:style style:name="Bad" style:family="table-cell"><style:table-cell-properties fo:background-color="#f8d7da"/></style:style></office:automatic-styles><office:body><office:spreadsheet><table:table table:name="Export"><table:table-column table:number-columns-repeated="' . $columnCount . '"/><table:table-header-rows>' . $headerXml . '</table:table-header-rows>' . $bodyXml . '</table:table><table:database-ranges><table:database-range table:name="ExportFilter" table:target-range="Export.A1:' . $lastColumn . count($rows) . '" table:display-filter-buttons="true" table:contains-header="true"/></table:database-ranges></office:spreadsheet></office:body></office:document-content>';
        $zip->addFromString('content.xml', $content);
        $zip->addFromString('styles.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:styles/></office:document-styles>');
        $zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>');
        $zip->addFromString('settings.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-settings xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:settings/></office:document-settings>');
        $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"><manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="settings.xml"/></manifest:manifest>');
        $zip->close();
        $body = file_get_contents($tmp); @unlink($tmp);
        return [200, ['Content-Type' => 'application/vnd.oasis.opendocument.spreadsheet', 'Content-Disposition' => 'attachment; filename="' . $title . '.ods"'], $body];
    }

    private static function pdf(array $rows, string $title): array
    {
        $lines = array_map(static fn(array $row): string => implode('  ·  ', array_map(static fn($v): string => preg_replace('/\s+/', ' ', (string) $v), $row)), $rows);
        $stream = "0.12 0.25 0.42 rg 36 805 523 24 re f\nBT /F1 14 Tf 42 812 Td 1 0 0 1 0 0 Tm ("
            . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title)
            . ") Tj ET\n";
        $stream .= "BT /F1 8 Tf 36 780 Td\n";
        foreach (array_slice($lines, 0, 58) as $index => $line) {
            if ($index === 0) $stream .= "0.12 0.25 0.42 rg\n";
            elseif (str_contains($line, 'Rot') || str_contains($line, 'durchgefallen')) $stream .= "0.75 0.10 0.10 rg\n";
            elseif (str_contains($line, 'Gelb')) $stream .= "0.65 0.45 0.05 rg\n";
            else $stream .= "0.10 0.10 0.10 rg\n";
            $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_substr($line, 0, 150)) . ") Tj 0 -12 Td\n";
        }
        $stream .= "ET";
        $objects = ["1 0 obj<< /Type /Catalog /Pages 2 0 R>>endobj", "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj", "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources<< /Font<< /F1 4 0 R>>>> /Contents 5 0 R>>endobj", "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica>>endobj", "5 0 obj<< /Length " . strlen($stream) . ">>stream\n" . $stream . "\nendstream endobj"];
        $pdf = "%PDF-1.4\n"; $offsets = [];
        foreach ($objects as $obj) { $offsets[] = strlen($pdf); $pdf .= $obj . "\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; foreach ($offsets as $o) $pdf .= sprintf("%010d 00000 n \n", $o); $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
        return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $pdf];
    }
}
