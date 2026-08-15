<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/v2/V2Exception.php';
require_once '../includes/v2/V2KeyManager.php';
require_once '../includes/v2/V2Provisioner.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$success = '';
$error = '';
$keyManager = new V2KeyManager();
$provisioner = new V2Provisioner($db, $keyManager, dirname(__DIR__) . '/migration-v5.2.0-api-v2.sql');
$provisionStatus = $provisioner->status();
$schemaReady = !empty($provisionStatus['schema_ready']);
$v2Ready = !empty($provisionStatus['ready']);

function licora_v2_app_id($value) {
    $value = trim((string)$value);
    return preg_match('/^[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]$/', $value) ? $value : '';
}
function licora_v2_bounded_int($value, $min, $max, $fallback) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) { return $fallback; }
    return (int)$value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'initialize_v2') {
        try {
            $result = $provisioner->provision(true);
            $provisionStatus = $provisioner->status();
            $schemaReady = !empty($provisionStatus['schema_ready']);
            $v2Ready = !empty($provisionStatus['ready']);
            AdminHelpers::audit('v2_system', null, 'v2_provisioned', 'API v2 additive schema/signing-key provisioning completed from authenticated admin UI');
            $success = !empty($result['signing_keys_generated'])
                ? 'Secure API v2 initialized. Database tables are ready and a new server signing key pair was generated.'
                : 'Secure API v2 verified. Database tables and the existing server signing key pair are ready.';
        } catch (Throwable $e) {
            error_log('API v2 admin provisioning failed: ' . $e->getMessage());
            $error = 'Secure API v2 could not be initialized automatically. Existing key files are never replaced. Check database privileges, includes-directory permissions, and the server error log.';
        }
    } elseif (!$schemaReady) {
        $error = 'API v2 database migration is required before client applications can be managed.';
    } else {
        if ($action === 'create') {
            $appId = licora_v2_app_id($_POST['app_id'] ?? '');
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $minVersion = trim((string)($_POST['min_version'] ?? ''));
            if ($appId === '' || $displayName === '' || strlen($displayName) > 160 || ($minVersion !== '' && !preg_match('/^\d+(?:\.\d+){1,3}$/', $minVersion))) {
                $error = 'Enter a valid app ID, display name and optional numeric minimum version.';
            } else {
                try {
                    $stmt = $db->prepare('INSERT INTO v2_client_apps (app_id, display_name, is_active, min_version, access_token_ttl, refresh_token_ttl, clock_skew_seconds, rate_limit_per_hour, created_at, updated_at) VALUES (:app_id, :display_name, 1, :min_version, :access_ttl, :refresh_ttl, :clock_skew, :rate_limit, NOW(), NOW())');
                    $stmt->execute([
                        ':app_id' => $appId,
                        ':display_name' => $displayName,
                        ':min_version' => $minVersion === '' ? null : $minVersion,
                        ':access_ttl' => licora_v2_bounded_int($_POST['access_token_ttl'] ?? 3600, 300, 86400, 3600),
                        ':refresh_ttl' => licora_v2_bounded_int($_POST['refresh_token_ttl'] ?? 2592000, 3600, 31536000, 2592000),
                        ':clock_skew' => licora_v2_bounded_int($_POST['clock_skew_seconds'] ?? 300, 30, 900, 300),
                        ':rate_limit' => licora_v2_bounded_int($_POST['rate_limit_per_hour'] ?? 300, 10, 100000, 300),
                    ]);
                    AdminHelpers::audit('v2_client_app', (int)$db->lastInsertId(), 'v2_client_app_created', 'API v2 client application created: ' . $appId);
                    $success = 'Client application created.';
                } catch (PDOException $e) {
                    $error = $e->getCode() === '23000' ? 'That App ID already exists.' : 'Client application could not be created.';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $minVersion = trim((string)($_POST['min_version'] ?? ''));
            if ($id < 1 || $displayName === '' || strlen($displayName) > 160 || ($minVersion !== '' && !preg_match('/^\d+(?:\.\d+){1,3}$/', $minVersion))) {
                $error = 'Invalid client application update.';
            } else {
                $stmt = $db->prepare('UPDATE v2_client_apps SET display_name=:display_name, is_active=:is_active, min_version=:min_version, access_token_ttl=:access_ttl, refresh_token_ttl=:refresh_ttl, clock_skew_seconds=:clock_skew, rate_limit_per_hour=:rate_limit, updated_at=NOW() WHERE id=:id');
                $stmt->execute([
                    ':display_name' => $displayName,
                    ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                    ':min_version' => $minVersion === '' ? null : $minVersion,
                    ':access_ttl' => licora_v2_bounded_int($_POST['access_token_ttl'] ?? 3600, 300, 86400, 3600),
                    ':refresh_ttl' => licora_v2_bounded_int($_POST['refresh_token_ttl'] ?? 2592000, 3600, 31536000, 2592000),
                    ':clock_skew' => licora_v2_bounded_int($_POST['clock_skew_seconds'] ?? 300, 30, 900, 300),
                    ':rate_limit' => licora_v2_bounded_int($_POST['rate_limit_per_hour'] ?? 300, 10, 100000, 300),
                    ':id' => $id,
                ]);
                AdminHelpers::audit('v2_client_app', $id, 'v2_client_app_updated', 'API v2 client application policy updated');
                $success = 'Client application updated.';
            }
        }
    }
}

$apps = [];
if ($schemaReady) {
    $apps = $db->query('SELECT * FROM v2_client_apps ORDER BY display_name, app_id')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Apps · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2">
        <h2><i class="bi bi-boxes"></i> API v2 Client Apps</h2>
        <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="v2_devices.php"><i class="bi bi-shield-check"></i> V2 Devices</a><?php if (AdminHelpers::canManage()): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientAppModal" <?php echo $v2Ready ? '' : 'disabled'; ?>><i class="bi bi-plus-circle"></i> Create Client App</button><?php endif; ?></div>
    </div>
    <?php if (!$v2Ready): ?>
    <div class="alert alert-warning"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2"><div><strong>Secure API v2 provisioning is incomplete.</strong><div class="ui-meta-line">Schema: <?php echo $schemaReady ? 'ready' : 'missing'; ?> · Signing key pair: <?php echo !empty($provisionStatus['key_pair_ready']) ? 'ready' : 'missing or invalid'; ?></div></div><form method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="action" value="initialize_v2"><button class="btn btn-warning" type="submit" data-confirm="Initialize or verify Secure API v2 now? Existing API v1 data and signing key files will not be replaced."><i class="bi bi-shield-lock"></i> Initialize API v2</button></form></div></div>
    <?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo Security::escape($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo Security::escape($error); ?></div><?php endif; ?>

    <section class="ui-table-panel">
        <div class="ui-table-toolbar"><div class="ui-table-toolbar-main"><input type="search" class="form-control" placeholder="Search client apps" data-ui-table-search="client-apps-list"><select class="form-select" data-ui-table-status="client-apps-list"><option value="">All status</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="ui-table-toolbar-actions"><span class="ui-meta-line"><?php echo count($apps); ?> apps</span></div></div>
        <div id="client-apps-list">
        <?php if (!$apps): ?><div class="empty-state py-4"><h6>No API v2 client apps found</h6></div><?php endif; ?>
        <?php foreach ($apps as $app): ?>
            <article class="ui-compact-record" data-ui-search="<?php echo Security::escape(($app['app_id'] ?? '') . ' ' . ($app['display_name'] ?? '') . ' ' . ($app['min_version'] ?? '')); ?>" data-ui-status="<?php echo (int)$app['is_active'] ? 'active' : 'inactive'; ?>">
                <form method="post" class="ui-client-app-form">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$app['id']; ?>">
                    <div><label class="form-label">App ID</label><input class="form-control" value="<?php echo Security::escape($app['app_id']); ?>" readonly></div>
                    <div><label class="form-label">Display Name</label><input class="form-control" name="display_name" maxlength="160" value="<?php echo Security::escape($app['display_name']); ?>" required></div>
                    <div><label class="form-label">Minimum Version</label><input class="form-control" name="min_version" value="<?php echo Security::escape($app['min_version'] ?? ''); ?>"></div>
                    <div><label class="form-label">Access TTL</label><input class="form-control" type="number" name="access_token_ttl" min="300" max="86400" value="<?php echo (int)$app['access_token_ttl']; ?>"></div>
                    <div><label class="form-label">Refresh TTL</label><input class="form-control" type="number" name="refresh_token_ttl" min="3600" max="31536000" value="<?php echo (int)$app['refresh_token_ttl']; ?>"></div>
                    <div><label class="form-label">Clock Skew</label><input class="form-control" type="number" name="clock_skew_seconds" min="30" max="900" value="<?php echo (int)$app['clock_skew_seconds']; ?>"></div>
                    <div><label class="form-label">Rate / hour</label><input class="form-control" type="number" name="rate_limit_per_hour" min="10" max="100000" value="<?php echo (int)$app['rate_limit_per_hour']; ?>"></div>
                    <label class="ui-switch-field"><input class="form-check-input" type="checkbox" name="is_active" <?php echo (int)$app['is_active'] ? 'checked' : ''; ?>><span>Active</span></label>
                    <?php if (AdminHelpers::canManage()): ?><button class="ui-icon-button" title="Save client app" aria-label="Save client app"><i class="bi bi-save"></i></button><?php endif; ?>
                </form>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="modal fade" id="createClientAppModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create Client App</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="action" value="create"><div class="ui-form-grid"><div><label class="form-label">App ID</label><input class="form-control" name="app_id" placeholder="vibrapilot" pattern="[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]" required></div><div><label class="form-label">Display Name</label><input class="form-control" name="display_name" maxlength="160" required></div><div><label class="form-label">Minimum Version</label><input class="form-control" name="min_version" placeholder="1.0.6.2"></div><div><label class="form-label">Access TTL</label><input class="form-control" type="number" name="access_token_ttl" min="300" max="86400" value="3600"></div><div><label class="form-label">Refresh TTL</label><input class="form-control" type="number" name="refresh_token_ttl" min="3600" max="31536000" value="2592000"></div><div><label class="form-label">Clock Skew</label><input class="form-control" type="number" name="clock_skew_seconds" min="30" max="900" value="300"></div><div><label class="form-label">Rate / hour</label><input class="form-control" type="number" name="rate_limit_per_hour" min="10" max="100000" value="300"></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" <?php echo $v2Ready ? '' : 'disabled'; ?>><i class="bi bi-plus-circle"></i> Create Client App</button></div>
</form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
