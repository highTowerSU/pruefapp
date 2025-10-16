<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/MoodleImportService.php';

class SettingsController
{
    public static function general(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $storedMoodlePath = trim((string) (get_app_config('moodle_path') ?? ''));
        $envOverride = env_value('MOODLE_PATH');
        $effectiveMoodlePath = moodle_root_path();

        $values = [
            'moodle_path' => $storedMoodlePath,
        ];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $values['moodle_path'] = trim((string) ($_POST['moodle_path'] ?? ''));

            if ($values['moodle_path'] !== '' && !is_dir($values['moodle_path'])) {
                $errors['moodle_path'] = 'Das angegebene Verzeichnis wurde nicht gefunden.';
            }

            if ($errors === []) {
                $previousValue = $storedMoodlePath;

                try {
                    set_app_config('moodle_path', $values['moodle_path'] === '' ? null : $values['moodle_path']);
                    audit_log('konfiguration_moodle_pfad_geaendert', [
                        'feld' => 'moodle_path',
                        'alt' => $previousValue,
                        'neu' => $values['moodle_path'],
                    ]);
                    $_SESSION['meldung'] = 'Die Konfiguration wurde gespeichert.';

                    return [303, ['Location' => url_for('admin/konfiguration')], ''];
                } catch (\Throwable $exception) {
                    $errors['general'] = 'Konfiguration konnte nicht gespeichert werden: ' . $exception->getMessage();
                }
            }
        }

        $statusPath = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) {
            $statusPath = $values['moodle_path'] !== '' ? $values['moodle_path'] : null;
        }

        $statusService = new MoodleImportService($statusPath);
        $moodleStatus = $statusService->getStatus();

        $content = render_template('settings.php', [
            'values' => $values,
            'errors' => $errors,
            'storedMoodlePath' => $storedMoodlePath,
            'effectiveMoodlePath' => $effectiveMoodlePath,
            'envOverride' => $envOverride,
            'moodleStatus' => $moodleStatus,
        ]);

        if ($isHx) {
            return [200, [], $content];
        }

        $body = render_template('layout.php', [
            'title' => 'Konfiguration',
            'content' => $content,
        ]);

        return [200, [], $body];
    }
}
