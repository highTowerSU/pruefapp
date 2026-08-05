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
    [str_contains($schema, 'CREATE TABLE IF NOT EXISTS billing_export'), 'Billing export history table is missing.'],
    [str_contains($controller, 'idempotency_key') && str_contains($controller, "status = 'running'"), 'Export idempotency is missing.'],
    [str_contains($controller, 'public static function resetExport'), 'Manual export reset endpoint is missing.'],
    [str_contains($controller, 'public static function eligibility'), 'Eligibility endpoint is missing.'],
    [str_contains($controller, "i.test_date >= '2025-01-01'"), 'Pre-2025 inspections must not be billable.'],
    [str_contains($controller, "HX-Trigger' => 'billing-refresh'"), 'Billing status actions must refresh through HTMX.'],
    [str_contains($template, 'hx-get=') && str_contains($template, 'billing-status'), 'Billing filters are not HTMX-enabled.'],
    [str_contains($template, 'Export zurücksetzen'), 'Billing reset action is missing from the UI.'],
    [str_contains($detail, 'Abrechnungshistorie'), 'Inspection billing history is missing.'],
    [str_contains($routes, "'/admin/abrechnung/pruefung/{id}/export-zuruecksetzen'"), 'Billing reset route is missing.'],
    [str_contains($devices, 'billing_status') && str_contains($devices, 'billing_eligibility'), 'Device billing filters are missing.'],
    [str_contains($devices, 'Abrechnung vorbereiten') && str_contains($deviceController, "\$action === 'billing'"), 'Device billing bulk action is missing.'],
    [str_contains($renderer, 'render_common_filter_panel') && str_contains($devices, 'device-common-filter') && str_contains($template, 'billing-common-filter') && str_contains($devices, ':not(.common-filter-panel)') && str_contains($template, ':not(#billing-common-filter)'), 'Shared filter renderer is missing from both views.'],
    [str_contains($deviceController, "if (\$isHx) return [200") && str_contains($renderer, "'#device-page'"), 'Device HTMX partial rendering is missing.'],
    [str_contains($controller, '$requestedPerPage = (int) ($_GET[\'per_page\'] ?? 50)') && str_contains($controller, '$perPage = in_array($requestedPerPage'), 'Billing pagination must use a safe default page size.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message ?? 'Billing v1 check failed.');
}

echo "PASS: billing v1 status, history, HTMX UI and routes are present\n";
