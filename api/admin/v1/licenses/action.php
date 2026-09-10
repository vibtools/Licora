<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('POST');
    AdminApi::requireHttps();
    [$data, $raw] = AdminApi::readJson();
    AdminApi::assertFields($data, ['license_id', 'action'], ['license_id', 'action', 'additional_hours', 'reason']);
    [$repository, $service, $context] = licora_admin_api_services($raw);
    $licenseId = filter_var($data['license_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($licenseId === false) { throw new AdminApiException('INVALID_REQUEST', 'Invalid license_id.', 400); }
    $action = strtolower(trim((string)$data['action']));
    $actionInput = array_diff_key($data, ['license_id' => true, 'action' => true]);
    $license = $service->action($context, (int)$licenseId, $action, $actionInput);
    $scope = 'license:' . $action;
    $repository->audit((int)$context['id'], 'license_' . $action, $scope, 200, ['license_id' => (int)$licenseId]);
    $codes = [
        'activate' => 'LICENSE_ACTIVATED', 'suspend' => 'LICENSE_SUSPENDED',
        'extend' => 'LICENSE_EXTENDED', 'ban' => 'LICENSE_BANNED', 'delete' => 'LICENSE_DELETED',
    ];
    AdminApi::respond(200, $codes[$action], 'License action completed.', $license);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'license_action_failed', isset($action) ? 'license:' . $action : null, $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
