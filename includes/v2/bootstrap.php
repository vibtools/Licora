<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/security.php';
require_once dirname(__DIR__) . '/functions.php';
require_once __DIR__ . '/V2Exception.php';
require_once __DIR__ . '/V2KeyManager.php';
require_once __DIR__ . '/V2TokenService.php';
require_once __DIR__ . '/V2DeviceProof.php';
require_once __DIR__ . '/ApiV2.php';
require_once __DIR__ . '/V2Repository.php';

function licora_v2_services(): array
{
    $repository = new V2Repository(Database::getInstance());
    $repository->requireSchema();
    if (random_int(1, 100) === 1) { $repository->cleanupExpiredNonces(); }
    $keys = new V2KeyManager();
    $keys->assertPairMatches();
    $skew = ApiV2::settingInt('LICENSE_V2_CLOCK_SKEW', 300, 30, 3600);
    return [$repository, new V2TokenService($keys, $skew), $keys, $skew];
}

function licora_v2_rate_limits(array $app, string $endpoint, ?string $deviceId = null, bool $includeIp = true): void
{
    $global = ApiV2::settingInt('LICENSE_V2_RATE_LIMIT', 300, 10, 100000);
    if ($includeIp) {
        ApiV2::rateLimit('v2.' . $endpoint . '.ip', $global);
    }
    $appLimit = max(10, (int)($app['rate_limit_per_hour'] ?? $global));
    ApiV2::rateLimit('v2.' . $endpoint . '.app.' . (string)$app['app_id'], $appLimit);
    if ($deviceId !== null && $deviceId !== '') {
        ApiV2::rateLimit('v2.' . $endpoint . '.device.' . substr(hash('sha256', $deviceId), 0, 24), $appLimit);
    }
}

function licora_v2_verify_access_request(V2Repository $repository, V2TokenService $tokens, string $rawBody): array
{
    $token = ApiV2::bearerToken();
    $claims = $tokens->verify($token);
    $context = $repository->accessContext($claims);
    [$timestampRaw, $nonce, $signature] = ApiV2::proofHeaders();
    $skew = max(30, (int)($context['clock_skew_seconds'] ?? 300));
    $timestamp = V2DeviceProof::validateRequestMetadata($timestampRaw, $nonce, $skew);
    $canonical = V2DeviceProof::canonical(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'POST'), ApiV2::path(), $timestamp, $nonce,
        hash('sha256', $rawBody), (string)$claims['jti']
    );
    V2DeviceProof::verify((string)$context['public_key'], $signature, $canonical);
    $repository->rememberNonce((int)$context['id'], $nonce, $skew * 2);
    return [$claims, $context];
}
