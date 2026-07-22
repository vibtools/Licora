<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

$auth = new Auth();

if ($auth->isAdminLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCSRFToken($_POST['csrf_token'] ?? '');
    $username = Security::sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $result = $auth->adminLogin($username, $password);
    
    if ($result['success']) {
        header("Location: index.php");
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - License System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
    <style>
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body class="admin-ui login-page">
    <div class="container px-3">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <div class="card login-card">
                    <div class="card-header login-header text-white text-center py-4">
                        <div class="empty-icon mx-auto mb-3" style="background:rgba(255,255,255,.12);color:#fff"><i class="bi bi-shield-lock"></i></div><h3 class="fw-bold mb-1">License System</h3>
                        <p class="mb-0">Admin Panel</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo Security::escape($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape(Security::generateCSRFToken()); ?>">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" name="username" required autofocus><div class="invalid-feedback">Username is required.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" name="password" required><div class="invalid-feedback">Password is required.</div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right"></i> Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> Secure Admin Access
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-white">
                    <small>© <?php echo date('Y'); ?> License Management System</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-ui.js"></script>
</body>
</html>