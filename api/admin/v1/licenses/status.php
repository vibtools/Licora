<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('GET');
    AdminApi::requireHttps();
    [$repository, $service, $context] = licora_admin_api_services('');
    $licenseId = AdminApi::queryPositiveInt('license_id');
    $externalOrderId = isset($_GET['external_order_id']) ? (string)$_GET['external_order_id'] : null;
    $includeKey = isset($_GET['include_key']) && in_array(strtolower((string)$_GET['include_key']), ['1', 'true', 'yes'], true);
    $license = $service->get($context, $licenseId, $externalOrderId, $includeKey);
    $repository->audit((int)$context['id'], 'license_read', 'license:read', 200, ['license_id' => $license['license_id']]);
    AdminApi::respond(200, 'OK', 'License status returned.', $license);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'license_read_failed', 'license:read', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
