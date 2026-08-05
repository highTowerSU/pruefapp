<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Update\Updater;

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
            'benning_reimport_directory' => trim((string) (get_app_config('benning_reimport_directory', '') ?? '')),
            'benning_reports_directory' => trim((string) (get_app_config('benning_reports_directory', '') ?? '')),
            'auto_update_enabled' => get_app_config('auto_update_enabled', '1') === '1' ? '1' : '0',
            'cron_log_max_rows' => (string) max(500, (int) (get_app_config('cron_log_max_rows', '5000') ?? '5000')),
            'cron_log_max_bytes' => (string) max(262144, (int) (get_app_config('cron_log_max_bytes', (string) (5 * 1024 * 1024)) ?? (5 * 1024 * 1024))),
        ];
        $errors = [];
        $databaseWizard = null;
        $updateResult = null;
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $values['auto_update_enabled'] === '1') {
            try {
                $updateResult = Updater::updateIfNeeded(dirname(__DIR__), true);
            } catch (Throwable $exception) {
                $updateResult = ['ok' => [], 'skipped' => [], 'errors' => ['Automatische Aktualisierung: ' . $exception->getMessage()]];
            }
        }
        $skipGeneralSave = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'update_app') {
                $skipGeneralSave = true;
                try {
                    $updateResult = Updater::update(dirname(__DIR__), true);
                } catch (Throwable $exception) {
                    $updateResult = ['ok' => [], 'skipped' => [], 'errors' => [$exception->getMessage()]];
                }
            }
            if (($_POST['action'] ?? '') === 'db_wizard') {
                $skipGeneralSave = true;
                try {
                    $adminDsn = trim((string) ($_POST['admin_dsn'] ?? ''));
                    $adminUser = trim((string) ($_POST['admin_user'] ?? ''));
                    $adminPassword = (string) ($_POST['admin_password'] ?? '');
                    $databaseName = trim((string) ($_POST['database_name'] ?? 'pruefapp'));
                    $appUser = trim((string) ($_POST['app_user'] ?? 'pruefapp'));
                    if (!preg_match('/^mysql:\s*/i', $adminDsn) || $adminUser === '' || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName) || !preg_match('/^[A-Za-z0-9_]+$/', $appUser)) throw new RuntimeException('Bitte MySQL-DSN, Admin-Benutzer sowie gültige Datenbank- und Benutzernamen angeben.');
                    $pdo = new PDO($adminDsn, $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $quote = static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`';
                    $appPassword = bin2hex(random_bytes(24));
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quote($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $sqlUser = str_replace("'", "''", $appUser); $sqlPassword = str_replace("'", "''", $appPassword);
                    $pdo->exec("CREATE USER IF NOT EXISTS '{$sqlUser}'@'localhost' IDENTIFIED BY '{$sqlPassword}'");
                    $pdo->exec("GRANT ALL PRIVILEGES ON {$quote($databaseName)}.* TO '{$sqlUser}'@'localhost'");
                    $pdo->exec('FLUSH PRIVILEGES');
                    $databaseWizard = ['success' => true, 'message' => 'MySQL-Datenbank und App-Benutzer wurden eingerichtet. Das Admin-Passwort wurde nicht gespeichert.', 'snippet' => "'APP_DATABASE_DSN' => 'mysql:host=127.0.0.1;dbname={$databaseName};charset=utf8mb4',\n'APP_DATABASE_USER' => '{$appUser}',\n'APP_DATABASE_PASSWORD' => '{$appPassword}',"];
                } catch (Throwable $exception) {
                    $databaseWizard = ['success' => false, 'message' => $exception->getMessage()];
                }
            }
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
                        $reportRoot = app_data_root() . '/reports';
                        self::removeDirectoryContents($reportRoot);
                    }
                    audit_log('elektro_daten_nuke', ['umfang' => $scope, 'tabellen' => $tables]);
                    $_SESSION['meldung'] = $scope === 'all' ? 'Elektro-Prüfdaten, Geräte, Struktur und Berichte wurden gelöscht.' : ($scope === 'structure' ? 'Elektro-Struktur wurde gelöscht.' : 'Prüfungen, Geräte und Berichte wurden gelöscht.');
                }
                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
            if ($skipGeneralSave) {
                // The database wizard renders its one-time result in place.
            } else {
            $values['keycloak_account_console_base_url'] = trim((string) ($_POST['keycloak_account_console_base_url'] ?? ''));
            $values['keycloak_admin_console_base_url'] = trim((string) ($_POST['keycloak_admin_console_base_url'] ?? ''));
            $values['benning_reimport_directory'] = trim((string) ($_POST['benning_reimport_directory'] ?? ''));
            $values['benning_reports_directory'] = trim((string) ($_POST['benning_reports_directory'] ?? ''));
            $values['auto_update_enabled'] = isset($_POST['auto_update_enabled']) ? '1' : '0';
            $values['cron_log_max_rows'] = trim((string) ($_POST['cron_log_max_rows'] ?? '5000'));
            $values['cron_log_max_bytes'] = trim((string) ($_POST['cron_log_max_bytes'] ?? (5 * 1024 * 1024)));

            $cronRows = filter_var($values['cron_log_max_rows'], FILTER_VALIDATE_INT);
            $cronBytes = filter_var($values['cron_log_max_bytes'], FILTER_VALIDATE_INT);
            if ($cronRows === false || $cronRows < 500 || $cronRows > 1000000) $errors['cron_log_max_rows'] = 'Bitte zwischen 500 und 1.000.000 Einträge wählen.';
            if ($cronBytes === false || $cronBytes < 262144 || $cronBytes > 100 * 1024 * 1024) $errors['cron_log_max_bytes'] = 'Bitte zwischen 262.144 Bytes und 100 MB wählen.';

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
                set_app_config('benning_reimport_directory', $values['benning_reimport_directory'] === '' ? null : $values['benning_reimport_directory']);
                set_app_config('benning_reports_directory', $values['benning_reports_directory'] === '' ? null : $values['benning_reports_directory']);
                set_app_config('auto_update_enabled', $values['auto_update_enabled']);
                set_app_config('cron_log_max_rows', (string) $cronRows);
                set_app_config('cron_log_max_bytes', (string) $cronBytes);

                $_SESSION['meldung'] = 'Die Konfiguration wurde gespeichert.';

                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
            }
        }

        $content = render_template('settings.php', [
            'values' => $values,
            'errors' => $errors,
            'effectiveKeycloakAccountUrl' => keycloak_account_console_base_url(),
            'effectiveKeycloakAdminUrl' => keycloak_admin_console_base_url(),
            'keycloakAccountFileOverride' => config_value('APP_KEYCLOAK_ACCOUNT_CONSOLE_BASE_URL'),
            'keycloakAdminFileOverride' => config_value('APP_KEYCLOAK_ADMIN_CONSOLE_BASE_URL'),
            'databasePath' => app_database_path(),
            'databaseWizard' => $databaseWizard,
            'updateResult' => $updateResult,
            'migrationStatus' => self::migrationStatus(),
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

    private static function migrationStatus(): ?array
    {
        $path = app_data_root() . '/migration/benning-measurements-v3.done';
        if (!is_file($path)) return null;
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
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
