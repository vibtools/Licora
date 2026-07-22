<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
$auth = new Auth(); if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit; }
$dbOk=false; $dbMsg=''; try { Database::getInstance()->query('SELECT 1'); $dbOk=true; $dbMsg='Connected'; } catch(Exception $e){$dbMsg='Failed';}
$checks = [
 ['Database Status',$dbOk,$dbMsg],
 ['PHP Version', version_compare(PHP_VERSION,'8.0.0','>='), PHP_VERSION],
 ['Root Writable', is_writable(dirname(__DIR__)), is_writable(dirname(__DIR__))?'Writable':'Not writable'],
 ['Admin Assets Writable', is_writable(__DIR__.'/assets'), is_writable(__DIR__.'/assets')?'Writable':'Not writable'],
 ['Cron Directory', is_dir(dirname(__DIR__).'/cron'), is_dir(dirname(__DIR__).'/cron')?'Found':'Missing'],
 ['Config Local', file_exists(dirname(__DIR__).'/includes/config.local.php'), file_exists(dirname(__DIR__).'/includes/config.local.php')?'Found':'Not found']
];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>System Health</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><script>tailwind=window.tailwind||{};tailwind.config={corePlugins:{preflight:false},darkMode:'class'};</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css"></head><body class="admin-ui"><?php include 'includes/navbar.php'; ?><div class="container-fluid admin-shell"><div class="page-hero"><h2><i class="bi bi-heart-pulse"></i> System Health</h2><p>Database, PHP, writable folder, and cron status.</p></div><div class="row g-3"><?php foreach($checks as $c): ?><div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><h5><?php echo Security::escape($c[0]); ?></h5><span class="badge bg-<?php echo $c[1]?'success':'danger'; ?>"><?php echo $c[1]?'OK':'Issue'; ?></span></div><p class="text-muted mb-0"><?php echo Security::escape($c[2]); ?></p></div></div></div><?php endforeach; ?></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>
