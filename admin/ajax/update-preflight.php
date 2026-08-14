<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service,$repo,$schema,$auth,$data]=updater_boot(true);$version=trim((string)($data['target_version']??''));updater_json(200,['success'=>true,'data'=>$service->previewPreflight($version)]);}catch(Throwable $e){updater_fail($e);}
