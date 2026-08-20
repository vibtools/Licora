<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) { $failures[] = $message; }
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$modelPath = 'includes/dashboard.php';
$endpointPath = 'admin/ajax/dashboard-data.php';
$dashboardPath = 'admin/index.php';
$model = $read($modelPath);
$endpoint = $read($endpointPath);
$dashboard = $read($dashboardPath);

$assert($model !== '', 'dashboard read model exists');
$assert($endpoint !== '', 'dashboard data endpoint exists');
$assert(strpos($model, 'final class DashboardReadModel') !== false, 'central DashboardReadModel class exists');
$assert(strpos($model, 'DEVICE_RECENCY_SECONDS = 300') !== false, 'device recency is explicitly five minutes');
foreach (['total', 'active', 'expired', 'suspended', 'expiring_soon'] as $marker) {
    $assert(strpos($model, "'{$marker}'") !== false, 'truthful license metric marker: ' . $marker);
}
foreach (['total_records', 'active_flagged', 'recently_seen', 'recency_window_seconds'] as $marker) {
    $assert(strpos($model, "'{$marker}'") !== false, 'truthful device metric marker: ' . $marker);
}
foreach (['api_logs', 'v2_audit_logs', "'legacy_check_license_included' => false", 'expired_last_30_days', 'expiring_next_30_days', "'recent_activity'", "'key_pair_ready'"] as $marker) {
    $assert(strpos($model, $marker) !== false, 'dashboard data-source/expiration/health marker: ' . $marker);
}
$assert(strpos($model, 'requirePublicKey()') !== false, 'API v2 public-key readiness requires a parseable public signing key');
$assert(strpos($model, 'assertPairMatches()') !== false, 'API v2 readiness requires the private/public signing pair to match');

$mutationFree = preg_replace('/\bUPDATE\b/i', '', $model); // Ignore the word only if it appears inside this test helper expression, never in source.
$assert(preg_match('/\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE|REPLACE)\b/i', $model) !== 1, 'dashboard read model contains no mutation/schema SQL verb');
$assert(strpos($endpoint, "\$method !== 'GET'") !== false, 'dashboard endpoint is GET only');
$assert(strpos($endpoint, "header('Allow: GET')") !== false, 'dashboard endpoint advertises GET on 405');
$assert(strpos($endpoint, 'isAdminLoggedIn()') !== false, 'dashboard endpoint requires existing admin authentication');
$assert(strpos($endpoint, "'AUTH_REQUIRED'") !== false, 'dashboard endpoint exposes AUTH_REQUIRED');
$assert(strpos($endpoint, "'METHOD_NOT_ALLOWED'") !== false, 'dashboard endpoint exposes METHOD_NOT_ALLOWED');
$assert(strpos($endpoint, "'DASHBOARD_DATA_ERROR'") !== false, 'dashboard endpoint exposes sanitized internal error code');
$assert(strpos($endpoint, "Cache-Control: no-store") !== false, 'dashboard endpoint disables caching');
$assert(strpos($endpoint, 'getTraceAsString') === false, 'dashboard endpoint does not expose stack trace');
$assert(strpos($endpoint, 'DB_PASS') === false, 'dashboard endpoint does not expose database password');
$assert(strpos($endpoint, 'PRIVATE_KEY') === false, 'dashboard endpoint does not expose private keys');
$assert(strpos($endpoint, "'recent_activity' => \$snapshot['recent_activity']") !== false, 'dashboard endpoint matches the declared top-level recent_activity contract');

$assert(strpos($dashboard, 'Tracked API Activity') !== false, 'dashboard uses truthful API activity label');
$assert(strpos($dashboard, 'Expiration Timeline') !== false, 'dashboard uses truthful expiration label');
$assert(strpos($dashboard, 'Top Licenses — API v1 Verify') !== false, 'top licenses identify API v1 source');
$assert(strpos($dashboard, 'Recent API v1 Verify Calls') !== false, 'recent calls identify API v1 source');
$assert(strpos($dashboard, '>Security</h6>') === false, 'hardcoded Security Active dashboard row removed');
$assert(strpos($dashboard, '>API Server</h6>') === false, 'hardcoded API Server Running dashboard row removed');
$assert(strpos($dashboard, "ENVIRONMENT === 'production' ? 'Live' : 'Dev'") === false, 'production environment is not labeled as live health');
$assert(strpos($dashboard, "\$health['api_v2']['key_pair_ready']") !== false, 'API v2 Ready UI requires a verified signing key pair');
$assert(strpos($dashboard, 'window.location.reload()') !== false, 'Phase 1 intentionally preserves baseline 30-second full reload for Phase 2');
$assert(strpos($dashboard, 'dashboard.js') === false, 'Phase 2 dashboard polling controller is not introduced early');

if ($failures !== []) {
    fwrite(STDERR, "Dashboard data contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Dashboard data contract checks passed.\n";
