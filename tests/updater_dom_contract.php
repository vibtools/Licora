<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function ud_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$page=(string)file_get_contents($root.'/admin/updates.php');
$js=(string)file_get_contents($root.'/admin/assets/js/licora-updater.js');
preg_match_all('/\bid="([A-Za-z0-9_-]+)"/',$page,$htmlMatches);
$htmlIds=array_fill_keys($htmlMatches[1]??[],true);
preg_match_all('/\bel\([\'\"]([A-Za-z0-9_-]+)[\'\"]\)/',$js,$jsMatches);
$jsIds=array_values(array_unique($jsMatches[1]??[]));
$missing=[];foreach($jsIds as $id){if(!isset($htmlIds[$id])){$missing[]=$id;}}
ud_ok($missing===[],'JavaScript references missing updater DOM IDs: '.implode(', ',$missing));
ud_ok(strpos($js,"el('update-log-title')")===false,'legacy broken update-log-title selector must not remain');
ud_ok(strpos($js,"el('licora-update-log-title')")!==false,'live log title must use the HTML licora-update-log-title contract');
ud_ok(strpos($js,'confirm(')===false,'browser-native confirm() must not bypass the Licora Light component UI');
foreach(['licora-update-confirm-modal','update-confirm-message','update-confirm-proceed','update-confirm-cancel'] as $id){ud_ok(isset($htmlIds[$id]),'confirmation component missing '.$id);}
ud_ok(strpos($page,'data-log-close')!==false,'live log close hook missing');
ud_ok(strpos($page,'data-confirm-close')!==false,'confirmation close hook missing');
ud_ok(strpos($js,'jobIsActive')!==false,'active-vs-terminal updater job state predicate missing');
ud_ok(strpos($js,'validateDom')!==false,'runtime updater DOM validation missing');
echo "Updater DOM contract checks passed.\n";
