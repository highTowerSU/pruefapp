<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Persists customer hints and defects independently from a single inspection. */
final class DeviceFindingService
{
    /** @param list<array<string,mixed>> $answers */
    public static function syncFromInspection(int $deviceId, int $inspectionId, string $type, array $answers, string $failedAction = ''): void
    {
        R::exec("UPDATE device_finding SET state = 'resolved', resolved_at = ?, resolution_note = 'Durch erneute Prüfung ersetzt.' WHERE inspection_id = ? AND state = 'open'", [date(DATE_ATOM), $inspectionId]);
        foreach ($answers as $answer) {
            $severity = strtolower(trim((string) ($answer['severity'] ?? '')));
            if (!in_array($severity, ['green', 'orange', 'red'], true)) continue;
            $state = 'open';
            $dueDate = $severity === 'orange' ? date('Y-m-d', strtotime('+28 days')) : null;
            $blocked = $severity === 'red' ? 1 : 0;
            R::exec(
                'INSERT INTO device_finding (device_id, inspection_id, inspection_type_code, item_key, severity, state, action, due_date, blocked, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$deviceId, $inspectionId, $type, (string) ($answer['item_key'] ?? ''), $severity, $state, $failedAction, $dueDate, $blocked, trim((string) ($answer['remark'] ?? $answer['question_snapshot'] ?? '')), date(DATE_ATOM), date(DATE_ATOM)]
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public static function openForDevice(int $deviceId): array
    {
        return R::getAll("SELECT * FROM device_finding WHERE device_id = ? AND state = 'open' ORDER BY blocked DESC, due_date, id DESC", [$deviceId]);
    }

    public static function syncCustomerHint(int $deviceId, int $inspectionId, string $type, string $hint): void
    {
        R::exec("DELETE FROM device_finding WHERE inspection_id = ? AND item_key = 'customer_hint'", [$inspectionId]);
        $hint = trim($hint);
        if ($hint === '') return;
        R::exec(
            "INSERT INTO device_finding (device_id, inspection_id, inspection_type_code, item_key, severity, state, action, blocked, description, created_at, updated_at) VALUES (?, ?, ?, 'customer_hint', 'green', 'open', '', 0, ?, ?, ?)",
            [$deviceId, $inspectionId, InspectionTypeService::normalize($type), $hint, date(DATE_ATOM), date(DATE_ATOM)]
        );
    }
}
