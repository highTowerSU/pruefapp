<?php

declare(strict_types=1);

/** Generic OAuth 2.0 Authorization Code flow with PKCE for AI providers. */
final class VocabularyOAuthService
{
    public static function callbackUrl(): string
    {
        return absolute_url_for('admin/konfiguration/ki-oauth/callback');
    }

    public static function begin(): string
    {
        self::requireConfigured();
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $_SESSION['vocabulary_oauth_state'] = $state;
        $_SESSION['vocabulary_oauth_verifier'] = $verifier;
        $_SESSION['vocabulary_oauth_started_at'] = time();
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) get_app_config('vocabulary_ai_oauth_client_id', ''),
            'redirect_uri' => self::callbackUrl(),
            'scope' => trim((string) get_app_config('vocabulary_ai_oauth_scopes', '')),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
        return rtrim((string) get_app_config('vocabulary_ai_oauth_authorization_url', ''), '?') . '?' . $query;
    }

    public static function complete(string $code, string $state): void
    {
        $expected = (string) ($_SESSION['vocabulary_oauth_state'] ?? '');
        $startedAt = (int) ($_SESSION['vocabulary_oauth_started_at'] ?? 0);
        $verifier = (string) ($_SESSION['vocabulary_oauth_verifier'] ?? '');
        unset($_SESSION['vocabulary_oauth_state'], $_SESSION['vocabulary_oauth_started_at'], $_SESSION['vocabulary_oauth_verifier']);
        if ($code === '' || $expected === '' || !hash_equals($expected, $state) || $startedAt < time() - 600 || $verifier === '') {
            throw new RuntimeException('Die OAuth-Anmeldung ist abgelaufen oder konnte nicht bestätigt werden.');
        }
        self::storeToken(self::requestToken(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::callbackUrl(), 'code_verifier' => $verifier]));
    }

    public static function accessToken(): string
    {
        $token = trim((string) get_app_config('vocabulary_ai_oauth_access_token', ''));
        $expiresAt = (int) get_app_config('vocabulary_ai_oauth_expires_at', '0');
        if ($token !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) return $token;
        $refreshToken = trim((string) get_app_config('vocabulary_ai_oauth_refresh_token', ''));
        if ($refreshToken === '') throw new RuntimeException('Die OAuth-Verbindung ist abgelaufen. Bitte in der Konfiguration erneut verbinden.');
        self::storeToken(self::requestToken(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]));
        $token = trim((string) get_app_config('vocabulary_ai_oauth_access_token', ''));
        if ($token === '') throw new RuntimeException('Die OAuth-Verbindung konnte nicht erneuert werden.');
        return $token;
    }

    private static function requireConfigured(): void
    {
        foreach (['vocabulary_ai_oauth_authorization_url', 'vocabulary_ai_oauth_token_url', 'vocabulary_ai_oauth_client_id'] as $key) {
            if (filter_var((string) get_app_config($key, ''), FILTER_VALIDATE_URL) === false && $key !== 'vocabulary_ai_oauth_client_id') throw new RuntimeException('OAuth ist noch nicht vollständig konfiguriert.');
            if (trim((string) get_app_config($key, '')) === '') throw new RuntimeException('OAuth ist noch nicht vollständig konfiguriert.');
        }
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    private static function requestToken(array $params): array
    {
        self::requireConfigured();
        $params['client_id'] = (string) get_app_config('vocabulary_ai_oauth_client_id', '');
        $clientSecret = trim((string) get_app_config('vocabulary_ai_oauth_client_secret', ''));
        if ($clientSecret !== '') $params['client_secret'] = $clientSecret;
        $ch = curl_init((string) get_app_config('vocabulary_ai_oauth_token_url', ''));
        if ($ch === false) throw new RuntimeException('OAuth-Tokenverbindung konnte nicht geöffnet werden.');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986)]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($body === false || $error !== '') throw new RuntimeException('OAuth-Netzwerkfehler: ' . ($error ?: 'unbekannt'));
        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || trim((string) ($decoded['access_token'] ?? '')) === '') throw new RuntimeException('OAuth-Token konnte nicht abgerufen werden (HTTP ' . $status . ').');
        return $decoded;
    }

    /** @param array<string,mixed> $token */
    private static function storeToken(array $token): void
    {
        set_app_config('vocabulary_ai_oauth_access_token', (string) $token['access_token']);
        if (trim((string) ($token['refresh_token'] ?? '')) !== '') set_app_config('vocabulary_ai_oauth_refresh_token', (string) $token['refresh_token']);
        set_app_config('vocabulary_ai_oauth_expires_at', (string) (time() + max(60, (int) ($token['expires_in'] ?? 3600))));
    }
}
