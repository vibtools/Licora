<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit(); }
$db = Database::getInstance();
$success = '';
$error = '';
$schemaReady = AdminHelpers::tableExists('v2_device_credentials');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_device'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $id = (int)($_POST['device_credential_id'] ?? 0);
    if (!$schemaReady || $id < 1) {
        $error = 'Invalid API v2 device.';
    } else {
        try {
            require_once '../includes/v2/V2Exception.php';
            require_once '../includes/v2/V2TokenService.php';
            require_once '../includes/v2/V2Repository.php';
            $repo = new V2Repository($db);
            $repo->revokeCredential($id);
            AdminHelpers::audit('v2_device', $id, 'v2_device_revoked', 'API v2 device revoked by administrator');
            $success = 'API v2 device revoked. Its refresh credentials are no longer valid.';
        } catch (Throwable $e) {
            error_log('v2 admin revoke failed: ' . $e->getMessage());
            $error = 'The device could not be revoked.';
        }
    }
}

$devices = [];
if ($schemaReady) {
    $sql = "SELECT dc.id, dc.app_id, dc.device_hash, dc.public_key_fingerprint, dc.status, dc.first_seen_at, dc.last_seen_at, dc.revoked_at, l.license_key, a.display_name
            FROM v2_device_credentials dc
            JOIN licenses l ON l.id = dc.license_id
            LEFT JOIN v2_client_apps a ON a.app_id = dc.app_id
            ORDER BY dc.last_seen_at DESC, dc.id DESC";
    $devices = $db->query($sql)->fetchAll();
}
function licora_v2_mask_license($key) {
    $key = (string)$key;
    return strlen($key) > 12 ? substr($key, 0, 8) . '…' . substr($key, -4) : '…';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>API v2 Devices</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/admin-ui.css"></head>
<body class="admin-ui"><?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
<div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3"><div><h2><i class="bi bi-shield-check"></i> API v2 Devices</h2><p>Review device-bound public-key credentials and revoke compromised or retired API v2 clients.</p></div><a class="btn btn-outline-light" href="client_apps.php"><i class="bi bi-boxes"></i> Client Applications</a></div>
<?php if (!$schemaReady): ?><div class="alert alert-warning">API v2 provisioning is incomplete. Open <a href="client_apps.php" class="alert-link">Client Apps</a> to initialize it from the authenticated admin UI, or run <code>php scripts/setup-v2.php</code> from CLI.</div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo Security::escape($success); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo Security::escape($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h5 class="mb-0">Registered API v2 Device Credentials</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>App</th><th>License</th><th>Device</th><th>Public-key fingerprint</th><th>Status</th><th>Last seen</th><th></th></tr></thead><tbody>
<?php if (!$devices): ?><tr><td colspan="7" class="text-muted text-center py-4">No API v2 device credentials found.</td></tr><?php endif; ?>
<?php foreach ($devices as $row): ?><tr><td><strong><?php echo Security::escape($row['display_name'] ?: $row['app_id']); ?></strong><br><small class="text-muted"><?php echo Security::escape($row['app_id']); ?></small></td><td><code><?php echo Security::escape(licora_v2_mask_license($row['license_key'])); ?></code></td><td><code><?php echo Security::escape(substr((string)$row['device_hash'], 0, 16)); ?>…</code></td><td><code><?php echo Security::escape(substr((string)$row['public_key_fingerprint'], 0, 20)); ?>…</code></td><td><span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo Security::escape($row['status']); ?></span></td><td><?php echo Security::escape($row['last_seen_at']); ?></td><td><?php if ($row['status'] === 'active'): ?><form method="post" onsubmit="return confirm('Revoke this API v2 device and all of its refresh credentials?');"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="device_credential_id" value="<?php echo (int)$row['id']; ?>"><button class="btn btn-sm btn-outline-danger" name="revoke_device" value="1"><i class="bi bi-slash-circle"></i> Revoke</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>
