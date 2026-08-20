<?php

declare(strict_types=1);

if (getenv('LICORA_V2_TEST_ALLOW_SCHEMA_RESET') !== '1') {
    echo "Dashboard DB integration skipped (dedicated test DB not enabled).\n";
    exit(0);
}

$dsn = getenv('LICORA_TEST_DB_DSN') ?: '';
if ($dsn === '') { fwrite(STDERR, "LICORA_TEST_DB_DSN is required.\n"); exit(1); }
$db = new PDO($dsn, getenv('LICORA_TEST_DB_USER') ?: '', getenv('LICORA_TEST_DB_PASS') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function dashboard_db_ok($value, string $message): void
{
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$db->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ([
        'v2_used_nonces',
        'v2_refresh_tokens',
        'v2_audit_logs',
        'v2_device_credentials',
        'v2_client_apps',
        'api_logs',
        'api_keys',
        'devices',
        'licenses',
    ] as $table) {
        $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
} finally {
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

$db->exec("CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL
) ENGINE=InnoDB");
$db->exec("CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    device_hash VARCHAR(255) NOT NULL,
    last_active DATETIME NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    KEY idx_device_identity (license_id, device_hash)
) ENGINE=InnoDB");
$db->exec("CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    request_count INT NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->exec("CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    endpoint VARCHAR(50) NOT NULL,
    license_key VARCHAR(100) NULL,
    response_code INT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB");
$db->exec("CREATE TABLE v2_device_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    app_id VARCHAR(120) NOT NULL,
    device_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_seen_at DATETIME NOT NULL,
    KEY idx_v2_identity (license_id, device_hash)
) ENGINE=InnoDB");
$db->exec("CREATE TABLE v2_client_apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id VARCHAR(120) NOT NULL UNIQUE,
    display_name VARCHAR(160) NOT NULL
) ENGINE=InnoDB");
$db->exec("CREATE TABLE v2_refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_credential_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    CONSTRAINT fk_v2_refresh_device FOREIGN KEY (device_credential_id) REFERENCES v2_device_credentials (id) ON DELETE CASCADE
) ENGINE=InnoDB");
$db->exec("CREATE TABLE v2_used_nonces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_credential_id BIGINT UNSIGNED NOT NULL,
    nonce_hash CHAR(64) NOT NULL,
    CONSTRAINT fk_v2_nonce_device FOREIGN KEY (device_credential_id) REFERENCES v2_device_credentials (id) ON DELETE CASCADE
) ENGINE=InnoDB");
$db->exec("CREATE TABLE v2_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    app_id VARCHAR(120) NULL,
    license_id INT NULL,
    device_credential_id BIGINT UNSIGNED NULL,
    request_id CHAR(32) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_v2_created (created_at)
) ENGINE=InnoDB");

$db->exec("INSERT INTO licenses (license_key,status,expires_at) VALUES
 ('AAAA1111-BBBB2222-CCCC3333-DDDD4444','active',DATE_ADD(NOW(), INTERVAL 10 DAY)),
 ('EEEE1111-FFFF2222-GGGG3333-HHHH4444','active',DATE_ADD(NOW(), INTERVAL 60 DAY)),
 ('IIII1111-JJJJ2222-KKKK3333-LLLL4444','suspended',DATE_ADD(NOW(), INTERVAL 20 DAY)),
 ('MMMM1111-NNNN2222-OOOO3333-PPPP4444','active',DATE_SUB(NOW(), INTERVAL 5 DAY))");
$db->exec("INSERT INTO devices (license_id,device_hash,last_active,is_active) VALUES
 (1,'device-a',DATE_SUB(NOW(), INTERVAL 20 MINUTE),1),
 (2,'device-b',DATE_SUB(NOW(), INTERVAL 2 MINUTE),1),
 (3,'device-c',DATE_SUB(NOW(), INTERVAL 1 MINUTE),0)");
$db->exec("INSERT INTO v2_device_credentials (license_id,app_id,device_hash,status,last_seen_at) VALUES
 (1,'app-a','device-a','active',DATE_SUB(NOW(), INTERVAL 1 MINUTE)),
 (1,'app-b','device-a','active',DATE_SUB(NOW(), INTERVAL 30 SECOND)),
 (2,'app-a','device-b','active',DATE_SUB(NOW(), INTERVAL 1 HOUR))");
$db->exec("INSERT INTO api_keys (name,is_active,request_count) VALUES ('Primary',1,7),('Old',0,3)");
$db->exec("INSERT INTO api_logs (api_key_id,endpoint,license_key,response_code,created_at) VALUES
 (1,'verify','AAAA1111-BBBB2222-CCCC3333-DDDD4444',200,DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (1,'verify','AAAA1111-BBBB2222-CCCC3333-DDDD4444',403,NOW()),
 (2,'verify','EEEE1111-FFFF2222-GGGG3333-HHHH4444',200,NOW())");
$db->exec("INSERT INTO v2_audit_logs (event_type,app_id,license_id,device_credential_id,request_id,created_at) VALUES
 ('activation_success','app-a',1,1,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',DATE_SUB(NOW(), INTERVAL 1 DAY)),
 ('refresh_success','app-a',1,1,'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',NOW()),
 ('status_failed','app-b',1,2,'cccccccccccccccccccccccccccccccc',NOW())");

require_once dirname(__DIR__) . '/includes/dashboard.php';
$model = new DashboardReadModel($db);

$before = [];
foreach (['licenses','devices','api_keys','api_logs','v2_client_apps','v2_device_credentials','v2_refresh_tokens','v2_used_nonces','v2_audit_logs'] as $table) {
    $before[$table] = (int)$db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

$snapshot = $model->snapshot();

$licenses = $snapshot['licenses'];
dashboard_db_ok($licenses['total'] === 4, 'total license count');
dashboard_db_ok($licenses['active'] === 2, 'active license count excludes expired/suspended');
dashboard_db_ok($licenses['expired'] === 1, 'expired license count');
dashboard_db_ok($licenses['suspended'] === 1, 'suspended license count');
dashboard_db_ok($licenses['expiring_soon'] === 1, 'expiring-soon excludes suspended licenses');

$devices = $snapshot['devices'];
dashboard_db_ok($devices['total_records'] === 3, 'total device records');
dashboard_db_ok($devices['active_flagged'] === 2, 'active-flagged device records');
dashboard_db_ok($devices['recently_seen'] === 2, 'recent device count uses base/v2 recency without double count');
dashboard_db_ok($devices['v2_presence_source_available'] === true, 'v2 presence source detected');

$keys = $snapshot['api_keys'];
dashboard_db_ok($keys['active'] === 1 && $keys['inactive'] === 1, 'API key state summary');
dashboard_db_ok($keys['tracked_v1_requests'] === 10, 'API key v1 request counter remains explicit');

$api = $snapshot['api_activity'];
dashboard_db_ok($api['legacy_check_license_included'] === false, 'legacy compatibility endpoint exclusion is explicit');
dashboard_db_ok($api['v1_tracked']['source'] === 'api_logs', 'v1 source identity');
dashboard_db_ok($api['v1_tracked']['total_last_14_days'] === 3, 'v1 tracked activity count');
dashboard_db_ok($api['v2_tracked']['source'] === 'v2_audit_logs' && $api['v2_tracked']['available'] === true, 'v2 source identity');
dashboard_db_ok($api['v2_tracked']['total_last_14_days'] === 3, 'v2 tracked audit activity count');
dashboard_db_ok(($api['v1_tracked']['top_licenses'][0]['license_key'] ?? '') === 'AAAA1111-BBBB2222-CCCC3333-DDDD4444', 'top license uses v1 verify source');

$recent = $snapshot['recent_activity'];
dashboard_db_ok(count($recent['v1_tracked']) === 3, 'top-level recent activity exposes v1 tracked calls');
dashboard_db_ok(count($recent['v2_tracked']) === 3, 'top-level recent activity exposes v2 tracked events');

$health = $snapshot['health'];
dashboard_db_ok($health['api_v2']['schema_ready'] === true, 'API v2 health requires the complete five-table schema');
dashboard_db_ok(array_key_exists('key_pair_ready', $health['api_v2']), 'API v2 health exposes signing key-pair readiness');

$expiration = $snapshot['expiration'];
$expiredCount = array_sum(array_column($expiration['expired_last_30_days'], 'count'));
$expiringCount = array_sum(array_column($expiration['expiring_next_30_days'], 'count'));
dashboard_db_ok($expiredCount === 1, 'expired timeline contains past expiration only');
dashboard_db_ok($expiringCount === 2, 'future expiration timeline includes future licenses without calling them expired');

foreach ($before as $table => $count) {
    $after = (int)$db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    dashboard_db_ok($after === $count, 'dashboard snapshot is read-only for ' . $table);
}

echo "Dashboard DB integration checks passed.\n";
