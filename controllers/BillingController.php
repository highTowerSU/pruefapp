<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Tenant\TenantRepository;

final class BillingController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $rows = self::rows([], $_GET);
        $customers = R::findAll('customer', ' ORDER BY name ');
        $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
        $configured = $tenant && trim((string) ($tenant->sevdesk_api_token ?? '')) !== '';
        $content = render_template('billing_index.php', ['rows' => $rows, 'tenant' => $tenant, 'customers' => $customers, 'configured' => $configured, 'message' => $_SESSION['billing_message'] ?? null, 'filters' => $_GET]);
        unset($_SESSION['billing_message']);
        return $isHx ? [200, ['Content-Type' => 'text/html; charset=utf-8'], $content] : [200, [], render_template('layout.php', ['title' => 'Abrechnung', 'content' => $content])];
    }

    public static function export(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['inspection_ids'] ?? [])), static fn(int $id): bool => $id > 0));
        $rows = self::rows($ids);
        if ($rows === []) { $_SESSION['billing_message'] = 'Bitte mindestens eine nicht exportierte, abrechenbare Prüfung auswählen.'; return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], '']; }
        $action = (string) ($_POST['action'] ?? 'csv');
        if ($action === 'csv') return self::csv($rows);
        $exportedByUser = current_user();
        $exportedBy = trim((string) (($exportedByUser->name ?? '') ?: ($exportedByUser->email ?? '')));
        if ($action === 'mark') {
            $invoiceId = self::recordInvoice($rows, 'manual', 'marked');
            foreach ($rows as $row) R::exec('UPDATE inspection SET billing_exported_at = ?, billing_export_id = ?, billing_exported_by = ? WHERE id = ?', [date(DATE_ATOM), 'manual', $exportedBy, $row['id']]);
            audit_log('abrechnung_exportiert', ['inspection_ids' => array_column($rows, 'id'), 'exported_by' => $exportedBy, 'export_id' => 'manual']);
            $_SESSION['billing_message'] = count($rows) . ' Prüfung(en) als exportiert markiert. Rechnung #' . $invoiceId . ' wurde angelegt.';
        } elseif ($action === 'sevdesk') {
            $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
            $client = new SevDeskClient((string) ($tenant->sevdesk_api_url ?? 'https://my.sevdesk.de/api/v1'), (string) ($tenant->sevdesk_api_token ?? ''));
            $exportKey = hash('sha256', implode(',', array_map('intval', array_column($rows, 'id'))) . '|' . (int) (current_user()->id ?? 0) . '|' . (string) floor(time() / 10));
            $existingExport = R::findOne('billing_export', ' idempotency_key = ? ', [$exportKey]);
            if ($existingExport && in_array((string) $existingExport->status, ['pending', 'running', 'succeeded'], true)) {
                $_SESSION['billing_message'] = $existingExport->status === 'succeeded' ? 'Dieser Export wurde bereits erfolgreich verarbeitet.' : 'Dieser Export wird bereits verarbeitet.';
                return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
            }
            $export = $existingExport ?: R::dispense('billing_export');
            $export->idempotency_key = $exportKey; $export->owner_user_id = (int) (current_user()->id ?? 0); $export->status = 'running'; $export->inspection_ids_json = json_encode(array_map('intval', array_column($rows, 'id'))); $export->updated_at = date(DATE_ATOM); if (!$export->created_at) $export->created_at = date(DATE_ATOM); R::store($export);
            $byCustomer = [];
            foreach ($rows as $row) $byCustomer[(string) $row['sevdesk_customer_id']][] = $row;
            try {
                foreach ($byCustomer as $customerId => $items) {
                    if ($customerId === '') throw new RuntimeException('Kundenverknüpfung zu SevDesk fehlt: ' . $items[0]['customer_name']);
                    $response = $client->createDraftInvoice($customerId, 'PR-' . date('Ymd-His'), date('Y-m-d'), $items, (float) ($tenant->sevdesk_inspection_rate ?? 0), (float) ($tenant->sevdesk_regie_rate ?? 0));
                    $exportId = (string) ($response['objects']['id'] ?? $response['id'] ?? '');
                    if ($exportId === '') throw new RuntimeException('SevDesk lieferte keine Rechnungs-ID zurück.');
                    $invoiceId = self::recordInvoice($items, $exportId, 'sevdesk', $response);
                    $export->invoice_id = $invoiceId;
                    $export->sevdesk_invoice_id = $exportId;
                    $export->sevdesk_invoice_number = (string) ($response['objects']['invoiceNumber'] ?? $response['invoiceNumber'] ?? '');
                    foreach ($items as $row) R::exec('UPDATE inspection SET billing_exported_at = ?, billing_export_id = ?, billing_exported_by = ? WHERE id = ?', [date(DATE_ATOM), $exportId, $exportedBy, $row['id']]);
                    audit_log('abrechnung_exportiert', ['inspection_ids' => array_column($items, 'id'), 'exported_by' => $exportedBy, 'export_id' => $exportId, 'invoice_id' => $invoiceId]);
                }
                $export->status = 'succeeded'; $export->updated_at = date(DATE_ATOM); R::store($export);
                $_SESSION['billing_message'] = count($rows) . ' Prüfung(en) an SevDesk übertragen.';
            } catch (Throwable $exception) {
                $export->status = 'failed'; $export->error_details = mb_substr($exception->getMessage(), 0, 2000); $export->updated_at = date(DATE_ATOM); R::store($export);
                foreach ($rows as $row) R::exec("UPDATE inspection SET billing_status = 'export_failed', billing_last_error = ? WHERE id = ? AND billing_status <> 'exported'", [$exception->getMessage(), $row['id']]);
                audit_log('abrechnung_export_fehlgeschlagen', ['inspection_ids' => array_column($rows, 'id'), 'error' => $exception->getMessage(), 'export_id' => (int) $export->id]);
                $_SESSION['billing_message'] = 'SevDesk-Export fehlgeschlagen: ' . $exception->getMessage();
            }
        }
        return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
    }

    public static function eligibility(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden.'];
        $eligibility = trim((string) ($_POST['eligibility'] ?? ''));
        if (!in_array($eligibility, ['billable', 'not_billable'], true)) return [422, [], 'Ungültige Abrechenbarkeit.'];
        $hasActiveInvoice = (int) R::getCell('SELECT COUNT(*) FROM billing_invoice_item WHERE inspection_id = ? AND active = 1', [(int) $inspection->id]) > 0;
        if ($hasActiveInvoice && ($_POST['confirm_exported'] ?? '') !== '1') return [409, [], 'Diese Prüfung ist bereits einer Rechnung zugeordnet. Bitte die Änderung ausdrücklich bestätigen.'];
        if ($eligibility === 'not_billable' && trim((string) ($_POST['reason'] ?? '')) === '') return [422, [], 'Bitte einen Grund für die Nichtabrechenbarkeit angeben.'];
        $old = (string) ($inspection->billing_eligibility ?? ($inspection->billable ? 'billable' : 'not_billable'));
        $inspection->billing_eligibility = $eligibility;
        $inspection->billable = $eligibility === 'billable' ? 1 : 0;
        $inspection->billing_not_billable_reason = trim((string) ($_POST['reason'] ?? ''));
        $inspection->billing_not_billable_comment = trim((string) ($_POST['comment'] ?? ''));
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
        audit_log('abrechenbarkeit_geaendert', ['inspection_id' => (int) $inspection->id, 'previous' => $old, 'new' => $eligibility, 'reason' => $inspection->billing_not_billable_reason, 'comment' => $inspection->billing_not_billable_comment, 'confirmed_existing_invoice' => $hasActiveInvoice]);
        return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
    }

    public static function resetExport(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $inspectionId = (int) ($params['id'] ?? 0);
        $inspection = R::load('inspection', $inspectionId);
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden.'];
        if (($_POST['confirm'] ?? '') !== '1') return [409, [], 'Bitte die Warnung ausdrücklich bestätigen.'];
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') return [422, [], 'Bitte einen Grund für das Zurücksetzen angeben.'];
        R::begin();
        try {
            $items = R::findAll('billing_invoice_item', ' inspection_id = ? AND active = 1 ', [$inspectionId]);
            foreach ($items as $item) { $item->active = 0; $item->deactivated_at = date(DATE_ATOM); $item->deactivation_reason = $reason; R::store($item); }
            $inspection->billing_status = 'manually_unexported'; $inspection->billing_active_invoice_item_id = null; $inspection->billing_last_error = ''; $inspection->updated_at = date(DATE_ATOM); R::store($inspection);
            R::commit();
        } catch (Throwable $exception) { R::rollback(); throw $exception; }
        audit_log('abrechnung_export_zurueckgesetzt', ['inspection_id' => $inspectionId, 'reason' => $reason]);
        $_SESSION['billing_message'] = 'Exportstatus zurückgesetzt. Die Prüfung kann erneut abgerechnet werden.';
        return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
    }

    public static function invoice(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $id = (int) ($params['id'] ?? 0);
        $invoice = R::load('billing_invoice', $id);
        if (!$invoice->id) return [404, [], 'Rechnung nicht gefunden.'];
        $items = R::getAll('SELECT bi.*, i.external_number AS inspection_number, i.test_date, i.billing_status, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name FROM billing_invoice_item bi JOIN inspection i ON i.id=bi.inspection_id JOIN device d ON d.id=bi.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE bi.invoice_id = ? ORDER BY d.external_number, i.test_date, i.id', [$id]);
        $content = render_template('billing_invoice.php', ['invoice' => $invoice, 'items' => $items]);
        return [200, [], render_template('layout.php', ['title' => 'Rechnung #' . $id, 'content' => $content])];
    }

    private static function recordInvoice(array $rows, string $externalId, string $status, array $response = []): int
    {
        $first = $rows[0] ?? [];
        R::begin();
        try {
        $invoice = R::dispense('billing_invoice');
        $invoice->customer_id = (int) ($first['customer_id'] ?? 0) ?: null;
        $invoice->tenant_id = (int) (get_branding()['company_id'] ?? 0) ?: null;
        $invoice->sevdesk_invoice_id = $externalId;
        $invoice->sevdesk_invoice_number = (string) ($response['objects']['invoiceNumber'] ?? $response['invoiceNumber'] ?? '');
        $invoice->sevdesk_url = (string) ($response['objects']['url'] ?? $response['url'] ?? '');
        $invoice->invoice_number = 'PR-' . date('Ymd-His');
        $invoice->invoice_date = date('Y-m-d');
        $invoice->status = $status;
        $invoice->exported_by = trim((string) (($user = current_user())->name ?? ($user->email ?? '')));
        $invoice->exported_at = date(DATE_ATOM);
        $invoice->created_by = (int) ($user->id ?? 0);
        $invoice->created_at = date(DATE_ATOM);
        $invoice->updated_at = date(DATE_ATOM);
        $invoiceId = (int) R::store($invoice);
        foreach ($rows as $row) {
            $item = R::dispense('billing_invoice_item');
            $item->invoice_id = $invoiceId;
            $item->inspection_id = (int) ($row['id'] ?? 0);
            $item->device_id = (int) ($row['device_id'] ?? 0);
            $item->quantity = 1;
            $item->description = (string) ($row['device_number'] ?? '') . ' · ' . (string) ($row['device_name'] ?? 'Prüfung');
            $item->active = 1; $item->assigned_at = date(DATE_ATOM); $item->source = $status === 'sevdesk' ? 'sevdesk' : 'manual'; $item->created_at = date(DATE_ATOM);
            R::store($item);
            R::exec("UPDATE inspection SET billing_status = 'exported', billing_active_invoice_item_id = ?, billing_last_error = '' WHERE id = ?", [(int) $item->id, (int) ($row['id'] ?? 0)]);
        }
        R::commit();
        return $invoiceId;
        } catch (Throwable $exception) {
            R::rollback();
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    private static function rows(array $ids = [], array $filters = []): array
    {
        $eligibilityFilter = trim((string) ($filters['eligibility'] ?? 'billable'));
        $statusFilter = trim((string) ($filters['billing_status'] ?? ''));
        $eligibilityFilter = in_array($eligibilityFilter, ['billable', 'not_billable'], true) ? $eligibilityFilter : 'billable';
        // Abrechnungsstart ist 2025; Altbestand bis einschließlich 2024 bleibt
        // sichtbar in Prüfungen, wird aber niemals für einen Export angeboten.
        $where = "COALESCE(i.billing_eligibility, CASE WHEN i.billable = 1 THEN 'billable' ELSE 'not_billable' END) = ? AND i.result_status IN ('bestanden','durchgefallen') AND i.test_date >= '2025-01-01'";
        $args = [$eligibilityFilter];
        if ($eligibilityFilter === 'billable') $where .= " AND TRIM(COALESCE(i.report_path, '')) <> ''";
        if ($statusFilter !== '') { $where .= ' AND COALESCE(i.billing_status, CASE WHEN i.billing_exported_at IS NULL OR i.billing_exported_at = \'\' THEN \'not_exported\' ELSE \'exported\' END) = ?'; $args[] = $statusFilter; }
        else if ($eligibilityFilter === 'billable') $where .= " AND COALESCE(i.billing_status, CASE WHEN i.billing_exported_at IS NULL OR i.billing_exported_at = '' THEN 'not_exported' ELSE 'exported' END) IN ('not_exported','manually_unexported','export_failed')";
        if ($ids !== []) { $where .= ' AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; array_push($args, ...$ids); }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') { $where .= ' AND (i.external_number LIKE ? OR d.external_number LIKE ? OR d.name LIKE ? OR c.name LIKE ?)'; array_push($args, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'); }
        foreach (['customer_id' => 'c.id', 'from' => 'i.test_date >= ?', 'to' => 'i.test_date <= ?'] as $key => $condition) { if (($value = trim((string) ($filters[$key] ?? ''))) !== '') { $where .= ' AND ' . $condition; $args[] = $key === 'customer_id' ? (int) $value : $value; } }
        return R::getAll("SELECT i.id, i.device_id, i.external_number, i.test_date, i.next_due_date, i.result_status, i.regie_minutes, i.regie_reason, i.billing_eligibility, i.billing_status, i.billing_not_billable_reason, i.billing_last_error, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, c.sevdesk_customer_id, c.sevdesk_customer_number, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number, bi.invoice_id, inv.invoice_number, inv.sevdesk_invoice_id FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN billing_invoice_item bi ON bi.inspection_id=i.id AND bi.active=1 LEFT JOIN billing_invoice inv ON inv.id=bi.invoice_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE {$where} ORDER BY c.name, i.test_date, i.id", $args);
    }

    private static function csv(array $rows): array
    {
        $filename = 'abrechnung-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Prüfung', 'Datum', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung', 'SevDesk-Kundennummer'], ';');
        foreach ($rows as $row) fputcsv($out, [$row['external_number'], $row['test_date'], $row['customer_name'], $row['device_number'] . ' · ' . $row['device_name'], trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: '—', $row['result_status'], $row['regie_minutes'], $row['regie_reason'], $row['sevdesk_customer_number'] ?: ($row['sevdesk_customer_id'] ?: 'FEHLT')], ';');
        fclose($out); return [200, [], ''];
    }
}
