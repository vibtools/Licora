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
<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>System Health · Licora</title><link rel="icon" href="assets/brand/favicon/favicon.ico"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><link rel="stylesheet" href="assets/css/admin-ui.css"></head><body class="admin-ui"><?php include 'includes/navbar.php'; ?><div class="container-fluid admin-shell"><div class="page-hero"><h2><i class="bi bi-heart-pulse"></i> System Health</h2></div><section class="card"><div class="card-header"><h5 class="mb-0">Runtime Checks</h5></div><div class="table-responsive ui-scrollbar"><table class="table align-middle mb-0"><thead><tr><th>Check</th><th>Status</th><th>Value</th></tr></thead><tbody><?php foreach($checks as $c): ?><tr><td><strong><?php echo Security::escape($c[0]); ?></strong></td><td><span class="badge bg-<?php echo $c[1]?'success':'danger'; ?>"><?php echo $c[1]?'OK':'Issue'; ?></span></td><td><?php echo Security::escape($c[2]); ?></td></tr><?php endforeach; ?></tbody></table></div></section></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>