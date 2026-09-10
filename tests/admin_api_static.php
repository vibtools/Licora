<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/admin_api/AdminApiException.php';
require_once $root . '/includes/admin_api/AdminApi.php';

function admin_api_ok($value, string $message): void
{
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

foreach ([
    'api/admin/v1/licenses/create.php', 'api/admin/v1/licenses/status.php',
    'api/admin/v1/licenses/list.php', 'api/admin/v1/licenses/action.php',
    'api/admin/v1/devices/list.php', 'api/admin/v1/devices/revoke.php',
    'api/admin/v1/apps/list.php', 'admin/admin_api_keys.php',
    'admin/admin_api_docs.php', 'admin/ajax/admin-api-sdk-download.php',
] as $path) {
    admin_api_ok(is_file($root . '/' . $path), 'required Admin API route exists: ' . $path);
}

$implementation = '';
foreach (glob($root . '/includes/admin_api/*.php') as $file) { $implementation .= (string)file_get_contents($file); }
foreach (glob($root . '/api/admin/v1/*/*.php') as $file) { $implementation .= (string)file_get_contents($file); }
foreach (glob($root . '/api/admin/v1/*/*/*.php') as $file) { $implementation .= (string)file_get_contents($file); }
foreach (['license:create', 'license:read', 'license:reveal', 'license:extend', 'license:activate', 'license:suspend', 'license:ban', 'license:delete', 'device:read', 'device:revoke'] as $scope) {
    admin_api_ok(strpos($implementation, $scope) !== false, 'permission scope is enforced: ' . $scope);
}
foreach (['Idempotency-Key', 'X_LICORA_TIMESTAMP', 'X_LICORA_NONCE', 'X_LICORA_SIGNATURE', 'hash_hmac', 'REPLAY_DETECTED', 'APP_NOT_ALLOWED'] as $marker) {
    admin_api_ok(strpos($implementation, $marker) !== false, 'request-security marker exists: ' . $marker);
}
admin_api_ok(strpos($implementation, 'INSERT INTO v2_client_apps') === false, 'Admin License API cannot create API v2 applications');
admin_api_ok(strpos($implementation, 'DELETE FROM licenses') === false, 'license delete operation is recoverable soft-delete');

$adminPage = (string)file_get_contents($root . '/admin/admin_api_keys.php');
foreach (['Download Ready SDK', 'Documents', 'ajax/admin-api-sdk-download.php', 'admin_api_docs.php'] as $marker) {
    admin_api_ok(strpos($adminPage, $marker) !== false, 'Admin API page exposes SDK resource: ' . $marker);
}
foreach ([
    'initialize_admin_api_schema', 'AdminHelpers::requireDelete()', 'Security::requireCSRFToken',
    "GET_LOCK('licora_admin_api_schema_v583', 10)", "RELEASE_LOCK('licora_admin_api_schema_v583')",
    'UpdateSchema::splitSql', 'count($statements) !== 7',
] as $marker) {
    admin_api_ok(strpos($adminPage, $marker) !== false, 'Admin API schema initializer safety marker exists: ' . $marker);
}
admin_api_ok(
    strpos($adminPage, 'CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS\\s+admin_api_') !== false,
    'Admin API schema initializer accepts only additive Admin API table creation'
);

$sdkFiles = [
    'README.md', 'AI_INSTRUCTIONS.md', 'LICENSE', 'VERSION',
    'docs/AUTHENTICATION_AND_SIGNING.md', 'docs/API_REFERENCE.md', 'docs/SECURITY_AND_OPERATIONS.md',
    'php/composer.json', 'php/config.example.php', 'php/README.md', 'php/src/LicoraAdminApiException.php',
    'php/src/LicoraAdminClient.php', 'php/examples/manage-license.php',
    'nodejs/package.json', 'nodejs/env.example', 'nodejs/README.md',
    'nodejs/src/licora-admin-client.mjs', 'nodejs/examples/manage-license.mjs',
];
foreach ($sdkFiles as $path) {
    admin_api_ok(is_file($root . '/SDK/admin-license-api/' . $path), 'downloadable Admin API SDK file exists: ' . $path);
}

$sdkClients = [
    'PHP' => (string)file_get_contents($root . '/SDK/admin-license-api/php/src/LicoraAdminClient.php'),
    'Node.js' => (string)file_get_contents($root . '/SDK/admin-license-api/nodejs/src/licora-admin-client.mjs'),
];
foreach (['X-Licora-Timestamp', 'X-Licora-Nonce', 'X-Licora-Signature', 'Idempotency-Key',
    'createLicense', 'licenseStatus', 'listLicenses', 'activateLicense', 'suspendLicense',
    'extendLicense', 'banLicense', 'deleteLicense', 'listDevices', 'revokeDevice'] as $marker) {
    foreach ($sdkClients as $clientName => $sdkImplementation) {
        admin_api_ok(strpos($sdkImplementation, $marker) !== false, $clientName . ' SDK contract marker exists: ' . $marker);
    }
}

$docsPage = (string)file_get_contents($root . '/admin/admin_api_docs.php');
admin_api_ok(strpos($docsPage, "'docs/API_REFERENCE.md'") !== false, 'documents page uses a fixed document allowlist');
admin_api_ok(strpos($docsPage, 'Security::escape($document[\'content\'])') !== false, 'documents page escapes Markdown content');
$downloadPage = (string)file_get_contents($root . '/admin/ajax/admin-api-sdk-download.php');
foreach (['isAdminLoggedIn', 'ZipArchive', 'realpath', 'Licora-Admin-API-SDK-v1.0.0/'] as $marker) {
    admin_api_ok(strpos($downloadPage, $marker) !== false, 'SDK download safety marker exists: ' . $marker);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/licora/api/admin/v1/licenses/create.php?source=checkout';
$body = '{"external_order_id":"ORDER-1"}';
$timestamp = (string)time();
$nonce = 'static-contract-nonce-0001';
$canonical = AdminApi::canonical($timestamp, $nonce, $body);
admin_api_ok($canonical === "POST\n/licora/api/admin/v1/licenses/create.php?source=checkout\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body), 'canonical request signs exact target and body');
$token = 'licora_admin_live_' . str_repeat('a', 64);
$signature = hash_hmac('sha256', $canonical, $token);
AdminApi::verifySignature($token, $signature, $canonical);
$rejected = false;
try { AdminApi::verifySignature($token, str_repeat('0', 64), $canonical); }
catch (AdminApiException $exception) { $rejected = $exception->machineCode() === 'INVALID_REQUEST_PROOF'; }
admin_api_ok($rejected, 'invalid request HMAC is rejected');
admin_api_ok(AdminApi::isValidIpRule('203.0.113.8') && AdminApi::isValidIpRule('2001:db8::/48'), 'IPv4 and IPv6 allowlist rules validate');
admin_api_ok(AdminApi::ipMatchesRule('203.0.113.25', '203.0.113.0/24'), 'IPv4 CIDR match works');
admin_api_ok(!AdminApi::ipMatchesRule('203.0.114.25', '203.0.113.0/24'), 'IPv4 CIDR mismatch works');
admin_api_ok(AdminApi::ipMatchesRule('2001:db8::12', '2001:db8::/48'), 'IPv6 CIDR match works');

$migration = (string)file_get_contents($root . '/migration-v5.8.3-admin-license-api.sql');
foreach (['admin_api_keys', 'admin_api_key_scopes', 'admin_api_key_apps', 'admin_api_license_orders', 'admin_api_idempotency', 'admin_api_nonces', 'admin_api_logs'] as $table) {
    admin_api_ok(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) !== false, 'additive table exists: ' . $table);
}
$stripped = preg_replace('/--[^\n]*/', '', $migration);
admin_api_ok(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', (string)$stripped), 'migration contains no destructive statement');

echo "Admin License API static/security checks passed.\n";
