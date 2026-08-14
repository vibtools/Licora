<?php
declare(strict_types=1);

final class UpdateService
{
    public const STAGES = [
        'fetch_manifest','preflight','download','stage_archive','backup_source','backup_database',
        'lock_update','migrate','apply_files','post_verify','cleanup',
        'rollback_migrations','rollback_source','rollback_finalize'
    ];

    private UpdateRepository $repo;
    private ReleaseClient $releases;
    private HttpClient $http;
    private ManifestVerifier $manifestVerifier;
    private PreflightService $preflight;
    private ArchiveValidator $archives;
    private BackupService $backup;
    private MigrationRunner $migrations;
    private FileInstaller $installer;
    private RollbackService $rollback;

    public function __construct(
        UpdateRepository $repo,
        ReleaseClient $releases,
        HttpClient $http,
        ManifestVerifier $manifestVerifier,
        PreflightService $preflight,
        ArchiveValidator $archives,
        BackupService $backup,
        MigrationRunner $migrations,
        FileInstaller $installer,
        RollbackService $rollback
    ) {
        $this->repo=$repo; $this->releases=$releases; $this->http=$http; $this->manifestVerifier=$manifestVerifier;
        $this->preflight=$preflight; $this->archives=$archives; $this->backup=$backup; $this->migrations=$migrations;
        $this->installer=$installer; $this->rollback=$rollback;
    }

    public function checkForUpdates(bool $force=false): array
    {
        $current=defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        $auto=$this->repo->getSetting('updater_auto_check','1') !== '0';
        $interval=(int)$this->repo->getSetting('updater_check_interval_seconds', defined('LICORA_UPDATE_CHECK_INTERVAL') ? (string)LICORA_UPDATE_CHECK_INTERVAL : '21600');
        $interval=max(900,min(604800,$interval));
        $last=(int)$this->repo->getSetting('updater_last_check_at','0');
        $cachedRaw=(string)$this->repo->getSetting('updater_latest_release_json','');
        $cached=$cachedRaw!=='' ? json_decode($cachedRaw,true) : null;
        // updater_auto_check=0 must not initiate an outbound GitHub request; a Super Admin can still force a manual check.
        $shouldFetch=$force || ($auto && (!is_array($cached) || !$last || (time()-$last)>=$interval));
        $warning=null;
        if($shouldFetch){
            try{
                $cached=$this->releases->latest();
                $this->repo->setSetting('updater_latest_release_json',json_encode($cached,JSON_UNESCAPED_SLASHES));
                $this->repo->setSetting('updater_last_check_at',(string)time());
                $last=time();
            }catch(Throwable $e){
                if(!is_array($cached)){ throw $e; }
                $warning=$e instanceof UpdateException ? $e->getMessage() : 'GitHub update check failed; cached release metadata is being shown.';
                error_log('Licora updater release check failed: '.get_class($e).' '.$e->getMessage());
            }
        }
        if(!is_array($cached)){return ['current_version'=>$current,'latest'=>null,'update_available'=>false,'last_checked_at'=>$last?:null,'warning'=>$warning];}
        return [
            'current_version'=>$current,
            'latest'=>self::safeRelease($cached),
            'update_available'=>version_compare((string)$cached['version'],$current,'>'),
            'last_checked_at'=>$last?:null,
            'warning'=>$warning,
        ];
    }

    public function previewPreflight(string $targetVersion): array
    {
        if(!preg_match('/^\d+\.\d+\.\d+$/',$targetVersion)){throw new UpdateException('UPDATE_VERSION_INVALID','Requested update version is invalid.',400);}
        $release=$this->releases->latest();
        if(($release['version']??'')!==$targetVersion){throw new UpdateException('UPDATE_RELEASE_CHANGED','The selected release is no longer the latest stable release.',409);}
        $temp=UpdateRuntime::root().'/preflight-'.bin2hex(random_bytes(6));
        if(!is_dir($temp)&&!@mkdir($temp,0700,true)&&!is_dir($temp)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Updater preflight storage could not be created.',500);}
        try{
            $manifestAsset=$this->releases->asset($release,'licora-update-manifest.json');
            $sigAsset=$this->releases->asset($release,'licora-update-manifest.sig');
            $manifestInfo=$this->http->download($manifestAsset['url'],$temp.'/manifest.json',[],2097152);
            $sigInfo=$this->http->download($sigAsset['url'],$temp.'/manifest.sig',[],65536);
            $manifestJson=(string)file_get_contents($manifestInfo['path']);
            $signature=(string)file_get_contents($sigInfo['path']);
            $manifest=$this->manifestVerifier->verify($manifestJson,$signature,$targetVersion,defined('APP_VERSION')?APP_VERSION:'0.0.0');
            $package=$this->releases->asset($release,(string)$manifest['package']['name']);
            if((int)($manifest['package']['size']??0)!==(int)$package['size']){throw new UpdateException('PACKAGE_SIZE_MISMATCH','GitHub release asset size differs from the signed update manifest.',409);}
            $result=$this->preflight->run($manifest,(int)$package['size']);
            return ['version'=>$targetVersion,'signature_verified'=>true,'preflight'=>$result];
        } finally { ArchiveValidator::removeTree($temp); }
    }

    public function start(string $targetVersion,int $adminId): array
    {
        if(!preg_match('/^\d+\.\d+\.\d+$/',$targetVersion)){throw new UpdateException('UPDATE_VERSION_INVALID','Requested update version is invalid.',400);}
        $check=$this->checkForUpdates(true);$latest=$check['latest']??null;
        if(!$latest||($latest['version']??'')!==$targetVersion){throw new UpdateException('UPDATE_RELEASE_CHANGED','The selected release is no longer the latest stable release. Check again.',409);}
        $current=defined('APP_VERSION')?APP_VERSION:'0.0.0'; if(!version_compare($targetVersion,$current,'>')){throw new UpdateException('UPDATE_NOT_NEWER','The selected release is not newer than the installed version.',409);}
        // Re-fetch normalized release including full trusted asset metadata for the job snapshot.
        $release=$this->releases->latest(); if(($release['version']??'')!==$targetVersion){throw new UpdateException('UPDATE_RELEASE_CHANGED','The latest stable release changed before the update started.',409);}
        $job=$this->repo->withCoordinatorLock(function() use ($adminId,$current,$targetVersion,$release){
            if($this->repo->activeJob()){throw new UpdateException('UPDATE_ALREADY_RUNNING','Another update or rollback is already in progress.',409);}
            return $this->repo->createJob(['admin_id'=>$adminId,'from_version'=>$current,'target_version'=>$targetVersion,'release_tag'=>$release['tag'],'release_url'=>$release['html_url'],'context'=>['release'=>$release,'started_by'=>$adminId]]);
        });
        $logger=new UpdateLogger($this->repo,(string)$job['job_uuid']);
        try {
            UpdateRuntime::ensureJob((string)$job['job_uuid']);
            $logger->info('fetch_manifest','UPDATE_JOB_STARTED','Secure update job started: Licora '.$current.' → '.$targetVersion.'.');
        } catch (Throwable $e) {
            // The database job already exists at this point. Finalize it instead of leaving
            // an unrecoverable forever-running job if runtime storage cannot be initialized.
            $this->handleFailure($job,$logger,$e);
            throw $e;
        }
        return self::safeJob($job);
    }

    public function step(string $uuid): array
    {
        $job=$this->repo->job($uuid);if(!$job){throw new UpdateException('UPDATE_JOB_NOT_FOUND','Update job was not found.',404);} if(!in_array((string)$job['status'],['running','rollback_running'],true)){return self::safeJob($job);}
        $logger=new UpdateLogger($this->repo,$uuid);
        $stepHandle=null;
        try{
            $stepLockPath=UpdateRuntime::ensureJob($uuid).'/step.lock';
            $stepHandle=@fopen($stepLockPath,'c');
            if($stepHandle===false){throw new UpdateException('UPDATE_STEP_LOCK_FAILED','Updater step lock could not be opened.',500);}
            if(!@flock($stepHandle,LOCK_EX|LOCK_NB)){fclose($stepHandle);$stepHandle=null;return self::safeJob($job);}
            if((string)$job['status']==='rollback_running'){return $this->rollbackStep($job,$logger);}
            $stage=(string)$job['stage']; if(!in_array($stage,self::STAGES,true)){throw new UpdateException('UPDATE_STAGE_INVALID','Update job contains an invalid stage.',500);}
            switch($stage){
                case 'fetch_manifest': $job=$this->stageFetchManifest($job,$logger); break;
                case 'preflight': $job=$this->stagePreflight($job,$logger); break;
                case 'download': $job=$this->stageDownload($job,$logger); break;
                case 'stage_archive': $job=$this->stageArchive($job,$logger); break;
                case 'backup_source': $job=$this->stageSourceBackup($job,$logger); break;
                case 'backup_database': $job=$this->stageDatabaseBackup($job,$logger); break;
                case 'lock_update': $job=$this->stageLock($job,$logger); break;
                case 'migrate': $job=$this->stageMigrate($job,$logger); break;
                case 'apply_files': $job=$this->stageApply($job,$logger); break;
                case 'post_verify': $job=$this->stagePostVerify($job,$logger); break;
                case 'cleanup': $job=$this->stageCleanup($job,$logger); break;
                default: throw new UpdateException('UPDATE_STAGE_INVALID','Update job stage is not executable.',500);
            }
            if(UpdateLock::heldBy($uuid)){UpdateLock::touch($uuid);} return self::safeJob($job);
        }catch(Throwable $e){return self::safeJob($this->handleFailure($job,$logger,$e));}
        finally { if(is_resource($stepHandle)){@flock($stepHandle,LOCK_UN);fclose($stepHandle);} }
    }

    public function status(string $uuid): array
    {
        $job=$this->repo->job($uuid);if(!$job){throw new UpdateException('UPDATE_JOB_NOT_FOUND','Update job was not found.',404);}return self::safeJob($job);
    }

    public function active(): ?array { $job=$this->repo->activeJob();return $job?self::safeJob($job):null; }
    public function history(int $limit=20): array { return array_map([self::class,'safeJob'],$this->repo->history($limit)); }

    public function events(string $uuid,int $after=0): array
    {
        if(!$this->repo->job($uuid)){throw new UpdateException('UPDATE_JOB_NOT_FOUND','Update job was not found.',404);} $events=$this->repo->eventsSince($uuid,$after,300);$last=$after;if($events){$last=(int)end($events)['id'];}return ['events'=>$events,'last_id'=>$last];
    }

    public function diagnostics(string $uuid): string
    {
        $job=$this->repo->job($uuid);if(!$job){throw new UpdateException('UPDATE_JOB_NOT_FOUND','Update job was not found.',404);} $lines=[];$lines[]='Licora Update Diagnostics';$lines[]='Job: '.$uuid;$lines[]='From: '.$job['from_version'];$lines[]='Target: '.$job['target_version'];$lines[]='Status: '.$job['status'];$lines[]='Stage: '.$job['stage'];$lines[]='Progress: '.$job['progress'].'%';$lines[]='Error Code: '.($job['error_code']?:'none');$lines[]='Error: '.($job['error_message']?:'none');$lines[]='Created: '.$job['created_at'];$lines[]='Finished: '.($job['finished_at']?:'n/a');$lines[]='';$lines[]='Events:';foreach($this->repo->allEvents($uuid) as $event){$lines[]=sprintf('#%d %s %-7s %-20s %s',(int)$event['id'],(string)$event['created_at'],strtoupper((string)$event['level']),(string)$event['stage'],(string)$event['message']);} return implode("\n",$lines)."\n";
    }

    public function requestRollback(string $uuid,int $adminId): array
    {
        $lockAcquired=false;
        try {
            $job=$this->repo->withCoordinatorLock(function() use ($uuid,$adminId,&$lockAcquired){
                if($this->repo->activeJob()){throw new UpdateException('UPDATE_ALREADY_RUNNING','Another update or rollback is already in progress.',409);}
                $job=$this->repo->job($uuid);if(!$job){throw new UpdateException('UPDATE_JOB_NOT_FOUND','Update job was not found.',404);} if((string)$job['status']!=='success'){throw new UpdateException('ROLLBACK_NOT_AVAILABLE','Only a completed update can be manually rolled back from Update History.',409);} if(!defined('APP_VERSION')||APP_VERSION!==(string)$job['target_version']){throw new UpdateException('ROLLBACK_VERSION_MISMATCH','Installed version no longer matches this update job, so rollback is blocked.',409);} $context=$this->repo->context($job);if(empty($context['source_backup_complete'])){throw new UpdateException('ROLLBACK_BACKUP_MISSING','Source rollback backup is unavailable.',409);} $context['manual_rollback']=true;$context['rollback_requested_by']=$adminId;unset($context['rollback_migration_ids'],$context['rollback_migration_index'],$context['rollback_migrations_complete'],$context['rollback_paths'],$context['rollback_source_cursor'],$context['rollback_created_cursor'],$context['rollback_source_complete']);
                // Acquire the filesystem lock before committing the database transition so
                // a filesystem failure cannot strand the job in rollback_running state.
                UpdateLock::acquire($uuid);$lockAcquired=true;
                return $this->repo->saveContext($uuid,$context,['status'=>'rollback_running','stage'=>'rollback_migrations','rollback_status'=>'running','finished_at'=>null]);
            });
        } catch (Throwable $e) {
            if($lockAcquired){UpdateLock::release($uuid);}
            throw $e;
        }
        $logger=new UpdateLogger($this->repo,$uuid);$logger->warning('rollback','MANUAL_ROLLBACK_REQUESTED','Super Admin requested rollback to Licora '.$job['from_version'].'.');return self::safeJob($job);
    }

    private function stageFetchManifest(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];$context=$this->repo->context($job);$release=$context['release']??null;if(!is_array($release)){throw new UpdateException('RELEASE_METADATA_MISSING','Update job release metadata is missing.',500);} $jobDir=UpdateRuntime::ensureJob($uuid);$manifestAsset=$this->releases->asset($release,'licora-update-manifest.json');$sigAsset=$this->releases->asset($release,'licora-update-manifest.sig');$logger->info('fetch_manifest','MANIFEST_DOWNLOAD','Downloading signed update manifest.');$manifestInfo=$this->http->download($manifestAsset['url'],$jobDir.'/licora-update-manifest.json',[],2097152);$sigInfo=$this->http->download($sigAsset['url'],$jobDir.'/licora-update-manifest.sig',[],65536);$manifestJson=(string)file_get_contents($manifestInfo['path']);$signature=(string)file_get_contents($sigInfo['path']);$manifest=$this->manifestVerifier->verify($manifestJson,$signature,(string)$job['target_version'],(string)$job['from_version']);$context['manifest_path']=$manifestInfo['path'];$context['signature_path']=$sigInfo['path'];$context['manifest_verified']=true;$logger->success('fetch_manifest','MANIFEST_SIGNATURE_OK','Update manifest signature verified with the dedicated Licora updater key.');return $this->repo->saveContext($uuid,$context,['manifest_json'=>$manifestJson,'stage'=>'preflight','progress'=>8]);
    }

    private function stagePreflight(array $job,UpdateLogger $logger): array
    {
        $manifest=$this->manifest($job);$context=$this->repo->context($job);$release=$context['release']??[];$packageName=(string)($manifest['package']['name']??'');$packageAsset=$this->releases->asset($release,$packageName);if((int)($manifest['package']['size']??0)!==(int)$packageAsset['size']){throw new UpdateException('PACKAGE_SIZE_MISMATCH','GitHub release asset size differs from the signed update manifest.',409);}$logger->info('preflight','PREFLIGHT_START','Running cPanel/VPS-safe update preflight checks.');$result=$this->preflight->run($manifest,(int)$packageAsset['size']);foreach($result['checks'] as $check){$logger->event($check['ok']?'success':'error','preflight',$check['ok']?'PREFLIGHT_PASS':'PREFLIGHT_FAIL',$check['label'].': '.($check['detail']?:($check['ok']?'OK':'FAILED')));} $context['preflight']=$result;if(!$result['ok']){$this->repo->saveContext((string)$job['job_uuid'],$context);throw new UpdateException('PREFLIGHT_FAILED','One or more update preflight checks failed. No application files were changed.',409);} $logger->success('preflight','PREFLIGHT_COMPLETE','All mandatory update preflight checks passed.');return $this->repo->saveContext((string)$job['job_uuid'],$context,['stage'=>'download','progress'=>15]);
    }

    private function stageDownload(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];$manifest=$this->manifest($job);$context=$this->repo->context($job);$release=$context['release']??[];$packageName=(string)$manifest['package']['name'];$asset=$this->releases->asset($release,$packageName);$dest=UpdateRuntime::ensureJob($uuid).'/'.$packageName;$logger->info('download','PACKAGE_DOWNLOAD_START','Downloading '.$packageName.' from the verified GitHub release.');$max=defined('LICORA_UPDATE_MAX_PACKAGE_BYTES')?(int)LICORA_UPDATE_MAX_PACKAGE_BYTES:104857600;$info=$this->http->download($asset['url'],$dest,[],$max);$signedSize=(int)($manifest['package']['size']??0);if($signedSize<1||(int)$info['size']!==$signedSize){@unlink($dest);throw new UpdateException('PACKAGE_SIZE_MISMATCH','Downloaded release ZIP size did not match the signed update manifest.',400);}$expected=strtolower((string)$manifest['package']['sha256']);if(!hash_equals($expected,strtolower((string)$info['sha256']))){@unlink($dest);throw new UpdateException('PACKAGE_CHECKSUM_MISMATCH','Downloaded release ZIP did not match the signed SHA-256 checksum.',400);} if(!empty($asset['digest'])&&str_starts_with((string)$asset['digest'],'sha256:')){$githubHash=strtolower(substr((string)$asset['digest'],7));if(!hash_equals($expected,$githubHash)){throw new UpdateException('PACKAGE_GITHUB_DIGEST_MISMATCH','GitHub asset digest differs from the signed update manifest.',409);}}$context['package_path']=$dest;$context['package_sha256']=$expected;$context['package_size']=$info['size'];$logger->success('download','PACKAGE_VERIFIED','Release package downloaded and SHA-256 verified.',['bytes'=>$info['size'],'sha256'=>$expected]);return $this->repo->saveContext($uuid,$context,['stage'=>'stage_archive','progress'=>28]);
    }

    private function stageArchive(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];$manifest=$this->manifest($job);$context=$this->repo->context($job);$result=$this->archives->extract((string)($context['package_path']??''),(string)$job['target_version'],$manifest,$uuid,$logger);$context['staging_root']=$result['staging_root'];$context['staged_files']=$result['files'];return $this->repo->saveContext($uuid,$context,['stage'=>'backup_source','progress'=>38]);
    }

    private function stageSourceBackup(array $job,UpdateLogger $logger): array
    {
        $manifest=$this->manifest($job);$job=$this->backup->sourceStep($job,$manifest,$logger,30);$context=$this->repo->context($job);if(!empty($context['source_backup_complete'])){
            $migrations=$manifest['migrations']??[];
            if($migrations){
                // The pure-PHP database safety dump spans resumable requests. Enter the
                // critical lock before any migration backup so Licora writes cannot make
                // the chunked dump internally inconsistent while it is being captured.
                $context['lock_next']='backup_database';
                $job=$this->repo->saveContext((string)$job['job_uuid'],$context,['stage'=>'lock_update','progress'=>50]);
            }else{
                $job=$this->repo->updateJob((string)$job['job_uuid'],['stage'=>'lock_update','progress'=>50]);
            }
        }return $job;
    }

    private function stageDatabaseBackup(array $job,UpdateLogger $logger): array
    {
        $job=$this->backup->databaseStep($job,$logger,250);$context=$this->repo->context($job);if(!empty($context['db_backup_complete'])){$next=UpdateLock::heldBy((string)$job['job_uuid'])?'migrate':'lock_update';$job=$this->repo->updateJob((string)$job['job_uuid'],['stage'=>$next,'progress'=>58]);}return $job;
    }

    private function stageLock(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];UpdateLock::acquire($uuid);$logger->warning('lock_update','UPDATE_LOCK_ACTIVE','Critical update lock enabled. Non-updater application requests will receive a temporary 503 response.');$context=$this->repo->context($job);$next=(string)($context['lock_next']??'migrate');unset($context['lock_next']);return $this->repo->saveContext($uuid,$context,['stage'=>$next,'progress'=>62]);
    }

    private function stageMigrate(array $job,UpdateLogger $logger): array
    {
        $manifest=$this->manifest($job);$context=$this->repo->context($job);$staging=(string)($context['staging_root']??'');$job=$this->migrations->applyNext($job,$manifest,$staging,$logger);$context=$this->repo->context($job);if(!empty($context['migrations_complete'])){$logger->success('migrate','MIGRATIONS_COMPLETE','All signed release migrations are complete.');$job=$this->repo->updateJob((string)$job['job_uuid'],['stage'=>'apply_files','progress'=>68]);}return $job;
    }

    private function stageApply(array $job,UpdateLogger $logger): array
    {
        $manifest=$this->manifest($job);$context=$this->repo->context($job);$staging=(string)($context['staging_root']??'');$job=$this->installer->step($job,$manifest,$staging,$logger,28);$context=$this->repo->context($job);$paths=$context['apply_paths']??[];$cursor=(int)($context['apply_cursor']??0);$progress=68;if($paths){$progress=min(92,68+(int)floor(24*$cursor/count($paths)));}$job=$this->repo->updateJob((string)$job['job_uuid'],['progress'=>$progress]);$context=$this->repo->context($job);if(!empty($context['apply_complete'])){$job=$this->repo->updateJob((string)$job['job_uuid'],['stage'=>'post_verify','progress'=>94]);}return $job;
    }

    private function stagePostVerify(array $job,UpdateLogger $logger): array
    {
        $manifest=$this->manifest($job);$logger->info('post_verify','POST_VERIFY_START','Verifying installed source against the signed release manifest.');$result=$this->installer->verifyInstalled($manifest,(string)$job['target_version']);$logger->success('post_verify','POST_VERIFY_COMPLETE','Installed source and runtime version passed post-update verification.',['files'=>$result['files']]);return $this->repo->updateJob((string)$job['job_uuid'],['stage'=>'cleanup','progress'=>98]);
    }

    private function stageCleanup(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];$this->repo->setSetting('updater_last_installed_version',(string)$job['target_version']);$this->repo->setSetting('updater_last_success_job',$uuid);$logger->success('cleanup','UPDATE_COMPLETE','Licora '.$job['target_version'].' update completed successfully. Rollback backup and diagnostics were retained.');UpdateLock::release($uuid);return $this->repo->updateJob($uuid,['status'=>'success','stage'=>'complete','progress'=>100,'rollback_status'=>null,'finished_at'=>date('Y-m-d H:i:s')]);
    }

    private function rollbackStep(array $job,UpdateLogger $logger): array
    {
        $uuid=(string)$job['job_uuid'];if(!UpdateLock::heldBy($uuid)){UpdateLock::acquire($uuid);} $manifest=$this->manifest($job);$context=$this->repo->context($job);$staging=(string)($context['staging_root']??'');$stage=(string)$job['stage'];
        if($stage==='rollback_migrations'){$job=$this->migrations->rollbackNext($job,$manifest,$staging,$logger);$context=$this->repo->context($job);if(!empty($context['rollback_migrations_complete'])){$job=$this->repo->updateJob($uuid,['stage'=>'rollback_source','rollback_status'=>'restoring_source']);}return self::safeJob($job);}
        if($stage==='rollback_source'){$job=$this->rollback->sourceStep($job,$logger,36);$context=$this->repo->context($job);if(!empty($context['rollback_source_complete'])){$job=$this->repo->updateJob($uuid,['stage'=>'rollback_finalize','rollback_status'=>'verifying']);}return self::safeJob($job);}
        if($stage==='rollback_finalize'){$logger->success('rollback','ROLLBACK_COMPLETE','Automatic/source rollback completed. Installed source was restored to Licora '.$job['from_version'].'.');UpdateLock::release($uuid);$job=$this->repo->updateJob($uuid,['status'=>'rolled_back','stage'=>'rolled_back','progress'=>100,'rollback_status'=>'complete','finished_at'=>date('Y-m-d H:i:s')]);return self::safeJob($job);}
        throw new UpdateException('ROLLBACK_STAGE_INVALID','Rollback job contains an invalid stage.',500);
    }

    private function handleFailure(array $job,UpdateLogger $logger,Throwable $e): array
    {
        $uuid=(string)$job['job_uuid'];$code=$e instanceof UpdateException?$e->errorCode():'UPDATE_INTERNAL_ERROR';$message=$e instanceof UpdateException?$e->getMessage():'Update failed due to an internal server error. Review updater diagnostics.';if(!($e instanceof UpdateException)){error_log('Licora updater ['.$uuid.'] '.get_class($e).' '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine());}$logger->error((string)$job['stage'],$code,$message);$context=$this->repo->context($job);$critical=UpdateLock::heldBy($uuid)||in_array((string)$job['stage'],['migrate','apply_files','post_verify','cleanup'],true);
        if($critical&&!empty($context['source_backup_complete'])){$logger->warning('rollback','AUTO_ROLLBACK_SCHEDULED','Update failed after entering the critical section. Automatic rollback has been scheduled.');return $this->repo->updateJob($uuid,['status'=>'rollback_running','stage'=>'rollback_migrations','error_code'=>$code,'error_message'=>$message,'rollback_status'=>'scheduled']);}
        UpdateLock::release($uuid);return $this->repo->updateJob($uuid,['status'=>'failed','error_code'=>$code,'error_message'=>$message,'rollback_status'=>null,'finished_at'=>date('Y-m-d H:i:s')]);
    }

    private function manifest(array $job): array
    {
        $json=(string)($job['manifest_json']??'');$manifest=json_decode($json,true);if(!is_array($manifest)){throw new UpdateException('UPDATE_MANIFEST_MISSING','Verified update manifest is unavailable for this job.',500);}return $manifest;
    }

    public static function safeJob(array $job): array
    {
        return [
            'job_uuid'=>(string)($job['job_uuid']??''),'from_version'=>(string)($job['from_version']??''),'target_version'=>(string)($job['target_version']??''),'release_tag'=>(string)($job['release_tag']??''),'status'=>(string)($job['status']??''),'stage'=>(string)($job['stage']??''),'progress'=>(int)($job['progress']??0),'error_code'=>$job['error_code']??null,'error_message'=>$job['error_message']??null,'rollback_status'=>$job['rollback_status']??null,'created_at'=>$job['created_at']??null,'finished_at'=>$job['finished_at']??null,
        ];
    }
    private static function safeRelease(array $release): array
    {
        return ['version'=>(string)($release['version']??''),'tag'=>(string)($release['tag']??''),'name'=>(string)($release['name']??''),'body'=>(string)($release['body']??''),'published_at'=>(string)($release['published_at']??''),'html_url'=>(string)($release['html_url']??'')];
    }
}
