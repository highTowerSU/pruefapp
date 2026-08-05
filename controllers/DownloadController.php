<?php

declare(strict_types=1);

final class DownloadController
{
    public static function markRead(array $params, bool $isHx): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        $user = current_user();
        if ($user === null) return [403, [], ''];
        if ($id === '') return [404, [], 'Aufgabe nicht gefunden.'];
        $paths = array_merge(
            [sys_get_temp_dir() . '/pruefapp-phoenix-jobs/' . $id . '.status.json'],
            glob(app_data_root() . '/logs/background-jobs/' . $id . '.status.json') ?: []
        );
        $found = false;
        foreach (array_unique($paths) as $path) {
            if (!is_file($path)) continue;
            $job = json_decode((string) @file_get_contents($path), true) ?: [];
            if (!current_user_is_superadmin() && (int) ($job['owner_user_id'] ?? 0) !== (int) ($user->id ?? 0)) return forbidden_response();
            $job['notification_read'] = true;
            $job['notification_read_at'] = date(DATE_ATOM);
            file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE), LOCK_EX);
            $found = true;
        }
        if (!$found) return [404, [], 'Aufgabe nicht gefunden.'];
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

        $content = render_template('downloads.php', [
            'jobs' => $jobs,
            'canSeeAll' => current_user_is_superadmin(),
        ]);
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Downloads', 'content' => $content])];
    }
}
