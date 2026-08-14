<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/includes/updater/UpdateException.php';
require_once $root.'/includes/updater/UpdateRuntime.php';
require_once $root.'/includes/updater/ManifestVerifier.php';
require_once $root.'/includes/updater/ArchiveValidator.php';

function vr_fail(string $message): never { fwrite(STDERR,"FAIL: {$message}\n"); exit(1); }
function vr_arg(array $argv,string $name): string {
    $index=array_search($name,$argv,true);
    if($index===false||!isset($argv[$index+1])){vr_fail('missing argument '.$name);}
    return (string)$argv[$index+1];
}

$version=vr_arg($argv,'--version');
$package=vr_arg($argv,'--package');
$manifestPath=vr_arg($argv,'--manifest');
$signaturePath=vr_arg($argv,'--signature');
if(!preg_match('/^\d+\.\d+\.\d+$/',$version)){vr_fail('invalid version');}
foreach([$package,$manifestPath,$signaturePath] as $path){if(!is_file($path)||!is_readable($path)){vr_fail('missing/read-protected artifact '.$path);}}
$manifestJson=(string)file_get_contents($manifestPath);$signature=(string)file_get_contents($signaturePath);
$decoded=json_decode($manifestJson,true);if(!is_array($decoded)){vr_fail('manifest JSON is invalid');}
if(($decoded['version']??'')!==$version){vr_fail('manifest version mismatch');}
$expectedPackage='Licora-'.$version.'.zip';
if(basename($package)!==$expectedPackage||($decoded['package']['name']??'')!==$expectedPackage){vr_fail('package filename mismatch');}
$size=filesize($package);$hash=hash_file('sha256',$package);
if($size===false||!is_string($hash)||$size!==(int)($decoded['package']['size']??-1)||!hash_equals(strtolower((string)($decoded['package']['sha256']??'')),strtolower($hash))){vr_fail('package size/hash mismatch');}

$verifier=new ManifestVerifier();$sources=$decoded['upgrade_from']??[];
if(!is_array($sources)||$sources===[]){vr_fail('upgrade_from is empty');}
foreach($sources as $source){$verifier->verify($manifestJson,$signature,$version,(string)$source);}

$job='00000000-0000-4000-8000-'.substr(hash('sha256',$version.$hash),0,12);
$validator=new ArchiveValidator();
try{
    $verified=$verifier->verify($manifestJson,$signature,$version,(string)$sources[0]);
    $result=$validator->extract($package,$version,$verified,$job,null);
    if((int)($result['files']??0)!==count($verified['files'])){vr_fail('runtime archive file count mismatch');}
} finally {
    ArchiveValidator::removeTree(UpdateRuntime::jobDir($job));
}

echo "Runtime signed-release artifact verification passed.\n";
echo "Version: {$version}\n";
echo "Sources: ".implode(', ',array_map('strval',$sources))."\n";
echo "Files: ".count($decoded['files'])."\n";
