<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('POST');
    AdminApi::requireHttps();
    [$data, $raw] = AdminApi::readJson();
    [$repository, $service, $context] = licora_admin_api_services($raw);
    $result = $service->create($context, $data, AdminApi::idempotencyKey());
    $status = !empty($result['idempotent_replay']) ? 200 : 201;
    $repository->audit((int)$context['id'], 'license_created', 'license:create', $status, [
        'license_id' => $result['license']['license_id'],
        'external_order_id' => $result['license']['external_order_id'],
        'app_id' => $result['license']['app_id'],
        'idempotent_replay' => (bool)$result['idempotent_replay'],
    ]);
    AdminApi::respond($status, !empty($result['idempotent_replay']) ? 'LICENSE_EXISTS' : 'LICENSE_CREATED', !empty($result['idempotent_replay']) ? 'Existing order license returned.' : 'License created.', $result['license']);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'license_create_failed', 'license:create', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
