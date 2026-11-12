<?php

declare(strict_types=1);

/** Integer allocation of an approved historical total, without changing source measurements. */
final class HistoricalBillingAllocation
{
    /** @param list<int|float> $weights @return list<int> */
    public static function distribute(int $total, array $weights): array
    {
        if ($total < 0 || $weights === [] || min($weights) < 0) {
            throw new InvalidArgumentException('Nichtnegative Summe und mindestens ein Gewicht erforderlich.');
        }
        if (array_sum($weights) === 0) {
            $weights = array_fill(0, count($weights), 1);
        }
        $sum = array_sum($weights);
        $values = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $numerator = $total * $weight;
            $values[$index] = (int) floor($numerator / $sum);
            $remainders[$index] = $numerator - $values[$index] * $sum;
        }
        $order = array_keys($weights);
        usort($order, static fn(int $a, int $b): int => $remainders[$b] <=> $remainders[$a] ?: $a <=> $b);
        $remaining = $total - array_sum($values);
        for ($index = 0; $index < $remaining; $index++) {
            $values[$order[$index]]++;
        }
        return $values;
    }
}
