<?php
declare(strict_types=1);

final class ArchiveValidator
{
    public function extract(string $zipPath,string $version,array $manifest,string $jobUuid,?UpdateLogger $logger=null): array
    {
        if(!class_exists('ZipArchive')){throw new UpdateException('ZIP_EXTENSION_MISSING','ZipArchive is required to install updates.',500);}
        if(!is_file($zipPath)){throw new UpdateException('UPDATE_PACKAGE_MISSING','Downloaded update package is missing.',500);}
        $zip=new ZipArchive();
        if($zip->open($zipPath)!==true){throw new UpdateException('UPDATE_ZIP_INVALID','Downloaded update package is not a readable ZIP archive.',400);}
        $prefix='Licora-'.$version.'/';$seen=[];$seenFold=[];
        try{
            for($i=0;$i<$zip->numFiles;$i++){
                $name=(string)$zip->getNameIndex($i);
                if($name===''||!str_starts_with($name,$prefix)){throw new UpdateException('UPDATE_ZIP_ROOT_INVALID','Update archive root does not match the release version.',400);}
                $relative=substr($name,strlen($prefix));
                if($relative===''){continue;}
                $isDirectory=str_ends_with($relative,'/');
                $path=$isDirectory?rtrim($relative,'/'):$relative;
                ManifestVerifier::assertPath($path);
                if(ManifestVerifier::isProtected($path)||ManifestVerifier::isProtected($relative)){throw new UpdateException('UPDATE_PROTECTED_PATH','Update package contains protected deployment data.',400);}

                $opsys=0;$attr=0;$type=0;
                if(method_exists($zip,'getExternalAttributesIndex')&&$zip->getExternalAttributesIndex($i,$opsys,$attr)){
                    $type=($attr>>16)&0170000;
                    if($type===0120000){throw new UpdateException('UPDATE_ZIP_SYMLINK','Update package contains a symbolic link.',400);}
                    if($type!==0&&$type!==0100000&&$type!==0040000){throw new UpdateException('UPDATE_ZIP_SPECIAL_FILE','Update package contains an unsupported special filesystem entry.',400);}
                    if($isDirectory&&$type!==0&&$type!==0040000){throw new UpdateException('UPDATE_ZIP_ENTRY_TYPE','Update package directory metadata is inconsistent.',400);}
                    if(!$isDirectory&&$type===0040000){throw new UpdateException('UPDATE_ZIP_ENTRY_TYPE','Update package file metadata is inconsistent.',400);}
                }
                if($isDirectory){continue;}

                if(!array_key_exists($path,$manifest['files'])){throw new UpdateException('UPDATE_ZIP_EXTRA_FILE','Update package contains a file not declared by the signed manifest: '.$path,400);}
                $fold=strtolower($path);
                if(isset($seen[$path])||isset($seenFold[$fold])){throw new UpdateException('UPDATE_ZIP_DUPLICATE_FILE','Update package contains a duplicate or case-colliding path.',400);}
                $seen[$path]=true;$seenFold[$fold]=$path;
            }
            if(count($seen)!==count($manifest['files'])){throw new UpdateException('UPDATE_ZIP_INCOMPLETE','Update package file inventory does not match the signed manifest.',400);}
            $jobDir=UpdateRuntime::ensureJob($jobUuid);$stagingParent=$jobDir.'/staging';self::removeTree($stagingParent);
            if(!@mkdir($stagingParent,0700,true)&&!is_dir($stagingParent)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Update staging directory could not be created.',500);}
            if(!$zip->extractTo($stagingParent)){throw new UpdateException('UPDATE_ZIP_EXTRACT_FAILED','Update package could not be extracted to staging.',500);}
        }finally{$zip->close();}
        $root=rtrim($stagingParent.'/'.$prefix,'/');
        if(!is_dir($root)){throw new UpdateException('UPDATE_ZIP_EXTRACT_FAILED','Extracted update root is missing.',500);}
        foreach($manifest['files'] as $path=>$expected){
            $full=$root.'/'.$path;
            if(is_link($full)||!is_file($full)||!hash_equals(strtolower((string)$expected),strtolower((string)hash_file('sha256',$full)))){throw new UpdateException('UPDATE_FILE_HASH_MISMATCH','Staged file checksum mismatch: '.$path,400);}
        }
        if($logger){$logger->success('stage_archive','ARCHIVE_VERIFIED','Release archive extracted and every file checksum matched the signed manifest.',['files'=>count($manifest['files'])]);}
        return ['staging_root'=>$root,'files'=>count($manifest['files'])];
    }

    public static function removeTree(string $path): void
    {
        if(!file_exists($path)&&!is_link($path)){return;}
        if(is_link($path)||is_file($path)){@unlink($path);return;}
        $items=scandir($path);if($items===false){return;}
        foreach($items as $item){if($item==='.'||$item==='..'){continue;}self::removeTree($path.'/'.$item);}
        @rmdir($path);
    }
}
