#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reassign legacy Phoenix inspections that still carry the shared importer
 * account as examiner. The command is deliberately dry-run by default.
 *
 * Usage:
 *   php bin/migrate_examiner_attribution.php
 *   php bin/migrate_examiner_attribution.php --apply
 */

require_once dirname(__DIR__) . '/lib/lib.inc.php';

use RedBeanPHP\R;

$apply = in_array('--apply', $argv ?? [], true);
$source = 'info@CENEOS.net';
$bea = 'bdebertshaeuser@koenigsbl.au';
$eandro = 'edebertshaeuser@koenigsbl.au';

$rows = R::getAll(
    "SELECT id, test_date, examiner, source_type FROM inspection WHERE test_date IS NOT NULL AND COALESCE(source_type, '') IN ('json', 'csv') AND (LOWER(TRIM(examiner)) = LOWER(?) OR TRIM(COALESCE(examiner, '')) IN ('', '—')) ORDER BY test_date, id",
    [$source]
);

$changes = [];
foreach ($rows as $row) {
    $year = (int) substr(trim((string) ($row['test_date'] ?? '')), 0, 4);
    $target = in_array($year, [2023, 2024], true) ? $bea : ($year >= 2025 ? $eandro : '');
    if ($target === '') continue;
    $changes[] = [
        'id' => (int) $row['id'],
        'test_date' => (string) $row['test_date'],
        'from' => (string) $row['examiner'],
        'to' => $target,
        'source_type' => (string) ($row['source_type'] ?? ''),
    ];
}

$summary = [
    'quelle' => $source,
    'bea_2023_2024' => count(array_filter($changes, static fn(array $row): bool => $row['to'] === $bea)),
    'eandro_ab_2025' => count(array_filter($changes, static fn(array $row): bool => $row['to'] === $eandro)),
    'gesamt' => count($changes),
    'modus' => $apply ? 'apply' : 'dry-run',
];

if ($apply) {
    foreach ($changes as $change) {
        $inspection = R::load('inspection', $change['id']);
        if (!$inspection->id) continue;
        $currentExaminer = trim((string) ($inspection->examiner ?? ''));
        if (strcasecmp($currentExaminer, $source) !== 0 && !in_array($currentExaminer, ['', '—'], true)) continue;
        $inspection->examiner = $change['to'];
        if (($change['source_type'] ?? '') === 'csv') $inspection->report_path = '';
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
    }
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if (!$apply && $changes !== []) {
    echo "Keine Änderungen gespeichert. Mit --apply ausführen.\n";
}
