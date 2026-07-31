<?php

declare(strict_types=1);

use RedBeanPHP\R;

class SettingsController
{
    public static function general(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) {
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
            if (($_POST['action'] ?? '') === 'nuke_electrical') {
                if (trim((string) ($_POST['confirmation'] ?? '')) !== 'NUKE ELEKTRO') {
                    $_SESSION['fehlermeldung'] = 'Bitte zur Bestätigung exakt NUKE ELEKTRO eingeben.';
                } else {
                    $scope = (string) ($_POST['scope'] ?? 'devices');
                    $tables = match ($scope) {
                        'structure' => ['room', 'area', 'floor', 'building', 'site', 'customer'],
                        'all' => ['inspection', 'device', 'room', 'area', 'floor', 'building', 'site', 'customer'],
                        default => ['inspection', 'device'],
                    };
                    foreach ($tables as $table) {
                        R::wipe($table);
                    }
                    if ($scope !== 'structure') {
                        $reportRoot = dirname(__DIR__) . '/data/' . app_storage_namespace() . '/reports';
                        self::removeDirectoryContents($reportRoot);
                    }
                    audit_log('elektro_daten_nuke', ['umfang' => $scope, 'tabellen' => $tables]);
                    $_SESSION['meldung'] = $scope === 'all' ? 'Elektro-Prüfdaten, Geräte, Struktur und Berichte wurden gelöscht.' : ($scope === 'structure' ? 'Elektro-Struktur wurde gelöscht.' : 'Prüfungen, Geräte und Berichte wurden gelöscht.');
                }
                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
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
            'keycloakAccountFileOverride' => config_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL'),
            'keycloakAdminFileOverride' => config_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL'),
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

    private static function removeDirectoryContents(string $directory): void
    {
        if (!is_dir($directory)) return;
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot()) continue;
            $path = $entry->getPathname();
            if ($entry->isDir()) self::removeDirectoryContents($path);
            else @unlink($path);
        }
    }
}
