<?php
declare(strict_types=1);

final class BackupService
{
    private UpdateRepository $repo;
    public function __construct(UpdateRepository $repo){$this->repo=$repo;}

    public function sourceStep(array $job,array $manifest,UpdateLogger $logger,int $batch=25): array
    {
        $uuid=(string)$job['job_uuid'];$context=$this->repo->context($job);$root=UpdateRuntime::appRoot();$jobDir=UpdateRuntime::ensureJob($uuid);$backupRoot=$jobDir.'/backup/source';
        if(!isset($context['source_backup_paths'])){
            $paths=[];$created=[];$modes=[];foreach($manifest['files'] as $path=>$targetHash){$full=$root.'/'.$path;if(is_file($full)&&hash_equals(strtolower((string)$targetHash),strtolower((string)hash_file('sha256',$full)))){continue;}$paths[]=$path;if(!file_exists($full)){$created[]=$path;}elseif(is_file($full)){$perm=@fileperms($full);if($perm!==false){$modes[$path]=$perm&0777;}}}foreach($manifest['delete_files']??[] as $path){$full=$root.'/'.$path;if(file_exists($full)&&!in_array($path,$paths,true)){$paths[]=$path;if(is_file($full)){$perm=@fileperms($full);if($perm!==false){$modes[$path]=$perm&0777;}}}}
            sort($paths,SORT_STRING);$context['source_backup_paths']=$paths;$context['source_created_paths']=$created;$context['source_backup_modes']=$modes;$context['source_backup_cursor']=0;$context['source_backup_complete']=false;$context['source_backup_started_at']=gmdate('c');$job=$this->repo->saveContext($uuid,$context);$logger->info('backup_source','SOURCE_BACKUP_START','Backing up installed files that will change.',['paths'=>count($paths)]);
        }
        $context=$this->repo->context($job);$paths=$context['source_backup_paths']??[];$cursor=(int)($context['source_backup_cursor']??0);$end=min(count($paths),$cursor+max(1,$batch));
        for($i=$cursor;$i<$end;$i++){$path=(string)$paths[$i];$src=$root.'/'.$path;if(!file_exists($src)){continue;}$dest=$backupRoot.'/'.$path;$dir=dirname($dest);if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)){throw new UpdateException('SOURCE_BACKUP_FAILED','Source backup directory could not be created.',500);}if(!is_file($src)||!@copy($src,$dest)){throw new UpdateException('SOURCE_BACKUP_FAILED','Installed file could not be backed up: '.$path,500);}$logger->debug('backup_source','SOURCE_BACKUP_FILE','Backed up '.$path);}
        $context['source_backup_cursor']=$end;if($end>=count($paths)){$context['source_backup_complete']=true;$meta=['paths'=>$paths,'created_paths'=>$context['source_created_paths']??[],'modes'=>$context['source_backup_modes']??[],'from_version'=>(string)$job['from_version'],'target_version'=>(string)$job['target_version'],'created_at'=>gmdate('c')];if(!is_dir(dirname($backupRoot.'/source-manifest.json'))){@mkdir(dirname($backupRoot.'/source-manifest.json'),0700,true);}file_put_contents($jobDir.'/backup/source-manifest.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);$logger->success('backup_source','SOURCE_BACKUP_COMPLETE','Source rollback backup completed.',['paths'=>count($paths)]);}
        return $this->repo->saveContext($uuid,$context);
    }

    public function databaseStep(array $job,UpdateLogger $logger,int $rowBatch=250): array
    {
        $uuid=(string)$job['job_uuid'];$context=$this->repo->context($job);$db=$this->repo->db();$jobDir=UpdateRuntime::ensureJob($uuid);$file=$jobDir.'/backup/database-before-'.$job['target_version'].'.sql';$backupDir=dirname($file);if(!is_dir($backupDir)&&!@mkdir($backupDir,0700,true)&&!is_dir($backupDir)){throw new UpdateException('DATABASE_BACKUP_FAILED','Database backup directory could not be created.',500);}
        if(!isset($context['db_backup_tables'])){$rows=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);$tables=[];foreach($rows as $row){$name=(string)($row[0]??'');if($name!==''&&preg_match('/^[A-Za-z0-9_]+$/',$name)){$tables[]=$name;}}$context['db_backup_tables']=$tables;$context['db_backup_table_index']=0;$context['db_backup_offset']=0;$context['db_backup_triggers_written']=false;$context['db_backup_started']=true;$context['db_backup_complete']=false;file_put_contents($file,"-- Licora updater database safety backup\n-- Job: {$uuid}\nSET FOREIGN_KEY_CHECKS=0;\n",LOCK_EX);$job=$this->repo->saveContext($uuid,$context);$logger->info('backup_database','DATABASE_BACKUP_START','Database safety backup started.',['tables'=>count($tables)]);}
        $context=$this->repo->context($job);$tables=$context['db_backup_tables']??[];$idx=(int)($context['db_backup_table_index']??0);$offset=(int)($context['db_backup_offset']??0);if($idx>=count($tables)){$this->writeTriggers($db,$file,$context,$logger);$this->finalizeDatabaseBackup($file,$context,$uuid,$logger);return $this->repo->saveContext($uuid,$context);} $table=(string)$tables[$idx];
        if($offset===0){$create=$db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);$createSql=(string)($create[1]??'');file_put_contents($file,"\n-- Table `{$table}`\nDROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n",FILE_APPEND|LOCK_EX);}
        $limit=max(10,min(1000,$rowBatch));$rows=$db->query("SELECT * FROM `{$table}` LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $row){$cols=[];$vals=[];foreach($row as $col=>$value){$cols[]='`'.str_replace('`','',(string)$col).'`';$vals[]=$value===null?'NULL':$db->quote((string)$value);}file_put_contents($file,'INSERT INTO `'.$table.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n",FILE_APPEND|LOCK_EX);} if(count($rows)<$limit){$context['db_backup_table_index']=$idx+1;$context['db_backup_offset']=0;$logger->debug('backup_database','DATABASE_BACKUP_TABLE','Backed up database table '.$table.'.',['rows_through'=>$offset+count($rows)]);}else{$context['db_backup_offset']=$offset+count($rows);} if((int)$context['db_backup_table_index']>=count($tables)){$this->writeTriggers($db,$file,$context,$logger);$this->finalizeDatabaseBackup($file,$context,$uuid,$logger);} return $this->repo->saveContext($uuid,$context);
    }

    private function writeTriggers(PDO $db,string $file,array &$context,UpdateLogger $logger): void
    {
        if(!empty($context['db_backup_triggers_written'])){return;}
        try{
            $triggers=$db->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
            foreach($triggers as $row){
                $name=(string)($row['Trigger']??'');
                if($name===''||!preg_match('/^[A-Za-z0-9_]+$/',$name)){continue;}
                $create=$db->query("SHOW CREATE TRIGGER `{$name}`")->fetch(PDO::FETCH_ASSOC);
                $sql=(string)($create['SQL Original Statement']??$create['Create Trigger']??'');
                if($sql===''){continue;}
                file_put_contents($file,"
-- Trigger `{$name}`
DROP TRIGGER IF EXISTS `{$name}`;
DELIMITER $$
{$sql}$$
DELIMITER ;
",FILE_APPEND|LOCK_EX);
            }
            $logger->debug('backup_database','DATABASE_BACKUP_TRIGGERS','Database trigger definitions were included in the safety backup.',['triggers'=>count($triggers)]);
        }catch(Throwable $e){
            throw new UpdateException('DATABASE_BACKUP_FAILED','Database trigger definitions could not be backed up.',500,$e);
        }
        $context['db_backup_triggers_written']=true;
    }

    private function finalizeDatabaseBackup(string $file,array &$context,string $uuid,UpdateLogger $logger): void
    {
        if(!empty($context['db_backup_complete'])){return;}file_put_contents($file,"SET FOREIGN_KEY_CHECKS=1;\n",FILE_APPEND|LOCK_EX);$context['db_backup_complete']=true;$context['db_backup_path']=$file;$context['db_backup_sha256']=hash_file('sha256',$file);$logger->success('backup_database','DATABASE_BACKUP_COMPLETE','Database safety backup completed.',['sha256'=>$context['db_backup_sha256']]);
    }
}
