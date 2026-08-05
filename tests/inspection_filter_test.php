<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/InspectionFilterService.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE inspection (id INTEGER PRIMARY KEY, next_due_date TEXT)');
$insert = $pdo->prepare('INSERT INTO inspection (id, next_due_date) VALUES (?, ?)');
foreach ([
    [1, '2026-08-05'],
    [2, '2026-08-06'],
    [3, '2026-09-05'],
    [4, '2026-09-06'],
    [5, null],
] as $row) {
    $insert->execute($row);
}

$expected = [
    'expired' => [1],
    'due_soon' => [2, 3],
    'valid' => [4],
    'missing' => [5],
];
foreach ($expected as $status => $ids) {
    $condition = InspectionFilterService::dueCondition($status, 'next_due_date', '2026-08-06');
    $statement = $pdo->prepare('SELECT id FROM inspection WHERE ' . $condition['sql'] . ' ORDER BY id');
    $statement->execute($condition['params']);
    $actual = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if ($actual !== $ids) {
        throw new RuntimeException("Fälligkeit {$status} wurde falsch abgegrenzt: " . json_encode($actual));
    }
}

$invalid = InspectionFilterService::dueCondition('invalid', 'next_due_date', '2026-08-06');
if ($invalid !== ['sql' => '', 'params' => []]) {
    throw new RuntimeException('Unbekannte Fälligkeitswerte dürfen keinen SQL-Filter erzeugen.');
}

$latest = InspectionFilterService::latestValueExpression('examiner');
if (!str_contains($latest, 'ORDER BY latest_filter.test_date DESC, latest_filter.id DESC LIMIT 1')) {
    throw new RuntimeException('Der Gerätefilter verwendet nicht eindeutig die jüngste Prüfung.');
}

$renderer = (string) file_get_contents(dirname(__DIR__) . '/lib/filter_renderer.php');
$deviceController = (string) file_get_contents(dirname(__DIR__) . '/controllers/DeviceController.php');
$billingController = (string) file_get_contents(dirname(__DIR__) . '/controllers/BillingController.php');
$reportController = (string) file_get_contents(dirname(__DIR__) . '/controllers/ReportController.php');
foreach (['name="examiner"', 'name="due_status"'] as $needle) {
    if (!str_contains($renderer, $needle)) {
        throw new RuntimeException("Gemeinsames Filterfeld fehlt: {$needle}");
    }
}
foreach ([$deviceController, $billingController, $reportController] as $controller) {
    if (!str_contains($controller, 'InspectionFilterService::dueCondition')) {
        throw new RuntimeException('Ein Abfrageweg berücksichtigt den Fälligkeitsfilter nicht.');
    }
    if (!str_contains($controller, "['examiner']")) {
        throw new RuntimeException('Ein Abfrageweg berücksichtigt den Prüferfilter nicht.');
    }
}

echo "PASS: Shared examiner and due-date filters use stable server-side boundaries\n";
