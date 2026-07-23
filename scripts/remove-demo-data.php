<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';

$pdo = Database::getInstance();
$pdo->beginTransaction();

try {
    $settingsStatement = $pdo->query(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('demo_data_installed','demo_api_key_id','demo_license_id')"
    );
    $settings = [];
    foreach ($settingsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }

    $licenseId = isset($settings['demo_license_id']) ? (int)$settings['demo_license_id'] : 0;
    $apiKeyId = isset($settings['demo_api_key_id']) ? (int)$settings['demo_api_key_id'] : 0;

    if ($licenseId > 0) {
        $deleteLicense = $pdo->prepare("DELETE FROM licenses WHERE id = :id AND notes LIKE '[DEMO CUSTOMER]%' ");
        $deleteLicense->execute([':id' => $licenseId]);
    }

    if ($apiKeyId > 0) {
        $deleteApiKey = $pdo->prepare("DELETE FROM api_keys WHERE id = :id AND name LIKE '[DEMO]%' ");
        $deleteApiKey->execute([':id' => $apiKeyId]);
    }

    $deleteSettings = $pdo->prepare(
        "DELETE FROM settings WHERE setting_key IN ('demo_data_installed','demo_api_key_id','demo_license_id')"
    );
    $deleteSettings->execute();

    $pdo->commit();
    fwrite(STDOUT, "Licora demo data removed.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Unable to remove Licora demo data.\n");
    exit(1);
}
