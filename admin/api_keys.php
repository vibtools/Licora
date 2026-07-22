<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$db = Database::getInstance();
$message = '';
$error = '';
$schemaReady = AdminHelpers::ensureV5Schema();
if (!$schemaReady) {
    $message = 'App/Scope columns will be used if they already exist. If saving fails, run migration-v5-hotfix.sql once.';
}

function detected_api_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/api_keys.php')), '/\\');
    if ($base === '/' || $base === '.') { $base = ''; }
    return $scheme . '://' . $host . $base . '/api/verify.php';
}

// সেটিংস পান
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$detectedApiBaseUrl = detected_api_base_url();

// API কী তৈরি
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_api_key'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $name = Security::sanitize($_POST['api_key_name'] ?? '');
    $expires = $_POST['api_key_expires'] ?: null;
    $appName = Security::sanitize($_POST['app_name'] ?? '');
    $scopeLabel = Security::sanitize($_POST['scope_label'] ?? '');

    if (empty($name)) {
        $error = 'API key name is required';
    } else {
        try {
            $apiKey = bin2hex(random_bytes(32));
            $apiKeyHash = hash('sha256', $apiKey);
            $apiKeyEncrypted = Security::encrypt($apiKey);
            try {
                $stmt = $db->prepare("INSERT INTO api_keys (api_key_hash, api_key_encrypted, name, app_name, scope_label, expires_at, created_at) VALUES (:hash, :encrypted, :name, :app_name, :scope_label, :expires, NOW())");
                $stmt->execute([':hash'=>$apiKeyHash, ':encrypted'=>$apiKeyEncrypted, ':name'=>$name, ':app_name'=>$appName, ':scope_label'=>$scopeLabel, ':expires'=>$expires]);
            } catch (PDOException $columnError) {
                if ($columnError->getCode() === '42S22') {
                    throw new Exception('API key App/Scope columns are missing in the connected database. Run migration-v5-hotfix.sql once.');
                }
                throw $columnError;
            }
            $keyId = $db->lastInsertId();
            AdminHelpers::audit('api_key', $keyId, 'api_key_created', 'API key created: ' . $name);
            $_SESSION['new_api_key'] = ['id' => $keyId, 'key' => $apiKey, 'name' => $name];
            header("Location: api_keys.php?new_key=$keyId");
            exit();
        } catch (Exception $e) {
            $error = 'API key create failed: ' . $e->getMessage();
        }
    }
}

// API কী টেস্ট
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api_key'])) {
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $apiKey = trim($_POST['test_key'] ?? '');
    $keyId = (int)($_POST['key_id'] ?? 0);
    if ($apiKey === '' && $keyId > 0) {
        $keyStmt = $db->prepare("SELECT api_key_encrypted FROM api_keys WHERE id = :id");
        $keyStmt->execute([':id' => $keyId]);
        $storedKey = $keyStmt->fetch();
        if ($storedKey && !empty($storedKey['api_key_encrypted'])) {
            $apiKey = Security::decrypt($storedKey['api_key_encrypted']);
        }
    }
    $testResult = testAPIKey($apiKey, $db, $settings['api_base_url'] ?? $detectedApiBaseUrl);
    if ($testResult['success']) {
        $message = 'API Key Test Successful! Status: ' . $testResult['status'] . ' Message: ' . $testResult['message'];
    } else {
        $error = 'API Key Test Failed! Error: ' . $testResult['error'] . ' Details: ' . $testResult['details'];
    }
}

// API key app/scope update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_api_key_scope'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $key_id = (int)($_POST['key_id'] ?? 0);
    $appName = Security::sanitize($_POST['app_name'] ?? '');
    $scopeLabel = Security::sanitize($_POST['scope_label'] ?? '');
    if ($key_id > 0) {
        try {
            $stmt = $db->prepare("UPDATE api_keys SET app_name = :app_name, scope_label = :scope_label WHERE id = :id");
            $stmt->execute([':app_name' => $appName, ':scope_label' => $scopeLabel, ':id' => $key_id]);
            AdminHelpers::audit('api_key', $key_id, 'api_key_scope_updated', 'API key app/scope updated');
            $message = 'API key app/scope updated';
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $error = 'Connected database is missing api_keys.app_name / api_keys.scope_label. Run migration-v5-hotfix.sql once.';
            } else {
                $error = 'API key app/scope update failed: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = 'API key app/scope update failed: ' . $e->getMessage();
        }
    }
}

// API কী টগল/ডিলিট
if (isset($_GET['toggle_key'])) {
    AdminHelpers::requireManage();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $key_id = (int)$_GET['toggle_key'];
    $stmt = $db->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = :id");
    $stmt->execute([':id' => $key_id]);
    AdminHelpers::audit('api_key', $key_id, 'api_key_status_updated', 'API key status updated');
    $message = 'API key status updated';
}

if (isset($_GET['delete_key'])) {
    AdminHelpers::requireDelete();
    Security::requireCSRFToken($_GET['csrf_token'] ?? '');
    $key_id = (int)$_GET['delete_key'];
    $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id");
    $stmt->execute([':id' => $key_id]);
    AdminHelpers::audit('api_key', $key_id, 'api_key_deleted', 'API key deleted');
    $message = 'API key deleted';
}

$newKeyHtml = '';
if (isset($_GET['new_key']) && isset($_SESSION['new_api_key'])) {
    $newKey = $_SESSION['new_api_key'];
    if ((string)$newKey['id'] === (string)$_GET['new_key']) {
        $newKeyName = Security::escape($newKey['name']);
        $newKeyValue = Security::escape($newKey['key']);
        $apiBase = Security::escape($settings['api_base_url'] ?? $detectedApiBaseUrl);
        $newKeyHtml = "<div class='alert alert-success' id='new-key-alert'><h5>✅ New API Key Created!</h5><p><strong>Name:</strong> {$newKeyName}</p><div class='input-group mb-3'><input type='text' class='form-control' id='api-key-display' value='{$newKeyValue}' readonly><button class='btn btn-success' type='button' onclick='copyApiKey()'><i class='bi bi-clipboard'></i> Copy</button></div><p class='text-danger mb-2'><strong>⚠️ Important:</strong> Copy this key now. It is also stored encrypted for later viewing on this page.</p><small class='text-muted'>Endpoint: {$apiBase}</small></div>";
        unset($_SESSION['new_api_key']);
    }
}

try {
    $apiKeys = $db->query("SELECT api_keys.*, COALESCE(app_name, '') AS app_name, COALESCE(scope_label, '') AS scope_label FROM api_keys ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() === '42S22') {
        $apiKeys = $db->query("SELECT api_keys.*, '' AS app_name, '' AS scope_label FROM api_keys ORDER BY created_at DESC")->fetchAll();
        if (!$error) { $error = 'Connected database is missing api_keys.app_name / api_keys.scope_label. Run migration-v5-hotfix.sql once.'; }
    } else {
        throw $e;
    }
}

function testAPIKey($apiKey, $db, $url) {
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return ['success' => false, 'error' => 'API key not available', 'details' => 'This key cannot be tested because the full key is not stored'];
    }
    $hash = hash('sha256', $apiKey);
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key_hash = :hash");
    $stmt->execute([':hash' => $hash]);
    $keyData = $stmt->fetch();
    if (!$keyData) return ['success' => false, 'error' => 'Key not found in database', 'details' => 'The key does not exist or hash mismatch'];
    if (!$keyData['is_active']) return ['success' => false, 'error' => 'Key is inactive', 'details' => 'API key is disabled'];
    try {
        $data = json_encode(['license_key' => 'TEST-0000-0000-0000', 'device_hash' => 'test_device_hash', 'app_id' => 'api_test']);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $apiKey, 'Content-Type: application/json', 'Content-Length: ' . strlen($data)]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) return ['success' => false, 'error' => 'Connection failed', 'details' => $error];
        $result = json_decode($response, true);
        if ($httpCode === 401) return ['success' => false, 'error' => 'Unauthorized', 'details' => 'API returned 401 - Invalid API key'];
        if ($httpCode === 200 || $httpCode === 400) return ['success' => true, 'status' => "HTTP $httpCode", 'message' => $httpCode === 200 ? 'API key is valid and working!' : 'API key valid (license invalid)'];
        return ['success' => false, 'error' => "HTTP $httpCode", 'details' => $result['message'] ?? 'Unknown error'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Test failed', 'details' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys</title>
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
        <div>
            <h2><i class="bi bi-key-fill"></i> API Keys</h2>
            <p>Create, view, copy, test, enable, disable, and delete API keys without changing API contracts.</p>
        </div>
        <a href="settings.php" class="btn btn-outline-light"><i class="bi bi-gear"></i> Settings</a>
    </div>
    <div id="message-container">
        <?php if ($newKeyHtml) echo $newKeyHtml; ?>
        <?php if ($message): ?><div class="alert alert-info alert-dismissible fade show"><?php echo Security::escape($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    </div>
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Generate API Key</h5></div>
                <div class="card-body">
                    <form method="POST" id="api-key-form" class="needs-validation" novalidate>
                        <input type="hidden" name="create_api_key" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                        <div class="mb-3"><label class="form-label">New API Key Name</label><input type="text" class="form-control" name="api_key_name" placeholder="e.g., Production API Key" required><div class="invalid-feedback">API key name is required.</div></div>
                        <div class="mb-3"><label class="form-label">App Name</label><input type="text" class="form-control" name="app_name" placeholder="e.g., Desktop App"><small class="text-muted">লাইসেন্স এই app/API key-এর সাথে bind করা যাবে।</small></div><div class="mb-3"><label class="form-label">Scope Label</label><input type="text" class="form-control" name="scope_label" placeholder="e.g., production, staging"></div><div class="mb-3"><label class="form-label">Expires (optional)</label><input type="date" class="form-control" name="api_key_expires"></div>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Generate API Key</button>
                    </form>
                    <hr>
                    <div class="small text-muted"><strong>Detected endpoint:</strong><br><code><?php echo Security::escape($settings['api_base_url'] ?? $detectedApiBaseUrl); ?></code></div>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div><h5 class="mb-0"><i class="bi bi-key"></i> Existing API Keys</h5><small class="text-muted"><?php echo count($apiKeys); ?> keys loaded</small></div>
                    <div class="d-flex align-items-center gap-2"><span class="text-muted small">10 rows per page</span></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="api-keys-table" data-ui-paginate="true" data-ui-page-size="10">
                            <thead class="table-dark"><tr><th>Name</th><th>App/Scope</th><th>API Key</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($apiKeys)): ?>
                                <tr><td colspan="5"><div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-key"></i></div><h6>No API keys found</h6><p class="mb-0">Create one from the form.</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($apiKeys as $key):
                                    $decryptedKey = '';
                                    $displayKey = 'Not available for old key';
                                    if (!empty($key['api_key_encrypted'])) {
                                        $decryptedKey = Security::decrypt($key['api_key_encrypted']);
                                        $displayKey = $decryptedKey ?: 'Unable to decrypt';
                                    }
                                ?>
                                <tr data-key-id="<?php echo $key['id']; ?>">
                                    <td><strong><?php echo Security::escape($key['name']); ?></strong><br><small class="text-muted">Created: <?php echo date('Y-m-d', strtotime($key['created_at'])); ?><br>Last Used: <?php echo $key['last_used_at'] ? date('Y-m-d H:i', strtotime($key['last_used_at'])) : 'Never'; ?></small></td>
                                    <td>
                                        <form method="POST" class="d-flex flex-column gap-1" data-no-spinner>
                                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                                            <input type="hidden" name="update_api_key_scope" value="1">
                                            <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                            <input type="text" class="form-control form-control-sm" name="app_name" value="<?php echo Security::escape($key['app_name'] ?? ''); ?>" placeholder="App Name">
                                            <input type="text" class="form-control form-control-sm" name="scope_label" value="<?php echo Security::escape($key['scope_label'] ?? ''); ?>" placeholder="Scope Label">
                                            <?php if (AdminHelpers::canManage()): ?><button type="submit" class="btn btn-sm btn-outline-primary">Save Scope</button><?php endif; ?>
                                        </form>
                                    </td>
                                    <td><div class="api-key-display"><code class="api-key-full"><?php echo Security::escape($displayKey); ?></code></div><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-copy="<?php echo Security::escape($decryptedKey); ?>" <?php echo $decryptedKey ? '' : 'disabled'; ?>><i class="bi bi-clipboard"></i> Copy</button><button type="button" class="btn btn-outline-info" onclick="openTestModal(<?php echo $key['id']; ?>, <?php echo htmlspecialchars(json_encode($decryptedKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)" <?php echo $decryptedKey ? '' : 'disabled'; ?>><i class="bi bi-play-circle"></i> Test</button></div></td>
                                    <td><span class="badge bg-<?php echo $key['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?></span><?php if ($key['expires_at']): ?><br><small class="text-muted">Expires: <?php echo date('Y-m-d', strtotime($key['expires_at'])); ?></small><?php endif; ?></td>
                                    <td><div class="btn-group-vertical"><a href="?toggle_key=<?php echo $key['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-sm btn-<?php echo $key['is_active'] ? 'warning' : 'success'; ?>"><?php echo $key['is_active'] ? 'Disable' : 'Enable'; ?></a><a href="?delete_key=<?php echo $key['id']; ?>&csrf_token=<?php echo urlencode(Security::generateCSRFToken()); ?>" class="btn btn-sm btn-danger" data-confirm="Are you sure you want to delete this API key?"><i class="bi bi-trash"></i> Delete</a></div></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="mt-3" aria-label="API key pagination"><ul class="pagination justify-content-end mb-0" data-ui-pager-for="api-keys-table"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">API Key Test</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="testApiForm" method="POST"><div class="modal-body"><input type="hidden" name="test_api_key" value="1"><input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>"><input type="hidden" name="key_id" id="test-key-id"><div class="mb-3"><label class="form-label">API Key to Test</label><input type="text" class="form-control" name="test_key" id="test-key-input" readonly></div><div class="mb-3"><label class="form-label">Test Endpoint</label><input type="text" class="form-control" value="<?php echo Security::escape($settings['api_base_url'] ?? $detectedApiBaseUrl); ?>" readonly></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary"><i class="bi bi-play-circle"></i> Run Test</button></div></form></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
<script>
function copyApiKey(){var input=document.getElementById('api-key-display'); if(input){input.select(); document.execCommand('copy');}}
function openTestModal(id,key){document.getElementById('test-key-id').value=id;document.getElementById('test-key-input').value=key||'';var modal=new bootstrap.Modal(document.getElementById('testModal'));modal.show();}
</script>
</body>
</html>
