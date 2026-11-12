<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/HistoricalBillingAllocation.php';
require_once dirname(__DIR__) . '/controllers/BillingController.php';

foreach ([[293, [80, 70, 65, 60, 58], [70, 62, 57, 53, 51]], [24, [0, 0, 0], [8, 8, 8]], [7, [3, 1, 0], [5, 2, 0]], [0, [1, 2], [0, 0]]] as [$total, $weights, $expected]) {
    if (HistoricalBillingAllocation::distribute($total, $weights) !== $expected) throw new RuntimeException('Historische Verteilung stimmt nicht.');
}
$period = new ReflectionMethod(BillingController::class, 'importedPerformancePeriod');
foreach ([
    [['deliveryDate' => '2026-03-27T00:00:00+01:00', 'deliveryDateUntil' => '2026-03-29T23:59:59+02:00'], ['from' => '2026-03-27', 'until' => '2026-03-29']],
    [['deliveryDate' => '2026-01-05'], ['from' => '2026-01-05', 'until' => '2026-01-05']],
    [['deliveryDate' => '2026-02-30'], ['from' => '', 'until' => '']],
    [['deliveryDate' => '2026-04-02', 'deliveryDateUntil' => '2026-04-01'], ['from' => '', 'until' => '']],
    [[], ['from' => '', 'until' => '']],
] as [$raw, $expected]) {
    if ($period->invoke(null, $raw) !== $expected) throw new RuntimeException('Leistungszeitraum falsch übernommen.');
}
echo "PASS: historical totals preserve integer sums and original service dates\n";
