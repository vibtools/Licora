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

    <section class="ui-product-hero" aria-labelledby="about-product-title">
        <div class="ui-product-hero-brand">
            <img src="assets/brand/logos/logo-lg.png" alt="Licora" class="ui-product-hero-logo">
        </div>
        <div class="ui-product-hero-content">
            <div class="ui-product-hero-heading">
                <div>
                    <h3 id="about-product-title">Licora</h3>
                    <p>Open-source, self-hosted central license management for PHP and MySQL/MariaDB deployments.</p>
                </div>
                <div class="ui-product-badges" aria-label="Release information">
                    <span class="ui-status ui-status-primary">v<?php echo Security::escape(APP_VERSION); ?></span>
                    <span class="ui-status ui-status-success">Stable</span>
                    <span class="ui-status">MIT</span>
                </div>
            </div>
            <div class="ui-product-actions">
                <a class="btn btn-primary" href="https://vib.tools/" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe2"></i> Official Website</a>
                <a class="btn btn-outline-secondary" href="https://github.com/vibtools/Licora" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i> GitHub</a>
                <a class="btn btn-outline-secondary" href="mailto:support@vib.tools"><i class="bi bi-envelope"></i> Support</a>
            </div>
        </div>
    </section>

    <section class="ui-about-section" aria-labelledby="about-capabilities-title">
        <div class="ui-section-heading"><h3 id="about-capabilities-title">Core Capabilities</h3></div>
        <div class="ui-feature-grid">
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-key"></i></span>
                <div><h4>License Control</h4><p>Create licenses with expiration periods, device limits, notes, application scopes and optional API-key binding.</p></div>
            </article>
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-laptop"></i></span>
                <div><h4>Device Control</h4><p>Track registered devices, activity, revocation, blacklist state and license-bound device limits.</p></div>
            </article>
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-shield-check"></i></span>
                <div><h4>Secure API v2</h4><p>Use public App IDs, device-bound P-256 credentials and short-lived signed access tokens without embedding a shared API v1 key.</p></div>
            </article>
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-key-fill"></i></span>
                <div><h4>API Management</h4><p>Manage API keys, application identities, activation state, expiry and request controls from the administration panel.</p></div>
            </article>
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-cloud-arrow-down"></i></span>
                <div><h4>Secure Updates</h4><p>Install signed GitHub releases through preflight, staged validation, resumable deployment, live logs and rollback protection.</p></div>
            </article>
            <article class="ui-feature-card">
                <span class="ui-feature-icon"><i class="bi bi-activity"></i></span>
                <div><h4>Operations</h4><p>Review audit trails and logs, generate SQL backups and exports, run health checks and schedule CLI maintenance jobs.</p></div>
            </article>
        </div>
    </section>

    <div class="ui-about-detail-grid">
        <section class="ui-about-section" aria-labelledby="about-company-title">
            <div class="ui-section-heading"><h3 id="about-company-title">Vib Tools</h3></div>
            <div class="ui-company-panel">
                <div class="ui-company-mark"><img src="assets/brand/images/Licora-icon.png" alt="" aria-hidden="true"></div>
                <div class="ui-company-copy">
                    <h4>Developed and maintained by Vib Tools</h4>
                    <p>Vib Tools provides secure online tools, license delivery, web services, marketing support, business consultation and professional digital services for businesses and individuals.</p>
                </div>
            </div>
        </section>

        <section class="ui-about-section" aria-labelledby="about-project-title">
            <div class="ui-section-heading"><h3 id="about-project-title">Project Information</h3></div>
            <dl class="ui-product-meta">
                <div><dt>Product</dt><dd>Licora</dd></div>
                <div><dt>Version</dt><dd><?php echo Security::escape(APP_VERSION); ?></dd></div>
                <div><dt>Release Channel</dt><dd>Stable</dd></div>
                <div><dt>Maintainer</dt><dd>Vib Tools</dd></div>
                <div><dt>Repository</dt><dd><a href="https://github.com/vibtools/Licora" target="_blank" rel="noopener noreferrer">vibtools/Licora</a></dd></div>
                <div><dt>Website</dt><dd><a href="https://vib.tools/" target="_blank" rel="noopener noreferrer">vib.tools</a></dd></div>
                <div><dt>Support</dt><dd><a href="mailto:support@vib.tools">support@vib.tools</a></dd></div>
                <div><dt>License</dt><dd>MIT License</dd></div>
            </dl>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
