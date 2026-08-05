<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class InspectionFilterService
{
    public const DUE_SOON_DAYS = 30;

    /** @return array<string, string> */
    public static function dueOptions(): array
    {
        return [
            'valid' => 'Gültig (mehr als 30 Tage)',
            'due_soon' => 'Bald fällig (bis 30 Tage)',
            'expired' => 'Abgelaufen',
            'missing' => 'Ohne nächsten Prüftermin',
        ];
    }

    /**
     * @return array{sql: string, params: array<int, string>}
     */
    public static function dueCondition(string $status, string $dateExpression, ?string $today = null): array
    {
        if (!array_key_exists($status, self::dueOptions())) return ['sql' => '', 'params' => []];
        $today = $today ?: date('Y-m-d');
        $soon = (new DateTimeImmutable($today))->modify('+' . self::DUE_SOON_DAYS . ' days')->format('Y-m-d');
        $date = "NULLIF(TRIM(COALESCE({$dateExpression}, '')), '')";
        return match ($status) {
            'expired' => ['sql' => "{$date} IS NOT NULL AND {$date} < ?", 'params' => [$today]],
            'due_soon' => ['sql' => "{$date} IS NOT NULL AND {$date} >= ? AND {$date} <= ?", 'params' => [$today, $soon]],
            'valid' => ['sql' => "{$date} IS NOT NULL AND {$date} > ?", 'params' => [$soon]],
            'missing' => ['sql' => "{$date} IS NULL", 'params' => []],
        };
    }

    public static function latestValueExpression(string $column, string $deviceAlias = 'd'): string
    {
        if (!in_array($column, ['next_due_date', 'examiner'], true)) {
            throw new InvalidArgumentException('Unsupported latest inspection column.');
        }
        return "(SELECT latest_filter.{$column} FROM inspection latest_filter WHERE latest_filter.device_id = {$deviceAlias}.id ORDER BY latest_filter.test_date DESC, latest_filter.id DESC LIMIT 1)";
    }

    /**
     * @param array<int, int> $allowedCustomerIds
     * @return array<int, array{value: string, label: string}>
     */
    public static function examinerOptions(array $allowedCustomerIds = [], bool $restrictCustomers = false): array
    {
        if ($restrictCustomers && $allowedCustomerIds === []) return [];
        $where = "TRIM(COALESCE(i.examiner, '')) <> ''";
        $params = [];
        if ($restrictCustomers) {
            $where .= ' AND c.id IN (' . implode(',', array_fill(0, count($allowedCustomerIds), '?')) . ')';
            $params = array_values(array_map('intval', $allowedCustomerIds));
        }
        $rows = R::getAll(
            'SELECT DISTINCT TRIM(i.examiner) AS examiner FROM inspection i '
            . 'JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id '
            . 'LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id '
            . 'LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id '
            . "WHERE {$where} ORDER BY LOWER(TRIM(i.examiner))",
            $params
        );
        return array_map(static function (array $row): array {
            $value = trim((string) ($row['examiner'] ?? ''));
            return ['value' => $value, 'label' => display_examiner_name($value)];
        }, $rows);
    }
}
