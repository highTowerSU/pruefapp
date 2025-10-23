<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/MoodleImportService.php';
require_once __DIR__ . '/../lib/MoodleCourseService.php';

class SettingsController
{
    public static function general(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $storedMoodlePath = trim((string) (get_app_config('moodle_path') ?? ''));
        $storedKeycloakAccountUrl = trim((string) (get_app_config('keycloak_account_console_base_url') ?? ''));
        $storedKeycloakAdminUrl = trim((string) (get_app_config('keycloak_admin_console_base_url') ?? ''));
        $storedMoodleWebserviceUrl = trim((string) (get_app_config('moodle_webservice_url') ?? ''));
        $storedMoodleWebserviceToken = trim((string) (get_app_config('moodle_webservice_token') ?? ''));

        $envOverride = env_value('MOODLE_PATH');
        $keycloakAccountEnvOverride = env_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL');
        $keycloakAdminEnvOverride = env_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL');
        $webserviceUrlEnvOverride = env_value('MOODLE_WEBSERVICE_URL');
        $webserviceTokenEnvOverride = env_value('MOODLE_WEBSERVICE_TOKEN');

        $effectiveMoodlePath = moodle_root_path();
        $effectiveKeycloakAccountUrl = keycloak_account_console_base_url();
        $effectiveKeycloakAdminUrl = keycloak_admin_console_base_url();

        $values = [
            'moodle_path' => $storedMoodlePath,
            'keycloak_account_console_base_url' => $storedKeycloakAccountUrl,
            'keycloak_admin_console_base_url' => $storedKeycloakAdminUrl,
            'moodle_webservice_url' => $storedMoodleWebserviceUrl,
            'moodle_webservice_token' => '',
        ];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $values['moodle_path'] = trim((string) ($_POST['moodle_path'] ?? ''));
            $values['keycloak_account_console_base_url'] = trim((string) ($_POST['keycloak_account_console_base_url'] ?? ''));
            $values['keycloak_admin_console_base_url'] = trim((string) ($_POST['keycloak_admin_console_base_url'] ?? ''));
            $values['moodle_webservice_url'] = trim((string) ($_POST['moodle_webservice_url'] ?? ''));
            $submittedToken = trim((string) ($_POST['moodle_webservice_token'] ?? ''));
            $shouldClearToken = isset($_POST['moodle_webservice_token_clear']);

            if ($values['moodle_path'] !== '' && !is_dir($values['moodle_path'])) {
                $errors['moodle_path'] = 'Das angegebene Verzeichnis wurde nicht gefunden.';
            }

            if ($values['keycloak_account_console_base_url'] !== ''
                && filter_var($values['keycloak_account_console_base_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['keycloak_account_console_base_url'] = 'Bitte eine gültige URL angeben oder das Feld leer lassen.';
            }

            if ($values['keycloak_admin_console_base_url'] !== ''
                && filter_var($values['keycloak_admin_console_base_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['keycloak_admin_console_base_url'] = 'Bitte eine gültige URL angeben oder das Feld leer lassen.';
            }

            if ($values['moodle_webservice_url'] !== ''
                && filter_var($values['moodle_webservice_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['moodle_webservice_url'] = 'Bitte gib eine gültige URL an oder lasse das Feld leer.';
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

                    if ($values['keycloak_admin_console_base_url'] !== $storedKeycloakAdminUrl) {
                        set_app_config(
                            'keycloak_admin_console_base_url',
                            $values['keycloak_admin_console_base_url'] === ''
                                ? null
                                : $values['keycloak_admin_console_base_url']
                        );
                        audit_log('konfiguration_keycloak_admin_url_geaendert', [
                            'feld' => 'keycloak_admin_console_base_url',
                            'alt' => $storedKeycloakAdminUrl,
                            'neu' => $values['keycloak_admin_console_base_url'],
                        ]);
                    }

                    if ($values['moodle_webservice_url'] !== $storedMoodleWebserviceUrl) {
                        set_app_config(
                            'moodle_webservice_url',
                            $values['moodle_webservice_url'] === '' ? null : $values['moodle_webservice_url']
                        );
                        audit_log('konfiguration_moodle_webservice_url_geaendert', [
                            'feld' => 'moodle_webservice_url',
                            'alt' => $storedMoodleWebserviceUrl,
                            'neu' => $values['moodle_webservice_url'],
                        ]);
                    }

                    if ($shouldClearToken && $storedMoodleWebserviceToken !== '') {
                        set_app_config('moodle_webservice_token', null);
                        audit_log('konfiguration_moodle_webservice_token_geloescht', [
                            'feld' => 'moodle_webservice_token',
                            'alt' => audit_log_mask_token($storedMoodleWebserviceToken),
                        ]);
                    } elseif ($submittedToken !== '') {
                        set_app_config('moodle_webservice_token', $submittedToken);
                        audit_log('konfiguration_moodle_webservice_token_gesetzt', [
                            'feld' => 'moodle_webservice_token',
                            'alt' => audit_log_mask_token($storedMoodleWebserviceToken),
                            'neu' => audit_log_mask_token($submittedToken),
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
        $courseService = new MoodleCourseService($statusPath);
        $webserviceStatus = $courseService->getWebserviceStatus();

        $storedMoodleWebserviceTokenMasked = $storedMoodleWebserviceToken !== ''
            ? audit_log_mask_token($storedMoodleWebserviceToken)
            : '';

        $content = render_template('settings.php', [
            'values' => $values,
            'errors' => $errors,
            'storedMoodlePath' => $storedMoodlePath,
            'effectiveMoodlePath' => $effectiveMoodlePath,
            'envOverride' => $envOverride,
            'storedKeycloakAccountUrl' => $storedKeycloakAccountUrl,
            'effectiveKeycloakAccountUrl' => $effectiveKeycloakAccountUrl,
            'keycloakAccountEnvOverride' => $keycloakAccountEnvOverride,
            'storedKeycloakAdminUrl' => $storedKeycloakAdminUrl,
            'effectiveKeycloakAdminUrl' => $effectiveKeycloakAdminUrl,
            'keycloakAdminEnvOverride' => $keycloakAdminEnvOverride,
            'moodleStatus' => $moodleStatus,
            'webserviceStatus' => $webserviceStatus,
            'storedMoodleWebserviceUrl' => $storedMoodleWebserviceUrl,
            'storedMoodleWebserviceTokenMasked' => $storedMoodleWebserviceTokenMasked,
            'webserviceUrlEnvOverride' => $webserviceUrlEnvOverride,
            'webserviceTokenEnvOverride' => $webserviceTokenEnvOverride,
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
