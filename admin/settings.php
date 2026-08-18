<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/database.php';
require_once 'includes/ui/integration.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit(); }
$db = Database::getInstance();
$message = '';
$error = '';

function save_setting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :insert_value) ON DUPLICATE KEY UPDATE setting_value = :update_value");
    $stmt->execute([':setting_key'=>$key, ':insert_value'=>(string)$value, ':update_value'=>(string)$value]);
}

$editableDefaults = [
    'default_license_hours' => '24',
    'default_device_limit' => '1',
    'license_min_hours' => '1',
    'license_max_hours' => '8760',
    'log_retention_days' => '90',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $settingsToSave = [
        'default_license_hours' => max(1, (int)($_POST['default_license_hours'] ?? $editableDefaults['default_license_hours'])),
        'default_device_limit' => max(1, (int)($_POST['default_device_limit'] ?? $editableDefaults['default_device_limit'])),
        'license_min_hours' => max(1, (int)($_POST['license_min_hours'] ?? $editableDefaults['license_min_hours'])),
        'license_max_hours' => max(1, (int)($_POST['license_max_hours'] ?? $editableDefaults['license_max_hours'])),
        'log_retention_days' => max(1, (int)($_POST['log_retention_days'] ?? $editableDefaults['log_retention_days'])),
    ];
    if ($settingsToSave['license_max_hours'] < $settingsToSave['license_min_hours']) {
        $settingsToSave['license_max_hours'] = $settingsToSave['license_min_hours'];
    }
    if ($settingsToSave['default_license_hours'] < $settingsToSave['license_min_hours']) {
        $settingsToSave['default_license_hours'] = $settingsToSave['license_min_hours'];
    }
    if ($settingsToSave['default_license_hours'] > $settingsToSave['license_max_hours']) {
        $settingsToSave['default_license_hours'] = $settingsToSave['license_max_hours'];
    }
    try {
        foreach ($settingsToSave as $key => $value) { save_setting($db, $key, $value); }
        AdminHelpers::audit('settings', null, 'settings_updated', 'Runtime-backed settings updated');
        $message = 'Settings updated successfully';
    } catch (Throwable $e) {
        error_log('Settings update failed: ' . $e->getMessage());
        $error = 'Settings update failed.';
    }
}

$settings = $editableDefaults;
foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
$endpoints = licora_ui_endpoints();
$cronJobs = licora_ui_cron_jobs();
$v2Keys = licora_ui_v2_key_status();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Settings · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero"><h2><i class="bi bi-gear"></i> Settings</h2><?php if (AdminHelpers::canManage()): ?><button type="submit" form="settings-form" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button><?php endif; ?></div>
    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <nav class="ui-shortcuts ui-shortcut-grid mb-2" aria-label="Management shortcuts">
        <a href="license.php" class="btn btn-outline-secondary"><i class="bi bi-key"></i> Licenses</a>
        <a href="device.php" class="btn btn-outline-secondary"><i class="bi bi-devices"></i> Devices</a>
        <a href="api_keys.php" class="btn btn-outline-secondary"><i class="bi bi-key-fill"></i> API Keys</a>
        <a href="logs.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Logs</a>
        <a href="audit.php" class="btn btn-outline-secondary"><i class="bi bi-journal-text"></i> Audit</a>
        <a href="backup.php" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Backup</a>
        <a href="health.php" class="btn btn-outline-secondary"><i class="bi bi-heart-pulse"></i> Health</a>
    </nav>

    <form method="POST" id="settings-form" class="needs-validation" novalidate>
        <input type="hidden" name="update_settings" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
        <div class="ui-settings-grid ui-settings-grid-summary">
            <section class="ui-settings-section">
                <div class="ui-settings-section-header"><span><i class="bi bi-sliders"></i> License Defaults</span></div>
                <div class="ui-settings-section-body"><div class="ui-form-grid">
                    <div><label class="form-label">Default License Hours</label><input type="number" class="form-control" name="default_license_hours" value="<?php echo Security::escape($settings['default_license_hours']); ?>" min="1" required></div>
                    <div><label class="form-label">Default Device Limit</label><input type="number" class="form-control" name="default_device_limit" value="<?php echo Security::escape($settings['default_device_limit']); ?>" min="1" max="100" required></div>
                    <div><label class="form-label">Minimum License Hours</label><input type="number" class="form-control" name="license_min_hours" value="<?php echo Security::escape($settings['license_min_hours']); ?>" min="1" required></div>
                    <div><label class="form-label">Maximum License Hours</label><input type="number" class="form-control" name="license_max_hours" value="<?php echo Security::escape($settings['license_max_hours']); ?>" min="1" required></div>
                </div></div>
            </section>

            <section class="ui-settings-section">
                <div class="ui-settings-section-header"><span><i class="bi bi-clock-history"></i> Operations</span></div>
                <div class="ui-settings-section-body"><div class="ui-form-grid"><div><label class="form-label">Log Retention Days</label><input type="number" class="form-control" name="log_retention_days" value="<?php echo Security::escape($settings['log_retention_days']); ?>" min="1" max="3650" required></div><div><label class="form-label">Environment</label><input type="text" class="form-control" value="<?php echo Security::escape(ENVIRONMENT); ?>" readonly></div><div><label class="form-label">App Version</label><input type="text" class="form-control" value="<?php echo Security::escape(APP_VERSION); ?>" readonly></div><div><label class="form-label">Server Time</label><input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly></div></div></div>
            </section>
        </div>

        <div class="ui-settings-detail-grid mt-2">
            <section class="ui-settings-section ui-settings-integration">
                <div class="ui-settings-section-header"><span><i class="bi bi-hdd-network"></i> API & Integration</span></div>
                <div class="ui-settings-section-body"><div class="ui-info-list">
                    <?php foreach ($endpoints as $label => $value): ?><div class="ui-info-row"><span class="ui-info-label"><?php echo Security::escape($label); ?></span><code class="ui-info-value"><?php echo Security::escape($value); ?></code><button type="button" class="btn btn-outline-secondary ui-copy-button" data-copy="<?php echo Security::escape($value); ?>" aria-label="Copy <?php echo Security::escape($label); ?>"><i class="bi bi-clipboard"></i></button></div><?php endforeach; ?>
                    <div class="ui-info-row"><span class="ui-info-label">API v1 Version</span><code class="ui-info-value"><?php echo Security::escape(API_VERSION); ?></code><span></span></div>
                    <div class="ui-info-row"><span class="ui-info-label">API v1 Rate Limit</span><code class="ui-info-value"><?php echo (int)API_RATE_LIMIT; ?> requests / hour</code><span></span></div>
                    <div class="ui-info-row"><span class="ui-info-label">API v2 Global Rate Limit</span><code class="ui-info-value"><?php echo (int)LICENSE_V2_RATE_LIMIT; ?> requests / hour</code><span></span></div>
                    <div class="ui-info-row"><span class="ui-info-label">API v2 Clock Skew</span><code class="ui-info-value"><?php echo (int)LICENSE_V2_CLOCK_SKEW; ?> seconds</code><span></span></div>
                    <div class="ui-info-row"><span class="ui-info-label">API v2 Max Body</span><code class="ui-info-value"><?php echo (int)LICENSE_V2_MAX_BODY_BYTES; ?> bytes</code><span></span></div>
                    <div class="ui-info-row"><span class="ui-info-label">API v2 HTTPS</span><code class="ui-info-value"><?php echo filter_var(LICENSE_V2_REQUIRE_HTTPS, FILTER_VALIDATE_BOOLEAN) ? 'Required' : 'Not required'; ?></code><span></span></div>
                </div></div>
            </section>

            <div class="ui-settings-stack">
                <section class="ui-settings-section">
                    <div class="ui-settings-section-header"><span><i class="bi bi-terminal"></i> Cron Jobs</span></div>
                    <div class="ui-settings-section-body"><div class="ui-info-list">
                        <?php foreach ($cronJobs as $label => $job): ?><div class="ui-info-row"><span class="ui-info-label"><?php echo Security::escape($label); ?></span><code class="ui-info-value"><?php echo Security::escape($job['command']); ?></code><button type="button" class="btn btn-outline-secondary ui-copy-button" data-copy="<?php echo Security::escape($job['command']); ?>" aria-label="Copy <?php echo Security::escape($label); ?> cron command"><i class="bi bi-clipboard"></i></button></div><?php endforeach; ?>
                    </div></div>
                </section>

                <section class="ui-settings-section">
                    <div class="ui-settings-section-header"><span><i class="bi bi-shield-check"></i> API v2 Signing</span></div>
                    <div class="ui-settings-section-body"><div class="ui-info-list">
                        <div class="ui-info-row"><span class="ui-info-label">Key ID</span><code class="ui-info-value"><?php echo Security::escape($v2Keys['key_id']); ?></code><button type="button" class="btn btn-outline-secondary ui-copy-button" data-copy="<?php echo Security::escape($v2Keys['key_id']); ?>" aria-label="Copy key ID"><i class="bi bi-clipboard"></i></button></div>
                        <div class="ui-info-row"><span class="ui-info-label">Private Key</span><span class="ui-info-value"><span class="badge bg-<?php echo $v2Keys['private_configured'] ? 'success' : 'danger'; ?>"><?php echo $v2Keys['private_configured'] ? 'Configured' : 'Missing'; ?></span></span><span></span></div>
                        <div class="ui-info-row"><span class="ui-info-label">Public Key</span><span class="ui-info-value"><span class="badge bg-<?php echo $v2Keys['public_configured'] ? 'success' : 'danger'; ?>"><?php echo $v2Keys['public_configured'] ? 'Configured' : 'Missing'; ?></span></span><?php if ($v2Keys['public_configured'] && AdminHelpers::canDelete()): ?><a class="btn btn-outline-secondary ui-copy-button" href="ajax/v2-public-key.php" aria-label="Download API v2 public key"><i class="bi bi-download"></i></a><?php else: ?><span></span><?php endif; ?></div>
                        <div class="ui-info-row"><span class="ui-info-label">Pair Status</span><span class="ui-info-value"><span class="badge bg-<?php echo $v2Keys['pair_valid'] ? 'success' : 'warning'; ?>"><?php echo $v2Keys['pair_valid'] ? 'Verified' : 'Unavailable'; ?></span></span><span></span></div>
                        <div class="ui-info-row"><span class="ui-info-label">Public Fingerprint</span><code class="ui-info-value"><?php echo Security::escape($v2Keys['public_fingerprint'] ?: 'Unavailable'); ?></code><?php if ($v2Keys['public_fingerprint']): ?><button type="button" class="btn btn-outline-secondary ui-copy-button" data-copy="<?php echo Security::escape($v2Keys['public_fingerprint']); ?>" aria-label="Copy public-key fingerprint"><i class="bi bi-clipboard"></i></button><?php else: ?><span></span><?php endif; ?></div>
                    </div></div>
                </section>
            </div>
        </div>
        <?php if (AdminHelpers::canManage()): ?><div class="ui-save-bar mt-2"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button></div><?php endif; ?>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
