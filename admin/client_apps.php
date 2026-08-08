<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$success = '';
$error = '';
$schemaReady = AdminHelpers::tableExists('v2_client_apps');

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
    if (!$schemaReady) {
        $error = 'API v2 database migration is required before client applications can be managed.';
    } else {
        $action = (string)($_POST['action'] ?? '');
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
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API v2 Client Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div><h2><i class="bi bi-boxes"></i> API v2 Client Applications</h2><p>Register public application identities and token policies. No client master API key is created or displayed here.</p></div>
        <a class="btn btn-outline-light" href="v2_devices.php"><i class="bi bi-shield-check"></i> V2 Devices</a>
    </div>
    <?php if (!$schemaReady): ?><div class="alert alert-warning">API v2 schema is not installed. Run <code>php scripts/setup-v2.php</code> after deploying the v5.2.0 source.</div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo Security::escape($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo Security::escape($error); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card"><div class="card-header"><h5 class="mb-0">Create Client App</h5></div><div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label">App ID</label><input class="form-control" name="app_id" placeholder="vibrapilot" pattern="[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]" required><div class="form-text">Stable public identifier. It cannot be renamed from this page after creation.</div></div>
                    <div class="mb-3"><label class="form-label">Display Name</label><input class="form-control" name="display_name" maxlength="160" required></div>
                    <div class="mb-3"><label class="form-label">Minimum Version (optional)</label><input class="form-control" name="min_version" placeholder="1.0.6.2"></div>
                    <div class="row g-2"><div class="col-6"><label class="form-label">Access TTL (s)</label><input class="form-control" type="number" name="access_token_ttl" min="300" max="86400" value="3600"></div><div class="col-6"><label class="form-label">Refresh TTL (s)</label><input class="form-control" type="number" name="refresh_token_ttl" min="3600" max="31536000" value="2592000"></div></div>
                    <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Clock Skew (s)</label><input class="form-control" type="number" name="clock_skew_seconds" min="30" max="900" value="300"></div><div class="col-6"><label class="form-label">Rate / hour</label><input class="form-control" type="number" name="rate_limit_per_hour" min="10" max="100000" value="300"></div></div>
                    <button class="btn btn-primary w-100 mt-3" <?php echo $schemaReady ? '' : 'disabled'; ?>>Create Client App</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <?php if (!$apps): ?><div class="card"><div class="card-body text-muted">No API v2 client applications are configured.</div></div><?php endif; ?>
            <?php foreach ($apps as $app): ?>
            <div class="card mb-3"><div class="card-body">
                <form method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$app['id']; ?>">
                    <div class="col-md-4"><label class="form-label">App ID</label><input class="form-control" value="<?php echo Security::escape($app['app_id']); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label">Display Name</label><input class="form-control" name="display_name" maxlength="160" value="<?php echo Security::escape($app['display_name']); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Minimum Version</label><input class="form-control" name="min_version" value="<?php echo Security::escape($app['min_version'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Access TTL</label><input class="form-control" type="number" name="access_token_ttl" min="300" max="86400" value="<?php echo (int)$app['access_token_ttl']; ?>"></div>
                    <div class="col-md-3"><label class="form-label">Refresh TTL</label><input class="form-control" type="number" name="refresh_token_ttl" min="3600" max="31536000" value="<?php echo (int)$app['refresh_token_ttl']; ?>"></div>
                    <div class="col-md-2"><label class="form-label">Clock Skew</label><input class="form-control" type="number" name="clock_skew_seconds" min="30" max="900" value="<?php echo (int)$app['clock_skew_seconds']; ?>"></div>
                    <div class="col-md-2"><label class="form-label">Rate/hour</label><input class="form-control" type="number" name="rate_limit_per_hour" min="10" max="100000" value="<?php echo (int)$app['rate_limit_per_hour']; ?>"></div>
                    <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="active-<?php echo (int)$app['id']; ?>" <?php echo (int)$app['is_active'] ? 'checked' : ''; ?>><label class="form-check-label" for="active-<?php echo (int)$app['id']; ?>">Active</label></div></div>
                    <div class="col-md-1"><button class="btn btn-outline-primary w-100" title="Save"><i class="bi bi-save"></i></button></div>
                </form>
            </div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/admin-ui.js"></script>
</body></html>
