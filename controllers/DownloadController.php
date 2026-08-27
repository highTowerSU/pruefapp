<?php

declare(strict_types=1);

final class DownloadController
{
    public static function markNotificationRead(array $params, bool $isHx): array
    {
        $user = current_user();
        if ($user === null) return [403, [], ''];
        $notificationId = max(0, (int) ($params['id'] ?? 0));
        if ($notificationId <= 0) return [404, [], 'Benachrichtigung nicht gefunden.'];
        \Ceneos\PhpBase\Notification\NotificationRepository::markRead($notificationId, (int) $user->id);
        if ($isHx) return self::notificationFragment();
        return [303, ['Location' => url_for('downloads')], ''];
    }

    public static function markAllRead(array $params, bool $isHx): array
    {
        $user = current_user();
        if ($user === null) return [403, [], ''];
        \Ceneos\PhpBase\Notification\NotificationRepository::markAllRead((int) $user->id);
        if ($isHx) return self::notificationFragment();
        return [303, ['Location' => url_for('downloads')], ''];
    }

    public static function notifications(array $params, bool $isHx): array
    {
        if (current_user() === null) return [403, [], ''];
        return self::notificationDropdownFragment();
    }

    private static function notificationFragment(): array
    {
        if (($_SERVER['HTTP_HX_TARGET'] ?? '') !== 'downloads-notifications') {
            return self::notificationDropdownFragment();
        }
        $notifications = self::notificationsForDisplay(100);
        $content = render_template('_notifications_list.php', ['notifications' => $notifications])
            . render_template('_notification_badge.php', [
                'unreadCount' => count(array_filter($notifications, static fn(array $entry): bool => !empty($entry['notification_unread']))),
                'oob' => true,
            ]);
        return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
    }

    private static function notificationDropdownFragment(): array
    {
        return [200, ['Content-Type' => 'text/html; charset=utf-8'], render_template('_notifications_dropdown.php', [
            'notifications' => self::notificationsForDisplay(6),
            'downloadsUrl' => url_for('downloads'),
            'fragment' => true,
        ])];
    }

    /** @return array<int, array<string, mixed>> */
    private static function notificationsForDisplay(int $limit): array
    {
        $notifications = current_user_notifications($limit);
        foreach ($notifications as &$notification) {
            $actionUrl = trim((string) ($notification['action_url'] ?? ''));
            if ($actionUrl !== '' && preg_match('#/pruefapp/(?:bin/)?(.+)$#', $actionUrl, $match) === 1) {
                $notification['action_url'] = url_for($match[1]);
            }
        }
        unset($notification);
        return $notifications;
    }

    public static function markRead(array $params, bool $isHx): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        $user = current_user();
        if ($user === null) return [403, [], ''];
        if ($id === '') return [404, [], 'Aufgabe nicht gefunden.'];
        $job = BackgroundJobService::find($id);
        if ($job === null) return [404, [], 'Aufgabe nicht gefunden.'];
        if (!current_user_is_superadmin() && (int) ($job['owner_user_id'] ?? 0) !== (int) ($user->id ?? 0)) return forbidden_response();
        BackgroundJobService::markRead($id, (int) $user->id);
        return [303, ['Location' => url_for('downloads')], ''];
    }

    public static function index(array $params, bool $isHx): array
    {
        $user = current_user();
        if ($user === null) return [303, ['Location' => url_for('login.php')], ''];

        $jobs = current_user_background_jobs(100);
        foreach ($jobs as &$job) {
            $job['status_label'] = match ((string) ($job['state'] ?? '')) {
                'queued' => 'Wartet',
                'running' => 'Läuft',
                'done' => 'Fertig',
                'error' => 'Fehler',
                'cancelled' => 'Abgebrochen',
                'cancel_requested' => 'Abbruch vorgemerkt',
                default => 'Unbekannt',
            };
            $job['download_url'] = !empty($job['downloadable'])
                ? url_for('geraete/zip/' . rawurlencode((string) $job['id']) . '/download')
                : '';
        }
        unset($job);

        // Older CLI-created notifications could contain the filesystem path
        // derived from bin/cron.php. Convert those historical links to the
        // public application route while keeping normal URLs untouched.
        $notifications = self::notificationsForDisplay(100);

        $content = render_template('downloads.php', [
            'jobs' => $jobs,
            'notifications' => $notifications,
            'canSeeAll' => current_user_is_superadmin(),
            'whatsNewEntries' => WhatsNewService::entries(),
        ]);
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Downloads', 'content' => $content])];
    }
}
