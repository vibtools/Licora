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

$db = Database::getInstance();
$license_id = isset($_GET['license_id']) ? (int)$_GET['license_id'] : 0;
$success = '';
$error = '';

$deviceClearOptions = [
    '1_hour' => ['label' => '1 hour old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)'],
    '1_day' => ['label' => '1 day old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 1 DAY)'],
    '3_days' => ['label' => '3 days old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 3 DAY)'],
    '1_week' => ['label' => '1 week old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)'],
    '30_days' => ['label' => '30 days old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)']
];

// Log out device
if (isset($_GET['logout_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $device_id = (int)$_GET['logout_device'];
    $stmt = $db->prepare("UPDATE devices SET is_active = FALSE WHERE id = :id");
    $stmt->execute([':id' => $device_id]);
    $success = "Device logged out successfully";
}

// Blacklist device
if (isset($_GET['blacklist_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $device_hash = Security::sanitize($_GET['blacklist_device']);
    $stmt = $db->prepare("INSERT INTO blacklist (type, value, reason, banned_by) VALUES ('device', :value, 'Admin blacklisted', :admin_id)");
    $stmt->execute([':value' => $device_hash, ':admin_id' => $_SESSION['admin_id']]);
    $stmt = $db->prepare("UPDATE devices SET is_active = FALSE WHERE device_hash = :hash");
    $stmt->execute([':hash' => $device_hash]);
    $success = "Device blacklisted successfully";
}

// Delete device
if (isset($_GET['delete_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $device_id = (int)$_GET['delete_device'];
    $stmt = $db->prepare("DELETE FROM devices WHERE id = :id");
    $stmt->execute([':id' => $device_id]);
    $success = "Device deleted successfully";
}

// Clear old devices
if (isset($_GET['clear_devices']) && ($_GET['confirm'] ?? '') === 'yes') {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $age = $_GET['age'] ?? '1_day';
    if (isset($deviceClearOptions[$age])) {
        $cutoffSql = $deviceClearOptions[$age]['sql'];
        $stmt = $db->prepare("DELETE FROM devices WHERE last_active < {$cutoffSql}");
        $stmt->execute();
        $success = "Devices older than " . $deviceClearOptions[$age]['label'] . " have been cleared";
    } else {
        $error = "Invalid clear range";
    }
}

// Device list
$query = "SELECT d.*, l.license_key, l.device_limit FROM devices d JOIN licenses l ON d.license_id = l.id";
$params = [];
if ($license_id > 0) {
    $query .= " WHERE d.license_id = :license_id";
    $params[':license_id'] = $license_id;
}
$query .= " ORDER BY d.last_active DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$devices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Devices · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero">
        <h2><i class="bi bi-laptop"></i> Device Management</h2>
        <div class="d-flex gap-2"><a href="license.php" class="btn btn-outline-secondary"><i class="bi bi-key"></i> Licenses</a><button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearDevicesModal"><i class="bi bi-trash3"></i> Clear Old Devices</button></div>
    </div>
    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <section class="ui-table-panel" aria-label="Registered devices">
        <div class="ui-table-toolbar">
            <div class="ui-table-controls-left">
                <input class="form-control ui-search" type="search" data-ui-table-search="devices-table" placeholder="Search devices..." aria-label="Search devices">
                <select class="form-select" data-ui-table-status="devices-table" aria-label="Filter device status"><option value="">All status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <select class="form-select" data-ui-table-size="devices-table" aria-label="Rows per page"><option value="10">10 / page</option><option value="25">25 / page</option><option value="50">50 / page</option></select>
                <?php if ($license_id): ?><span class="badge bg-primary">License #<?php echo $license_id; ?></span><a href="device.php" class="btn btn-outline-secondary">All Devices</a><?php endif; ?>
            </div>
        </div>
        <?php if (empty($devices)): ?>
            <div class="empty-state"><div class="empty-icon mx-auto"><i class="bi bi-laptop"></i></div><h5 class="mt-2 mb-0">No devices found</h5></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="devices-table" data-ui-paginate="true" data-ui-page-size="10">
                <thead><tr><th>ID</th><th>License</th><th>Device</th><th>IP</th><th>First Login</th><th>Last Active</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($devices as $device):
                    $device_info = json_decode($device['device_info'] ?? '{}', true); if (!is_array($device_info)) { $device_info = []; }
                    $inactive = strtotime($device['last_active']) < time() - 300;
                    $status = $device['is_active'] ? 'active' : 'inactive';
                    $ip = (string)($device_info['ip'] ?? 'N/A');
                    $searchText = implode(' ', [(string)$device['license_key'], (string)($device['os'] ?? ''), (string)($device['browser'] ?? ''), $ip, (string)$device['device_hash']]);
                ?>
                    <tr data-ui-search="<?php echo Security::escape($searchText); ?>" data-ui-status="<?php echo $status; ?>">
                        <td><span class="badge bg-secondary">#<?php echo (int)$device['id']; ?></span></td>
                        <td><a href="license.php?search=<?php echo urlencode($device['license_key']); ?>"><code><?php echo Security::escape(substr($device['license_key'], 0, 14)); ?>…</code></a></td>
                        <td><div class="ui-device-summary"><span class="ui-device-primary"><?php echo Security::escape($device['os'] ?? 'Unknown'); ?> · <?php echo Security::escape($device['browser'] ?? 'Unknown'); ?></span><span class="ui-device-secondary ui-compact-code"><code title="<?php echo Security::escape($device['device_hash']); ?>"><?php echo Security::escape(substr($device['device_hash'], 0, 18)); ?>…</code><button type="button" class="ui-icon-button" data-copy="<?php echo Security::escape($device['device_hash']); ?>" title="Copy device hash" aria-label="Copy device hash"><i class="bi bi-clipboard"></i></button></span></div></td>
                        <td><?php echo Security::escape($ip); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($device['login_time'])); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($device['last_active'])); ?><?php if ($inactive && $device['is_active']): ?> <span class="badge bg-warning">Idle</span><?php endif; ?></td>
                        <td><span class="badge bg-<?php echo $device['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $device['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td class="text-end"><details class="ui-action-menu"><summary class="ui-icon-button" aria-label="Open device actions"><i class="bi bi-three-dots"></i></summary><div class="ui-action-menu-panel">
                            <?php if ($device['is_active']): ?><a class="is-warning" href="?logout_device=<?php echo (int)$device['id']; ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Log out this device?"><i class="bi bi-box-arrow-right"></i> Log Out</a><?php endif; ?>
                            <a class="is-danger" href="?blacklist_device=<?php echo urlencode($device['device_hash']); ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Blacklist this device?"><i class="bi bi-ban"></i> Blacklist</a>
                            <a class="is-danger" href="?delete_device=<?php echo (int)$device['id']; ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" data-confirm="Delete this device record?"><i class="bi bi-trash"></i> Delete</a>
                        </div></details></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="ui-table-footer"><span class="ui-table-count" data-ui-count-for="devices-table"><?php echo count($devices); ?> entries</span><nav aria-label="Device pagination"><ul class="pagination mb-0" data-ui-pager-for="devices-table"></ul></nav></div>
        <?php endif; ?>
    </section>
</div>
<div class="modal fade" id="clearDevicesModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-trash3"></i> Clear Old Devices</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="GET"><div class="modal-body"><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> This deletes device records older than the selected range.</div><label class="form-label">Older Than</label><select name="age" class="form-select" required><?php foreach ($deviceClearOptions as $key => $option): ?><option value="<?php echo Security::escape($key); ?>"><?php echo Security::escape($option['label']); ?></option><?php endforeach; ?></select><input type="hidden" name="clear_devices" value="1"><input type="hidden" name="confirm" value="yes"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Clear Devices</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
