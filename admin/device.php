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

// ডিভাইস লগআউট
if (isset($_GET['logout_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $device_id = (int)$_GET['logout_device'];
    $stmt = $db->prepare("UPDATE devices SET is_active = FALSE WHERE id = :id");
    $stmt->execute([':id' => $device_id]);
    $success = "Device logged out successfully";
}

// ডিভাইস ব্ল্যাকলিস্ট
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

// ডিভাইস ডিলিট
if (isset($_GET['delete_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $device_id = (int)$_GET['delete_device'];
    $stmt = $db->prepare("DELETE FROM devices WHERE id = :id");
    $stmt->execute([':id' => $device_id]);
    $success = "Device deleted successfully";
}

// পুরোনো ডিভাইস ক্লিয়ার
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

// ডিভাইস লিস্ট
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
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Device Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div><h2><i class="bi bi-devices"></i> Device Management</h2><p>Review, logout, blacklist, delete, and clean old device records with existing backend actions.</p></div>
        <div class="d-flex gap-2 flex-wrap"><a href="license.php" class="btn btn-light"><i class="bi bi-key"></i> Licenses</a><button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#clearDevicesModal"><i class="bi bi-trash3"></i> Clear Old Devices</button></div>
    </div>
    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h5 class="mb-0"><i class="bi bi-devices"></i> Registered Devices <?php if ($license_id): ?><small class="text-muted">(License ID: <?php echo $license_id; ?>)</small><?php endif; ?></h5><small class="text-muted"><?php echo count($devices); ?> records loaded · 10 rows per page</small></div>
            <div class="d-flex gap-2 flex-wrap"><a href="device.php" class="btn btn-sm <?php echo $license_id ? 'btn-outline-primary' : 'btn-primary'; ?>">All Devices</a><?php if ($license_id): ?><a href="license.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a><?php endif; ?></div>
        </div>
        <div class="card-body">
            <?php if (empty($devices)): ?>
                <div class="empty-state"><div class="empty-icon"><i class="bi bi-devices"></i></div><h4>No devices found</h4><p>No devices have been registered yet.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="devices-table" data-ui-paginate="true" data-ui-page-size="10">
                    <thead class="table-dark"><tr><th>ID</th><th>License Key</th><th>Device Info</th><th>First Login</th><th>Last Active</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($devices as $device): $device_info = json_decode($device['device_info'] ?? '{}', true); if (!is_array($device_info)) { $device_info = []; } $inactive = strtotime($device['last_active']) < time() - 300; ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?php echo (int)$device['id']; ?></span></td>
                            <td><a href="license.php?search=<?php echo urlencode($device['license_key']); ?>"><?php echo substr(Security::escape($device['license_key']), 0, 15); ?>...</a></td>
                            <td><small><strong>OS:</strong> <?php echo Security::escape($device['os'] ?? 'Unknown'); ?><br><strong>Browser:</strong> <?php echo Security::escape($device['browser'] ?? 'Unknown'); ?><br><strong>IP:</strong> <?php echo Security::escape($device_info['ip'] ?? 'N/A'); ?><br><strong>Hash:</strong> <code class="small"><?php echo substr(Security::escape($device['device_hash']), 0, 20); ?>...</code></small></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($device['login_time'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($device['last_active'])); ?><?php if ($inactive && $device['is_active']): ?><br><span class="badge bg-warning">Inactive</span><?php endif; ?></td>
                            <td><span class="badge bg-<?php echo $device['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $device['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td><div class="btn-group btn-group-sm">
                                <?php if ($device['is_active']): ?><a href="?logout_device=<?php echo (int)$device['id']; ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-warning" data-confirm="Logout this device?" title="Logout"><i class="bi bi-box-arrow-right"></i></a><?php endif; ?>
                                <a href="?blacklist_device=<?php echo urlencode($device['device_hash']); ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-danger" data-confirm="Blacklist this device?" title="Blacklist"><i class="bi bi-ban"></i></a>
                                <a href="?delete_device=<?php echo (int)$device['id']; ?>&license_id=<?php echo $license_id; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-outline-danger" data-confirm="Delete this device record?" title="Delete"><i class="bi bi-trash"></i></a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav class="mt-3" aria-label="Device pagination"><ul class="pagination justify-content-end mb-0" data-ui-pager-for="devices-table"></ul></nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="clearDevicesModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-trash3"></i> Clear Old Devices</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="GET"><div class="modal-body"><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> This will delete device records older than the selected range.</div><label class="form-label">Clear devices older than</label><select name="age" class="form-select" required><?php foreach ($deviceClearOptions as $key => $option): ?><option value="<?php echo Security::escape($key); ?>"><?php echo Security::escape($option['label']); ?></option><?php endforeach; ?></select><input type="hidden" name="clear_devices" value="1"><input type="hidden" name="confirm" value="yes"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Clear Devices</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
