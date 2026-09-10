<?php
declare(strict_types=1);

require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once 'includes/ui/integration.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit(); }

$sdkRoot = realpath(__DIR__ . '/../SDK/admin-license-api');
$documents = [
    'README.md' => 'SDK overview and quick start',
    'AI_INSTRUCTIONS.md' => 'AI integration instructions',
    'docs/AUTHENTICATION_AND_SIGNING.md' => 'Authentication and request signing',
    'docs/API_REFERENCE.md' => 'Complete endpoint reference',
    'docs/SECURITY_AND_OPERATIONS.md' => 'Security and production operations',
    'php/README.md' => 'PHP integration guide',
    'nodejs/README.md' => 'Node.js integration guide',
];

$loadedDocuments = [];
if (is_string($sdkRoot)) {
    foreach ($documents as $relativePath => $title) {
        $fullPath = $sdkRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($fullPath)) {
            $content = file_get_contents($fullPath);
            if (is_string($content)) {
                $loadedDocuments[] = ['path' => $relativePath, 'title' => $title, 'content' => $content];
            }
        }
    }
}
$endpoints = licora_ui_endpoints();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin License API Documents · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui developer-guide-page">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero developer-guide-hero">
        <div>
            <h2><i class="bi bi-book"></i> Admin License API Documents</h2>
            <p>Complete server-to-server integration rules and ready PHP/Node.js client instructions.</p>
        </div>
        <div class="developer-guide-hero-actions">
            <a href="admin_api_keys.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Admin License API</a>
            <a href="admin_api_sdk_download.php" class="btn btn-success"><i class="bi bi-file-earmark-zip"></i> Download Ready SDK</a>
        </div>
    </div>

    <?php if ($loadedDocuments === []): ?>
        <div class="alert alert-danger">Admin License API documentation is unavailable in this installation.</div>
    <?php else: ?>
        <div class="developer-guide-banner">
            <div class="developer-guide-banner-icon"><i class="bi bi-shield-check"></i></div>
            <div><div class="developer-guide-eyebrow">Server-side integration only</div><h3>Authenticated Admin API documentation</h3><p>Secrets must remain in a server-side secret manager. Never embed an Admin API key in browser, desktop, or mobile code.</p></div>
            <span class="ui-status ui-status-success">Ready</span>
        </div>
        <?php foreach ($loadedDocuments as $index => $document):
            $anchor = 'document-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($document['path'])); ?>
            <details class="developer-guide-source mb-3" id="<?php echo Security::escape($anchor); ?>" <?php echo $index === 0 ? 'open' : ''; ?>>
                <summary><span><i class="bi bi-filetype-md"></i> <?php echo Security::escape($document['title']); ?></span><span><?php echo Security::escape($document['path']); ?></span></summary>
                <pre><code><?php echo Security::escape($document['content']); ?></code></pre>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
