<?php

declare(strict_types=1);

final class InspectionReportWriter
{
    public static function render(array $rows, string $title, array $branding): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }

        $office = is_executable('/usr/bin/libreoffice')
            ? '/usr/bin/libreoffice'
            : (is_executable('/usr/bin/soffice') ? '/usr/bin/soffice' : '');
        if ($office === '') {
            return null;
        }

        $values = self::values($rows);
        $raw = self::decodeJson($values['__raw_json'] ?? '');
        $measurements = self::decodeJson($values['__measurements_json'] ?? '');
        $checklist = self::decodeJson($values['__checklist_json'] ?? '');
        $items = self::reportItems($rows, $values, $raw, $measurements, $checklist);

        $root = sys_get_temp_dir() . '/pruefapp-inspection-writer';
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            return null;
        }

        $token = bin2hex(random_bytes(8));
        $odtPath = $root . '/' . $token . '.odt';
        $outDir = $root . '/' . $token . '-out';
        $profile = $root . '/' . $token . '-profile';
        if (!mkdir($outDir, 0700, true) && !is_dir($outDir)) {
            return null;
        }

        try {
            if (!self::writeOdt($odtPath, $title, $branding, $values, $raw, $items)) {
                return null;
            }

            $debug = getenv('PRUEFAPP_REPORT_DEBUG') === '1';
            $command = 'timeout 40s ' . escapeshellarg($office)
                . ' -env:UserInstallation=' . escapeshellarg('file://' . $profile)
                . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outDir)
                . ' ' . escapeshellarg($odtPath) . ' 2>&1';
            $conversionLog = (string) shell_exec($command);
            if ($debug) file_put_contents($root . '/last-conversion.log', $conversionLog, LOCK_EX);
            $converted = glob($outDir . '/*.pdf') ?: [];
            if ($converted === []) {
                return null;
            }

            $body = file_get_contents($converted[0]);
            return is_string($body) && $body !== '' ? $body : null;
        } finally {
            if (getenv('PRUEFAPP_REPORT_DEBUG') === '1' && is_file($odtPath)) {
                @copy($odtPath, $root . '/last-report.odt');
            }
            @unlink($odtPath);
            foreach (glob($outDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($outDir);
            self::removeDirectory($profile);
        }
    }

    private static function writeOdt(
        string $path,
        string $title,
        array $branding,
        array $values,
        array $raw,
        array $items
    ): bool {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        if (method_exists($zip, 'setCompressionName')) {
            $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        }

        $images = [];
        $logo = self::logoAsset($branding);
        if ($logo !== null) {
            $images['Pictures/report-logo.' . $logo['extension']] = $logo;
        }
        $signature = self::signatureAsset($raw, $values);
        if ($signature !== null) {
            $images['Pictures/report-signature.' . $signature['extension']] = $signature;
        }
        foreach ($images as $name => $image) {
            $zip->addFromString($name, $image['body']);
        }

        $zip->addFromString('content.xml', self::contentXml($title, $branding, $values, $raw, $items, $images));
        $zip->addFromString('styles.xml', self::stylesXml($branding));
        $zip->addFromString('meta.xml', self::metaXml($title));
        $zip->addFromString('settings.xml', self::settingsXml());
        $zip->addFromString('META-INF/manifest.xml', self::manifestXml($images));
        return $zip->close();
    }

    private static function contentXml(
        string $title,
        array $branding,
        array $values,
        array $raw,
        array $items,
        array $images
    ): string {
        $company = trim((string) ($branding['company_name'] ?? 'CENEOS GmbH')) ?: 'CENEOS GmbH';
        $primary = self::color($branding['theme_colors']['primary'] ?? '', '#E6B800');
        $result = trim((string) ($values['Ergebnis'] ?? 'ausstehend')) ?: 'ausstehend';
        $resultStyle = self::resultStyle($result);
        $logoName = self::firstImageName($images, 'report-logo.');
        $signatureName = self::firstImageName($images, 'report-signature.');
        $number = (string) ($values['Prüfnummer'] ?? '');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-content office:version="1.3"'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
            . ' xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"'
            . ' xmlns:xlink="http://www.w3.org/1999/xlink"'
            . ' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"'
            . ' xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">'
            . '<office:scripts/><office:font-face-decls/>'
            . '<office:automatic-styles>'
            . self::automaticStyles($primary)
            . '</office:automatic-styles><office:body><office:text>';

        $xml .= '<table:table table:name="Berichtskopf" table:style-name="THead">'
            . '<table:table-column table:style-name="HeadCol1"/>'
            . '<table:table-column table:style-name="HeadCol2"/>'
            . '<table:table-column table:style-name="HeadCol3"/>'
            . '<table:table-row>';
        $xml .= '<table:table-cell table:style-name="CellNoBorder"><text:p text:style-name="PLogo">';
        if ($logoName !== null) {
            $xml .= self::imageXml($logoName, '4.2cm', '1.26cm', 'Logo');
        }
        $xml .= '</text:p></table:table-cell>';
        $xml .= '<table:table-cell table:style-name="CellNoBorder">'
            . self::paragraph($company, 'PCompany')
            . self::paragraph('Gutenbergstr. 1–3', 'PSmall')
            . self::paragraph('53757 Sankt Augustin', 'PSmall')
            . self::paragraph('Deutschland', 'PSmall')
            . '</table:table-cell>';
        $xml .= '<table:table-cell table:style-name="CellNoBorder">'
            . self::paragraph('Prüfberichts-Nr.:', 'PReportLabel')
            . self::paragraph($number, 'PReportNumber')
            . '</table:table-cell></table:table-row></table:table>';

        $xml .= '<table:table table:name="Akzent" table:style-name="TAccent"><table:table-column table:style-name="AccentCol"/>'
            . '<table:table-row><table:table-cell table:style-name="CellAccent"><text:p/></table:table-cell></table:table-row></table:table>';
        $xml .= self::paragraph('Prüfbericht', 'PTitle');

        $groups = self::metadataGroups($values, $result);
        $xml .= '<table:table table:name="Prüfungsdaten" table:style-name="TMetaOuter">'
            . '<table:table-column table:style-name="MetaOuterCol1"/>'
            . '<table:table-column table:style-name="MetaOuterCol2"/>'
            . '<table:table-column table:style-name="MetaOuterCol3"/>'
            . '<table:table-row>';
        foreach ($groups as $groupIndex => $group) {
            $xml .= '<table:table-cell table:style-name="CellMetaOuter">'
                . '<table:table table:name="Metablock' . ($groupIndex + 1) . '" table:style-name="TMetaInner">'
                . '<table:table-column table:style-name="MetaLabelCol' . ($groupIndex + 1) . '"/>'
                . '<table:table-column table:style-name="MetaValueCol' . ($groupIndex + 1) . '"/>';
            foreach ($group as [$label, $value]) {
                if ($value === '') {
                    continue;
                }
                $valueCellStyle = $label === 'Ergebnis' ? $resultStyle : 'CellMetaValue';
                $valueParagraphStyle = $label === 'Ergebnis' ? 'PResult' : 'PMetaValue';
                $xml .= '<table:table-row>'
                    . '<table:table-cell table:style-name="CellMetaLabel">' . self::paragraph($label . ':', 'PMetaLabel') . '</table:table-cell>'
                    . '<table:table-cell table:style-name="' . $valueCellStyle . '">' . self::paragraph($value, $valueParagraphStyle) . '</table:table-cell>'
                    . '</table:table-row>';
            }
            $xml .= '</table:table><text:p/></table:table-cell>';
        }
        $xml .= '</table:table-row></table:table>';

        $xml .= self::paragraph('Prüfergebnisse', 'PSection');
        $xml .= '<table:table table:name="Prüfergebnisse" table:style-name="TResults">'
            . '<table:table-column table:style-name="ResultCol1"/>'
            . '<table:table-column table:style-name="ResultCol2"/>'
            . '<table:table-column table:style-name="ResultCol3"/>'
            . '<table:table-column table:style-name="ResultCol4"/>'
            . '<table:table-column table:style-name="ResultCol5"/>'
            . '<table:table-header-rows><table:table-row>';
        foreach (['Fragentyp', 'Prüffrage', 'Kriterium', 'Ergebnis', 'OK'] as $header) {
            $xml .= '<table:table-cell table:style-name="CellHeader">' . self::paragraph($header, 'PTableHeader') . '</table:table-cell>';
        }
        $xml .= '</table:table-row></table:table-header-rows>';
        foreach ($items as $item) {
            $ok = (bool) ($item['ok'] ?? false);
            $failed = (bool) ($item['failed'] ?? false);
            $resultCellStyle = $failed ? 'CellResultFailed' : (($item['result'] ?? '') !== '' ? 'CellResultNeutral' : 'CellBody');
            $okCellStyle = $failed ? 'CellResultFailed' : ($ok ? 'CellResultPassed' : 'CellResultPending');
            $xml .= '<table:table-row table:style-name="RowKeep">'
                . '<table:table-cell table:style-name="CellBody">' . self::paragraph((string) ($item['category'] ?? ''), 'PTable') . '</table:table-cell>'
                . '<table:table-cell table:style-name="CellBody">' . self::paragraph((string) ($item['question'] ?? ''), 'PTable') . '</table:table-cell>'
                . '<table:table-cell table:style-name="CellBody">' . self::paragraph((string) ($item['criterion'] ?? ''), 'PTable') . '</table:table-cell>'
                . '<table:table-cell table:style-name="' . $resultCellStyle . '">' . self::paragraph((string) ($item['result'] ?? ''), 'PTableCenter') . '</table:table-cell>'
                . '<table:table-cell table:style-name="' . $okCellStyle . '">' . self::paragraph($failed ? '✕' : ($ok ? '✓' : '…'), 'PBadge') . '</table:table-cell>'
                . '</table:table-row>';
        }
        $xml .= '</table:table>';

        $completion = self::formatDate((string) ($values['Prüfungsabschluss'] ?? ($raw['end_time'] ?? ($values['Datum'] ?? ''))));
        $xml .= '<table:table table:name="Abschluss" table:style-name="TClosing">'
            . '<table:table-column table:style-name="ClosingCol1"/><table:table-column table:style-name="ClosingCol2"/>'
            . '<table:table-row><table:table-cell table:style-name="CellNoBorder">'
            . self::labelValueParagraph('Regiezeit:', (string) ($values['Regiezeit'] ?? '0 Minuten'))
            . self::labelValueParagraph('Prüfungsabschluss:', $completion)
            . self::paragraph((string) ($values['Regiebegründung'] ?? ''), 'PSmallMuted')
            . '</table:table-cell><table:table-cell table:style-name="CellNoBorder">'
            . self::paragraph('Unterschrift:', 'PMetaLabel');
        if ($signatureName !== null) {
            $xml .= '<text:p text:style-name="PSignature">' . self::imageXml($signatureName, '3.8cm', '1.6cm', 'Unterschrift') . '</text:p>';
        } else {
            $xml .= '<text:p text:style-name="PSignatureLine">&#160;</text:p>';
        }
        $xml .= self::paragraph((string) ($values['Prüfer'] ?? ''), 'PSmallMuted')
            . '</table:table-cell></table:table-row></table:table>';

        $xml .= '</office:text></office:body></office:document-content>';
        return $xml;
    }

    private static function automaticStyles(string $primary): string
    {
        return '<style:style style:name="THead" style:family="table"><style:table-properties table:align="margins" style:width="17.7cm"/></style:style>'
            . self::columnStyle('HeadCol1', '4.7cm') . self::columnStyle('HeadCol2', '7.0cm') . self::columnStyle('HeadCol3', '6.0cm')
            . '<style:style style:name="TAccent" style:family="table"><style:table-properties table:align="margins" style:width="17.7cm" fo:margin-top="0.25cm" fo:margin-bottom="0.35cm"/></style:style>'
            . self::columnStyle('AccentCol', '17.7cm')
            . '<style:style style:name="TMetaOuter" style:family="table"><style:table-properties table:align="margins" style:width="17.7cm" fo:margin-bottom="0.7cm"/></style:style>'
            . self::columnStyle('MetaOuterCol1', '8.0cm') . self::columnStyle('MetaOuterCol2', '4.6cm') . self::columnStyle('MetaOuterCol3', '5.1cm')
            . '<style:style style:name="TMetaInner" style:family="table"><style:table-properties table:align="margins"/></style:style>'
            . self::columnStyle('MetaLabelCol1', '2.15cm') . self::columnStyle('MetaValueCol1', '5.65cm')
            . self::columnStyle('MetaLabelCol2', '2.05cm') . self::columnStyle('MetaValueCol2', '2.35cm')
            . self::columnStyle('MetaLabelCol3', '2.1cm') . self::columnStyle('MetaValueCol3', '2.8cm')
            . '<style:style style:name="TResults" style:family="table"><style:table-properties table:align="margins" style:width="17.7cm" fo:margin-bottom="0.55cm"/></style:style>'
            . self::columnStyle('ResultCol1', '2.45cm') . self::columnStyle('ResultCol2', '6.5cm')
            . self::columnStyle('ResultCol3', '5.5cm') . self::columnStyle('ResultCol4', '2.15cm') . self::columnStyle('ResultCol5', '1.1cm')
            . '<style:style style:name="TClosing" style:family="table"><style:table-properties table:align="margins" style:width="17.7cm" fo:margin-top="0.45cm"/></style:style>'
            . self::columnStyle('ClosingCol1', '9.2cm') . self::columnStyle('ClosingCol2', '8.5cm')
            . '<style:style style:name="CellNoBorder" style:family="table-cell"><style:table-cell-properties fo:padding="0.08cm" fo:border="none" style:vertical-align="top"/></style:style>'
            . '<style:style style:name="CellAccent" style:family="table-cell"><style:table-cell-properties fo:background-color="' . $primary . '" fo:padding="0.045cm" fo:border="none"/></style:style>'
            . '<style:style style:name="CellMetaOuter" style:family="table-cell"><style:table-cell-properties fo:padding-right="0.22cm" fo:border="none" style:vertical-align="top"/></style:style>'
            . '<style:style style:name="CellMetaLabel" style:family="table-cell"><style:table-cell-properties fo:padding="0.06cm 0.08cm" fo:border="none" style:vertical-align="top"/></style:style>'
            . '<style:style style:name="CellMetaValue" style:family="table-cell"><style:table-cell-properties fo:padding="0.06cm 0.08cm" fo:border="none" style:vertical-align="top"/></style:style>'
            . '<style:style style:name="CellHeader" style:family="table-cell"><style:table-cell-properties fo:background-color="#D9D9D9" fo:border="0.5pt solid #222222" fo:padding="0.13cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellBody" style:family="table-cell"><style:table-cell-properties fo:border="0.5pt solid #222222" fo:padding="0.13cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellResultPassed" style:family="table-cell"><style:table-cell-properties fo:background-color="#D1E7DD" fo:border="0.5pt solid #222222" fo:padding="0.11cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellResultFailed" style:family="table-cell"><style:table-cell-properties fo:background-color="#F8D7DA" fo:border="0.5pt solid #222222" fo:padding="0.11cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellResultPending" style:family="table-cell"><style:table-cell-properties fo:background-color="#FFF3CD" fo:border="0.5pt solid #222222" fo:padding="0.11cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellResultNeutral" style:family="table-cell"><style:table-cell-properties fo:background-color="#EEF1F4" fo:border="0.5pt solid #222222" fo:padding="0.11cm" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellMetaPassed" style:family="table-cell"><style:table-cell-properties fo:background-color="#D1E7DD" fo:padding="0.07cm 0.13cm" fo:border="0.5pt solid #75B798" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellMetaFailed" style:family="table-cell"><style:table-cell-properties fo:background-color="#F8D7DA" fo:padding="0.07cm 0.13cm" fo:border="0.5pt solid #EA868F" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="CellMetaPending" style:family="table-cell"><style:table-cell-properties fo:background-color="#FFF3CD" fo:padding="0.07cm 0.13cm" fo:border="0.5pt solid #FFDA6A" style:vertical-align="middle"/></style:style>'
            . '<style:style style:name="RowKeep" style:family="table-row"><style:table-row-properties fo:keep-together="always"/></style:style>'
            . self::paragraphStyle('PLogo', '8pt', false, '#111111', 'start', '0cm', '0cm')
            . self::paragraphStyle('PCompany', '10pt', true, '#111111', 'start', '0cm', '0.04cm')
            . self::paragraphStyle('PSmall', '8pt', false, '#333333', 'start', '0cm', '0.02cm')
            . self::paragraphStyle('PSmallMuted', '7.5pt', false, '#666666', 'start', '0.04cm', '0.02cm')
            . self::paragraphStyle('PReportLabel', '9pt', true, '#111111', 'end', '0cm', '0.06cm')
            . self::paragraphStyle('PReportNumber', '10pt', false, '#111111', 'end', '0cm', '0.02cm')
            . self::paragraphStyle('PTitle', '16pt', true, '#222222', 'start', '0cm', '0.55cm')
            . self::paragraphStyle('PSection', '11pt', true, '#222222', 'start', '0cm', '0.18cm')
            . self::paragraphStyle('PMetaLabel', '8pt', true, '#222222', 'start', '0cm', '0cm')
            . self::paragraphStyle('PMetaValue', '8pt', false, '#222222', 'start', '0cm', '0cm')
            . self::paragraphStyle('PResult', '8pt', true, '#222222', 'center', '0cm', '0cm')
            . self::paragraphStyle('PTableHeader', '7.5pt', true, '#111111', 'start', '0cm', '0cm')
            . self::paragraphStyle('PTable', '7.4pt', false, '#111111', 'start', '0cm', '0cm')
            . self::paragraphStyle('PTableCenter', '7.4pt', false, '#111111', 'center', '0cm', '0cm')
            . self::paragraphStyle('PBadge', '10pt', true, '#111111', 'center', '0cm', '0cm')
            . self::paragraphStyle('PSignature', '8pt', false, '#111111', 'start', '0.05cm', '0cm')
            . '<style:style style:name="TStrong" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style>'
            . '<style:style style:name="PSignatureLine" style:family="paragraph"><style:paragraph-properties fo:margin-top="0.65cm" fo:margin-bottom="0.08cm" fo:border-bottom="0.5pt solid #666666"/><style:text-properties fo:font-size="8pt"/></style:style>';
    }

    private static function stylesXml(array $branding): string
    {
        $company = self::xml((string) ($branding['company_name'] ?? 'CENEOS GmbH'));
        $date = (new DateTimeImmutable())->format('d.m.Y');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-styles office:version="1.3"'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
            . ' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">'
            . '<office:font-face-decls/><office:styles>'
            . '<style:default-style style:family="paragraph"><style:paragraph-properties fo:orphans="2" fo:widows="2"/><style:text-properties style:font-name="Liberation Sans" fo:font-family="Liberation Sans" fo:font-size="8pt"/></style:default-style>'
            . '<style:style style:name="Standard" style:family="paragraph" style:class="text"/>'
            . '<style:style style:name="PFooter" style:family="paragraph"><style:paragraph-properties><style:tab-stops><style:tab-stop style:type="right" style:position="17.5cm"/></style:tab-stops></style:paragraph-properties><style:text-properties fo:font-size="7pt" fo:color="#555555"/></style:style>'
            . '</office:styles><office:automatic-styles>'
            . '<style:page-layout style:name="PageA4"><style:page-layout-properties fo:page-width="21cm" fo:page-height="29.7cm" style:print-orientation="portrait" fo:margin-left="2.0cm" fo:margin-right="1.3cm" fo:margin-top="1.2cm" fo:margin-bottom="1.35cm"/><style:footer-style><style:header-footer-properties fo:min-height="0.45cm" fo:margin-left="0cm" fo:margin-right="0cm" fo:margin-top="0.2cm"/></style:footer-style></style:page-layout>'
            . '</office:automatic-styles><office:master-styles>'
            . '<style:master-page style:name="Standard" style:page-layout-name="PageA4"><style:footer><text:p text:style-name="PFooter">' . $company . '<text:tab/>' . self::xml($date) . ' · Seite <text:page-number text:select-page="current">1</text:page-number></text:p></style:footer></style:master-page>'
            . '</office:master-styles></office:document-styles>';
    }

    private static function reportItems(array $rows, array $values, array $raw, array $measurements, array $checklist): array
    {
        $globalPassed = self::isPassed((string) ($values['Ergebnis'] ?? '')) || (($raw['audit_ok'] ?? false) === true);
        $globalFailed = self::isFailed((string) ($values['Ergebnis'] ?? ''));
        $items = [];
        if ($checklist !== []) {
            foreach (array_values($checklist) as $index => $step) {
                if (!is_array($step)) {
                    continue;
                }
                $question = trim((string) ($step['step'] ?? $step['label'] ?? ''));
                if ($question === '') {
                    continue;
                }
                $category = trim((string) ($step['function']['title'] ?? $step['category'] ?? ''));
                $category = $category !== '' ? $category : self::category($question, $index);
                $result = trim(implode(' ', array_filter([
                    (string) ($step['result'] ?? ''),
                    (string) ($step['unit'] ?? ''),
                ], static fn(string $value): bool => trim($value) !== '')));
                $failed = array_key_exists('check', $step) && $step['check'] === false;
                $ok = !$failed && (($step['check'] ?? null) === true || self::isPassed($result) || ($result === '' && $globalPassed));
                $items[] = compact('category', 'question') + [
                    'criterion' => trim((string) ($step['criterion'] ?? '')),
                    'result' => $result,
                    'ok' => $ok,
                    'failed' => $failed,
                ];
            }
            if ($items !== []) {
                return $items;
            }
        }

        $manualChecklistItems = self::manualChecklistItems($checklist, $values);

        $measurementMap = self::measurementMap($measurements, $rows);
        $steps = [];
        foreach ($raw as $key => $question) {
            if (preg_match('/^step(\d+)$/', (string) $key, $match)) {
                $steps[(int) $match[1]] = (string) $question;
            }
        }
        if ($steps !== []) {
            ksort($steps);
            foreach ($steps as $index => $question) {
                $result = trim((string) ($raw['result' . $index] ?? ''));
                if ($result === '') {
                    $measurementName = self::measurementNameForQuestion($question);
                    $result = $measurementName !== '' ? (string) ($measurementMap[$measurementName]['display'] ?? '') : '';
                }
                $failed = self::isFailed($result);
                $items[] = [
                    'category' => self::category($question, $index),
                    'question' => $question,
                    'criterion' => trim((string) ($raw['criterion' . $index] ?? '')),
                    'result' => $result,
                    'ok' => !$failed && (self::isPassed($result) || ($result === '' && $globalPassed) || $globalPassed),
                    'failed' => $failed,
                ];
            }
            return $items;
        }

        $manualFunctionItems = [];
        if ($manualChecklistItems !== []) {
            $items = [];
            foreach ($manualChecklistItems as $manualItem) {
                if (($manualItem['category'] ?? '') === 'Funktionsprüfung') $manualFunctionItems[] = $manualItem;
                else $items[] = $manualItem;
            }
        } else {
            $items = self::defaultQuestions($values, $globalPassed);
        }
        $recognized = [];
        foreach ($measurementMap as $name => $measurement) {
            if (!in_array($name, ['RPE', 'RSL', 'RISO', 'IPE', 'IBER', 'IEA', 'KABEL', 'FI/RCD'], true)) {
                continue;
            }
            $recognized[] = $name;
            $display = (string) ($measurement['display'] ?? '');
            $failed = self::isFailed($display);
            $items[] = [
                'category' => 'Messung',
                'question' => self::measurementQuestion($name),
                'criterion' => self::measurementCriterion($name, $values),
                'result' => $display,
                'ok' => !$failed && (self::isPassed($display) || $globalPassed),
                'failed' => $failed,
            ];
        }
        if ($recognized === []) {
            foreach ($measurementMap as $name => $measurement) {
                $display = (string) ($measurement['display'] ?? '');
                $items[] = [
                    'category' => 'Messung',
                    'question' => $name,
                    'criterion' => '',
                    'result' => $display,
                    'ok' => self::isPassed($display) || $globalPassed,
                    'failed' => self::isFailed($display),
                ];
            }
        }
        $items = array_merge($items, $manualFunctionItems);
        $closing = self::defaultClosingQuestions($globalPassed, $globalFailed);
        if ($manualChecklistItems !== []) array_shift($closing);
        return array_merge($items, $closing);
    }

    private static function manualChecklistItems(array $checklist, array $values): array
    {
        $definitions = [
            'label' => ['Sichtprüfung', 'Ist die Beschriftung vollständig und lesbar?', 'Beschriftungen sind vollständig und eindeutig erkennbar.'],
            'leitung' => ['Sichtprüfung', 'Sind Anschlussleitung und Zugentlastung ohne erkennbare Schäden?', 'Leitung, Stecker und Zugentlastung sind unbeschädigt.'],
            'gehaeuse' => ['Sichtprüfung', 'Sind Gehäuse und Lüftungsöffnungen ohne erkennbare Schäden?', 'Gehäuse und Lüftungsöffnungen sind intakt und sauber.'],
            'stecker' => ['Inventarisierung', 'Sind Stecker, Kontakte und Schutzklasse eindeutig und unbeschädigt?', self::plugCriterion($values)],
            'funktion' => ['Funktionsprüfung', 'Arbeiten alle sicherheitsrelevanten Funktionen ordnungsgemäß?', 'Es sind keine sicherheitsrelevanten Abweichungen erkennbar.'],
        ];
        $items = [];
        foreach ($definitions as $key => [$category, $question, $criterion]) {
            if (!array_key_exists($key, $checklist) || is_array($checklist[$key])) continue;
            $status = mb_strtolower(trim((string) $checklist[$key]));
            $failed = $status === 'nein';
            $items[] = [
                'category' => $category,
                'question' => $question,
                'criterion' => $criterion,
                'result' => $status === 'ja' || $status === 'ok' ? 'Ja' : ($failed ? 'Nein' : 'Offen'),
                'ok' => $status === 'ja' || $status === 'ok',
                'failed' => $failed,
            ];
        }
        return $items;
    }

    private static function plugCriterion(array $values): string
    {
        $class = strtoupper(trim((string) ($values['Prüfart'] ?? '')));
        if (str_contains($class, 'II')) return 'Keine Schutzkontakte; Schutzisolierung ist eindeutig erkennbar.';
        if (str_contains($class, 'III')) return 'Schutzkleinspannung und Anschluss sind eindeutig erkennbar.';
        if (str_contains($class, 'KABEL')) return 'Stecker, Kupplung und Kontakte sind vollständig und unbeschädigt.';
        return 'Metallische Schutzkontakte auf 6 und 12 Uhr sind vorhanden und unbeschädigt.';
    }

    private static function defaultQuestions(array $values, bool $passed): array
    {
        $plugCriterion = self::plugCriterion($values);
        return [
            ['category' => 'Inventarisierung', 'question' => 'Eingabe und Identifikation des zu prüfenden Gerätes auf Grundlage des Netzanschlusses.', 'criterion' => $plugCriterion, 'result' => '', 'ok' => $passed, 'failed' => false],
            ['category' => 'Sichtprüfung', 'question' => 'Sind keine Schäden an der Beschriftung erkennbar?', 'criterion' => 'Beschriftungen sind vollständig und eindeutig erkennbar.', 'result' => '', 'ok' => $passed, 'failed' => false],
            ['category' => 'Sichtprüfung', 'question' => 'Sind keine Schäden an der Anschlussleitung erkennbar?', 'criterion' => 'Leitung, Stecker und Zugentlastung sind unbeschädigt.', 'result' => '', 'ok' => $passed, 'failed' => false],
            ['category' => 'Sichtprüfung', 'question' => 'Sind keine Abweichungen am Gehäuse erkennbar?', 'criterion' => 'Gehäuse und Kühlluftöffnungen sind intakt und sauber.', 'result' => '', 'ok' => $passed, 'failed' => false],
        ];
    }

    private static function defaultClosingQuestions(bool $passed, bool $failed): array
    {
        $functionResult = $passed ? 'Ja' : ($failed ? 'Nein' : 'Offen');
        $safeResult = $passed ? 'Ja' : ($failed ? 'Nein' : 'Offen');
        $noticeResult = $passed ? 'Nein' : ($failed ? 'Ja' : 'Offen');
        return [
            ['category' => 'Funktionsprüfung', 'question' => 'Arbeiten alle sicherheitsrelevanten Funktionen ordnungsgemäß?', 'criterion' => 'Es sind keine sicherheitsrelevanten Abweichungen erkennbar.', 'result' => $functionResult, 'ok' => $passed, 'failed' => $failed],
            ['category' => 'Organisatorische Hinweise', 'question' => 'Ist ein sicherer Betrieb des Gerätes bis zur nächsten Prüfung zu erwarten?', 'criterion' => 'Ein sicherer Betrieb ist bis zur nächsten Prüfung zu erwarten.', 'result' => $safeResult, 'ok' => $passed, 'failed' => $failed],
            ['category' => 'Organisatorische Hinweise', 'question' => 'Muss der Auftraggeber auf Mängel oder Abweichungen hingewiesen werden?', 'criterion' => 'Es sind keine zusätzlichen Hinweise oder Abweichungen vorhanden.', 'result' => $noticeResult, 'ok' => $passed, 'failed' => $failed],
        ];
    }

    private static function metadataGroups(array $values, string $result): array
    {
        return [
            [
                ['Prüfungs-Nr.', (string) ($values['Prüfnummer'] ?? '')],
                ['Datum', self::formatDate((string) ($values['Datum'] ?? ''))],
                ['Art der Prüfung', (string) ($values['Prüfart'] ?? ($values['Prüfungstyp'] ?? ''))],
                ['Prüfer', (string) ($values['Prüfer'] ?? '')],
                ['Nächste Prüfung', self::formatDate((string) ($values['Nächste Prüfung'] ?? ''))],
                ['Gerät', (string) ($values['Gerät'] ?? '')],
            ],
            [
                ['Inventar-Nr.', (string) ($values['Inventarnummer'] ?? '')],
                ['Geräteart', (string) ($values['Geräteart'] ?? '')],
                ['Hersteller', (string) ($values['Hersteller'] ?? '')],
                ['Typ', (string) ($values['Typ'] ?? '')],
                ['Wärmegerät', (string) ($values['Wärmegerät'] ?? '')],
            ],
            [
                ['Auftraggeber', (string) ($values['Auftraggeber'] ?? '')],
                ['Liegenschaft', (string) ($values['Liegenschaft'] ?? '')],
                ['Gebäude', (string) ($values['Gebäude'] ?? '')],
                ['Etage', (string) ($values['Etage'] ?? '')],
                ['Raum-Nr.', (string) ($values['Raum-Nr.'] ?? '')],
                ['Ergebnis', $result],
            ],
        ];
    }

    private static function measurementMap(array $measurements, array $rows): array
    {
        $map = [];
        foreach ($measurements as $measurement) {
            if (!is_array($measurement)) {
                continue;
            }
            $name = mb_strtoupper(trim((string) ($measurement['name'] ?? 'MESSUNG')));
            $parts = array_values(array_filter([
                trim((string) ($measurement['value'] ?? '')),
                trim((string) ($measurement['unit'] ?? '')),
            ], static fn(string $value): bool => $value !== ''));
            $status = trim((string) ($measurement['result'] ?? ''));
            $display = trim(implode(' ', $parts) . ($status !== '' ? ' · ' . $status : ''));
            $map[$name] = ['display' => $display];
        }
        if ($map === []) {
            $known = ['PRÜFNUMMER', 'DATUM', 'PRÜFART', 'PRÜFUNGSTYP', 'PRÜFER', 'NÄCHSTE PRÜFUNG', 'GERÄT', 'ERGEBNIS', 'REGIEZEIT', 'REGIEBEGRÜNDUNG', 'INVENTARNUMMER', 'GERÄTEART', 'HERSTELLER', 'TYP', 'WÄRMEGERÄT', 'AUFTRAGGEBER', 'LIEGENSCHAFT', 'GEBÄUDE', 'ETAGE', 'RAUM-NR.', 'PRÜFUNGSABSCHLUSS'];
            foreach (array_slice($rows, 1) as $row) {
                $name = mb_strtoupper(trim((string) ($row[0] ?? '')));
                if ($name === '' || str_starts_with($name, '__') || in_array($name, $known, true) || $name === 'PRÜFSCHRITTE') {
                    continue;
                }
                $map[$name] = ['display' => trim((string) ($row[1] ?? ''))];
            }
        }
        return $map;
    }

    private static function measurementNameForQuestion(string $question): string
    {
        $upper = strtoupper($question);
        foreach (['RPE', 'RSL', 'RISO', 'IPE', 'IBER', 'IEA'] as $name) {
            if (str_contains(str_replace(' ', '', $upper), $name)) {
                return $name;
            }
        }
        if (str_contains($upper, 'SCHUTZLEITERWIDERSTAND')) {
            return 'RPE';
        }
        if (str_contains($upper, 'ISOLATIONSWIDERSTAND')) {
            return 'RISO';
        }
        if (str_contains($upper, 'BERÜHRUNGSSTROM')) {
            return 'IBER';
        }
        if (str_contains($upper, 'SCHUTZLEITERSTROM')) {
            return 'IPE';
        }
        return '';
    }

    private static function measurementQuestion(string $name): string
    {
        return match ($name) {
            'RPE', 'RSL' => 'Messung Schutzleiterwiderstand R SL mit Ergebnis in Ohm.',
            'RISO' => 'Messung Isolationswiderstand R ISO mit Ergebnis in MΩ.',
            'IPE' => 'Messung Schutzleiterstrom I PE mit Ergebnis in mA.',
            'IBER' => 'Messung Berührungsstrom I B mit Ergebnis in mA.',
            'IEA' => 'Messung Ersatzableitstrom I EA mit Ergebnis in mA.',
            'KABEL' => 'Prüfung der Anschlussleitung.',
            'FI/RCD' => 'Prüfung des Fehlerstrom-Schutzschalters.',
            default => $name,
        };
    }

    private static function measurementCriterion(string $name, array $values): string
    {
        $warming = strtolower((string) ($values['Wärmegerät'] ?? '')) === 'ja';
        return match ($name) {
            'RPE', 'RSL' => '≤ 0,3 Ω bis 5 m Leitungslänge; je weitere 7,5 m +0,1 Ω, maximal 1 Ω.',
            'RISO' => $warming ? '≥ 0,3 MΩ bei Geräten mit Heizelementen.' : '≥ 1 MΩ.',
            'IPE' => '≤ 3,5 mA an leitfähigen Teilen mit Schutzleiterverbindung.',
            'IBER' => '≤ 0,5 mA an berührbaren leitfähigen Teilen.',
            'IEA' => 'Grenzwert gemäß Prüfart und Schutzklasse.',
            'KABEL' => 'Leitung und Schutzleiterverbindung sind elektrisch sicher.',
            'FI/RCD' => 'Auslöseverhalten innerhalb des zulässigen Grenzwerts.',
            default => '',
        };
    }

    private static function category(string $question, int $index): string
    {
        $lower = mb_strtolower($question);
        if ($index === 0 || str_contains($lower, 'eingabe') || str_contains($lower, 'identifik')) {
            return 'Inventarisierung';
        }
        if (str_contains($lower, 'messung') || str_contains($lower, 'widerstand') || str_contains($lower, 'strom')) {
            return 'Messung';
        }
        if (str_contains($lower, 'beschrift') || str_contains($lower, 'leitung') || str_contains($lower, 'gehäuse')) {
            return 'Sichtprüfung';
        }
        if (str_contains($lower, 'funktion')) {
            return 'Funktionsprüfung';
        }
        return 'Organisatorische Hinweise';
    }

    private static function signatureAsset(array $raw, array $values): ?array
    {
        $candidates = [
            $raw['signature'] ?? null,
            $raw['signature_data'] ?? null,
            $raw['signature_image'] ?? null,
            $values['__profile_signature'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || !preg_match('#^data:(image/(?:png|jpeg|svg\+xml));base64,(.+)$#s', trim($candidate), $match)) {
                continue;
            }
            $body = base64_decode($match[2], true);
            if (!is_string($body) || $body === '') {
                continue;
            }
            return [
                'body' => $body,
                'mime' => $match[1],
                'extension' => $match[1] === 'image/png' ? 'png' : ($match[1] === 'image/jpeg' ? 'jpg' : 'svg'),
            ];
        }
        return null;
    }

    private static function logoAsset(array $branding): ?array
    {
        $path = (string) (($branding['logos']['long'] ?? '') ?: (($branding['logos']['light'] ?? '') ?: ($branding['header_logo']['path'] ?? '')));
        if ($path !== '' && !str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . ltrim($path, '/');
        }
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
        return ['body' => (string) file_get_contents($path), 'mime' => $mime, 'extension' => $extension === 'jpeg' ? 'jpg' : $extension];
    }

    private static function manifestXml(array $images): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<manifest:manifest manifest:version="1.3" xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">'
            . '<manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>';
        foreach ($images as $name => $image) {
            $xml .= '<manifest:file-entry manifest:full-path="' . self::xml($name) . '" manifest:media-type="' . self::xml($image['mime']) . '"/>';
        }
        return $xml . '</manifest:manifest>';
    }

    private static function metaXml(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-meta office:version="1.3" xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<office:meta><dc:title>' . self::xml($title) . '</dc:title><meta:generator>Prüfapp InspectionReportWriter</meta:generator><meta:creation-date>' . (new DateTimeImmutable())->format(DATE_ATOM) . '</meta:creation-date></office:meta></office:document-meta>';
    }

    private static function settingsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><office:document-settings office:version="1.3" xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"><office:settings/></office:document-settings>';
    }

    private static function values(array $rows): array
    {
        $values = [];
        foreach (array_slice($rows, 1) as $row) {
            $key = trim((string) ($row[0] ?? ''));
            if ($key !== '') {
                $values[$key] = trim((string) ($row[1] ?? ''));
            }
        }
        return $values;
    }

    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function isPassed(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        return $value !== '' && !self::isFailed($value)
            && (str_contains($value, 'bestanden') || in_array($value, ['ok', 'ja', 'gut', 'passed', 'true', '1'], true));
    }

    private static function isFailed(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        return str_contains($value, 'durchgefallen') || str_contains($value, 'nicht bestanden')
            || in_array($value, ['nein', 'fail', 'failed', 'nok', 'false'], true);
    }

    private static function resultStyle(string $result): string
    {
        return self::isFailed($result) ? 'CellMetaFailed' : (self::isPassed($result) ? 'CellMetaPassed' : 'CellMetaPending');
    }

    private static function formatDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($value))->format('d.m.Y');
        } catch (Throwable) {
            return $value;
        }
    }

    private static function color(string $value, string $fallback): string
    {
        $value = strtoupper(trim($value));
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value;
        }
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
    }

    private static function paragraph(string $text, string $style): string
    {
        $lines = preg_split('/\R/u', $text) ?: [''];
        return '<text:p text:style-name="' . $style . '">' . implode('<text:line-break/>', array_map([self::class, 'xml'], $lines)) . '</text:p>';
    }

    private static function labelValueParagraph(string $label, string $value): string
    {
        return '<text:p text:style-name="PMetaValue"><text:span text:style-name="TStrong">' . self::xml($label) . '</text:span> ' . self::xml($value) . '</text:p>';
    }

    private static function imageXml(string $name, string $width, string $height, string $label): string
    {
        return '<draw:frame draw:name="' . self::xml($label) . '" text:anchor-type="as-char" svg:width="' . $width . '" svg:height="' . $height . '">'
            . '<draw:image xlink:href="' . self::xml($name) . '" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/>'
            . '</draw:frame>';
    }

    private static function columnStyle(string $name, string $width): string
    {
        return '<style:style style:name="' . $name . '" style:family="table-column"><style:table-column-properties style:column-width="' . $width . '"/></style:style>';
    }

    private static function paragraphStyle(string $name, string $size, bool $bold, string $color, string $align, string $top, string $bottom): string
    {
        return '<style:style style:name="' . $name . '" style:family="paragraph"><style:paragraph-properties fo:text-align="' . $align . '" fo:margin-top="' . $top . '" fo:margin-bottom="' . $bottom . '"/><style:text-properties fo:font-size="' . $size . '" fo:color="' . $color . '"' . ($bold ? ' fo:font-weight="bold"' : '') . '/></style:style>';
    }

    private static function firstImageName(array $images, string $prefix): ?string
    {
        foreach (array_keys($images) as $name) {
            if (str_starts_with(basename($name), $prefix)) {
                return $name;
            }
        }
        return null;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
