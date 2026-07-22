<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = Security::getClientIP();
    if (!Security::checkRateLimit($ip, 'check_license', API_RATE_LIMIT)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again later.']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $license_key = trim((string)($input['license_key'] ?? ''));
    $device_hash = $input['device_hash'] ?? Security::generateDeviceHash();

    if (!Security::validateLicenseFormat($license_key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid license key']);
        exit();
    }

    $system = new LicenseSystem();
    $result = $system->verifyLicense($license_key, $device_hash);

    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
