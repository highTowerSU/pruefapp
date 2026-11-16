<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/controllers/BillingController.php';

use RedBeanPHP\R;

function get_branding(): array
{
    return ['company_id' => 7];
}

R::setup('sqlite::memory:');
R::exec('CREATE TABLE billinginvoice (id INTEGER PRIMARY KEY, tenant_id INTEGER, customer_id INTEGER, invoice_number TEXT, sevdesk_invoice_number TEXT, sevdesk_url TEXT, invoice_date TEXT, status TEXT, created_at TEXT, historical_device_balance REAL DEFAULT 0, historical_balance_reason TEXT, historical_approved_at TEXT,amount_net REAL,amount_gross REAL,raw_json TEXT)');
R::exec('CREATE TABLE customer (id INTEGER PRIMARY KEY, name TEXT)');
R::exec('CREATE TABLE billinginvoiceitem (id INTEGER PRIMARY KEY, invoice_id INTEGER, inspection_id INTEGER, device_id INTEGER, active INTEGER, quantity REAL, regie_minutes INTEGER)');
R::exec('CREATE TABLE billinginvoiceposition (id INTEGER PRIMARY KEY, invoice_id INTEGER, position_number TEXT, kind TEXT, suggested_kind TEXT, quantity REAL, regie_minutes INTEGER)');
foreach ([[1, 7], [2, 7], [3, 7], [4, 8]] as [$id, $tenant]) {
    R::exec("INSERT INTO billinginvoice (id, tenant_id, status, created_at) VALUES (?, ?, 'draft', '2026-11-10')", [$id, $tenant]);
}
foreach ([[1, 10, 1], [1, 10, 1], [1, 20, 1], [1, 20, 1], [1, 20, 1], [1, 30, 1], [1, 30, 0], [2, 10, 1], [4, 10, 1], [4, 10, 1]] as [$invoice, $device, $active]) {
    R::exec('INSERT INTO billinginvoiceitem (invoice_id, device_id, active, quantity, regie_minutes) VALUES (?, ?, ?, 1, 5)', [$invoice, $device, $active]);
}
R::exec('UPDATE billinginvoiceitem SET inspection_id=id');
R::exec("UPDATE billinginvoice SET raw_json=? WHERE id=1",[json_encode(['sumGross'=>'1234.56'])]);
R::exec('UPDATE billinginvoice SET amount_gross=0 WHERE id=2');
R::exec("INSERT INTO billinginvoiceposition (invoice_id, kind, quantity, regie_minutes) VALUES (1, 'device', 6, 0), (1, 'regie', 0, 30)");
$rows = (new ReflectionMethod(BillingController::class, 'recentInvoices'))->invoke(null);
$byId = array_column($rows, null, 'id');
if ((float)$byId[1]['amount_gross']!==1234.56 || (float)$byId[2]['amount_gross']!==0.0 || $byId[3]['amount_gross']!==null) throw new RuntimeException('Invoice gross amount, zero or missing state lost.');
if (count($byId) !== 3 || isset($byId[4])) {
    throw new RuntimeException('Die Übersicht muss auf den aktuellen Mandanten begrenzt bleiben.');
}
foreach ([1 => [2, 5, 6, 30, false], 2 => [0, 0, 1, 5, false], 3 => [0, 0, 0, 0, false]] as $id => $expected) {
    $row = $byId[$id];
    $actual = [(int) $row['repeated_device_count'], (int) $row['repeated_device_inspection_count'], (int) $row['billed_device_actual'], (int) $row['billed_regie_actual'], $row['device_assignment_warning']];
    if ($actual !== $expected) {
        throw new RuntimeException('Falsche Zuordnungszahlen für Rechnung ' . $id . ': ' . json_encode($actual));
    }
}
if ($byId[2]['reconciliation_result'] !== 'Abgleich ausstehend' || $byId[3]['reconciliation_result'] !== 'nicht prüfbezogen') throw new RuntimeException('Linked invoice without snapshot misclassified as unrelated.');
if ($byId[1]['reconciliation_result'] !== 'passend') {
    throw new RuntimeException('Die Voraggregation darf den Positionsabgleich nicht vervielfachen.');
}
R::exec('UPDATE billinginvoice SET historical_device_balance=2 WHERE id=2');
$rows = (new ReflectionMethod(BillingController::class, 'recentInvoices'))->invoke(null);
$updated = array_column($rows, null, 'id');
if ((int) $updated[2]['billed_device_actual'] !== 3) throw new RuntimeException('Historische Restmenge fehlt in der Übersicht.');
R::exec('CREATE TABLE inspection (id INTEGER PRIMARY KEY, external_number TEXT)');
R::exec('CREATE TABLE billingregietransfer (source_invoice_id INTEGER, target_invoice_id INTEGER, minutes INTEGER, active INTEGER)');
R::exec("INSERT INTO inspection (id,external_number) SELECT id,'TEST-' || id FROM billinginvoiceitem");

R::exec('UPDATE billinginvoiceitem SET inspection_id=id');
R::exec("INSERT INTO billinginvoiceposition (invoice_id,kind,quantity,regie_minutes) VALUES (2,'device',3,0),(2,'regie',0,5)");
$check = (new ReflectionMethod(BillingController::class, 'reconciliation'))->invoke(null, 2);
if ($check['result'] !== 'historisch ausgeglichen' || (int) $check['assignedDeviceQuantity'] !== 1 || (int) $check['historicalDeviceBalance'] !== 2) {
    throw new RuntimeException('Historischer Ausgleich muss getrennt von belegten Prüfungen erkennbar bleiben.');
}
R::exec('INSERT INTO billinginvoiceitem(invoice_id,inspection_id,device_id,active,quantity,regie_minutes) VALUES(3,99,40,1,1,0),(3,99,40,1,1,0)');
$duplicates=array_column((new ReflectionMethod(BillingController::class,'recentInvoices'))->invoke(null),null,'id');
if(!$duplicates[3]['device_assignment_warning'])throw new RuntimeException('Real duplicate inspection allocation did not warn.');
R::close();
echo "PASS: recent invoices count active repeated devices without multiplying totals or crossing tenants\n";
