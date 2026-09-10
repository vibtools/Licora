<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('GET');
    AdminApi::requireHttps();
    [$repository, $service, $context] = licora_admin_api_services('');
    $licenseId = AdminApi::queryPositiveInt('license_id');
    if ($licenseId === null) { throw new AdminApiException('INVALID_REQUEST', 'license_id is required.', 400); }
    $devices = $service->devices($context, $licenseId);
    $repository->audit((int)$context['id'], 'device_listed', 'device:read', 200, ['license_id' => $licenseId, 'count' => count($devices)]);
    AdminApi::respond(200, 'OK', 'Devices returned.', $devices);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'device_list_failed', 'device:read', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
