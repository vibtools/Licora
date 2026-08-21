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

$dashboard = $read('admin/index.php');
$css = $read('admin/assets/css/admin-ui.css');
$js = $read('admin/assets/js/dashboard.js');
$endpoint = $read('admin/ajax/dashboard-data.php');

$assert($dashboard !== '', 'dashboard page exists');
$assert($js !== '', 'Phase 2 dashboard controller exists');
$assert(strpos($dashboard, 'class="admin-ui dashboard-page"') !== false, 'dashboard page uses scoped Phase 2 class');
$assert(strpos($dashboard, 'data-dashboard-endpoint="ajax/dashboard-data.php"') !== false, 'dashboard binds the authenticated Phase 1 data endpoint');
$assert(strpos($dashboard, 'data-dashboard-poll-ms="30000"') !== false, 'dashboard keeps the reviewed 30-second refresh cadence');
$assert(strpos($dashboard, 'window.location.reload()') === false, 'full-page Dashboard reload is removed');
$assert(strpos($dashboard, 'assets/js/dashboard.js') !== false, 'dedicated Dashboard controller is loaded');
$assert(strpos($dashboard, 'dashboard-initial-data') !== false, 'server-rendered initial Dashboard snapshot remains available to progressive enhancement');
$assert(strpos($dashboard, 'data-dashboard-refresh') !== false, 'manual Dashboard refresh control exists');
$assert(strpos($dashboard, 'data-dashboard-updated-at') !== false, 'last-updated indicator exists');
$assert(strpos($dashboard, 'data-dashboard-state') !== false, 'stale/auth Dashboard status surface exists');

foreach (['total_licenses', 'active_licenses', 'recent_devices', 'expiring_soon'] as $metric) {
    $assert(strpos($dashboard, 'data-dashboard-kpi="' . $metric . '"') !== false, 'primary KPI marker exists: ' . $metric);
}
foreach (['database', 'api_v2', 'cron_scripts', 'php', 'environment'] as $fact) {
    $assert(strpos($dashboard, 'data-dashboard-health="' . $fact . '"') !== false, 'compact health-strip fact exists: ' . $fact);
}
foreach (['license.php?action=create', 'device.php', 'api_keys.php', 'client_apps.php', 'health.php'] as $route) {
    $assert(strpos($dashboard, 'href="' . $route . '"') !== false, 'Quick Action preserves existing route: ' . $route);
}
$assert(strpos($dashboard, 'Tracked API Activity') !== false, 'truthful API chart label is preserved');
$assert(strpos($dashboard, 'API v1 Verify') !== false, 'API v1 tracked source remains explicit');
$assert(strpos($dashboard, 'API v2 Audit Events') !== false, 'API v2 tracked source remains explicit');
$assert(strpos($dashboard, 'Expiration Timeline') !== false, 'truthful expiration chart label is preserved');
$assert(strpos($dashboard, 'Recent Activity') !== false, 'combined source-labelled recent activity panel exists');
$assert(strpos($dashboard, 'Top Licenses — API v1 Verify') !== false, 'top-license source remains explicit');

foreach ([
    '.dashboard-page .dashboard-health-strip',
    '.dashboard-page .dashboard-kpi-grid',
    '.dashboard-page .dashboard-chart-grid',
    '.dashboard-page .dashboard-operations-grid',
    '@media (prefers-reduced-motion: reduce)',
] as $marker) {
    $assert(strpos($css, $marker) !== false, 'scoped/responsive Dashboard CSS marker exists: ' . $marker);
}

foreach (['DEFAULT_POLL_MS = 30000', "credentials: 'same-origin'", "cache: 'no-store'", 'setInterval', 'inFlight', 'showStale', 'showAuthRequired', "update('none')", 'textContent'] as $marker) {
    $assert(strpos($js, $marker) !== false, 'Dashboard runtime marker exists: ' . $marker);
}
$assert(strpos($js, 'innerHTML') === false, 'Dashboard runtime does not inject backend data through innerHTML');
$assert(strpos($js, 'location.reload') === false, 'Dashboard runtime never reloads the full page');
$assert(strpos($js, 'POST') === false, 'Dashboard polling controller contains no mutation request method');
$assert(strpos($js, 'document.write') === false, 'Dashboard runtime does not use document.write');

foreach (["\$method !== 'GET'", 'isAdminLoggedIn()', "'AUTH_REQUIRED'", "'DASHBOARD_DATA_ERROR'", "Cache-Control: no-store"] as $marker) {
    $assert(strpos($endpoint, $marker) !== false, 'Phase 1 endpoint contract remains frozen: ' . $marker);
}

if ($failures !== []) {
    fwrite(STDERR, "Dashboard Phase 2 contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Dashboard Phase 2 contract checks passed.\n";
