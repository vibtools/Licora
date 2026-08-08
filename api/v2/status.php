<?php
declare(strict_types=1);
require_once '../../includes/v2/bootstrap.php';
try {
    ApiV2::requirePost(); ApiV2::requireHttps();
    [$repository, $tokens] = licora_v2_services();
    [$data, $raw] = ApiV2::readJson(); ApiV2::assertFields($data, [], []);
    ApiV2::rateLimit('v2.status.ip', ApiV2::settingInt('LICENSE_V2_RATE_LIMIT', 300, 10, 100000));
    [$claims, $context] = licora_v2_verify_access_request($repository, $tokens, $raw);
    licora_v2_rate_limits(['app_id'=>$claims['app_id'],'rate_limit_per_hour'=>(int)$context['rate_limit_per_hour']], 'status', (string)$claims['device_id'], false);
    $repository->audit('status_success', (string)$claims['app_id'], (int)$claims['license_id'], (int)$context['id'], ApiV2::requestId());
    ApiV2::respond(200, true, 'OK', 'License is active.', [
        'license'=>['status'=>(string)$context['license_status'],'expires_at'=>(string)$context['license_expires_at']],
        'device'=>['device_id'=>(string)$claims['device_id'],'status'=>(string)$context['status']],
        'app_id'=>(string)$claims['app_id'],
    ]);
} catch (Throwable $e) {
    if (isset($repository) && $repository instanceof V2Repository) {
        $repository->audit('status_failed', isset($claims['app_id']) ? (string)$claims['app_id'] : null, isset($claims['license_id']) ? (int)$claims['license_id'] : null, isset($context['id']) ? (int)$context['id'] : null, ApiV2::requestId(), ['reason'=>$e instanceof V2Exception ? $e->machineCode() : 'INTERNAL_ERROR']);
    }
    ApiV2::handle($e);
}
