<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/installation.php';

echo "Checking database state...\n";

$state = licora_installation_database_state();

if (!$state['connected']) {
    echo "Database not connected yet. Skipping auto-init.\n";
    exit(0);
}

if ($state['tables_valid']) {
    echo "Database tables already valid. Skipping auto-init.\n";
    exit(0);
}

echo "Database connected but tables are missing. Auto-initializing schema...\n";

try {
    $pdo = new PDO(
        licora_installation_dsn((string)DB_HOST, (int)DB_PORT, (string)DB_NAME),
        (string)DB_USER,
        (string)DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    licora_installer_execute_schema($pdo, __DIR__ . '/../database.sql');
    echo "Database schema initialized successfully.\n";
} catch (Throwable $e) {
    echo "Failed to auto-initialize database: " . $e->getMessage() . "\n";
    exit(1);
}
