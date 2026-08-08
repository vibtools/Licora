<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';
require_once $root . '/includes/v2/V2Exception.php';
require_once $root . '/includes/v2/V2KeyManager.php';

$db = Database::getInstance();
$migration = $root . '/migration-v5.2.0-api-v2.sql';
if (!is_file($migration)) {
    fwrite(STDERR, "Missing migration-v5.2.0-api-v2.sql\n");
    exit(1);
}

$sql = (string)file_get_contents($migration);
$statements = array_filter(array_map('trim', explode(';', $sql)), static fn($value) => $value !== '');
try {
    foreach ($statements as $statement) {
        $db->exec($statement);
    }
    $manager = new V2KeyManager();
    $generated = $manager->generateIfMissing();
    $public = $manager->publicKeyPem();
    $fingerprint = hash('sha256', $public);
    echo "Licora API v2 setup complete.\n";
    echo "Database schema: ready\n";
    echo "Signing key pair: " . ($generated ? 'generated' : 'already present') . "\n";
    echo "Public signing-key SHA-256: {$fingerprint}\n";
    echo "Keep the private key server-side. Do not copy it into a desktop client or Git repository.\n";
} catch (Throwable $e) {
    error_log('Licora API v2 setup failed: ' . $e->getMessage());
    fwrite(STDERR, "Licora API v2 setup failed. Check the server error log.\n");
    exit(1);
}
