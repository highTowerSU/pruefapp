<?php

declare(strict_types=1);

final class DownloadController
{
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
