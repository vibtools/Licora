<?php
declare(strict_types=1);

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load config, but bypass normal fatal errors if possible
try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/database.php';
    
    // Test database connection
    $db = Database::getInstance();
    $stmt = $db->query('SELECT 1');
    if (!$stmt) {
        throw new Exception("Database query failed.");
    }
    
    // All checks passed
    http_response_code(200);
    echo json_encode([
        'status' => 'pass',
        'database' => 'connected',
        'timestamp' => time()
    ]);
    exit(0);

} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'fail',
        'error' => $e->getMessage()
    ]);
    exit(1);
}
