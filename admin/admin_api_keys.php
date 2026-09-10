<?php
declare(strict_types=1);

require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/database.php';
require_once '../includes/admin_api/AdminApiException.php';
require_once '../includes/admin_api/AdminApi.php';
require_once '../includes/admin_api/AdminApiRepository.php';
require_once 'includes/ui/integration.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit(); }

$db = Database::getInstance();
$message = '';
$error = '';
$allowedScopes = [
    'license:create' => 'Create licenses', 'license:read' => 'Read license status/list',
    'license:reveal' => 'Reveal license keys', 'license:extend' => 'Extend validity',
    'license:activate' => 'Activate licenses', 'license:suspend' => 'Suspend licenses',
    'license:ban' => 'Ban licenses and revoke devices', 'license:delete' => 'Soft-delete licenses and revoke devices',
    'device:read' => 'List license devices', 'device:revoke' => 'Revoke devices',
];
$schemaReady = true;
foreach (AdminApiRepository::requiredTables() as $table) {
    if (!AdminHelpers::tableExists($table)) { $schemaReady = false; break; }
}

function admin_api_normalize_ips(string $value): string
{
    $items = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $normalized = [];
    foreach ($items as $item) {
        if (!AdminApi::isValidIpRule($item)) { throw new InvalidArgumentException('Invalid IP/CIDR rule: ' . $item); }
        $normalized[] = $item;
    }
    return implode("\n", array_values(array_unique($normalized)));
}

function admin_api_selection(array $values, array $allowed, string $label): array
{
    $selection = array_values(array_unique(array_map('strval', $values)));
    if ($selection === [] || array_diff($selection, $allowed)) {
        throw new InvalidArgumentException('Select at least one valid ' . $label . '.');
    }
    return $selection;
}

function admin_api_expiry(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') { return null; }
    $timestamp = strtotime($value);
    if ($timestamp === false || $timestamp <= time()) { throw new InvalidArgumentException('Expiry must be in the future.'); }
    return date('Y-m-d H:i:s', $timestamp);
}

function admin_api_replace_assignments(PDO $db, int $keyId, array $scopes, array $apps): void
{
    $deleteScopes = $db->prepare('DELETE FROM admin_api_key_scopes WHERE api_key_id = :id');
    $deleteScopes->execute([':id' => $keyId]);
    $scopeInsert = $db->prepare('INSERT INTO admin_api_key_scopes (api_key_id, scope_name) VALUES (:id, :scope)');
    foreach ($scopes as $scope) { $scopeInsert->execute([':id' => $keyId, ':scope' => $scope]); }
    $deleteApps = $db->prepare('DELETE FROM admin_api_key_apps WHERE api_key_id = :id');
    $deleteApps->execute([':id' => $keyId]);
    $appInsert = $db->prepare('INSERT INTO admin_api_key_apps (api_key_id, app_id) VALUES (:id, :app_id)');
    foreach ($apps as $appId) { $appInsert->execute([':id' => $keyId, ':app_id' => $appId]); }
}

$apps = [];
if ($schemaReady) {
    try { $apps = $db->query('SELECT app_id, display_name, is_active FROM v2_client_apps ORDER BY display_name, app_id')->fetchAll(); }
    catch (Throwable $exception) { $schemaReady = false; $error = 'Admin API schema could not be inspected.'; }
}
$activeAppIds = array_values(array_map(static fn(array $app): string => (string)$app['app_id'], array_filter($apps, static fn(array $app): bool => (bool)$app['is_active'])));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    if (!$schemaReady) {
        $error = 'Run migration-v5.8.3-admin-license-api.sql before managing Admin API keys.';
    } else {
        try {
            if (isset($_POST['create_admin_api_key'])) {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '' || strlen($name) > 160) { throw new InvalidArgumentException('Key name must contain 1 to 160 characters.'); }
                $scopes = admin_api_selection((array)($_POST['scopes'] ?? []), array_keys($allowedScopes), 'scope');
                $selectedApps = admin_api_selection((array)($_POST['apps'] ?? []), $activeAppIds, 'active application');
                $allowedIps = admin_api_normalize_ips((string)($_POST['allowed_ips'] ?? ''));
                $rateLimit = filter_var($_POST['rate_limit_per_hour'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 100000]]);
                if ($rateLimit === false) { throw new InvalidArgumentException('Rate limit must be between 10 and 100000 requests per hour.'); }
                $expiresAt = admin_api_expiry($_POST['expires_at'] ?? null);
                $environment = in_array($_POST['environment'] ?? '', ['live', 'test'], true) ? (string)$_POST['environment'] : 'live';
                $token = 'licora_admin_' . $environment . '_' . bin2hex(random_bytes(32));
                $db->beginTransaction();
                $insert = $db->prepare("INSERT INTO admin_api_keys (name, key_prefix, key_hash, status, allowed_ips, rate_limit_per_hour, expires_at, created_by, created_at, updated_at) VALUES (:name, :prefix, :hash, 'active', :allowed_ips, :rate_limit, :expires_at, :created_by, NOW(), NOW())");
                $insert->execute([
                    ':name' => $name, ':prefix' => substr($token, 0, 32), ':hash' => hash('sha256', $token),
                    ':allowed_ips' => $allowedIps !== '' ? $allowedIps : null, ':rate_limit' => (int)$rateLimit,
                    ':expires_at' => $expiresAt, ':created_by' => $_SESSION['admin_id'] ?? null,
                ]);
                $keyId = (int)$db->lastInsertId();
                admin_api_replace_assignments($db, $keyId, $scopes, $selectedApps);
                $db->commit();
                AdminHelpers::audit('admin_api_key', $keyId, 'admin_api_key_created', ['name' => $name, 'scopes' => $scopes, 'apps' => $selectedApps]);
                $_SESSION['new_admin_api_key'] = ['id' => $keyId, 'name' => $name, 'token' => $token];
                header('Location: admin_api_keys.php?new_key=' . $keyId); exit();
            }

            if (isset($_POST['update_admin_api_key'])) {
                $keyId = filter_var($_POST['key_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $name = trim((string)($_POST['name'] ?? ''));
                if ($keyId === false || $name === '' || strlen($name) > 160) { throw new InvalidArgumentException('Valid key and name are required.'); }
                $scopes = admin_api_selection((array)($_POST['scopes'] ?? []), array_keys($allowedScopes), 'scope');
                $selectedApps = admin_api_selection((array)($_POST['apps'] ?? []), $activeAppIds, 'active application');
                $allowedIps = admin_api_normalize_ips((string)($_POST['allowed_ips'] ?? ''));
                $rateLimit = filter_var($_POST['rate_limit_per_hour'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 100000]]);
                if ($rateLimit === false) { throw new InvalidArgumentException('Rate limit must be between 10 and 100000 requests per hour.'); }
                $expiresAt = admin_api_expiry($_POST['expires_at'] ?? null);
                $db->beginTransaction();
                $update = $db->prepare("UPDATE admin_api_keys SET name = :name, allowed_ips = :allowed_ips, rate_limit_per_hour = :rate_limit, expires_at = :expires_at, updated_at = NOW() WHERE id = :id AND status <> 'revoked'");
                $update->execute([':name' => $name, ':allowed_ips' => $allowedIps !== '' ? $allowedIps : null, ':rate_limit' => (int)$rateLimit, ':expires_at' => $expiresAt, ':id' => (int)$keyId]);
                if ($update->rowCount() < 1) {
                    $exists = $db->prepare("SELECT COUNT(*) FROM admin_api_keys WHERE id = :id AND status <> 'revoked'");
                    $exists->execute([':id' => (int)$keyId]);
                    if ((int)$exists->fetchColumn() !== 1) { throw new InvalidArgumentException('Key is missing or permanently revoked.'); }
                }
                admin_api_replace_assignments($db, (int)$keyId, $scopes, $selectedApps);
                $db->commit();
                AdminHelpers::audit('admin_api_key', (int)$keyId, 'admin_api_key_updated', ['scopes' => $scopes, 'apps' => $selectedApps]);
                $message = 'Admin API key controls updated.';
            }

            if (isset($_POST['admin_api_key_action'])) {
                $keyId = filter_var($_POST['key_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $action = (string)($_POST['key_action'] ?? '');
                if ($keyId === false || !in_array($action, ['activate', 'suspend', 'rotate', 'revoke'], true)) { throw new InvalidArgumentException('Invalid key action.'); }
                if ($action === 'revoke') { AdminHelpers::requireDelete(); }
                $keyStmt = $db->prepare('SELECT id, name, key_prefix, status FROM admin_api_keys WHERE id = :id LIMIT 1');
                $keyStmt->execute([':id' => (int)$keyId]);
                $key = $keyStmt->fetch();
                if (!$key || $key['status'] === 'revoked') { throw new InvalidArgumentException('Key is missing or permanently revoked.'); }
                if ($action === 'rotate') {
                    $environment = strpos((string)$key['key_prefix'], 'licora_admin_test_') === 0 ? 'test' : 'live';
                    $token = 'licora_admin_' . $environment . '_' . bin2hex(random_bytes(32));
                    $update = $db->prepare("UPDATE admin_api_keys SET key_prefix = :prefix, key_hash = :hash, status = 'active', rotated_at = NOW(), updated_at = NOW() WHERE id = :id");
                    $update->execute([':prefix' => substr($token, 0, 32), ':hash' => hash('sha256', $token), ':id' => (int)$keyId]);
                    $_SESSION['new_admin_api_key'] = ['id' => (int)$keyId, 'name' => $key['name'], 'token' => $token];
                    AdminHelpers::audit('admin_api_key', (int)$keyId, 'admin_api_key_rotated', 'Previous secret invalidated immediately');
                    header('Location: admin_api_keys.php?new_key=' . (int)$keyId); exit();
                }
                $status = ['activate' => 'active', 'suspend' => 'suspended', 'revoke' => 'revoked'][$action];
                $update = $db->prepare('UPDATE admin_api_keys SET status = :status, updated_at = NOW() WHERE id = :id');
                $update->execute([':status' => $status, ':id' => (int)$keyId]);
                AdminHelpers::audit('admin_api_key', (int)$keyId, 'admin_api_key_' . $action, 'Status changed to ' . $status);
                $message = $action === 'revoke' ? 'Admin API key permanently revoked.' : 'Admin API key status updated.';
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('Admin API key management failed: ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Admin API key operation failed.';
        }
    }
}

$newKey = null;
if (isset($_GET['new_key'], $_SESSION['new_admin_api_key']) && AdminHelpers::canManage()) {
    if ((string)$_GET['new_key'] === (string)$_SESSION['new_admin_api_key']['id']) {
        $newKey = $_SESSION['new_admin_api_key'];
        unset($_SESSION['new_admin_api_key']);
    }
}

$keys = [];
$logs = [];
if ($schemaReady) {
    $keys = $db->query("SELECT k.*,
        GROUP_CONCAT(DISTINCT s.scope_name ORDER BY s.scope_name SEPARATOR ',') AS scope_names,
        GROUP_CONCAT(DISTINCT a.app_id ORDER BY a.app_id SEPARATOR ',') AS app_ids
        FROM admin_api_keys k
        LEFT JOIN admin_api_key_scopes s ON s.api_key_id = k.id
        LEFT JOIN admin_api_key_apps a ON a.api_key_id = k.id
        GROUP BY k.id ORDER BY k.id DESC")->fetchAll();
    $logs = $db->query('SELECT l.*, k.name AS key_name FROM admin_api_logs l LEFT JOIN admin_api_keys k ON k.id = l.api_key_id ORDER BY l.id DESC LIMIT 100')->fetchAll();
}
$csrf = Security::generateCSRFToken();
$endpoints = licora_ui_endpoints();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin License API · Licora</title><link rel="icon" href="assets/brand/favicon/favicon.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"><link rel="stylesheet" href="assets/css/admin-ui.css"></head>
<body class="admin-ui"><?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
<div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2"><div><h2><i class="bi bi-shield-lock"></i> Admin License API</h2><p class="text-muted mb-0">Scoped server-to-server license automation. Existing public API v1/v2 credentials are not accepted.</p></div><?php if ($schemaReady && AdminHelpers::canManage()): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKeyModal"><i class="bi bi-plus-circle"></i> Create Admin API Key</button><?php endif; ?></div>
<?php if (!$schemaReady): ?><div class="alert alert-warning"><strong>Migration required.</strong> Run <code>migration-v5.8.3-admin-license-api.sql</code>. No existing table is changed.</div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-success"><?php echo Security::escape($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo Security::escape($error); ?></div><?php endif; ?>
<?php if ($newKey): ?><div class="alert alert-success"><h5>Copy this secret now</h5><p>The full secret is shown only once. Store it in the order website's server-side secret store.</p><div class="input-group"><input id="admin-api-secret" class="form-control font-monospace" readonly value="<?php echo Security::escape($newKey['token']); ?>"><button class="btn btn-success" type="button" data-copy="<?php echo Security::escape($newKey['token']); ?>"><i class="bi bi-clipboard"></i> Copy</button></div></div><?php endif; ?>

<section class="ui-settings-section mb-3"><div class="ui-settings-section-header"><span><i class="bi bi-diagram-3"></i> Integration contract</span></div><div class="ui-settings-section-body"><p class="mb-2">Every request requires <code>Authorization: Bearer …</code>, <code>X-Licora-Timestamp</code>, a unique <code>X-Licora-Nonce</code>, and <code>X-Licora-Signature</code>. Create also requires <code>Idempotency-Key</code>. Signature input is <code>METHOD + "\n" + request-target + "\n" + timestamp + "\n" + nonce + "\n" + SHA256(exact body)</code>.</p><a href="../docs/ADMIN_LICENSE_API.md" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book"></i> API reference</a></div></section>

<section class="ui-table-panel mb-3"><div class="ui-table-toolbar"><div class="ui-table-toolbar-main"><input class="form-control" type="search" placeholder="Search Admin API keys" data-ui-table-search="admin-api-keys-table"><select class="form-select" data-ui-table-status="admin-api-keys-table"><option value="">All status</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="revoked">Revoked</option></select></div></div><div class="table-responsive ui-scrollbar"><table class="table table-hover align-middle mb-0" id="admin-api-keys-table" data-ui-paginate="true" data-ui-page-size="10"><thead><tr><th>Key</th><th>Applications</th><th>Scopes</th><th>Controls</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (!$keys): ?><tr><td colspan="5"><div class="empty-state py-4"><h6>No Admin API keys found</h6></div></td></tr><?php endif; ?>
<?php foreach ($keys as $key): $keyScopes = array_filter(explode(',', (string)$key['scope_names'])); $keyApps = array_filter(explode(',', (string)$key['app_ids'])); ?>
<tr data-ui-search="<?php echo Security::escape($key['name'] . ' ' . $key['key_prefix'] . ' ' . $key['scope_names'] . ' ' . $key['app_ids']); ?>" data-ui-status="<?php echo Security::escape($key['status']); ?>"><td><strong><?php echo Security::escape($key['name']); ?></strong><div><code><?php echo Security::escape($key['key_prefix']); ?>…</code></div><span class="badge bg-<?php echo $key['status'] === 'active' ? 'success' : ($key['status'] === 'revoked' ? 'danger' : 'secondary'); ?>"><?php echo Security::escape(ucfirst($key['status'])); ?></span><div class="ui-meta-line">Last used <?php echo $key['last_used_at'] ? Security::escape($key['last_used_at']) : 'Never'; ?></div></td><td><?php foreach ($keyApps as $app): ?><span class="badge bg-light text-dark border me-1"><?php echo Security::escape($app); ?></span><?php endforeach; ?></td><td><small><?php echo Security::escape(implode(', ', $keyScopes)); ?></small></td><td><div><?php echo (int)$key['rate_limit_per_hour']; ?>/hour</div><small class="text-muted">IPs: <?php echo $key['allowed_ips'] ? nl2br(Security::escape($key['allowed_ips'])) : 'Any'; ?><br>Expires: <?php echo $key['expires_at'] ? Security::escape($key['expires_at']) : 'Never'; ?></small></td><td class="text-end">
<?php if ($key['status'] !== 'revoked' && AdminHelpers::canManage()): ?><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editKey<?php echo (int)$key['id']; ?>">Edit</button>
<form method="POST" class="d-inline" data-no-spinner><input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>"><input type="hidden" name="admin_api_key_action" value="1"><input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>"><input type="hidden" name="key_action" value="<?php echo $key['status'] === 'active' ? 'suspend' : 'activate'; ?>"><button class="btn btn-sm btn-outline-secondary"><?php echo $key['status'] === 'active' ? 'Suspend' : 'Activate'; ?></button></form>
<form method="POST" class="d-inline" data-no-spinner><input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>"><input type="hidden" name="admin_api_key_action" value="1"><input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>"><input type="hidden" name="key_action" value="rotate"><button class="btn btn-sm btn-outline-warning" data-confirm="Rotate this key? The old secret stops working immediately.">Rotate</button></form>
<?php if (AdminHelpers::canDelete()): ?><form method="POST" class="d-inline" data-no-spinner><input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>"><input type="hidden" name="admin_api_key_action" value="1"><input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>"><input type="hidden" name="key_action" value="revoke"><button class="btn btn-sm btn-outline-danger" data-confirm="Permanently revoke this key?">Revoke</button></form><?php endif; ?><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><div class="ui-table-footer"><span data-ui-count-for="admin-api-keys-table"></span><ul class="pagination mb-0" data-ui-pager-for="admin-api-keys-table"></ul></div></section>

<section class="ui-table-panel"><div class="ui-table-toolbar"><strong>Latest API requests</strong></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Time</th><th>Key</th><th>Event</th><th>Route</th><th>Status</th><th>Request ID</th></tr></thead><tbody><?php if (!$logs): ?><tr><td colspan="6" class="text-muted">No requests logged.</td></tr><?php endif; ?><?php foreach ($logs as $log): ?><tr><td><?php echo Security::escape($log['created_at']); ?></td><td><?php echo Security::escape($log['key_name'] ?? 'Deleted key'); ?></td><td><?php echo Security::escape($log['event_type']); ?></td><td><code><?php echo Security::escape($log['http_method'] . ' ' . $log['request_path']); ?></code></td><td><?php echo (int)$log['response_code']; ?></td><td><code><?php echo Security::escape($log['request_id']); ?></code></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>

<?php
$renderFields = static function (array $selectedScopes, array $selectedApps, string $allowedIps, int $rateLimit, ?string $expiresAt) use ($allowedScopes, $apps): void { ?>
<div class="ui-form-grid"><div><label class="form-label">Allowed applications</label><?php foreach ($apps as $app): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="apps[]" value="<?php echo Security::escape($app['app_id']); ?>" <?php echo in_array($app['app_id'], $selectedApps, true) ? 'checked' : ''; ?> <?php echo $app['is_active'] ? '' : 'disabled'; ?>><label class="form-check-label"><?php echo Security::escape($app['display_name'] . ' (' . $app['app_id'] . ')'); ?><?php echo $app['is_active'] ? '' : ' — inactive'; ?></label></div><?php endforeach; ?></div><div><label class="form-label">Permissions</label><?php foreach ($allowedScopes as $scope => $label): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="<?php echo Security::escape($scope); ?>" <?php echo in_array($scope, $selectedScopes, true) ? 'checked' : ''; ?>><label class="form-check-label"><code><?php echo Security::escape($scope); ?></code> — <?php echo Security::escape($label); ?></label></div><?php endforeach; ?></div><div><label class="form-label">Allowed IP/CIDR (optional)</label><textarea class="form-control" name="allowed_ips" rows="4" placeholder="203.0.113.10&#10;2001:db8::/48"><?php echo Security::escape($allowedIps); ?></textarea></div><div><label class="form-label">Rate limit / hour</label><input class="form-control" type="number" name="rate_limit_per_hour" min="10" max="100000" value="<?php echo $rateLimit; ?>" required><label class="form-label mt-2">Expiry (optional)</label><input class="form-control" type="datetime-local" name="expires_at" value="<?php echo $expiresAt ? Security::escape(date('Y-m-d\TH:i', strtotime($expiresAt))) : ''; ?>"></div></div>
<?php }; ?>
<div class="modal fade" id="createKeyModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Create Admin API key</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>"><input type="hidden" name="create_admin_api_key" value="1"><div class="row g-3 mb-3"><div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="160" required></div><div class="col-md-4"><label class="form-label">Environment label</label><select class="form-select" name="environment"><option value="live">Live</option><option value="test">Test</option></select></div></div><?php $renderFields(['license:create', 'license:read'], [], '', 300, null); ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create key</button></div></form></div></div></div>
<?php foreach ($keys as $key): if ($key['status'] === 'revoked') continue; $keyScopes = array_filter(explode(',', (string)$key['scope_names'])); $keyApps = array_filter(explode(',', (string)$key['app_ids'])); ?><div class="modal fade" id="editKey<?php echo (int)$key['id']; ?>" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit <?php echo Security::escape($key['name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>"><input type="hidden" name="update_admin_api_key" value="1"><input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="160" value="<?php echo Security::escape($key['name']); ?>" required></div><?php $renderFields($keyScopes, $keyApps, (string)$key['allowed_ips'], (int)$key['rate_limit_per_hour'], $key['expires_at']); ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save controls</button></div></form></div></div></div><?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script></body></html>
