<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit; }
$db = Database::getInstance();

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    AdminHelpers::requireManage();
    if ($type === 'licenses') {
        $st = $db->query("SELECT id,license_key,status,expires_at,device_limit,total_devices,created_at,notes FROM licenses ORDER BY created_at DESC");
        AdminHelpers::csv('licenses.csv', ['id','license_key','status','expires_at','device_limit','total_devices','created_at','notes'], $st->fetchAll(PDO::FETCH_NUM));
    }
    if ($type === 'devices') {
        $st = $db->query("SELECT id,license_id,device_hash,os,browser,is_active,login_time,last_active FROM devices ORDER BY last_active DESC");
        AdminHelpers::csv('devices.csv', ['id','license_id','device_hash','os','browser','is_active','login_time','last_active'], $st->fetchAll(PDO::FETCH_NUM));
    }
    if ($type === 'logs') {
        $st = $db->query("SELECT id,license_id,admin_id,action,details,ip_address,created_at FROM logs ORDER BY created_at DESC");
        AdminHelpers::csv('logs.csv', ['id','license_id','admin_id','action','details','ip_address','created_at'], $st->fetchAll(PDO::FETCH_NUM));
    }
    if ($type === 'database') {
        AdminHelpers::requireDelete();
        header('Content-Type:text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="database-backup.sql"');
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $t) {
            $table = $t[0];
            echo "\n-- Table `$table`\n";
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            echo $create[1] . ";\n";
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', array_keys($row));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
                echo "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
        }
        exit;
    }
}

$counts = [
    'licenses' => 0,
    'devices' => 0,
    'logs' => 0,
    'tables' => 0
];
try { $counts['licenses'] = (int)$db->query('SELECT COUNT(*) FROM licenses')->fetchColumn(); } catch (Exception $e) {}
try { $counts['devices'] = (int)$db->query('SELECT COUNT(*) FROM devices')->fetchColumn(); } catch (Exception $e) {}
try { $counts['logs'] = (int)$db->query('SELECT COUNT(*) FROM logs')->fetchColumn(); } catch (Exception $e) {}
try { $counts['tables'] = count($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)); } catch (Exception $e) {}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backup & Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind=window.tailwind||{};tailwind.config={corePlugins:{preflight:false},darkMode:'class'};</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div>
            <h2><i class="bi bi-download"></i> Backup & Export</h2>
            <p>License, device, logs CSV export এবং full database SQL backup এখান থেকে নেওয়া যাবে।</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="settings.php" class="btn btn-light"><i class="bi bi-gear"></i> Settings</a>
            <a href="health.php" class="btn btn-outline-light"><i class="bi bi-heart-pulse"></i> Health</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card stat-card text-white bg-primary"><div class="card-body"><h6>Licenses</h6><h2><?php echo $counts['licenses']; ?></h2><small>Ready for CSV export</small></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-info"><div class="card-body"><h6>Devices</h6><h2><?php echo $counts['devices']; ?></h2><small>Registered device rows</small></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-warning"><div class="card-body"><h6>Logs</h6><h2><?php echo $counts['logs']; ?></h2><small>Activity log rows</small></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-success"><div class="card-body"><h6>Tables</h6><h2><?php echo $counts['tables']; ?></h2><small>Database backup scope</small></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-filetype-csv"></i> CSV Export</h5>
                    <span class="badge bg-light text-dark">Manager+</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a class="backup-tile text-decoration-none" href="?export=licenses">
                                <span class="backup-icon"><i class="bi bi-key"></i></span>
                                <strong>Licenses CSV</strong>
                                <small>License key, status, expiry, devices, notes</small>
                                <em>Download</em>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a class="backup-tile text-decoration-none" href="?export=devices">
                                <span class="backup-icon"><i class="bi bi-devices"></i></span>
                                <strong>Devices CSV</strong>
                                <small>Device hash, OS, browser, active status</small>
                                <em>Download</em>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a class="backup-tile text-decoration-none" href="?export=logs">
                                <span class="backup-icon"><i class="bi bi-clock-history"></i></span>
                                <strong>Logs CSV</strong>
                                <small>Actions, details, IP address, timestamp</small>
                                <em>Download</em>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="empty-icon mb-3"><i class="bi bi-database-down"></i></div>
                    <h5>Full Database Backup</h5>
                    <p class="text-muted">All tables-এর SQL dump download করবে। এটি শুধু Super Admin-এর জন্য।</p>
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> Backup file-এ sensitive data থাকতে পারে। নিরাপদ জায়গায় সংরক্ষণ করুন।</div>
                    <a class="btn btn-primary mt-auto <?php echo AdminHelpers::canDelete() ? '' : 'disabled'; ?>" href="?export=database">
                        <i class="bi bi-download"></i> Download SQL Backup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
