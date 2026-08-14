<?php
declare(strict_types=1);

final class UpdateLogger
{
    private UpdateRepository $repo;
    private string $jobUuid;
    public function __construct(UpdateRepository $repo,string $jobUuid){$this->repo=$repo;$this->jobUuid=$jobUuid;}
    public function event(string $level,string $stage,string $code,string $message,array $context=[]): int
    {
        try {
            return $this->repo->appendEvent($this->jobUuid,$level,$stage,$code,$message,$context);
        } catch (Throwable $e) {
            // Update event telemetry must never be allowed to strand or corrupt the
            // update state machine. Persisted job state remains authoritative.
            error_log('Licora updater event write failed ['.$this->jobUuid.'] '.$code.': '.get_class($e).' '.$e->getMessage());
            return 0;
        }
    }
    public function info(string $stage,string $code,string $message,array $context=[]): int {return $this->event('info',$stage,$code,$message,$context);}    
    public function debug(string $stage,string $code,string $message,array $context=[]): int {return $this->event('debug',$stage,$code,$message,$context);}    
    public function warning(string $stage,string $code,string $message,array $context=[]): int {return $this->event('warning',$stage,$code,$message,$context);}    
    public function success(string $stage,string $code,string $message,array $context=[]): int {return $this->event('success',$stage,$code,$message,$context);}    
    public function error(string $stage,string $code,string $message,array $context=[]): int {return $this->event('error',$stage,$code,$message,$context);}    
}
