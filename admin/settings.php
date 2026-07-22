<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/database.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$db = Database::getInstance();
$message = '';
$error = '';

function detected_api_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/settings.php')), '/\\');
    if ($base === '/' || $base === '.') { $base = ''; }
    return $scheme . '://' . $host . $base . '/api/verify.php';
}

function detected_root_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/settings.php')), '/\\');
    if ($base === '/' || $base === '.') { $base = ''; }
    return $scheme . '://' . $host . $base . '/';
}

function save_setting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :insert_value) ON DUPLICATE KEY UPDATE setting_value = :update_value");
    $stmt->execute([
        ':setting_key' => $key,
        ':insert_value' => (string)$value,
        ':update_value' => (string)$value
    ]);
}

$detectedApiBaseUrl = detected_api_base_url();
$detectedRootUrl = detected_root_url();

$defaults = [
    'system_name' => 'License Management System',
    'timezone' => 'Asia/Dhaka',
    'default_license_hours' => '24',
    'default_device_limit' => '1',
    'api_rate_limit' => '1000',
    'log_retention_days' => '90',
    'enable_two_factor' => '0',
    'maintenance_mode' => '0',
    'api_base_url' => $detectedApiBaseUrl,
    'license_key_prefix' => '',
    'license_min_hours' => '1',
    'license_max_hours' => '8760',
    'device_inactive_minutes' => '5',
    'api_timeout_seconds' => '10',
    'dashboard_rows' => '10',
    'admin_session_timeout_minutes' => '120',
    'enable_api_logging' => '1',
    'show_server_version' => '1',
    'auto_detect_base_url' => '1',
    'system_root_url' => $detectedRootUrl,
    'support_email' => '',
    'license_warning_days' => '7'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');

    $settingsToSave = [
        'system_name' => Security::sanitize($_POST['system_name'] ?? $defaults['system_name']),
        'timezone' => Security::sanitize($_POST['timezone'] ?? $defaults['timezone']),
        'default_license_hours' => max(1, (int)($_POST['default_license_hours'] ?? $defaults['default_license_hours'])),
        'default_device_limit' => max(1, (int)($_POST['default_device_limit'] ?? $defaults['default_device_limit'])),
        'api_rate_limit' => max(1, (int)($_POST['api_rate_limit'] ?? $defaults['api_rate_limit'])),
        'log_retention_days' => max(1, (int)($_POST['log_retention_days'] ?? $defaults['log_retention_days'])),
        'enable_two_factor' => isset($_POST['enable_two_factor']) ? 1 : 0,
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
        'api_base_url' => Security::sanitize($_POST['api_base_url'] ?? $detectedApiBaseUrl),
        'license_key_prefix' => Security::sanitize($_POST['license_key_prefix'] ?? ''),
        'license_min_hours' => max(1, (int)($_POST['license_min_hours'] ?? $defaults['license_min_hours'])),
        'license_max_hours' => max(1, (int)($_POST['license_max_hours'] ?? $defaults['license_max_hours'])),
        'device_inactive_minutes' => max(1, (int)($_POST['device_inactive_minutes'] ?? $defaults['device_inactive_minutes'])),
        'api_timeout_seconds' => max(1, (int)($_POST['api_timeout_seconds'] ?? $defaults['api_timeout_seconds'])),
        'dashboard_rows' => max(1, (int)($_POST['dashboard_rows'] ?? $defaults['dashboard_rows'])),
        'admin_session_timeout_minutes' => max(5, (int)($_POST['admin_session_timeout_minutes'] ?? $defaults['admin_session_timeout_minutes'])),
        'enable_api_logging' => isset($_POST['enable_api_logging']) ? 1 : 0,
        'show_server_version' => isset($_POST['show_server_version']) ? 1 : 0,
        'auto_detect_base_url' => isset($_POST['auto_detect_base_url']) ? 1 : 0,
        'system_root_url' => Security::sanitize($_POST['system_root_url'] ?? $detectedRootUrl),
        'support_email' => Security::sanitize($_POST['support_email'] ?? ''),
        'license_warning_days' => max(0, (int)($_POST['license_warning_days'] ?? $defaults['license_warning_days']))
    ];

    if ($settingsToSave['license_max_hours'] < $settingsToSave['license_min_hours']) {
        $settingsToSave['license_max_hours'] = $settingsToSave['license_min_hours'];
    }

    try {
        foreach ($settingsToSave as $key => $value) {
            save_setting($db, $key, $value);
        }
        $message = 'Settings updated successfully';
        if (!empty($settingsToSave['timezone'])) {
            date_default_timezone_set($settingsToSave['timezone']);
        }
    } catch (Exception $e) {
        $error = 'Settings update failed. Please check database permissions and settings table unique key.';
    }
}

$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = $defaults;
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$currentApiBaseUrl = $settings['api_base_url'] ?? $detectedApiBaseUrl;
$currentRootUrl = $settings['system_root_url'] ?? $detectedRootUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div><h2><i class="bi bi-gear"></i> Settings</h2><p>Control license defaults, API base URL, UI preferences, cleanup rules, and system flags from one panel.</p></div>
        <div class="d-flex gap-2 flex-wrap"><a href="api_keys.php" class="btn btn-light"><i class="bi bi-key-fill"></i> API Keys</a><span class="badge bg-light text-dark align-self-center"><i class="bi bi-shield-check"></i> Admin Area</span></div>
    </div>
    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <form method="POST" id="settings-form" class="needs-validation" novalidate>
        <input type="hidden" name="update_settings" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card settings-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="bi bi-sliders"></i> Core License Controls</h5></div><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">System Name</label><input type="text" class="form-control" name="system_name" value="<?php echo Security::escape($settings['system_name']); ?>" required></div>
                        <div class="col-md-3"><label class="form-label">Default Hours</label><input type="number" class="form-control" name="default_license_hours" value="<?php echo Security::escape($settings['default_license_hours']); ?>" min="1" max="8760"></div>
                        <div class="col-md-3"><label class="form-label">Default Devices</label><input type="number" class="form-control" name="default_device_limit" value="<?php echo Security::escape($settings['default_device_limit']); ?>" min="1" max="100"></div>
                        <div class="col-md-4"><label class="form-label">License Key Prefix</label><input type="text" class="form-control" name="license_key_prefix" value="<?php echo Security::escape($settings['license_key_prefix']); ?>" maxlength="24" placeholder="Optional"></div>
                        <div class="col-md-4"><label class="form-label">Minimum License Hours</label><input type="number" class="form-control" name="license_min_hours" value="<?php echo Security::escape($settings['license_min_hours']); ?>" min="1"></div>
                        <div class="col-md-4"><label class="form-label">Maximum License Hours</label><input type="number" class="form-control" name="license_max_hours" value="<?php echo Security::escape($settings['license_max_hours']); ?>" min="1"></div>
                        <div class="col-md-4"><label class="form-label">License Warning Days</label><input type="number" class="form-control" name="license_warning_days" value="<?php echo Security::escape($settings['license_warning_days']); ?>" min="0" max="365"></div>
                        <div class="col-md-4"><label class="form-label">Inactive Device Minutes</label><input type="number" class="form-control" name="device_inactive_minutes" value="<?php echo Security::escape($settings['device_inactive_minutes']); ?>" min="1" max="10080"></div>
                        <div class="col-md-4"><label class="form-label">Dashboard Rows</label><input type="number" class="form-control" name="dashboard_rows" value="<?php echo Security::escape($settings['dashboard_rows']); ?>" min="1" max="100"></div>
                    </div>
                </div></div>
                <div class="card settings-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="bi bi-hdd-network"></i> API & URL Controls</h5></div><div class="card-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">API Base URL</label><div class="input-group"><input type="text" class="form-control" name="api_base_url" id="api-base-url" value="<?php echo Security::escape($currentApiBaseUrl); ?>" required><button class="btn btn-outline-primary" type="button" data-autofill-target="api-base-url" data-autofill-value="<?php echo Security::escape($detectedApiBaseUrl); ?>"><i class="bi bi-magic"></i> Auto Detect</button></div><small class="text-muted">Detected: <?php echo Security::escape($detectedApiBaseUrl); ?></small></div>
                        <div class="col-12"><label class="form-label">System Root URL</label><div class="input-group"><input type="text" class="form-control" name="system_root_url" id="system-root-url" value="<?php echo Security::escape($currentRootUrl); ?>"><button class="btn btn-outline-primary" type="button" data-autofill-target="system-root-url" data-autofill-value="<?php echo Security::escape($detectedRootUrl); ?>"><i class="bi bi-magic"></i> Auto Detect</button></div></div>
                        <div class="col-md-4"><label class="form-label">API Rate Limit / Hour</label><input type="number" class="form-control" name="api_rate_limit" value="<?php echo Security::escape($settings['api_rate_limit']); ?>" min="1" max="100000"></div>
                        <div class="col-md-4"><label class="form-label">API Timeout Seconds</label><input type="number" class="form-control" name="api_timeout_seconds" value="<?php echo Security::escape($settings['api_timeout_seconds']); ?>" min="1" max="120"></div>
                        <div class="col-md-4"><label class="form-label">Log Retention Days</label><input type="number" class="form-control" name="log_retention_days" value="<?php echo Security::escape($settings['log_retention_days']); ?>" min="1" max="3650"></div>
                    </div>
                </div></div>
                <div class="card settings-card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-lock"></i> Admin & System Flags</h5></div><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Timezone</label><select class="form-select" name="timezone"><?php $timezones=DateTimeZone::listIdentifiers(); $current=$settings['timezone']; foreach($timezones as $tz){$selected=$tz===$current?'selected':''; echo '<option value="'.Security::escape($tz).'" '.$selected.'>'.Security::escape($tz).'</option>'; } ?></select></div>
                        <div class="col-md-6"><label class="form-label">Admin Session Timeout Minutes</label><input type="number" class="form-control" name="admin_session_timeout_minutes" value="<?php echo Security::escape($settings['admin_session_timeout_minutes']); ?>" min="5" max="10080"></div>
                        <div class="col-md-6"><label class="form-label">Support Email</label><input type="email" class="form-control" name="support_email" value="<?php echo Security::escape($settings['support_email']); ?>" placeholder="support@example.com"></div>
                        <div class="col-md-6 d-grid gap-2 align-content-end">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enable_two_factor" <?php echo !empty($settings['enable_two_factor']) ? 'checked' : ''; ?>><label class="form-check-label">Enable Two-Factor Authentication</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="maintenance_mode" <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>><label class="form-check-label">Maintenance Mode</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enable_api_logging" <?php echo !empty($settings['enable_api_logging']) ? 'checked' : ''; ?>><label class="form-check-label">Enable API Logging</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_server_version" <?php echo !empty($settings['show_server_version']) ? 'checked' : ''; ?>><label class="form-check-label">Show Server Version in Admin</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_detect_base_url" <?php echo !empty($settings['auto_detect_base_url']) ? 'checked' : ''; ?>><label class="form-check-label">Prefer Auto Detected URL</label></div>
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card mb-4"><div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle"></i> Auto Detected Information</h5></div><div class="card-body"><div class="settings-summary"><div><span>Detected API URL</span><code><?php echo Security::escape($detectedApiBaseUrl); ?></code></div><div><span>Detected Root URL</span><code><?php echo Security::escape($detectedRootUrl); ?></code></div><div><span>Environment</span><strong><?php echo Security::escape(ENVIRONMENT); ?></strong></div><div><span>App Version</span><strong><?php echo Security::escape(APP_VERSION); ?></strong></div><div><span>Server Time</span><strong><?php echo date('Y-m-d H:i:s'); ?></strong></div></div></div></div>
                <div class="card mb-4"><div class="card-header"><h5 class="mb-0"><i class="bi bi-diagram-3"></i> Management Shortcuts</h5></div><div class="card-body d-grid gap-2"><a href="license.php" class="btn btn-outline-primary"><i class="bi bi-key"></i> License Control</a><a href="device.php" class="btn btn-outline-info"><i class="bi bi-devices"></i> Device Control</a><a href="logs.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Logs Control</a><a href="api_keys.php" class="btn btn-outline-success"><i class="bi bi-key-fill"></i> API Key Control</a><a href="audit.php" class="btn btn-outline-dark"><i class="bi bi-journal-text"></i> Audit Trail</a><a href="backup.php" class="btn btn-outline-dark"><i class="bi bi-download"></i> Backup & Export</a><a href="health.php" class="btn btn-outline-dark"><i class="bi bi-heart-pulse"></i> System Health</a></div></div>
                <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-save"></i> Save Settings</button>
            </div>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
