<?php
declare(strict_types=1);

final class HttpClient
{
    private array $allowedHosts;
    private int $timeout;
    public function __construct(int $timeout=20)
    {
        $this->timeout=max(5,min(120,$timeout));
        $this->allowedHosts=['api.github.com','github.com','objects.githubusercontent.com','release-assets.githubusercontent.com','github-releases.githubusercontent.com'];
    }

    public function get(string $url,array $headers=[],int $maxBytes=5242880): string
    {
        $this->assertUrl($url);
        if (function_exists('curl_init')) { return $this->curlGet($url,$headers,$maxBytes); }
        if ((bool)ini_get('allow_url_fopen')) { return $this->streamGet($url,$headers,$maxBytes); }
        throw new UpdateException('HTTP_TRANSPORT_UNAVAILABLE','Neither cURL nor HTTPS stream transport is available.',500);
    }

    public function download(string $url,string $destination,array $headers=[],int $maxBytes=104857600): array
    {
        $this->assertUrl($url);
        $dir=dirname($destination); if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Updater storage directory could not be created.',500);}
        $tmp=$destination.'.part'; @unlink($tmp);
        if (function_exists('curl_init')) {
            $current=$url;
            for($redirect=0;$redirect<=5;$redirect++){
                $this->assertUrl($current);
                $fp=@fopen($tmp,'wb'); if(!$fp){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Update download file could not be opened.',500);}
                $bytes=0;$location='';$tooLarge=false;
                $ch=curl_init($current);
                $reqHeaders=array_merge(['Accept: application/octet-stream','User-Agent: Licora-Updater/'.(defined('APP_VERSION')?APP_VERSION:'unknown')],$headers);
                curl_setopt_array($ch,[
                    CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$this->timeout,
                    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$reqHeaders,CURLOPT_FILE=>$fp,
                    CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$location){if(stripos($line,'Location:')===0){$location=trim(substr($line,9));}return strlen($line);},
                    CURLOPT_NOPROGRESS=>false,
                    CURLOPT_PROGRESSFUNCTION=>static function($resource,$downloadSize,$downloaded) use (&$bytes,&$tooLarge,$maxBytes){$bytes=(int)$downloaded;if($downloaded>$maxBytes){$tooLarge=true;return 1;}return 0;},
                ]);
                $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);fclose($fp);
                if($tooLarge||(is_file($tmp)&&(int)filesize($tmp)>$maxBytes)){@unlink($tmp);throw new UpdateException('DOWNLOAD_TOO_LARGE','Update asset exceeds the configured size limit.',413);}
                if(in_array($status,[301,302,303,307,308],true)){
                    @unlink($tmp);
                    if($location===''){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);}
                    $current=$this->resolveRedirect($current,$location);
                    continue;
                }
                if(!$ok||$status<200||$status>=300){@unlink($tmp);error_log('Licora updater cURL download failed: '.$err);throw new UpdateException('DOWNLOAD_FAILED','Update asset download failed.'.($status?' HTTP '.$status:''),502,null);}
                if(!@rename($tmp,$destination)){@unlink($tmp);throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Downloaded update could not be finalized.',500);}
                return ['path'=>$destination,'size'=>(int)filesize($destination),'sha256'=>hash_file('sha256',$destination)];
            }
            @unlink($tmp);throw new UpdateException('HTTP_REDIRECT_LIMIT','Too many redirects while downloading update data.',502);
        }
        return $this->streamDownload($url,$destination,$tmp,array_merge(['Accept: application/octet-stream'],$headers),$maxBytes);
    }

    private function curlGet(string $url,array $headers,int $maxBytes): string
    {
        $current=$url;
        for($redirect=0;$redirect<=5;$redirect++){
            $this->assertUrl($current);$body='';$location='';$tooLarge=false;
            $ch=curl_init($current);
            $reqHeaders=array_merge(['Accept: application/vnd.github+json','User-Agent: Licora-Updater/'.(defined('APP_VERSION')?APP_VERSION:'unknown')],$headers);
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$this->timeout,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$reqHeaders,
                CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$location){if(stripos($line,'Location:')===0){$location=trim(substr($line,9));}return strlen($line);},
                CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use (&$body,&$tooLarge,$maxBytes){if(strlen($body)+strlen($chunk)>$maxBytes){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);},
            ]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
            if($tooLarge){throw new UpdateException('HTTP_RESPONSE_TOO_LARGE','GitHub response exceeded the allowed size.',413);}
            if(in_array($status,[301,302,303,307,308],true)){
                if($location===''){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);}
                $current=$this->resolveRedirect($current,$location);continue;
            }
            if(!$ok||$status<200||$status>=300){error_log('Licora updater cURL request failed: '.$err);throw new UpdateException('HTTP_REQUEST_FAILED','GitHub request failed.'.($status?' HTTP '.$status:''),502);}return $body;
        }
        throw new UpdateException('HTTP_REDIRECT_LIMIT','Too many redirects while downloading update data.',502);
    }

    private function streamDownload(string $url,string $destination,string $tmp,array $headers,int $maxBytes): array
    {
        $current=$url;
        for($redirect=0;$redirect<=5;$redirect++){
            $this->assertUrl($current);
            $headerLines=array_merge(['Accept: application/octet-stream','User-Agent: Licora-Updater/'.(defined('APP_VERSION')?APP_VERSION:'unknown')],$headers);
            $context=stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$headerLines),'timeout'=>$this->timeout,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $remote=@fopen($current,'rb',false,$context);
            $resp=$http_response_header??[];$status=0;if(isset($resp[0])&&preg_match('/\s(\d{3})\s/',$resp[0],$m)){$status=(int)$m[1];}
            if(in_array($status,[301,302,303,307,308],true)){
                if(is_resource($remote)){fclose($remote);}
                $location='';foreach($resp as $line){if(stripos($line,'Location:')===0){$location=trim(substr($line,9));break;}}
                if($location===''){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);}
                $current=$this->resolveRedirect($current,$location);continue;
            }
            if(!is_resource($remote)||$status<200||$status>=300){if(is_resource($remote)){fclose($remote);}throw new UpdateException('DOWNLOAD_FAILED','Update asset download failed.'.($status?' HTTP '.$status:''),502);}
            $local=@fopen($tmp,'wb');if($local===false){fclose($remote);throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Update download file could not be opened.',500);}
            $bytes=0;
            try{
                while(!feof($remote)){
                    $chunk=fread($remote,65536);
                    if($chunk===false){throw new UpdateException('DOWNLOAD_FAILED','Update asset stream could not be read.',502);}
                    if($chunk===''){
                        $meta=stream_get_meta_data($remote);
                        if(!empty($meta['timed_out'])){throw new UpdateException('DOWNLOAD_FAILED','Update asset download timed out.',502);}
                        if(!feof($remote)){usleep(10000);}
                        continue;
                    }
                    $bytes+=strlen($chunk);
                    if($bytes>$maxBytes){throw new UpdateException('DOWNLOAD_TOO_LARGE','Update asset exceeds the configured size limit.',413);}
                    $written=fwrite($local,$chunk);
                    if($written===false||$written!==strlen($chunk)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Downloaded update could not be written completely.',500);}
                }
                if(!fflush($local)){throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Downloaded update could not be flushed to storage.',500);}
            }catch(Throwable $e){
                fclose($remote);fclose($local);@unlink($tmp);throw $e;
            }
            fclose($remote);fclose($local);
            if(!@rename($tmp,$destination)){@unlink($tmp);throw new UpdateException('UPDATE_STORAGE_UNWRITABLE','Downloaded update could not be finalized.',500);}
            $size=@filesize($destination);$hash=@hash_file('sha256',$destination);
            if($size===false||!is_string($hash)){@unlink($destination);throw new UpdateException('DOWNLOAD_FAILED','Downloaded update could not be verified on disk.',500);}
            return ['path'=>$destination,'size'=>(int)$size,'sha256'=>$hash];
        }
        @unlink($tmp);throw new UpdateException('HTTP_REDIRECT_LIMIT','Too many redirects while downloading update data.',502);
    }

    private function streamGet(string $url,array $headers,int $maxBytes): string
    {
        $current=$url;
        for($redirect=0;$redirect<=5;$redirect++){
            $this->assertUrl($current);
            $headerLines=array_merge(['Accept: application/vnd.github+json','User-Agent: Licora-Updater/'.(defined('APP_VERSION')?APP_VERSION:'unknown')],$headers);
            $context=stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$headerLines), 'timeout'=>$this->timeout,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $data=@file_get_contents($current,false,$context,0,$maxBytes+1); $resp=$http_response_header??[]; $status=0; if(isset($resp[0])&&preg_match('/\s(\d{3})\s/',$resp[0],$m)){$status=(int)$m[1];}
            if(in_array($status,[301,302,303,307,308],true)){$location='';foreach($resp as $line){if(stripos($line,'Location:')===0){$location=trim(substr($line,9));break;}}if($location===''){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);} $current=$this->resolveRedirect($current,$location);continue;}
            if($data===false||$status<200||$status>=300){throw new UpdateException('HTTP_REQUEST_FAILED','GitHub request failed.'.($status?' HTTP '.$status:''),502);} if(strlen($data)>$maxBytes){throw new UpdateException('HTTP_RESPONSE_TOO_LARGE','GitHub response exceeded the allowed size.',413);} return $data;
        }
        throw new UpdateException('HTTP_REDIRECT_LIMIT','Too many redirects while downloading update data.',502);
    }

    private function resolveRedirect(string $base,string $location): string
    {
        $location=trim($location);
        if($location===''){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);}
        $parts=parse_url($location);
        if(is_array($parts)&&isset($parts['scheme'])){$this->assertUrl($location);return $location;}
        $baseParts=parse_url($base);if(!is_array($baseParts)||empty($baseParts['scheme'])||empty($baseParts['host'])){throw new UpdateException('HTTP_REDIRECT_INVALID','GitHub returned an invalid redirect.',502);}
        if(str_starts_with($location,'//')){$candidate=$baseParts['scheme'].':'.$location;}
        elseif(str_starts_with($location,'/')){$candidate=$baseParts['scheme'].'://'.$baseParts['host'].(isset($baseParts['port'])?':'.$baseParts['port']:'').$location;}
        else{throw new UpdateException('HTTP_REDIRECT_INVALID','Updater refused an unsupported relative redirect.',502);}
        $this->assertUrl($candidate);return $candidate;
    }

    private function assertUrl(string $url): void
    {
        $parts=parse_url($url); if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])){throw new UpdateException('UPDATE_URL_REJECTED','Updater refused a non-HTTPS or invalid release URL.',400);} $host=strtolower((string)$parts['host']);
        $allowed=in_array($host,$this->allowedHosts,true)||str_ends_with($host,'.githubusercontent.com')||str_ends_with($host,'.github.com'); if(!$allowed){throw new UpdateException('UPDATE_URL_REJECTED','Updater refused a release URL outside the trusted GitHub host set.',400);}
        if(isset($parts['user'])||isset($parts['pass'])){throw new UpdateException('UPDATE_URL_REJECTED','Updater release URLs may not contain credentials.',400);}
    }
}
