<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/v2/V2Exception.php';
require_once $root . '/includes/v2/V2KeyManager.php';
require_once $root . '/includes/v2/V2TokenService.php';
require_once $root . '/includes/v2/V2DeviceProof.php';

function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$tmp = sys_get_temp_dir() . '/licora-v2-crypto-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$private = $tmp . '/private.pem'; $public = $tmp . '/public.pem';
try {
    $manager = new V2KeyManager($private, $public, 'test-key');
    check($manager->generateIfMissing() === true, 'first key generation must report generated');
    check($manager->generateIfMissing() === false, 'existing key pair must be retained');
    $manager->assertPairMatches();

    $altPrivate = $tmp . '/alt-private.pem'; $altPublic = $tmp . '/alt-public.pem';
    $altManager = new V2KeyManager($altPrivate, $altPublic, 'alt-key');
    check($altManager->generateIfMissing() === true, 'alternate signing key generation');
    $mismatch = new V2KeyManager($private, $altPublic, 'mismatch-key');
    $failed = false; try { $mismatch->assertPairMatches(); } catch (RuntimeException $e) { $failed = true; }
    check($failed, 'mismatched server signing key pair must fail closed');

    $tokens = new V2TokenService($manager, 30);
    $token = $tokens->issue([
        'app_id'=>'vibrapilot','license_id'=>7,'device_id'=>'device-1234567890',
        'device_credential_id'=>11,'device_key_fingerprint'=>str_repeat('a', 64),
    ], 600);
    $claims = $tokens->verify($token, 'vibrapilot');
    check($claims['app_id'] === 'vibrapilot' && (int)$claims['license_id'] === 7, 'signed token claims');
    $parts = explode('.', $token); $parts[1][5] = $parts[1][5] === 'A' ? 'B' : 'A';
    $tampered = implode('.', $parts);
    $failed = false; try { $tokens->verify($tampered, 'vibrapilot'); } catch (V2Exception $e) { $failed = true; }
    check($failed, 'tampered access token must fail');

    $ec = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC, 'curve_name'=>'prime256v1']);
    check($ec !== false, 'P-256 key generation');
    $privateEc = ''; check(openssl_pkey_export($ec, $privateEc), 'P-256 private export');
    $details = openssl_pkey_get_details($ec); check(is_array($details) && !empty($details['key']), 'P-256 public export');
    $normalized = V2DeviceProof::validatePublicKey((string)$details['key']);
    $canonical = V2DeviceProof::canonical('POST', '/api/v2/status.php', time(), 'nonce_1234567890123456', hash('sha256', '{}'), 'jti-test');
    $signature = ''; check(openssl_sign($canonical, $signature, $privateEc, OPENSSL_ALGO_SHA256), 'device proof sign');
    V2DeviceProof::verify($normalized, V2TokenService::b64urlEncode($signature), $canonical);
    $failed = false; try { V2DeviceProof::verify($normalized, V2TokenService::b64urlEncode($signature), $canonical . 'x'); } catch (V2Exception $e) { $failed = true; }
    check($failed, 'tampered device proof must fail');
    echo "API v2 crypto checks passed.\n";
} finally {
    @unlink($private); @unlink($public); @unlink($altPrivate ?? ''); @unlink($altPublic ?? ''); @rmdir($tmp);
}
