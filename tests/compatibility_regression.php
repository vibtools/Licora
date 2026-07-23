<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$expectedImmutableHashes = [
    'database.sql' => '6a39d7fbb8a48b51cba1118457465b4c31eea1023d18cbeb803f8f2e84430fa6',
    'migration.sql' => '3da777f815bb3bc5e766be01cc5872ef1630623e4da3e4fa8f4f0b0716fa6db0',
    'migration-v4.sql' => 'a45793f40329da0b48e71a2c2da19b9b1c17457fe00000d18007ea8cd59cb243',
    'migration-v5.sql' => '2622592c14de6621cf0695dd76fb21957cc1ea49dcf338c44e0c4f3cdd93eef0',
    'migration-v5-fix.sql' => '16cf4e0beae4e13e91003c439377893becaec8876718e8e02dad2801104b96f3',
    'migration-v5-hotfix.sql' => 'e735acebfdad6254ca8fc6d6bbec28af2d35db41d6bf43fa77da1e2605e8ed62',
    'cron/cleanup.php' => '62467804576ecf6d29cb9a35a92eb8d8a9bae76490d328784ec0c2aa478e3fc5',
    'cron/check_expiring.php' => 'c52715837a84512f0378d14fc2e57024d928435d1a75c0014d5af616e58a41c2',
    'admin/assets/css/admin-ui.css' => 'a18773b97f793cd86c011df1fe9bf472613749f26660a9e9a4220582e89d5913',
    'admin/assets/js/admin-ui.js' => '5722297eec36a653241520b19a2217f9c4653aa43f6c1db6d05758264201e5da'
];

$lineEndingNeutralPaths = [
    'cron/cleanup.php',
    'cron/check_expiring.php'
];

foreach ($expectedImmutableHashes as $path => $expectedHash) {
    $full = $root . '/' . $path;
    $assert(is_file($full), "required immutable file exists: {$path}");
    if (is_file($full)) {
        $content = (string)file_get_contents($full);
        $actualHash = in_array($path, $lineEndingNeutralPaths, true)
            ? hash('sha256', str_replace(["\r\n", "\r"], "\n", $content))
            : hash('sha256', $content);
        $assert($actualHash === $expectedHash, "immutable hash preserved: {$path}");
    }
}

$publicRoutes = [
    'index.php',
    'install.php',
    'install/index.php',
    'api/verify.php',
    'api/check_license.php'
];
foreach ($publicRoutes as $path) {
    $assert(is_file($root . '/' . $path), "public route preserved: {$path}");
}

$adminRoutes = [
    'admin/index.php',
    'admin/login.php',
    'admin/logout.php',
    'admin/admins.php',
    'admin/api_keys.php',
    'admin/license.php',
    'admin/device.php',
    'admin/logs.php',
    'admin/audit.php',
    'admin/settings.php',
    'admin/backup.php',
    'admin/health.php'
];
foreach ($adminRoutes as $path) {
    $assert(is_file($root . '/' . $path), "admin route preserved: {$path}");
}

$config = $read('includes/config.php');
$assert(strpos($config, "env_value('APP_VERSION', '5.1.1')") !== false, 'application version is v5.1.1');
$assert(strpos($config, "if (!defined('DB_PORT'))") !== false, 'database port support is additive');
$assert(strpos($config, 'licora_enforce_installation_guard') !== false, 'first-run guard is enabled before application boot');

$security = $read('includes/security.php');
$assert(strpos($security, '/^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/') !== false, 'license format unchanged');
$assert(strpos($security, "'v2:' . base64_encode") !== false, 'versioned encryption present');
$assert(strpos($security, 'Legacy v1 payload support') !== false, 'legacy encryption compatibility present');
$assert(strpos($security, 'GET_LOCK(:lock_name, 2)') !== false, 'rate-limit advisory lock present');

$functions = $read('includes/functions.php');
$assert(strpos($functions, 'for ($i = 0; $i < 4; $i++)') !== false, 'license segment count unchanged');
$assert(strpos($functions, 'strtoupper(bin2hex(random_bytes(4)))') !== false, 'license generation unchanged');
$assert(strpos($functions, "return ['success' => false, 'message' => 'Invalid license key'];") !== false, 'license validation failure contract preserved');

$verify = $read('api/verify.php');
foreach ([
    "'success' => false",
    "'message' => 'Method not allowed'",
    "'message' => 'API key is required'",
    "'message' => 'Invalid API key'",
    "'license' => [",
    '\'key\' => $result[\'license\'][\'license_key\']',
    '\'expires\' => $result[\'license\'][\'expires_at\']',
    '\'device_limit\' => $result[\'license\'][\'device_limit\']',
    '\'devices_used\' => $result[\'license\'][\'total_devices\']',
    '\'status\' => $result[\'license\'][\'status\']',
    "'server_version' => APP_VERSION"
] as $snippet) {
    $assert(strpos($verify, $snippet) !== false, 'primary API contract marker preserved: ' . $snippet);
}
$assert(strpos($verify, 'Security::normalizeAPIKeyCredential($apiKey)') !== false, 'Bearer normalization enabled');

$legacy = $read('api/check_license.php');
foreach ([
    "'message' => 'Rate limit exceeded. Try again later.'",
    "'message' => 'Invalid license key'",
    "'message' => 'Invalid request method'",
    '$system->verifyLicense($license_key, $device_hash)'
] as $snippet) {
    $assert(strpos($legacy, $snippet) !== false, 'legacy API contract preserved: ' . $snippet);
}

$licenseAdmin = $read('admin/license.php');
$assert(
    preg_match('/if \(\$bulkAction === \'export\'\) \{\s*AdminHelpers::requireManage\(\);/s', $licenseAdmin) === 1,
    'viewer license export is blocked server-side'
);

$apiAdmin = $read('admin/api_keys.php');
$assert(
    preg_match('/isset\(\$_POST\[\'test_api_key\'\]\)\) \{\s*AdminHelpers::requireManage\(\);/s', $apiAdmin) === 1,
    'viewer API key test action is blocked server-side'
);
$assert(strpos($apiAdmin, 'Restricted for Viewer role') !== false, 'viewer secret display is restricted');

$auth = $read('includes/auth.php');
foreach ([
    "\$_SESSION['admin_id']",
    "\$_SESSION['admin_username']",
    "\$_SESSION['admin_email']",
    "\$_SESSION['admin_role']",
    "\$_SESSION['admin_logged_in']",
    "\$_SESSION['login_time']",
    "\$_SESSION['session_ip']",
    "\$_SESSION['session_user_agent']"
] as $sessionKey) {
    $assert(strpos($auth, $sessionKey) !== false, 'session structure preserved: ' . $sessionKey);
}
$assert(strpos($auth, '$timeout = 30 * 60;') !== false, 'existing 30-minute session timeout enforced');

$installer = $read('install.php');
$installationHelper = $read('includes/installation.php');
foreach ([
    'installer_csrf_token',
    'Complete Installation',
    'Installation already completed.',
    'Step <?php echo $step; ?> of 10',
] as $marker) {
    $assert(strpos($installer, $marker) !== false, 'smart installer marker present: ' . $marker);
}
foreach ([
    'licora_enforce_installation_guard',
    'licora_installer_finalize',
    'licora_installer_execute_schema',
    'licora_installation_write_flag',
    'Table prefixes are not supported by the frozen Licora database contract.',
] as $marker) {
    $assert(strpos($installationHelper, $marker) !== false, 'installation helper marker present: ' . $marker);
}
$assert(strpos($installationHelper, "'database.sql'") !== false, 'existing database schema is the installer source');
$assert(strpos($installationHelper, "'products'") === false, 'installer creates no products table');
$assert(strpos($installationHelper, "'customers'") === false, 'installer creates no customers table');
$assert(strpos($installationHelper, "'roles'") === false, 'installer creates no roles table');
$assert(strpos($installationHelper, "'permissions'") === false, 'installer creates no permissions table');

$database = $read('includes/database.php');
$assert(strpos($database, 'defined(\'DB_PORT\') ? (int)DB_PORT : 3306') !== false, 'database port defaults preserve existing connections');

if ($failures !== []) {
    fwrite(STDERR, "Compatibility regression test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Compatibility regression checks passed.\n";
