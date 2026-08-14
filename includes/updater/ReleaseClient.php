<?php
declare(strict_types=1);

final class ReleaseClient
{
    private const OFFICIAL_REPOSITORY = 'vibtools/Licora';
    private HttpClient $http;
    private string $repository;
    public function __construct(HttpClient $http,?string $repository=null)
    {
        $this->http=$http; $this->repository=$repository ?: (defined('LICORA_UPDATE_REPOSITORY')?LICORA_UPDATE_REPOSITORY:self::OFFICIAL_REPOSITORY);
        if($this->repository!==self::OFFICIAL_REPOSITORY){throw new UpdateException('UPDATE_REPOSITORY_INVALID','Licora updater is locked to the official vibtools/Licora release repository.',500);}
    }
    public function latest(): array
    {
        $url='https://api.github.com/repos/'.$this->repository.'/releases/latest'; $headers=[]; $token=getenv('LICORA_GITHUB_TOKEN'); if($token!==false&&trim($token)!==''){$headers[]='Authorization: Bearer '.trim($token);}
        $json=$this->http->get($url,$headers,2097152); $data=json_decode($json,true); if(!is_array($data)){throw new UpdateException('RELEASE_METADATA_INVALID','GitHub release metadata was invalid.',502);} if(!empty($data['draft'])||!empty($data['prerelease'])){throw new UpdateException('RELEASE_NOT_STABLE','Latest GitHub release is not a stable release.',409);} $tag=(string)($data['tag_name']??''); if(!preg_match('/^v(\d+\.\d+\.\d+)$/',$tag,$m)){throw new UpdateException('RELEASE_TAG_INVALID','Latest GitHub release does not use a supported semantic version tag.',502);}        
        return ['version'=>$m[1],'tag'=>$tag,'name'=>(string)($data['name']??$tag),'body'=>(string)($data['body']??''),'published_at'=>(string)($data['published_at']??''),'html_url'=>(string)($data['html_url']??''),'assets'=>is_array($data['assets']??null)?$data['assets']:[]];
    }
    public function asset(array $release,string $name): array
    {
        foreach($release['assets']??[] as $asset){if((string)($asset['name']??'')===$name){$url=(string)($asset['browser_download_url']??''); if($url===''){break;} return ['name'=>$name,'url'=>$url,'size'=>(int)($asset['size']??0),'digest'=>(string)($asset['digest']??'')];}}
        throw new UpdateException('RELEASE_ASSET_MISSING','Required release asset is missing: '.$name,502);
    }
}
