<?php

declare(strict_types=1);

define('ENCRYPTION_KEY', 'test-only-32-byte-encryption-key-2026');
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
$assert(Security::decrypt($ciphertext) === $plaintext, 'encryption round trip');
$assert(Security::decrypt('malformed') === '', 'malformed ciphertext rejection');

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
