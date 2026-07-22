<?php
// ইন্সটলেশন স্ক্রিপ্ট
if (file_exists(__DIR__ . '/config.php') || file_exists(__DIR__ . '/includes/config.local.php')) {
    die('System already installed. Please remove install.php file.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$connectionMessage = '';

function installer_dsn($host, $dbName = '') {
    $host = trim($host);
    $port = '';
    if (strpos($host, ':') !== false && substr_count($host, ':') === 1) {
        [$hostOnly, $portOnly] = explode(':', $host, 2);
        if (ctype_digit($portOnly)) {
            $host = $hostOnly;
            $port = ';port=' . $portOnly;
        }
    }
    $dsn = 'mysql:host=' . $host . $port;
    if ($dbName !== '') {
        $dsn .= ';dbname=' . $dbName;
    }
    return $dsn . ';charset=utf8mb4';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $host = trim($_POST['host'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['pass'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $error = 'Invalid database name. Use only letters, numbers, and underscore.';
        }
        if ($host === '' || $user === '') {
            $error = 'Database host and username are required.';
        }
        if (empty($error)) {
            try {
                $pdo = new PDO(installer_dsn($host), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `{$name}`");
                    $connectionMessage = 'Database connected and selected successfully.';
                } catch (PDOException $createException) {
                    $pdo = new PDO(installer_dsn($host, $name), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                    $connectionMessage = 'Database exists and was selected successfully.';
                }
                $sqlPath = __DIR__ . '/database.sql';
                if (!file_exists($sqlPath)) {
                    throw new RuntimeException('database.sql file is missing.');
                }
                $configSamplePath = __DIR__ . '/config.sample.php';
                $configTemplatePath = file_exists($configSamplePath) ? $configSamplePath : (__DIR__ . '/includes/config.php');
                if (!file_exists($configTemplatePath)) {
                    throw new RuntimeException('Configuration template is missing.');
                }
                $encryption_key = bin2hex(random_bytes(32));
                $csrf_secret = bin2hex(random_bytes(32));
                $jwt_secret = bin2hex(random_bytes(32));
                $config_content = file_get_contents($configTemplatePath);
                $config_content = str_replace([
                    "'localhost'", "'license_system'", "'root'", "''",
                    "'your-32-byte-encryption-key-here-change-this'",
                    "'your-csrf-secret-key-change-this'",
                    "'your-jwt-secret-key-change-this'"
                ], [
                    var_export($host, true), var_export($name, true), var_export($user, true), var_export($pass, true),
                    var_export($encryption_key, true), var_export($csrf_secret, true), var_export($jwt_secret, true)
                ], $config_content);
                if (file_put_contents(__DIR__ . '/includes/config.local.php', $config_content) === false) {
                    throw new RuntimeException('Could not write includes/config.local.php.');
                }
                $sql = file_get_contents($sqlPath);
                $pdo->exec($sql);
                $success = $connectionMessage . ' Installation completed successfully!';
                $step = 3;
            } catch (Throwable $e) {
                error_log('Install failed: ' . $e->getMessage());
                $error = 'Installation failed. Please verify database credentials, database permissions, and writable includes folder.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License System Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin/assets/css/admin-ui.css">
    <style>
        body { background: #f8f9fa; }
        .install-container { max-width: 720px; margin: 50px auto; }
    </style>
</head>
<body class="admin-ui">
    <div class="install-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h3><i class="bi bi-shield-lock"></i> License System Installation</h3>
                <p>Step <?php echo $step; ?> of 3</p>
            </div>
            
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                <form method="POST">
                    <h5 class="mb-3">Database Configuration</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Database Host</label>
                        <input type="text" class="form-control" name="host" value="localhost" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Database Name</label>
                        <input type="text" class="form-control" name="name" value="license_system" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Database Username</label>
                        <input type="text" class="form-control" name="user" value="root" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Database Password</label>
                        <input type="password" class="form-control" name="pass">
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will create a new database and all required tables.
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Continue Installation</button>
                    </div>
                </form>
                
                <?php elseif ($step == 2): ?>
                <!-- Additional installation steps if needed -->
                
                <?php elseif ($step == 3): ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <h3 class="mt-3">Installation Complete!</h3>
                    <p class="lead">License System has been successfully installed.</p>
                    
                    <div class="alert alert-warning mt-4">
                        <strong>Important:</strong>
                        <ul class="text-start">
                            <li>Delete the <code>install.php</code> file</li>
                            <li>Change the default admin password immediately</li>
                            <li>Set up cron jobs for automatic cleanup</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <a href="admin/login.php" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Admin Panel
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer text-center text-muted">
                <small>© <?php echo date('Y'); ?> License Management System</small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>