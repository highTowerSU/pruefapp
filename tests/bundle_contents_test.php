<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/controllers/ReportController.php';
require_once dirname(__DIR__) . '/lib/BundleContentsService.php';

$entries = [];
for ($index = 0; $index < 96; $index++) {
    $entries[] = [
        'site_name' => 'Neunkirchen', 'building_name' => 'Klassentrakt Johanneshaus',
        'floor_name' => 'Untergeschoss', 'room_number' => 'Musikraum 06',
        'device_number' => (string) (100015921 + $index),
        'device_name' => 'Bildschirm mit langem Hersteller- und Modellnamen',
        'external_number' => (100015921 + $index) . '-26',
        'test_date' => '2026-04-02', 'pages' => $index === 1 ? 2 : 1,
    ];
}
$layout = BundleContentsService::layout($entries);
if (count($layout) < 2 || array_sum(BundleContentsService::WIDTHS) !== 794) {
    throw new RuntimeException('Expected a full-width, multi-page contents table.');
}
foreach ($layout as $page) {
    if (array_sum(array_column($page, 'height')) > BundleContentsService::TOP - BundleContentsService::BOTTOM) {
        throw new RuntimeException('Rows overlap the page footer.');
    }
    foreach ($page as $row) {
        foreach ($row['cells'] as $column => $lines) {
            foreach ($lines as $line) {
                if (BundleContentsService::textWidth($line) > BundleContentsService::WIDTHS[$column] - 12 + 0.01) {
                    throw new RuntimeException('A line extends into the neighbouring column.');
                }
            }
        }
    }
}
$pdf = ReportController::renderBundleContents($entries);
if (!str_starts_with($pdf['body'], '%PDF-') || $pdf['pages'] !== count($layout)) {
    throw new RuntimeException('Invalid PDF or inconsistent contents page count.');
}
preg_match_all('/310 [0-9.]+ Td \(([0-9]+)\) Tj ET/', $pdf['body'], $matches);
$expected = [];
$page = count($layout) + 2;
foreach ($entries as $entry) {
    $expected[] = (string) $page;
    $page += $entry['pages'];
}
if ($matches[1] !== $expected || !str_contains($pdf['body'], '(02.04.2026)')) {
    throw new RuntimeException('Page references or German dates are incorrect.');
}
$long = $entries[0];
$long['device_name'] = str_repeat('WWWWWWWWWW', 200);
$longLayout = BundleContentsService::layout([$long]);
if (count($longLayout) < 2) {
    throw new RuntimeException('Extremely long device names must continue without overflowing.');
}
file_put_contents('/tmp/pruefapp-bundle-contents-preview.pdf', $pdf['body']);
echo "PASS: Full-width contents, wrapped columns, multi-page references and long device names\n";

$branding = ['company_name'=>'CENEOS GmbH','theme_colors'=>['primary'=>'#FFC800','primary_text'=>'#FFFFFF']];
$context = ['part'=>1,'parts'=>2,'created_at'=>'2026-11-16T18:00:00+01:00','invoice_number'=>'RE-2193'];
foreach ($entries as &$entry) $entry['customer_name']='Antoniuskolleg';
unset($entry);
$cover = ReportController::renderBundleCover($entries, $branding, $context);
$total = 1 + count($layout) + array_sum(array_column($entries,'pages'));
if (!str_contains($cover, '/Count 1>>') || !str_contains($cover, '(Antoniuskolleg)') || !str_contains($cover, '(Rechnungsbezug: RE-2193')) throw new RuntimeException('Cover metadata missing or cover has multiple pages.');
if (!str_contains($cover, '1.000 0.784 0.000 rg') || !str_contains($cover, '(Seite 1 von ' . $total . ')')) throw new RuntimeException('Cover CI or total-page footer incorrect.');
$contents = ReportController::renderBundleContents($entries,$branding,$context);
if ($contents['pages']!==count($layout) || !str_contains($contents['body'],'(Seite 2 von '.$total.')') || !str_contains($contents['body'],'(CENEOS GmbH)')) throw new RuntimeException('Contents branding changed pagination or omitted shared page footer.');
if (str_contains($contents['body'],'1 1 1 rg 30 515')) throw new RuntimeException('Yellow table header must not use unreadable white text.');
file_put_contents('/tmp/pruefapp-bundle-ci-cover.pdf',$cover);
file_put_contents('/tmp/pruefapp-bundle-ci-contents.pdf',$contents['body']);
echo "PASS: One-page CI cover, actual document metadata, contrast, shared header/footer and stable references\n";
