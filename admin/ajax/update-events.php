<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service]=updater_boot(false);$uuid=trim((string)($_GET['job']??''));$after=max(0,(int)($_GET['after']??0));updater_json(200,['success'=>true]+$service->events($uuid,$after));}catch(Throwable $e){updater_fail($e);}
