<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('POST');
    AdminApi::requireHttps();
    [$data, $raw] = AdminApi::readJson();
    AdminApi::assertFields($data, ['credential_id'], ['credential_id']);
    [$repository, $service, $context] = licora_admin_api_services($raw);
    $credentialId = filter_var($data['credential_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($credentialId === false) { throw new AdminApiException('INVALID_REQUEST', 'Invalid credential_id.', 400); }
    $device = $service->revokeDevice($context, (int)$credentialId);
    $repository->audit((int)$context['id'], 'device_revoked', 'device:revoke', 200, $device);
    AdminApi::respond(200, 'DEVICE_REVOKED', 'Device revoked.', $device);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'device_revoke_failed', 'device:revoke', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
