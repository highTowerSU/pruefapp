<?php

declare(strict_types=1);

use Ceneos\PhpBase\Integration\OAuthAuthorizationCodePkce;
use RedBeanPHP\R;

/** Generic OAuth 2.0 Authorization Code flow with PKCE for AI providers. */
final class VocabularyOAuthService
{
    public static function callbackUrl(): string
    {
        return absolute_url_for('admin/konfiguration/ki-oauth/callback');
    }

    public static function begin(object $provider): string
    {
        return OAuthAuthorizationCodePkce::begin(self::configuration($provider), self::callbackUrl(), $_SESSION, 'vocabulary_oauth_' . (int) $provider->id);
    }

    public static function complete(object $provider, string $code, string $state): void
    {
        self::storeToken($provider, OAuthAuthorizationCodePkce::complete(self::configuration($provider), self::callbackUrl(), $_SESSION, 'vocabulary_oauth_' . (int) $provider->id, $code, $state));
    }

    public static function accessToken(object $provider): string
    {
        $token = trim((string) $provider->oauth_access_token);
        $expiresAt = (int) $provider->oauth_expires_at;
        if ($token !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) return $token;
        $refreshToken = trim((string) $provider->oauth_refresh_token);
        if ($refreshToken === '') throw new RuntimeException('Die OAuth-Verbindung ist abgelaufen. Bitte in der Konfiguration erneut verbinden.');
        self::storeToken($provider, OAuthAuthorizationCodePkce::refresh(self::configuration($provider), $refreshToken));
        $token = trim((string) $provider->oauth_access_token);
        if ($token === '') throw new RuntimeException('Die OAuth-Verbindung konnte nicht erneuert werden.');
        return $token;
    }

    /** @return array{authorization_url:string,token_url:string,client_id:string,client_secret:string,scopes:string} */
    private static function configuration(object $provider): array
    {
        return [
            'authorization_url' => trim((string) $provider->oauth_authorization_url),
            'token_url' => trim((string) $provider->oauth_token_url),
            'client_id' => trim((string) $provider->oauth_client_id),
            'client_secret' => (string) $provider->oauth_client_secret,
            'scopes' => trim((string) $provider->oauth_scopes),
        ];
    }

    /** @param array<string,mixed> $token */
    private static function storeToken(object $provider, array $token): void
    {
        $provider->oauth_access_token = (string) $token['access_token'];
        if (trim((string) ($token['refresh_token'] ?? '')) !== '') $provider->oauth_refresh_token = (string) $token['refresh_token'];
        $provider->oauth_expires_at = time() + max(60, (int) ($token['expires_in'] ?? 3600));
        $provider->updated_at = date(DATE_ATOM);
        R::store($provider);
    }
}
