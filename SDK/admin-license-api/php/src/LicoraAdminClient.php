<?php
declare(strict_types=1);

namespace VibTools\Licora\AdminApi;

use JsonException;

final class LicoraAdminClient
{
    private const PROTOCOL = 'licora-admin-api';
    private const API_VERSION = 1;

    private string $baseUrl;
    private string $apiKey;
    private int $timeoutMs;
    private int $maxResponseBytes;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeoutMs = 15000,
        int $maxResponseBytes = 2097152
    ) {
        $parts = parse_url($baseUrl);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new LicoraAdminApiException(
                'baseUrl must be an HTTPS Licora installation URL without credentials, query or fragment.',
                'INVALID_CONFIGURATION'
            );
        }
        if (!preg_match('/^licora_admin_live_[a-f0-9]{64}$/', $apiKey)) {
            throw new LicoraAdminApiException('Invalid Licora Admin API key format.', 'INVALID_CONFIGURATION');
        }
        if ($timeoutMs < 1 || $maxResponseBytes < 1024) {
            throw new LicoraAdminApiException('Timeout and response-size limits must be positive.', 'INVALID_CONFIGURATION');
        }

        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->apiKey = $apiKey;
        $this->timeoutMs = $timeoutMs;
        $this->maxResponseBytes = $maxResponseBytes;
    }

    /** @return array<string, mixed> */
    public function listAllowedApps(): array
    {
        return $this->request('GET', 'api/admin/v1/apps/list.php');
    }

    /** @param array<string, mixed> $license @return array<string, mixed> */
    public function createLicense(array $license, string $idempotencyKey): array
    {
        return $this->request('POST', 'api/admin/v1/licenses/create.php', [], $license, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function licenseStatus(?int $licenseId = null, ?string $externalOrderId = null, bool $includeKey = false): array
    {
        $query = [];
        if ($licenseId !== null) { $query['license_id'] = $licenseId; }
        if ($externalOrderId !== null) { $query['external_order_id'] = $externalOrderId; }
        if ($includeKey) { $query['include_key'] = 'true'; }
        return $this->request('GET', 'api/admin/v1/licenses/status.php', $query);
    }

    /** @param array<string, scalar|null> $filters @return array<string, mixed> */
    public function listLicenses(array $filters = []): array
    {
        return $this->request('GET', 'api/admin/v1/licenses/list.php', $this->withoutNulls($filters));
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    public function licenseAction(int $licenseId, string $action, array $fields = []): array
    {
        return $this->request('POST', 'api/admin/v1/licenses/action.php', [], array_merge(
            $fields,
            ['license_id' => $licenseId, 'action' => $action]
        ));
    }

    /** @return array<string, mixed> */
    public function activateLicense(int $licenseId): array
    {
        return $this->licenseAction($licenseId, 'activate');
    }

    /** @return array<string, mixed> */
    public function suspendLicense(int $licenseId): array
    {
        return $this->licenseAction($licenseId, 'suspend');
    }

    /** @return array<string, mixed> */
    public function extendLicense(int $licenseId, int $additionalHours): array
    {
        return $this->licenseAction($licenseId, 'extend', ['additional_hours' => $additionalHours]);
    }

    /** @return array<string, mixed> */
    public function banLicense(int $licenseId, string $reason): array
    {
        return $this->licenseAction($licenseId, 'ban', ['reason' => $reason]);
    }

    /** @return array<string, mixed> */
    public function deleteLicense(int $licenseId): array
    {
        return $this->licenseAction($licenseId, 'delete');
    }

    /** @return array<string, mixed> */
    public function listDevices(int $licenseId): array
    {
        return $this->request('GET', 'api/admin/v1/devices/list.php', ['license_id' => $licenseId]);
    }

    /** @return array<string, mixed> */
    public function revokeDevice(int $credentialId): array
    {
        return $this->request('POST', 'api/admin/v1/devices/revoke.php', [], ['credential_id' => $credentialId]);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        ?array $payload = null,
        ?string $idempotencyKey = null
    ): array {
        $method = strtoupper($method);
        if ($idempotencyKey !== null && !preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $idempotencyKey)) {
            throw new LicoraAdminApiException('Invalid Idempotency-Key format.', 'INVALID_CONFIGURATION');
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = $this->baseUrl . ltrim($endpoint, '/');
        if ($queryString !== '') { $url .= '?' . $queryString; }
        $target = (string)parse_url($url, PHP_URL_PATH);
        if ($queryString !== '') { $target .= '?' . $queryString; }

        try {
            $body = $payload === null
                ? ''
                : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LicoraAdminApiException('Unable to encode the request body.', 'INVALID_REQUEST_BODY', 0, null, null, $exception);
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $method . "\n" . $target . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $canonical, $this->apiKey);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'X-Licora-Timestamp: ' . $timestamp,
            'X-Licora-Nonce: ' . $nonce,
            'X-Licora-Signature: ' . $signature,
        ];
        if ($payload !== null) { $headers[] = 'Content-Type: application/json'; }
        if ($idempotencyKey !== null) { $headers[] = 'Idempotency-Key: ' . $idempotencyKey; }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new LicoraAdminApiException('Unable to initialize the HTTP client.', 'NETWORK_ERROR');
        }

        $responseBody = '';
        $responseTooLarge = false;
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT_MS => min($this->timeoutMs, 5000),
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBytes) {
                    $responseTooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($payload !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, $body); }

        $executed = curl_exec($curl);
        $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseTooLarge) {
            throw new LicoraAdminApiException('Licora response exceeded the configured size limit.', 'INVALID_SERVER_RESPONSE', $httpStatus);
        }
        if ($executed === false) {
            throw new LicoraAdminApiException('Licora request failed: ' . $curlError, 'NETWORK_ERROR', $httpStatus);
        }

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LicoraAdminApiException('Licora returned invalid JSON.', 'INVALID_SERVER_RESPONSE', $httpStatus, null, null, $exception);
        }
        if (!is_array($decoded)
            || ($decoded['protocol'] ?? null) !== self::PROTOCOL
            || ($decoded['api_version'] ?? null) !== self::API_VERSION
            || !is_bool($decoded['success'] ?? null)
            || !is_string($decoded['code'] ?? null)
            || !is_string($decoded['request_id'] ?? null)
        ) {
            throw new LicoraAdminApiException('Licora returned an invalid response envelope.', 'INVALID_SERVER_RESPONSE', $httpStatus);
        }

        if ($httpStatus < 200 || $httpStatus >= 300 || $decoded['success'] !== true) {
            $message = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'Licora Admin API request failed.';
            throw new LicoraAdminApiException($message, $decoded['code'], $httpStatus, $decoded['request_id'], $decoded);
        }
        return $decoded;
    }

    /** @param array<string, scalar|null> $values @return array<string, scalar> */
    private function withoutNulls(array $values): array
    {
        return array_filter($values, static fn ($value): bool => $value !== null);
    }
}

