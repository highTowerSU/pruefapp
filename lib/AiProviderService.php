<?php

declare(strict_types=1);

use Ceneos\PhpBase\Integration\OpenAiCompatibleClient;
use Ceneos\PhpBase\Central\SecretStore;
use Ceneos\PhpBase\Config\Config;
use Ceneos\PhpBase\Tenant\TenantRepository;
use RedBeanPHP\R;

/** Shared provider profiles; tasks select a profile instead of duplicating credentials. */
final class AiProviderService
{
    private static bool $migratingLegacy = false;
    public static function ensureSchema(): void
    {
        $mysql = str_starts_with(strtolower((string) ($GLOBALS['pruefapp_database_path'] ?? '')), 'mysql:');
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        R::exec("CREATE TABLE IF NOT EXISTS aiprovider (id {$id}, name TEXT NOT NULL, base_url TEXT NOT NULL DEFAULT '', header_name TEXT NOT NULL DEFAULT 'Authorization', auth_mode TEXT NOT NULL DEFAULT 'token', api_token TEXT NOT NULL DEFAULT '', pricing_url TEXT NOT NULL DEFAULT '', oauth_authorization_url TEXT NOT NULL DEFAULT '', oauth_token_url TEXT NOT NULL DEFAULT '', oauth_client_id TEXT NOT NULL DEFAULT '', oauth_client_secret TEXT NOT NULL DEFAULT '', oauth_scopes TEXT NOT NULL DEFAULT '', oauth_access_token TEXT NOT NULL DEFAULT '', oauth_refresh_token TEXT NOT NULL DEFAULT '', oauth_expires_at INTEGER NOT NULL DEFAULT 0, models_json TEXT NOT NULL DEFAULT '[]', created_at TEXT NULL, updated_at TEXT NULL)");
        R::exec('CREATE INDEX IF NOT EXISTS idx_aiprovider_name ON aiprovider (name)');
        self::migrateLegacyProvider();
    }

    /** @return list<object> */
    public static function all(): array
    {
        self::ensureSchema();
        return array_values(R::findAll('aiprovider', ' ORDER BY LOWER(name), id '));
    }

    public static function find(int $id): ?object
    {
        self::ensureSchema();
        return $id > 0 ? R::load('aiprovider', $id) : null;
    }

    public static function selectedVocabularyProvider(): ?object
    {
        self::ensureSchema();
        $id = (int) get_app_config('vocabulary_ai_provider_id', '0');
        $provider = self::find($id);
        if ($provider !== null && (int) $provider->id > 0) return $provider;
        $providers = self::all();
        return $providers[0] ?? null;
    }

    /** @param array<string,string> $values */
    public static function save(int $id, array $values): object
    {
        self::ensureSchema();
        $provider = $id > 0 ? R::load('aiprovider', $id) : R::dispense('aiprovider');
        $now = date(DATE_ATOM);
        foreach (['name', 'base_url', 'header_name', 'auth_mode', 'pricing_url', 'oauth_authorization_url', 'oauth_token_url', 'oauth_client_id', 'oauth_scopes'] as $field) {
            if (array_key_exists($field, $values)) $provider->{$field} = trim((string) $values[$field]);
        }
        if (array_key_exists('oauth_client_secret', $values) && trim((string) $values['oauth_client_secret']) !== '') $provider->oauth_client_secret = (string) $values['oauth_client_secret'];
        $provider->auth_mode = $provider->auth_mode === 'oauth' ? 'oauth' : 'token';
        $provider->header_name = trim((string) $provider->header_name) ?: 'Authorization';
        $provider->name = trim((string) $provider->name) ?: 'KI-Anbieter';
        $provider->updated_at = $now;
        if (empty($provider->created_at)) $provider->created_at = $now;
        R::store($provider);
        $token = trim((string) ($values['api_token'] ?? ''));
        if ($token !== '') {
            (new SecretStore())->set(self::tokenScope($provider), self::tokenName($provider), $token);
            $provider->api_token = '';
            R::store($provider);
        } elseif (trim((string) ($provider->api_token ?? '')) !== '' && Config::string('APP_CENEOS_SECRETS_KEY') !== null) {
            $store = new SecretStore();
            if (!$store->has(self::tokenScope($provider), self::tokenName($provider))) {
                $store->set(self::tokenScope($provider), self::tokenName($provider), (string) $provider->api_token);
            }
            $provider->api_token = '';
            R::store($provider);
        }
        return $provider;
    }

    /** @return list<string> */
    public static function models(object $provider): array
    {
        $decoded = json_decode((string) ($provider->models_json ?? '[]'), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public static function refreshModels(object $provider): array
    {
        $models = (new OpenAiCompatibleClient((string) $provider->base_url, self::accessToken($provider), (string) $provider->header_name, 15))->models();
        $provider->models_json = json_encode($models, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $provider->updated_at = date(DATE_ATOM);
        R::store($provider);
        return $models;
    }

    /** @return array{model:string,content:string} */
    public static function diagnose(object $provider, string $model): array
    {
        $model = trim($model);
        if ($model === '') {
            throw new InvalidArgumentException('Bitte zuerst ein Modell für die Stammdatenprüfung auswählen.');
        }
        $header = (string) $provider->auth_mode === 'oauth'
            ? 'Authorization'
            : (trim((string) $provider->header_name) ?: 'Authorization');
        $response = (new OpenAiCompatibleClient(
            (string) $provider->base_url,
            self::accessToken($provider),
            $header,
            25
        ))->chatCompletions([
            'model' => $model,
            'temperature' => 0,
            'max_tokens' => 24,
            'messages' => [[
                'role' => 'user',
                'content' => 'Antworte ausschließlich mit OK.',
            ]],
        ]);
        $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
        return [
            'model' => trim((string) ($response['model'] ?? $model)) ?: $model,
            'content' => mb_strimwidth($content, 0, 240, '…', 'UTF-8'),
        ];
    }

    public static function accessToken(object $provider): string
    {
        if ((string) $provider->auth_mode === 'oauth') return VocabularyOAuthService::accessToken($provider);
        if (self::isInnoGpt($provider)) {
            $store = new SecretStore();
            try {
                $effective = $store->innoGptApiKey(app_storage_namespace(), self::currentTenantId());
                if ($effective !== null && $effective !== '') {
                    if (trim((string) ($provider->api_token ?? '')) !== '') {
                        $provider->api_token = '';
                        R::store($provider);
                    }
                    return $effective;
                }
            } catch (Throwable $exception) {
                if (Config::string('APP_CENEOS_SECRETS_KEY') !== null) throw $exception;
            }
            $configured = trim((string) Config::string('APP_INNOGPT_API_KEY', ''));
            $keyFile = trim((string) Config::string('APP_INNOGPT_API_KEY_FILE', ''));
            if ($configured === '' && $keyFile !== '' && is_readable($keyFile)) $configured = trim((string) file_get_contents($keyFile));
            if ($configured !== '') return $configured;
            $legacy = trim((string) ($provider->api_token ?? ''));
            if ($legacy !== '' && Config::string('APP_CENEOS_SECRETS_KEY') !== null) {
                $store->set(SecretStore::innoGptAppScope(app_storage_namespace()), 'innogpt_api_key', $legacy);
                $provider->api_token = '';
                R::store($provider);
                return $legacy;
            }
            if ($legacy !== '') return $legacy;
            throw new RuntimeException('innoGPT-Schlüssel fehlt. Bitte unter /admin/base global, für diese App oder für den Mandanten hinterlegen.');
        }
        try {
            $secret = (new SecretStore())->get(self::tokenScope($provider), self::tokenName($provider));
            if ($secret !== null && $secret !== '') {
                if (trim((string) ($provider->api_token ?? '')) !== '') {
                    $provider->api_token = '';
                    R::store($provider);
                }
                return $secret;
            }
        } catch (Throwable $exception) {
            if (Config::string('APP_CENEOS_SECRETS_KEY') !== null) throw $exception;
        }
        $legacy = trim((string) ($provider->api_token ?? ''));
        if ($legacy !== '' && Config::string('APP_CENEOS_SECRETS_KEY') !== null) {
            (new SecretStore())->set(self::tokenScope($provider), self::tokenName($provider), $legacy);
            $provider->api_token = '';
            R::store($provider);
            return $legacy;
        }
        return $legacy;
    }

    public static function tokenConfigured(object $provider): bool
    {
        if ((string) ($provider->auth_mode ?? '') === 'oauth') return trim((string) ($provider->oauth_access_token ?? '')) !== '';
        try {
            if (self::isInnoGpt($provider)) {
                $store = new SecretStore();
                $appScope = SecretStore::innoGptAppScope(app_storage_namespace());
                $tenantId = self::currentTenantId();
                if (($tenantId > 0 && $store->has(SecretStore::innoGptTenantScope(app_storage_namespace(), $tenantId), 'innogpt_api_key'))
                    || $store->has($appScope, 'innogpt_api_key')
                    || $store->has('global', 'innogpt_api_key')) return true;
                $keyFile = trim((string) Config::string('APP_INNOGPT_API_KEY_FILE', ''));
                if (Config::string('APP_INNOGPT_API_KEY') !== null || ($keyFile !== '' && is_readable($keyFile) && trim((string) file_get_contents($keyFile)) !== '')) return true;
            }
            if ((new SecretStore())->has(self::tokenScope($provider), self::tokenName($provider))) return true;
        } catch (Throwable) {
            // The legacy row remains the migration fallback until the key exists.
        }
        return trim((string) ($provider->api_token ?? '')) !== '';
    }

    private static function isInnoGpt(object $provider): bool
    {
        $identity = mb_strtolower((string) ($provider->name ?? '') . ' ' . (string) ($provider->base_url ?? ''), 'UTF-8');
        return str_contains($identity, 'innogpt') || str_contains($identity, 'inno-gpt');
    }

    private static function tokenScope(object $provider): string
    {
        return self::isInnoGpt($provider) ? SecretStore::innoGptAppScope(app_storage_namespace()) : 'app:' . app_storage_namespace() . ':ai-provider:' . (int) ($provider->id ?? 0);
    }

    private static function currentTenantId(): int
    {
        if (!function_exists('get_branding')) return 0;
        try { return max(0, (int) (get_branding()['company_id'] ?? 0)); }
        catch (Throwable) { return 0; }
    }

    private static function tokenName(object $provider): string
    {
        return self::isInnoGpt($provider) ? 'innogpt_api_key' : 'api_token';
    }

    public static function pricingUrl(object $provider): string
    {
        $configured = trim((string) ($provider->pricing_url ?? ''));
        if ($configured !== '') return $configured;
        $identity = mb_strtolower((string) ($provider->name ?? '') . ' ' . (string) ($provider->base_url ?? ''), 'UTF-8');
        if (str_contains($identity, 'innogpt')) return 'https://docs.innogpt.de/en/help/articles/8468742-usage-limits-andamp-fair-use-policy';
        if (str_contains($identity, 'ionos')) return 'https://docs.ionos.com/cloud/ai/ai-model-hub/ai-model-hub';
        if (str_contains($identity, 'ovh')) return 'https://help.ovhcloud.com/csm/en-au-public-cloud-ai-endpoints-code-assistant?id=kb_article_view&sysparm_article=KB0067282';
        return '';
    }

    private static function migrateLegacyProvider(): void
    {
        if (self::$migratingLegacy || R::count('aiprovider') > 0 || get_app_config('vocabulary_ai_base_url', '') === '') return;
        self::$migratingLegacy = true;
        try {
        $provider = self::save(0, [
            'name' => 'Bisheriger KI-Anbieter',
            'base_url' => (string) get_app_config('vocabulary_ai_base_url', ''),
            'header_name' => (string) get_app_config('vocabulary_ai_header', 'Authorization'),
            'auth_mode' => (string) get_app_config('vocabulary_ai_auth_mode', 'token'),
            'api_token' => (string) get_app_config('vocabulary_ai_token', ''),
            'oauth_authorization_url' => (string) get_app_config('vocabulary_ai_oauth_authorization_url', ''),
            'oauth_token_url' => (string) get_app_config('vocabulary_ai_oauth_token_url', ''),
            'oauth_client_id' => (string) get_app_config('vocabulary_ai_oauth_client_id', ''),
            'oauth_client_secret' => (string) get_app_config('vocabulary_ai_oauth_client_secret', ''),
            'oauth_scopes' => (string) get_app_config('vocabulary_ai_oauth_scopes', ''),
        ]);
        $provider->oauth_access_token = (string) get_app_config('vocabulary_ai_oauth_access_token', '');
        $provider->oauth_refresh_token = (string) get_app_config('vocabulary_ai_oauth_refresh_token', '');
        $provider->oauth_expires_at = (int) get_app_config('vocabulary_ai_oauth_expires_at', '0');
        R::store($provider);
        set_app_config('vocabulary_ai_provider_id', (string) $provider->id);
        } finally {
            self::$migratingLegacy = false;
        }
    }
}
