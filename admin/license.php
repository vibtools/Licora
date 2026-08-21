<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$system = new LicenseSystem();
$db = Database::getInstance();

$success = '';
$error = '';
$search = $_GET['search'] ?? '';
$schemaReady = AdminHelpers::ensureV5Schema();
if (!$schemaReady) {
    $error = 'License App/API Key binding columns are not ready. Please run migration-v5.sql or check database ALTER permission.';
}

$settingsRows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$adminSettings = [];
foreach ($settingsRows as $settingRow) { $adminSettings[$settingRow['setting_key']] = $settingRow['setting_value']; }
$defaultLicenseHours = (int)($adminSettings['default_license_hours'] ?? 24);
$defaultDeviceLimit = (int)($adminSettings['default_device_limit'] ?? 1);
$minLicenseHours = (int)($adminSettings['license_min_hours'] ?? 1);
$maxLicenseHours = (int)($adminSettings['license_max_hours'] ?? 8760);
if ($minLicenseHours < 1) { $minLicenseHours = 1; }
if ($maxLicenseHours < $minLicenseHours) { $maxLicenseHours = $minLicenseHours; }
if ($defaultLicenseHours < $minLicenseHours) { $defaultLicenseHours = $minLicenseHours; }
if ($defaultLicenseHours > $maxLicenseHours) { $defaultLicenseHours = $maxLicenseHours; }
if ($defaultDeviceLimit < 1) { $defaultDeviceLimit = 1; }
$appOptions = [];
try {
    $appOptions = $db->query("SELECT id, name, COALESCE(app_name, '') AS app_name, COALESCE(scope_label, '') AS scope_label, is_active FROM api_keys WHERE is_active = 1 ORDER BY COALESCE(NULLIF(app_name,''), name), name")->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() === '42S22') {
        $appOptions = $db->query("SELECT id, name, '' AS app_name, '' AS scope_label, is_active FROM api_keys WHERE is_active = 1 ORDER BY name")->fetchAll();
    } else {
        $appOptions = [];
    }
} catch (Exception $e) { $appOptions = []; }
$v2AppOptions = [];
try {
    if (AdminHelpers::tableExists('v2_client_apps')) {
        $v2AppOptions = $db->query("SELECT app_id, display_name FROM v2_client_apps WHERE is_active = 1 ORDER BY display_name, app_id")->fetchAll();
    }
} catch (Exception $e) { $v2AppOptions = []; }
$v2AllowedAppIds = array_values(array_filter(array_map(static function ($row) { return (string)($row['app_id'] ?? ''); }, $v2AppOptions)));

// Create license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_license'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $hours = (int)$_POST['hours'];
    $device_limit = (int)$_POST['device_limit'];
    $notes = Security::sanitize($_POST['notes'] ?? '');
    $app_scope = Security::sanitize($_POST['app_scope'] ?? '');
    $license_api_key_id = (int)($_POST['license_api_key_id'] ?? 0);
    if ($license_api_key_id <= 0 && $app_scope !== '' && !in_array($app_scope, $v2AllowedAppIds, true)) {
        $error = 'Selected API v2 client application is not active or does not exist';
        $app_scope = '';
    }
    if ($license_api_key_id > 0) {
        try {
            $apiScopeStmt = $db->prepare("SELECT id, COALESCE(app_name, '') AS app_name, COALESCE(scope_label, '') AS scope_label, name FROM api_keys WHERE id = :id AND is_active = 1 LIMIT 1");
            $apiScopeStmt->execute([':id' => $license_api_key_id]);
            $selectedApiKey = $apiScopeStmt->fetch();
        } catch (PDOException $e) {
            $apiScopeStmt = $db->prepare("SELECT id, '' AS app_name, '' AS scope_label, name FROM api_keys WHERE id = :id AND is_active = 1 LIMIT 1");
            $apiScopeStmt->execute([':id' => $license_api_key_id]);
            $selectedApiKey = $apiScopeStmt->fetch();
        }
        if ($selectedApiKey) {
            $app_scope = trim($selectedApiKey['app_name'] ?: ($selectedApiKey['scope_label'] ?: $selectedApiKey['name']));
        } else {
            $error = 'Selected API key/app is not active or does not exist';
            $license_api_key_id = 0;
        }
    }
    
    $result = $error ? ['success' => false, 'message' => $error] : $system->createLicense($hours, $device_limit, $_SESSION['admin_id'], $notes, $app_scope, $license_api_key_id ?: null);
    
    if ($result['success']) {
        $success = "License created successfully: <code>{$result['license_key']}</code>";
    } else {
        $error = $result['message'];
    }
}

// Suspend/activate license
if (isset($_GET['action']) && isset($_GET['id'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    $status = ($action === 'suspend') ? 'suspended' : 'active';
    $system->updateLicenseStatus($id, $status);
    
    $success = "License status updated to {$status}";
}

// Extend license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_license'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $license_id = (int)$_POST['license_id'];
    $hours = (int)$_POST['extend_hours'];
    
    if ($system->extendLicense($license_id, $hours)) {
        $success = "License extended by {$hours} hours";
    } else {
        $error = "Failed to extend license";
    }
}

// Blacklist license
if (isset($_GET['blacklist']) && isset($_GET['id'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $license_id = (int)$_GET['id'];
    
    // Get license key
    $stmt = $db->prepare("SELECT license_key FROM licenses WHERE id = :id");
    $stmt->execute([':id' => $license_id]);
    $license = $stmt->fetch();
    
    if ($license) {
        $system->blacklistLicense($license['license_key'], 'Admin blacklisted', $_SESSION['admin_id']);
        $success = "License blacklisted successfully";
    }
}



// Delete license
if (isset($_GET['delete_license']) && isset($_GET['id'])) {
    AdminHelpers::requireDelete();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $license_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM licenses WHERE id = :id");
    $stmt->execute([':id' => $license_id]);
    AdminHelpers::audit('license', $license_id, 'license_deleted', 'License deleted');
    $success = "License deleted successfully";
}


// Bulk license actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $ids = array_values(array_filter(array_map('intval', $_POST['license_ids'] ?? [])));
    $bulkAction = $_POST['bulk_action'] ?? '';
    if ($bulkAction === 'export') {
        AdminHelpers::requireManage();
        if (empty($ids)) { $stmt = $db->query("SELECT id, license_key, status, expires_at, device_limit, total_devices, created_at, notes FROM licenses ORDER BY created_at DESC"); }
        else { $ph = implode(',', array_fill(0, count($ids), '?')); $stmt = $db->prepare("SELECT id, license_key, status, expires_at, device_limit, total_devices, created_at, notes FROM licenses WHERE id IN ($ph) ORDER BY created_at DESC"); $stmt->execute($ids); }
        AdminHelpers::csv('licenses-export.csv', ['id','license_key','status','expires_at','device_limit','total_devices','created_at','notes'], $stmt->fetchAll(PDO::FETCH_NUM));
    }
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if (in_array($bulkAction, ['activate','suspend'], true)) { AdminHelpers::requireManage(); $status = $bulkAction === 'activate' ? 'active' : 'suspended'; $stmt=$db->prepare("UPDATE licenses SET status=?, updated_at=NOW() WHERE id IN ($ph)"); $stmt->execute(array_merge([$status],$ids)); AdminHelpers::audit('license', null, 'bulk_'.$bulkAction, 'Bulk update '.count($ids)); $success='Bulk action completed'; }
        elseif ($bulkAction === 'extend') { AdminHelpers::requireManage(); $hours=max(1,(int)($_POST['bulk_extend_hours']??24)); $stmt=$db->prepare("UPDATE licenses SET expires_at=DATE_ADD(expires_at, INTERVAL ? HOUR), updated_at=NOW() WHERE id IN ($ph)"); $stmt->execute(array_merge([$hours],$ids)); AdminHelpers::audit('license', null, 'bulk_extend', 'Bulk extend '.count($ids)); $success='Bulk extend completed'; }
        elseif ($bulkAction === 'delete') { AdminHelpers::requireDelete(); $stmt=$db->prepare("DELETE FROM licenses WHERE id IN ($ph)"); $stmt->execute($ids); AdminHelpers::audit('license', null, 'bulk_delete', 'Bulk delete '.count($ids)); $success='Bulk delete completed'; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_create_license'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $count = min(500, max(1, (int)($_POST['bulk_count'] ?? 1)));
    $hours = max(1, (int)($_POST['bulk_hours'] ?? $defaultLicenseHours));
    $limit = max(1, (int)($_POST['bulk_device_limit'] ?? $defaultDeviceLimit));
    $appScope = Security::sanitize($_POST['bulk_app_scope'] ?? '');
    $bulkApiKeyId = (int)($_POST['bulk_license_api_key_id'] ?? 0);
    if ($bulkApiKeyId <= 0 && $appScope !== '' && !in_array($appScope, $v2AllowedAppIds, true)) {
        $error = 'Selected API v2 client application is not active or does not exist';
        $appScope = '';
    }
    if ($bulkApiKeyId > 0) {
        try {
            $apiScopeStmt = $db->prepare("SELECT id, COALESCE(app_name, '') AS app_name, COALESCE(scope_label, '') AS scope_label, name FROM api_keys WHERE id = :id AND is_active = 1 LIMIT 1");
            $apiScopeStmt->execute([':id' => $bulkApiKeyId]);
            $selectedApiKey = $apiScopeStmt->fetch();
        } catch (PDOException $e) {
            $apiScopeStmt = $db->prepare("SELECT id, '' AS app_name, '' AS scope_label, name FROM api_keys WHERE id = :id AND is_active = 1 LIMIT 1");
            $apiScopeStmt->execute([':id' => $bulkApiKeyId]);
            $selectedApiKey = $apiScopeStmt->fetch();
        }
        if ($selectedApiKey) {
            $appScope = trim($selectedApiKey['app_name'] ?: ($selectedApiKey['scope_label'] ?: $selectedApiKey['name']));
        } else {
            $error = 'Selected API key/app is not active or does not exist';
            $bulkApiKeyId = 0;
        }
    }
    if (!$error) {
        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $r = $system->createLicense($hours, $limit, $_SESSION['admin_id'], 'Bulk created', $appScope, $bulkApiKeyId ?: null);
            if (!empty($r['success'])) { $created++; }
        }
        AdminHelpers::audit('license', null, 'bulk_create', 'Bulk created ' . $created);
        $success = $created . ' licenses created successfully';
    }
}

// License list
$query = "SELECT * FROM licenses";
$params = [];

if ($search) {
    $query .= " WHERE license_key LIKE :search OR notes LIKE :search";
    $params[':search'] = "%{$search}%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$licenses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenses · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero">
        <h2><i class="bi bi-key"></i> License Management</h2>
        <?php if (AdminHelpers::canManage()): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#licenseCreateModal"><i class="bi bi-plus-circle"></i> Create License</button><?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <section class="ui-table-panel" aria-label="License records">
        <div class="ui-table-toolbar">
            <div class="ui-table-controls-left">
                <form method="GET" class="d-flex align-items-center gap-1 ui-search" role="search">
                    <input class="form-control" type="search" name="search" value="<?php echo Security::escape($search); ?>" placeholder="Search licenses..." aria-label="Search licenses">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
                </form>
                <select id="license-status-filter" class="form-select" aria-label="Filter by status">
                    <option value="">All status</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="blacklisted">Blacklisted</option><option value="expired">Expired</option>
                </select>
                <input id="license-date-filter" class="form-control" type="date" aria-label="Filter by created date">
                <select id="license-page-size" class="form-select" aria-label="Rows per page"><option value="10">10 / page</option><option value="25">25 / page</option><option value="50">50 / page</option></select>
                <form method="POST" id="bulkLicenseForm" class="d-flex align-items-center gap-1">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                    <select name="bulk_action" class="form-select" aria-label="Bulk action"><option value="">Bulk action</option><option value="activate">Activate</option><option value="suspend">Suspend</option><option value="extend">Extend</option><option value="export">Export</option><?php if (AdminHelpers::canDelete()): ?><option value="delete">Delete</option><?php endif; ?></select>
                    <input type="number" name="bulk_extend_hours" class="form-control" min="1" value="24" aria-label="Bulk extension hours" style="width:84px">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-square"></i> Apply</button>
                </form>
            </div>
        </div>

        <?php if (empty($licenses)): ?>
            <div class="empty-state"><div class="empty-icon mx-auto"><i class="bi bi-key"></i></div><h5 class="mt-2 mb-0">No licenses found</h5></div>
        <?php else: ?>
            <div class="bulk-bar" id="license-bulk-bar"><strong><span id="license-selected-count">0</span> selected</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="license-table">
                    <thead><tr><th class="ui-table-select-col"><input class="form-check-input" type="checkbox" id="license-check-all" aria-label="Select visible licenses"></th><th>ID</th><th>License Key</th><th>App / API</th><th>Created</th><th>Expires</th><th>Devices</th><th>Status</th><th>Risk</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($licenses as $license):
                        $expired = strtotime($license['expires_at']) < time();
                        $effective_status = $expired ? 'expired' : $license['status'];
                        $status_class = $effective_status === 'active' ? 'success' : ($effective_status === 'suspended' ? 'warning' : 'danger');
                        $risk = $system->getLicenseRiskScore($license['id']);
                    ?>
                    <tr data-status="<?php echo Security::escape($effective_status); ?>" data-created="<?php echo date('Y-m-d', strtotime($license['created_at'])); ?>">
                        <td><input class="form-check-input" type="checkbox" form="bulkLicenseForm" name="license_ids[]" value="<?php echo (int)$license['id']; ?>" aria-label="Select license <?php echo (int)$license['id']; ?>"></td>
                        <td><span class="badge bg-secondary">#<?php echo (int)$license['id']; ?></span></td>
                        <td><span class="copyable-code"><code><?php echo Security::escape($license['license_key']); ?></code><button type="button" class="ui-icon-button" data-copy="<?php echo Security::escape($license['license_key']); ?>" title="Copy license key" aria-label="Copy license key"><i class="bi bi-clipboard"></i></button></span><?php if ($license['notes']): ?><div class="ui-compact-meta"><?php echo Security::escape($license['notes']); ?></div><?php endif; ?></td>
                        <td><?php if (!empty($license['api_key_id'])): ?><span class="badge bg-primary">API #<?php echo (int)$license['api_key_id']; ?></span><?php endif; ?><div class="ui-compact-meta"><?php echo Security::escape(!empty($license['app_scope']) ? $license['app_scope'] : 'Any app'); ?></div></td>
                        <td><?php echo date('Y-m-d', strtotime($license['created_at'])); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($license['expires_at'])); ?><?php if ($expired): ?> <span class="badge bg-danger">Expired</span><?php endif; ?></td>
                        <td><?php echo (int)$license['total_devices']; ?> / <?php echo (int)$license['device_limit']; ?></td>
                        <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo Security::escape(ucfirst($effective_status)); ?></span></td>
                        <td><span class="badge bg-<?php echo $risk['level'] === 'High' ? 'danger' : ($risk['level'] === 'Medium' ? 'warning' : 'success'); ?>"><?php echo Security::escape($risk['level']); ?> <?php echo (int)$risk['score']; ?></span><div class="ui-compact-meta"><?php echo (int)$risk['device_count']; ?> devices · <?php echo (int)$risk['ip_count']; ?> IPs</div></td>
                        <td class="text-end">
                            <details class="ui-action-menu">
                                <summary class="ui-icon-button" aria-label="Open license actions"><i class="bi bi-three-dots"></i></summary>
                                <div class="ui-action-menu-panel">
                                    <?php if ($license['status'] === 'active'): ?><a class="is-warning" href="?action=suspend&id=<?php echo (int)$license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Suspend this license?"><i class="bi bi-pause-circle"></i> Suspend</a><?php else: ?><a class="is-success" href="?action=activate&id=<?php echo (int)$license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Activate this license?"><i class="bi bi-play-circle"></i> Activate</a><?php endif; ?>
                                    <a href="device.php?license_id=<?php echo (int)$license['id']; ?>"><i class="bi bi-laptop"></i> View Devices</a>
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo (int)$license['id']; ?>"><i class="bi bi-clock"></i> Extend</button>
                                    <a class="is-danger" href="?blacklist=1&id=<?php echo (int)$license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Blacklist this license?"><i class="bi bi-ban"></i> Blacklist</a>
                                    <?php if (AdminHelpers::canDelete()): ?><a class="is-danger" href="?delete_license=1&id=<?php echo (int)$license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Delete this license permanently?"><i class="bi bi-trash"></i> Delete</a><?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="empty-state" id="license-empty-state" hidden><h5 class="mb-0">No matching licenses</h5></div>
            <div class="ui-table-footer"><span class="ui-table-count" id="license-visible-count"><?php echo count($licenses); ?> entries</span><nav aria-label="License pagination"><ul class="pagination mb-0" id="license-pagination"></ul></nav></div>
        <?php endif; ?>
    </section>
</div>

<?php if (AdminHelpers::canManage()): ?>
<div class="modal fade" id="licenseCreateModal" tabindex="-1" aria-labelledby="licenseCreateTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ui-modal-wide"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="licenseCreateTitle"><i class="bi bi-plus-circle"></i> Create License</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="ui-segmented mb-3" role="tablist"><button type="button" class="active" data-license-create-mode="single">Single</button><button type="button" data-license-create-mode="bulk">Bulk</button></div>
            <form method="POST" id="license-create-single" class="needs-validation" data-license-create-panel="single" novalidate>
                <input type="hidden" name="create_license" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                <div class="ui-form-grid">
                    <div><label class="form-label">Validity (Hours)</label><input type="number" class="form-control" name="hours" value="<?php echo $defaultLicenseHours; ?>" min="<?php echo $minLicenseHours; ?>" max="<?php echo $maxLicenseHours; ?>" required><div class="d-flex gap-1 mt-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-hours-preset="24">1 day</button><button type="button" class="btn btn-sm btn-outline-secondary" data-hours-preset="168">7 days</button><button type="button" class="btn btn-sm btn-outline-secondary" data-hours-preset="720">30 days</button></div></div>
                    <div><label class="form-label">Valid Until</label><input type="date" id="license-valid-until" class="form-control"></div>
                    <div><label class="form-label">Device Limit</label><input type="number" class="form-control" name="device_limit" value="<?php echo $defaultDeviceLimit; ?>" min="1" max="100" required></div>
                    <div><label class="form-label">Allowed App / API Key</label><select class="form-select" name="license_api_key_id"><option value="0">Any app / Default</option><?php foreach ($appOptions as $app): $label=trim(($app['app_name']??'') ?: (($app['scope_label']??'') ?: ($app['name']??''))); ?><option value="<?php echo (int)$app['id']; ?>"><?php echo Security::escape($label); ?></option><?php endforeach; ?></select></div>
                    <div class="ui-span-2"><label class="form-label">API v2 Client App</label><select class="form-select" name="app_scope"><option value="">No API v2 app scope</option><?php foreach ($v2AppOptions as $app): ?><option value="<?php echo Security::escape($app['app_id']); ?>"><?php echo Security::escape(($app['display_name'] ?: $app['app_id']) . ' · ' . $app['app_id']); ?></option><?php endforeach; ?></select></div>
                    <div class="ui-span-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="1000"></textarea></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create License</button></div>
            </form>
            <form method="POST" id="license-create-bulk" class="needs-validation" data-license-create-panel="bulk" hidden novalidate>
                <input type="hidden" name="bulk_create_license" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                <div class="ui-form-grid">
                    <div><label class="form-label">Number of Licenses</label><input type="number" class="form-control" name="bulk_count" value="10" min="1" max="500" required></div>
                    <div><label class="form-label">Validity (Hours)</label><input type="number" class="form-control" name="bulk_hours" value="<?php echo $defaultLicenseHours; ?>" min="<?php echo $minLicenseHours; ?>" max="<?php echo $maxLicenseHours; ?>" required></div>
                    <div><label class="form-label">Device Limit</label><input type="number" class="form-control" name="bulk_device_limit" value="<?php echo $defaultDeviceLimit; ?>" min="1" max="100" required></div>
                    <div><label class="form-label">Allowed App / API Key</label><select class="form-select" name="bulk_license_api_key_id"><option value="0">Any app / Default</option><?php foreach ($appOptions as $app): $label=trim(($app['app_name']??'') ?: (($app['scope_label']??'') ?: ($app['name']??''))); ?><option value="<?php echo (int)$app['id']; ?>"><?php echo Security::escape($label); ?></option><?php endforeach; ?></select></div>
                    <div class="ui-span-2"><label class="form-label">API v2 Client App</label><select class="form-select" id="bulk_v2_app_scope" name="bulk_app_scope"><option value="">No API v2 app scope</option><?php foreach ($v2AppOptions as $app): ?><option value="<?php echo Security::escape($app['app_id']); ?>"><?php echo Security::escape(($app['display_name'] ?: $app['app_id']) . ' · ' . $app['app_id']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-layers"></i> Generate Licenses</button></div>
            </form>
        </div>
    </div></div>
</div>
<?php endif; ?>

<?php foreach ($licenses as $license): ?>
<div class="modal fade" id="extendModal<?php echo (int)$license['id']; ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history"></i> Extend License</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST" class="needs-validation" novalidate><div class="modal-body"><input type="hidden" name="extend_license" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="license_id" value="<?php echo (int)$license['id']; ?>"><label class="form-label">Extend by (hours)</label><input type="number" class="form-control" name="extend_hours" min="1" max="720" value="24" required></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Extend License</button></div></form></div></div></div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
