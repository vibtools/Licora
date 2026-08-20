<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/v2/V2KeyManager.php';

final class DashboardReadModel
{
    public const DEVICE_RECENCY_SECONDS = 300;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function snapshot(): array
    {
        $apiActivity = $this->apiActivity();

        return [
            'generated_at' => date(DATE_ATOM),
            'licenses' => $this->licenseSummary(),
            'devices' => $this->deviceSummary(),
            'api_keys' => $this->apiKeySummary(),
            'api_activity' => $apiActivity,
            'recent_activity' => [
                'v1_tracked' => $apiActivity['v1_tracked']['recent_calls'],
                'v2_tracked' => $apiActivity['v2_tracked']['recent_events'],
            ],
            'expiration' => $this->expirationTimeline(),
            'health' => $this->healthFacts(),
        ];
    }

    public function licenseSummary(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'active' AND expires_at > NOW()) AS active,
                SUM(expires_at <= NOW()) AS expired,
                SUM(status = 'suspended') AS suspended,
                SUM(status = 'active' AND expires_at > NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)) AS expiring_soon
             FROM licenses"
        )->fetch() ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'expired' => (int)($row['expired'] ?? 0),
            'suspended' => (int)($row['suspended'] ?? 0),
            'expiring_soon' => (int)($row['expiring_soon'] ?? 0),
            'expiring_soon_window_days' => 30,
        ];
    }

    public function deviceSummary(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total_records,
                    SUM(is_active = 1) AS active_flagged
             FROM devices"
        )->fetch() ?: [];

        $recentSql = "SELECT COUNT(*) FROM devices d
                      WHERE d.is_active = 1
                        AND d.last_active >= DATE_SUB(NOW(), INTERVAL " . self::DEVICE_RECENCY_SECONDS . " SECOND)";

        $v2Available = $this->tableExists('v2_device_credentials');
        if ($v2Available) {
            $recentSql = "SELECT COUNT(*)
                          FROM devices d
                          LEFT JOIN (
                              SELECT license_id, device_hash, MAX(last_seen_at) AS last_seen_at
                              FROM v2_device_credentials
                              WHERE status = 'active'
                              GROUP BY license_id, device_hash
                          ) v2
                            ON v2.license_id = d.license_id
                           AND v2.device_hash = d.device_hash
                          WHERE d.is_active = 1
                            AND (
                                d.last_active >= DATE_SUB(NOW(), INTERVAL " . self::DEVICE_RECENCY_SECONDS . " SECOND)
                                OR v2.last_seen_at >= DATE_SUB(NOW(), INTERVAL " . self::DEVICE_RECENCY_SECONDS . " SECOND)
                            )";
        }

        return [
            'total_records' => (int)($row['total_records'] ?? 0),
            'active_flagged' => (int)($row['active_flagged'] ?? 0),
            'recently_seen' => (int)$this->db->query($recentSql)->fetchColumn(),
            'recency_window_seconds' => self::DEVICE_RECENCY_SECONDS,
            'v2_presence_source_available' => $v2Available,
        ];
    }

    public function apiKeySummary(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(is_active = 1) AS active,
                    SUM(is_active = 0) AS inactive,
                    COALESCE(SUM(request_count), 0) AS tracked_v1_requests
             FROM api_keys"
        )->fetch() ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'inactive' => (int)($row['inactive'] ?? 0),
            'tracked_v1_requests' => (int)($row['tracked_v1_requests'] ?? 0),
        ];
    }

    public function apiActivity(): array
    {
        $v1Daily = $this->rows(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM api_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );

        $topLicenses = $this->rows(
            "SELECT l.license_key, COUNT(al.id) AS c
             FROM api_logs al
             LEFT JOIN licenses l ON al.license_key = l.license_key
             GROUP BY al.license_key, l.license_key
             ORDER BY c DESC
             LIMIT 5"
        );

        $recentCalls = $this->rows(
            "SELECT l.license_key, a.name AS api_key_name,
                    al.endpoint, al.response_code, al.created_at
             FROM api_logs al
             LEFT JOIN api_keys a ON al.api_key_id = a.id
             LEFT JOIN licenses l ON al.license_key = l.license_key
             ORDER BY al.created_at DESC
             LIMIT 10"
        );

        $v2Available = $this->tableExists('v2_audit_logs');
        $v2Daily = [];
        $v2Recent = [];
        if ($v2Available) {
            $v2Daily = $this->rows(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c
                 FROM v2_audit_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY d ASC"
            );
            $v2Recent = $this->rows(
                "SELECT event_type, app_id, license_id, request_id, created_at
                 FROM v2_audit_logs
                 ORDER BY created_at DESC, id DESC
                 LIMIT 10"
            );
        }

        return [
            'legacy_check_license_included' => false,
            'v1_tracked' => [
                'available' => true,
                'source' => 'api_logs',
                'last_14_days' => $this->normalizeCountSeries($v1Daily),
                'total_last_14_days' => $this->sumSeries($v1Daily),
                'top_licenses' => $this->normalizeTopLicenses($topLicenses),
                'recent_calls' => $this->normalizeV1RecentCalls($recentCalls),
            ],
            'v2_tracked' => [
                'available' => $v2Available,
                'source' => 'v2_audit_logs',
                'last_14_days' => $this->normalizeCountSeries($v2Daily),
                'total_last_14_days' => $this->sumSeries($v2Daily),
                'recent_events' => $this->normalizeV2RecentEvents($v2Recent),
            ],
        ];
    }

    public function expirationTimeline(): array
    {
        $expired = $this->rows(
            "SELECT DATE(expires_at) AS d, COUNT(*) AS c
             FROM licenses
             WHERE expires_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND expires_at <= NOW()
             GROUP BY DATE(expires_at)
             ORDER BY d ASC"
        );
        $expiring = $this->rows(
            "SELECT DATE(expires_at) AS d, COUNT(*) AS c
             FROM licenses
             WHERE expires_at > NOW()
               AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(expires_at)
             ORDER BY d ASC"
        );

        return [
            'expired_last_30_days' => $this->normalizeCountSeries($expired),
            'expiring_next_30_days' => $this->normalizeCountSeries($expiring),
        ];
    }

    public function healthFacts(): array
    {
        $dbOk = false;
        try {
            $dbOk = (int)$this->db->query('SELECT 1')->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('Dashboard database health probe failed: ' . $e->getMessage());
        }

        $v2Tables = ['v2_client_apps', 'v2_device_credentials', 'v2_refresh_tokens', 'v2_used_nonces', 'v2_audit_logs'];
        $v2SchemaReady = true;
        foreach ($v2Tables as $table) {
            if (!$this->tableExists($table)) {
                $v2SchemaReady = false;
                break;
            }
        }

        $v2PublicKeyReady = false;
        $v2KeyPairReady = false;
        try {
            $keys = new V2KeyManager();
            if (is_file($keys->publicPath()) && is_readable($keys->publicPath())) {
                $keys->requirePublicKey();
                $v2PublicKeyReady = true;
            }
            if ($v2PublicKeyReady && is_file($keys->privatePath()) && is_readable($keys->privatePath())) {
                $keys->assertPairMatches();
                $v2KeyPairReady = true;
            }
        } catch (Throwable $e) {
            error_log('Dashboard API v2 key readiness check failed: ' . $e->getMessage());
        }

        return [
            'database' => ['ok' => $dbOk, 'label' => $dbOk ? 'Connected' : 'Unavailable'],
            'php' => [
                'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
                'version' => PHP_VERSION,
                'minimum' => '8.0',
            ],
            'environment' => ['value' => defined('ENVIRONMENT') ? (string)ENVIRONMENT : 'unknown'],
            'config_local' => ['present' => is_file(__DIR__ . '/config.local.php')],
            'cron_scripts' => [
                'available' => is_file(dirname(__DIR__) . '/cron/cleanup.php') && is_file(dirname(__DIR__) . '/cron/check_expiring.php'),
                'execution_verified' => false,
            ],
            'api_v2' => [
                'schema_ready' => $v2SchemaReady,
                'public_key_ready' => $v2PublicKeyReady,
                'key_pair_ready' => $v2KeyPairReady,
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $stmt->execute([':table' => $table]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('Dashboard schema check failed for ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }

    private function rows(string $sql): array
    {
        $rows = $this->db->query($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function normalizeCountSeries(array $rows): array
    {
        return array_map(static function (array $row): array {
            return ['date' => (string)($row['d'] ?? ''), 'count' => (int)($row['c'] ?? 0)];
        }, $rows);
    }

    private function sumSeries(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += (int)($row['c'] ?? 0);
        }
        return $sum;
    }

    private function normalizeTopLicenses(array $rows): array
    {
        return array_map(static function (array $row): array {
            return ['license_key' => $row['license_key'] !== null ? (string)$row['license_key'] : null, 'count' => (int)($row['c'] ?? 0)];
        }, $rows);
    }

    private function normalizeV1RecentCalls(array $rows): array
    {
        return array_map(static function (array $row): array {
            return [
                'license_key' => $row['license_key'] !== null ? (string)$row['license_key'] : null,
                'api_key_name' => $row['api_key_name'] !== null ? (string)$row['api_key_name'] : null,
                'endpoint' => (string)($row['endpoint'] ?? ''),
                'response_code' => (int)($row['response_code'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    private function normalizeV2RecentEvents(array $rows): array
    {
        return array_map(static function (array $row): array {
            return [
                'event_type' => (string)($row['event_type'] ?? ''),
                'app_id' => $row['app_id'] !== null ? (string)$row['app_id'] : null,
                'license_id' => $row['license_id'] !== null ? (int)$row['license_id'] : null,
                'request_id' => $row['request_id'] !== null ? (string)$row['request_id'] : null,
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }, $rows);
    }
}
