<?php
declare(strict_types=1);

final class PreflightService
{
    public function run(array $manifest,int $packageBytes=0): array
    {
        $checks=[]; $add=static function(string $id,string $label,bool $ok,string $detail='') use (&$checks){$checks[]=['id'=>$id,'label'=>$label,'ok'=>$ok,'detail'=>$detail];};
        $minPhp=(string)($manifest['minimum_php']??'8.0'); $add('php','PHP '.PHP_VERSION,version_compare(PHP_VERSION,$minPhp,'>='),'Requires '.$minPhp.'+');
        $add('openssl','OpenSSL extension',extension_loaded('openssl'),'Required for signed manifest verification');
        $add('pdo_mysql','PDO MySQL extension',extension_loaded('pdo_mysql'),'Required for Licora database access');
        $add('zip','ZipArchive',class_exists('ZipArchive'),'Required for staged release extraction');
        $transport=function_exists('curl_init')||(bool)ini_get('allow_url_fopen'); $add('https_transport','HTTPS transport',$transport,function_exists('curl_init')?'cURL':'PHP streams');
        $current=defined('APP_VERSION')?APP_VERSION:'0.0.0'; $minimumUpdater=(string)($manifest['minimum_updater']??'5.3.0'); $add('updater_version','Updater compatibility',version_compare($current,$minimumUpdater,'>='),'Installed '.$current.'; requires '.$minimumUpdater.'+');
        $versionOverride=getenv('APP_VERSION'); $overrideOk=($versionOverride===false||trim((string)$versionOverride)===''||trim((string)$versionOverride)===(string)($manifest['version']??'')); $add('version_override','APP_VERSION environment override',$overrideOk,$versionOverride===false||trim((string)$versionOverride)===''?'No pinning override detected':'Configured override: '.trim((string)$versionOverride));
        try{UpdateRuntime::ensure();$runtime=UpdateRuntime::root();$w=is_writable($runtime);$add('runtime_write','Updater runtime storage',$w,$runtime);}catch(Throwable $e){$add('runtime_write','Updater runtime storage',false,'Runtime storage is unavailable');}
        $root=UpdateRuntime::appRoot(); $unwritable=[]; foreach(array_keys($manifest['files']??[]) as $path){$full=$root.'/'.$path;if(is_file($full)){if(!is_writable($full)||!is_writable(dirname($full))){$unwritable[]=$path;}}else{$parent=dirname($full);while(!is_dir($parent)&&$parent!==$root){$next=dirname($parent);if($next===$parent){break;}$parent=$next;}if(!is_dir($parent)||!is_writable($parent)){$unwritable[]=$path;}}if(count($unwritable)>=8){break;}} foreach($manifest['delete_files']??[] as $path){$full=$root.'/'.$path;if(file_exists($full)&&(!is_writable($full)||!is_writable(dirname($full)))){$unwritable[]=$path;}}
        $add('source_write','Application source writable',$unwritable===[],$unwritable?('Blocked: '.implode(', ',$unwritable)):'Tracked update paths are writable');
        $free=@disk_free_space($root); $required=max(25*1024*1024,$packageBytes>0?$packageBytes*4:25*1024*1024); $add('disk','Disk space',$free===false||$free>$required,$free===false?'Unable to measure; filesystem write checks still apply':number_format($free/1048576,1).' MiB free');
        $pub=defined('LICORA_UPDATE_PUBLIC_KEY_PATH')?LICORA_UPDATE_PUBLIC_KEY_PATH:__DIR__.'/update-signing-public.pem'; $add('public_key','Update verification key',is_readable($pub),'Dedicated updater public key');
        $ok=!in_array(false,array_column($checks,'ok'),true); return ['ok'=>$ok,'checks'=>$checks];
    }
}
