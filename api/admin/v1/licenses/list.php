<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('GET');
    AdminApi::requireHttps();
    [$repository, $service, $context] = licora_admin_api_services('');
    $result = $service->list($context, [
        'page' => AdminApi::queryPositiveInt('page', 1),
        'per_page' => AdminApi::queryPositiveInt('per_page', 25),
        'app_id' => $_GET['app_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'customer_reference' => $_GET['customer_reference'] ?? null,
    ]);
    $repository->audit((int)$context['id'], 'license_listed', 'license:read', 200, ['page' => $result['page'], 'count' => count($result['items'])]);
    AdminApi::respond(200, 'OK', 'Licenses returned.', $result['items'], [
        'page' => $result['page'], 'per_page' => $result['per_page'], 'total' => $result['total'], 'pages' => $result['pages'],
    ]);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'license_list_failed', 'license:read', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
