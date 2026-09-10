<?php
declare(strict_types=1);

final class AdminApiRepository
{
    private PDO $db;
    private const TABLES = [
        'admin_api_keys', 'admin_api_key_scopes', 'admin_api_key_apps',
        'admin_api_license_orders', 'admin_api_idempotency', 'admin_api_nonces', 'admin_api_logs',
    ];

    public function __construct(PDO $db) { $this->db = $db; }
    public function db(): PDO { return $this->db; }
    public static function requiredTables(): array { return self::TABLES; }

    public function requireSchema(): void
    {
        foreach (self::TABLES as $table) {
            $statement = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
            $statement->execute([':table' => $table]);
            if ((int)$statement->fetchColumn() !== 1) {
                throw new AdminApiException('ADMIN_API_NOT_READY', 'Admin API database migration is required.', 503);
            }
        }
    }

    public function authenticate(string $token, string $rawBody): array
    {
        $statement = $this->db->prepare("SELECT * FROM admin_api_keys WHERE key_hash = :key_hash AND status = 'active' AND (expires_at IS NULL OR expires_at >= NOW()) LIMIT 1");
        $statement->execute([':key_hash' => hash('sha256', $token)]);
        $key = $statement->fetch();
        if (!$key) { throw new AdminApiException('INVALID_API_KEY', 'Invalid or inactive Admin API key.', 401); }

        $ip = Security::getClientIP();
        if (!$this->ipAllowed($ip, (string)($key['allowed_ips'] ?? ''))) {
            throw new AdminApiException('IP_NOT_ALLOWED', 'The request source is not allowed.', 403);
        }

        $rateLimit = max(10, min(100000, (int)($key['rate_limit_per_hour'] ?? 300)));
        if (!Security::checkRateLimit($ip, 'admin.api.key.' . (int)$key['id'], $rateLimit)) {
            throw new AdminApiException('RATE_LIMITED', 'Too many requests.', 429);
        }

        [$timestamp, $nonce, $signature] = AdminApi::proofHeaders();
        $skew = AdminApi::validateProofMetadata($timestamp, $nonce);
        AdminApi::verifySignature($token, $signature, AdminApi::canonical($timestamp, $nonce, $rawBody));
        $this->rememberNonce((int)$key['id'], $nonce, $skew * 2);

        $scopes = $this->values('SELECT scope_name FROM admin_api_key_scopes WHERE api_key_id = :id ORDER BY scope_name', ':id', (int)$key['id']);
        $apps = $this->values('SELECT app_id FROM admin_api_key_apps WHERE api_key_id = :id ORDER BY app_id', ':id', (int)$key['id']);
        $touch = $this->db->prepare('UPDATE admin_api_keys SET last_used_at = NOW(), last_used_ip = :ip WHERE id = :id');
        $touch->execute([':ip' => $ip, ':id' => $key['id']]);
        $key['scopes'] = $scopes;
        $key['app_ids'] = $apps;
        return $key;
    }

    public function requireScope(array $context, string $scope): void
    {
        if (!in_array($scope, $context['scopes'] ?? [], true)) {
            throw new AdminApiException('SCOPE_REQUIRED', 'The Admin API key does not allow this operation.', 403);
        }
    }

    public function requireApp(array $context, string $appId): array
    {
        if (!in_array($appId, $context['app_ids'] ?? [], true)) {
            throw new AdminApiException('APP_NOT_ALLOWED', 'The Admin API key is not authorized for this application.', 403);
        }
        $statement = $this->db->prepare('SELECT app_id, display_name, is_active FROM v2_client_apps WHERE app_id = :app_id AND is_active = 1 LIMIT 1');
        $statement->execute([':app_id' => $appId]);
        $app = $statement->fetch();
        if (!$app) { throw new AdminApiException('APP_NOT_ALLOWED', 'The selected application is not active.', 403); }
        return $app;
    }

    public function audit(?int $keyId, string $event, ?string $scope, int $status, array $details = []): void
    {
        try {
            $statement = $this->db->prepare('INSERT INTO admin_api_logs (api_key_id, event_type, scope_name, request_id, http_method, request_path, response_code, ip_address, details_json, created_at) VALUES (:key_id, :event_type, :scope_name, :request_id, :http_method, :request_path, :response_code, :ip_address, :details_json, NOW())');
            $statement->execute([
                ':key_id' => $keyId,
                ':event_type' => $event,
                ':scope_name' => $scope,
                ':request_id' => AdminApi::requestId(),
                ':http_method' => substr(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), 0, 12),
                ':request_path' => substr(AdminApi::path(), 0, 500),
                ':response_code' => $status,
                ':ip_address' => Security::getClientIP(),
                ':details_json' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            error_log('Admin API audit write failed: ' . $exception->getMessage());
        }
    }

    public function maskLicense(string $licenseKey): string
    {
        return strlen($licenseKey) >= 17 ? substr($licenseKey, 0, 8) . '-********-********-' . substr($licenseKey, -8) : '********';
    }

    private function rememberNonce(int $keyId, string $nonce, int $ttl): void
    {
        $nonceHash = hash('sha256', $nonce);
        $expired = $this->db->prepare('DELETE FROM admin_api_nonces WHERE api_key_id = :key_id AND nonce_hash = :nonce_hash AND expires_at < NOW()');
        $expired->execute([':key_id' => $keyId, ':nonce_hash' => $nonceHash]);
        try {
            $statement = $this->db->prepare('INSERT INTO admin_api_nonces (api_key_id, nonce_hash, expires_at, created_at) VALUES (:key_id, :nonce_hash, :expires_at, NOW())');
            $statement->execute([
                ':key_id' => $keyId,
                ':nonce_hash' => $nonceHash,
                ':expires_at' => date('Y-m-d H:i:s', time() + max(60, $ttl)),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new AdminApiException('REPLAY_DETECTED', 'The request nonce was already used.', 409);
            }
            throw $exception;
        }
        if (random_int(1, 100) === 1) {
            try { $this->db->exec('DELETE FROM admin_api_nonces WHERE expires_at < NOW()'); }
            catch (Throwable $exception) { error_log('Admin API nonce cleanup failed: ' . $exception->getMessage()); }
        }
    }

    private function ipAllowed(string $ip, string $rules): bool
    {
        $items = preg_split('/[\s,]+/', trim($rules), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($items === []) { return true; }
        foreach ($items as $rule) {
            if (AdminApi::isValidIpRule($rule) && AdminApi::ipMatchesRule($ip, $rule)) { return true; }
        }
        return false;
    }

    private function values(string $sql, string $parameter, int $id): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute([$parameter => $id]);
        return array_values(array_map(static fn(array $row): string => (string)array_values($row)[0], $statement->fetchAll()));
    }
}
