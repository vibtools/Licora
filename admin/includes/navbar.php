<?php
/* v5.4.0 compatibility entrypoint: legacy include name retained, rendered shell is sidebar + utility topbar. */
if (!isset($auth)) {
    require_once __DIR__ . '/../../includes/auth.php';
    $auth = new Auth();
}
if (!class_exists('AdminHelpers')) {
    require_once __DIR__ . '/../../includes/admin_helpers.php';
}
if (!class_exists('Security')) {
    require_once __DIR__ . '/../../includes/security.php';
}
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
require __DIR__ . '/ui/sidebar.php';
require __DIR__ . '/ui/topbar.php';
?>
<?php if (AdminHelpers::hasTemporaryAdminCredentials()): ?>
<div class="ui-shell-notice">
    <div class="alert alert-danger mb-0" role="alert">
        <strong><i class="bi bi-exclamation-octagon-fill"></i> Critical security warning:</strong>
        Temporary administrator credentials are still active for the <code>admin</code> account.
        A Super Admin must change that account password before internet exposure.
        Licora has not disabled, deleted, or changed the account automatically.
    </div>
</div>
<?php endif; ?>
<script src="assets/js/components/sidebar.js" defer></script>
<?php if (AdminHelpers::canDelete() && $currentPage !== 'updates.php'): ?>
<script src="assets/js/update-notifier.js" defer></script>
<?php endif; ?>
