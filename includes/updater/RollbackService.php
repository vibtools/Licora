<?php
declare(strict_types=1);

final class RollbackService
{
    private UpdateRepository $repo;
    public function __construct(UpdateRepository $repo){$this->repo=$repo;}

    public function sourceStep(array $job,UpdateLogger $logger,int $batch=30): array
    {
        $uuid=(string)$job['job_uuid'];$context=$this->repo->context($job);$root=UpdateRuntime::appRoot();$backupRoot=UpdateRuntime::jobDir($uuid).'/backup/source';$paths=$context['source_backup_paths']??[];
        if(!isset($context['rollback_paths'])){
            $restore=[];foreach($paths as $path){if(is_file($backupRoot.'/'.$path)){$restore[]=$path;}}
            usort($restore,[self::class,'compareRollbackPath']);$context['rollback_paths']=$restore;$context['rollback_source_cursor']=0;$context['rollback_created_cursor']=0;
            $job=$this->repo->saveContext($uuid,$context);$logger->warning('rollback','SOURCE_ROLLBACK_START','Restoring pre-update source files.',['files'=>count($restore)]);
        }
        $context=$this->repo->context($job);$restore=$context['rollback_paths']??[];$hashes=is_array($context['source_backup_hashes']??null)?$context['source_backup_hashes']:[];$cursor=(int)($context['rollback_source_cursor']??0);$end=min(count($restore),$cursor+max(1,$batch));
        if($cursor<count($restore)){
            if(FileInstaller::isControlPath((string)$restore[$cursor])){$end=count($restore);}
            for($i=$cursor;$i<$end;$i++){
                $path=(string)$restore[$i];$src=$backupRoot.'/'.$path;
                if(!is_file($src)){throw new UpdateException('ROLLBACK_BACKUP_INVALID','Rollback backup file is missing for '.$path.'.',500);}
                $sourceHash=hash_file('sha256',$src);if(!is_string($sourceHash)){throw new UpdateException('ROLLBACK_BACKUP_INVALID','Rollback backup could not be hashed for '.$path.'.',500);}
                // Jobs started by v5.3.0/v5.4.0 were backed up before v5.4.1 source code
                // was applied and therefore do not contain source_backup_hashes. Preserve
                // rollback compatibility for those in-flight jobs while requiring the
                // persisted hash whenever the v5.4.1 backup engine created it.
                $expected=(string)($hashes[$path]??$sourceHash);
                if(!preg_match('/^[a-f0-9]{64}$/i',$expected)||!hash_equals(strtolower($expected),strtolower($sourceHash))){throw new UpdateException('ROLLBACK_BACKUP_INVALID','Rollback backup checksum failed for '.$path.'.',500);}
                $dest=$root.'/'.$path;$dir=dirname($dest);if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir)){throw new UpdateException('ROLLBACK_FAILED','Rollback directory could not be created.',500);}
                $tmp=$dest.'.licora-rollback.tmp';@unlink($tmp);
                if(!@copy($src,$tmp)){throw new UpdateException('ROLLBACK_FAILED','Rollback could not stage '.$path.'.',500);}
                $tmpHash=hash_file('sha256',$tmp);if(!is_string($tmpHash)||!hash_equals(strtolower($expected),strtolower($tmpHash))){@unlink($tmp);throw new UpdateException('ROLLBACK_FAILED','Rollback staged checksum failed for '.$path.'.',500);}
                $mode=(int)(($context['source_backup_modes']??[])[$path]??0644);@chmod($tmp,$mode>0?$mode:0644);
                if(!@rename($tmp,$dest)){@unlink($tmp);throw new UpdateException('ROLLBACK_FAILED','Rollback could not atomically restore '.$path.'.',500);}
                $destHash=hash_file('sha256',$dest);if(!is_string($destHash)||!hash_equals(strtolower($expected),strtolower($destHash))){throw new UpdateException('ROLLBACK_FAILED','Rollback restored checksum failed for '.$path.'.',500);}
                $logger->debug('rollback','SOURCE_FILE_RESTORED','Restored and verified '.$path.'.');
            }
            $context['rollback_source_cursor']=$end;return $this->repo->saveContext($uuid,$context);
        }
        $created=array_values($context['source_created_paths']??[]);$cc=(int)($context['rollback_created_cursor']??0);$ce=min(count($created),$cc+max(1,$batch));
        for($i=$cc;$i<$ce;$i++){
            $path=(string)$created[$i];$full=$root.'/'.$path;
            if(is_file($full)||is_link($full)){if(!@unlink($full)){throw new UpdateException('ROLLBACK_FAILED','Rollback could not remove update-created file '.$path.'.',500);}if(file_exists($full)||is_link($full)){throw new UpdateException('ROLLBACK_FAILED','Rollback removal verification failed for '.$path.'.',500);}$logger->debug('rollback','NEW_FILE_REMOVED','Removed update-created file '.$path.'.');}
        }
        $context['rollback_created_cursor']=$ce;
        if($ce>=count($created)){$context['rollback_source_complete']=true;$logger->success('rollback','SOURCE_ROLLBACK_COMPLETE','Pre-update source was restored and verified.');}
        return $this->repo->saveContext($uuid,$context);
    }
    public static function compareRollbackPath(string $a,string $b): int{return FileInstaller::compareApplyPath($a,$b);}
}
