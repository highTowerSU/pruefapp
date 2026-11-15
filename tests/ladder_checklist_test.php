<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/LadderInspectionService.php';
require_once dirname(__DIR__) . '/lib/InspectionEvaluationService.php';
$catalog=LadderInspectionService::decorateCatalog(LadderInspectionService::definitions());
$inspection=['inspection_type_code'=>'ladder','examiner'=>'test@example.test','test_date'=>'2026-11-15','next_due_date'=>'2027-11-15'];
$values=[];$remarks=[];
foreach ($catalog as $item) {
    $values[$item['item_key']]=$item['allow_na'] ? 'na' : 'ok';
    if ($item['allow_na']) $remarks[$item['item_key']]='Bei dieser Bauart nicht vorhanden.';
}
$answers=LadderInspectionService::answers($catalog,$values,$remarks);
if (InspectionEvaluationService::evaluate($inspection,$answers,[],true)['status'] !== 'passed') throw new RuntimeException('Begründet nicht vorhandene Bauteile dürfen die Prüfung nicht blockieren.');
$bad=$values;$bad['rails']='na';
if (InspectionEvaluationService::evaluate($inspection,LadderInspectionService::answers($catalog,$bad,$remarks),[],true)['status'] !== 'data_missing') throw new RuntimeException('Grundlegende tragende Teile dürfen nicht übersprungen werden.');
$badRemarks=$remarks;unset($badRemarks['rope']);
if (InspectionEvaluationService::evaluate($inspection,LadderInspectionService::answers($catalog,$values,$badRemarks),[],true)['status'] !== 'data_missing') throw new RuntimeException('Nicht zutreffend erfordert eine Begründung.');
$bad=$values;$bad['feet']='orange';$remarks['feet']='Fußkappe beschädigt.';
$failed=LadderInspectionService::answers($catalog,$bad,$remarks);
if (InspectionEvaluationService::evaluate($inspection,$failed,[],true)['status'] !== 'failed') throw new RuntimeException('Sicherheitsmangel muss zum Nichtbestehen führen.');
$missingRemark=LadderInspectionService::answers($catalog,array_replace($values,['feet'=>'green']),$badRemarks);
if (LadderInspectionService::missingDescriptions($missingRemark) === []) throw new RuntimeException('Hinweise benötigen eine Beschreibung.');
$bare=LadderInspectionService::answers($catalog,[],[]);
if (InspectionEvaluationService::evaluate($inspection,$bare,[],true)['status'] !== 'data_missing') throw new RuntimeException('Ungeprüfte Punkte dürfen nicht vorausgefüllt bestehen.');
echo "PASS: Leiterkatalog trennt Grundprüfungen, begründete Bauart-Ausnahmen und Sicherheitsmängel\n";

require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/lib/DeviceFindingService.php';
RedBeanPHP\R::setup('sqlite::memory:');
RedBeanPHP\R::exec('CREATE TABLE device_finding (id INTEGER PRIMARY KEY,device_id INTEGER,inspection_id INTEGER,inspection_type_code TEXT,item_key TEXT,severity TEXT,state TEXT,action TEXT,due_date TEXT,blocked INTEGER,description TEXT,resolution_note TEXT,resolved_at TEXT,created_at TEXT,updated_at TEXT)');
DeviceFindingService::syncFromInspection(1,1,'ladder',[['item_key'=>'feet','severity'=>'orange','remark'=>'Fußkappe fehlt.']],'repair');
$defect=RedBeanPHP\R::getRow('SELECT blocked,due_date,action FROM device_finding WHERE inspection_id=1');
if ((int)$defect['blocked']!==1 || $defect['due_date']!==null || $defect['action']!=='repair') throw new RuntimeException('Leitermangel darf keine automatische Nutzungsfrist bekommen.');
RedBeanPHP\R::close();

$stepladder = LadderInspectionService::answers($catalog,$values,$remarks,['ladder_kind'=>'stehleiter']);
if (InspectionEvaluationService::evaluate($inspection,$stepladder,[],true)['status'] !== 'data_missing') throw new RuntimeException('Die Spreizsicherung einer Stehleiter darf nicht als nicht zutreffend durchgehen.');
