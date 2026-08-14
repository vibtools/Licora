<?php
require_once __DIR__ . '/navigation.php';
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'] ?? 'index.php');
$pageTitle = licora_ui_page_title($currentPage);
?>
<header class="ui-topbar" aria-label="Admin utility bar">
    <div class="ui-topbar-inner">
        <div class="ui-topbar-left">
            <button class="ui-sidebar-toggle" id="licoraSidebarToggle" type="button" aria-controls="licoraSidebar" aria-expanded="false" aria-label="Open navigation">
                <i class="bi bi-list"></i>
            </button>
            <span class="ui-topbar-title"><?php echo Security::escape($pageTitle); ?></span>
            <span class="ui-topbar-context">Licora administration</span>
        </div>
        <div class="ui-topbar-right">
            <span class="ui-topbar-version">v<?php echo Security::escape(defined('APP_VERSION') ? APP_VERSION : ''); ?></span>
        </div>
    </div>
</header>
