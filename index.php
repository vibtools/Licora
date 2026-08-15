<?php
require_once __DIR__ . '/includes/config.php';
$adminUrl = 'admin/login.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licora</title>
    <link rel="icon" href="admin/assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin/assets/css/admin-ui.css">
</head>
<body class="root-landing">
    <main class="root-card">
        <img src="admin/assets/brand/logos/logo-lg.png" alt="Licora" class="ui-root-logo">
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
            <a href="<?php echo htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Admin Login</a>
            <span class="btn btn-outline-secondary btn-lg disabled"><i class="bi bi-lock"></i> Restricted Area</span>
        </div>
    </main>
</body>
</html>
