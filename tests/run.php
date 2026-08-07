<?php

declare(strict_types=1);

$tests = [
    'bootstrap_config_test.php',
    'branding_context_test.php',
    'link_branding_test.php',
    'revision_history_test.php',
    'shared_tenant_database_test.php',
    'structure_identifier_test.php',
    'background_job_resume_test.php',
    'cron_queue_integration_test.php',
    'audit_htmx_test.php',
    'notifications_htmx_test.php',
    'billing_v1_test.php',
    'inspection_evaluation_test.php',
    'inspection_type_workflow_test.php',
    'inspection_migration_test.php',
    'pending_measurement_job_test.php',
    'import_directory_debug_api_test.php',
    'inspection_filter_test.php',
    'user_reminder_test.php',
    'signature_gate_test.php',
    'device_ui_test.php',
    'device_media_test.php',
    'device_vocabulary_test.php',
    'vocabulary_storage_test.php',
    'ai_provider_ui_test.php',
    'vocabulary_oauth_test.php',
    'user_customer_access_test.php',
    'navbar_sticky_test.php',
    'action_navigation_test.php',
];
$failed = 0;
foreach ($tests as $test) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $test) . ' 2>&1';
    exec($command, $output, $code);
    if ($code === 0) echo 'PASS: ' . $test . PHP_EOL;
    else { $failed++; echo 'FAIL: ' . $test . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL; }
    $output = [];
}
if ($failed > 0) exit(1);
echo 'OK: ' . count($tests) . " tests\n";
