<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/dashboard.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$dashboard = new DashboardReadModel();
$dashboardSnapshot = $dashboard->snapshot();
$licenseStats = $dashboardSnapshot['licenses'];
$deviceStats = $dashboardSnapshot['devices'];
$apiStats = $dashboardSnapshot['api_keys'];
$apiActivity = $dashboardSnapshot['api_activity'];
$expiration = $dashboardSnapshot['expiration'];
$health = $dashboardSnapshot['health'];

$chartV1 = $apiActivity['v1_tracked']['last_14_days'];
$chartV2 = $apiActivity['v2_tracked']['last_14_days'];
$topLicenses = $apiActivity['v1_tracked']['top_licenses'];
$recentCalls = $apiActivity['v1_tracked']['recent_calls'];

$apiDates = [];
foreach (array_merge($chartV1, $chartV2) as $point) {
    if (!empty($point['date'])) {
        $apiDates[(string)$point['date']] = true;
    }
}
$apiLabels = array_keys($apiDates);
sort($apiLabels);
$seriesByDate = static function (array $series, array $labels): array {
    $indexed = [];
    foreach ($series as $point) {
        $indexed[(string)($point['date'] ?? '')] = (int)($point['count'] ?? 0);
    }
    return array_map(static fn(string $date): int => (int)($indexed[$date] ?? 0), $labels);
};
$apiV1Data = $seriesByDate($chartV1, $apiLabels);
$apiV2Data = $seriesByDate($chartV2, $apiLabels);

$expiredSeries = $expiration['expired_last_30_days'];
$expiringSeries = $expiration['expiring_next_30_days'];
$expirationDates = [];
foreach (array_merge($expiredSeries, $expiringSeries) as $point) {
    if (!empty($point['date'])) {
        $expirationDates[(string)$point['date']] = true;
    }
}
$expirationLabels = array_keys($expirationDates);
sort($expirationLabels);
$expiredData = $seriesByDate($expiredSeries, $expirationLabels);
$expiringData = $seriesByDate($expiringSeries, $expirationLabels);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid admin-shell">
        <div class="page-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div><h2><i class="bi bi-speedometer2"></i> Dashboard</h2></div>
            <a href="license.php?action=create" class="btn btn-light"><i class="bi bi-plus-circle"></i> Create License</a>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl col-md-6">
                <div class="card stat-card text-white bg-license">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Licenses</h6>
                                <h2 class="stat-number"><?php echo $licenseStats['total']; ?></h2>
                                <small>Active: <?php echo $licenseStats['active']; ?></small>
                            </div>
                            <i class="bi bi-key stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card stat-card text-white bg-device">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Recently Seen Devices</h6>
                                <h2 class="stat-number"><?php echo $deviceStats['recently_seen']; ?></h2>
                                <small>Active flagged: <?php echo $deviceStats['active_flagged']; ?> · Total: <?php echo $deviceStats['total_records']; ?></small>
                            </div>
                            <i class="bi bi-devices stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-xl col-md-6">
                <div class="card stat-card text-white bg-expired">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Expired Licenses</h6>
                                <h2 class="stat-number"><?php echo $licenseStats['expired']; ?></h2>
                                <small>Suspended: <?php echo $licenseStats['suspended']; ?></small>
                            </div>
                            <i class="bi bi-calendar-x stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
                        <div class="col-xl col-md-6">
                <div class="card stat-card text-white bg-api">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">API Keys</h6>
                                <h2 class="stat-number"><?php echo $apiStats['active']; ?></h2>
                                <small>Tracked v1 requests: <?php echo number_format($apiStats['tracked_v1_requests']); ?></small>
                            </div>
                            <i class="bi bi-key stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card stat-card text-white bg-log">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Environment</h6>
                                <h2 class="stat-number"><?php echo Security::escape(ucfirst((string)$health['environment']['value'])); ?></h2>
                                <small>Version: <?php echo APP_VERSION; ?></small>
                            </div>
                            <i class="bi bi-shield-check stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="row g-3 mb-4">
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Tracked API Activity</h5></div><div class="card-body"><canvas id="dailyApiChart" height="180"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Expiration Timeline</h5></div><div class="card-body"><canvas id="expiredTrendChart" height="180"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Top Licenses — API v1 Verify</h5></div><div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($topLicenses as $tl): ?><li class="list-group-item d-flex justify-content-between"><span><code><?php echo Security::escape(substr($tl['license_key'] ?? 'Unknown',0,18)); ?></code></span><span class="badge bg-primary"><?php echo (int)$tl['count']; ?></span></li><?php endforeach; ?></ul></div></div></div>
        </div>

        <!-- Quick Actions & Recent Activity -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="license.php?action=create" class="btn btn-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-plus-circle fs-1 mb-2"></i>
                                    <span>Create License</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="api_keys.php" class="btn btn-success btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-key fs-1 mb-2"></i>
                                    <span>API Keys</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="device.php" class="btn btn-info btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-devices fs-1 mb-2"></i>
                                    <span>Devices</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="logs.php" class="btn btn-warning btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-clock-history fs-1 mb-2"></i>
                                    <span>Activity Logs</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="audit.php" class="btn btn-outline-dark btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-journal-text fs-1 mb-2"></i>
                                    <span>Audit Trail</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="backup.php" class="btn btn-outline-dark btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-download fs-1 mb-2"></i>
                                    <span>Backup</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="health.php" class="btn btn-outline-dark btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                    <i class="bi bi-heart-pulse fs-1 mb-2"></i>
                                    <span>Health</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-activity"></i> Recent API v1 Verify Calls</h5>
                        <a href="logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive ui-scrollbar">
                            <table class="table table-sm table-hover" id="recent-calls-table" data-ui-paginate="true" data-ui-page-size="10">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Endpoint</th>
                                        <th>License</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentCalls)): ?>
                                    <tr>
                                        <td colspan="4"><div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-activity"></i></div><h6>No API calls yet</h6></div></td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentCalls as $call): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($call['created_at'])); ?></td>
                                        <td><code><?php echo htmlspecialchars($call['endpoint']); ?></code></td>
                                        <td>
                                            <?php if ($call['license_key']): ?>
                                            <small><?php echo substr(htmlspecialchars($call['license_key']), 0, 8) . '...'; ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">Test</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $call['response_code'] == 200 ? 'success' : ($call['response_code'] == 400 ? 'warning' : 'danger'); ?>">
                                                <?php echo $call['response_code']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-server"></i> System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3"><div class="p-2 bg-<?php echo $health['database']['ok'] ? 'success' : 'danger'; ?> rounded-circle"><i class="bi bi-database text-white"></i></div></div>
                                    <div><h6 class="mb-0">Database</h6><small class="text-muted"><?php echo Security::escape($health['database']['label']); ?></small></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3"><div class="p-2 bg-<?php echo $health['php']['ok'] ? 'success' : 'danger'; ?> rounded-circle"><i class="bi bi-code-slash text-white"></i></div></div>
                                    <div><h6 class="mb-0">PHP Runtime</h6><small class="text-muted"><?php echo Security::escape($health['php']['version']); ?></small></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3"><div class="p-2 bg-<?php echo $health['cron_scripts']['available'] ? 'success' : 'danger'; ?> rounded-circle"><i class="bi bi-clock-history text-white"></i></div></div>
                                    <div><h6 class="mb-0">Cron Scripts</h6><small class="text-muted"><?php echo $health['cron_scripts']['available'] ? 'Available' : 'Missing'; ?></small></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <?php $v2Ready = !empty($health['api_v2']['schema_ready']) && !empty($health['api_v2']['key_pair_ready']); ?>
                                    <div class="me-3"><div class="p-2 bg-<?php echo $v2Ready ? 'success' : 'warning'; ?> rounded-circle"><i class="bi bi-shield-check text-white"></i></div></div>
                                    <div><h6 class="mb-0">API v2</h6><small class="text-muted"><?php echo $v2Ready ? 'Ready' : 'Needs setup'; ?></small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/admin-ui.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
<script>
const apiLabels=<?php echo json_encode($apiLabels); ?>;
const apiV1Data=<?php echo json_encode($apiV1Data); ?>;
const apiV2Data=<?php echo json_encode($apiV2Data); ?>;
const expirationLabels=<?php echo json_encode($expirationLabels); ?>;
const expiredData=<?php echo json_encode($expiredData); ?>;
const expiringData=<?php echo json_encode($expiringData); ?>;
if(document.getElementById('dailyApiChart')) new Chart(document.getElementById('dailyApiChart'),{type:'line',data:{labels:apiLabels,datasets:[{label:'API v1 Verify',data:apiV1Data},{label:'API v2 Audit Events',data:apiV2Data}]},options:{responsive:true}});
if(document.getElementById('expiredTrendChart')) new Chart(document.getElementById('expiredTrendChart'),{type:'bar',data:{labels:expirationLabels,datasets:[{label:'Expired — Last 30 Days',data:expiredData},{label:'Expiring — Next 30 Days',data:expiringData}]},options:{responsive:true}});
</script>
</body>
</html>