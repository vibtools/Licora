<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function uc_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$required=[
 'admin/assets/css/licora/licora-ui.css',
 'admin/assets/css/licora/theme/light.css',
 'admin/assets/css/licora/base/base.css',
 'admin/assets/css/licora/layout/app-shell.css',
 'admin/assets/css/licora/components/core.css',
 'admin/assets/css/licora/components/updater.css',
 'admin/assets/css/licora/utilities/utilities.css',
 'admin/includes/ui/navigation.php','admin/includes/ui/sidebar.php','admin/includes/ui/topbar.php',
 'admin/assets/js/components/sidebar.js',
 'admin/includes/ui/integration.php','admin/about.php','admin/ajax/v2-public-key.php',
];
foreach($required as $rel){uc_ok(is_file($root.'/'.$rel),'shared UI component missing '.$rel);}
$entry=(string)file_get_contents($root.'/admin/assets/css/admin-ui.css');
uc_ok(strpos($entry,"licora/licora-ui.css")!==false,'admin-ui.css must be a shared component entrypoint');
$theme=(string)file_get_contents($root.'/admin/assets/css/licora/theme/light.css');
foreach(['--licora-primary: #2563eb','--licora-secondary: #7c3aed','--licora-sidebar-bg: #ffffff','color-scheme: light'] as $marker){uc_ok(strpos($theme,$marker)!==false,'Licora light theme marker missing '.$marker);}
$shell=(string)file_get_contents($root.'/admin/assets/css/licora/layout/app-shell.css');
foreach(['.ui-sidebar','.ui-topbar','.admin-shell','@media (max-width: 899.98px)'] as $marker){uc_ok(strpos($shell,$marker)!==false,'shared app-shell marker missing '.$marker);}
$pages=glob($root.'/admin/*.php')?:[];
foreach($pages as $path){if(basename($path)==='logout.php')continue;$text=(string)file_get_contents($path);uc_ok(stripos($text,'<style')===false,'page-level style block forbidden: '.basename($path));uc_ok(strpos($text,'cdn.tailwindcss.com')===false,'Tailwind runtime dependency forbidden in migrated admin page: '.basename($path));if(basename($path)!=='login.php'){uc_ok(strpos($text,'includes/navbar.php')!==false,'admin shell include missing: '.basename($path));}}
$install=(string)file_get_contents($root.'/install.php');uc_ok(stripos($install,'<style')===false,'installer page-level style block forbidden');
$topLevelCss=array_map('basename',glob($root.'/admin/assets/css/*.css')?:[]);sort($topLevelCss);uc_ok($topLevelCss===['admin-ui.css','licora-updater.css'],'top-level admin CSS must remain compatibility entrypoints only');
$adminJs=(string)file_get_contents($root.'/admin/assets/js/admin-ui.js');uc_ok(strpos($adminJs,"setAttribute('data-theme', 'light')")!==false,'light-theme runtime lock missing');uc_ok(strpos($adminJs,'uiThemeToggle')===false,'dark-theme toggle must not be applied in the v5.4.0 light migration');
$brandFiles=['favicon/favicon-16.png','favicon/favicon-32.png','favicon/favicon-48.png','favicon/favicon.ico','icons/icon-64.png','icons/icon-128.png','icons/icon-180.png','icons/icon-192.png','icons/icon-256.png','icons/icon-384.png','icons/icon-512.png','images/Licora-icon.png','images/Licora-logo.png','logos/logo-sm.png','logos/logo-md.png','logos/logo-lg.png'];foreach($brandFiles as $brand){uc_ok(is_file($root.'/admin/assets/brand/'.$brand),'Licora brand asset missing '.$brand);}
$sidebar=(string)file_get_contents($root.'/admin/includes/ui/sidebar.php');uc_ok(strpos($sidebar,'assets/brand/logos/logo-sm.png')!==false,'sidebar must use approved Licora brand asset');
echo "Shared UI component contract checks passed.\n";
