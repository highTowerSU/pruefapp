#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/lib.inc.php';

$directory = $argv[1] ?? '';
$reportsDirectory = $argv[2] ?? null;
if ($directory === '') {
    fwrite(STDERR, "Verwendung: php bin/import_electro.php /pfad/zu/Import [pdf-quellordner]\n");
    exit(2);
}

try {
    $stats = (new ElectricalInspectionImportService())->importDirectory($directory, $reportsDirectory);
    app_write_import_log('CLI Elektro-Import', $stats);
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(count($stats['errors']) > 0 ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
