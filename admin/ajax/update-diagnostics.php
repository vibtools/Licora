<?php
declare(strict_types=1);
require_once __DIR__.'/update-bootstrap.php';
try{[$service]=updater_boot(false);$uuid=trim((string)($_GET['job']??''));$text=$service->diagnostics($uuid);header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="licora-update-diagnostics-'.preg_replace('/[^A-Za-z0-9-]/','',$uuid).'.txt"');echo $text;exit;}catch(Throwable $e){updater_fail($e);}
