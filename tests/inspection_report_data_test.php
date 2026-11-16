<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/InspectionReportWriter.php';
$method = new ReflectionMethod(InspectionReportWriter::class, 'reportItems');
$values = ['Ergebnis' => 'bestanden', 'Prüfart' => 'Schutzklasse I'];
$rows = [['Prüfung','Wert'], ['Mandant','CENEOS GmbH'], ['Ergebnisbegründung','Historisches Ergebnis'], ['Schutzklasse','I']];
$readings = [
    ['key'=>'RPE','name'=>'Schutzleiterwiderstand RPE','value'=>'0.12','unit'=>'Ω','result'=>'bestanden','limit'=>'0.3 Ω'],
    ['name'=>'IB','value'=>'0.1','unit'=>'mA','result'=>'bestanden'],
    ['name'=>'Mandant','value'=>'CENEOS GmbH','result'=>'bestanden'],
];
$checklist = [['step'=>'Sichtprüfung der Leitung','result'=>'Ja','check'=>true,'category'=>'Sichtprüfung']];
$items = $method->invoke(null, $rows, $values, [], $readings, $checklist);
$measurements = array_values(array_filter($items, static fn($item) => $item['category'] === 'Messung'));
if (count($measurements)!==2 || !str_contains($measurements[0]['result'],'0.12 Ω') || !str_contains($measurements[1]['result'],'0.1 mA')) throw new RuntimeException('Checklist hides real measurements or aliases/units are lost.');
$items = $method->invoke(null, $rows, $values, [], [], []);
$measurements = array_values(array_filter($items, static fn($item) => $item['category'] === 'Messung'));
if (count($measurements)!==1 || $measurements[0]['question']!=='Keine Messwerte hinterlegt' || $measurements[0]['ok']) throw new RuntimeException('Metadata presented as successful measurements.');
$items = $method->invoke(null, [['Prüfung','Wert'],['RISO','> 200 MΩ']], $values, [], [], []);
$measurements = array_values(array_filter($items, static fn($item) => $item['category'] === 'Messung'));
if (count($measurements)!==1 || $measurements[0]['ok'] || $measurements[0]['result']!=='> 200 MΩ') throw new RuntimeException('Legacy measurement value lost or inherited an invented success mark.');
$items = $method->invoke(null, $rows, $values, ['step0'=>'Messung RPE','result0'=>'bestanden'], $readings, []);
$measurements = array_values(array_filter($items, static fn($item) => $item['category'] === 'Messung'));
if (!str_contains($measurements[0]['result'],'0.12 Ω')) throw new RuntimeException('Phoenix question hides actual reading.');
$logoMethod = new ReflectionMethod(InspectionReportWriter::class, 'logoAsset');
$logo = $logoMethod->invoke(null, ['logos'=>['long'=>dirname(__DIR__).'/public/img/ceneos-logo.svg']]);
$ratio = (float)$logo['display_width'] / (float)$logo['display_height'];
if (abs($ratio - 160/48)>0.001) throw new RuntimeException('Logo aspect ratio changed.');
echo "PASS: Actual readings with checklists, metadata exclusion, missing-data disclosure and logo proportions\n";

$cache = tempnam(sys_get_temp_dir(), 'report-version-');
file_put_contents($cache, '%PDF-test');
if (InspectionReportWriter::isCurrentFile($cache)) throw new RuntimeException('Stale PDF accepted.');
file_put_contents($cache, '%PDF-test' . "\n%Pruefapp-Report: " . InspectionReportWriter::CACHE_VERSION . "\n");
if (!InspectionReportWriter::isCurrentFile($cache)) throw new RuntimeException('Current PDF rejected.');
unlink($cache);

$items=$method->invoke(null,$rows,$values,[],[['name'=>'RISO','value'=>'>19.99','unit'=>'MΩ','result'=>'bestanden','voltage'=>'500V']],$checklist);
$readings=array_values(array_filter($items,static fn($item)=>$item['category']==='Messung'));
if(!str_contains($readings[0]['criterion'],'500V'))throw new RuntimeException('Recovered test voltage is absent from report.');
