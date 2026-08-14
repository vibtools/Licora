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

    public function verify(string $manifestJson,string $signature,string $expectedVersion): array
    {
        if(!is_readable($this->publicKeyPath)){throw new UpdateException('UPDATE_PUBLIC_KEY_MISSING','Update verification public key is missing.',500);} $key=openssl_pkey_get_public((string)file_get_contents($this->publicKeyPath)); if($key===false){throw new UpdateException('UPDATE_PUBLIC_KEY_INVALID','Update verification public key is invalid.',500);} $verified=openssl_verify($manifestJson,$signature,$key,OPENSSL_ALGO_SHA256); if($verified!==1){throw new UpdateException('UPDATE_SIGNATURE_INVALID','Update manifest signature verification failed.',400);} $manifest=json_decode($manifestJson,true); if(!is_array($manifest)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest JSON is invalid.',400);} $this->validate($manifest,$expectedVersion); return $manifest;
    }

    public function validate(array $m,string $expectedVersion): void
    {
        if((int)($m['protocol_version']??0)!==1||($m['application']??'')!=='Licora'){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest protocol/application is not supported.',400);} $version=(string)($m['version']??''); if($version!==$expectedVersion||!preg_match('/^\d+\.\d+\.\d+$/',$version)||($m['tag']??'')!=='v'.$version){throw new UpdateException('UPDATE_VERSION_MISMATCH','Update manifest version does not match the selected release.',409);} if(!preg_match('/^[a-f0-9]{40}$/i',(string)($m['commit']??''))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest commit identity is invalid.',400);}        
        $pkg=$m['package']??null; if(!is_array($pkg)||($pkg['name']??'')!=='Licora-'.$version.'.zip'||!preg_match('/^[a-f0-9]{64}$/i',(string)($pkg['sha256']??''))||(int)($pkg['size']??0)<1){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update package metadata is invalid.',400);} if(!is_array($m['files']??null)||($m['files']??[])===[]){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains no source file inventory.',400);} foreach($m['files'] as $path=>$hash){self::assertPath((string)$path);if(self::isProtected((string)$path)){throw new UpdateException('UPDATE_PROTECTED_PATH','Update manifest attempts to overwrite protected deployment data.',400);}if(!preg_match('/^[a-f0-9]{64}$/i',(string)$hash)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update manifest contains an invalid file checksum.',400);}}
        $deleteFiles=$m['delete_files']??[];if(!is_array($deleteFiles)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update deletion metadata is invalid.',400);}$seenDeletes=[];foreach($deleteFiles as $path){$path=(string)$path;self::assertPath($path);if(isset($seenDeletes[$path])||isset($m['files'][$path])){throw new UpdateException('UPDATE_MANIFEST_INVALID','Update deletion list contains a duplicate or packaged file path.',400);}$seenDeletes[$path]=true;if(self::isProtected($path)){throw new UpdateException('UPDATE_PROTECTED_PATH','Update manifest attempts to delete protected deployment data.',400);}}
        foreach(($m['migrations']??[]) as $migration){if(!is_array($migration)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration metadata is invalid.',400);} $id=(string)($migration['id']??'');$path=(string)($migration['path']??'');if(!preg_match('/^[A-Za-z0-9._:-]{3,190}$/',$id)){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration ID is invalid.',400);}self::assertPath($path);if(!isset($m['files'][$path])||!hash_equals((string)$m['files'][$path],(string)($migration['checksum']??''))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration checksum does not match the source inventory.',400);}if(!empty($migration['destructive'])&&empty($migration['rollback_path'])){throw new UpdateException('UPDATE_ROLLBACK_REQUIRED','Destructive migrations require a signed rollback path.',409);}if(!empty($migration['rollback_path'])){$rp=(string)$migration['rollback_path'];self::assertPath($rp);if(!isset($m['files'][$rp])||!hash_equals((string)$m['files'][$rp],(string)($migration['rollback_checksum']??''))){throw new UpdateException('UPDATE_MANIFEST_INVALID','Migration rollback checksum does not match the source inventory.',400);}}}
    }

    public static function assertPath(string $path): void
    {
        if($path===''||strlen($path)>400||str_contains($path,"\0")||str_contains($path,'\\')||str_starts_with($path,'/')||preg_match('/^[A-Za-z]:/',$path)){throw new UpdateException('UPDATE_PATH_REJECTED','Update manifest contains an unsafe path.',400);} $parts=explode('/',$path); foreach($parts as $part){if($part===''||$part==='.'||$part==='..'){throw new UpdateException('UPDATE_PATH_REJECTED','Update manifest contains an unsafe path.',400);}}
    }
    public static function isProtected(string $path): bool
    {
        if(in_array($path,self::PROTECTED_EXACT,true)){return true;} if(str_starts_with($path,'includes/.licora-updater/')){return true;} if($path==='.env'||str_starts_with($path,'.env.')){return true;} foreach(['logs/','backups/','exports/','.git/'] as $prefix){if(str_starts_with($path,$prefix)){return true;}} return false;
    }
}
