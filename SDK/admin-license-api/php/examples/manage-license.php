<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use VibTools\Licora\AdminApi\LicoraAdminApiException;
use VibTools\Licora\AdminApi\LicoraAdminClient;

$baseUrl = (string)getenv('LICORA_ADMIN_API_BASE_URL');
$apiKey = (string)getenv('LICORA_ADMIN_API_KEY');
if ($baseUrl === '' || $apiKey === '') {
    fwrite(STDERR, "Set LICORA_ADMIN_API_BASE_URL and LICORA_ADMIN_API_KEY.\n");
    exit(2);
}

$client = new LicoraAdminClient($baseUrl, $apiKey);

try {
    $response = $client->createLicense([
        'external_order_id' => 'ORDER-10482',
        'customer_reference' => 'USER-9081',
        'app_id' => 'vibrapilot',
        'validity_hours' => 720,
        'device_limit' => 2,
        'notes' => 'Paid order',
    ], 'checkout-ORDER-10482');

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (LicoraAdminApiException $exception) {
    fwrite(STDERR, sprintf(
        "Licora request failed: code=%s http=%d request_id=%s message=%s\n",
        $exception->getApiCode(),
        $exception->getHttpStatus(),
        $exception->getRequestId() ?? '-',
        $exception->getMessage()
    ));
    exit(1);
}

