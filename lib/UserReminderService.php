<?php

declare(strict_types=1);

use Ceneos\PhpBase\Auth\RolePolicy;
use Ceneos\PhpBase\Notification\NotificationRepository;
use RedBeanPHP\R;

/** Creates actionable personal reminders after a successful login. */
final class UserReminderService
{
    /** @return list<array{title:string,message:string,severity:string,action_url:string,category:string}> */
    public static function afterLogin($user, ?DateTimeImmutable $now = null): array
    {
        if (!$user || !self::canPerformInspections($user)) return [];
        $now ??= new DateTimeImmutable('now');
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) return [];

        $reminders = [];
        $signatureKey = 'profile-signature-missing:user:' . $userId . ':v1';
        self::markDedupeRead($signatureKey, $userId);

        $permissionKey = 'inspection-permission-missing:user:' . $userId . ':v1';
        $expiredTypes = [];
        foreach (InspectionTypeService::active() as $inspectionType) {
            foreach (InspectionTypeService::examinerEligibility($user, (string) $inspectionType['code'])['requirements'] as $requirement) {
                if ((int) ($requirement['validity_days'] ?? 0) < 1) continue;
                $qualification = R::getRow('SELECT issued_at, expires_at FROM user_qualification WHERE oauthuser_id = ? AND requirement_code = ? ORDER BY confirmed_at DESC, id DESC LIMIT 1', [$userId, (string) $requirement['code']]);
                if ($qualification === []) continue;
                $expiresAt = trim((string) ($qualification['expires_at'] ?? ''));
                if ($expiresAt === '' && trim((string) ($qualification['issued_at'] ?? '')) !== '') {
                    $expiresAt = date('Y-m-d', strtotime((string) $qualification['issued_at'] . ' +' . (int) $requirement['validity_days'] . ' days'));
                }
                if ($expiresAt !== '' && $expiresAt < $now->format('Y-m-d')) {
                    $expiredTypes[] = (string) $inspectionType['name'] . ': ' . (string) $requirement['name'] . ' (abgelaufen am ' . (new DateTimeImmutable($expiresAt))->format('d.m.Y') . ')';
                }
            }
        }
        if ($expiredTypes !== []) {
            $message = 'Folgende Prüfberechtigungsnachweise sind abgelaufen:\n' . implode("\n", array_values(array_unique($expiredTypes))) . '\n\nBitte aktualisiere die Nachweise im Profil.';
            $reminders[] = self::reminder('Prüfberechtigung abgelaufen', $message, 'warning', url_for('profil'), 'profile');
            NotificationRepository::publish(
                [$userId],
                'Prüfberechtigung abgelaufen',
                $message,
                ['dedupe_key' => $permissionKey, 'category' => 'profile', 'severity' => 'warning', 'action_url' => url_for('profil')]
            );
        } else {
            self::markDedupeRead($permissionKey, $userId);
        }

        $today = $now->format('Y-m-d');
        $identities = self::identities($user);
        $missing = self::missingInspections($identities, $today);
        $dedupeKey = 'inspection-missing:user:' . $userId . ':open-v2';
        if ($missing !== []) {
            $identity = $identities[0] ?? '';
            $grouped = [];
            foreach ($missing as $item) {
                $date = (string) ($item['test_date'] ?? '');
                $grouped[$date][] = $item;
            }
            krsort($grouped);
            $displayDates = array_slice(array_keys($grouped), 0, 10);
            $actionFrom = (string) (end($displayDates) ?: $today);
            $query = http_build_query(['from' => $actionFrom, 'to' => $now->modify('-1 day')->format('Y-m-d'), 'examiner' => $identity, 'result_status' => 'open']);
            $actionUrl = url_for('pruefungen?' . $query);
            $lines = [];
            foreach ($displayDates as $date) {
                $items = $grouped[$date];
                $label = (new DateTimeImmutable($date))->format('d.m.Y');
                $lines[] = $label . ': ' . count($items) . ' Prüfung' . (count($items) === 1 ? '' : 'en');
            }
            $otherDays = count($grouped) - count($displayDates);
            if ($otherDays > 0) $lines[] = '… weitere ' . $otherDays . ' Tage';
            $message = count($missing) . ' offene Prüfung' . (count($missing) === 1 ? '' : 'en') . " mit fehlenden Daten oder ohne Abschluss an den zuletzt geprüften Tagen:\n" . implode("\n", $lines);
            $reminders[] = self::reminder('Offene Prüfdaten', $message, 'warning', $actionUrl, 'inspection');
            NotificationRepository::publish(
                [$userId],
                'Offene Prüfdaten',
                $message,
                ['dedupe_key' => $dedupeKey, 'category' => 'inspection', 'severity' => 'warning', 'action_url' => $actionUrl]
            );
        } else {
            self::markDedupeRead($dedupeKey, $userId);
        }

        return $reminders;
    }

    public static function canPerformInspections($user): bool
    {
        return RolePolicy::allows((string) ($user->role ?? ''), RolePolicy::EDITOR);
    }

    /** @param list<string> $identities */
    /** @return list<array{inspection_id:int,test_date:string,inspection_number:string,device_name:string}> */
    private static function missingInspections(array $identities, string $beforeDate): array
    {
        if ($identities === []) return [];
        $where = ["i.test_date < ?", "COALESCE(i.test_date, '') <> ''", InspectionEvaluationService::sqlStatusExpression('i') . " IN ('in_progress','data_missing')"];
        $args = [$beforeDate];
        $clauses = [];
        foreach ($identities as $identity) {
            $clauses[] = 'LOWER(TRIM(COALESCE(i.examiner, \'\'))) = ?';
            $args[] = strtolower($identity);
        }
        $where[] = '(' . implode(' OR ', $clauses) . ')';
        $rows = R::getAll(
            'SELECT i.id AS inspection_id, i.test_date, COALESCE(i.external_number, \'\') AS inspection_number, COALESCE(d.name, \'\') AS device_name FROM inspection i LEFT JOIN device d ON d.id = i.device_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.test_date ASC, i.id ASC',
            $args
        );
        return array_map(static fn (array $row): array => [
            'inspection_id' => (int) ($row['inspection_id'] ?? 0),
            'test_date' => (string) ($row['test_date'] ?? ''),
            'inspection_number' => (string) ($row['inspection_number'] ?? ''),
            'device_name' => (string) ($row['device_name'] ?? ''),
        ], $rows);
    }

    /** @return list<string> */
    private static function identities($user): array
    {
        $values = [
            trim((string) ($user->email ?? '')),
            trim((string) ($user->name ?? '')),
            trim((string) ($user->preferred_username ?? '')),
        ];
        $seen = [];
        foreach ($values as $value) {
            if ($value !== '') $seen[strtolower($value)] = $value;
        }
        return array_values($seen);
    }

    /** @return array{title:string,message:string,severity:string,action_url:string,category:string,details?:list<array{inspection_id:int,test_date:string,inspection_number:string,device_name:string}>} */
    private static function reminder(string $title, string $message, string $severity, string $actionUrl, string $category, array $details = []): array
    {
        $reminder = [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'action_url' => $actionUrl,
            'category' => $category,
        ];
        if ($details !== []) $reminder['details'] = $details;
        return $reminder;
    }

    private static function markDedupeRead(string $dedupeKey, int $userId): void
    {
        try {
            NotificationRepository::forUser($userId, 1);
            R::exec(
                "UPDATE notificationreceipt SET read_at = ? WHERE user_id = ? AND COALESCE(read_at, '') = '' AND notification_id IN (SELECT id FROM notification WHERE dedupe_key = ?)",
                [date(DATE_ATOM), $userId, $dedupeKey]
            );
        } catch (Throwable) {
            // A reminder must never make login fail.
        }
    }
}
