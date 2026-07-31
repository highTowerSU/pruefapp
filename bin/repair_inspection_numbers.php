#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/lib.inc.php';

$duplicates = R::getAll('SELECT external_number, COUNT(*) AS amount FROM inspection WHERE external_number IS NOT NULL AND external_number <> "" GROUP BY external_number HAVING COUNT(*) > 1');
$updated = [];
foreach ($duplicates as $duplicate) {
    $base = (string) $duplicate['external_number'];
    $rows = R::getAll('SELECT id FROM inspection WHERE external_number = ? ORDER BY id ASC', [$base]);
    $suffix = 2;
    foreach (array_slice($rows, 1) as $row) {
        do {
            $candidate = $base . '-' . $suffix++;
        } while (R::count('inspection', ' external_number = ? ', [$candidate]) > 0);
        $inspection = R::load('inspection', (int) $row['id']);
        $inspection->external_number = $candidate;
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
        $updated[] = ['id' => (int) $inspection->id, 'old' => $base, 'new' => $candidate];
    }
}

echo json_encode(['updated' => $updated], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
