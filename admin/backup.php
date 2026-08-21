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
<html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Backup & Export · Licora</title><link rel="icon" href="assets/brand/favicon/favicon.ico"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><link rel="stylesheet" href="assets/css/admin-ui.css"></head>
<body class="admin-ui"><?php include 'includes/navbar.php'; ?><div class="container-fluid admin-shell">
<div class="page-hero d-flex justify-content-between align-items-center gap-2"><h2><i class="bi bi-download"></i> Backup & Export</h2><div class="d-flex gap-2"><a href="settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> Settings</a><a href="health.php" class="btn btn-outline-secondary"><i class="bi bi-heart-pulse"></i> Health</a></div></div>
<div class="row g-2 mb-2"><div class="col-6 col-lg-3"><div class="card"><div class="card-body ui-stat-compact"><span>Licenses</span><strong><?php echo $counts['licenses']; ?></strong></div></div></div><div class="col-6 col-lg-3"><div class="card"><div class="card-body ui-stat-compact"><span>Devices</span><strong><?php echo $counts['devices']; ?></strong></div></div></div><div class="col-6 col-lg-3"><div class="card"><div class="card-body ui-stat-compact"><span>Logs</span><strong><?php echo $counts['logs']; ?></strong></div></div></div><div class="col-6 col-lg-3"><div class="card"><div class="card-body ui-stat-compact"><span>Tables</span><strong><?php echo $counts['tables']; ?></strong></div></div></div></div>
<div class="ui-settings-grid">
<section class="ui-settings-section"><div class="ui-settings-section-header"><span><i class="bi bi-filetype-csv"></i> CSV Export</span><span class="badge bg-light text-dark">Manager+</span></div><div class="ui-settings-section-body"><div class="ui-export-list"><a href="?export=licenses"><i class="bi bi-key"></i><span>Licenses CSV</span><i class="bi bi-download ms-auto"></i></a><a href="?export=devices"><i class="bi bi-laptop"></i><span>Devices CSV</span><i class="bi bi-download ms-auto"></i></a><a href="?export=logs"><i class="bi bi-clock-history"></i><span>Logs CSV</span><i class="bi bi-download ms-auto"></i></a></div></div></section>
<section class="ui-settings-section"><div class="ui-settings-section-header"><span><i class="bi bi-database-down"></i> Database Backup</span><span class="badge bg-light text-dark">Super Admin</span></div><div class="ui-settings-section-body"><div class="alert alert-warning mb-2"><i class="bi bi-exclamation-triangle"></i> SQL backups can contain sensitive data. Store downloaded files securely.</div><a class="btn btn-primary w-100 <?php echo AdminHelpers::canDelete() ? '' : 'disabled'; ?>" href="?export=database"><i class="bi bi-download"></i> Download SQL Backup</a></div></section>
</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>