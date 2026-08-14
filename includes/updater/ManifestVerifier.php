<?php
declare(strict_types=1);

final class ManifestVerifier
{
    private string $publicKeyPath;
    private const PROTECTED_EXACT = [
        'includes/config.local.php','includes/.licora-encryption.key','includes/.licora-installed',
        'includes/.licora-v2-signing-private.pem','includes/.licora-v2-signing-public.pem',
        'includes/updater/update-signing-private.pem'
    ];
    public function __construct(?string $publicKeyPath=null){$this->publicKeyPath=$publicKeyPath ?: (defined('LICORA_UPDATE_PUBLIC_KEY_PATH')?LICORA_UPDATE_PUBLIC_KEY_PATH:__DIR__.'/update-signing-public.pem');}

    public function verify(string $manifestJson,string $signature,string $expectedVersion,string $currentVersion): array
    {
        if(!is_readable($this->publicKeyPath)){throw new UpdateException('UPDATE_PUBLIC_KEY_MISSING','Update verification public key is missing.',500);} $key=openssl_pkey_get_public((string)file_get_contents($this->publicKeyPath)); if($key===false){throw new UpdateException('UPDATE_PUBLIC_KEY_INVALID','Update verification public key is invalid.',500);} $verified=openssl_verify($manifestJson,$signature,$key,OPENSSL_ALGO_SHA256); if($verified!==1){throw new UpdateException('UPDATE_SIGNATURE_INVALID','Update manifest signature verification failed.',400);} $manifest=json_decode($manifestJson,true); if(!is_array($manifest)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest JSON is invalid.',400);} $this->validate($manifest,$expectedVersion,$currentVersion); return $manifest;
    }

    public function validate(array $m,string $expectedVersion,string $currentVersion): void
    {
        if((int)($m['protocol_version']??0)!==1||($m['application']??'')!=='Licora'){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest protocol/application is not supported.',400);}
        $version=(string)($m['version']??'');
        if($version!==$expectedVersion||!preg_match('/^\d+\.\d+\.\d+$/',$version)||($m['tag']??'')!=='v'.$version){throw new UpdateException('UPDATE_VERSION_MISMATCH','Update manifest version does not match the selected release.',409);}
        if(!preg_match('/^[a-f0-9]{40}$/i',(string)($m['commit']??''))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest commit identity is invalid.',400);}
        if(($m['channel']??'')!=='stable'){throw new UpdateException('UPDATE_MANIFEST_INVALID','Only the signed stable update channel is supported.',400);}
        $minimumUpdater=(string)($m['minimum_updater']??'');$minimumPhp=(string)($m['minimum_php']??'');
        if(!preg_match('/^\d+\.\d+\.\d+$/',$minimumUpdater)||!preg_match('/^\d+\.\d+(?:\.\d+)?$/',$minimumPhp)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest compatibility versions are invalid.',400);}
        if(!preg_match('/^\d+\.\d+\.\d+$/',$currentVersion)){throw new UpdateException('UPDATE_SOURCE_VERSION_INVALID','Installed Licora version is not a supported semantic version.',409);}
        $upgradeFrom=$m['upgrade_from']??null;if(!is_array($upgradeFrom)||$upgradeFrom===[]){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest is missing its supported source-version contract.',400);}
        $seenSources=[];foreach($upgradeFrom as $sourceVersion){$sourceVersion=(string)$sourceVersion;if(!preg_match('/^\d+\.\d+\.\d+$/',$sourceVersion)||isset($seenSources[$sourceVersion])){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains an invalid or duplicate source version.',400);}$seenSources[$sourceVersion]=true;}
        if(!isset($seenSources[$currentVersion])){throw new UpdateException('UPDATE_SOURCE_VERSION_UNSUPPORTED','This release does not support a direct update from Licora '.$currentVersion.'. Install a supported intermediate release first.',409);}

        $pkg=$m['package']??null;
        if(!is_array($pkg)||($pkg['name']??'')!=='Licora-'.$version.'.zip'||!preg_match('/^[a-f0-9]{64}$/i',(string)($pkg['sha256']??''))||(int)($pkg['size']??0)<1){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update package metadata is invalid.',400);}
        $files=$m['files']??null;
        if(!is_array($files)||$files===[]){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains no source file inventory.',400);}
        $seenFileFold=[];
        foreach($files as $path=>$hash){
            $path=(string)$path;self::assertPath($path);
            if(self::isProtected($path)){throw new UpdateException('UPDATE_PROTECTED_PATH','Update manifest attempts to overwrite protected deployment data.',400);}
            $fold=strtolower($path);if(isset($seenFileFold[$fold])){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains case-colliding source paths.',400);}$seenFileFold[$fold]=$path;
            if(!preg_match('/^[a-f0-9]{64}$/i',(string)$hash)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains an invalid file checksum.',400);}
        }

        $deleteFiles=$m['delete_files']??[];if(!is_array($deleteFiles)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update deletion metadata is invalid.',400);}
        $seenDeletes=[];$seenDeleteFold=[];
        foreach($deleteFiles as $path){
            if(!is_string($path)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update deletion metadata contains a non-string path.',400);}
            self::assertPath($path);$fold=strtolower($path);
            if(isset($seenDeletes[$path])||isset($files[$path])||isset($seenDeleteFold[$fold])||isset($seenFileFold[$fold])){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update deletion list contains a duplicate, case-colliding, or packaged file path.',400);}
            $seenDeletes[$path]=true;$seenDeleteFold[$fold]=$path;
            if(self::isProtected($path)){throw new UpdateException('UPDATE_PROTECTED_PATH','Update manifest attempts to delete protected deployment data.',400);}
            if(self::isCriticalControlPath($path)){throw new UpdateException('UPDATE_CONTROL_DELETE_REJECTED','Updater control files cannot be deleted by protocol v1 releases.',409);}
        }

        $migrations=$m['migrations']??[];
        if(!is_array($migrations)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration metadata list is invalid.',400);}
        $seenMigrationIds=[];
        foreach($migrations as $migration){
            if(!is_array($migration)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration metadata is invalid.',400);}
            $id=(string)($migration['id']??'');$path=(string)($migration['path']??'');
            if(!preg_match('/^[A-Za-z0-9._:-]{3,190}$/',$id)||isset($seenMigrationIds[$id])){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration ID is invalid or duplicated.',400);}$seenMigrationIds[$id]=true;
            self::assertPath($path);
            if(!isset($files[$path])||!hash_equals(strtolower((string)$files[$path]),strtolower((string)($migration['checksum']??'')))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration checksum does not match the source inventory.',400);}
            $destructive=!empty($migration['destructive']);$idempotent=!empty($migration['idempotent']);
            if(!$destructive&&!$idempotent){throw new UpdateException('UPDATE_MANIFEST_INVALID','Non-destructive migrations must be explicitly idempotent.',400);}
            if($destructive&&empty($migration['rollback_path'])){throw new UpdateException('UPDATE_ROLLBACK_REQUIRED','Destructive migrations require a signed rollback path.',409);}
            if(!empty($migration['rollback_path'])){
                $rp=(string)$migration['rollback_path'];self::assertPath($rp);
                if(!isset($files[$rp])||!hash_equals(strtolower((string)$files[$rp]),strtolower((string)($migration['rollback_checksum']??'')))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration rollback checksum does not match the source inventory.',400);}
            }
        }
    }

    public static function assertPath(string $path): void
    {
        if($path===''||strlen($path)>400||str_contains($path,"\0")||str_contains($path,'\\')||str_starts_with($path,'/')||preg_match('/^[A-Za-z]:/',$path)){throw new UpdateException('UPDATE_PATH_REJECTED','Update manifest contains an unsafe path.',400);} $parts=explode('/',$path); foreach($parts as $part){if($part===''||$part==='.'||$part==='..'){throw new UpdateException('UPDATE_PATH_REJECTED','Update manifest contains an unsafe path.',400);}}
    }
    public static function isProtected(string $path): bool
    {
        if(in_array($path,self::PROTECTED_EXACT,true)){return true;} if(str_starts_with($path,'includes/.licora-updater/')){return true;} if($path==='.env'||str_starts_with($path,'.env.')){return true;} foreach(['logs/','backups/','exports/','.git/'] as $prefix){if(str_starts_with($path,$prefix)){return true;}} return false;
    }

    private static function isCriticalControlPath(string $path): bool
    {
        return $path==='includes/config.php'
            || $path==='admin/updates.php'
            || str_starts_with($path,'includes/updater/')
            || str_starts_with($path,'admin/ajax/update-')
            || str_starts_with($path,'admin/assets/js/licora-updater')
            || str_starts_with($path,'admin/assets/css/licora-updater');
    }
}
