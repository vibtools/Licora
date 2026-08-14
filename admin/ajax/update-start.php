<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service,$repo,$schema,$auth,$data]=updater_boot(true);$version=trim((string)($data['target_version']??''));$job=$service->start($version,(int)($_SESSION['admin_id']??0));updater_json(201,['success'=>true,'job'=>$job]);}catch(Throwable $e){updater_fail($e);}
