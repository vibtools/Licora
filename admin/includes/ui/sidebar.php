<?php
require_once __DIR__ . '/navigation.php';
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'] ?? 'index.php');
$username = (string)($auth->getUsername() ?? 'Admin');
$role = (string)($auth->getRole() ?? 'super_admin');
$initial = strtoupper(substr($username, 0, 1));
?>
<aside class="ui-sidebar" id="licoraSidebar" aria-label="Admin primary navigation">
    <a class="ui-sidebar-brand" href="index.php" aria-label="Licora Dashboard">
        <img class="ui-brand-logo" src="assets/brand/logos/logo-sm.png" alt="Licora">
    </a>
    <div class="ui-sidebar-scroll">
        <?php foreach (licora_ui_navigation_groups() as $groupLabel => $items): ?>
            <?php
            $visibleItems = array_values(array_filter($items, 'licora_ui_item_visible'));
            if (!$visibleItems) { continue; }
            ?>
            <section class="ui-nav-group" aria-label="<?php echo Security::escape($groupLabel); ?>">
                <p class="ui-nav-label"><?php echo Security::escape($groupLabel); ?></p>
                <?php foreach ($visibleItems as $item):
                    $active = licora_ui_item_active($item, $currentPage);
                    $hasChildren = !empty($item['children']); ?>
                    <a class="ui-nav-link<?php echo $active ? ' active' : ''; ?>" href="<?php echo Security::escape($item['file']); ?>"<?php echo $currentPage === $item['file'] ? ' aria-current="page"' : ''; ?>>
                        <span class="ui-nav-icon"><i class="bi <?php echo Security::escape($item['icon']); ?>"></i></span>
                        <span class="ui-nav-text"><?php echo Security::escape($item['label']); ?></span>
                        <?php if (!empty($item['update_badge'])): ?><span class="ui-nav-badge" data-licora-update-badge hidden></span><?php endif; ?>
                    </a>
                    <?php if ($hasChildren): ?>
                        <div class="ui-nav-submenu" aria-label="<?php echo Security::escape($item['label']); ?> submenu">
                            <?php foreach ($item['children'] as $child): $childActive = $currentPage === $child['file']; ?>
                                <a class="ui-nav-sublink<?php echo $childActive ? ' active' : ''; ?>" href="<?php echo Security::escape($child['file']); ?>"<?php echo $childActive ? ' aria-current="page"' : ''; ?>>
                                    <span class="ui-nav-subicon"><i class="bi <?php echo Security::escape($child['icon']); ?>"></i></span>
                                    <span><?php echo Security::escape($child['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="ui-sidebar-footer">
        <div class="ui-user-card">
            <span class="ui-user-avatar" aria-hidden="true"><?php echo Security::escape($initial !== '' ? $initial : 'A'); ?></span>
            <span class="ui-user-copy"><span class="ui-user-name"><?php echo Security::escape($username); ?></span><span class="ui-user-role"><?php echo Security::escape(str_replace('_', ' ', $role)); ?></span></span>
            <span class="ui-user-actions">
                <a class="ui-user-action" href="settings.php" title="Settings" aria-label="Settings"><i class="bi bi-gear"></i></a>
                <a class="ui-user-action" href="logout.php" title="Logout" aria-label="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </span>
        </div>
    </div>
</aside>
<div class="ui-sidebar-backdrop" id="licoraSidebarBackdrop" aria-hidden="true"></div>
