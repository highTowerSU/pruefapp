<?php

declare(strict_types=1);

$schema = (string) file_get_contents(dirname(__DIR__) . '/lib/lib.inc.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/BillingController.php');
$template = (string) file_get_contents(dirname(__DIR__) . '/templates/billing_index.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$detail = (string) file_get_contents(dirname(__DIR__) . '/templates/inspection_detail.php');
$devices = (string) file_get_contents(dirname(__DIR__) . '/templates/device_index.php');
$deviceController = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceController.php');
$renderer = (string) file_get_contents(dirname(__DIR__) . '/lib/filter_renderer.php');

$checks = [
    [str_contains($schema, 'billing_eligibility') && str_contains($schema, 'billing_status'), 'Billing status fields are missing.'],
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS billingexport'), 'Billing export history table is missing.'],
    [str_contains($controller, "R::dispense('billingexport')") && str_contains($controller, "R::dispense('billinginvoice')"), 'Billing beans must use RedBean-compatible names without underscores.'],
    [str_contains($controller, 'idempotency_key') && str_contains($controller, "status = 'running'"), 'Export idempotency is missing.'],
    [str_contains($controller, 'missingSevDeskCustomerMessage') && str_contains($controller, "abrechnung_export_blockiert"), 'SevDesk export must validate customer links before creating invoices.'],
    [str_contains($controller, 'public static function resetExport'), 'Manual export reset endpoint is missing.'],
    [str_contains($controller, 'public static function eligibility'), 'Eligibility endpoint is missing.'],
    [str_contains($controller, "i.test_date >= '2025-01-01'"), 'Pre-2025 inspections must not be billable.'],
    [str_contains($controller, "HX-Trigger' => 'billing-refresh'"), 'Billing status actions must refresh through HTMX.'],
    [str_contains($template, 'data-billing-csv-download') && str_contains($template, 'download.submit()') && str_contains($template, "action.value = 'csv'") && str_contains($template, "source.getAttribute('action')"), 'CSV export must use a native browser download instead of an HTMX swap.'],
    [str_contains($template, 'billing-selection-scope') && str_contains($template, 'Alle gefilterten Prüfungen') && str_contains($template, 'billing-mark-all') && str_contains($controller, 'filtersFromSelectionQuery') && str_contains($controller, "\$selectionScope === 'all'"), 'Billing pagination selection and all-filtered scope are missing.'],
    [str_contains($template, 'hx-get=') && str_contains($template, 'billing-status'), 'Billing filters are not HTMX-enabled.'],
    [str_contains($renderer, 'customer_link') && str_contains($renderer, 'Kunde vorhanden') && str_contains($renderer, 'SevDesk fehlt') && str_contains($renderer, 'Exportiert / abgerechnet'), 'Billing customer-link and invoice-state filters are incomplete.'],
    [str_contains($controller, "\$eligibilityFilter !== 'all'") && str_contains($controller, "\$customerLink === 'sevdesk_missing'"), 'Billing filters are not enforced on the server.'],
    [str_contains($renderer, 'name="sort"') && str_contains($controller, "'test_date_desc' =>") && str_contains($controller, 'ORDER BY {$orderBy}'), 'Billing sort options are not enforced on the server.'],
    [str_contains($renderer, 'Noch nicht abgerechnet (Standard)') && str_contains($controller, "\$statusFilter === 'not_billed'") && str_contains($controller, "'export_failed','manually_unexported'"), 'Default billing status must include retryable, non-billed exports.'],
    [str_contains($template, 'Export zurücksetzen'), 'Billing reset action is missing from the UI.'],
    [str_contains($template, "if (\$status !== 'exported')") && str_contains($template, 'form-check-input billing-check'), 'Non-billable inspections must remain selectable for bulk eligibility changes.'],
    [str_contains($detail, 'Abrechnungshistorie'), 'Inspection billing history is missing.'],
    [str_contains($routes, "'/admin/abrechnung/pruefung/{id}/export-zuruecksetzen'"), 'Billing reset route is missing.'],
    [str_contains($devices, 'billing_status') && str_contains($devices, 'billing_eligibility'), 'Device billing filters are missing.'],
    [str_contains($devices, 'Abrechnung vorbereiten') && str_contains($deviceController, "\$action === 'billing'"), 'Device billing bulk action is missing.'],
    [str_contains($renderer, 'render_common_filter_panel') && str_contains($devices, 'device-common-filter') && str_contains($template, 'billing-common-filter') && !str_contains($devices, 'display:none') && !str_contains($template, 'display:none'), 'Shared filter renderer is missing from both views.'],
    [str_contains($renderer, "\$hxSelect = \$context === 'billing' ? ''") && str_contains($template, "{target:'#billing-content', swap:'outerHTML'"), 'Billing HTMX responses must swap their single root without selecting a second fragment.'],
    [str_contains($deviceController, "if (\$isHx) return [200") && str_contains($renderer, "'#device-page'") && str_contains($renderer, 'change from:select delay:120ms') && str_contains($renderer, 'Filter wird angewendet'), 'Device HTMX partial rendering or immediate filter feedback is missing.'],
    [str_contains($controller, '$requestedPerPage = (int) ($_GET[\'per_page\'] ?? 50)') && str_contains($controller, '$perPage = in_array($requestedPerPage'), 'Billing pagination must use a safe default page size.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message ?? 'Billing v1 check failed.');
}

echo "PASS: billing v1 status, history, HTMX UI and routes are present\n";
foreach (['c.id = ?', 's.id = ?', 'b.id = ?', 'f.id = ?', 'r.id = ?', 'i.test_date >= ?', 'i.test_date <= ?'] as $condition) {
    if (!str_contains($controller, "'" . $condition . "'")) throw new RuntimeException('Abrechnungsfilter muss den SQL-Platzhalter enthalten: ' . $condition);
}
