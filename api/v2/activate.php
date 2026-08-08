<?php
declare(strict_types=1);
require_once '../../includes/v2/bootstrap.php';
try {
    ApiV2::requirePost(); ApiV2::requireHttps();
    [$repository, $tokens] = licora_v2_services();
    [$data, $raw] = ApiV2::readJson();
    ApiV2::assertFields($data, ['license_key','app_id','app_version','device_id','device_public_key'], ['license_key','app_id','app_version','device_id','device_public_key']);
    $licenseKey = strtoupper(trim((string)$data['license_key']));
    if (!Security::validateLicenseFormat($licenseKey)) { throw new V2Exception('INVALID_LICENSE', 'License is not valid.', 403); }
    $appId = ApiV2::appId($data['app_id']);
    $appVersion = ApiV2::appVersion($data['app_version']);
    $deviceId = ApiV2::deviceId($data['device_id']);
    $publicKey = V2DeviceProof::validatePublicKey((string)$data['device_public_key']);
    $fingerprint = V2DeviceProof::fingerprint($publicKey);
    ApiV2::rateLimit('v2.activate.ip', ApiV2::settingInt('LICENSE_V2_RATE_LIMIT', 300, 10, 100000));
    $app = $repository->clientApp($appId);
    licora_v2_rate_limits($app, 'activate', $deviceId, false);
    [$timestampRaw, $nonce, $signature] = ApiV2::proofHeaders();
    $skew = max(30, (int)($app['clock_skew_seconds'] ?? 300));
    $timestamp = V2DeviceProof::validateRequestMetadata($timestampRaw, $nonce, $skew);
    $canonical = V2DeviceProof::canonical('POST', ApiV2::path(), $timestamp, $nonce, hash('sha256', $raw), 'activate:' . $appId);
    V2DeviceProof::verify($publicKey, $signature, $canonical);
    $activated = $repository->activate($licenseKey, $app, $appVersion, $deviceId, $publicKey, $fingerprint);
    $credentialId = (int)$activated['credential']['id'];
    $repository->rememberNonce($credentialId, $nonce, $skew * 2);
    $accessTtl = max(60, (int)$app['access_token_ttl']);
    $refreshTtl = max(300, (int)$app['refresh_token_ttl']);
    $accessToken = $tokens->issue([
        'app_id'=>$appId,'license_id'=>(int)$activated['license']['id'],'device_id'=>$deviceId,
        'device_credential_id'=>$credentialId,'device_key_fingerprint'=>$fingerprint,
    ], $accessTtl);
    $refresh = $repository->createRefreshToken($credentialId, $refreshTtl);
    $repository->audit('activation_success', $appId, (int)$activated['license']['id'], $credentialId, ApiV2::requestId());
    ApiV2::respond(200, true, 'OK', 'License activated.', [
        'access_token'=>$accessToken,'token_type'=>'Bearer','expires_in'=>$accessTtl,
        'refresh_token'=>$refresh['token'],'refresh_expires_at'=>$refresh['expires_at'],
        'license'=>['status'=>'active','expires_at'=>$activated['license']['expires_at'],'device_limit'=>(int)$activated['license']['device_limit']],
        'device'=>['device_id'=>$deviceId,'public_key_fingerprint'=>$fingerprint],
    ]);
} catch (Throwable $e) {
    if (isset($repository) && $repository instanceof V2Repository) {
        $repository->audit('activation_failed', isset($appId) ? $appId : null, null, null, ApiV2::requestId(), ['reason'=>$e instanceof V2Exception ? $e->machineCode() : 'INTERNAL_ERROR']);
    }
    ApiV2::handle($e);
}
