#!/usr/bin/env php
<?php

declare(strict_types=1);

/** One-time operator-approved reconciliation of AK's imported 2025 / early 2026 invoices. */
use RedBeanPHP\R;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/lib/lib.inc.php';
require_once dirname(__DIR__) . '/controllers/BillingController.php';
require_once dirname(__DIR__) . '/lib/HistoricalBillingAllocation.php';

$apply = in_array('--apply', $argv, true);
$backup = '';
foreach ($argv as $argument) if (str_starts_with($argument, '--backup=')) $backup = substr($argument, 9);
$marker = 'historical_reconciled_20261112';
$reason = 'Betreiberfreigabe vom 12.11.2026: historische Quellen abschließen, Quellergebnisse unverändert übernehmen, Rechnungsregie verteilen; nicht mehr rekonstruierbare Einzelbelege als historischen Mengenausgleich ausweisen.';
$numbers = ['RE-1913', 'RE-1914', 'RE-1915', 'RE-1916', 'RE-1917', 'RE-2193', 'RE-2194', 'RE-2195'];
$marks = implode(',', array_fill(0, count($numbers), '?'));
$invoices = R::getAll("SELECT * FROM billinginvoice WHERE invoice_number IN ({$marks}) ORDER BY id", $numbers);
if (count($invoices) !== 8) throw new RuntimeException('Die acht freigegebenen Rechnungen wurden nicht eindeutig gefunden.');
foreach ($invoices as $invoice) {
    if ((int) $invoice['tenant_id'] !== 2 || (int) $invoice['customer_id'] !== 3 || $invoice['status'] !== 'paid') {
        throw new RuntimeException('Rechnungskreis oder Rechnungsstatus weicht von der Freigabe ab.');
    }
}
$approved = array_filter($invoices, static fn(array $row): bool => trim((string) $row['historical_approved_at']) !== '');
if (count($approved) === 8) {
    echo json_encode(['state' => 'already_applied', 'invoices' => $numbers], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}
if ($approved !== [] || (int) R::getCell("SELECT COUNT(*) FROM billinginvoiceitem WHERE active=1 AND invoice_id IN ({$marks})", array_column($invoices, 'id')) > 0) {
    throw new RuntimeException('Bereits vorhandene Zuordnungen: keine automatische Überschreibung.');
}
$candidates = R::getAll("SELECT * FROM importcandidate WHERE run_id=11 AND state='number_missing' AND source_kind='csv_ods' AND source_path LIKE '%/AK_Elektro-%' AND ((test_date>='2025-01-01' AND test_date<'2026-01-01') OR test_date='2026-01-06' OR test_date='2026-03-28') ORDER BY id");
foreach ($candidates as $candidate) {
    $raw = json_decode((string) $candidate['raw_json'], true, 512, JSON_THROW_ON_ERROR);
    $status = InspectionEvaluationService::normalizeStatus((string) ($raw['result_status'] ?? ''));
    if (!in_array($status, ['passed', 'failed'], true)) throw new RuntimeException('Kein abschließendes Quellergebnis für Kandidat ' . $candidate['id']);
    if (R::getCell('SELECT COUNT(*) FROM importcandidate WHERE run_id=11 AND group_key=?', [$candidate['group_key']]) != 1) throw new RuntimeException('Mehrdeutige Quellgruppe.');
}
echo json_encode(['mode' => $apply ? 'apply' : 'preview', 'candidate_count' => count($candidates), 'candidate_years' => array_count_values(array_map(static fn(array $row): string => substr($row['test_date'], 0, 4), $candidates)), 'invoice_numbers' => $numbers], JSON_UNESCAPED_UNICODE) . PHP_EOL;
if (!$apply) exit;
if (count($candidates) !== 95) throw new RuntimeException('Der Kandidatenbestand weicht vom geprüften Stand mit 95 Quellzeilen ab.');
if ($backup === '' || !is_file($backup) || filesize($backup) < 1024 || !is_file($backup . '.sha256')) {
    throw new RuntimeException('Eine vollständige Datenbanksicherung mit SHA-256-Datei ist erforderlich.');
}
$expected = explode(' ', trim((string) file_get_contents($backup . '.sha256')))[0];
if (!hash_equals($expected, hash_file('sha256', $backup))) throw new RuntimeException('Die Sicherungsprüfsumme stimmt nicht.');
$lock = fopen(sys_get_temp_dir() . '/pruefapp-historical-reconciliation.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Der historische Abgleich läuft bereits.');
$now = date(DATE_ATOM);
$summary = [];
R::begin();
try {
    $importer = new ElectricalInspectionImportService();
    foreach ($candidates as $candidate) {
        $record = json_decode((string) $candidate['raw_json'], true, 512, JSON_THROW_ON_ERROR);
        $identity = 'HIST-C' . $candidate['id'];
        $record['inspection_number'] = $identity;
        $record['external_number'] = $identity;
        $record['device_number'] = $identity;
        $record['_audit_correlation_id'] = $marker;
        $importer->importCandidateRecord($record, (string) $candidate['source_path']);
        $number = $identity . '-' . substr((string) $candidate['test_date'], 2, 2);
        $inspection = R::findOne('inspection', ' external_number=? AND test_date=? ', [$number, $candidate['test_date']]);
        if (!$inspection) throw new RuntimeException('Importierter historischer Datensatz nicht auffindbar.');
        $status = InspectionEvaluationService::normalizeStatus((string) $record['result_status']);
        if ((string) $inspection->result_status !== $status) throw new RuntimeException('Quellergebnis wurde verändert.');
        $inspection->status = 'completed';
        $inspection->classification = 'migrated_import';
        $inspection->customer_hint = 'Antoniuskolleg';
        $inspection->metadata_notes = $reason . ' Historische Ersatzkennung: keine bestätigte dauerhafte Geräteidentität. Quelle: ' . basename((string) $candidate['source_path']) . ', Zeile ' . $candidate['source_row_no'] . '.';
        R::store($inspection);
        R::exec("UPDATE importcandidate SET state='resolved', decision_json=? WHERE id=? AND state='number_missing'", [json_encode(['action' => 'accept_source_result', 'authorization' => $reason, 'inspection_id' => (int) $inspection->id, 'historical_identity' => $identity, 'source_result' => $record['result_status'], 'at' => $now], JSON_UNESCAPED_UNICODE), $candidate['id']]);
    }
    $periodMethod = new ReflectionMethod(BillingController::class, 'importedPerformancePeriod');
    foreach (R::findAll('billinginvoice', ' tenant_id=2 AND customer_id=3 ') as $invoice) {
        $raw = json_decode((string) $invoice->raw_json, true) ?: [];
        $period = $periodMethod->invoke(null, $raw);
        if ($period['from'] !== '') $invoice->performance_date_from = $period['from'];
        if ($period['until'] !== '') $invoice->performance_date_until = $period['until'];
        $invoice->updated_at = $now;
        R::store($invoice);
    }
    $select = static function (string $from, string $until): array {
        return R::getAll("SELECT id,device_id,test_date,regie_minutes,external_number FROM inspection WHERE test_date>=? AND test_date<=? AND source_file LIKE 'AK_%' AND COALESCE(archived_at,'')='' AND result_status IN ('passed','failed') AND NOT EXISTS (SELECT 1 FROM billinginvoiceitem bi WHERE bi.inspection_id=inspection.id AND bi.active=1) ORDER BY test_date,id", [$from, $until]);
    };
    $summer = $select('2025-07-14', '2025-08-17');
    $counts = HistoricalBillingAllocation::distribute(count($summer), [80, 70, 65, 60, 58]);
    $plan = [];
    foreach (array_slice($numbers, 0, 5) as $index => $number) $plan[$number] = array_splice($summer, 0, $counts[$index]);
    $plan['RE-2193'] = $select('2026-01-05', '2026-01-31');
    $marchOne = $select('2026-03-27', '2026-03-29');
    $marchTwo = $select('2026-03-30', '2026-03-31');
    // Keep 94 rows for March II; assign any remaining boundary rows to March I.
    // Actual inspection dates remain unchanged and every cross-period link is labelled.
    $excess = max(0, count($marchTwo) - 94);
    $plan['RE-2194'] = array_merge($marchOne, array_splice($marchTwo, 0, $excess));
    $plan['RE-2195'] = $marchTwo;
    foreach ($invoices as $original) {
        $invoice = R::load('billinginvoice', (int) $original['id']);
        $positions = R::getAll('SELECT * FROM billinginvoiceposition WHERE invoice_id=? ORDER BY id', [$invoice->id]);
        $devices = array_values(array_filter($positions, static fn(array $p): bool => $p['kind'] === 'device'));
        $regie = array_values(array_filter($positions, static fn(array $p): bool => $p['kind'] === 'regie'));
        if (count($devices) !== 1 || count($regie) !== 1) throw new RuntimeException('Unerwartete Rechnungspositionen.');
        $rows = $plan[(string) $invoice->invoice_number];
        $target = (int) $devices[0]['quantity'];
        if ($rows === [] || count($rows) > $target) throw new RuntimeException('Ungültige Prüfungsmenge.');
        $minutes = HistoricalBillingAllocation::distribute((int) $regie[0]['regie_minutes'], array_map(static fn(array $row): int => max(0, (int) $row['regie_minutes']), $rows));
        foreach ($rows as $index => $row) {
            $outside = $row['test_date'] < $invoice->performance_date_from || $row['test_date'] > $invoice->performance_date_until;
            $item = R::dispense('billinginvoiceitem');
            $item->invoice_id = (int) $invoice->id;
            $item->inspection_id = (int) $row['id'];
            $item->device_id = (int) $row['device_id'];
            $item->quantity = 1;
            $item->regie_minutes = $minutes[$index];
            $item->regie_reason = 'Historische Rechnungsregie rechnerisch verteilt; ursprüngliche Prüfminuten unverändert. ' . $reason;
            $item->description = $outside ? 'Genehmigte historische Verteilung zwischen März I/II; Prüftag außerhalb des Rechnungszeitraums bleibt unverändert.' : 'Historische Zuordnung mit rechnerisch verteilter Rechnungsregie; Freigabe 12.11.2026.';
            $item->active = 1;
            $item->source = $marker;
            $item->assigned_at = $now;
            $item->created_at = $now;
            $itemId = (int) R::store($item);
            foreach (['device' => $devices[0], 'regie' => $regie[0]] as $kind => $position) {
                $link = R::dispense('billinginvoiceitemposition');
                $link->invoice_item_id = $itemId;
                $link->invoice_position_id = (int) $position['id'];
                $link->allocation_kind = $kind;
                $link->created_at = $now;
                R::store($link);
            }
            R::exec("UPDATE inspection SET billing_active_invoice_item_id=?,billing_status='abgerechnet',updated_at=? WHERE id=?", [$itemId, $now, $row['id']]);
        }
        $invoice->historical_device_balance = $target - count($rows);
        $invoice->historical_balance_reason = $reason;
        $invoice->historical_approved_at = $now;
        R::store($invoice);
        $check = (new ReflectionMethod(BillingController::class, 'reconciliation'))->invoke(null, (int) $invoice->id);
        if (!in_array($check['result'], ['vollständig passend', 'historisch ausgeglichen'], true)) throw new RuntimeException('Abgleich fehlgeschlagen: ' . $invoice->invoice_number . ' / ' . $check['result']);
        $summary[] = ['invoice' => (string) $invoice->invoice_number, 'inspections' => count($rows), 'historical_device_balance' => (int) $invoice->historical_device_balance, 'regie_minutes' => array_sum($minutes), 'result' => $check['result']];
    }
    audit_log('historische_rechnungen_abgeglichen', ['authorization' => $reason, 'source_candidates' => count($candidates), 'backup' => basename($backup), 'invoices' => $summary]);
    R::commit();
} catch (Throwable $error) {
    R::rollback();
    throw $error;
}
echo json_encode(['state' => 'success', 'imported_candidates' => count($candidates), 'invoices' => $summary], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
