<?php
declare(strict_types=1);

final class V2TokenService
{
    private V2KeyManager $keys;
    private int $clockSkew;

    public function __construct(V2KeyManager $keys, int $clockSkew = 300)
    {
        $this->keys = $keys;
        $this->clockSkew = max(0, $clockSkew);
    }

    public static function b64urlEncode(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

    public static function b64urlDecode(string $encoded): string
    {
        if ($encoded === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $encoded)) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        $padding = (4 - (strlen($encoded) % 4)) % 4;
        $decoded = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        return $decoded;
    }

    public function issue(array $claims, int $ttl): string
    {
        foreach (['app_id','license_id','device_id','device_credential_id','device_key_fingerprint'] as $name) {
            if (!isset($claims[$name]) || (string)$claims[$name] === '') { throw new InvalidArgumentException('Missing token claim: ' . $name); }
        }
        $now = time();
        $payload = array_merge($claims, [
            'iss' => 'licora', 'aud' => (string)$claims['app_id'], 'iat' => $now, 'nbf' => $now - 5,
            'exp' => $now + max(60, $ttl), 'jti' => bin2hex(random_bytes(16)), 'token_version' => 2,
        ]);
        $header = ['typ' => 'LICORA-V2', 'alg' => 'RS256', 'kid' => $this->keys->keyId()];
        $h = self::b64urlEncode(self::json($header));
        $p = self::b64urlEncode(self::json($payload));
        $input = $h . '.' . $p;
        $signature = '';
        if (!openssl_sign($input, $signature, $this->keys->requirePrivateKey(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign API v2 access token.');
        }
        return $input . '.' . self::b64urlEncode($signature);
    }

    public function verify(string $token, ?string $expectedAudience = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        [$eh,$ep,$es] = $parts;
        $header = self::decodeJson(self::b64urlDecode($eh));
        $payload = self::decodeJson(self::b64urlDecode($ep));
        if (($header['typ'] ?? null) !== 'LICORA-V2' || ($header['alg'] ?? null) !== 'RS256' || ($header['kid'] ?? null) !== $this->keys->keyId()) {
            throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401);
        }
        $verified = openssl_verify($eh . '.' . $ep, self::b64urlDecode($es), $this->keys->requirePublicKey(), OPENSSL_ALGO_SHA256);
        if ($verified !== 1) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        foreach (['iss','aud','app_id','license_id','device_id','device_credential_id','device_key_fingerprint','iat','nbf','exp','jti','token_version'] as $claim) {
            if (!array_key_exists($claim, $payload)) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        }
        if ($payload['iss'] !== 'licora' || (int)$payload['token_version'] !== 2) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        if (!is_string($payload['aud']) || !is_string($payload['app_id']) || !hash_equals($payload['aud'], $payload['app_id'])) {
            throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401);
        }
        if ($expectedAudience !== null && (!hash_equals($expectedAudience, (string)$payload['aud']) || !hash_equals($expectedAudience, (string)$payload['app_id']))) {
            throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401);
        }
        $now = time();
        if ((int)$payload['nbf'] > $now + $this->clockSkew) { throw new V2Exception('TOKEN_NOT_YET_VALID', 'Token is not yet valid.', 401); }
        if ((int)$payload['exp'] < $now - $this->clockSkew) { throw new V2Exception('TOKEN_EXPIRED', 'Token expired.', 401); }
        if ((int)$payload['iat'] > $now + $this->clockSkew) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        return $payload;
    }

    private static function json(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES);
        if ($json === false) { throw new RuntimeException('JSON encoding failed.'); }
        return $json;
    }

    private static function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) { throw new V2Exception('INVALID_TOKEN', 'Invalid token.', 401); }
        return $decoded;
    }
}
