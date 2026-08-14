<?php
declare(strict_types=1);

final class FileInstaller
{
    private UpdateRepository $repo;
    public function __construct(UpdateRepository $repo){$this->repo=$repo;}

    public function step(array $job,array $manifest,string $stagingRoot,UpdateLogger $logger,int $batch=24): array
    {
        $uuid=(string)$job['job_uuid'];$context=$this->repo->context($job);$root=UpdateRuntime::appRoot();if(!isset($context['apply_paths'])){$paths=[];foreach($manifest['files'] as $path=>$hash){$full=$root.'/'.$path;if(is_file($full)&&hash_equals(strtolower((string)$hash),strtolower((string)hash_file('sha256',$full)))){continue;}$paths[]=$path;}usort($paths,[self::class,'compareApplyPath']);$context['apply_paths']=$paths;$context['apply_cursor']=0;$context['delete_cursor']=0;$context['apply_complete']=false;$job=$this->repo->saveContext($uuid,$context);$logger->info('apply_files','FILE_APPLY_START','Applying verified release files.',['files'=>count($paths),'deletions'=>count($manifest['delete_files']??[])]);} $context=$this->repo->context($job);$paths=$context['apply_paths']??[];$cursor=(int)($context['apply_cursor']??0);$end=min(count($paths),$cursor+max(1,$batch));if($cursor<count($paths)){
            if(self::isControlPath((string)$paths[$cursor])){$end=count($paths);} for($i=$cursor;$i<$end;$i++){$path=(string)$paths[$i];$source=$stagingRoot.'/'.$path;$target=$root.'/'.$path;$expected=(string)$manifest['files'][$path];$this->atomicInstall($source,$target,$expected,$uuid);$logger->debug('apply_files','FILE_APPLIED','Applied '.$path.'.');}$context['apply_cursor']=$end;return $this->repo->saveContext($uuid,$context);
        }
        $deletes=array_values($manifest['delete_files']??[]);$dc=(int)($context['delete_cursor']??0);$de=min(count($deletes),$dc+max(1,$batch));for($i=$dc;$i<$de;$i++){$path=(string)$deletes[$i];$target=$root.'/'.$path;if(is_file($target)||is_link($target)){if(!@unlink($target)){throw new UpdateException('FILE_DELETE_FAILED','Update could not delete obsolete file: '.$path,500);}$logger->debug('apply_files','FILE_DELETED','Deleted obsolete file '.$path.'.');}}$context['delete_cursor']=$de;if($de>=count($deletes)){$context['apply_complete']=true;$logger->success('apply_files','FILE_APPLY_COMPLETE','All release file changes were applied.',['files'=>count($paths),'deletions'=>count($deletes)]);}return $this->repo->saveContext($uuid,$context);
    }

    public function verifyInstalled(array $manifest,string $targetVersion): array
    {
        $root=UpdateRuntime::appRoot();$bad=[];foreach($manifest['files'] as $path=>$expected){$full=$root.'/'.$path;if(!is_file($full)||!hash_equals(strtolower((string)$expected),strtolower((string)hash_file('sha256',$full)))){$bad[]=$path;if(count($bad)>=10){break;}}}foreach($manifest['delete_files']??[] as $path){if(file_exists($root.'/'.$path)){$bad[]=$path;}}if($bad){throw new UpdateException('POST_VERIFY_FAILED','Installed source does not match the signed release manifest: '.implode(', ',$bad),500);} if(!defined('APP_VERSION')||APP_VERSION!==$targetVersion){throw new UpdateException('POST_VERIFY_VERSION','Runtime version did not switch to '.$targetVersion.'. Reload/resume will retry verification.',500);} return ['ok'=>true,'files'=>count($manifest['files'])];
    }

    private function atomicInstall(string $source,string $target,string $expected,string $uuid): void
    {
        if(!is_file($source)||!hash_equals(strtolower($expected),strtolower((string)hash_file('sha256',$source)))){throw new UpdateException('STAGED_FILE_INVALID','Staged source file is missing or corrupt.',500);} $dir=dirname($target);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir)){throw new UpdateException('SOURCE_DIRECTORY_FAILED','Application directory could not be created.',500);} $tmp=$target.'.licora-update-'.substr(str_replace('-','',$uuid),0,12).'.tmp';@unlink($tmp);if(!@copy($source,$tmp)){throw new UpdateException('FILE_APPLY_FAILED','Update could not write temporary source file: '.basename($target),500);}$perm=is_file($target)?@fileperms($target):@fileperms($source);$mode=$perm===false?0644:($perm&0777);@chmod($tmp,$mode>0?$mode:0644);if(!@rename($tmp,$target)){@unlink($tmp);throw new UpdateException('FILE_APPLY_FAILED','Update could not atomically replace source file: '.basename($target),500);}if(!hash_equals(strtolower($expected),strtolower((string)hash_file('sha256',$target)))){throw new UpdateException('FILE_APPLY_HASH_FAILED','Installed file checksum did not match: '.basename($target),500);}
    }

    public static function isControlPath(string $path): bool
    {
        return $path==='includes/config.php'||str_starts_with($path,'includes/updater/')||$path==='admin/updates.php'||str_starts_with($path,'admin/ajax/update-')||str_starts_with($path,'admin/assets/js/licora-updater')||str_starts_with($path,'admin/assets/css/licora-updater');
    }
    public static function compareApplyPath(string $a,string $b): int { $wa=self::weight($a);$wb=self::weight($b);return $wa===$wb?strcmp($a,$b):($wa<=>$wb); }
    private static function weight(string $p): int { if($p==='includes/config.php'){return 1000;} if(str_starts_with($p,'admin/ajax/update-step')){return 950;} if(str_starts_with($p,'includes/updater/')){return 900;} if(self::isControlPath($p)){return 850;} return 0; }
}
