<?php

declare(strict_types=1);

class SettingsController
{
    public static function general(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $storedKeycloakAccountUrl = trim((string) (get_app_config('keycloak_account_console_base_url') ?? ''));
        $storedKeycloakAdminUrl = trim((string) (get_app_config('keycloak_admin_console_base_url') ?? ''));

        $values = [
            'keycloak_account_console_base_url' => $storedKeycloakAccountUrl,
            'keycloak_admin_console_base_url' => $storedKeycloakAdminUrl,
        ];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $values['keycloak_account_console_base_url'] = trim((string) ($_POST['keycloak_account_console_base_url'] ?? ''));
            $values['keycloak_admin_console_base_url'] = trim((string) ($_POST['keycloak_admin_console_base_url'] ?? ''));

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

            if ($errors === []) {
                set_app_config(
                    'keycloak_account_console_base_url',
                    $values['keycloak_account_console_base_url'] === '' ? null : $values['keycloak_account_console_base_url']
                );
                set_app_config(
                    'keycloak_admin_console_base_url',
                    $values['keycloak_admin_console_base_url'] === '' ? null : $values['keycloak_admin_console_base_url']
                );

                $_SESSION['meldung'] = 'Die Konfiguration wurde gespeichert.';

                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
        }

        $content = render_template('settings.php', [
            'values' => $values,
            'errors' => $errors,
            'effectiveKeycloakAccountUrl' => keycloak_account_console_base_url(),
            'effectiveKeycloakAdminUrl' => keycloak_admin_console_base_url(),
            'keycloakAccountEnvOverride' => env_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL'),
            'keycloakAdminEnvOverride' => env_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL'),
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
