<?php
declare(strict_types=1);

if (getenv('LICORA_V2_TEST_ALLOW_SCHEMA_RESET') !== '1') {
    echo "API v2 admin UI DB integration skipped (dedicated test DB not enabled).\n";
    exit(0);
}

require_once dirname(__DIR__) . '/includes/admin_helpers.php';

function admin_v2_ok($value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$db = Database::getInstance();
admin_v2_ok(AdminHelpers::tableExists('v2_client_apps'), 'admin helper detects v2_client_apps');
admin_v2_ok(AdminHelpers::tableExists('v2_device_credentials'), 'admin helper detects v2_device_credentials');
admin_v2_ok(!AdminHelpers::tableExists('v2_nonexistent_test_table'), 'admin helper rejects missing table');
admin_v2_ok(!AdminHelpers::tableExists('v2_client_apps;DROP_TABLE'), 'admin helper rejects invalid table identifier');

$appOptions = $db->query("SELECT app_id, display_name FROM v2_client_apps WHERE is_active = 1 ORDER BY display_name, app_id")->fetchAll();
$appIds = array_map(static fn(array $row): string => (string)($row['app_id'] ?? ''), $appOptions);
admin_v2_ok(in_array('vibrapilot', $appIds, true), 'license UI data path returns active vibrapilot client app');

$devices = $db->query("SELECT dc.id, dc.app_id, dc.status, l.license_key, a.display_name
    FROM v2_device_credentials dc
    JOIN licenses l ON l.id = dc.license_id
    LEFT JOIN v2_client_apps a ON a.app_id = dc.app_id
    ORDER BY dc.last_seen_at DESC, dc.id DESC")->fetchAll();
admin_v2_ok(count($devices) >= 1, 'V2 Devices data path returns an activated credential');
admin_v2_ok((string)($devices[0]['app_id'] ?? '') === 'vibrapilot', 'V2 Devices data path retains app identity');

echo "API v2 admin UI DB integration checks passed.\n";
