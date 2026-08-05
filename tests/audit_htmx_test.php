<?php

declare(strict_types=1);

$template = (string) file_get_contents(dirname(__DIR__) . '/templates/audit_log.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/controllers/AdminController.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/index.php');

$checks = [
    [str_contains($template, 'hx-target="#audit-events-panel"'), 'Event-Panel has no HTMX target.'],
    [str_contains($template, 'hx-target="#audit-cron-panel"'), 'Cron-Panel has no HTMX target.'],
    [str_contains($template, 'hx-target="#audit-revisions-panel"'), 'Revision-Panel has no HTMX target.'],
    [str_contains($template, 'data-run-show-all="cron"') && str_contains($template, 'data-run-show-all="import"'), 'Run tables lack an all-show action.'],
    [str_contains($template, 'hx-push-url="true"'), 'Audit navigation does not synchronize the URL.'],
    [str_contains($template, "panel.setAttribute('hx-trigger', 'every 30s')"), 'Auto-refresh is not HTMX based.'],
    [!str_contains($template, 'location.reload'), 'Audit template still performs a full page reload.'],
    [!preg_match('/<meta[^>]+refresh/i', $template), 'Audit template contains a meta refresh.'],
    [str_contains($controller, "cron_runs"), 'Controller does not support multiple Cron runs.'],
    [str_contains($controller, 'exportAuditRuns'), 'Audit export endpoint is missing.'],
    [str_contains($routes, "'/admin/audit-log/export'"), 'Audit export route is missing.'],
];

foreach ($checks as [$ok, $message]) {
    if (!$ok) throw new RuntimeException($message);
}

echo "PASS: Audit view uses partial HTMX updates without page reloads\n";
