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
        $signaturePath = trim((string) ($user->report_signature_path ?? ''));
        if ($signaturePath === '' || !is_file($signaturePath)) {
            $reminders[] = self::reminder(
                'Unterschrift im Profil ergänzen',
                'Für neue Prüfberichte fehlt noch deine Unterschrift. Bitte hinterlege sie im Profil.',
                'warning',
                url_for('profil'),
                'profile',
            );
            NotificationRepository::publish(
                [$userId],
                'Unterschrift im Profil ergänzen',
                'Für neue Prüfberichte fehlt noch deine Unterschrift. Bitte hinterlege sie im Profil.',
                ['dedupe_key' => $signatureKey, 'category' => 'profile', 'severity' => 'warning', 'action_url' => url_for('profil')]
            );
        } else {
            self::markDedupeRead($signatureKey, $userId);
        }

        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $identities = self::identities($user);
        $missing = self::missingInspections($identities, $yesterday);
        if ($missing > 0) {
            $identity = $identities[0] ?? '';
            $query = http_build_query(['from' => $yesterday, 'to' => $yesterday, 'examiner' => $identity, 'result_status' => 'open']);
            $actionUrl = url_for('pruefungen?' . $query);
            $message = $missing . ' Prüfung' . ($missing === 1 ? '' : 'en') . ' vom ' . (new DateTimeImmutable($yesterday))->format('d.m.Y') . ' enthalten noch fehlende Daten oder sind nicht abgeschlossen.';
            $dedupeKey = 'inspection-missing:user:' . $userId . ':' . $yesterday;
            $reminders[] = self::reminder('Prüfdaten vom Vortag fehlen', $message, 'warning', $actionUrl, 'inspection');
            NotificationRepository::publish(
                [$userId],
                'Prüfdaten vom Vortag fehlen',
                $message,
                ['dedupe_key' => $dedupeKey, 'category' => 'inspection', 'severity' => 'warning', 'action_url' => $actionUrl]
            );
        }

        return $reminders;
    }

    public static function canPerformInspections($user): bool
    {
        return RolePolicy::allows((string) ($user->role ?? ''), RolePolicy::EDITOR);
    }

    /** @param list<string> $identities */
    private static function missingInspections(array $identities, string $date): int
    {
        if ($identities === []) return 0;
        $where = ["i.test_date = ?", InspectionEvaluationService::sqlStatusExpression('i') . " IN ('in_progress','data_missing')"];
        $args = [$date];
        $clauses = [];
        foreach ($identities as $identity) {
            $clauses[] = 'LOWER(TRIM(COALESCE(i.examiner, \'\'))) = ?';
            $args[] = strtolower($identity);
        }
        $where[] = '(' . implode(' OR ', $clauses) . ')';
        return (int) R::getCell('SELECT COUNT(*) FROM inspection i WHERE ' . implode(' AND ', $where), $args);
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

    /** @return array{title:string,message:string,severity:string,action_url:string,category:string} */
    private static function reminder(string $title, string $message, string $severity, string $actionUrl, string $category): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'action_url' => $actionUrl,
            'category' => $category,
        ];
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
