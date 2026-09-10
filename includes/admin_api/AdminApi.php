<?php
declare(strict_types=1);

final class AdminApi
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId === null) { self::$requestId = bin2hex(random_bytes(16)); }
        return self::$requestId;
    }

    public static function requireMethod(string $expected): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== strtoupper($expected)) {
            throw new AdminApiException('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
        }
    }

    public static function requireHttps(): void
    {
        if (PHP_SAPI === 'cli') { return; }
        $required = defined('ADMIN_API_REQUIRE_HTTPS') ? (bool)filter_var(ADMIN_API_REQUIRE_HTTPS, FILTER_VALIDATE_BOOLEAN) : true;
        if (!$required) { return; }
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        if (!$https && defined('LICENSE_TRUST_PROXY_HEADERS') && filter_var(LICENSE_TRUST_PROXY_HEADERS, FILTER_VALIDATE_BOOLEAN)) {
            $https = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
        }
        if (!$https) { throw new AdminApiException('HTTPS_REQUIRED', 'HTTPS is required.', 400); }
    }

    public static function readJson(): array
    {
        $type = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($type, 'application/json') !== 0) {
            throw new AdminApiException('INVALID_CONTENT_TYPE', 'Content-Type must be application/json.', 415);
        }
        $max = defined('ADMIN_API_MAX_BODY_BYTES') ? max(1024, min(1048576, (int)ADMIN_API_MAX_BODY_BYTES)) : 65536;
        $raw = file_get_contents('php://input', false, null, 0, $max + 1);
        if ($raw === false) { throw new AdminApiException('INVALID_REQUEST', 'Unable to read request body.', 400); }
        if (strlen($raw) > $max) { throw new AdminApiException('REQUEST_TOO_LARGE', 'Request body is too large.', 413); }
        $data = json_decode($raw, true);
        if (!is_array($data)) { throw new AdminApiException('INVALID_JSON', 'Invalid JSON request.', 400); }
        return [$data, $raw];
    }

    public static function assertFields(array $data, array $required, array $allowed): void
    {
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                throw new AdminApiException('INVALID_REQUEST', 'Required request field is missing: ' . $field, 400);
            }
        }
        if (array_diff(array_keys($data), $allowed)) {
            throw new AdminApiException('INVALID_REQUEST', 'Request contains unsupported fields.', 400);
        }
    }

    public static function bearerToken(): string
    {
        $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(licora_admin_(?:live|test)_[a-f0-9]{64})$/', trim($header), $match)) {
            throw new AdminApiException('AUTHENTICATION_REQUIRED', 'Admin API authentication is required.', 401);
        }
        return $match[1];
    }

    public static function proofHeaders(): array
    {
        return [
            (string)($_SERVER['HTTP_X_LICORA_TIMESTAMP'] ?? ''),
            (string)($_SERVER['HTTP_X_LICORA_NONCE'] ?? ''),
            strtolower((string)($_SERVER['HTTP_X_LICORA_SIGNATURE'] ?? '')),
        ];
    }

    public static function path(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public static function target(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($uri === '' || preg_match('/[\r\n]/', $uri)) { return '/'; }
        return $uri;
    }

    public static function canonical(string $timestamp, string $nonce, string $rawBody): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) . "\n"
            . self::target() . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }

    public static function validateProofMetadata(string $timestamp, string $nonce): int
    {
        if (!preg_match('/^[0-9]{10}$/', $timestamp)) {
            throw new AdminApiException('INVALID_REQUEST_PROOF', 'Invalid request proof.', 401);
        }
        $parsed = (int)$timestamp;
        $skew = defined('ADMIN_API_CLOCK_SKEW') ? max(30, min(900, (int)ADMIN_API_CLOCK_SKEW)) : 300;
        if (abs(time() - $parsed) > $skew) {
            throw new AdminApiException('STALE_REQUEST', 'Request timestamp is outside the allowed window.', 401);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) {
            throw new AdminApiException('INVALID_REQUEST_PROOF', 'Invalid request proof.', 401);
        }
        return $skew;
    }

    public static function verifySignature(string $token, string $signature, string $canonical): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            throw new AdminApiException('INVALID_REQUEST_PROOF', 'Invalid request proof.', 401);
        }
        $expected = hash_hmac('sha256', $canonical, $token);
        if (!hash_equals($expected, $signature)) {
            throw new AdminApiException('INVALID_REQUEST_PROOF', 'Invalid request proof.', 401);
        }
    }

    public static function idempotencyKey(): string
    {
        $key = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $key)) {
            throw new AdminApiException('IDEMPOTENCY_KEY_REQUIRED', 'A valid Idempotency-Key header is required.', 400);
        }
        return $key;
    }

    public static function queryPositiveInt(string $name, ?int $default = null): ?int
    {
        if (!isset($_GET[$name]) || $_GET[$name] === '') { return $default; }
        $value = filter_var($_GET[$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) { throw new AdminApiException('INVALID_REQUEST', 'Invalid query parameter: ' . $name, 400); }
        return (int)$value;
    }

    public static function respond(int $status, string $code, string $message, array $data = [], array $meta = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        $payload = [
            'success' => $status >= 200 && $status < 300,
            'protocol' => 'licora-admin-api',
            'api_version' => 1,
            'server_version' => defined('APP_VERSION') ? APP_VERSION : '5.8.3',
            'request_id' => self::requestId(),
            'code' => $code,
            'message' => $message,
            'server_time' => time(),
        ];
        if ($data !== []) { $payload['data'] = $data; }
        if ($meta !== []) { $payload['meta'] = $meta; }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function handle(Throwable $exception): void
    {
        if ($exception instanceof AdminApiException) {
            error_log('Licora Admin API [' . self::requestId() . '] ' . $exception->machineCode());
            self::respond($exception->httpStatus(), $exception->machineCode(), $exception->getMessage());
        }
        error_log('Licora Admin API [' . self::requestId() . '] internal error: ' . get_class($exception));
        self::respond(500, 'INTERNAL_ERROR', 'Request could not be completed.');
    }

    public static function isValidIpRule(string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') { return false; }
        [$ip, $prefix] = array_pad(explode('/', $rule, 2), 2, null);
        $packed = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;
        if ($packed === false) { return false; }
        if ($prefix === null) { return true; }
        if (!ctype_digit($prefix)) { return false; }
        $bits = strlen($packed) * 8;
        return (int)$prefix >= 0 && (int)$prefix <= $bits;
    }

    public static function ipMatchesRule(string $ip, string $rule): bool
    {
        $rule = trim($rule);
        [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, null);
        $addressBytes = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;
        $networkBytes = filter_var($network, FILTER_VALIDATE_IP) ? inet_pton($network) : false;
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) { return false; }
        $bits = $prefix === null ? strlen($addressBytes) * 8 : (int)$prefix;
        $whole = intdiv($bits, 8);
        $remaining = $bits % 8;
        if ($whole > 0 && !hash_equals(substr($networkBytes, 0, $whole), substr($addressBytes, 0, $whole))) { return false; }
        if ($remaining === 0) { return true; }
        $mask = (0xff << (8 - $remaining)) & 0xff;
        return (ord($networkBytes[$whole]) & $mask) === (ord($addressBytes[$whole]) & $mask);
    }
}
