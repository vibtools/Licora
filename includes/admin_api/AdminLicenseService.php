<?php
declare(strict_types=1);

final class AdminLicenseService
{
    private AdminApiRepository $repository;
    private PDO $db;

    public function __construct(AdminApiRepository $repository)
    {
        $this->repository = $repository;
        $this->db = $repository->db();
    }

    public function create(array $context, array $input, string $idempotencyKey): array
    {
        $this->repository->requireScope($context, 'license:create');
        AdminApi::assertFields(
            $input,
            ['external_order_id', 'app_id', 'validity_hours', 'device_limit'],
            ['external_order_id', 'app_id', 'validity_hours', 'device_limit', 'customer_reference', 'notes']
        );

        $appId = strtolower(trim((string)$input['app_id']));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]$/', $appId)) {
            throw new AdminApiException('INVALID_APP', 'Invalid application.', 400);
        }
        $this->repository->requireApp($context, $appId);
        $externalOrderId = $this->identifier($input['external_order_id'], 'external_order_id');
        $customerReference = $this->optionalIdentifier($input['customer_reference'] ?? null, 'customer_reference');
        $notes = trim((string)($input['notes'] ?? ''));
        if (strlen($notes) > 1000) { throw new AdminApiException('INVALID_REQUEST', 'Notes may not exceed 1000 characters.', 400); }

        [$minimumHours, $maximumHours] = $this->hourBounds();
        $hours = filter_var($input['validity_hours'], FILTER_VALIDATE_INT);
        if ($hours === false || $hours < $minimumHours || $hours > $maximumHours) {
            throw new AdminApiException('INVALID_VALIDITY', "Validity must be between {$minimumHours} and {$maximumHours} hours.", 400);
        }
        $deviceLimit = filter_var($input['device_limit'], FILTER_VALIDATE_INT);
        if ($deviceLimit === false || $deviceLimit < 1 || $deviceLimit > 100) {
            throw new AdminApiException('INVALID_DEVICE_LIMIT', 'Device limit must be between 1 and 100.', 400);
        }

        $canonicalInput = [
            'external_order_id' => $externalOrderId,
            'app_id' => $appId,
            'validity_hours' => (int)$hours,
            'device_limit' => (int)$deviceLimit,
            'customer_reference' => $customerReference,
            'notes' => $notes,
        ];
        $requestHash = hash('sha256', (string)json_encode($canonicalInput, JSON_UNESCAPED_SLASHES));
        $apiKeyId = (int)$context['id'];

        $this->db->beginTransaction();
        try {
            $existing = $this->ownedByOrder($apiKeyId, $externalOrderId, true);
            if ($existing) {
                if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                    throw new AdminApiException('ORDER_CONFLICT', 'The order already has a license with different parameters.', 409);
                }
                $this->assertIdempotencyCompatible($apiKeyId, $idempotencyKey, $requestHash, (int)$existing['id']);
                $this->db->commit();
                return ['license' => $this->formatLicense($existing, true), 'idempotent_replay' => true];
            }

            $idempotency = $this->idempotency($apiKeyId, $idempotencyKey, true);
            if ($idempotency) {
                if (!hash_equals((string)$idempotency['request_hash'], $requestHash)) {
                    throw new AdminApiException('IDEMPOTENCY_CONFLICT', 'The Idempotency-Key was used for a different request.', 409);
                }
                if (!empty($idempotency['license_id'])) {
                    $row = $this->ownedLicense($apiKeyId, (int)$idempotency['license_id'], true);
                    $this->db->commit();
                    return ['license' => $this->formatLicense($row, true), 'idempotent_replay' => true];
                }
                throw new AdminApiException('REQUEST_IN_PROGRESS', 'A request with this Idempotency-Key is still being processed.', 409);
            }

            $reserve = $this->db->prepare('INSERT INTO admin_api_idempotency (api_key_id, idempotency_key, request_hash, created_at) VALUES (:api_key_id, :idempotency_key, :request_hash, NOW())');
            $reserve->execute([':api_key_id' => $apiKeyId, ':idempotency_key' => $idempotencyKey, ':request_hash' => $requestHash]);

            $licenseKey = $this->newLicenseKey();
            $expiresAt = date('Y-m-d H:i:s', time() + ((int)$hours * 3600));
            $insert = $this->db->prepare('INSERT INTO licenses (license_key, encrypted_key, created_by, notes, app_scope, api_key_id, expires_at, device_limit, status, total_devices, created_at, updated_at) VALUES (:license_key, :encrypted_key, :created_by, :notes, :app_scope, NULL, :expires_at, :device_limit, \'active\', 0, NOW(), NOW())');
            $insert->execute([
                ':license_key' => $licenseKey,
                ':encrypted_key' => Security::encrypt($licenseKey),
                ':created_by' => !empty($context['created_by']) ? (int)$context['created_by'] : null,
                ':notes' => $notes,
                ':app_scope' => $appId,
                ':expires_at' => $expiresAt,
                ':device_limit' => (int)$deviceLimit,
            ]);
            $licenseId = (int)$this->db->lastInsertId();

            $order = $this->db->prepare('INSERT INTO admin_api_license_orders (api_key_id, license_id, external_order_id, customer_reference, request_hash, created_at, updated_at) VALUES (:api_key_id, :license_id, :external_order_id, :customer_reference, :request_hash, NOW(), NOW())');
            $order->execute([
                ':api_key_id' => $apiKeyId,
                ':license_id' => $licenseId,
                ':external_order_id' => $externalOrderId,
                ':customer_reference' => $customerReference,
                ':request_hash' => $requestHash,
            ]);
            $complete = $this->db->prepare('UPDATE admin_api_idempotency SET license_id = :license_id WHERE api_key_id = :api_key_id AND idempotency_key = :idempotency_key');
            $complete->execute([':license_id' => $licenseId, ':api_key_id' => $apiKeyId, ':idempotency_key' => $idempotencyKey]);
            $row = $this->ownedLicense($apiKeyId, $licenseId, true);
            $this->db->commit();
            return ['license' => $this->formatLicense($row, true), 'idempotent_replay' => false];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $existing = $this->ownedByOrder($apiKeyId, $externalOrderId, false);
                if ($existing && hash_equals((string)$existing['request_hash'], $requestHash)) {
                    return ['license' => $this->formatLicense($existing, true), 'idempotent_replay' => true];
                }
                throw new AdminApiException('IDEMPOTENCY_CONFLICT', 'The order or Idempotency-Key is already in use.', 409);
            }
            throw $exception;
        }
    }

    public function get(array $context, ?int $licenseId, ?string $externalOrderId, bool $includeKey): array
    {
        $this->repository->requireScope($context, 'license:read');
        if ($includeKey) { $this->repository->requireScope($context, 'license:reveal'); }
        if ($licenseId === null && ($externalOrderId === null || trim($externalOrderId) === '')) {
            throw new AdminApiException('INVALID_REQUEST', 'license_id or external_order_id is required.', 400);
        }
        $row = $licenseId !== null
            ? $this->ownedLicense((int)$context['id'], $licenseId, false)
            : $this->ownedByOrder((int)$context['id'], $this->identifier($externalOrderId, 'external_order_id'), false);
        if (!$row) { throw new AdminApiException('LICENSE_NOT_FOUND', 'License was not found.', 404); }
        $this->repository->requireApp($context, (string)$row['app_scope']);
        return $this->formatLicense($row, $includeKey);
    }

    public function list(array $context, array $filters): array
    {
        $this->repository->requireScope($context, 'license:read');
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
        $where = ['o.api_key_id = :api_key_id'];
        $parameters = [':api_key_id' => (int)$context['id']];
        if (!empty($filters['app_id'])) {
            $appId = strtolower(trim((string)$filters['app_id']));
            $this->repository->requireApp($context, $appId);
            $where[] = 'l.app_scope = :app_id';
            $parameters[':app_id'] = $appId;
        }
        if (!empty($filters['status'])) {
            $status = strtolower(trim((string)$filters['status']));
            if (!in_array($status, ['active', 'suspended', 'expired', 'banned', 'deleted'], true)) {
                throw new AdminApiException('INVALID_REQUEST', 'Invalid status filter.', 400);
            }
            if ($status === 'deleted') { $where[] = 'o.deleted_at IS NOT NULL'; }
            elseif ($status === 'banned') { $where[] = 'o.banned_at IS NOT NULL AND o.deleted_at IS NULL'; }
            elseif ($status === 'expired') { $where[] = 'o.banned_at IS NULL AND o.deleted_at IS NULL AND l.expires_at < NOW()'; }
            else { $where[] = 'o.banned_at IS NULL AND o.deleted_at IS NULL AND l.expires_at >= NOW() AND l.status = :status'; $parameters[':status'] = $status; }
        }
        if (!empty($filters['customer_reference'])) {
            $where[] = 'o.customer_reference = :customer_reference';
            $parameters[':customer_reference'] = $this->identifier($filters['customer_reference'], 'customer_reference');
        }
        $clause = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM admin_api_license_orders o JOIN licenses l ON l.id = o.license_id WHERE {$clause}");
        $count->execute($parameters);
        $total = (int)$count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT l.*, o.external_order_id, o.customer_reference, o.request_hash, o.banned_at, o.ban_reason, o.deleted_at FROM admin_api_license_orders o JOIN licenses l ON l.id = o.license_id WHERE {$clause} ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        $items = array_map(fn(array $row): array => $this->formatLicense($row, false), $statement->fetchAll());
        return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $total === 0 ? 0 : (int)ceil($total / $perPage)];
    }

    public function action(array $context, int $licenseId, string $action, array $input): array
    {
        $scopes = [
            'activate' => 'license:activate', 'suspend' => 'license:suspend', 'extend' => 'license:extend',
            'ban' => 'license:ban', 'delete' => 'license:delete',
        ];
        if (!isset($scopes[$action])) { throw new AdminApiException('INVALID_ACTION', 'Unsupported license action.', 400); }
        $this->repository->requireScope($context, $scopes[$action]);
        $this->db->beginTransaction();
        try {
            $row = $this->ownedLicense((int)$context['id'], $licenseId, true);
            $this->repository->requireApp($context, (string)$row['app_scope']);
            if ($row['deleted_at'] !== null) { throw new AdminApiException('LICENSE_DELETED', 'Deleted licenses cannot be modified.', 409); }
            if ($row['banned_at'] !== null && $action !== 'delete') { throw new AdminApiException('LICENSE_BANNED', 'Banned licenses cannot be modified.', 409); }

            if ($action === 'activate') {
                AdminApi::assertFields($input, [], []);
                if (strtotime((string)$row['expires_at']) < time()) { throw new AdminApiException('LICENSE_EXPIRED', 'Extend the license before activating it.', 409); }
                $statement = $this->db->prepare("UPDATE licenses SET status = 'active', updated_at = NOW() WHERE id = :id");
                $statement->execute([':id' => $licenseId]);
            } elseif ($action === 'suspend') {
                AdminApi::assertFields($input, [], []);
                $statement = $this->db->prepare("UPDATE licenses SET status = 'suspended', updated_at = NOW() WHERE id = :id");
                $statement->execute([':id' => $licenseId]);
            } elseif ($action === 'extend') {
                AdminApi::assertFields($input, ['additional_hours'], ['additional_hours']);
                [$minimumHours, $maximumHours] = $this->hourBounds();
                $hours = filter_var($input['additional_hours'], FILTER_VALIDATE_INT);
                if ($hours === false || $hours < $minimumHours || $hours > $maximumHours) {
                    throw new AdminApiException('INVALID_VALIDITY', "Extension must be between {$minimumHours} and {$maximumHours} hours.", 400);
                }
                $base = max(time(), strtotime((string)$row['expires_at']));
                $newExpiry = $base + ((int)$hours * 3600);
                if (($newExpiry - time()) > ($maximumHours * 3600)) {
                    throw new AdminApiException('INVALID_VALIDITY', 'The resulting expiry exceeds the configured maximum horizon.', 400);
                }
                $statement = $this->db->prepare('UPDATE licenses SET expires_at = :expires_at, updated_at = NOW() WHERE id = :id');
                $statement->execute([':expires_at' => date('Y-m-d H:i:s', $newExpiry), ':id' => $licenseId]);
            } elseif ($action === 'ban') {
                AdminApi::assertFields($input, ['reason'], ['reason']);
                $reason = trim((string)$input['reason']);
                if ($reason === '' || strlen($reason) > 500) { throw new AdminApiException('INVALID_REQUEST', 'Ban reason must contain 1 to 500 characters.', 400); }
                $this->disableLicenseDevices($licenseId);
                $exists = $this->db->prepare("SELECT COUNT(*) FROM blacklist WHERE type = 'license' AND value = :license_key AND (expires_at IS NULL OR expires_at >= NOW())");
                $exists->execute([':license_key' => $row['license_key']]);
                if ((int)$exists->fetchColumn() === 0) {
                    $blacklist = $this->db->prepare("INSERT INTO blacklist (type, value, reason, banned_by, expires_at) VALUES ('license', :value, :reason, :banned_by, NULL)");
                    $blacklist->execute([':value' => $row['license_key'], ':reason' => $reason, ':banned_by' => !empty($context['created_by']) ? (int)$context['created_by'] : null]);
                }
                $statement = $this->db->prepare("UPDATE licenses SET status = 'suspended', updated_at = NOW() WHERE id = :id");
                $statement->execute([':id' => $licenseId]);
                $order = $this->db->prepare('UPDATE admin_api_license_orders SET banned_at = NOW(), ban_reason = :reason, updated_at = NOW() WHERE license_id = :id');
                $order->execute([':reason' => $reason, ':id' => $licenseId]);
            } elseif ($action === 'delete') {
                AdminApi::assertFields($input, [], []);
                $this->disableLicenseDevices($licenseId);
                $statement = $this->db->prepare("UPDATE licenses SET status = 'suspended', updated_at = NOW() WHERE id = :id");
                $statement->execute([':id' => $licenseId]);
                $order = $this->db->prepare('UPDATE admin_api_license_orders SET deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW() WHERE license_id = :id');
                $order->execute([':id' => $licenseId]);
            }
            $updated = $this->ownedLicense((int)$context['id'], $licenseId, true);
            $this->db->commit();
            return $this->formatLicense($updated, false);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    public function devices(array $context, int $licenseId): array
    {
        $this->repository->requireScope($context, 'device:read');
        $license = $this->ownedLicense((int)$context['id'], $licenseId, false);
        $this->repository->requireApp($context, (string)$license['app_scope']);
        $statement = $this->db->prepare('SELECT id, app_id, device_hash, public_key_fingerprint, status, first_seen_at, last_seen_at, revoked_at FROM v2_device_credentials WHERE license_id = :license_id AND app_id = :app_id ORDER BY id DESC');
        $statement->execute([':license_id' => $licenseId, ':app_id' => $license['app_scope']]);
        return array_map(static function (array $row): array {
            return [
                'credential_id' => (int)$row['id'], 'app_id' => (string)$row['app_id'],
                'device_id' => (string)$row['device_hash'], 'public_key_fingerprint' => (string)$row['public_key_fingerprint'],
                'status' => (string)$row['status'], 'first_seen_at' => $row['first_seen_at'],
                'last_seen_at' => $row['last_seen_at'], 'revoked_at' => $row['revoked_at'],
            ];
        }, $statement->fetchAll());
    }

    public function revokeDevice(array $context, int $credentialId): array
    {
        $this->repository->requireScope($context, 'device:revoke');
        $statement = $this->db->prepare('SELECT dc.id, dc.license_id, dc.app_id, dc.device_hash, dc.status FROM v2_device_credentials dc JOIN admin_api_license_orders o ON o.license_id = dc.license_id WHERE dc.id = :id AND o.api_key_id = :api_key_id LIMIT 1');
        $statement->execute([':id' => $credentialId, ':api_key_id' => (int)$context['id']]);
        $device = $statement->fetch();
        if (!$device) { throw new AdminApiException('DEVICE_NOT_FOUND', 'Device was not found.', 404); }
        $this->repository->requireApp($context, (string)$device['app_id']);
        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare("UPDATE v2_device_credentials SET status = 'revoked', revoked_at = COALESCE(revoked_at, NOW()), last_seen_at = NOW() WHERE id = :id");
            $update->execute([':id' => $credentialId]);
            $tokens = $this->db->prepare('UPDATE v2_refresh_tokens SET revoked_at = COALESCE(revoked_at, NOW()) WHERE device_credential_id = :id');
            $tokens->execute([':id' => $credentialId]);
            $legacy = $this->db->prepare('UPDATE devices SET is_active = 0, last_active = NOW() WHERE license_id = :license_id AND device_hash = :device_hash');
            $legacy->execute([':license_id' => $device['license_id'], ':device_hash' => $device['device_hash']]);
            $this->syncTotalDevices((int)$device['license_id']);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
        return ['credential_id' => $credentialId, 'license_id' => (int)$device['license_id'], 'status' => 'revoked'];
    }

    public function allowedApps(array $context): array
    {
        $this->repository->requireScope($context, 'license:read');
        if (($context['app_ids'] ?? []) === []) { return []; }
        $placeholders = implode(',', array_fill(0, count($context['app_ids']), '?'));
        $statement = $this->db->prepare("SELECT app_id, display_name, min_version FROM v2_client_apps WHERE is_active = 1 AND app_id IN ({$placeholders}) ORDER BY display_name, app_id");
        $statement->execute(array_values($context['app_ids']));
        return $statement->fetchAll();
    }

    private function ownedLicense(int $apiKeyId, int $licenseId, bool $forUpdate): array
    {
        $sql = 'SELECT l.*, o.external_order_id, o.customer_reference, o.request_hash, o.banned_at, o.ban_reason, o.deleted_at FROM admin_api_license_orders o JOIN licenses l ON l.id = o.license_id WHERE o.api_key_id = :api_key_id AND l.id = :license_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db->prepare($sql);
        $statement->execute([':api_key_id' => $apiKeyId, ':license_id' => $licenseId]);
        $row = $statement->fetch();
        if (!$row) { throw new AdminApiException('LICENSE_NOT_FOUND', 'License was not found.', 404); }
        return $row;
    }

    private function ownedByOrder(int $apiKeyId, string $externalOrderId, bool $forUpdate): ?array
    {
        $sql = 'SELECT l.*, o.external_order_id, o.customer_reference, o.request_hash, o.banned_at, o.ban_reason, o.deleted_at FROM admin_api_license_orders o JOIN licenses l ON l.id = o.license_id WHERE o.api_key_id = :api_key_id AND o.external_order_id = :external_order_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db->prepare($sql);
        $statement->execute([':api_key_id' => $apiKeyId, ':external_order_id' => $externalOrderId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function idempotency(int $apiKeyId, string $key, bool $forUpdate): ?array
    {
        $sql = 'SELECT * FROM admin_api_idempotency WHERE api_key_id = :api_key_id AND idempotency_key = :idempotency_key LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db->prepare($sql);
        $statement->execute([':api_key_id' => $apiKeyId, ':idempotency_key' => $key]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function assertIdempotencyCompatible(int $apiKeyId, string $key, string $requestHash, int $licenseId): void
    {
        $existing = $this->idempotency($apiKeyId, $key, true);
        if ($existing) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash) || (int)($existing['license_id'] ?? 0) !== $licenseId) {
                throw new AdminApiException('IDEMPOTENCY_CONFLICT', 'The Idempotency-Key was used for a different request.', 409);
            }
            return;
        }
        $statement = $this->db->prepare('INSERT INTO admin_api_idempotency (api_key_id, idempotency_key, request_hash, license_id, created_at) VALUES (:api_key_id, :idempotency_key, :request_hash, :license_id, NOW())');
        $statement->execute([':api_key_id' => $apiKeyId, ':idempotency_key' => $key, ':request_hash' => $requestHash, ':license_id' => $licenseId]);
    }

    private function formatLicense(array $row, bool $includeKey): array
    {
        $effectiveStatus = (string)$row['status'];
        if ($row['deleted_at'] !== null) { $effectiveStatus = 'deleted'; }
        elseif ($row['banned_at'] !== null) { $effectiveStatus = 'banned'; }
        elseif (strtotime((string)$row['expires_at']) < time()) { $effectiveStatus = 'expired'; }
        $result = [
            'license_id' => (int)$row['id'],
            'license_key_masked' => $this->repository->maskLicense((string)$row['license_key']),
            'external_order_id' => (string)$row['external_order_id'],
            'customer_reference' => $row['customer_reference'],
            'app_id' => (string)$row['app_scope'],
            'status' => $effectiveStatus,
            'device_limit' => (int)$row['device_limit'],
            'active_devices' => (int)$row['total_devices'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'expires_at' => $row['expires_at'],
            'banned_at' => $row['banned_at'],
            'ban_reason' => $row['ban_reason'],
            'deleted_at' => $row['deleted_at'],
        ];
        if ($includeKey) {
            $decrypted = Security::decrypt((string)$row['encrypted_key']);
            $result['license_key'] = $decrypted !== '' ? $decrypted : (string)$row['license_key'];
        }
        return $result;
    }

    private function disableLicenseDevices(int $licenseId): void
    {
        $credentials = $this->db->prepare("UPDATE v2_device_credentials SET status = 'revoked', revoked_at = COALESCE(revoked_at, NOW()) WHERE license_id = :license_id");
        $credentials->execute([':license_id' => $licenseId]);
        $tokens = $this->db->prepare('UPDATE v2_refresh_tokens rt JOIN v2_device_credentials dc ON dc.id = rt.device_credential_id SET rt.revoked_at = COALESCE(rt.revoked_at, NOW()) WHERE dc.license_id = :license_id');
        $tokens->execute([':license_id' => $licenseId]);
        $legacy = $this->db->prepare('UPDATE devices SET is_active = 0, last_active = NOW() WHERE license_id = :license_id');
        $legacy->execute([':license_id' => $licenseId]);
        $this->syncTotalDevices($licenseId);
    }

    private function syncTotalDevices(int $licenseId): void
    {
        $statement = $this->db->prepare('UPDATE licenses SET total_devices = (SELECT COUNT(*) FROM devices WHERE license_id = :count_id AND is_active = 1), updated_at = NOW() WHERE id = :license_id');
        $statement->execute([':count_id' => $licenseId, ':license_id' => $licenseId]);
    }

    private function hourBounds(): array
    {
        $values = ['license_min_hours' => 1, 'license_max_hours' => 8760];
        try {
            $statement = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('license_min_hours', 'license_max_hours')");
            foreach ($statement->fetchAll() as $row) { $values[(string)$row['setting_key']] = (int)$row['setting_value']; }
        } catch (Throwable $exception) { error_log('Admin API license-bound settings fallback: ' . $exception->getMessage()); }
        $minimum = max(1, (int)$values['license_min_hours']);
        $maximum = max($minimum, min(87600, (int)$values['license_max_hours']));
        return [$minimum, $maximum];
    }

    private function identifier($value, string $field): string
    {
        $value = trim((string)$value);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,119}$/', $value)) {
            throw new AdminApiException('INVALID_REQUEST', 'Invalid ' . $field . '.', 400);
        }
        return $value;
    }

    private function optionalIdentifier($value, string $field): ?string
    {
        if ($value === null || trim((string)$value) === '') { return null; }
        return $this->identifier($value, $field);
    }

    private function newLicenseKey(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $segments = [];
            for ($index = 0; $index < 4; $index++) { $segments[] = strtoupper(bin2hex(random_bytes(4))); }
            $key = implode('-', $segments);
            $statement = $this->db->prepare('SELECT COUNT(*) FROM licenses WHERE license_key = :license_key');
            $statement->execute([':license_key' => $key]);
            if ((int)$statement->fetchColumn() === 0) { return $key; }
        }
        throw new RuntimeException('Unable to generate a unique license key.');
    }
}
