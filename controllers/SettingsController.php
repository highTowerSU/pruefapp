<?php

declare(strict_types=1);

use RedBeanPHP\R;
use Ceneos\PhpBase\Update\Updater;

class SettingsController
{
    public static function aiProvider(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $id = max(0, (int) ($_REQUEST['provider_id'] ?? get_app_config('vocabulary_ai_provider_id', '0')));
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $action = (string) ($_POST['action'] ?? 'save');
                $provider = AiProviderService::save($id, [
                    'name' => (string) ($_POST['name'] ?? ''), 'base_url' => (string) ($_POST['base_url'] ?? ''),
                    'header_name' => (string) ($_POST['header_name'] ?? 'Authorization'), 'auth_mode' => (string) ($_POST['auth_mode'] ?? 'token'),
                    'api_token' => (string) ($_POST['api_token'] ?? ''), 'pricing_url' => (string) ($_POST['pricing_url'] ?? ''),
                    'oauth_authorization_url' => (string) ($_POST['oauth_authorization_url'] ?? ''), 'oauth_token_url' => (string) ($_POST['oauth_token_url'] ?? ''),
                    'oauth_client_id' => (string) ($_POST['oauth_client_id'] ?? ''), 'oauth_client_secret' => (string) ($_POST['oauth_client_secret'] ?? ''), 'oauth_scopes' => (string) ($_POST['oauth_scopes'] ?? ''),
                ]);
                if (filter_var((string) $provider->base_url, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Bitte eine gültige OpenAI-kompatible Basis-URL angeben.');
                if ($action === 'test') {
                    $models = AiProviderService::refreshModels($provider);
                    $message = $models === [] ? 'Verbindung hergestellt; der Anbieter lieferte keine Modellliste. Das Modell kann manuell eingetragen werden.' : count($models) . ' Modelle geladen.';
                } elseif ($action === 'oauth') {
                    $_SESSION['vocabulary_oauth_provider_id'] = (int) $provider->id;
                    return [303, ['Location' => VocabularyOAuthService::begin($provider)], ''];
                } else {
                    set_app_config('vocabulary_ai_provider_id', (string) $provider->id);
                    set_app_config('vocabulary_ai_model', trim((string) ($_POST['vocabulary_ai_model'] ?? '')) ?: null);
                    set_app_config('vocabulary_ai_enabled', isset($_POST['vocabulary_ai_enabled']) ? '1' : '0');
                    $message = 'KI-Provider und Stammdatenprüfung wurden gespeichert.';
                }
                $id = (int) $provider->id;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        $providers = AiProviderService::all();
        $provider = AiProviderService::find($id);
        if ($provider === null || (int) $provider->id === 0) $provider = (object) ['id' => 0, 'name' => '', 'base_url' => '', 'header_name' => 'Authorization', 'auth_mode' => 'token', 'api_token' => '', 'pricing_url' => '', 'oauth_authorization_url' => '', 'oauth_token_url' => '', 'oauth_client_id' => '', 'oauth_client_secret' => '', 'oauth_scopes' => '', 'oauth_access_token' => ''];
        $content = render_template('settings_ai_provider.php', [
            'providers' => $providers, 'provider' => $provider, 'models' => (int) $provider->id > 0 ? AiProviderService::models($provider) : [], 'pricingUrl' => (int) $provider->id > 0 ? AiProviderService::pricingUrl($provider) : '',
            'enabled' => get_app_config('vocabulary_ai_enabled', '0') === '1', 'selectedProviderId' => (int) get_app_config('vocabulary_ai_provider_id', '0'),
            'selectedModel' => (string) get_app_config('vocabulary_ai_model', ''), 'message' => $message, 'error' => $error,
        ]);
        if ($isHx) return [200, [], $content];
        return [303, ['Location' => url_for('admin/konfiguration#settings-ai-panel')], ''];
    }

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
            'audit_log_max_rows' => (string) max(1000, (int) (get_app_config('audit_log_max_rows', '250000') ?? '250000')),
            'cron_time_budget_seconds' => (string) max(30, (int) (get_app_config('cron_time_budget_seconds', '120') ?? '120')),
            'cron_job_slice_seconds' => (string) max(5, (int) (get_app_config('cron_job_slice_seconds', '30') ?? '30')),
            'cron_job_lease_seconds' => (string) max(30, (int) (get_app_config('cron_job_lease_seconds', '180') ?? '180')),
            'background_history_days' => (string) max(7, (int) (get_app_config('background_history_days', '180') ?? '180')),
            'vocabulary_ai_enabled' => get_app_config('vocabulary_ai_enabled', '0') === '1' ? '1' : '0',
            'vocabulary_ai_base_url' => trim((string) (get_app_config('vocabulary_ai_base_url', '') ?? '')),
            'vocabulary_ai_header' => trim((string) (get_app_config('vocabulary_ai_header', 'Authorization') ?? 'Authorization')),
            'vocabulary_ai_model' => trim((string) (get_app_config('vocabulary_ai_model', '') ?? '')),
            'vocabulary_ai_auth_mode' => get_app_config('vocabulary_ai_auth_mode', 'token') === 'oauth' ? 'oauth' : 'token',
            'vocabulary_ai_oauth_authorization_url' => trim((string) (get_app_config('vocabulary_ai_oauth_authorization_url', '') ?? '')),
            'vocabulary_ai_oauth_token_url' => trim((string) (get_app_config('vocabulary_ai_oauth_token_url', '') ?? '')),
            'vocabulary_ai_oauth_client_id' => trim((string) (get_app_config('vocabulary_ai_oauth_client_id', '') ?? '')),
            'vocabulary_ai_oauth_scopes' => trim((string) (get_app_config('vocabulary_ai_oauth_scopes', '') ?? '')),
        ];
        $errors = [];
        $databaseWizard = null;
        $updateResult = null;
        $apiDebugSecretOnce = (string) ($_SESSION['api_debug_secret_once'] ?? '');
        $vocabularyAiModels = (array) ($_SESSION['vocabulary_ai_models'] ?? []);
        unset($_SESSION['vocabulary_ai_models']);
        unset($_SESSION['api_debug_secret_once']);
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $values['auto_update_enabled'] === '1') {
            try {
                $updateResult = Updater::updateIfNeeded(dirname(__DIR__), true);
            } catch (Throwable $exception) {
                $updateResult = ['ok' => [], 'skipped' => [], 'errors' => ['Automatische Aktualisierung: ' . $exception->getMessage()]];
            }
        }
        $skipGeneralSave = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'generate_api_debug_secret') {
                $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                set_app_config('api_debug_secret', $secret);
                $_SESSION['api_debug_secret_once'] = $secret;
                $_SESSION['meldung'] = 'API-Debug-Secret wurde erzeugt. Es wird nur jetzt einmal angezeigt.';
                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
            if (($_POST['action'] ?? '') === 'disable_api_debug_secret') {
                set_app_config('api_debug_secret', null);
                $_SESSION['meldung'] = 'API-Debug-Zugang wurde deaktiviert.';
                return [303, ['Location' => url_for('admin/konfiguration')], ''];
            }
            if (($_POST['action'] ?? '') === 'test_vocabulary_ai') {
                try {
                    $baseUrl = trim((string) ($_POST['vocabulary_ai_base_url'] ?? ''));
                    $header = trim((string) ($_POST['vocabulary_ai_header'] ?? 'Authorization')) ?: 'Authorization';
                    $token = (string) ($_POST['vocabulary_ai_token'] ?? '');
                    if (get_app_config('vocabulary_ai_auth_mode', 'token') === 'oauth') {
                        $header = 'Authorization';
                        $provider = AiProviderService::selectedVocabularyProvider();
                        $token = $provider ? VocabularyOAuthService::accessToken($provider) : '';
                    } elseif ($token === '') {
                        $token = (string) get_app_config('vocabulary_ai_token', '');
                    }
                    $_SESSION['vocabulary_ai_models'] = DeviceVocabularyService::availableModels($baseUrl, $token, $header);
                    set_app_config('vocabulary_ai_base_url', rtrim($baseUrl, '/'));
                    set_app_config('vocabulary_ai_header', $header);
                    if (trim((string) ($_POST['vocabulary_ai_token'] ?? '')) !== '') set_app_config('vocabulary_ai_token', (string) $_POST['vocabulary_ai_token']);
                    $_SESSION['meldung'] = $_SESSION['vocabulary_ai_models'] === [] ? 'Verbindung hergestellt; der Anbieter hat keine Modellliste geliefert. Das Modell kann manuell eingetragen werden.' : count($_SESSION['vocabulary_ai_models']) . ' Modelle gefunden.';
                } catch (Throwable $exception) {
                    $_SESSION['fehlermeldung'] = 'KI-Verbindung fehlgeschlagen: ' . $exception->getMessage();
                }
                return [303, ['Location' => url_for('admin/konfiguration#settings-ai-panel')], ''];
            }
            if (($_POST['action'] ?? '') === 'begin_vocabulary_oauth') {
                try {
                    $provider = AiProviderService::selectedVocabularyProvider();
                    if ($provider === null) throw new RuntimeException('Bitte zuerst einen KI-Provider speichern.');
                    $_SESSION['vocabulary_oauth_provider_id'] = (int) $provider->id;
                    return [303, ['Location' => VocabularyOAuthService::begin($provider)], ''];
                } catch (Throwable $exception) {
                    $_SESSION['fehlermeldung'] = 'OAuth-Verbindung konnte nicht gestartet werden: ' . $exception->getMessage();
                    return [303, ['Location' => url_for('admin/konfiguration#settings-ai-panel')], ''];
                }
            }
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
            $values['audit_log_max_rows'] = trim((string) ($_POST['audit_log_max_rows'] ?? '250000'));
            $values['cron_time_budget_seconds'] = trim((string) ($_POST['cron_time_budget_seconds'] ?? '120'));
            $values['cron_job_slice_seconds'] = trim((string) ($_POST['cron_job_slice_seconds'] ?? '30'));
            $values['cron_job_lease_seconds'] = trim((string) ($_POST['cron_job_lease_seconds'] ?? '180'));
            $values['background_history_days'] = trim((string) ($_POST['background_history_days'] ?? '180'));
            $legacyAiForm = array_key_exists('vocabulary_ai_enabled', $_POST) || array_key_exists('vocabulary_ai_base_url', $_POST);
            $values['vocabulary_ai_enabled'] = $legacyAiForm ? (isset($_POST['vocabulary_ai_enabled']) ? '1' : '0') : $values['vocabulary_ai_enabled'];
            $values['vocabulary_ai_base_url'] = trim((string) ($_POST['vocabulary_ai_base_url'] ?? $values['vocabulary_ai_base_url']));
            $values['vocabulary_ai_header'] = trim((string) ($_POST['vocabulary_ai_header'] ?? $values['vocabulary_ai_header'])) ?: 'Authorization';
            $values['vocabulary_ai_model'] = trim((string) ($_POST['vocabulary_ai_model'] ?? $values['vocabulary_ai_model']));
            $values['vocabulary_ai_auth_mode'] = ($_POST['vocabulary_ai_auth_mode'] ?? $values['vocabulary_ai_auth_mode']) === 'oauth' ? 'oauth' : 'token';
            $values['vocabulary_ai_oauth_authorization_url'] = trim((string) ($_POST['vocabulary_ai_oauth_authorization_url'] ?? $values['vocabulary_ai_oauth_authorization_url']));
            $values['vocabulary_ai_oauth_token_url'] = trim((string) ($_POST['vocabulary_ai_oauth_token_url'] ?? $values['vocabulary_ai_oauth_token_url']));
            $values['vocabulary_ai_oauth_client_id'] = trim((string) ($_POST['vocabulary_ai_oauth_client_id'] ?? $values['vocabulary_ai_oauth_client_id']));
            $values['vocabulary_ai_oauth_scopes'] = trim((string) ($_POST['vocabulary_ai_oauth_scopes'] ?? $values['vocabulary_ai_oauth_scopes']));

            $cronRows = filter_var($values['cron_log_max_rows'], FILTER_VALIDATE_INT);
            $cronBytes = filter_var($values['cron_log_max_bytes'], FILTER_VALIDATE_INT);
            $auditRows = filter_var($values['audit_log_max_rows'], FILTER_VALIDATE_INT);
            if ($cronRows === false || $cronRows < 500 || $cronRows > 1000000) $errors['cron_log_max_rows'] = 'Bitte zwischen 500 und 1.000.000 Einträge wählen.';
            if ($cronBytes === false || $cronBytes < 262144 || $cronBytes > 100 * 1024 * 1024) $errors['cron_log_max_bytes'] = 'Bitte zwischen 262.144 Bytes und 100 MB wählen.';
            if ($auditRows === false || $auditRows < 1000 || $auditRows > 5000000) $errors['audit_log_max_rows'] = 'Bitte zwischen 1.000 und 5.000.000 Ereignissen wählen.';
            $cronBudget = filter_var($values['cron_time_budget_seconds'], FILTER_VALIDATE_INT);
            $cronSlice = filter_var($values['cron_job_slice_seconds'], FILTER_VALIDATE_INT);
            $cronLease = filter_var($values['cron_job_lease_seconds'], FILTER_VALIDATE_INT);
            $historyDays = filter_var($values['background_history_days'], FILTER_VALIDATE_INT);
            if ($cronBudget === false || $cronBudget < 30 || $cronBudget > 900) $errors['cron_time_budget_seconds'] = 'Bitte zwischen 30 und 900 Sekunden wählen.';
            if ($cronSlice === false || $cronSlice < 5 || $cronSlice > 120) $errors['cron_job_slice_seconds'] = 'Bitte zwischen 5 und 120 Sekunden wählen.';
            if ($cronLease === false || $cronLease < 30 || $cronLease > 900) $errors['cron_job_lease_seconds'] = 'Bitte zwischen 30 und 900 Sekunden wählen.';
            if ($historyDays === false || $historyDays < 7 || $historyDays > 3650) $errors['background_history_days'] = 'Bitte zwischen 7 und 3.650 Tagen wählen.';

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
            if ($values['vocabulary_ai_enabled'] === '1') {
                if (filter_var($values['vocabulary_ai_base_url'], FILTER_VALIDATE_URL) === false) $errors['vocabulary_ai_base_url'] = 'Bitte eine gültige OpenAI-kompatible Basis-URL angeben.';
                if ($values['vocabulary_ai_model'] === '') $errors['vocabulary_ai_model'] = 'Bitte ein KI-Modell auswählen oder eintragen.';
                if ($values['vocabulary_ai_auth_mode'] === 'token' && (string) ($_POST['vocabulary_ai_token'] ?? '') === '' && trim((string) get_app_config('vocabulary_ai_token', '')) === '') $errors['vocabulary_ai_token'] = 'Bitte ein Token hinterlegen.';
                if ($values['vocabulary_ai_auth_mode'] === 'oauth') {
                    foreach (['vocabulary_ai_oauth_authorization_url', 'vocabulary_ai_oauth_token_url'] as $key) if (filter_var($values[$key], FILTER_VALIDATE_URL) === false) $errors[$key] = 'Bitte eine gültige OAuth-URL angeben.';
                    if ($values['vocabulary_ai_oauth_client_id'] === '') $errors['vocabulary_ai_oauth_client_id'] = 'Bitte eine OAuth-Client-ID angeben.';
                }
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
                set_app_config('audit_log_max_rows', (string) $auditRows);
                set_app_config('cron_time_budget_seconds', (string) $cronBudget);
                set_app_config('cron_job_slice_seconds', (string) $cronSlice);
                set_app_config('cron_job_lease_seconds', (string) $cronLease);
                set_app_config('background_history_days', (string) $historyDays);
                set_app_config('vocabulary_ai_enabled', $values['vocabulary_ai_enabled']);
                set_app_config('vocabulary_ai_base_url', $values['vocabulary_ai_base_url'] === '' ? null : rtrim($values['vocabulary_ai_base_url'], '/'));
                set_app_config('vocabulary_ai_header', $values['vocabulary_ai_header']);
                set_app_config('vocabulary_ai_model', $values['vocabulary_ai_model'] === '' ? null : $values['vocabulary_ai_model']);
                set_app_config('vocabulary_ai_auth_mode', $values['vocabulary_ai_auth_mode']);
                set_app_config('vocabulary_ai_oauth_authorization_url', $values['vocabulary_ai_oauth_authorization_url'] === '' ? null : $values['vocabulary_ai_oauth_authorization_url']);
                set_app_config('vocabulary_ai_oauth_token_url', $values['vocabulary_ai_oauth_token_url'] === '' ? null : $values['vocabulary_ai_oauth_token_url']);
                set_app_config('vocabulary_ai_oauth_client_id', $values['vocabulary_ai_oauth_client_id'] === '' ? null : $values['vocabulary_ai_oauth_client_id']);
                set_app_config('vocabulary_ai_oauth_scopes', $values['vocabulary_ai_oauth_scopes'] === '' ? null : $values['vocabulary_ai_oauth_scopes']);
                if (trim((string) ($_POST['vocabulary_ai_token'] ?? '')) !== '') set_app_config('vocabulary_ai_token', (string) $_POST['vocabulary_ai_token']);
                if (trim((string) ($_POST['vocabulary_ai_oauth_client_secret'] ?? '')) !== '') set_app_config('vocabulary_ai_oauth_client_secret', (string) $_POST['vocabulary_ai_oauth_client_secret']);

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
            'apiDebugSecretEnabled' => trim((string) get_app_config('api_debug_secret', '')) !== '',
            'apiDebugSecretOnce' => $apiDebugSecretOnce,
            'vocabularyAiModels' => $vocabularyAiModels,
            'vocabularyAiTokenConfigured' => trim((string) get_app_config('vocabulary_ai_token', '')) !== '',
            'vocabularyAiOAuthConnected' => trim((string) get_app_config('vocabulary_ai_oauth_access_token', '')) !== '',
            'vocabularyAiOAuthSecretConfigured' => trim((string) get_app_config('vocabulary_ai_oauth_client_secret', '')) !== '',
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

    public static function vocabularyOAuthCallback(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        try {
            if (!empty($_GET['error'])) throw new RuntimeException((string) ($_GET['error_description'] ?? $_GET['error']));
            $provider = AiProviderService::find((int) ($_SESSION['vocabulary_oauth_provider_id'] ?? 0));
            unset($_SESSION['vocabulary_oauth_provider_id']);
            if ($provider === null || (int) $provider->id === 0) throw new RuntimeException('Der zugehörige KI-Provider wurde nicht gefunden.');
            VocabularyOAuthService::complete($provider, trim((string) ($_GET['code'] ?? '')), trim((string) ($_GET['state'] ?? '')));
            $_SESSION['meldung'] = 'OAuth-Verbindung wurde hergestellt. Der Zugriffstoken wird bei Bedarf automatisch erneuert.';
        } catch (Throwable $exception) {
            $_SESSION['fehlermeldung'] = 'OAuth-Verbindung fehlgeschlagen: ' . $exception->getMessage();
        }
        return [303, ['Location' => url_for('admin/konfiguration#settings-ai-panel')], ''];
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
