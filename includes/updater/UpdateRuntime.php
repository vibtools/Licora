<?php
declare(strict_types=1);

final class UpdateRuntime
{
    public static function root(): string { return dirname(__DIR__) . '/.licora-updater'; }
    public static function jobDir(string $uuid): string { if(!preg_match('/^[a-f0-9-]{36}$/i',$uuid)){throw new UpdateException('UPDATE_JOB_INVALID','Update job identifier is invalid.',400);} return self::root().'/jobs/'.$uuid; }
    public static function ensure(): void { $root=self::root(); if(!is_dir($root)&&!@mkdir($root,0700,true)&&!is_dir($root)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Updater runtime directory could not be created.',500);} @chmod($root,0700); $jobs=$root.'/jobs'; if(!is_dir($jobs)&&!@mkdir($jobs,0700,true)&&!is_dir($jobs)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Updater jobs directory could not be created.',500);} }
    public static function ensureJob(string $uuid): string { self::ensure(); $dir=self::jobDir($uuid); if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Update job directory could not be created.',500);} @chmod($dir,0700); return $dir; }
    public static function appRoot(): string { return dirname(__DIR__,2); }
}
