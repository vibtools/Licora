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
require_once $root . '/includes/v2/V2Provisioner.php';

try {
    $db = Database::getInstance();
    $manager = new V2KeyManager();
    $provisioner = new V2Provisioner($db, $manager, $root . '/migration-v5.2.0-api-v2.sql');
    $status = $provisioner->provision(false);
    $public = $manager->publicKeyPem();
    $fingerprint = hash('sha256', $public);
    echo "Licora API v2 setup complete.\n";
    echo "Database schema: ready\n";
    echo "Signing key pair: " . (!empty($status['signing_keys_generated']) ? 'generated' : 'already present and verified') . "\n";
    echo "Public signing-key SHA-256: {$fingerprint}\n";
    echo "Keep the private key server-side. Do not copy it into a desktop client or Git repository.\n";
} catch (Throwable $e) {
    error_log('Licora API v2 setup failed: ' . $e->getMessage());
    fwrite(STDERR, "Licora API v2 setup failed. Check database connectivity, private configuration, includes-directory permissions, and the server error log.\n");
    exit(1);
}
