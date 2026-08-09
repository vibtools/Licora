<?php
declare(strict_types=1);
require_once '../../includes/v2/bootstrap.php';
try {
    ApiV2::requirePost(); ApiV2::requireHttps();
    [$repository, $tokens] = licora_v2_services();
    [$data, $raw] = ApiV2::readJson();
    ApiV2::assertFields($data, ['refresh_token','app_version'], ['refresh_token','app_version']);
    $refreshToken = trim((string)$data['refresh_token']);
    if (!preg_match('/^[A-Za-z0-9_-]{40,128}$/', $refreshToken)) { throw new V2Exception('INVALID_REFRESH_TOKEN', 'Invalid refresh token.', 401); }
    // The IP bucket is consumed before opening the refresh-token transaction so
    // failed proof attempts cannot roll the rate-limit update back with that transaction.
    ApiV2::rateLimit('v2.refresh.ip', ApiV2::settingInt('LICENSE_V2_RATE_LIMIT', 300, 10, 100000));
    // Consume app/device buckets before opening the refresh-token transaction.
    // Otherwise a later proof failure would roll the rate-limit writes back with
    // that transaction and allow repeated invalid-proof attempts to evade them.
    $rateContext = $repository->refreshRateLimitContext($refreshToken);
    if (is_array($rateContext)) {
        $rateAppId = (string)$rateContext['app_id'];
        $rateDeviceId = (string)$rateContext['device_hash'];
        $rateLimit = max(10, (int)$rateContext['rate_limit_per_hour']);
        ApiV2::rateLimit('v2.refresh.app.' . $rateAppId, $rateLimit);
        ApiV2::rateLimit('v2.refresh.device.' . substr(hash('sha256', $rateDeviceId), 0, 24), $rateLimit);
    }
    $context = $repository->refreshContextForUpdate($refreshToken);
    try {
        $appId = (string)$context['app_id']; $deviceId = (string)$context['device_hash'];
        $version = ApiV2::appVersion($data['app_version']);
        $minimum = trim((string)($context['min_version'] ?? ''));
        if ($minimum !== '' && version_compare($version, $minimum, '<')) { throw new V2Exception('APP_VERSION_UNSUPPORTED', 'Application version is not supported.', 426); }
        [$timestampRaw, $nonce, $signature] = ApiV2::proofHeaders();
        $skew = max(30, (int)($context['clock_skew_seconds'] ?? 300));
        $timestamp = V2DeviceProof::validateRequestMetadata($timestampRaw, $nonce, $skew);
        $canonical = V2DeviceProof::canonical('POST', ApiV2::path(), $timestamp, $nonce, hash('sha256', $raw), 'refresh:' . hash('sha256', $refreshToken));
        V2DeviceProof::verify((string)$context['public_key'], $signature, $canonical);
        $repository->rememberNonce((int)$context['device_credential_id'], $nonce, $skew * 2);
        $newRefresh = $repository->completeRefresh($context);
        $accessTtl = max(60, (int)$context['access_token_ttl']);
        $accessToken = $tokens->issue([
            'app_id'=>$appId,'license_id'=>(int)$context['license_id'],'device_id'=>$deviceId,
            'device_credential_id'=>(int)$context['device_credential_id'],'device_key_fingerprint'=>(string)$context['public_key_fingerprint'],
        ], $accessTtl);
        $repository->audit('refresh_success', $appId, (int)$context['license_id'], (int)$context['device_credential_id'], ApiV2::requestId());
        ApiV2::respond(200, true, 'OK', 'Token refreshed.', [
            'access_token'=>$accessToken,'token_type'=>'Bearer','expires_in'=>$accessTtl,
            'refresh_token'=>$newRefresh['token'],'refresh_expires_at'=>$newRefresh['expires_at'],
        ]);
    } catch (Throwable $inner) { $repository->abortTransaction(); throw $inner; }
} catch (Throwable $e) {
    if (isset($repository) && $repository instanceof V2Repository) {
        $repository->abortTransaction();
        $repository->audit('refresh_failed', isset($appId) ? $appId : null, isset($context['license_id']) ? (int)$context['license_id'] : null, isset($context['device_credential_id']) ? (int)$context['device_credential_id'] : null, ApiV2::requestId(), ['reason'=>$e instanceof V2Exception ? $e->machineCode() : 'INTERNAL_ERROR']);
    }
    ApiV2::handle($e);
}
