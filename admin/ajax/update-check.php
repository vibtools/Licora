<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{
    $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    [$service,$repo,$schema,$auth,$data]=updater_boot($post);
    $force=$post && !empty($data['force']);
    updater_json(200,['success'=>true,'data'=>$service->checkForUpdates($force),'active_job'=>$service->active()]);
}catch(Throwable $e){updater_fail($e);}
