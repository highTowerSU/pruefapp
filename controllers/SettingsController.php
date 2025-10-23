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
        $storedKeycloakAccountUrl = trim((string) (get_app_config('keycloak_account_console_base_url') ?? ''));

        $envOverride = env_value('MOODLE_PATH');
        $keycloakAccountEnvOverride = env_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL');

        $effectiveMoodlePath = moodle_root_path();
        $effectiveKeycloakAccountUrl = keycloak_account_console_base_url();

        $values = [
            'moodle_path' => $storedMoodlePath,
            'keycloak_account_console_base_url' => $storedKeycloakAccountUrl,
        ];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $values['moodle_path'] = trim((string) ($_POST['moodle_path'] ?? ''));
            $values['keycloak_account_console_base_url'] = trim((string) ($_POST['keycloak_account_console_base_url'] ?? ''));

            if ($values['moodle_path'] !== '' && !is_dir($values['moodle_path'])) {
                $errors['moodle_path'] = 'Das angegebene Verzeichnis wurde nicht gefunden.';
            }

            if ($values['keycloak_account_console_base_url'] !== ''
                && filter_var($values['keycloak_account_console_base_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['keycloak_account_console_base_url'] = 'Bitte eine gültige URL angeben oder das Feld leer lassen.';
            }

            if ($errors === []) {
                try {
                    if ($values['moodle_path'] !== $storedMoodlePath) {
                        set_app_config('moodle_path', $values['moodle_path'] === '' ? null : $values['moodle_path']);
                        audit_log('konfiguration_moodle_pfad_geaendert', [
                            'feld' => 'moodle_path',
                            'alt' => $storedMoodlePath,
                            'neu' => $values['moodle_path'],
                        ]);
                    }

                    if ($values['keycloak_account_console_base_url'] !== $storedKeycloakAccountUrl) {
                        set_app_config(
                            'keycloak_account_console_base_url',
                            $values['keycloak_account_console_base_url'] === ''
                                ? null
                                : $values['keycloak_account_console_base_url']
                        );
                        audit_log('konfiguration_keycloak_konto_url_geaendert', [
                            'feld' => 'keycloak_account_console_base_url',
                            'alt' => $storedKeycloakAccountUrl,
                            'neu' => $values['keycloak_account_console_base_url'],
                        ]);
                    }
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
            'storedKeycloakAccountUrl' => $storedKeycloakAccountUrl,
            'effectiveKeycloakAccountUrl' => $effectiveKeycloakAccountUrl,
            'keycloakAccountEnvOverride' => $keycloakAccountEnvOverride,
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
