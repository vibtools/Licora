<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service,$repo,$schema,$auth,$data]=updater_boot(true);$uuid=trim((string)($data['job_uuid']??''));$job=$service->requestRollback($uuid,(int)($_SESSION['admin_id']??0));updater_json(200,['success'=>true,'job'=>$job]);}catch(Throwable $e){updater_fail($e);}
