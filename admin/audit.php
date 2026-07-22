<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/admin_helpers.php';
$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit; }
$db = Database::getInstance();
$rows = [];
if (AdminHelpers::tableExists('audit_trail')) {
    $rows = $db->query("SELECT a.*, u.username FROM audit_trail a LEFT JOIN admin_users u ON a.admin_id = u.id ORDER BY a.created_at DESC LIMIT 1000")->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Audit Trail</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><script>tailwind=window.tailwind||{};tailwind.config={corePlugins:{preflight:false},darkMode:'class'};</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css"></head><body class="admin-ui"><?php include 'includes/navbar.php'; ?><div class="container-fluid admin-shell"><div class="page-hero"><h2><i class="bi bi-journal-text"></i> Audit Trail</h2><p>কে কখন কোন license/API key/device/settings পরিবর্তন করেছে তার history।</p></div><?php if(!AdminHelpers::tableExists('audit_trail')): ?><div class="alert alert-warning">Please run migration-v4.sql to enable full audit trail.</div><?php endif; ?><div class="card"><div class="card-header"><h5 class="mb-0">Recent Changes</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle" data-ui-paginate="true" data-ui-page-size="10" id="audit-table"><thead class="table-dark"><tr><th>Time</th><th>Admin</th><th>Entity</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo Security::escape($r['created_at']); ?></td><td><?php echo Security::escape($r['username'] ?: 'System'); ?></td><td><?php echo Security::escape($r['entity_type']); ?> #<?php echo Security::escape($r['entity_id']); ?></td><td><span class="badge bg-primary"><?php echo Security::escape($r['action']); ?></span></td><td><small><?php echo Security::escape($r['details']); ?></small></td><td><?php echo Security::escape($r['ip_address']); ?></td></tr><?php endforeach; ?></tbody></table></div><nav class="mt-3"><ul class="pagination justify-content-end" data-ui-pager-for="audit-table"></ul></nav></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>
