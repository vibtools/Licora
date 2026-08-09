<?php
declare(strict_types=1);

final class V2Provisioner
{
    private PDO $db;
    private V2KeyManager $keys;
    private string $migrationPath;

    private const TABLES = [
        'v2_client_apps',
        'v2_device_credentials',
        'v2_refresh_tokens',
        'v2_used_nonces',
        'v2_audit_logs',
    ];

    public function __construct(PDO $db, V2KeyManager $keys, ?string $migrationPath = null)
    {
        $this->db = $db;
        $this->keys = $keys;
        $this->migrationPath = $migrationPath ?: dirname(__DIR__, 2) . '/migration-v5.2.0-api-v2.sql';
    }

    public function status(): array
    {
        $missing = [];
        foreach (self::TABLES as $table) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $stmt->execute([':table' => $table]);
            if ((int)$stmt->fetchColumn() !== 1) {
                $missing[] = $table;
            }
        }

        $privateExists = is_file($this->keys->privatePath());
        $publicExists = is_file($this->keys->publicPath());
        $keyPairReady = false;
        $keyPairProblem = '';
        if ($privateExists && $publicExists) {
            try {
                $this->keys->assertPairMatches();
                $keyPairReady = true;
            } catch (Throwable $e) {
                $keyPairProblem = 'Signing key pair is invalid or mismatched.';
            }
        } elseif ($privateExists xor $publicExists) {
            $keyPairProblem = 'Only one signing key file exists; automatic replacement is refused.';
        }

        return [
            'schema_ready' => $missing === [],
            'missing_tables' => $missing,
            'private_key_exists' => $privateExists,
            'public_key_exists' => $publicExists,
            'key_pair_ready' => $keyPairReady,
            'key_pair_problem' => $keyPairProblem,
            'ready' => $missing === [] && $keyPairReady,
        ];
    }

    public function provision(bool $allowWebKeyGeneration = false): array
    {
        $this->applyMigration();
        $generated = $this->keys->generateIfMissing($allowWebKeyGeneration);
        $this->keys->assertPairMatches();
        $status = $this->status();
        if (!$status['ready']) {
            throw new RuntimeException('Licora API v2 provisioning did not reach a ready state.');
        }
        $status['signing_keys_generated'] = $generated;
        return $status;
    }

    public function applyMigration(): void
    {
        if (!is_file($this->migrationPath) || !is_readable($this->migrationPath)) {
            throw new RuntimeException('API v2 migration file is missing or unreadable.');
        }
        $sql = (string)file_get_contents($this->migrationPath);
        $statements = self::migrationStatements($sql);
        if ($statements === []) {
            throw new RuntimeException('API v2 migration contains no executable statements.');
        }
        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
    }

    public static function migrationStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $statements[] = $statement;
        }
        return $statements;
    }
}
