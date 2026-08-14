<?php
declare(strict_types=1);

final class UpdateSchema
{
    private PDO $db;
    private string $migrationPath;

    private const TABLES = ['update_jobs', 'update_events', 'app_migrations'];

    public function __construct(PDO $db, ?string $migrationPath = null)
    {
        $this->db = $db;
        $this->migrationPath = $migrationPath ?: dirname(__DIR__, 2) . '/migration-v5.3.0-updater.sql';
    }

    public function status(): array
    {
        $missing = [];
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) { $missing[] = $table; }
        }
        return ['ready' => $missing === [], 'missing_tables' => $missing];
    }

    public function ensure(): array
    {
        // The updater is additive to an existing Licora installation. Its migration seeds
        // updater settings, so a valid core settings table is a hard prerequisite. Report a
        // controlled updater error instead of leaking an engine-level PDO failure.
        if (!$this->coreSettingsSchemaReady()) {
            throw new UpdateException(
                'UPDATER_BASE_SCHEMA_MISSING',
                'Licora core settings schema is missing or incomplete. Repair the base database before initializing the updater.',
                500
            );
        }

        $status = $this->status();
        if ($status['ready']) { return $status; }
        if (!is_readable($this->migrationPath)) {
            throw new UpdateException('UPDATER_SCHEMA_MISSING', 'Updater migration file is missing or unreadable.', 500);
        }
        foreach (self::splitSql((string)file_get_contents($this->migrationPath)) as $statement) {
            $this->db->exec($statement);
        }
        $status = $this->status();
        if (!$status['ready']) {
            throw new UpdateException('UPDATER_SCHEMA_FAILED', 'Updater database schema could not be initialized.', 500);
        }
        return $status;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute([':table' => $table]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function coreSettingsSchemaReady(): bool
    {
        if (!$this->tableExists('settings')) { return false; }
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME IN ('setting_key','setting_value')");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() !== 2) { return false; }

        // Updater settings and the coordinator mutex rely on one row per setting_key.
        // Confirm that setting_key participates in a single-column UNIQUE/PRIMARY index.
        $index = $this->db->query("SELECT COUNT(*) FROM (SELECT INDEX_NAME, COUNT(*) AS column_count, SUM(CASE WHEN COLUMN_NAME = 'setting_key' THEN 1 ELSE 0 END) AS key_count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING column_count = 1 AND key_count = 1) AS unique_setting_key_indexes");
        return (int)$index->fetchColumn() >= 1;
    }

    public static function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $delimiter = ';';
        $buffer = '';
        $statements = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
                $delimiter = $m[1];
                continue;
            }
            if (preg_match('/^\s*--/', $line)) { continue; }
            $buffer .= $line . "\n";
            $trimmed = rtrim($buffer);
            if ($delimiter !== '' && substr($trimmed, -strlen($delimiter)) === $delimiter) {
                $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
                if ($statement !== '') { $statements[] = $statement; }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') { $statements[] = trim($buffer); }
        return $statements;
    }
}
