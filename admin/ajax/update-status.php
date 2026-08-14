<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service]=updater_boot(false);$uuid=trim((string)($_GET['job']??''));$payload=$uuid!==''?['job'=>$service->status($uuid)]:['active_job'=>$service->active(),'history'=>$service->history(20),'update'=>$service->checkForUpdates(false)];updater_json(200,['success'=>true]+$payload);}catch(Throwable $e){updater_fail($e);}
