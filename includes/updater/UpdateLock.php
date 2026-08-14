<?php
declare(strict_types=1);

final class UpdateLock
{
    public static function path(): string { return dirname(__DIR__). '/.licora-updater/active-lock.json'; }
    public static function acquire(string $jobUuid): void
    {
        UpdateRuntime::ensure();$path=self::path();$existing=self::read();if($existing&&($existing['job_uuid']??'')!==$jobUuid){throw new UpdateException('UPDATE_ALREADY_LOCKED','Another update holds the application update lock.',409);} $data=['job_uuid'=>$jobUuid,'created_at'=>time(),'updated_at'=>time()];$tmp=$path.'.tmp';if(@file_put_contents($tmp,json_encode($data),LOCK_EX)===false||!@rename($tmp,$path)){@unlink($tmp);throw new UpdateException('UPDATE_LOCK_FAILED','Application update lock could not be created.',500);}@chmod($path,0600);
    }
    public static function touch(string $jobUuid): void { $data=self::read();if(!$data||($data['job_uuid']??'')!==$jobUuid){return;}$data['updated_at']=time();@file_put_contents(self::path(),json_encode($data),LOCK_EX); }
    public static function release(string $jobUuid): void { $data=self::read();if(!$data||($data['job_uuid']??'')===$jobUuid){@unlink(self::path());} }
    public static function heldBy(string $jobUuid): bool { $data=self::read();return $data&&($data['job_uuid']??'')===$jobUuid; }
    public static function recoverOrphaned(UpdateRepository $repo): bool
    {
        $data=self::read(); if(!$data){return false;} $uuid=(string)($data['job_uuid']??'');
        if($uuid===''){@unlink(self::path());return true;}
        $job=$repo->job($uuid);
        if(!$job||!in_array((string)($job['status']??''),['running','rollback_running'],true)){
            @unlink(self::path());
            error_log('Licora updater recovered orphaned critical lock for job '.$uuid);
            return true;
        }
        return false;
    }
    public static function read(): ?array { $path=self::path();if(!is_file($path)){return null;}$data=json_decode((string)@file_get_contents($path),true);return is_array($data)?$data:null; }
    public static function enforceRequest(): void
    {
        if(PHP_SAPI==='cli'||!is_file(self::path())){return;} $uri=(string)($_SERVER['REQUEST_URI']??'');$path=(string)(parse_url($uri,PHP_URL_PATH)??'');$allowed=(bool)preg_match('#/admin/(?:updates\.php|login\.php|logout\.php|ajax/update-[A-Za-z0-9_-]+\.php)$#',$path);if($allowed){return;} http_response_code(503);header('Retry-After: 5');header('Cache-Control: no-store');if(str_contains($path,'/api/')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'code'=>'UPDATE_IN_PROGRESS','message'=>'Licora is applying a verified update. Retry shortly.']);}else{header('Content-Type: text/plain; charset=utf-8');echo 'Licora is applying a verified update. Please retry shortly.';}exit;
    }
}
