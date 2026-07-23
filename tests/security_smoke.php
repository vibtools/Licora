<?php

declare(strict_types=1);

define('ENCRYPTION_KEY', 'test-only-32-byte-encryption-key-2026');
define('CSRF_SECRET', 'test-only-csrf-secret-2026');
define('JWT_SECRET', 'test-only-jwt-secret-2026');
define('APP_URL', 'http://localhost');
define('DB_NAME', 'test');

require_once __DIR__ . '/../includes/security.php';

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(Security::validateLicenseFormat('AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD') === 1, 'valid license format');
$assert(Security::validateLicenseFormat('not-a-license') === 0, 'invalid license format');

$password = 'A-strong-test-password';
$hash = Security::hashPassword($password);
$assert(is_string($hash) && Security::verifyPassword($password, $hash), 'password hash round trip');
$assert(!Security::verifyPassword('wrong', $hash), 'password rejection');

$plaintext = 'sensitive-test-value';
$ciphertext = Security::encrypt($plaintext);
$assert($ciphertext !== $plaintext, 'encryption changes plaintext');
$assert(strpos($ciphertext, 'v2:') === 0, 'new encryption uses versioned v2 format');
$assert(Security::decrypt($ciphertext) === $plaintext, 'versioned encryption round trip');

$payload = base64_decode(substr($ciphertext, 3), true);
if (is_string($payload) && strlen($payload) > 20) {
    $payload[20] = chr(ord($payload[20]) ^ 1);
    $tampered = 'v2:' . base64_encode($payload);
    $assert(Security::decrypt($tampered) === '', 'authenticated encryption rejects tampering');
} else {
    $failures[] = 'versioned encryption payload decoding';
}

$legacySource = ENCRYPTION_KEY ?: hash('sha256', __DIR__ . '/../includes' . APP_URL . DB_NAME, true);
$legacyKey = substr(hash('sha256', $legacySource), 0, 32);
$legacyIv = str_repeat("\x01", 16);
$legacyEncrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $legacyKey, 0, $legacyIv);
$legacyPayload = base64_encode($legacyIv . $legacyEncrypted);
$assert(Security::decrypt($legacyPayload) === $plaintext, 'legacy encrypted value remains readable');

$assert(Security::decrypt('malformed') === '', 'malformed ciphertext rejection');

$assert(Security::normalizeAPIKeyCredential('Bearer token-value') === 'token-value', 'Bearer token extraction');
$assert(Security::normalizeAPIKeyCredential('bearer    token-value') === 'token-value', 'Bearer token whitespace handling');
$assert(Security::normalizeAPIKeyCredential('raw-token-value') === 'raw-token-value', 'raw authorization compatibility');
$assert(Security::normalizeAPIKeyCredential(' token value ') === 'tokenvalue', 'existing API key whitespace cleanup');

$escaped = Security::escape('<script>"x"</script>');
$assert(strpos($escaped, '<script>') === false, 'HTML escaping');

$tokenA = Security::generateToken(16);
$tokenB = Security::generateToken(16);
$assert(strlen($tokenA) === 32, 'token length');
$assert($tokenA !== $tokenB, 'token uniqueness');

if ($failures !== []) {
    fwrite(STDERR, "Security smoke test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Security smoke test passed.\n";
