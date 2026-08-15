<?php
declare(strict_types=1);
require_once '../../includes/auth.php';
require_once '../../includes/security.php';
require_once '../../includes/admin_helpers.php';
require_once '../../includes/v2/V2KeyManager.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { http_response_code(401); exit('Authentication required'); }
AdminHelpers::requireDelete();

try {
    $keys = new V2KeyManager();
    $pem = $keys->publicKeyPem();
    AdminHelpers::audit('security', null, 'v2_public_key_downloaded', 'API v2 public signing key downloaded');
    header('Content-Type: application/x-pem-file');
    header('Content-Disposition: attachment; filename="licora-api-v2-public-key.pem"');
    header('Content-Length: ' . strlen($pem));
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $pem;
} catch (Throwable $e) {
    error_log('API v2 public-key download failed: ' . $e->getMessage());
    http_response_code(404);
    echo 'API v2 public key is not available.';
}
