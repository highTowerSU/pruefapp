<?php

declare(strict_types=1);

use Ceneos\PhpBase\Integration\OAuthAuthorizationCodePkce;

/** Generic OAuth 2.0 Authorization Code flow with PKCE for AI providers. */
final class VocabularyOAuthService
{
    public static function callbackUrl(): string
    {
        return absolute_url_for('admin/konfiguration/ki-oauth/callback');
    }

    public static function begin(): string
    {
        return OAuthAuthorizationCodePkce::begin(self::configuration(), self::callbackUrl(), $_SESSION, 'vocabulary_oauth');
    }

    public static function complete(string $code, string $state): void
    {
        self::storeToken(OAuthAuthorizationCodePkce::complete(self::configuration(), self::callbackUrl(), $_SESSION, 'vocabulary_oauth', $code, $state));
    }

    public static function accessToken(): string
    {
        $token = trim((string) get_app_config('vocabulary_ai_oauth_access_token', ''));
        $expiresAt = (int) get_app_config('vocabulary_ai_oauth_expires_at', '0');
        if ($token !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) return $token;
        $refreshToken = trim((string) get_app_config('vocabulary_ai_oauth_refresh_token', ''));
        if ($refreshToken === '') throw new RuntimeException('Die OAuth-Verbindung ist abgelaufen. Bitte in der Konfiguration erneut verbinden.');
        self::storeToken(OAuthAuthorizationCodePkce::refresh(self::configuration(), $refreshToken));
        $token = trim((string) get_app_config('vocabulary_ai_oauth_access_token', ''));
        if ($token === '') throw new RuntimeException('Die OAuth-Verbindung konnte nicht erneuert werden.');
        return $token;
    }

    /** @return array{authorization_url:string,token_url:string,client_id:string,client_secret:string,scopes:string} */
    private static function configuration(): array
    {
        return [
            'authorization_url' => trim((string) get_app_config('vocabulary_ai_oauth_authorization_url', '')),
            'token_url' => trim((string) get_app_config('vocabulary_ai_oauth_token_url', '')),
            'client_id' => trim((string) get_app_config('vocabulary_ai_oauth_client_id', '')),
            'client_secret' => (string) get_app_config('vocabulary_ai_oauth_client_secret', ''),
            'scopes' => trim((string) get_app_config('vocabulary_ai_oauth_scopes', '')),
        ];
    }

    /** @param array<string,mixed> $token */
    private static function storeToken(array $token): void
    {
        set_app_config('vocabulary_ai_oauth_access_token', (string) $token['access_token']);
        if (trim((string) ($token['refresh_token'] ?? '')) !== '') set_app_config('vocabulary_ai_oauth_refresh_token', (string) $token['refresh_token']);
        set_app_config('vocabulary_ai_oauth_expires_at', (string) (time() + max(60, (int) ($token['expires_in'] ?? 3600))));
    }
}
