<?php

declare(strict_types=1);

use RedBeanPHP\R;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/lib/InspectionEvaluationService.php';
require_once dirname(__DIR__) . '/controllers/InspectionController.php';

$allowedRole = true;
$allowedCustomer = true;
function current_user_has_role(...$roles): bool { return $GLOBALS['allowedRole']; }
function current_user_can_access_customer($id): bool { return $GLOBALS['allowedCustomer']; }
function device_customer_id($device): int { return 1; }
function forbidden_response(): array { return [403, [], 'Forbidden']; }
function url_for($path): string { return '/' . $path; }
function audit_log($action, $context): void {}

R::setup('sqlite::memory:');
R::exec('CREATE TABLE inspection (id INTEGER PRIMARY KEY,device_id INTEGER,result_status TEXT,status TEXT)');
R::exec('CREATE TABLE device (id INTEGER PRIMARY KEY)');
R::exec('CREATE TABLE billinginvoiceitem (id INTEGER PRIMARY KEY,inspection_id INTEGER,active INTEGER)');
R::exec('INSERT INTO device (id) VALUES (1)');
foreach ([[1,'data_missing'],[2,'in_progress'],[3,'passed'],[4,'failed'],[5,'data_missing'],[6,'data_missing']] as [$id,$status]) {
    R::exec('INSERT INTO inspection (id,device_id,result_status,status) VALUES (?,1,?,?)', [$id,$status,$status]);
}
R::exec('INSERT INTO billinginvoiceitem (inspection_id,active) VALUES (5,1),(1,0)');
foreach ([1=>303,2=>303,3=>409,4=>409,5=>409] as $id=>$expected) {
    $response=InspectionController::delete(['id'=>$id],false);
    if ($response[0] !== $expected) throw new RuntimeException('Falsche Löschberechtigung für Prüfung '.$id);
    if ((R::load('inspection',$id)->id > 0) !== ($expected !== 303)) throw new RuntimeException('Unerwartete Bestandsänderung.');
}
$allowedCustomer=false;
if (InspectionController::delete(['id'=>6],false)[0] !== 404 || !R::load('inspection',6)->id) throw new RuntimeException('Kundenzugriff nicht geschützt.');
$allowedCustomer=true;
$allowedRole=false;
if (InspectionController::delete(['id'=>6],false)[0] !== 403 || !R::load('inspection',6)->id) throw new RuntimeException('Rollenprüfung fehlt.');
R::close();
echo "PASS: unfertige Prüfungen löschbar, abgeschlossene/abgerechnete/fremde Prüfungen geschützt\n";
