<?php

declare(strict_types=1);

$root = __DIR__;
require_once $root . '/includes/installation.php';

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function installer_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function installer_render_locked(): void
{
    $basePath = licora_installation_base_path();
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Installation already completed</title>';
    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f9;margin:0;padding:40px;color:#212529}.card{max-width:760px;margin:40px auto;background:#fff;border:1px solid #dee2e6;border-radius:14px;padding:32px;box-shadow:0 12px 36px rgba(0,0,0,.08)}h1{color:#b02a37}code{background:#f1f3f5;padding:.15rem .35rem;border-radius:4px}</style></head><body><main class="card">';
    echo '<h1>Installation already completed.</h1><p>Licora detected an existing installation and disabled installer execution. No configuration or database data was changed.</p>';
    echo '<p>For an intentional recovery, place the site in private maintenance mode, back up the database and private configuration, and follow <code>docs/INSTALLATION.md</code> and <code>docs/FIRST_RUN_GUIDE.md</code>.</p>';
    echo '<p><a href="' . installer_escape($basePath . '/admin/login.php') . '">Go to Admin Login</a></p></main></body></html>';
    exit;
}

$requestedStep = filter_input(INPUT_GET, 'step', FILTER_VALIDATE_INT);
$step = $requestedStep !== false && $requestedStep !== null ? max(1, min(10, (int)$requestedStep)) : 1;
$successData = $_SESSION['licora_installer_success'] ?? null;
$lockedAction = licora_installer_locked_request_action(
    licora_installer_is_locked($root),
    $step,
    is_array($successData),
    !empty($_SESSION['licora_installer_success_view_pending']),
    !empty($_SESSION['licora_installer_login_redirect_pending'])
);

if ($lockedAction === 'show_success') {
    unset($_SESSION['licora_installer_success_view_pending']);
    $_SESSION['licora_installer_login_redirect_pending'] = true;
} elseif ($lockedAction === 'redirect_login') {
    $adminUrl = (string)($successData['admin_url'] ?? '');
    unset(
        $_SESSION['licora_installer_success'],
        $_SESSION['licora_installer_success_view_pending'],
        $_SESSION['licora_installer_login_redirect_pending'],
        $_SESSION['licora_installer'],
        $_SESSION['licora_installer_csrf']
    );
    session_regenerate_id(true);
    if ($adminUrl === '' || preg_match('/[\r\n]/', $adminUrl) === 1) {
        installer_render_locked();
    }
    header('Location: ' . $adminUrl, true, 302);
    exit;
} elseif ($lockedAction === 'locked') {
    installer_render_locked();
}

if (empty($_SESSION['licora_installer_csrf'])) {
    $_SESSION['licora_installer_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['licora_installer']) || !is_array($_SESSION['licora_installer'])) {
    $_SESSION['licora_installer'] = ['max_step' => 1, 'data' => []];
}

$wizard =& $_SESSION['licora_installer'];
if (is_array($successData) && $step === 9) {
    $wizard['max_step'] = 9;
} elseif ($step > (int)($wizard['max_step'] ?? 1)) {
    $step = (int)($wizard['max_step'] ?? 1);
}

$error = '';
$notice = '';
$webBasePath = licora_installation_base_path();
$requirements = licora_installer_requirements($root);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['installer_csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['licora_installer_csrf'], $submittedToken)) {
        http_response_code(403);
        $error = 'Invalid installation request. Refresh the page and try again.';
    } else {
        try {
            if ($step === 1) {
                if (!licora_installer_requirements_pass($requirements)) {
                    $error = 'Resolve all failed required checks before continuing.';
                } else {
                    $wizard['max_step'] = max((int)$wizard['max_step'], 2);
                    header('Location: ?step=2');
                    exit;
                }
            } elseif ($step === 2) {
                $database = [
                    'host' => trim((string)($_POST['host'] ?? '')),
                    'port' => (int)($_POST['port'] ?? 3306),
                    'name' => trim((string)($_POST['name'] ?? '')),
                    'user' => trim((string)($_POST['user'] ?? '')),
                    'pass' => (string)($_POST['pass'] ?? ''),
                    'table_prefix' => trim((string)($_POST['table_prefix'] ?? '')),
                    'charset' => trim((string)($_POST['charset'] ?? 'utf8mb4')),
                    'collation' => trim((string)($_POST['collation'] ?? 'utf8mb4_unicode_ci')),
                ];
                $errors = licora_installer_validate_database($database);
                if ($errors !== []) {
                    $error = $errors[0];
                } else {
                    $result = licora_installer_test_database($database);
                    if (!$result['success']) {
                        $error = (string)$result['message'];
                    } else {
                        $wizard['data']['db'] = $database;
                        $wizard['max_step'] = max((int)$wizard['max_step'], 3);
                        header('Location: ?step=3');
                        exit;
                    }
                }
            } elseif ($step === 3) {
                $adminInput = [
                    'admin_name' => trim((string)($_POST['admin_name'] ?? '')),
                    'admin_email' => trim((string)($_POST['admin_email'] ?? '')),
                    'admin_username' => trim((string)($_POST['admin_username'] ?? '')),
                    'admin_password' => (string)($_POST['admin_password'] ?? ''),
                    'admin_password_confirm' => (string)($_POST['admin_password_confirm'] ?? ''),
                ];
                $errors = licora_installer_validate_admin($adminInput);
                if ($errors !== []) {
                    $error = $errors[0];
                } else {
                    $passwordHash = password_hash($adminInput['admin_password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    if ($passwordHash === false) {
                        throw new RuntimeException('Unable to secure the administrator password.');
                    }
                    $wizard['data']['admin'] = [
                        'name' => $adminInput['admin_name'],
                        'email' => $adminInput['admin_email'],
                        'username' => $adminInput['admin_username'],
                        'password_hash' => $passwordHash,
                    ];
                    $adminInput['admin_password'] = '';
                    $adminInput['admin_password_confirm'] = '';
                    $wizard['max_step'] = max((int)$wizard['max_step'], 4);
                    header('Location: ?step=4');
                    exit;
                }
            } elseif ($step === 4) {
                $application = [
                    'app_name' => trim((string)($_POST['app_name'] ?? 'Licora')),
                    'timezone' => trim((string)($_POST['timezone'] ?? 'Asia/Dhaka')),
                    'locale' => trim((string)($_POST['locale'] ?? 'en')),
                    'base_url' => rtrim(trim((string)($_POST['base_url'] ?? '')), '/'),
                    'mail_from_name' => trim((string)($_POST['mail_from_name'] ?? 'Licora')),
                ];
                $errors = licora_installer_validate_application($application);
                if ($errors !== []) {
                    $error = $errors[0];
                } else {
                    $wizard['data']['app'] = $application;
                    if (empty($wizard['data']['secrets'])) {
                        $wizard['data']['secrets'] = [
                            'app_key' => bin2hex(random_bytes(32)),
                            'encryption_key' => bin2hex(random_bytes(32)),
                            'csrf_secret' => bin2hex(random_bytes(32)),
                            'jwt_secret' => bin2hex(random_bytes(32)),
                        ];
                    }
                    $wizard['max_step'] = max((int)$wizard['max_step'], 5);
                    header('Location: ?step=5');
                    exit;
                }
            } elseif ($step === 5) {
                if (empty($wizard['data']['db']) || empty($wizard['data']['admin']) || empty($wizard['data']['app'])) {
                    $error = 'Required installation data is incomplete.';
                } else {
                    $wizard['max_step'] = max((int)$wizard['max_step'], 6);
                    header('Location: ?step=6');
                    exit;
                }
            } elseif ($step === 6) {
                $wizard['data']['install_demo'] = isset($_POST['install_demo']);
                $wizard['max_step'] = max((int)$wizard['max_step'], 7);
                header('Location: ?step=7');
                exit;
            } elseif ($step === 7) {
                if (!isset($_POST['confirm_lock'])) {
                    $error = 'Confirm that you understand the installer will be locked after completion.';
                } else {
                    $wizard['max_step'] = max((int)$wizard['max_step'], 8);
                    header('Location: ?step=8');
                    exit;
                }
            } elseif ($step === 8) {
                if (empty($wizard['data']['db']) || empty($wizard['data']['admin']) || empty($wizard['data']['app']) || empty($wizard['data']['secrets'])) {
                    $error = 'Installation data is incomplete. Return to the previous steps.';
                } else {
                    $result = licora_installer_finalize($root, $wizard['data']);
                    $_SESSION['licora_installer_success'] = $result;
                    $_SESSION['licora_installer_success_view_pending'] = true;
                    unset(
                        $_SESSION['licora_installer_login_redirect_pending'],
                        $_SESSION['licora_installer']
                    );
                    $_SESSION['licora_installer_csrf'] = bin2hex(random_bytes(32));
                    session_regenerate_id(true);
                    header('Location: ?step=9');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('Licora installer request failed [' . get_class($e) . '].');
            $error = $e->getMessage() !== '' ? $e->getMessage() : 'Installation request failed.';
        }
    }
}

$dbData = $wizard['data']['db'] ?? [];
$adminData = $wizard['data']['admin'] ?? [];
$appData = $wizard['data']['app'] ?? [];
$stepTitles = [
    1 => 'Welcome & Server Check',
    2 => 'Database Configuration',
    3 => 'Administrator Setup',
    4 => 'Application Configuration',
    5 => 'Database Initialization',
    6 => 'Optional Demo Data',
    7 => 'Installation Lock',
    8 => 'Finalize Installation',
    9 => 'Installation Successful',
    10 => 'Admin Login Redirect',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo installer_escape($stepTitles[$step] ?? 'Licora Installer'); ?> - Licora</title>
    <?php if ($step === 9 && is_array($successData)): ?>
    <meta http-equiv="refresh" content="8;url=?step=10">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo installer_escape($webBasePath . '/admin/assets/css/admin-ui.css'); ?>">
    <style>
        body{background:#f4f6f9}.install-container{max-width:920px;margin:32px auto;padding:0 14px}.installer-card{border:0;border-radius:16px;overflow:hidden}.installer-header{background:linear-gradient(135deg,#1f4e78,#2f6da1);color:#fff;padding:26px}.step-pill{font-size:.82rem;background:rgba(255,255,255,.16);padding:.35rem .7rem;border-radius:999px}.progress{height:7px}.requirement-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid #edf0f2}.requirement-row:last-child{border-bottom:0}.status-badge{min-width:82px;text-align:center}.review-list dt{color:#6c757d;font-weight:600}.review-list dd{word-break:break-word}.secret-note{font-size:.9rem}.form-text strong{color:#495057}
    </style>
</head>
<body class="admin-ui">
<div class="install-container">
    <div class="card shadow-sm installer-card">
        <div class="installer-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-shield-check me-2"></i>Licora First-Run Installer</h2>
                    <div class="opacity-75">Professional installation wizard for Licora v5.1.0</div>
                </div>
                <span class="step-pill">Step <?php echo $step; ?> of 10</span>
            </div>
            <div class="progress mt-4 bg-white bg-opacity-25">
                <div class="progress-bar bg-light" role="progressbar" style="width:<?php echo (int)(($step / 10) * 100); ?>%"></div>
            </div>
        </div>

        <div class="card-body p-4 p-md-5">
            <h3 class="h4 mb-4"><?php echo installer_escape($stepTitles[$step] ?? 'Installation'); ?></h3>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo installer_escape($error); ?></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="alert alert-info"><?php echo installer_escape($notice); ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <p class="text-muted">Licora checks the server before any configuration or database change is made.</p>
                <div class="border rounded-3 px-3 mb-4">
                    <?php foreach ($requirements as $requirement): ?>
                        <?php
                        $status = !empty($requirement['status']);
                        $required = !empty($requirement['required']);
                        $label = $status ? 'PASS' : ($required ? 'FAIL' : 'WARNING');
                        $class = $status ? 'success' : ($required ? 'danger' : 'warning');
                        ?>
                        <div class="requirement-row">
                            <div><strong><?php echo installer_escape($requirement['label']); ?></strong><div class="small text-muted"><?php echo installer_escape($requirement['detail']); ?></div></div>
                            <span class="badge bg-<?php echo $class; ?> status-badge"><?php echo $label; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-secondary"><strong>Product:</strong> Licora &nbsp; <strong>Version:</strong> 5.1.0 &nbsp; <strong>License:</strong> MIT &nbsp; <strong>Database:</strong> MySQL/MariaDB</div>
                <form method="post">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <button class="btn btn-primary" <?php echo licora_installer_requirements_pass($requirements) ? '' : 'disabled'; ?>>Continue</button>
                </form>

            <?php elseif ($step === 2): ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Database Host</label><input class="form-control" name="host" value="<?php echo installer_escape($dbData['host'] ?? 'localhost'); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Port</label><input class="form-control" type="number" min="1" max="65535" name="port" value="<?php echo installer_escape($dbData['port'] ?? 3306); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Database Name</label><input class="form-control" name="name" value="<?php echo installer_escape($dbData['name'] ?? 'license_system'); ?>" pattern="[A-Za-z0-9_]+" required></div>
                        <div class="col-md-6"><label class="form-label">Database Username</label><input class="form-control" name="user" value="<?php echo installer_escape($dbData['user'] ?? ''); ?>" required></div>
                        <div class="col-12"><label class="form-label">Database Password</label><input class="form-control" type="password" name="pass" autocomplete="new-password"><div class="form-text">The password is kept only in the server-side installer session and final private configuration. It is never displayed or logged.</div></div>
                        <div class="col-md-4"><label class="form-label">Table Prefix (optional)</label><input class="form-control" name="table_prefix" value=""><div class="form-text">Must remain blank to preserve the frozen schema contract.</div></div>
                        <div class="col-md-4"><label class="form-label">Charset</label><select class="form-select" name="charset"><option value="utf8mb4">utf8mb4</option></select></div>
                        <div class="col-md-4"><label class="form-label">Collation</label><select class="form-select" name="collation"><option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option><option value="utf8mb4_general_ci">utf8mb4_general_ci</option></select></div>
                    </div>
                    <div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=1">Back</a><button class="btn btn-primary">Validate & Continue</button></div>
                </form>

            <?php elseif ($step === 3): ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Administrator Name</label><input class="form-control" name="admin_name" value="<?php echo installer_escape($adminData['name'] ?? ''); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Administrator Email</label><input class="form-control" type="email" name="admin_email" value="<?php echo installer_escape($adminData['email'] ?? ''); ?>" required></div>
                        <div class="col-12"><label class="form-label">Administrator Username</label><input class="form-control" name="admin_username" value="<?php echo installer_escape($adminData['username'] ?? ''); ?>" pattern="[A-Za-z0-9_.-]{3,50}" required></div>
                        <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="admin_password" autocomplete="new-password" required></div>
                        <div class="col-md-6"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="admin_password_confirm" autocomplete="new-password" required></div>
                    </div>
                    <div class="form-text mt-2"><strong>Strong password:</strong> at least 12 characters with uppercase, lowercase, number, and symbol. No default credentials are used.</div>
                    <div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=2">Back</a><button class="btn btn-primary">Continue</button></div>
                </form>

            <?php elseif ($step === 4): ?>
                <form method="post">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Application Name</label><input class="form-control" name="app_name" value="<?php echo installer_escape($appData['app_name'] ?? 'Licora'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Mail From Name</label><input class="form-control" name="mail_from_name" value="<?php echo installer_escape($appData['mail_from_name'] ?? 'Licora'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?php echo installer_escape($appData['timezone'] ?? 'Asia/Dhaka'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Locale</label><input class="form-control" name="locale" value="<?php echo installer_escape($appData['locale'] ?? 'en'); ?>" required></div>
                        <div class="col-12"><label class="form-label">Base URL</label><input class="form-control" type="url" name="base_url" value="<?php echo installer_escape($appData['base_url'] ?? licora_installer_detect_base_url()); ?>" required></div>
                    </div>
                    <div class="alert alert-info mt-4 secret-note"><i class="bi bi-key me-2"></i>Application, encryption, CSRF, and JWT secrets are generated securely. Secret values are never displayed.</div>
                    <div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=3">Back</a><button class="btn btn-primary">Generate Configuration</button></div>
                </form>

            <?php elseif ($step === 5): ?>
                <p>Licora will initialize the unchanged, existing database schema from <code>database.sql</code>, including its existing indexes, constraints, triggers, and additive migrations.</p>
                <div class="alert alert-warning">The target database must not already contain Licora tables. Unrelated existing tables are never removed. If installation fails, only installer-created objects are cleaned up.</div>
                <dl class="row review-list">
                    <dt class="col-sm-4">Database</dt><dd class="col-sm-8"><?php echo installer_escape(($dbData['host'] ?? '') . ':' . ($dbData['port'] ?? '') . '/' . ($dbData['name'] ?? '')); ?></dd>
                    <dt class="col-sm-4">Schema changes</dt><dd class="col-sm-8">None beyond the existing repository schema</dd>
                    <dt class="col-sm-4">Business logic</dt><dd class="col-sm-8">Unchanged</dd>
                </dl>
                <form method="post"><input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>"><div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=4">Back</a><button class="btn btn-primary">Continue</button></div></form>

            <?php elseif ($step === 6): ?>
                <form method="post">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <div class="form-check border rounded-3 p-4 ps-5">
                        <input class="form-check-input" type="checkbox" id="install_demo" name="install_demo" <?php echo !empty($wizard['data']['install_demo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="install_demo"><strong>Install Demo Data</strong><br><span class="text-muted">Creates one clearly marked DEMO API credential, DEMO PRODUCT representation, DEMO license, and DEMO CUSTOMER marker using existing tables only.</span></label>
                    </div>
                    <p class="small text-muted mt-3">Unchecked = production installation. Checked = demonstration installation. Raw generated tokens are never shown on this screen. Demo records can be removed later with <code>php scripts/remove-demo-data.php</code>.</p>
                    <div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=5">Back</a><button class="btn btn-primary">Continue</button></div>
                </form>

            <?php elseif ($step === 7): ?>
                <form method="post">
                    <input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>">
                    <div class="alert alert-danger"><strong>Installation Lock:</strong> after successful installation, Licora writes <code>includes/.licora-installed</code>, activates <code>includes/config.local.php</code>, and disables installer execution. Installer files are not deleted.</div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="confirm_lock" name="confirm_lock" required><label class="form-check-label" for="confirm_lock">I understand that future recovery requires private maintenance mode and documented manual recovery steps.</label></div>
                    <div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=6">Back</a><button class="btn btn-primary">Continue</button></div>
                </form>

            <?php elseif ($step === 8): ?>
                <div class="alert alert-primary"><strong>Ready to install.</strong> Licora has validated the server, database credentials, administrator data, application configuration, and lock requirements.</div>
                <ul>
                    <li>Database password and generated secrets will not be displayed.</li>
                    <li>The existing schema and application business logic remain unchanged.</li>
                    <li>Temporary development administrator credentials will be replaced immediately.</li>
                    <li>Installer execution will be disabled after completion.</li>
                </ul>
                <form method="post"><input type="hidden" name="installer_csrf_token" value="<?php echo installer_escape($_SESSION['licora_installer_csrf']); ?>"><div class="d-flex justify-content-between mt-4"><a class="btn btn-outline-secondary" href="?step=7">Back</a><button class="btn btn-success btn-lg"><i class="bi bi-check-circle me-2"></i>Complete Installation</button></div></form>

            <?php elseif ($step === 9 && is_array($successData)): ?>
                <div class="text-center py-3"><i class="bi bi-check-circle-fill text-success display-2"></i><h2 class="mt-3">Installation Successful</h2><p class="lead">Licora is installed and the first-run installer is locked.</p></div>
                <dl class="row review-list border rounded-3 p-3">
                    <dt class="col-sm-4">Installed Version</dt><dd class="col-sm-8"><?php echo installer_escape($successData['version']); ?></dd>
                    <dt class="col-sm-4">Administrator Username</dt><dd class="col-sm-8"><?php echo installer_escape($successData['username']); ?></dd>
                    <dt class="col-sm-4">Application URL</dt><dd class="col-sm-8"><?php echo installer_escape($successData['application_url']); ?></dd>
                    <dt class="col-sm-4">Admin URL</dt><dd class="col-sm-8"><?php echo installer_escape($successData['admin_url']); ?></dd>
                    <dt class="col-sm-4">API URL</dt><dd class="col-sm-8"><?php echo installer_escape($successData['api_url']); ?></dd>
                    <dt class="col-sm-4">Demo Data</dt><dd class="col-sm-8"><?php echo !empty($successData['demo_installed']) ? 'Installed and marked DEMO' : 'Not installed'; ?></dd>
                </dl>
                <div class="alert alert-warning"><strong>Security recommendations:</strong> enable HTTPS, protect private configuration, schedule cron securely, test backups, and verify API authentication before public exposure.</div>
                <div class="d-flex flex-wrap gap-2 justify-content-center"><a class="btn btn-success" href="?step=10">Go to Login</a><a class="btn btn-outline-primary" href="<?php echo installer_escape($webBasePath . '/docs/FIRST_RUN_GUIDE.md'); ?>">View Documentation</a></div>
                <p class="text-center text-muted small mt-3">Redirecting to the admin login in 8 seconds. Licora will not auto-login.</p>
            <?php else: ?>
                <div class="alert alert-danger">Installer state is unavailable. Start the installation again.</div>
                <a class="btn btn-primary" href="?step=1">Restart</a>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center text-muted py-3"><small>Licora v5.1.0 &middot; Vib Tools &middot; MIT License</small></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
