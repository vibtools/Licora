<?php
declare(strict_types=1);
$root = dirname(__DIR__);
function v551_ok($value, string $message): void {
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function v551_read(string $root, string $rel): string { return (string)file_get_contents($root . '/' . $rel); }

$settings = v551_read($root, 'admin/settings.php');
$core = v551_read($root, 'admin/assets/css/licora/components/core.css');
$sidebar = v551_read($root, 'admin/includes/ui/sidebar.php');
$sidebarJs = v551_read($root, 'admin/assets/js/components/sidebar.js');
$about = v551_read($root, 'admin/about.php');

foreach (['ui-shortcuts ui-shortcut-grid','ui-settings-detail-grid','ui-settings-stack','API & Integration','Cron Jobs','API v2 Signing'] as $marker) {
    v551_ok(strpos($settings, $marker) !== false, 'Settings v5.5.1 layout marker missing: ' . $marker);
}
v551_ok(substr_count($settings, 'class="btn btn-outline-secondary"') >= 7, 'Settings shortcut buttons are incomplete');
v551_ok(strpos($settings, 'ui-settings-section ui-span-2') === false, 'legacy settings span-2 composition remains');
foreach (['grid-template-columns:repeat(7,minmax(0,1fr))','grid-template-columns:repeat(4,minmax(0,1fr))','grid-template-columns:repeat(2,minmax(0,1fr))','.ui-settings-detail-grid','.ui-settings-stack'] as $marker) {
    v551_ok(strpos(str_replace(' ', '', $core), str_replace(' ', '', $marker)) !== false, 'Settings compact grid CSS missing: ' . $marker);
}

foreach (['data-ui-submenu-toggle','aria-expanded=','aria-controls=','ui-nav-submenu-toggle','licoraSubmenu'] as $marker) {
    v551_ok(strpos($sidebar, $marker) !== false, 'collapsible sidebar markup missing: ' . $marker);
}
v551_ok(strpos($sidebar, "? '' : ' hidden'") !== false, 'inactive sidebar submenu is not server-rendered collapsed');
foreach (['function setSubmenu','data-ui-submenu-toggle','submenu.hidden','aria-expanded'] as $marker) {
    v551_ok(strpos($sidebarJs, $marker) !== false, 'collapsible sidebar JS missing: ' . $marker);
}
foreach (['.ui-nav-submenu-toggle','[aria-expanded="true"]','ui-nav-submenu[hidden]'] as $marker) {
    v551_ok(strpos($sidebar . $core . v551_read($root, 'admin/assets/css/licora/layout/app-shell.css'), $marker) !== false, 'collapsible sidebar style/contract missing: ' . $marker);
}

foreach (['ui-product-hero','ui-feature-grid','ui-feature-card','ui-about-detail-grid','ui-company-panel','ui-product-meta'] as $marker) {
    v551_ok(strpos($about, $marker) !== false, 'About Licora component missing: ' . $marker);
}
foreach (['License Control','Device Control','Secure API v2','API Management','Secure Updates','Operations','Developed and maintained by Vib Tools','support@vib.tools','vibtools/Licora','MIT License'] as $marker) {
    v551_ok(strpos($about, $marker) !== false, 'About Licora verified content missing: ' . $marker);
}
v551_ok(substr_count($about, 'class="ui-feature-card"') === 6, 'About Licora must contain exactly six verified capability cards');
v551_ok(strpos($about, 'ui-about-layout') === false, 'legacy sparse About layout remains');

$keyEndpoint = v551_read($root, 'admin/ajax/v2-public-key.php');
v551_ok(strpos($keyEndpoint, 'privateKeyPem') === false && strpos($keyEndpoint, 'privatePath()') === false, 'API v2 private key exposure regression');

foreach (glob($root . '/admin/*.php') ?: [] as $path) {
    v551_ok(strpos((string)file_get_contents($path), '<style') === false, 'page-specific style block introduced: ' . basename($path));
}

echo "Licora v5.5.1 Settings/sidebar/About UI hotfix contract checks passed.\n";
