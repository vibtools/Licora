<?php
declare(strict_types=1);

final class UpdateLock
{
    public static function path(): string { return dirname(__DIR__). '/.licora-updater/active-lock.json'; }

    public static function acquire(string $jobUuid): void
    {
        UpdateRuntime::ensure();
        $existing=self::read();
        if($existing&&($existing['job_uuid']??'')!==$jobUuid){
            throw new UpdateException('UPDATE_ALREADY_LOCKED','Another update holds the application update lock.',409);
        }
        self::writeAtomic(['job_uuid'=>$jobUuid,'created_at'=>time(),'updated_at'=>time()], true);
    }

    public static function touch(string $jobUuid): void
    {
        $data=self::read();
        if(!$data||($data['job_uuid']??'')!==$jobUuid){return;}
        $data['updated_at']=time();
        // Keep the existing valid lock if a best-effort heartbeat refresh cannot be finalized.
        self::writeAtomic($data, false);
    }

    public static function release(string $jobUuid): void
    {
        $data=self::read();
        if(!$data||($data['job_uuid']??'')===$jobUuid){@unlink(self::path());}
    }

    public static function heldBy(string $jobUuid): bool
    {
        $data=self::read();
        return $data&&($data['job_uuid']??'')===$jobUuid;
    }

    public static function recoverOrphaned(UpdateRepository $repo): bool
    {
        $path=self::path();
        if(!is_file($path)){return false;}
        $data=self::read();
        if(!$data){
            // acquire()/touch() publish valid JSON atomically. Therefore a persistent lock file
            // that cannot be decoded is corrupt and must not strand the entire application in 503.
            @unlink($path);
            error_log('Licora updater recovered corrupt critical lock metadata.');
            return true;
        }
        $uuid=(string)($data['job_uuid']??'');
        if($uuid===''){@unlink($path);return true;}
        $job=$repo->job($uuid);
        if(!$job||!in_array((string)($job['status']??''),['running','rollback_running'],true)){
            @unlink($path);
            error_log('Licora updater recovered orphaned critical lock for job '.$uuid);
            return true;
        }
        return false;
    }

    public static function read(): ?array
    {
        $path=self::path();
        if(!is_file($path)){return null;}
        $raw=@file_get_contents($path);
        if(!is_string($raw)||$raw===''){return null;}
        $data=json_decode($raw,true);
        return is_array($data)?$data:null;
    }

    private static function writeAtomic(array $data,bool $required): void
    {
        UpdateRuntime::ensure();
        $path=self::path();
        try{$suffix=bin2hex(random_bytes(6));}catch(Throwable $e){$suffix=str_replace('.','',uniqid('',true));}
        $tmp=$path.'.'.$suffix.'.tmp';
        $json=json_encode($data,JSON_UNESCAPED_SLASHES);
        $ok=is_string($json)&&@file_put_contents($tmp,$json,LOCK_EX)!==false;
        if($ok){@chmod($tmp,0600);$ok=@rename($tmp,$path);}
        if(!$ok){@unlink($tmp);if($required){throw new UpdateException('UPDATE_LOCK_FAILED','Application update lock could not be created.',500);}}
        elseif(is_file($path)){@chmod($path,0600);}
    }

    public static function enforceRequest(): void
    {
        if(PHP_SAPI==='cli'||!is_file(self::path())){return;}
        if(self::read()===null){
            // Lock files are atomically published. Invalid JSON therefore cannot represent
            // an active critical section and must not strand ordinary requests in HTTP 503.
            @unlink(self::path());
            error_log('Licora updater removed corrupt critical lock metadata during request boot.');
            return;
        }
        $uri=(string)($_SERVER['REQUEST_URI']??'');
        $path=(string)(parse_url($uri,PHP_URL_PATH)??'');
        $allowed=(bool)preg_match('#/admin/(?:updates\.php|login\.php|logout\.php|ajax/update-[A-Za-z0-9_-]+\.php)$#',$path);
        if($allowed){return;}
        http_response_code(503);
        header('Retry-After: 5');
        header('Cache-Control: no-store');
        if(str_contains($path,'/api/')){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'code'=>'UPDATE_IN_PROGRESS','message'=>'Licora is applying a verified update. Retry shortly.']);
        }else{
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Licora is applying a verified update. Please retry shortly.';
        }
        exit;
    }
}
