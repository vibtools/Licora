<?php
declare(strict_types=1);

final class V2DeviceProof
{
    public static function validatePublicKey(string $pem): string
    {
        if (strlen($pem) > 8192) { throw new V2Exception('INVALID_DEVICE_KEY', 'Invalid device key.', 400); }
        $key = openssl_pkey_get_public($pem);
        if ($key === false) { throw new V2Exception('INVALID_DEVICE_KEY', 'Invalid device key.', 400); }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) { throw new V2Exception('INVALID_DEVICE_KEY', 'Invalid device key.', 400); }
        $curve = (string)($details['ec']['curve_name'] ?? '');
        if (!in_array($curve, ['prime256v1','secp256r1'], true)) { throw new V2Exception('INVALID_DEVICE_KEY', 'Invalid device key.', 400); }
        return trim((string)$details['key']) . "\n";
    }

    public static function fingerprint(string $normalizedPem): string { return hash('sha256', $normalizedPem); }

    public static function canonical(string $method, string $path, int $timestamp, string $nonce, string $bodyHash, string $context): string
    {
        return strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . strtolower($bodyHash) . "\n" . $context;
    }

    public static function validateRequestMetadata(string $timestamp, string $nonce, int $clockSkew): int
    {
        if (!preg_match('/^[0-9]{10,13}$/', $timestamp)) { throw new V2Exception('INVALID_DEVICE_PROOF', 'Invalid device proof.', 401); }
        $parsed = (int)$timestamp;
        if ($parsed > 9999999999) { $parsed = intdiv($parsed, 1000); }
        if (abs(time() - $parsed) > max(30, $clockSkew)) { throw new V2Exception('STALE_REQUEST', 'Request timestamp outside allowed window.', 401); }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) { throw new V2Exception('INVALID_DEVICE_PROOF', 'Invalid device proof.', 401); }
        return $parsed;
    }

    public static function verify(string $publicKeyPem, string $signatureEncoded, string $canonical): void
    {
        try { $signature = V2TokenService::b64urlDecode($signatureEncoded); }
        catch (V2Exception $e) { throw new V2Exception('INVALID_DEVICE_PROOF', 'Invalid device proof.', 401); }
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false || openssl_verify($canonical, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new V2Exception('INVALID_DEVICE_PROOF', 'Invalid device proof.', 401);
        }
    }
}
