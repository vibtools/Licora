<?php
declare(strict_types=1);

final class ApiV2
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId === null) { self::$requestId = bin2hex(random_bytes(8)); }
        return self::$requestId;
    }

    public static function settingInt(string $name, int $default, int $min, int $max): int
    {
        $raw = defined($name) ? constant($name) : getenv($name);
        $value = ($raw === false || $raw === null || $raw === '') ? $default : (int)$raw;
        return max($min, min($max, $value));
    }

    public static function settingBool(string $name, bool $default): bool
    {
        $raw = defined($name) ? constant($name) : getenv($name);
        if ($raw === false || $raw === null || $raw === '') { return $default; }
        return in_array(strtolower(trim((string)$raw)), ['1','true','yes','on'], true);
    }

    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { throw new V2Exception('METHOD_NOT_ALLOWED', 'Method not allowed.', 405); }
    }

    public static function requireHttps(): void
    {
        if (!self::settingBool('LICENSE_V2_REQUIRE_HTTPS', true) || PHP_SAPI === 'cli') { return; }
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        if (!$https && self::settingBool('LICENSE_TRUST_PROXY_HEADERS', false)) {
            $https = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
        }
        if (!$https) { throw new V2Exception('HTTPS_REQUIRED', 'HTTPS is required.', 400); }
    }

    public static function readJson(): array
    {
        $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($contentType, 'application/json') !== 0) { throw new V2Exception('INVALID_CONTENT_TYPE', 'Content-Type must be application/json.', 415); }
        $max = self::settingInt('LICENSE_V2_MAX_BODY_BYTES', 32768, 1024, 1048576);
        $raw = file_get_contents('php://input', false, null, 0, $max + 1);
        if ($raw === false) { throw new V2Exception('INVALID_REQUEST', 'Unable to read request body.', 400); }
        if (strlen($raw) > $max) { throw new V2Exception('REQUEST_TOO_LARGE', 'Request body is too large.', 413); }
        $data = json_decode($raw, true);
        if (!is_array($data)) { throw new V2Exception('INVALID_JSON', 'Invalid JSON request.', 400); }
        return [$data, $raw];
    }

    public static function assertFields(array $data, array $required, array $allowed): void
    {
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) { throw new V2Exception('INVALID_REQUEST', 'Required request field is missing.', 400); }
        }
        if (array_diff(array_keys($data), $allowed)) { throw new V2Exception('INVALID_REQUEST', 'Request contains unsupported fields.', 400); }
    }

    public static function appId($value): string
    {
        $value = strtolower(trim((string)$value));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]$/', $value)) { throw new V2Exception('INVALID_APP', 'Invalid application.', 400); }
        return $value;
    }

    public static function deviceId($value): string
    {
        $value = trim((string)$value);
        if (!preg_match('/^[A-Za-z0-9._:-]{16,255}$/', $value)) { throw new V2Exception('INVALID_DEVICE', 'Invalid device.', 400); }
        return $value;
    }

    public static function appVersion($value): string
    {
        $value = trim((string)$value);
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,63}$/', $value)) { throw new V2Exception('INVALID_APP_VERSION', 'Invalid application version.', 400); }
        return $value;
    }

    public static function bearerToken(): string
    {
        $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]+)$/i', trim($header), $m)) { throw new V2Exception('TOKEN_REQUIRED', 'Access token is required.', 401); }
        return $m[1];
    }

    public static function proofHeaders(): array
    {
        return [(string)($_SERVER['HTTP_X_LICORA_TIMESTAMP'] ?? ''), (string)($_SERVER['HTTP_X_LICORA_NONCE'] ?? ''), (string)($_SERVER['HTTP_X_LICORA_DEVICE_SIGNATURE'] ?? '')];
    }

    public static function path(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public static function rateLimit(string $key, int $limit): void
    {
        if (!Security::checkRateLimit(Security::getClientIP(), $key, max(1, $limit))) { throw new V2Exception('RATE_LIMITED', 'Too many requests.', 429); }
    }

    public static function respond(int $status, bool $success, string $code, string $message, array $data = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(array_merge([
            'success' => $success, 'protocol' => 'licora-api-v2', 'api_version' => 2,
            'server_version' => defined('APP_VERSION') ? APP_VERSION : '5.2.0',
            'request_id' => self::requestId(), 'code' => $code, 'message' => $message, 'server_time' => time(),
        ], $data), JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function handle(Throwable $e): void
    {
        if ($e instanceof V2Exception) {
            error_log('Licora API v2 [' . self::requestId() . '] ' . $e->machineCode());
            self::respond($e->httpStatus(), false, $e->machineCode(), $e->getMessage());
        }
        error_log('Licora API v2 [' . self::requestId() . '] internal error: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
        self::respond(500, false, 'INTERNAL_ERROR', 'Request could not be completed.');
    }
}
