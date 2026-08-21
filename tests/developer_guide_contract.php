<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function dg_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$page=(string)file_get_contents($root.'/admin/developer_guide.php');
$nav=(string)file_get_contents($root.'/admin/includes/ui/navigation.php');
$js=(string)file_get_contents($root.'/admin/assets/js/developer-guide.js');
$css=(string)file_get_contents($root.'/admin/assets/css/admin-ui.css');
dg_ok(strpos($nav,"'file' => 'developer_guide.php'")!==false && strpos($nav,"'label' => 'Developer Guide'")!==false,'Developer Guide sidebar route missing');
foreach(['Secure API v2','Device Proof Contract','Language Examples','Production Security Checklist','Stable Error Codes','Legacy / Trusted Server API v1'] as $marker){dg_ok(strpos($page,$marker)!==false,'Developer Guide page marker missing '.$marker);}
foreach(['activate:&lt;app_id&gt;','refresh:&lt;sha256(refresh_token)&gt;','X-Licora-Timestamp','X-Licora-Nonce','X-Licora-Device-Signature'] as $marker){dg_ok(strpos($page,$marker)!==false,'proof contract marker missing '.$marker);}
dg_ok(strpos($page,'ajax/v2-public-key.php')!==false,'trusted server public-key download link missing');
dg_ok(strpos($page,'includes/navbar.php')!==false,'shared admin shell missing');
dg_ok(stripos($page,'<style')===false,'page-level style block forbidden');
dg_ok(strpos($js,'data-devguide-tab')!==false && strpos($js,'navigator.clipboard')!==false,'Developer Guide tab/copy controller missing');
dg_ok(strpos($css,'.developer-guide-page')!==false,'scoped Developer Guide CSS missing');
$files=[
 'python/licora_v2_client.py','powershell/licora-v2-test.ps1','c/licora_v2_client.c','cpp/licora_v2_client.cpp','csharp/LicoraV2Client.cs',
 'java/LicoraV2Client.java','flutter/licora_v2_client.dart','react-native/licoraV2Client.js','php/licora_v2_client.php','node/licora-v2-client.mjs'
];
foreach($files as $rel){$full=$root.'/admin/assets/examples/licora-v2/'.$rel;dg_ok(is_file($full),'downloadable example missing '.$rel);$text=(string)file_get_contents($full);foreach(['api/v2/','activate','refresh','status','deactivate','X-Licora-Timestamp','X-Licora-Nonce','X-Licora-Device-Signature'] as $marker){dg_ok(stripos($text,$marker)!==false,'example '.$rel.' missing '.$marker);}dg_ok(stripos($text,'X-API-Key')===false,'public API v2 example must not contain X-API-Key: '.$rel);dg_ok(stripos($text,'LICENSE_API_KEY')===false,'public API v2 example must not contain shared API-key config: '.$rel);dg_ok(stripos($text,'CURLOPT_SSL_VERIFYPEER, 0')===false,'example must not disable TLS verification: '.$rel);}
$ps=(string)file_get_contents($root.'/admin/assets/examples/licora-v2/powershell/licora-v2-test.ps1');
foreach(['Convert-P1363ToDer','Get-PublicKeyPem','Get-RandomBytes','Invoke-LicoraPost','refresh:'] as $marker){dg_ok(strpos($ps,$marker)!==false,'PowerShell test marker missing '.$marker);}
dg_ok(strpos($ps,'RandomNumberGenerator]::GetBytes(18)')===false,'PowerShell test must support Windows PowerShell without the newer static RNG overload');
echo "Developer Guide contract checks passed.\n";
