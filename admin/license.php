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

// লাইসেন্স তৈরি
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_license'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $hours = (int)$_POST['hours'];
    $device_limit = (int)$_POST['device_limit'];
    $notes = Security::sanitize($_POST['notes'] ?? '');
    $app_scope = Security::sanitize($_POST['app_scope'] ?? '');
    $license_api_key_id = (int)($_POST['license_api_key_id'] ?? 0);
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

// লাইসেন্স সাসপেন্ড/একটিভ
if (isset($_GET['action']) && isset($_GET['id'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    $status = ($action === 'suspend') ? 'suspended' : 'active';
    $system->updateLicenseStatus($id, $status);
    
    $success = "License status updated to {$status}";
}

// লাইসেন্স এক্সটেন্ড
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

// লাইসেন্স ব্ল্যাকলিস্ট
if (isset($_GET['blacklist']) && isset($_GET['id'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $license_id = (int)$_GET['id'];
    
    // লাইসেন্স কী পান
    $stmt = $db->prepare("SELECT license_key FROM licenses WHERE id = :id");
    $stmt->execute([':id' => $license_id]);
    $license = $stmt->fetch();
    
    if ($license) {
        $system->blacklistLicense($license['license_key'], 'Admin blacklisted', $_SESSION['admin_id']);
        $success = "License blacklisted successfully";
    }
}



// লাইসেন্স ডিলিট
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

// লাইসেন্স লিস্ট
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid admin-shell">
        <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h2><i class="bi bi-key"></i> License Management</h2>
                <p>Create licenses, review status, copy keys, and manage actions without changing backend behavior.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="#create-license-card" class="btn btn-light"><i class="bi bi-plus-circle"></i> Create License</a>
                <a href="device.php" class="btn btn-outline-light"><i class="bi bi-devices"></i> Devices</a>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo Security::escape($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <div class="col-xl-4 col-xxl-3">
                <div class="card h-100" id="create-license-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New License</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="create_license" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Validity (Hours)</label>
                                <input type="number" class="form-control" name="hours" min="<?php echo $minLicenseHours; ?>" max="<?php echo $maxLicenseHours; ?>" value="<?php echo $defaultLicenseHours; ?>" required>
                                <div class="invalid-feedback">Validity hours are required.</div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-hours-preset="24">1 day</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-hours-preset="168">7 days</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-hours-preset="720">30 days</button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Valid Until</label>
                                <input type="date" class="form-control" id="license-valid-until">
                                <small class="text-muted">Date picker updates the existing hours field only.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Device Limit</label>
                                <input type="number" class="form-control" name="device_limit" min="1" max="100" value="<?php echo $defaultDeviceLimit; ?>" required>
                                <div class="invalid-feedback">Device limit is required.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Allowed App / Scope / API Key</label>
                                <select class="form-select" name="license_api_key_id">
                                    <option value="">Any app / Default</option>
                                    <?php foreach ($appOptions as $app): ?>
                                        <?php $appLabel = trim(($app['app_name'] ?? '') . (($app['scope_label'] ?? '') ? ' / ' . $app['scope_label'] : '') . ' — ' . ($app['name'] ?? 'API Key')); ?>
                                        <option value="<?php echo (int)$app['id']; ?>"><?php echo Security::escape($appLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="app_scope" value="">
                                <?php if (empty($appOptions)): ?>
                                    <small class="text-warning">No active API key/app found. Create API key with App Name first.</small>
                                <?php else: ?>
                                    <small class="text-muted">Selected API key/app ছাড়া এই license verify হবে না।</small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Internal note only"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-key"></i> Generate License
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card mt-4" id="bulk-create-license-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-layers"></i> Bulk License Create</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                            <input type="hidden" name="bulk_create_license" value="1">
                            <input type="hidden" name="bulk_app_scope" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Number of Licenses</label>
                                    <input class="form-control" type="number" name="bulk_count" value="10" min="1" max="500" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Validity Hours</label>
                                    <input class="form-control" type="number" name="bulk_hours" value="<?php echo $defaultLicenseHours; ?>" min="<?php echo $minLicenseHours; ?>" max="<?php echo $maxLicenseHours; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Device Limit</label>
                                    <input class="form-control" type="number" name="bulk_device_limit" value="<?php echo $defaultDeviceLimit; ?>" min="1" max="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Allowed App / Scope / API Key</label>
                                    <select class="form-select" name="bulk_license_api_key_id">
                                        <option value="">Any app / Default</option>
                                        <?php foreach ($appOptions as $app): ?>
                                            <?php $appLabel = trim(($app['app_name'] ?? '') . (($app['scope_label'] ?? '') ? ' / ' . $app['scope_label'] : '') . ' — ' . ($app['name'] ?? 'API Key')); ?>
                                            <option value="<?php echo (int)$app['id']; ?>"><?php echo Security::escape($appLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-success w-100 mt-3" type="submit"><i class="bi bi-layers"></i> Bulk Generate Licenses</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-8 col-xxl-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-list-check"></i> All Licenses</h5>
                            <small class="text-muted"><?php echo count($licenses); ?> records loaded</small>
                        </div>
                        <form method="GET" class="d-flex gap-2" data-no-spinner>
                            <input type="text" class="form-control" name="search" placeholder="Search licenses..." value="<?php echo Security::escape($search); ?>">
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Search</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="ui-toolbar mb-3">
                            <div>
                                <label class="form-label small">Status</label>
                                <select class="form-select" id="license-status-filter">
                                    <option value="">All status</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label small">Created date</label>
                                <input type="date" class="form-control" id="license-date-filter">
                            </div>
                            <div>
                                <label class="form-label small">Rows</label>
                                <select class="form-select" id="license-page-size">
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>

                        <div class="bulk-bar" id="license-bulk-bar">
                            <strong><span id="license-selected-count">0</span> selected</strong>
                            <span class="text-muted">Bulk selection UI only; existing backend actions are unchanged.</span>
                        </div>

                        <?php if (empty($licenses)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-key"></i></div>
                            <h5>No licenses found</h5>
                            <p>Create a new license or adjust the search filter.</p>
                        </div>
                        <?php else: ?>
                                                <form method="POST" id="bulkLicenseForm" class="d-flex flex-wrap gap-2 align-items-center mb-3" data-no-spinner>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                            <select class="form-select form-select-sm w-auto" name="bulk_action"><option value="activate">Bulk Activate</option><option value="suspend">Bulk Suspend</option><option value="extend">Bulk Extend</option><option value="export">Bulk Export CSV</option><?php if (AdminHelpers::canDelete()): ?><option value="delete">Bulk Delete</option><?php endif; ?></select>
                            <input class="form-control form-control-sm w-auto" type="number" name="bulk_extend_hours" value="24" min="1">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2-square"></i> Apply Selected</button>
                        </form>
<div class="table-responsive">
                            <table class="table table-hover align-middle" id="license-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th><input class="form-check-input" type="checkbox" id="license-check-all" aria-label="Select all licenses"></th>
                                        <th>ID</th>
                                        <th>License Key</th>
                                        <th>App/API Key</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Devices</th>
                                        <th>Status</th>
                                        <th>Risk</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($licenses as $license): 
                                        $expired = strtotime($license['expires_at']) < time();
                                        $effective_status = $expired ? 'expired' : $license['status'];
                                        $status_class = $license['status'] == 'active' ? 'success' : 
                                                       ($license['status'] == 'suspended' ? 'warning' : 'danger');
                                    ?>
                                    <tr data-status="<?php echo Security::escape($effective_status); ?>" data-created="<?php echo date('Y-m-d', strtotime($license['created_at'])); ?>">
                                        <td><input class="form-check-input" type="checkbox" form="bulkLicenseForm" name="license_ids[]" value="<?php echo $license['id']; ?>" aria-label="Select license <?php echo $license['id']; ?>"></td>
                                        <td><span class="badge bg-secondary">#<?php echo $license['id']; ?></span></td>
                                        <td>
                                            <span class="copyable-code">
                                                <code><?php echo Security::escape($license['license_key']); ?></code>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?php echo Security::escape($license['license_key']); ?>" title="Copy license key"><i class="bi bi-clipboard"></i></button>
                                            </span>
                                            <?php if ($license['notes']): ?>
                                            <br><small class="text-muted"><?php echo Security::escape($license['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($license['api_key_id'])): ?>
                                                <span class="badge bg-primary">API #<?php echo (int)$license['api_key_id']; ?></span><br>
                                            <?php endif; ?>
                                            <?php if (!empty($license['app_scope'])): ?>
                                                <small class="text-muted"><?php echo Security::escape($license['app_scope']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Any app</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($license['created_at'])); ?></td>
                                        <td>
                                            <?php echo date('Y-m-d H:i', strtotime($license['expires_at'])); ?>
                                            <?php if ($expired): ?>
                                            <br><span class="badge bg-danger">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $license['total_devices']; ?> / <?php echo $license['device_limit']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($license['status']); ?></span>
                                        </td>
                                        <?php $risk = $system->getLicenseRiskScore($license['id']); ?>
                                        <td><span class="badge bg-<?php echo $risk['level'] === 'High' ? 'danger' : ($risk['level'] === 'Medium' ? 'warning' : 'success'); ?>"><?php echo $risk['level']; ?> <?php echo $risk['score']; ?></span><br><small class="text-muted"><?php echo $risk['device_count']; ?> devices / <?php echo $risk['ip_count']; ?> IPs</small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($license['status'] == 'active'): ?>
                                                <a href="?action=suspend&id=<?php echo $license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-warning" title="Suspend" data-confirm="Suspend this license?">
                                                    <i class="bi bi-pause-circle"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="?action=activate&id=<?php echo $license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-success" title="Activate" data-confirm="Activate this license?">
                                                    <i class="bi bi-play-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <a href="device.php?license_id=<?php echo $license['id']; ?>" class="btn btn-info" title="View Devices"><i class="bi bi-devices"></i></a>
                                                
                                                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo $license['id']; ?>" title="Extend"><i class="bi bi-clock"></i></button>
                                                
                                                <a href="?blacklist=1&id=<?php echo $license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-danger" title="Blacklist" data-confirm="Blacklist this license?">
                                                    <i class="bi bi-ban"></i>
                                                </a>
                                                
                                                <a href="?delete_license=1&id=<?php echo $license['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-outline-danger" title="Delete" data-confirm="Delete this license permanently? Related device and log records may also be removed by database constraints.">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                            
                                            <div class="modal fade" id="extendModal<?php echo $license['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><i class="bi bi-clock-history"></i> Extend License</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" class="needs-validation" novalidate>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="extend_license" value="1">
                                                                <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                                                                <input type="hidden" name="license_id" value="<?php echo $license['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Extend by (hours)</label>
                                                                    <input type="number" class="form-control" name="extend_hours" min="1" max="720" value="24" required>
                                                                    <div class="invalid-feedback">Extension hours are required.</div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Extend License</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="empty-state" id="license-empty-state" style="display:none;">
                            <div class="empty-icon"><i class="bi bi-funnel"></i></div>
                            <h5>No matching licenses</h5>
                            <p>Change status/date filters to see more results.</p>
                        </div>
                        <nav class="mt-3" aria-label="License pagination">
                            <ul class="pagination justify-content-end mb-0" id="license-pagination"></ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-ui.js"></script>
</body>
</html>
