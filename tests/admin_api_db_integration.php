<?php
declare(strict_types=1);

if (getenv('LICORA_V2_TEST_ALLOW_SCHEMA_RESET') !== '1') {
    echo "Admin License API DB integration skipped (dedicated test DB not enabled).\n";
    exit(0);
}
$root = dirname(__DIR__);
$dsn = getenv('LICORA_TEST_DB_DSN') ?: '';
if ($dsn === '') { fwrite(STDERR, "LICORA_TEST_DB_DSN is required.\n"); exit(1); }
$db = new PDO($dsn, getenv('LICORA_TEST_DB_USER') ?: '', getenv('LICORA_TEST_DB_PASS') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
function admin_api_db_ok($value, string $message): void
{
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['admin_api_logs', 'admin_api_nonces', 'admin_api_idempotency', 'admin_api_license_orders', 'admin_api_key_apps', 'admin_api_key_scopes', 'admin_api_keys', 'admin_users', 'settings'] as $table) {
    $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
$db->exec("CREATE TABLE admin_users (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE settings (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL UNIQUE, setting_value TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS rate_limits (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, endpoint VARCHAR(100) NOT NULL, request_count INT NOT NULL DEFAULT 1, first_request DATETIME DEFAULT CURRENT_TIMESTAMP, last_request DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec('DELETE FROM rate_limits');
$db->exec("INSERT INTO admin_users (username) VALUES ('integration-admin')");
$db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('license_min_hours','1'),('license_max_hours','8760')");

$columns = array_column($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='licenses'")->fetchAll(), 'COLUMN_NAME');
$licenseColumns = [
    'encrypted_key' => 'TEXT NULL', 'created_by' => 'INT NULL', 'notes' => 'TEXT NULL',
    'api_key_id' => 'INT NULL', 'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];
foreach ($licenseColumns as $name => $definition) {
    if (!in_array($name, $columns, true)) { $db->exec("ALTER TABLE licenses ADD COLUMN `{$name}` {$definition}"); }
}
$blacklistColumns = array_column($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blacklist'")->fetchAll(), 'COLUMN_NAME');
foreach (['reason' => 'TEXT NULL', 'banned_by' => 'INT NULL', 'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'] as $name => $definition) {
    if (!in_array($name, $blacklistColumns, true)) { $db->exec("ALTER TABLE blacklist ADD COLUMN `{$name}` {$definition}"); }
}

$sql = preg_replace('/^--.*$/m', '', (string)file_get_contents($root . '/migration-v5.8.3-admin-license-api.sql'));
$statements = preg_split('/;\s*(?:\r?\n|$)/', (string)$sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach ($statements as $statement) { if (trim($statement) !== '') { $db->exec($statement); } }
foreach ($statements as $statement) { if (trim($statement) !== '') { $db->exec($statement); } }

if (!defined('ENCRYPTION_KEY')) { define('ENCRYPTION_KEY', str_repeat('a', 64)); }
require_once $root . '/includes/database.php';
require_once $root . '/includes/security.php';
require_once $root . '/includes/admin_api/AdminApiException.php';
require_once $root . '/includes/admin_api/AdminApi.php';
require_once $root . '/includes/admin_api/AdminApiRepository.php';
require_once $root . '/includes/admin_api/AdminLicenseService.php';

$repository = new AdminApiRepository($db);
$repository->requireSchema();
$token = 'licora_admin_test_' . str_repeat('a', 64);
$keyInsert = $db->prepare("INSERT INTO admin_api_keys (id,name,key_prefix,key_hash,status,allowed_ips,rate_limit_per_hour,created_by) VALUES
 (77,'Checkout','licora_admin_test_aaaaaaaaaaaaa',:key_hash,'active','127.0.0.1/32',300,1),
 (78,'Other','licora_admin_live_other',REPEAT('b',64),'active',NULL,300,1)");
$keyInsert->execute([':key_hash' => hash('sha256', $token)]);
$allScopes = ['license:create','license:read','license:reveal','license:extend','license:activate','license:suspend','license:ban','license:delete','device:read','device:revoke'];
$scopeInsert = $db->prepare('INSERT INTO admin_api_key_scopes (api_key_id,scope_name) VALUES (77,:scope)');
foreach ($allScopes as $scope) { $scopeInsert->execute([':scope' => $scope]); }
$db->exec("INSERT INTO admin_api_key_apps (api_key_id,app_id) VALUES (77,'vibrapilot')");
$timestamp = (string)time();
$nonce = 'database-proof-nonce-0001';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/admin/v1/apps/list.php';
$_SERVER['HTTP_X_LICORA_TIMESTAMP'] = $timestamp;
$_SERVER['HTTP_X_LICORA_NONCE'] = $nonce;
$_SERVER['HTTP_X_LICORA_SIGNATURE'] = hash_hmac('sha256', AdminApi::canonical($timestamp, $nonce, ''), $token);
$context = $repository->authenticate($token, '');
admin_api_db_ok((int)$context['id'] === 77 && in_array('license:create', $context['scopes'], true) && $context['app_ids'] === ['vibrapilot'], 'HMAC authentication loads exact key scopes/apps');
$replayRejected = false;
try { $repository->authenticate($token, ''); }
catch (AdminApiException $exception) { $replayRejected = $exception->machineCode() === 'REPLAY_DETECTED'; }
admin_api_db_ok($replayRejected, 'persistent nonce replay is rejected');
$service = new AdminLicenseService($repository);
$input = ['external_order_id' => 'ORDER-583-1', 'app_id' => 'vibrapilot', 'validity_hours' => 48, 'device_limit' => 2, 'customer_reference' => 'CUSTOMER-1', 'notes' => 'Paid order'];
$created = $service->create($context, $input, 'checkout-order-583-1');
admin_api_db_ok(empty($created['idempotent_replay']), 'first order creates a license');
admin_api_db_ok($created['license']['app_id'] === 'vibrapilot' && isset($created['license']['license_key']), 'created license is exact-app scoped and returned once');
$licenseId = (int)$created['license']['license_id'];
$replay = $service->create($context, $input, 'checkout-order-583-1');
admin_api_db_ok(!empty($replay['idempotent_replay']) && (int)$replay['license']['license_id'] === $licenseId, 'order create is idempotent');
admin_api_db_ok((int)$db->query("SELECT COUNT(*) FROM admin_api_license_orders WHERE external_order_id='ORDER-583-1'")->fetchColumn() === 1, 'idempotent create persists one order/license mapping');
$stored = $db->query('SELECT app_scope,api_key_id,device_limit FROM licenses WHERE id=' . $licenseId)->fetch();
admin_api_db_ok($stored['app_scope'] === 'vibrapilot' && $stored['api_key_id'] === null && (int)$stored['device_limit'] === 2, 'license uses v2 app scope without legacy API-v1 key binding');

$conflict = false;
$different = $input; $different['device_limit'] = 3;
try { $service->create($context, $different, 'checkout-order-583-1'); }
catch (AdminApiException $exception) { $conflict = $exception->machineCode() === 'ORDER_CONFLICT'; }
admin_api_db_ok($conflict, 'same order with changed parameters is rejected');
$wrongApp = false;
$bad = $input; $bad['external_order_id'] = 'ORDER-OTHER-APP'; $bad['app_id'] = 'not-allowed';
try { $service->create($context, $bad, 'checkout-order-other-app'); }
catch (AdminApiException $exception) { $wrongApp = $exception->machineCode() === 'APP_NOT_ALLOWED'; }
admin_api_db_ok($wrongApp, 'unassigned app cannot receive a license');

$masked = $service->get($context, $licenseId, null, false);
admin_api_db_ok(!isset($masked['license_key']) && $masked['license_key_masked'] !== '', 'status hides full license key by default');
$listed = $service->list($context, ['app_id' => 'vibrapilot', 'page' => 1, 'per_page' => 10]);
admin_api_db_ok($listed['total'] === 1 && count($listed['items']) === 1, 'key can list only its owned licenses');
$otherContext = ['id' => 78, 'created_by' => 1, 'scopes' => ['license:read'], 'app_ids' => ['vibrapilot']];
$isolated = false;
try { $service->get($otherContext, $licenseId, null, false); }
catch (AdminApiException $exception) { $isolated = $exception->machineCode() === 'LICENSE_NOT_FOUND'; }
admin_api_db_ok($isolated, 'another Admin API key cannot read an unowned license');

$suspended = $service->action($context, $licenseId, 'suspend', []);
admin_api_db_ok($suspended['status'] === 'suspended', 'license suspend action works');
$activated = $service->action($context, $licenseId, 'activate', []);
admin_api_db_ok($activated['status'] === 'active', 'license activate action works');
$oldExpiry = strtotime((string)$activated['expires_at']);
$extended = $service->action($context, $licenseId, 'extend', ['additional_hours' => 24]);
admin_api_db_ok(strtotime((string)$extended['expires_at']) > $oldExpiry, 'license extension works');

$credential = $db->prepare("INSERT INTO v2_device_credentials (license_id,app_id,device_hash,public_key,public_key_fingerprint,status,first_seen_at,last_seen_at) VALUES (:license_id,'vibrapilot','admin-api-device','public-key',REPEAT('c',64),'active',NOW(),NOW())");
$credential->execute([':license_id' => $licenseId]);
$credentialId = (int)$db->lastInsertId();
$db->exec("INSERT INTO v2_refresh_tokens (device_credential_id,token_hash,family_id,expires_at,created_at) VALUES ({$credentialId},REPEAT('d',64),REPEAT('e',32),DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())");
admin_api_db_ok(count($service->devices($context, $licenseId)) === 1, 'device list returns owned v2 credentials');
$revoked = $service->revokeDevice($context, $credentialId);
admin_api_db_ok($revoked['status'] === 'revoked' && $db->query("SELECT revoked_at FROM v2_refresh_tokens WHERE device_credential_id={$credentialId}")->fetchColumn() !== null, 'device revoke invalidates refresh tokens');

$banInput = $input; $banInput['external_order_id'] = 'ORDER-583-BAN';
$banLicense = $service->create($context, $banInput, 'checkout-order-583-ban')['license'];
$db->exec("INSERT INTO v2_device_credentials (license_id,app_id,device_hash,public_key,public_key_fingerprint,status,first_seen_at,last_seen_at) VALUES (" . (int)$banLicense['license_id'] . ",'vibrapilot','banned-device','public-key',REPEAT('f',64),'active',NOW(),NOW())");
$banCredentialId = (int)$db->lastInsertId();
$db->exec("INSERT INTO v2_refresh_tokens (device_credential_id,token_hash,family_id,expires_at,created_at) VALUES ({$banCredentialId},REPEAT('1',64),REPEAT('2',32),DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())");
$banned = $service->action($context, (int)$banLicense['license_id'], 'ban', ['reason' => 'Chargeback confirmed']);
admin_api_db_ok($banned['status'] === 'banned' && (int)$db->query("SELECT COUNT(*) FROM blacklist WHERE type='license'")->fetchColumn() >= 1 && $db->query("SELECT revoked_at FROM v2_refresh_tokens WHERE device_credential_id={$banCredentialId}")->fetchColumn() !== null, 'ban marks order, blacklists license and revokes tokens');
$deleteInput = $input; $deleteInput['external_order_id'] = 'ORDER-583-DELETE';
$deleteLicense = $service->create($context, $deleteInput, 'checkout-order-583-delete')['license'];
$deleted = $service->action($context, (int)$deleteLicense['license_id'], 'delete', []);
admin_api_db_ok($deleted['status'] === 'deleted' && (int)$db->query('SELECT COUNT(*) FROM licenses WHERE id=' . (int)$deleteLicense['license_id'])->fetchColumn() === 1, 'delete is a recoverable soft-delete');

$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['admin_api_logs', 'admin_api_nonces', 'admin_api_idempotency', 'admin_api_license_orders', 'admin_api_key_apps', 'admin_api_key_scopes', 'admin_api_keys', 'admin_users'] as $table) {
    $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Admin License API DB integration checks passed.\n";
