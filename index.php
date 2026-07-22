<?php
$adminUrl = 'admin/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin/assets/css/admin-ui.css">
</head>
<body class="root-landing">
    <main class="root-card">
        <div class="root-icon"><i class="bi bi-shield-lock"></i></div>
        <h1 class="fw-bold mb-3">License System</h1>
        <p class="lead text-muted mb-4">This root page is not intended for public access. Use the admin panel or licensed application endpoints as configured.</p>
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
            <a href="<?php echo htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Admin Login</a>
            <span class="btn btn-outline-secondary btn-lg disabled"><i class="bi bi-lock"></i> Restricted Area</span>
        </div>
        <hr class="my-4">
        <small class="text-muted">If you are an end user, open the original application that uses this license server.</small>
    </main>
</body>
</html>
