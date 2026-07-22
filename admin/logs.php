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
$success = '';
$error = '';

$filter_action = $_GET['action'] ?? '';
$filter_license = $_GET['license'] ?? '';
$filter_date = $_GET['date'] ?? '';

$logClearOptions = [
    '1_hour' => ['label' => '1 hour old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)'],
    '1_day' => ['label' => '1 day old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 1 DAY)'],
    '3_days' => ['label' => '3 days old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 3 DAY)'],
    '1_week' => ['label' => '1 week old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)'],
    '30_days' => ['label' => '30 days old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'],
    '90_days' => ['label' => '90 days old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)'],
    '1_year' => ['label' => '1 year old', 'sql' => 'DATE_SUB(NOW(), INTERVAL 365 DAY)']
];

// লগ ক্লিয়ার
if (isset($_GET['clear_logs']) && ($_GET['confirm'] ?? '') === 'yes') {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    if (!empty($_GET['age']) && isset($logClearOptions[$_GET['age']])) {
        $option = $logClearOptions[$_GET['age']];
        $stmt = $db->prepare("DELETE FROM logs WHERE created_at < {$option['sql']}");
        $stmt->execute();
        $success = "Logs older than " . $option['label'] . " have been cleared";
    } else {
        $days = max(1, (int)($_GET['days'] ?? 30));
        $stmt = $db->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $stmt->execute([':days' => $days]);
        $success = "Logs older than {$days} days have been cleared";
    }
}

$query = "SELECT l.*, li.license_key, u.username FROM logs l LEFT JOIN licenses li ON l.license_id = li.id LEFT JOIN admin_users u ON l.admin_id = u.id WHERE 1=1";
$params = [];
if ($filter_action) { $query .= " AND l.action = :action"; $params[':action'] = $filter_action; }
if ($filter_license) { $query .= " AND li.license_key LIKE :license"; $params[':license'] = "%{$filter_license}%"; }
if ($filter_date) { $query .= " AND DATE(l.created_at) = :date"; $params[':date'] = $filter_date; }
$query .= " ORDER BY l.created_at DESC LIMIT 1000";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$actions = $db->query("SELECT DISTINCT action FROM logs ORDER BY action")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Activity Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div><h2><i class="bi bi-clock-history"></i> Activity Logs</h2><p>Filter, review, paginate, and clean older activity records without changing existing log storage.</p></div>
        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#clearLogsModal"><i class="bi bi-trash"></i> Clear Logs</button>
    </div>
    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h5 class="mb-0"><i class="bi bi-clock-history"></i> Activity Logs</h5><small class="text-muted"><?php echo count($logs); ?> rows loaded · 10 rows per page</small></div></div>
        <div class="card-body border-bottom">
            <form method="GET" class="row g-3" data-no-spinner>
                <div class="col-md-3"><select name="action" class="form-select"><option value="">All Actions</option><?php foreach ($actions as $action): ?><option value="<?php echo Security::escape($action['action']); ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>><?php echo Security::escape($action['action']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><input type="text" class="form-control" name="license" placeholder="License Key" value="<?php echo Security::escape($filter_license); ?>"></div>
                <div class="col-md-3"><input type="date" class="form-control" name="date" value="<?php echo Security::escape($filter_date); ?>"></div>
                <div class="col-md-3 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button></div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="logs-table" data-ui-paginate="true" data-ui-page-size="10">
                    <thead class="table-dark"><tr><th>Time</th><th>Action</th><th>License/User</th><th>Details</th><th>IP Address</th></tr></thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><i class="bi bi-journal-text"></i></div><h5>No logs found</h5><p class="mb-0">Try a different filter or date.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><span class="badge bg-<?php echo $log['action'] == 'login' ? 'success' : ($log['action'] == 'logout' ? 'danger' : 'info'); ?>"><?php echo Security::escape($log['action']); ?></span></td>
                            <td><?php if ($log['license_key']): ?><code><?php echo substr(Security::escape($log['license_key']), 0, 12); ?>...</code><?php elseif ($log['username']): ?><i class="bi bi-person"></i> <?php echo Security::escape($log['username']); ?><?php else: ?><span class="text-muted">System</span><?php endif; ?></td>
                            <td><small><?php echo Security::escape($log['details']); ?></small></td>
                            <td><?php echo Security::escape($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($logs)): ?><nav class="mt-3" aria-label="Logs pagination"><ul class="pagination justify-content-end mb-0" data-ui-pager-for="logs-table"></ul></nav><?php endif; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="clearLogsModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-trash"></i> Clear Old Logs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="GET"><div class="modal-body"><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone.</div><label class="form-label">Clear logs older than</label><select name="age" class="form-select" required><?php foreach ($logClearOptions as $key => $option): ?><option value="<?php echo Security::escape($key); ?>"><?php echo Security::escape($option['label']); ?></option><?php endforeach; ?></select><input type="hidden" name="clear_logs" value="1"><input type="hidden" name="confirm" value="yes"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Clear Logs</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
