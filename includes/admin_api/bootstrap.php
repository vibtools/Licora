<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/security.php';
require_once __DIR__ . '/AdminApiException.php';
require_once __DIR__ . '/AdminApi.php';
require_once __DIR__ . '/AdminApiRepository.php';
require_once __DIR__ . '/AdminLicenseService.php';

function licora_admin_api_services(string $rawBody): array
{
    $repository = new AdminApiRepository(Database::getInstance());
    $repository->requireSchema();
    $token = AdminApi::bearerToken();
    $context = $repository->authenticate($token, $rawBody);
    return [$repository, new AdminLicenseService($repository), $context];
}
