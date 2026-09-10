<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/includes/admin_api/bootstrap.php';

try {
    AdminApi::requireMethod('GET');
    AdminApi::requireHttps();
    [$repository, $service, $context] = licora_admin_api_services('');
    $apps = $service->allowedApps($context);
    $repository->audit((int)$context['id'], 'app_listed', 'license:read', 200, ['count' => count($apps)]);
    AdminApi::respond(200, 'OK', 'Allowed applications returned.', $apps);
} catch (Throwable $exception) {
    if (isset($repository) && $repository instanceof AdminApiRepository) {
        $repository->audit(isset($context['id']) ? (int)$context['id'] : null, 'app_list_failed', 'license:read', $exception instanceof AdminApiException ? $exception->httpStatus() : 500, ['reason' => $exception instanceof AdminApiException ? $exception->machineCode() : 'INTERNAL_ERROR']);
    }
    AdminApi::handle($exception);
}
