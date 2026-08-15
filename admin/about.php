<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/config.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero"><h2><i class="bi bi-info-circle"></i> About Licora</h2></div>
    <section class="card ui-about-card">
        <div class="card-body ui-about-layout">
            <div class="ui-about-brand">
                <img src="assets/brand/logos/logo-lg.png" alt="Licora" class="ui-about-logo">
                <h3>Licora</h3>
                <span class="ui-status ui-status-primary">v<?php echo Security::escape(APP_VERSION); ?></span>
            </div>
            <dl class="ui-info-list mb-0">
                <div><dt>Product</dt><dd>Licora — Open-Source Central License Management System</dd></div>
                <div><dt>Maintainer</dt><dd>Vib Tools</dd></div>
                <div><dt>Official Website</dt><dd><a href="https://vib.tools/" target="_blank" rel="noopener noreferrer">https://vib.tools/</a></dd></div>
                <div><dt>GitHub</dt><dd><a href="https://github.com/vibtools/Licora" target="_blank" rel="noopener noreferrer">vibtools/Licora</a></dd></div>
                <div><dt>Support</dt><dd><a href="mailto:support@vib.tools">support@vib.tools</a></dd></div>
                <div><dt>License</dt><dd>MIT License</dd></div>
            </dl>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
