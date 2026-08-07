<?php

declare(strict_types=1);

use Ceneos\PhpBase\Integration\OpenAiCompatibleClient;
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
        foreach (['api_token', 'oauth_client_secret'] as $secret) {
            if (array_key_exists($secret, $values) && trim((string) $values[$secret]) !== '') $provider->{$secret} = (string) $values[$secret];
        }
        $provider->auth_mode = $provider->auth_mode === 'oauth' ? 'oauth' : 'token';
        $provider->header_name = trim((string) $provider->header_name) ?: 'Authorization';
        $provider->name = trim((string) $provider->name) ?: 'KI-Anbieter';
        $provider->updated_at = $now;
        if (empty($provider->created_at)) $provider->created_at = $now;
        R::store($provider);
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

    public static function accessToken(object $provider): string
    {
        if ((string) $provider->auth_mode === 'oauth') return VocabularyOAuthService::accessToken($provider);
        return trim((string) $provider->api_token);
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
