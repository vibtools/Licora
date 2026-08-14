<?php
declare(strict_types=1);
if(!class_exists('ZipArchive')){echo "Updater archive failure/recovery test skipped (ZipArchive unavailable).\n";exit(0);}
$root=dirname(__DIR__);require_once $root.'/includes/updater/UpdateException.php';require_once $root.'/includes/updater/UpdateRuntime.php';require_once $root.'/includes/updater/ManifestVerifier.php';require_once $root.'/includes/updater/ArchiveValidator.php';
function uf_ok($v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
function uf_reject(string $zipPath,array $manifest,string $uuid,string $message):void{$failed=false;try{(new ArchiveValidator())->extract($zipPath,'5.3.1',$manifest,$uuid);}catch(UpdateException $e){$failed=true;}uf_ok($failed,$message);ArchiveValidator::removeTree(UpdateRuntime::jobDir($uuid));@unlink($zipPath);}
$uuid='00000000-0000-4000-8000-000000000001';$content="fixture\n";$hash=hash('sha256',$content);
$manifest=['files'=>['README.md'=>$hash]];$zipPath=tempnam(sys_get_temp_dir(),'licora-good-').'.zip';$z=new ZipArchive();$z->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('Licora-5.3.1/README.md',$content);$z->close();$result=(new ArchiveValidator())->extract($zipPath,'5.3.1',$manifest,$uuid);uf_ok(is_file($result['staging_root'].'/README.md'),'valid archive staged');ArchiveValidator::removeTree(UpdateRuntime::jobDir($uuid));@unlink($zipPath);

$badPath=tempnam(sys_get_temp_dir(),'licora-bad-').'.zip';$z=new ZipArchive();$z->open($badPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('Licora-5.3.1/../evil.php','bad');$z->close();uf_reject($badPath,['files'=>['evil.php'=>hash('sha256','bad')]],$uuid,'file path traversal archive rejected');

$badDir=tempnam(sys_get_temp_dir(),'licora-dir-').'.zip';$z=new ZipArchive();$z->open($badDir,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addEmptyDir('Licora-5.3.1/../escape');$z->addFromString('Licora-5.3.1/README.md',$content);$z->close();uf_reject($badDir,$manifest,$uuid,'directory path traversal archive rejected');

$caseZip=tempnam(sys_get_temp_dir(),'licora-case-').'.zip';$z=new ZipArchive();$z->open($caseZip,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('Licora-5.3.1/README.md',$content);$z->addFromString('Licora-5.3.1/readme.md',$content);$z->close();uf_reject($caseZip,['files'=>['README.md'=>$hash,'readme.md'=>$hash]],$uuid,'case-colliding archive paths rejected');

if(method_exists('ZipArchive','setExternalAttributesName')){
    $linkZip=tempnam(sys_get_temp_dir(),'licora-link-').'.zip';$z=new ZipArchive();$z->open($linkZip,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('Licora-5.3.1/link-entry','README.md');$z->setExternalAttributesName('Licora-5.3.1/link-entry',ZipArchive::OPSYS_UNIX,(0120777 << 16));$z->close();uf_reject($linkZip,['files'=>['link-entry'=>hash('sha256','README.md')]],$uuid,'symlink archive entry rejected');
}
echo "Updater archive failure/recovery checks passed.\n";
