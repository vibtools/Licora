<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function uiu_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$page=(string)file_get_contents($root.'/admin/updates.php');
$css=(string)file_get_contents($root.'/admin/assets/css/licora/components/updater.css');
foreach(['includes/navbar.php','assets/css/admin-ui.css','assets/css/licora-updater.css','licora-update-log-modal','Copy Logs','Download Diagnostics','Pin to bottom'] as $m){uiu_ok(strpos($page,$m)!==false,'updater UI contract missing '.$m);}
uiu_ok(strpos($page,'vibtools/vibtools-theme.css')===false,'Update Center must inherit the centralized Licora light theme rather than load a second theme directly');
uiu_ok(strpos($page,'style="')===false,'static updater page-specific inline design styles are forbidden');
foreach(['background:#fff','var(--licora-primary)','.vib-modal-backdrop','.licora-updater-grid'] as $m){uiu_ok(strpos($css,$m)!==false,'light updater component marker missing '.$m);}
uiu_ok(strpos($page,'AI Diagnostics')===false,'demo-only AI Diagnostics must remain absent');
echo "Updater light UI integration contract checks passed.\n";
