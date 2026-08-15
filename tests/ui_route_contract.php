<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function ur_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$pages=['index.php','license.php','device.php','logs.php','api_keys.php','client_apps.php','v2_devices.php','updates.php','settings.php','admins.php','audit.php','backup.php','health.php','about.php','login.php','logout.php'];
foreach($pages as $page){ur_ok(is_file($root.'/admin/'.$page),'admin route missing '.$page);}
$nav=(string)file_get_contents($root.'/admin/includes/ui/navigation.php');
foreach(['index.php','license.php','device.php','logs.php','api_keys.php','client_apps.php','v2_devices.php','updates.php','settings.php','admins.php','audit.php','backup.php','health.php','about.php'] as $route){ur_ok(strpos($nav,"'file' => '{$route}'")!==false,'primary sidebar route missing '.$route);}
$sidebar=(string)file_get_contents($root.'/admin/includes/ui/sidebar.php');
ur_ok(strpos($nav, "'super_admin' => true")!==false && strpos($nav,'AdminHelpers::canDelete()')!==false && strpos($sidebar,'licora_ui_item_visible')!==false,'existing Super Admin update visibility contract must be preserved');
ur_ok(strpos($sidebar,'data-licora-update-badge')!==false,'update notification badge must remain in primary navigation');
$wrapper=(string)file_get_contents($root.'/admin/includes/navbar.php');
ur_ok(strpos($wrapper,"ui/sidebar.php")!==false && strpos($wrapper,"ui/topbar.php")!==false,'legacy navbar include must render the shared sidebar/topbar shell');
ur_ok(strpos($wrapper,'admin-topnav')===false,'legacy horizontal primary navbar must not remain');
echo "UI route/navigation contract checks passed.\n";
