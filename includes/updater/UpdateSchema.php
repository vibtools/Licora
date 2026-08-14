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
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
            $stmt->execute([':table' => $table]);
            if ((int)$stmt->fetchColumn() !== 1) { $missing[] = $table; }
        }
        return ['ready' => $missing === [], 'missing_tables' => $missing];
    }

    public function ensure(): array
    {
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
