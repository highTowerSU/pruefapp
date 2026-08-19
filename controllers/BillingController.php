<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Tenant\TenantRepository;

final class BillingController
{
    /** Read-only support diagnostic for the exact export selection query. */
    public static function debugSelection(array $filters = [], array $ids = []): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        try {
            $rows = self::rows($ids, $filters);
            return [
                'ok' => true,
                'count' => count($rows),
                'rows' => array_slice(array_map(static fn(array $row): array => [
                    'id' => (int) $row['id'], 'inspection' => (string) $row['external_number'],
                    'date' => (string) $row['test_date'], 'customer' => (string) $row['customer_name'],
                    'billing_eligibility' => (string) $row['billing_eligibility'], 'billing_status' => (string) $row['billing_status'],
                    'invoice_number' => (string) $row['invoice_number'],
                ], $rows), 0, 50),
            ];
        } catch (Throwable $error) {
            return ['ok' => false, 'exception_class' => get_class($error), 'error' => $error->getMessage(), 'trace' => $error->getTraceAsString()];
        }
    }

    /** @return array<string,mixed> Read-only template probe for protected support diagnostics. */
    public static function debugRender(array $filters = []): array
    {
        try {
            $allRows = self::rows([], $filters);
            $requestedPerPage = (int) ($filters['per_page'] ?? 50);
            $perPage = in_array($requestedPerPage, [25, 50, 100], true) ? $requestedPerPage : 50;
            $page = max(1, min((int) ($filters['page'] ?? 1), max(1, (int) ceil(count($allRows) / $perPage))));
            $rooms = R::findAll('room', ' ORDER BY name ');
            $roomLabels = [];
            foreach ($rooms as $room) {
                $floor = R::load('floor', (int) $room->floor_id);
                $building = R::load('building', (int) $floor->building_id);
                $site = R::load('site', (int) $building->site_id);
                $customer = R::load('customer', (int) $site->customer_id);
                $area = (int) ($room->area_id ?? 0) > 0 ? R::load('area', (int) $room->area_id) : null;
                $roomLabels[(int) $room->id] = implode(' · ', array_filter([trim((string) ($customer->name ?? '')), trim((string) ($site->name ?? '')), StructureController::roomIdentifier($room, $floor, $area)]));
            }
            $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
            $content = render_template('billing_index.php', [
                'rows' => array_slice($allRows, ($page - 1) * $perPage, $perPage), 'tenant' => $tenant,
                'customers' => R::findAll('customer', ' ORDER BY name '), 'sites' => R::findAll('site', ' ORDER BY name '),
                'buildings' => R::findAll('building', ' ORDER BY name '), 'floors' => R::findAll('floor', ' ORDER BY sort_order, name '),
                'rooms' => $rooms, 'roomLabels' => $roomLabels, 'examinerOptions' => InspectionFilterService::examinerOptions(),
                'configured' => $tenant && trim((string) ($tenant->sevdesk_api_token ?? '')) !== '', 'preselectedIds' => [],
                'page' => $page, 'pages' => max(1, (int) ceil(count($allRows) / $perPage)), 'perPage' => $perPage, 'message' => null, 'filters' => $filters,
            ]);
            $pageHtml = render_template('layout.php', ['title' => 'Abrechnung', 'content' => $content]);
            return ['ok' => true, 'row_count' => count($allRows), 'content_length' => strlen($content), 'page_length' => strlen($pageHtml), 'has_root' => str_contains($content, 'id="billing-content"')];
        } catch (Throwable $error) {
            return ['ok' => false, 'exception_class' => get_class($error), 'error' => $error->getMessage(), 'file' => $error->getFile(), 'line' => $error->getLine(), 'trace' => $error->getTraceAsString()];
        }
    }

    /** Read-only support view for failed provider calls; available only behind the debug-secret endpoint. */
    public static function debugExportFailures(): array
    {
        try {
            $exports = R::getAll(
                "SELECT id, status, sevdesk_invoice_id, error_details, created_at, updated_at FROM billingexport WHERE status = 'failed' ORDER BY id DESC LIMIT 10"
            );
            return ['ok' => true, 'exports' => array_map(static fn(array $export): array => [
                'id' => (int) $export['id'],
                'status' => (string) $export['status'],
                'sevdesk_invoice_id' => (string) $export['sevdesk_invoice_id'],
                'error' => (string) $export['error_details'],
                'created_at' => (string) $export['created_at'],
                'updated_at' => (string) $export['updated_at'],
            ], $exports)];
        } catch (Throwable $error) {
            return ['ok' => false, 'exception_class' => get_class($error), 'error' => $error->getMessage()];
        }
    }

    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_billing()) return forbidden_response();
        $preselectedIds = ($_GET['preselect'] ?? '') === '1' ? array_values(array_filter(array_map('intval', (array) ($_SESSION['billing_preselect_inspection_ids'] ?? [])), static fn(int $id): bool => $id > 0)) : [];
        if ($preselectedIds !== []) unset($_SESSION['billing_preselect_inspection_ids']);
        $allRows = self::rows($preselectedIds, $_GET);
        $requestedPerPage = (int) ($_GET['per_page'] ?? 50);
        $perPage = in_array($requestedPerPage, [25, 50, 100], true) ? $requestedPerPage : 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil(count($allRows) / $perPage));
        $page = min($page, $pages);
        $rows = array_slice($allRows, ($page - 1) * $perPage, $perPage);
        $customers = R::findAll('customer', ' ORDER BY name ');
        $sites = R::findAll('site', ' ORDER BY name ');
        $buildings = R::findAll('building', ' ORDER BY name ');
        $floors = R::findAll('floor', ' ORDER BY sort_order, name ');
        $rooms = R::findAll('room', ' ORDER BY name ');
        $roomLabels = [];
        foreach ($rooms as $room) {
            $floor = R::load('floor', (int) $room->floor_id);
            $building = R::load('building', (int) $floor->building_id);
            $site = R::load('site', (int) $building->site_id);
            $customer = R::load('customer', (int) $site->customer_id);
            $area = (int) ($room->area_id ?? 0) > 0 ? R::load('area', (int) $room->area_id) : null;
            $identifier = StructureController::roomIdentifier($room, $floor, $area);
            $roomLabels[(int) $room->id] = implode(' · ', array_filter([
                trim((string) ($customer->name ?? '')),
                trim((string) ($site->name ?? '')),
                $identifier,
            ]));
        }
        $examinerOptions = InspectionFilterService::examinerOptions();
        $tenant = (new TenantRepository())->find((int) (get_branding()['company_id'] ?? 0));
        $configured = $tenant && trim((string) ($tenant->sevdesk_api_token ?? '')) !== '';
        $content = render_template('billing_index.php', ['rows' => $rows, 'tenant' => $tenant, 'customers' => $customers, 'sites' => $sites, 'buildings' => $buildings, 'floors' => $floors, 'rooms' => $rooms, 'roomLabels' => $roomLabels, 'examinerOptions' => $examinerOptions, 'configured' => $configured, 'preselectedIds' => $preselectedIds, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage, 'message' => $_SESSION['billing_message'] ?? null, 'filters' => $_GET]);
        unset($_SESSION['billing_message']);
        return $isHx ? [200, ['Content-Type' => 'text/html; charset=utf-8'], $content] : [200, [], render_template('layout.php', ['title' => 'Abrechnung', 'content' => $content])];
    }

    public static function export(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_billing()) return forbidden_response();
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['inspection_ids'] ?? [])), static fn(int $id): bool => $id > 0));
        $selectionScope = (string) ($_POST['selection_scope'] ?? 'selection');
        $filters = $selectionScope === 'all' ? self::filtersFromSelectionQuery((string) ($_POST['filter_query'] ?? '')) : [];
        $allFilteredRows = $selectionScope === 'all' ? self::rows([], $filters) : [];
        if ($selectionScope === 'all') $ids = array_map(static fn(array $row): int => (int) $row['id'], $allFilteredRows);
        $action = (string) ($_POST['action'] ?? 'csv');
        if (in_array($action, ['set_billable', 'set_not_billable'], true)) {
            if ($ids === []) { $_SESSION['billing_message'] = 'Bitte mindestens eine Prüfung auswählen.'; return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], '']; }
            $eligibility = $action === 'set_billable' ? 'billable' : 'not_billable';
            $reason = trim((string) ($_POST['reason'] ?? '')) ?: ($eligibility === 'not_billable' ? 'Manuell als nicht abrechenbar markiert' : '');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            R::exec("UPDATE inspection SET billable = ?, billing_eligibility = ?, billing_not_billable_reason = ?, billing_not_billable_comment = ?, updated_at = ? WHERE id IN ($marks)", array_merge([$eligibility === 'billable' ? 1 : 0, $eligibility, $reason, $reason, date(DATE_ATOM)], $ids));
            audit_log('abrechenbarkeit_massenaktion', ['inspection_ids' => $ids, 'new' => $eligibility, 'reason' => $reason]);
            $_SESSION['billing_message'] = count($ids) . ' Prüfung(en) aktualisiert.';
            return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
        }
        $rows = $selectionScope === 'all' ? $allFilteredRows : self::rows($ids, $filters);
        if ($rows === []) { $_SESSION['billing_message'] = 'Bitte mindestens eine nicht exportierte, abrechenbare Prüfung auswählen.'; return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], '']; }
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
            $missingCustomerRows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['customer_id'] ?? 0) <= 0 || trim((string) ($row['sevdesk_customer_id'] ?? '')) === ''));
            if ($missingCustomerRows !== []) {
                $_SESSION['billing_message'] = self::missingSevDeskCustomerMessage($missingCustomerRows);
                audit_log('abrechnung_export_blockiert', ['inspection_ids' => array_column($missingCustomerRows, 'id'), 'reason' => 'missing_sevdesk_customer']);
                return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
            }
            $client = new SevDeskClient((string) ($tenant->sevdesk_api_url ?? 'https://my.sevdesk.de/api/v1'), (string) ($tenant->sevdesk_api_token ?? ''));
            $exportKey = hash('sha256', implode(',', array_map('intval', array_column($rows, 'id'))) . '|' . (int) (current_user()->id ?? 0) . '|' . (string) floor(time() / 10));
            $existingExport = R::findOne('billingexport', ' idempotency_key = ? ', [$exportKey]);
            if ($existingExport && in_array((string) $existingExport->status, ['pending', 'running', 'succeeded'], true)) {
                $_SESSION['billing_message'] = $existingExport->status === 'succeeded' ? 'Dieser Export wurde bereits erfolgreich verarbeitet.' : 'Dieser Export wird bereits verarbeitet.';
                return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
            }
            $export = $existingExport ?: R::dispense('billingexport');
            $export->idempotency_key = $exportKey; $export->owner_user_id = (int) (current_user()->id ?? 0); $export->status = 'running'; $export->inspection_ids_json = json_encode(array_map('intval', array_column($rows, 'id'))); $export->updated_at = date(DATE_ATOM); if (!$export->created_at) $export->created_at = date(DATE_ATOM); R::store($export);
            $byCustomer = [];
            foreach ($rows as $row) $byCustomer[(string) $row['sevdesk_customer_id']][] = $row;
            try {
                foreach ($byCustomer as $customerId => $items) {
                    if ($customerId === '') throw new RuntimeException('Kundenverknüpfung zu SevDesk fehlt: ' . $items[0]['customer_name']);
                    // PHP converts numeric array keys to integers. SevDesk's
                    // API client deliberately accepts its customer ID only as
                    // a string, so retain that boundary explicitly.
                    $response = $client->createDraftInvoice((string) $customerId, 'PR-' . date('Ymd-His'), date('Y-m-d'), $items, (float) ($tenant->sevdesk_inspection_rate ?? 0), (float) ($tenant->sevdesk_regie_rate ?? 0), (int) ($tenant->sevdesk_tax_rule ?? 1), (float) ($tenant->sevdesk_tax_rate ?? 19), (string) ($tenant->sevdesk_contact_person_id ?? ''));
                    $sevDeskInvoice = self::sevDeskInvoiceResponse($response);
                    $exportId = $sevDeskInvoice['id'];
                    if ($exportId === '') {
                        $fields = implode(', ', array_slice(array_map('strval', array_keys($response)), 0, 8));
                        throw new RuntimeException('SevDesk lieferte keine Rechnungs-ID zurück' . ($fields !== '' ? ' (Antwortfelder: ' . $fields . ')' : '') . '.');
                    }
                    $invoiceId = self::recordInvoice($items, $exportId, 'sevdesk', $response);
                    $export->invoice_id = $invoiceId;
                    $export->sevdesk_invoice_id = $exportId;
                    $export->sevdesk_invoice_number = $sevDeskInvoice['number'];
                    foreach ($items as $row) R::exec('UPDATE inspection SET billing_exported_at = ?, billing_export_id = ?, billing_exported_by = ? WHERE id = ?', [date(DATE_ATOM), $exportId, $exportedBy, $row['id']]);
                    audit_log('abrechnung_exportiert', ['inspection_ids' => array_column($items, 'id'), 'exported_by' => $exportedBy, 'export_id' => $exportId, 'invoice_id' => $invoiceId]);
                }
                $export->status = 'succeeded'; $export->updated_at = date(DATE_ATOM); R::store($export);
                $_SESSION['billing_message'] = count($rows) . ' Prüfung(en) an SevDesk übertragen.';
            } catch (Throwable $exception) {
                $export->status = 'failed'; $export->error_details = mb_substr($exception->getMessage(), 0, 2000); $export->updated_at = date(DATE_ATOM); R::store($export);
                $displayError = self::displayExportError($exception);
                $errorReference = 'Fehler-ID: Export #' . (int) $export->id;
                foreach ($rows as $row) R::exec("UPDATE inspection SET billing_status = 'export_failed', billing_last_error = ?, billing_last_export_id = ? WHERE id = ? AND billing_status <> 'exported'", [$displayError . ' ' . $errorReference, (int) $export->id, $row['id']]);
                audit_log('abrechnung_export_fehlgeschlagen', ['inspection_ids' => array_column($rows, 'id'), 'error' => $exception->getMessage(), 'export_id' => (int) $export->id]);
                $_SESSION['billing_message'] = 'SevDesk-Export fehlgeschlagen (Fehler-ID: Export #' . (int) $export->id . '): ' . $displayError;
            }
        }
        return $isHx ? [200, ['HX-Trigger' => 'billing-refresh'], ''] : [303, ['Location' => url_for('admin/abrechnung')], ''];
    }

    public static function eligibility(array $params, bool $isHx): array
    {
        if (!current_user_can_manage_billing()) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden.'];
        $eligibility = trim((string) ($_POST['eligibility'] ?? ''));
        if (!in_array($eligibility, ['billable', 'not_billable'], true)) return [422, [], 'Ungültige Abrechenbarkeit.'];
        $hasActiveInvoice = (int) R::getCell('SELECT COUNT(*) FROM billinginvoiceitem WHERE inspection_id = ? AND active = 1', [(int) $inspection->id]) > 0;
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
            $items = R::findAll('billinginvoiceitem', ' inspection_id = ? AND active = 1 ', [$inspectionId]);
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
        if (!current_user_can_manage_billing()) return forbidden_response();
        $id = (int) ($params['id'] ?? 0);
        $invoice = R::load('billinginvoice', $id);
        if (!$invoice->id) return [404, [], 'Rechnung nicht gefunden.'];
        $items = R::getAll('SELECT bi.*, i.external_number AS inspection_number, i.test_date, i.billing_status, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name FROM billinginvoiceitem bi JOIN inspection i ON i.id=bi.inspection_id JOIN device d ON d.id=bi.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE bi.invoice_id = ? ORDER BY d.external_number, i.test_date, i.id', [$id]);
        $content = render_template('billing_invoice.php', ['invoice' => $invoice, 'items' => $items]);
        return [200, [], render_template('layout.php', ['title' => 'Rechnung #' . $id, 'content' => $content])];
    }

    private static function recordInvoice(array $rows, string $externalId, string $status, array $response = []): int
    {
        $first = $rows[0] ?? [];
        R::begin();
        try {
        $invoice = R::dispense('billinginvoice');
        $invoice->customer_id = (int) ($first['customer_id'] ?? 0) ?: null;
        $invoice->tenant_id = (int) (get_branding()['company_id'] ?? 0) ?: null;
        $invoice->sevdesk_invoice_id = $externalId;
        $sevDeskInvoice = self::sevDeskInvoiceResponse($response);
        $invoice->sevdesk_invoice_number = $sevDeskInvoice['number'];
        $invoice->sevdesk_url = $sevDeskInvoice['url'];
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
            $item = R::dispense('billinginvoiceitem');
            $item->invoice_id = $invoiceId;
            $item->inspection_id = (int) ($row['id'] ?? 0);
            $item->device_id = (int) ($row['device_id'] ?? 0);
            $item->quantity = 1;
            $item->description = (string) ($row['device_number'] ?? '') . ' · ' . (string) ($row['device_name'] ?? 'Prüfung');
            $item->active = 1; $item->assigned_at = date(DATE_ATOM); $item->source = $status === 'sevdesk' ? 'sevdesk' : 'manual'; $item->created_at = date(DATE_ATOM);
            R::store($item);
            R::exec("UPDATE inspection SET billing_status = 'exported', billing_active_invoice_item_id = ?, billing_last_error = '', billing_last_export_id = NULL WHERE id = ?", [(int) $item->id, (int) ($row['id'] ?? 0)]);
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
        $eligibilityFilter = in_array($eligibilityFilter, ['all', 'billable', 'not_billable'], true) ? $eligibilityFilter : 'billable';
        $statusFilter = $statusFilter === '' ? 'not_billed' : ($statusFilter === 'all' ? '' : $statusFilter);
        // Abrechnungsstart ist 2025; Altbestand bis einschließlich 2024 bleibt
        // sichtbar in Prüfungen, wird aber niemals für einen Export angeboten.
        $where = InspectionEvaluationService::sqlStatusExpression('i') . " IN ('passed','failed') AND i.test_date >= '2025-01-01'";
        $args = [];
        if ($eligibilityFilter !== 'all') {
            $where .= " AND COALESCE(i.billing_eligibility, CASE WHEN i.billable = 1 THEN 'billable' ELSE 'not_billable' END) = ?";
            $args[] = $eligibilityFilter;
        }
        if ($statusFilter === 'not_billed') {
            $where .= " AND COALESCE(i.billing_status, CASE WHEN i.billing_exported_at IS NULL OR i.billing_exported_at = '' THEN 'not_exported' ELSE 'exported' END) IN ('not_exported','export_failed','manually_unexported')";
        } elseif ($statusFilter !== '') {
            $where .= ' AND COALESCE(i.billing_status, CASE WHEN i.billing_exported_at IS NULL OR i.billing_exported_at = \'\' THEN \'not_exported\' ELSE \'exported\' END) = ?';
            $args[] = $statusFilter;
        }
        $customerLink = trim((string) ($filters['customer_link'] ?? ''));
        if ($customerLink === 'assigned') $where .= ' AND c.id IS NOT NULL';
        elseif ($customerLink === 'missing') $where .= ' AND c.id IS NULL';
        elseif ($customerLink === 'sevdesk_linked') $where .= " AND c.id IS NOT NULL AND COALESCE(c.sevdesk_customer_id, '') <> ''";
        elseif ($customerLink === 'sevdesk_missing') $where .= " AND (c.id IS NULL OR COALESCE(c.sevdesk_customer_id, '') = '')";
        if ($ids !== []) { $where .= ' AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; array_push($args, ...$ids); }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') { $where .= ' AND (i.external_number LIKE ? OR d.external_number LIKE ? OR d.name LIKE ? OR c.name LIKE ?)'; array_push($args, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'); }
        foreach ([
            'customer_id' => 'c.id = ?',
            'site_id' => 's.id = ?',
            'building_id' => 'b.id = ?',
            'floor_id' => 'f.id = ?',
            'room_id' => 'r.id = ?',
            'from' => 'i.test_date >= ?',
            'to' => 'i.test_date <= ?',
        ] as $key => $condition) {
            if (($value = trim((string) ($filters[$key] ?? ''))) === '') continue;
            $where .= ' AND ' . $condition;
            $args[] = in_array($key, ['customer_id', 'site_id', 'building_id', 'floor_id', 'room_id'], true) ? (int) $value : $value;
        }
        $examiner = trim((string) ($filters['examiner'] ?? ''));
        if ($examiner !== '') {
            $where .= " AND LOWER(TRIM(COALESCE(i.examiner, ''))) = LOWER(?)";
            $args[] = $examiner;
        }
        $dueCondition = InspectionFilterService::dueCondition(
            trim((string) ($filters['due_status'] ?? '')),
            'i.next_due_date'
        );
        if ($dueCondition['sql'] !== '') {
            $where .= ' AND (' . $dueCondition['sql'] . ')';
            array_push($args, ...$dueCondition['params']);
        }
        $orderBy = match ((string) ($filters['sort'] ?? 'customer')) {
            'test_date_desc' => 'i.test_date DESC, i.id DESC',
            'test_date_asc' => 'i.test_date ASC, i.id ASC',
            'inspection' => 'i.external_number ASC, i.id ASC',
            'device' => 'd.external_number ASC, i.test_date DESC, i.id DESC',
            'billing_status' => "COALESCE(i.billing_status, CASE WHEN i.billing_exported_at IS NULL OR i.billing_exported_at = '' THEN 'not_exported' ELSE 'exported' END) ASC, i.test_date DESC, i.id DESC",
            default => 'c.name ASC, i.test_date ASC, i.id ASC',
        };
        return R::getAll("SELECT i.id, i.device_id, i.external_number, i.test_date, i.next_due_date, i.examiner, i.result_status, i.regie_minutes, i.regie_reason, i.billing_eligibility, i.billing_status, i.billing_not_billable_reason, i.billing_last_error, i.billing_last_export_id, d.external_number AS device_number, d.name AS device_name, c.id AS customer_id, c.name AS customer_name, c.sevdesk_customer_id, c.sevdesk_customer_number, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number, bi.invoice_id, inv.invoice_number, inv.sevdesk_invoice_id FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN billinginvoiceitem bi ON bi.inspection_id=i.id AND bi.active=1 LEFT JOIN billinginvoice inv ON inv.id=bi.invoice_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE {$where} ORDER BY {$orderBy}", $args);
    }

    /** @return array<string,string> */
    private static function filtersFromSelectionQuery(string $query): array
    {
        $query = (string) (parse_url($query, PHP_URL_QUERY) ?? $query);
        parse_str($query, $values);
        $filters = [];
        foreach (['q', 'eligibility', 'billing_status', 'customer_link', 'customer_id', 'site_id', 'building_id', 'floor_id', 'room_id', 'from', 'to', 'examiner', 'due_status', 'sort'] as $key) {
            if (!isset($values[$key]) || is_array($values[$key])) continue;
            $filters[$key] = mb_substr(trim((string) $values[$key]), 0, 160);
        }
        return $filters;
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

    /** @param list<array<string,mixed>> $rows */
    private static function missingSevDeskCustomerMessage(array $rows): string
    {
        $groups = [];
        foreach ($rows as $row) {
            $customer = trim((string) ($row['customer_name'] ?? '')) ?: 'Kein Kunde zugeordnet';
            $groups[$customer][] = (string) ($row['external_number'] ?? ('#' . (int) ($row['id'] ?? 0)));
        }
        $details = [];
        foreach ($groups as $customer => $inspections) {
            $details[] = $customer . ' (' . implode(', ', array_slice(array_unique($inspections), 0, 5)) . (count($inspections) > 5 ? ', …' : '') . ')';
        }
        return 'SevDesk-Entwurf nicht gestartet: Für folgende Auswahl fehlt die Kundenverknüpfung zu SevDesk: '
            . implode('; ', $details)
            . '. Bitte im Kundenprofil den Mandanten und die SevDesk-Kundennummer/-ID hinterlegen; bei „Kein Kunde zugeordnet“ zuerst Raum, Standort und Kundenstruktur des Geräts prüfen.';
    }

    /** Keep technical provider exceptions in the audit log, not in every table row. */
    private static function displayExportError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if (str_contains($message, 'createDraftInvoice') || str_contains($message, 'Argument #')) {
            return 'Der Export konnte nicht gestartet werden. Bitte erneut versuchen.';
        }
        if (str_contains($message, 'SevDesk-Ansprechperson fehlt')) {
            return 'Für diesen Mandanten fehlt die SevDesk-Ansprechperson. Bitte in der Mandantenverwaltung hinterlegen.';
        }
        if (preg_match('/(?:cURL|timeout|timed out|Connection|Netzwerkfehler)/i', $message)) {
            return 'SevDesk ist momentan nicht erreichbar. Bitte später erneut versuchen.';
        }
        if (preg_match('/SevDesk antwortet mit HTTP \d{3}/', $message)) {
            return 'SevDesk hat den Entwurf abgelehnt. Details stehen im Audit-Protokoll.';
        }
        if (str_contains($message, 'keine Rechnungs-ID zurück')) {
            return 'SevDesk hat geantwortet, aber keine Rechnungs-ID zurückgeliefert. Es wurde keine Prüfung als abgerechnet markiert.';
        }
        return 'Der Export ist fehlgeschlagen. Die technische Ursache steht im Audit-Protokoll.';
    }

    /** @return array{id:string,number:string,url:string} */
    private static function sevDeskInvoiceResponse(array $response): array
    {
        // SevDesk-Varianten liefern die Rechnung direkt, unter objects oder
        // als Element in einer Objektliste. Wir lesen nur Rechnungsfelder.
        $candidates = [$response];
        foreach (['object', 'objects', 'invoice'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) $candidates[] = $response[$key];
        }
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) continue;
            foreach ($candidate as $value) if (is_array($value)) $candidates[] = $value;
        }
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) continue;
            $id = trim((string) ($candidate['id'] ?? $candidate['invoiceId'] ?? ''));
            if ($id === '') continue;
            return [
                'id' => $id,
                'number' => trim((string) ($candidate['invoiceNumber'] ?? $candidate['number'] ?? '')),
                'url' => trim((string) ($candidate['url'] ?? $candidate['link'] ?? '')),
            ];
        }
        return ['id' => '', 'number' => '', 'url' => ''];
    }
}
