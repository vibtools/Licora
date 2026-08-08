<?php
declare(strict_types=1);

final class V2Repository
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function requireSchema(): void
    {
        foreach (['v2_client_apps','v2_device_credentials','v2_refresh_tokens','v2_used_nonces','v2_audit_logs'] as $table) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
            $stmt->execute([':table' => $table]);
            if ((int)$stmt->fetchColumn() !== 1) { throw new V2Exception('API_V2_NOT_READY', 'API v2 database migration is required.', 503); }
        }
    }

    public function clientApp(string $appId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM v2_client_apps WHERE app_id = :app_id AND is_active = 1 LIMIT 1');
        $stmt->execute([':app_id' => $appId]);
        $row = $stmt->fetch();
        if (!$row) { throw new V2Exception('INVALID_APP', 'Application is not authorized.', 403); }
        return $row;
    }

    public function activate(string $licenseKey, array $app, string $appVersion, string $deviceId, string $publicKey, string $fingerprint): array
    {
        $this->db->beginTransaction();
        try {
            $licenseStmt = $this->db->prepare('SELECT * FROM licenses WHERE license_key = :license_key LIMIT 1 FOR UPDATE');
            $licenseStmt->execute([':license_key' => $licenseKey]);
            $license = $licenseStmt->fetch();
            $this->assertLicense($license, (string)$app['app_id']);

            $minimum = trim((string)($app['min_version'] ?? ''));
            if ($minimum !== '' && version_compare($appVersion, $minimum, '<')) {
                throw new V2Exception('APP_VERSION_UNSUPPORTED', 'Application version is not supported.', 426);
            }
            if ($this->isBlacklisted('license', $licenseKey) || $this->isBlacklisted('device', $deviceId)) {
                throw new V2Exception('ACCESS_DENIED', 'Access denied.', 403);
            }

            $deviceStmt = $this->db->prepare('SELECT * FROM devices WHERE license_id = :license_id AND device_hash = :device_hash ORDER BY id ASC LIMIT 1 FOR UPDATE');
            $deviceStmt->execute([':license_id' => $license['id'], ':device_hash' => $deviceId]);
            $device = $deviceStmt->fetch();
            if ($device) {
                if (!(int)$device['is_active']) { throw new V2Exception('DEVICE_REVOKED', 'Device is revoked.', 403); }
                $touch = $this->db->prepare('UPDATE devices SET last_active = NOW() WHERE id = :id');
                $touch->execute([':id' => $device['id']]);
            } else {
                $countStmt = $this->db->prepare('SELECT COUNT(*) FROM devices WHERE license_id = :license_id AND is_active = 1');
                $countStmt->execute([':license_id' => $license['id']]);
                if ((int)$countStmt->fetchColumn() >= (int)$license['device_limit']) {
                    throw new V2Exception('DEVICE_LIMIT_REACHED', 'Device limit reached.', 409);
                }
                $insertDevice = $this->db->prepare('INSERT INTO devices (license_id, device_hash, device_info, os, browser, login_time, last_active, is_active) VALUES (:license_id, :device_hash, :device_info, :os, :browser, NOW(), NOW(), 1)');
                $insertDevice->execute([
                    ':license_id' => $license['id'], ':device_hash' => $deviceId,
                    ':device_info' => json_encode(['api' => 'v2', 'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? '')]),
                    ':os' => 'API v2 client', ':browser' => 'N/A',
                ]);
                $this->syncTotalDevices((int)$license['id']);
            }

            $credStmt = $this->db->prepare('SELECT * FROM v2_device_credentials WHERE license_id = :license_id AND app_id = :app_id AND device_hash = :device_hash LIMIT 1 FOR UPDATE');
            $credStmt->execute([':license_id' => $license['id'], ':app_id' => $app['app_id'], ':device_hash' => $deviceId]);
            $credential = $credStmt->fetch();
            if ($credential) {
                if (($credential['status'] ?? '') !== 'active') { throw new V2Exception('DEVICE_REVOKED', 'Device is revoked.', 403); }
                if (!hash_equals((string)$credential['public_key_fingerprint'], $fingerprint)) {
                    throw new V2Exception('DEVICE_KEY_MISMATCH', 'Device key does not match the registered device.', 409);
                }
                $update = $this->db->prepare('UPDATE v2_device_credentials SET public_key = :public_key, last_seen_at = NOW() WHERE id = :id');
                $update->execute([':public_key' => $publicKey, ':id' => $credential['id']]);
            } else {
                $insert = $this->db->prepare("INSERT INTO v2_device_credentials (license_id, app_id, device_hash, public_key, public_key_fingerprint, status, first_seen_at, last_seen_at) VALUES (:license_id, :app_id, :device_hash, :public_key, :fingerprint, 'active', NOW(), NOW())");
                $insert->execute([':license_id' => $license['id'], ':app_id' => $app['app_id'], ':device_hash' => $deviceId, ':public_key' => $publicKey, ':fingerprint' => $fingerprint]);
                $credential = ['id' => (int)$this->db->lastInsertId(), 'license_id' => (int)$license['id'], 'app_id' => $app['app_id'], 'device_hash' => $deviceId, 'public_key' => $publicKey, 'public_key_fingerprint' => $fingerprint, 'status' => 'active'];
            }
            if (!isset($credential['id'])) {
                $credStmt->execute([':license_id' => $license['id'], ':app_id' => $app['app_id'], ':device_hash' => $deviceId]);
                $credential = $credStmt->fetch();
            }
            $this->db->commit();
            return ['license' => $license, 'credential' => $credential, 'app' => $app];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    public function createRefreshToken(int $credentialId, int $ttl, ?string $familyId = null): array
    {
        $token = V2TokenService::b64urlEncode(random_bytes(32));
        $hash = hash('sha256', $token);
        $family = $familyId ?: bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + max(300, $ttl));
        $stmt = $this->db->prepare('INSERT INTO v2_refresh_tokens (device_credential_id, token_hash, family_id, expires_at, created_at) VALUES (:credential_id, :token_hash, :family_id, :expires_at, NOW())');
        $stmt->execute([':credential_id' => $credentialId, ':token_hash' => $hash, ':family_id' => $family, ':expires_at' => $expires]);
        return ['token' => $token, 'hash' => $hash, 'family_id' => $family, 'expires_at' => $expires];
    }

    public function refreshContextForUpdate(string $refreshToken): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT rt.*, dc.license_id, dc.app_id, dc.device_hash, dc.public_key, dc.public_key_fingerprint, dc.status AS device_status, a.is_active AS app_active, a.min_version, a.access_token_ttl, a.refresh_token_ttl, a.clock_skew_seconds, a.rate_limit_per_hour, l.status AS license_status, l.expires_at AS license_expires_at, l.app_scope FROM v2_refresh_tokens rt JOIN v2_device_credentials dc ON dc.id = rt.device_credential_id JOIN v2_client_apps a ON a.app_id = dc.app_id JOIN licenses l ON l.id = dc.license_id WHERE rt.token_hash = :token_hash LIMIT 1 FOR UPDATE');
            $stmt->execute([':token_hash' => hash('sha256', $refreshToken)]);
            $row = $stmt->fetch();
            if (!$row) { throw new V2Exception('INVALID_REFRESH_TOKEN', 'Invalid refresh token.', 401); }
            if ($row['revoked_at'] !== null || $row['used_at'] !== null || strtotime((string)$row['expires_at']) < time()) {
                // Persist family revocation outside the row-lock transaction. If the
                // transaction were rolled back after marking the family revoked, the
                // reuse response would silently undo the security action.
                $familyId = (string)$row['family_id'];
                $this->db->rollBack();
                $this->revokeRefreshFamily($familyId);
                throw new V2Exception('REFRESH_TOKEN_REUSED', 'Refresh token is no longer valid.', 401);
            }
            $this->assertActiveContext($row);
            return $row;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    public function completeRefresh(array $context): array
    {
        if (!$this->db->inTransaction()) { throw new RuntimeException('Refresh transaction is not active.'); }
        try {
            $new = $this->createRefreshToken((int)$context['device_credential_id'], (int)$context['refresh_token_ttl'], (string)$context['family_id']);
            $update = $this->db->prepare('UPDATE v2_refresh_tokens SET used_at = NOW(), rotated_to_hash = :rotated WHERE id = :id AND used_at IS NULL AND revoked_at IS NULL');
            $update->execute([':rotated' => $new['hash'], ':id' => $context['id']]);
            if ($update->rowCount() !== 1) { throw new V2Exception('REFRESH_TOKEN_REUSED', 'Refresh token is no longer valid.', 401); }
            $touch = $this->db->prepare('UPDATE v2_device_credentials SET last_seen_at = NOW() WHERE id = :id');
            $touch->execute([':id' => $context['device_credential_id']]);
            $this->db->commit();
            return $new;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    public function abortTransaction(): void { if ($this->db->inTransaction()) { $this->db->rollBack(); } }

    public function accessContext(array $claims): array
    {
        $stmt = $this->db->prepare('SELECT dc.*, a.is_active AS app_active, a.clock_skew_seconds, a.rate_limit_per_hour, l.status AS license_status, l.expires_at AS license_expires_at, l.app_scope FROM v2_device_credentials dc JOIN v2_client_apps a ON a.app_id = dc.app_id JOIN licenses l ON l.id = dc.license_id WHERE dc.id = :credential_id AND dc.license_id = :license_id AND dc.app_id = :app_id AND dc.device_hash = :device_hash LIMIT 1');
        $stmt->execute([':credential_id' => (int)($claims['device_credential_id'] ?? 0), ':license_id' => (int)$claims['license_id'], ':app_id' => (string)$claims['app_id'], ':device_hash' => (string)$claims['device_id']]);
        $row = $stmt->fetch();
        if (!$row) { throw new V2Exception('DEVICE_REVOKED', 'Device is not authorized.', 403); }
        $this->assertActiveContext($row);
        if (!hash_equals((string)$claims['device_key_fingerprint'], (string)$row['public_key_fingerprint'])) { throw new V2Exception('INVALID_DEVICE_PROOF', 'Invalid device proof.', 401); }
        return $row;
    }

    public function rememberNonce(int $credentialId, string $nonce, int $ttlSeconds): void
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO v2_used_nonces (device_credential_id, nonce_hash, expires_at, created_at) VALUES (:credential_id, :nonce_hash, :expires_at, NOW())');
            $stmt->execute([':credential_id' => $credentialId, ':nonce_hash' => hash('sha256', $nonce), ':expires_at' => date('Y-m-d H:i:s', time() + max(60, $ttlSeconds))]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { throw new V2Exception('REPLAY_DETECTED', 'Request was already used.', 409); }
            throw $e;
        }
    }

    public function revokeCredential(int $credentialId): void
    {
        $this->db->beginTransaction();
        try {
            $device = $this->db->prepare('SELECT license_id, device_hash FROM v2_device_credentials WHERE id = :id LIMIT 1 FOR UPDATE');
            $device->execute([':id' => $credentialId]);
            $row = $device->fetch();
            if (!$row) { throw new V2Exception('DEVICE_REVOKED', 'Device is not authorized.', 404); }
            $stmt = $this->db->prepare("UPDATE v2_device_credentials SET status = 'revoked', revoked_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $credentialId]);
            $tokens = $this->db->prepare('UPDATE v2_refresh_tokens SET revoked_at = COALESCE(revoked_at, NOW()) WHERE device_credential_id = :id');
            $tokens->execute([':id' => $credentialId]);
            $disable = $this->db->prepare('UPDATE devices SET is_active = 0, last_active = NOW() WHERE license_id = :license_id AND device_hash = :device_hash');
            $disable->execute([':license_id' => $row['license_id'], ':device_hash' => $row['device_hash']]);
            $this->syncTotalDevices((int)$row['license_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    public function audit(string $event, ?string $appId = null, ?int $licenseId = null, ?int $credentialId = null, ?string $requestId = null, array $details = []): void
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO v2_audit_logs (event_type, app_id, license_id, device_credential_id, request_id, ip_address, user_agent, details_json, created_at) VALUES (:event_type, :app_id, :license_id, :credential_id, :request_id, :ip_address, :user_agent, :details_json, NOW())');
            $stmt->execute([':event_type' => $event, ':app_id' => $appId, ':license_id' => $licenseId, ':credential_id' => $credentialId, ':request_id' => $requestId, ':ip_address' => Security::getClientIP(), ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000), ':details_json' => $details ? json_encode($details) : null]);
        } catch (Throwable $e) { error_log('API v2 audit write failed: ' . $e->getMessage()); }
    }

    public function cleanupExpiredNonces(): void
    {
        try { $this->db->exec('DELETE FROM v2_used_nonces WHERE expires_at < NOW()'); }
        catch (Throwable $e) { error_log('API v2 nonce cleanup failed: ' . $e->getMessage()); }
    }

    private function assertLicense($license, string $appId): void
    {
        if (!$license) { throw new V2Exception('INVALID_LICENSE', 'License is not valid.', 403); }
        if (($license['status'] ?? '') !== 'active') { throw new V2Exception('LICENSE_INACTIVE', 'License is not active.', 403); }
        if (strtotime((string)$license['expires_at']) < time()) { throw new V2Exception('LICENSE_EXPIRED', 'License has expired.', 403); }
        if ((string)($license['app_scope'] ?? '') === '' || !hash_equals((string)$license['app_scope'], $appId)) {
            throw new V2Exception('APP_NOT_ALLOWED', 'License is not valid for this application.', 403);
        }
    }

    private function assertActiveContext(array $row): void
    {
        if (($row['device_status'] ?? $row['status'] ?? '') !== 'active') { throw new V2Exception('DEVICE_REVOKED', 'Device is revoked.', 403); }
        if (!(int)($row['app_active'] ?? 0)) { throw new V2Exception('INVALID_APP', 'Application is disabled.', 403); }
        if (($row['license_status'] ?? '') !== 'active' || strtotime((string)($row['license_expires_at'] ?? '1970-01-01')) < time()) {
            throw new V2Exception('LICENSE_INACTIVE', 'License is not active.', 403);
        }
        if ((string)($row['app_scope'] ?? '') === '' || !hash_equals((string)$row['app_scope'], (string)$row['app_id'])) {
            throw new V2Exception('APP_NOT_ALLOWED', 'License is not valid for this application.', 403);
        }
    }

    private function isBlacklisted(string $type, string $value): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM blacklist WHERE type = :type AND value = :value AND (expires_at IS NULL OR expires_at >= NOW())');
        $stmt->execute([':type' => $type, ':value' => $value]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function syncTotalDevices(int $licenseId): void
    {
        $stmt = $this->db->prepare('UPDATE licenses SET total_devices = (SELECT COUNT(*) FROM devices WHERE license_id = :count_id AND is_active = 1) WHERE id = :license_id');
        $stmt->execute([':count_id' => $licenseId, ':license_id' => $licenseId]);
    }

    private function revokeRefreshFamily(string $familyId): void
    {
        $stmt = $this->db->prepare('UPDATE v2_refresh_tokens SET revoked_at = COALESCE(revoked_at, NOW()) WHERE family_id = :family_id');
        $stmt->execute([':family_id' => $familyId]);
    }
}
