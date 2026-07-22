<?php
if (!isset($auth)) {
    require_once __DIR__ . '/../../includes/auth.php';
    $auth = new Auth();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg admin-topnav sticky-top" aria-label="Admin navigation">
    <div class="container-fluid">
        <a class="navbar-brand admin-brand" href="index.php">
            <span class="brand-icon"><i class="bi bi-shield-lock"></i></span>
            <span>License System</span>
        </a>

        <button class="navbar-toggler admin-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse admin-navbar-collapse" id="navbarNav">
            <ul class="navbar-nav admin-main-menu me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'license.php' ? 'active' : ''; ?>" href="license.php">
                        <i class="bi bi-key"></i> <span>Licenses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'device.php' ? 'active' : ''; ?>" href="device.php">
                        <i class="bi bi-devices"></i> <span>Devices</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                        <i class="bi bi-clock-history"></i> <span>Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'api_keys.php' ? 'active' : ''; ?>" href="api_keys.php">
                        <i class="bi bi-key-fill"></i> <span>API Keys</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="bi bi-gear"></i> <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'admins.php' ? 'active' : ''; ?>" href="admins.php">
                        <i class="bi bi-people"></i> <span>Admins</span>
                    </a>
                </li>
            </ul>

            <div class="navbar-nav admin-user-menu align-items-lg-center ms-lg-3">
                <button type="button" class="theme-toggle me-lg-2 mb-2 mb-lg-0" id="uiThemeToggle">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle admin-user-link" href="#" id="adminUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span><?php echo Security::escape($auth->getUsername() ?? 'Admin'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminUserDropdown">
                        <li><span class="dropdown-item-text"><small>Logged in as <?php echo Security::escape($auth->getUsername()); ?></small></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="api_keys.php"><i class="bi bi-key-fill"></i> API Keys</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
