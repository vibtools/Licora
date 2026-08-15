<?php
declare(strict_types=1);
$root = dirname(__DIR__);
function v550_ok($value, string $message): void {
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function v550_read(string $root, string $rel): string { return (string)file_get_contents($root . '/' . $rel); }

$core = v550_read($root, 'admin/assets/css/licora/components/core.css');
foreach ([
    'min-height:30px',
    'min-height:28px',
    'padding:6px 12px',
    'width:26px',
    '.ui-table-toolbar',
    '.ui-action-menu-panel',
    '.ui-confirm-card',
    '.ui-toast',
    '.ui-settings-grid',
    '.ui-scrollbar',
] as $marker) {
    v550_ok(strpos(str_replace(' ', '', $core), str_replace(' ', '', $marker)) !== false, 'compact UI marker missing: ' . $marker);
}

$adminFiles = glob($root . '/admin/*.php') ?: [];
foreach ($adminFiles as $path) {
    if (basename($path) === 'logout.php') { continue; }
    $text = (string)file_get_contents($path);
    v550_ok(strpos($text, 'Licora administration') === false, 'legacy topbar subtitle remains: ' . basename($path));
    v550_ok(strpos($text, 'License System') === false, 'legacy visible product name remains: ' . basename($path));
    v550_ok(strpos($text, 'class="form-text"') === false, 'helper-text block remains in compact admin UI: ' . basename($path));
    v550_ok(!preg_match('/\bconfirm\s*\(/', $text), 'native browser confirm remains in ' . basename($path));

    v550_ok(!preg_match('/[\x{0980}-\x{09FF}]/u', $text), 'Bengali text remains in active admin presentation source: ' . basename($path));
}
foreach (glob($root . '/admin/assets/js/*.js') ?: [] as $path) {
    v550_ok(!preg_match('/\bconfirm\s*\(/', (string)file_get_contents($path)), 'native browser confirm remains in ' . basename($path));
}

$license = v550_read($root, 'admin/license.php');
foreach (['id="licenseCreateModal"','data-license-create-mode="single"','data-license-create-mode="bulk"','id="license-table"','ui-table-toolbar','ui-action-menu-panel'] as $marker) {
    v550_ok(strpos($license, $marker) !== false, 'license compact contract missing: ' . $marker);
}
v550_ok(strpos($license, 'Create New License') === false, 'legacy persistent Create New License card remains');
v550_ok(strpos($license, 'Bulk License Create') === false, 'legacy persistent Bulk License Create card remains');

$device = v550_read($root, 'admin/device.php');
foreach (['data-ui-table-search="devices-table"','data-ui-table-status="devices-table"','data-ui-table-size="devices-table"','ui-action-menu-panel','ui-device-summary'] as $marker) {
    v550_ok(strpos($device, $marker) !== false, 'device compact contract missing: ' . $marker);
}


$apiKeys = v550_read($root, 'admin/api_keys.php');
foreach (['id="createApiKeyModal"','id="api-keys-table"','data-ui-table-search="api-keys-table"','ui-action-menu-panel'] as $marker) {
    v550_ok(strpos($apiKeys, $marker) !== false, 'API Keys compact contract missing: ' . $marker);
}
v550_ok(strpos($apiKeys, 'col-xl-4') === false, 'legacy narrow API Keys create column remains');

$clientApps = v550_read($root, 'admin/client_apps.php');
foreach (['id="createClientAppModal"','id="client-apps-list"','data-ui-table-search="client-apps-list"','ui-client-app-form'] as $marker) {
    v550_ok(strpos($clientApps, $marker) !== false, 'Client Apps compact contract missing: ' . $marker);
}
v550_ok(strpos($clientApps, 'col-xl-4') === false, 'legacy narrow Client Apps create column remains');

$settings = v550_read($root, 'admin/settings.php');
foreach (['default_license_hours','default_device_limit','license_min_hours','license_max_hours','log_retention_days','API & Integration','Cron Jobs','API v2 Signing','ajax/v2-public-key.php'] as $marker) {
    v550_ok(strpos($settings, $marker) !== false, 'settings truthful contract missing: ' . $marker);
}
foreach (['name="system_name"','name="api_base_url"','name="system_root_url"','name="license_warning_days"','name="device_inactive_minutes"','name="dashboard_rows"','name="api_rate_limit"','name="api_timeout_seconds"','name="admin_session_timeout_minutes"','name="enable_two_factor"','name="maintenance_mode"','name="enable_api_logging"'] as $marker) {
    v550_ok(strpos($settings, $marker) === false, 'stored-only setting remains editable: ' . $marker);
}
$keyEndpoint = v550_read($root, 'admin/ajax/v2-public-key.php');
v550_ok(strpos($keyEndpoint, 'publicKeyPem()') !== false, 'public-key download endpoint does not use publicKeyPem');
v550_ok(strpos($keyEndpoint, 'privateKeyPem') === false, 'private key must never be browser-downloadable');
v550_ok(strpos($keyEndpoint, 'privatePath()') === false, 'private key path must never be exposed by download endpoint');

$nav = v550_read($root, 'admin/includes/ui/navigation.php');
foreach (['audit.php','backup.php','health.php','about.php'] as $route) {
    v550_ok(strpos($nav, "'file' => '{$route}'") !== false, 'settings submenu route missing: ' . $route);
}
$about = v550_read($root, 'admin/about.php');
foreach (['Vib Tools','https://vib.tools/','vibtools/Licora','support@vib.tools','MIT License','assets/brand/logos/logo-lg.png'] as $marker) {
    v550_ok(strpos($about, $marker) !== false, 'About Licora verified metadata missing: ' . $marker);
}

$updater = v550_read($root, 'admin/assets/js/licora-updater.js');
v550_ok(strpos($updater, 'Licora is up to date') !== false, 'manual no-update feedback is missing');
v550_ok(strpos($updater, "window.LicoraUI.toast") !== false, 'updater does not use shared toast feedback');
$updaterPage = v550_read($root, 'admin/updates.php');
v550_ok(strpos($updaterPage, 'licora-updater-subtitle') === false, 'updater static subtitle remains');
v550_ok(strpos($updaterPage, 'ui-scrollbar') !== false, 'release-notes light scrollbar contract missing');

$builderTest = v550_read($root, 'tests/updater_builder_contract.py');
v550_ok(strpos($builderTest, 'sys.executable') !== false, 'builder contract must use the running Python interpreter');
v550_ok(strpos($builderTest, "['python3'") === false && strpos($builderTest, '["python3"') === false, 'builder contract still hardcodes python3');

$installer = v550_read($root, 'install.php');
v550_ok(strpos($installer, 'name="app_name" value="Licora"') !== false, 'installer must submit fixed Licora product name');
v550_ok(strpos($installer, '>Application Name<') === false, 'installer must not expose editable product name');
v550_ok(strpos($installer, 'assets/brand/logos/logo-md.png') !== false, 'installer brand asset is missing');

$requiredBrand = [
    'favicon/favicon-16.png','favicon/favicon-32.png','favicon/favicon-48.png','favicon/favicon.ico',
    'icons/icon-64.png','icons/icon-128.png','icons/icon-180.png','icons/icon-192.png','icons/icon-256.png','icons/icon-384.png','icons/icon-512.png',
    'images/Licora-icon.png','images/Licora-logo.png','logos/logo-sm.png','logos/logo-md.png','logos/logo-lg.png'
];
foreach ($requiredBrand as $rel) { v550_ok(is_file($root . '/admin/assets/brand/' . $rel), 'brand asset missing: ' . $rel); }

echo "Licora v5.5.0 compact UI/settings/branding contract checks passed.\n";
