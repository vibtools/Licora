<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/auth.php';
require_once dirname(__DIR__,2).'/includes/security.php';
require_once dirname(__DIR__,2).'/includes/admin_helpers.php';
require_once dirname(__DIR__,2).'/includes/updater/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

function updater_json(int $status,array $payload): void { http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($payload,JSON_UNESCAPED_SLASHES);exit; }
function updater_fail(Throwable $e): void {
    if($e instanceof UpdateException){updater_json($e->httpStatus(),['success'=>false,'code'=>$e->errorCode(),'message'=>$e->getMessage()]);}
    error_log('Updater endpoint failure: '.get_class($e).' '.$e->getMessage());
    updater_json(500,['success'=>false,'code'=>'UPDATE_INTERNAL_ERROR','message'=>'Updater request could not be completed. Review server diagnostics.']);
}
function updater_boot(bool $requireCsrf=false): array {
    $auth=new Auth(); if(!$auth->isAdminLoggedIn()){updater_json(401,['success'=>false,'code'=>'AUTH_REQUIRED','message'=>'Administrator login is required.']);}
    if(!AdminHelpers::canDelete()){updater_json(403,['success'=>false,'code'=>'SUPER_ADMIN_REQUIRED','message'=>'Super Admin permission is required for application updates.']);}
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    if($requireCsrf && $method!=='POST'){header('Allow: POST');updater_json(405,['success'=>false,'code'=>'METHOD_NOT_ALLOWED','message'=>'This updater action requires POST.']);}
    $data=[];
    if($method==='POST'){$raw=file_get_contents('php://input');$decoded=json_decode((string)$raw,true);if(is_array($decoded)){$data=$decoded;}}
    if($requireCsrf){$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($data['csrf_token']??''));if(!Security::validateCSRFToken($token)){updater_json(403,['success'=>false,'code'=>'CSRF_FAILED','message'=>'Invalid CSRF token.']);}}
    try{[$service,$repo,$schema]=licora_updater_services();return [$service,$repo,$schema,$auth,$data];}catch(Throwable $e){updater_fail($e);}
}
