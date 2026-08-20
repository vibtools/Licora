<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/dashboard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

function dashboard_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    header('Allow: GET');
    dashboard_json(405, [
        'success' => false,
        'code' => 'METHOD_NOT_ALLOWED',
        'message' => 'Dashboard data requires GET.',
    ]);
}

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    dashboard_json(401, [
        'success' => false,
        'code' => 'AUTH_REQUIRED',
        'message' => 'Administrator login is required.',
    ]);
}

try {
    $model = new DashboardReadModel();
    $snapshot = $model->snapshot();
    dashboard_json(200, [
        'success' => true,
        'generated_at' => $snapshot['generated_at'],
        'data' => [
            'licenses' => $snapshot['licenses'],
            'devices' => $snapshot['devices'],
            'api_keys' => $snapshot['api_keys'],
            'api_activity' => $snapshot['api_activity'],
            'recent_activity' => $snapshot['recent_activity'],
            'expiration' => $snapshot['expiration'],
            'health' => $snapshot['health'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Dashboard data endpoint failure: ' . get_class($e) . ' ' . $e->getMessage());
    dashboard_json(500, [
        'success' => false,
        'code' => 'DASHBOARD_DATA_ERROR',
        'message' => 'Dashboard data could not be refreshed.',
    ]);
}
