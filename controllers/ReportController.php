<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class ReportController
{
    public static function export(array $params, bool $isHx): array
    {
        if (!current_user()) return [401, [], 'Nicht angemeldet'];
        $format = strtolower(trim((string) ($_POST['format'] ?? 'csv')));
        $reportType = (string) ($_POST['report'] ?? '');
        $report = $reportType === 'rooms';
        $scope = trim((string) ($_POST['scope'] ?? 'selection'));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['device_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'page') $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['page_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'all') $ids = self::filteredIds((string) ($_POST['filter_query'] ?? ''));
        if ($ids === []) return [422, [], 'Bitte mindestens ein Gerät auswählen.'];
        $devices = self::devices($ids);
        if ($reportType === 'daily') $rows = self::dailyRows($ids, trim((string) ($_POST['daily_date'] ?? '')), trim((string) ($_POST['daily_examiner'] ?? '')), (int) ($_POST['daily_customer_id'] ?? 0));
        elseif ($report) $rows = self::roomRows($devices);
        else $rows = self::deviceRows($devices);
        $name = $reportType === 'daily' ? 'Tagesreport' : ($report ? 'Raum-Ampelreport' : 'Geräteexport');
        if ($format === 'json') return [200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $name . '.json"'], json_encode(['title' => $name, 'generated_at' => date(DATE_ATOM), 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)];
        if ($format === 'ods') return self::ods($rows, $name);
        if ($format === 'pdf') return self::pdf($rows, $name);
        return self::csv($rows, $reportType === 'daily' ? 'tagesreport.csv' : ($report ? 'raum-ampelreport.csv' : 'geraete-export.csv'));
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
        $rows = R::getAll("SELECT d.*, r.name AS room_name, r.number AS room_number, a.name AS area_name, f.name AS floor_name, b.name AS building_name, s.name AS site_name, c.name AS customer_name FROM device d LEFT JOIN room r ON r.id=d.room_id LEFT JOIN area a ON a.id=r.area_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE d.id IN ($marks) ORDER BY c.name, s.name, b.name, f.sort_order, r.name, d.name", $ids);
        $result = [];
        foreach ($rows as $row) {
            $latest = R::getRow('SELECT external_number AS inspection_number, test_date, next_due_date, result_status FROM inspection WHERE device_id = ? ORDER BY test_date DESC, id DESC LIMIT 1', [(int) $row['id']]);
            $row['inspection_number'] = $latest['inspection_number'] ?? '';
            $row['test_date'] = $latest['test_date'] ?? '';
            $row['next_due_date'] = $latest['next_due_date'] ?? '';
            $row['result_status'] = $latest['result_status'] ?? '';
            $result[] = $row;
        }
        return $result;
    }

    private static function deviceRows(array $devices): array
    {
        $rows = [['Gerätenummer', 'Bezeichnung', 'Hersteller', 'Typ/Modell', 'Kunde', 'Standort', 'Gebäude', 'Etage', 'Bereich', 'Raum', 'Letzte Prüfnummer', 'Letzte Prüfung', 'Nächste Prüfung', 'Ergebnis']];
        foreach ($devices as $d) $rows[] = [(string) $d['external_number'], (string) $d['name'], (string) $d['manufacturer'], (string) $d['device_model'], (string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) $d['floor_name'], (string) $d['area_name'], (string) ($d['room_number'] ?: $d['room_name']), (string) $d['inspection_number'], (string) $d['test_date'], (string) $d['next_due_date'], (string) $d['result_status']];
        return $rows;
    }

    private static function dailyRows(array $deviceIds, string $date, string $examiner, int $customerId): array
    {
        if ($deviceIds === []) return [['Prüfnummer', 'Datum', 'Prüfer', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung']];
        $marks = implode(',', array_fill(0, count($deviceIds), '?')); $args = $deviceIds; $where = ["i.device_id IN ($marks)"]; if ($date !== '') { $where[] = 'i.test_date = ?'; $args[] = $date; } if ($examiner !== '') { $where[] = 'LOWER(i.examiner) LIKE ?'; $args[] = '%' . strtolower($examiner) . '%'; } if ($customerId > 0) { $where[] = 'c.id = ?'; $args[] = $customerId; }
        $rows = [['Prüfnummer', 'Datum', 'Prüfer', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung']];
        foreach (R::getAll("SELECT i.external_number, i.test_date, i.examiner, i.result_status, i.regie_minutes, i.regie_reason, d.external_number AS device_number, d.name AS device_name, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE " . implode(' AND ', $where) . ' ORDER BY c.name, i.examiner, i.test_date, i.id', $args) as $row) $rows[] = [(string) $row['external_number'], (string) $row['test_date'], (string) $row['examiner'], (string) $row['customer_name'], (string) $row['device_number'] . ' · ' . (string) $row['device_name'], trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: '—', (string) $row['result_status'], (int) $row['regie_minutes'], (string) $row['regie_reason']];
        return $rows;
    }

    private static function roomRows(array $devices): array
    {
        $groups = [];
        $today = new DateTimeImmutable('today');
        $yellowLimit = $today->modify('+2 months');
        foreach ($devices as $d) {
            $key = implode("\x1f", [(string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) $d['floor_name'], (string) $d['area_name'], (string) ($d['room_number'] ?: $d['room_name'])]);
            $groups[$key][] = $d;
        }
        $rows = [['Kunde', 'Standort', 'Gebäude', 'Etage', 'Bereich', 'Raum', 'Geräte', 'Fällig/überfällig', 'Quote']];
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
            $parts = explode("\x1f", $room) + ['', '', '', '', '', ''];
            $rows[] = [$parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], count($items), $due, number_format($percent, 1, ',', '') . ' %'];
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
            if (!$header && str_contains($headerKeys[$index] ?? '', 'ergebnis')) { $lower = mb_strtolower($text); $style = str_contains($lower, 'nicht') || str_contains($lower, 'durch') ? 'Bad' : (str_contains($lower, 'bestanden') ? 'Good' : 'Warn'); }
            if (!$header && str_contains($headerKeys[$index] ?? '', 'quote')) { $percent = (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $text)); $style = $percent <= 10 ? 'Q1' : ($percent <= 20 ? 'Q2' : ($percent <= 40 ? 'Q3' : ($percent <= 60 ? 'Q4' : ($percent <= 80 ? 'Q5' : 'Q6')))); }
            if (!$header && str_contains($headerKeys[$index] ?? '', 'nächste prüfung')) {
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
        $widths = ['2.8cm', '4.5cm', '3.5cm', '3.5cm', '4cm', '4cm', '4cm', '3cm', '3cm', '4cm', '3cm', '3cm', '3.5cm'];
        $columns = ''; foreach (array_slice($widths, 0, $columnCount) as $index => $width) $columns .= '<table:table-column table:style-name="Col' . ($index + 1) . '"/>';
        $columnStyles = ''; foreach (array_slice($widths, 0, $columnCount) as $index => $width) $columnStyles .= '<style:style style:name="Col' . ($index + 1) . '" style:family="table-column"><style:table-column-properties style:column-width="' . $width . '"/></style:style>';
        $quoteColors = ['#d1e7dd', '#b7e4c7', '#fff3cd', '#ffe69c', '#ffda6a', '#ffb86b'];
        $quoteStyles = ''; foreach ($quoteColors as $index => $color) $quoteStyles .= '<style:style style:name="Q' . ($index + 1) . '" style:family="table-cell"><style:table-cell-properties fo:background-color="' . $color . '"/></style:style>';
        $content = '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" office:version="1.2"><office:automatic-styles>' . $columnStyles . '<style:style style:name="Header" style:family="table-cell"><style:table-cell-properties fo:background-color="#1f4e78"/><style:text-properties fo:color="#ffffff" fo:font-weight="bold"/></style:style><style:style style:name="Good" style:family="table-cell"><style:table-cell-properties fo:background-color="#d1e7dd"/></style:style><style:style style:name="Warn" style:family="table-cell"><style:table-cell-properties fo:background-color="#fff3cd"/></style:style><style:style style:name="Bad" style:family="table-cell"><style:table-cell-properties fo:background-color="#f8d7da"/></style:style>' . $quoteStyles . '</office:automatic-styles><office:body><office:spreadsheet><table:table table:name="Export">' . $columns . '<table:table-header-rows>' . $headerXml . '</table:table-header-rows>' . $bodyXml . '</table:table><table:database-ranges><table:database-range table:name="ExportFilter" table:target-range-address="Export.A1:' . $lastColumn . count($rows) . '" table:display-filter-buttons="true" table:contains-header="true"/></table:database-ranges></office:spreadsheet></office:body></office:document-content>';
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
        $headers = $rows[0] ?? []; $count = max(1, count($headers)); $widths = $count === 14 ? [62, 75, 65, 65, 65, 65, 65, 58, 58, 70, 64, 58, 58, 64] : ($count === 9 ? [76, 76, 76, 62, 62, 84, 48, 68, 68] : array_fill(0, $count, 794 / $count));
        $left = 24; $top = 565; $rowHeight = 19; $headerHeight = 28; $pageRows = 25; $streams = []; $stream = '';
        $page = static function (string &$stream) use (&$streams): void { $streams[] = $stream; $stream = ''; };
        $drawPageHeader = static function (string &$stream) use ($title, $left, $top, $headers, $widths, $headerHeight): float {
            $stream .= "0.12 0.30 0.48 rg {$left} " . ($top - 30) . " 794 30 re f\nBT /F1 16 Tf 1 1 1 rg {$left} " . ($top - 20) . " Td (" . self::pdfEscape(self::pdfText($title)) . ") Tj ET\n";
            $x = $left; $y = $top - 58; foreach ($headers as $i => $header) { $w = $widths[$i]; $stream .= "0.20 0.23 0.27 rg {$x} {$y} {$w} {$headerHeight} re f\nBT /F1 7 Tf 1 1 1 rg " . ($x + 3) . ' ' . ($y + 9) . " Td (" . self::pdfEscape(self::pdfText(mb_strimwidth((string) $header, 0, 24, '…'))) . ") Tj ET\n"; $x += $w; } return $y - $headerHeight;
        };
        $y = $drawPageHeader($stream); $rowIndex = 0; $quoteIndex = array_search('Quote', $headers, true);
        foreach (array_slice($rows, 1) as $row) {
            if ($rowIndex > 0 && $rowIndex % $pageRows === 0) { $page($stream); $y = $drawPageHeader($stream); }
            $x = $left; $quote = $quoteIndex !== false ? (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', (string) ($row[$quoteIndex] ?? ''))) : 0;
            foreach ($headers as $i => $header) { $value = self::pdfText(mb_strimwidth(preg_replace('/\s+/', ' ', (string) ($row[$i] ?? '')), 0, 28, '…')); [$r, $g, $b] = $quoteIndex === $i ? self::quoteRgb($quote) : ((str_contains(mb_strtolower((string) $header), 'ergebnis') && str_contains(mb_strtolower($value), 'durch')) ? [248, 215, 218] : [255, 255, 255]); $stream .= sprintf("%0.2f %0.2f %0.2f rg %.2f %.2f %.2f %.2f re f 0.70 0.70 0.70 RG %.2f %.2f %.2f %.2f re S\n", $r / 255, $g / 255, $b / 255, $x, $y, $widths[$i], $rowHeight, $x, $y, $widths[$i], $rowHeight); $stream .= "BT /F1 6 Tf 0.10 0.10 0.10 rg " . ($x + 3) . ' ' . ($y + 7) . " Td (" . self::pdfEscape($value) . ") Tj ET\n"; $x += $widths[$i]; }
            $y -= $rowHeight; $rowIndex++;
        }
        if ($stream !== '') $page($stream);
        $pdf = self::buildPdf($streams); return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $pdf];
    }

    private static function pdfText(string $value): string { return function_exists('iconv') ? (string) iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $value) : $value; }
    private static function pdfEscape(string $value): string { return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $value); }
    private static function buildPdf(array $streams): string { $objects = ['1 0 obj<< /Type /Catalog /Pages 2 0 R>>endobj', '2 0 obj<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($i): string => (string) (4 + $i) . ' 0 R', array_keys($streams))) . '] /Count ' . count($streams) . '>>endobj', '3 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>endobj']; foreach ($streams as $i => $body) $objects[] = (4 + $i) . ' 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources<< /Font<< /F1 3 0 R>>>> /Contents ' . (4 + count($streams) + $i) . ' 0 R>>endobj'; foreach ($streams as $i => $body) $objects[] = (4 + count($streams) + $i) . ' 0 obj<< /Length ' . strlen($body) . '>>stream\n' . $body . 'endstream endobj'; $pdf = "%PDF-1.4\n"; $offsets = []; foreach ($objects as $object) { $offsets[] = strlen($pdf); $pdf .= $object . "\n"; } $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset); return $pdf . "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R>>\nstartxref\n" . $xref . "\n%%EOF"; }
    private static function quoteRgb(float $value): array { return $value <= 10 ? [209, 231, 221] : ($value <= 20 ? [183, 228, 199] : ($value <= 40 ? [255, 243, 205] : ($value <= 60 ? [255, 230, 156] : ($value <= 80 ? [255, 218, 106] : [255, 184, 107])))); }
}
