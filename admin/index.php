<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/dashboard.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$dashboard = new DashboardReadModel();
$dashboardSnapshot = $dashboard->snapshot();
$licenseStats = $dashboardSnapshot['licenses'];
$deviceStats = $dashboardSnapshot['devices'];
$apiActivity = $dashboardSnapshot['api_activity'];
$expiration = $dashboardSnapshot['expiration'];
$health = $dashboardSnapshot['health'];
$topLicenses = $apiActivity['v1_tracked']['top_licenses'];

$recentActivity = [];
foreach ($dashboardSnapshot['recent_activity']['v1_tracked'] as $call) {
    $recentActivity[] = [
        'timestamp' => strtotime((string)($call['created_at'] ?? '')) ?: 0,
        'time' => (string)($call['created_at'] ?? ''),
        'source' => 'API v1',
        'action' => (string)($call['endpoint'] ?? 'verify'),
        'context' => !empty($call['license_key']) ? substr((string)$call['license_key'], 0, 12) . '…' : 'No license',
        'result' => (string)((int)($call['response_code'] ?? 0)),
        'result_class' => (int)($call['response_code'] ?? 0) === 200 ? 'success' : (((int)($call['response_code'] ?? 0) >= 400 && (int)($call['response_code'] ?? 0) < 500) ? 'warning' : 'danger'),
    ];
}
foreach ($dashboardSnapshot['recent_activity']['v2_tracked'] as $event) {
    $contextParts = [];
    if (!empty($event['app_id'])) {
        $contextParts[] = (string)$event['app_id'];
    }
    if ($event['license_id'] !== null) {
        $contextParts[] = 'License #' . (int)$event['license_id'];
    }
    $recentActivity[] = [
        'timestamp' => strtotime((string)($event['created_at'] ?? '')) ?: 0,
        'time' => (string)($event['created_at'] ?? ''),
        'source' => 'API v2',
        'action' => (string)($event['event_type'] ?? 'audit_event'),
        'context' => $contextParts !== [] ? implode(' · ', $contextParts) : 'Audit event',
        'result' => 'Recorded',
        'result_class' => 'primary',
    ];
}
usort($recentActivity, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
$recentActivity = array_slice($recentActivity, 0, 12);

$v2Ready = !empty($health['api_v2']['schema_ready']) && !empty($health['api_v2']['key_pair_ready']);
$initialPayload = [
    'success' => true,
    'generated_at' => $dashboardSnapshot['generated_at'],
    'data' => [
        'licenses' => $dashboardSnapshot['licenses'],
        'devices' => $dashboardSnapshot['devices'],
        'api_keys' => $dashboardSnapshot['api_keys'],
        'api_activity' => $dashboardSnapshot['api_activity'],
        'recent_activity' => $dashboardSnapshot['recent_activity'],
        'expiration' => $dashboardSnapshot['expiration'],
        'health' => $dashboardSnapshot['health'],
    ],
];
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
<body class="admin-ui dashboard-page">
    <?php include 'includes/navbar.php'; ?>

    <main class="container-fluid admin-shell" id="licora-dashboard" data-dashboard-endpoint="ajax/dashboard-data.php" data-dashboard-poll-ms="30000">
        <header class="page-hero dashboard-header">
            <div>
                <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                <p>License system overview</p>
            </div>
            <div class="dashboard-refresh-group">
                <div class="dashboard-refresh-meta" aria-live="polite" aria-atomic="true">
                    <span class="dashboard-refresh-label" data-dashboard-refresh-label>Last updated</span>
                    <strong data-dashboard-updated-at>—</strong>
                </div>
                <button type="button" class="btn btn-outline-primary" data-dashboard-refresh aria-label="Refresh dashboard data">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                    <span data-dashboard-refresh-text>Refresh</span>
                </button>
            </div>
        </header>

        <div class="dashboard-state" data-dashboard-state role="status" aria-live="polite" hidden>
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span data-dashboard-state-text></span>
            <a href="login.php" data-dashboard-signin hidden>Sign in</a>
        </div>

        <section class="dashboard-health-strip" aria-label="System status">
            <div class="dashboard-health-item <?php echo $health['database']['ok'] ? 'is-ok' : 'is-danger'; ?>" data-dashboard-health="database">
                <span class="dashboard-health-dot" aria-hidden="true"></span>
                <span>Database</span>
                <strong data-dashboard-health-value="database"><?php echo Security::escape($health['database']['label']); ?></strong>
            </div>
            <div class="dashboard-health-item <?php echo $v2Ready ? 'is-ok' : 'is-warning'; ?>" data-dashboard-health="api_v2">
                <span class="dashboard-health-dot" aria-hidden="true"></span>
                <span>API v2</span>
                <strong data-dashboard-health-value="api_v2"><?php echo $v2Ready ? 'Ready' : 'Needs setup'; ?></strong>
            </div>
            <div class="dashboard-health-item <?php echo $health['cron_scripts']['available'] ? 'is-ok' : 'is-danger'; ?>" data-dashboard-health="cron_scripts">
                <span class="dashboard-health-dot" aria-hidden="true"></span>
                <span>Cron Scripts</span>
                <strong data-dashboard-health-value="cron_scripts"><?php echo $health['cron_scripts']['available'] ? 'Available' : 'Missing'; ?></strong>
            </div>
            <div class="dashboard-health-item <?php echo $health['php']['ok'] ? 'is-ok' : 'is-danger'; ?>" data-dashboard-health="php">
                <span class="dashboard-health-dot" aria-hidden="true"></span>
                <span>PHP</span>
                <strong data-dashboard-health-value="php"><?php echo Security::escape($health['php']['version']); ?></strong>
            </div>
            <div class="dashboard-health-item is-neutral" data-dashboard-health="environment">
                <span class="dashboard-health-dot" aria-hidden="true"></span>
                <span>Environment</span>
                <strong data-dashboard-health-value="environment"><?php echo Security::escape(ucfirst((string)$health['environment']['value'])); ?></strong>
            </div>
        </section>

        <section class="dashboard-kpi-grid" aria-label="License overview">
            <article class="dashboard-kpi-card">
                <div class="dashboard-kpi-icon"><i class="bi bi-key" aria-hidden="true"></i></div>
                <div class="dashboard-kpi-copy">
                    <span class="dashboard-kpi-label">Total Licenses</span>
                    <strong class="dashboard-kpi-value" data-dashboard-kpi="total_licenses"><?php echo (int)$licenseStats['total']; ?></strong>
                    <span class="dashboard-kpi-meta">All license records</span>
                </div>
            </article>
            <article class="dashboard-kpi-card">
                <div class="dashboard-kpi-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                <div class="dashboard-kpi-copy">
                    <span class="dashboard-kpi-label">Active Licenses</span>
                    <strong class="dashboard-kpi-value" data-dashboard-kpi="active_licenses"><?php echo (int)$licenseStats['active']; ?></strong>
                    <span class="dashboard-kpi-meta">Expired: <span data-dashboard-kpi-meta="expired_licenses"><?php echo (int)$licenseStats['expired']; ?></span> · Suspended: <span data-dashboard-kpi-meta="suspended_licenses"><?php echo (int)$licenseStats['suspended']; ?></span></span>
                </div>
            </article>
            <article class="dashboard-kpi-card">
                <div class="dashboard-kpi-icon"><i class="bi bi-laptop" aria-hidden="true"></i></div>
                <div class="dashboard-kpi-copy">
                    <span class="dashboard-kpi-label">Recently Seen Devices</span>
                    <strong class="dashboard-kpi-value" data-dashboard-kpi="recent_devices"><?php echo (int)$deviceStats['recently_seen']; ?></strong>
                    <span class="dashboard-kpi-meta">5 min · Active flagged: <span data-dashboard-kpi-meta="active_devices"><?php echo (int)$deviceStats['active_flagged']; ?></span> · Total: <span data-dashboard-kpi-meta="total_devices"><?php echo (int)$deviceStats['total_records']; ?></span></span>
                </div>
            </article>
            <article class="dashboard-kpi-card">
                <div class="dashboard-kpi-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></div>
                <div class="dashboard-kpi-copy">
                    <span class="dashboard-kpi-label">Expiring Soon</span>
                    <strong class="dashboard-kpi-value" data-dashboard-kpi="expiring_soon"><?php echo (int)$licenseStats['expiring_soon']; ?></strong>
                    <span class="dashboard-kpi-meta">Next <?php echo (int)$licenseStats['expiring_soon_window_days']; ?> days</span>
                </div>
            </article>
        </section>

        <section class="dashboard-chart-grid" aria-label="Dashboard analytics">
            <article class="card dashboard-panel dashboard-api-panel">
                <div class="card-header">
                    <div>
                        <h5 class="mb-0">Tracked API Activity</h5>
                        <small>API v1 Verify and API v2 Audit Events · last 14 days</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-body">
                    <canvas id="dailyApiChart" role="img" aria-label="Tracked API activity over the last 14 days">Tracked API activity chart</canvas>
                </div>
            </article>
            <article class="card dashboard-panel dashboard-expiration-panel">
                <div class="card-header">
                    <div>
                        <h5 class="mb-0">Expiration Timeline</h5>
                        <small>Expired last 30 days · expiring next 30 days</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-body">
                    <canvas id="expiredTrendChart" role="img" aria-label="License expiration timeline">License expiration timeline chart</canvas>
                </div>
            </article>
        </section>

        <section class="dashboard-operations-grid">
            <article class="card dashboard-panel dashboard-activity-panel">
                <div class="card-header">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-activity" aria-hidden="true"></i> Recent Activity</h5>
                        <small>Tracked API v1 calls and API v2 audit events</small>
                    </div>
                    <a href="logs.php" class="btn btn-sm btn-outline-primary">View logs</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive ui-scrollbar">
                        <table class="table table-sm table-hover dashboard-activity-table mb-0">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Source</th>
                                    <th>Activity</th>
                                    <th>Context</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody data-dashboard-recent-activity>
                                <?php if ($recentActivity === []): ?>
                                <tr data-dashboard-empty-row><td colspan="5"><div class="dashboard-empty">No tracked activity yet.</div></td></tr>
                                <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?php echo $activity['time'] !== '' ? Security::escape(date('M j, H:i', strtotime($activity['time']))) : '—'; ?></td>
                                    <td><span class="dashboard-source-badge"><?php echo Security::escape($activity['source']); ?></span></td>
                                    <td><code><?php echo Security::escape($activity['action']); ?></code></td>
                                    <td><?php echo Security::escape($activity['context']); ?></td>
                                    <td><span class="badge bg-<?php echo Security::escape($activity['result_class']); ?>"><?php echo Security::escape($activity['result']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <aside class="card dashboard-panel dashboard-actions-panel">
                <div class="card-header">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Quick Actions</h5>
                        <small>Existing admin workflows</small>
                    </div>
                </div>
                <div class="card-body dashboard-quick-actions">
                    <a href="license.php?action=create" class="dashboard-action-link"><i class="bi bi-plus-circle" aria-hidden="true"></i><span><strong>Create License</strong><small>Issue a new license</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <a href="device.php" class="dashboard-action-link"><i class="bi bi-laptop" aria-hidden="true"></i><span><strong>Manage Devices</strong><small>Review device records</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <a href="api_keys.php" class="dashboard-action-link"><i class="bi bi-key" aria-hidden="true"></i><span><strong>API Keys</strong><small>Manage API v1 keys</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <a href="client_apps.php" class="dashboard-action-link"><i class="bi bi-boxes" aria-hidden="true"></i><span><strong>Client Apps</strong><small>Manage API v2 apps</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <a href="health.php" class="dashboard-action-link"><i class="bi bi-heart-pulse" aria-hidden="true"></i><span><strong>System Health</strong><small>Open health diagnostics</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </aside>
        </section>

        <section class="card dashboard-panel dashboard-top-licenses-panel">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Top Licenses — API v1 Verify</h5>
                    <small>Highest tracked API v1 verification volume</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive ui-scrollbar">
                    <table class="table table-sm table-hover mb-0 dashboard-top-licenses-table">
                        <thead><tr><th>License</th><th class="text-end">Tracked Requests</th></tr></thead>
                        <tbody data-dashboard-top-licenses>
                            <?php if ($topLicenses === []): ?>
                            <tr data-dashboard-empty-row><td colspan="2"><div class="dashboard-empty">No tracked API v1 license activity yet.</div></td></tr>
                            <?php else: ?>
                            <?php foreach ($topLicenses as $license): ?>
                            <tr>
                                <td><code><?php echo Security::escape(substr((string)($license['license_key'] ?? 'Unknown'), 0, 18)); ?></code></td>
                                <td class="text-end"><span class="badge bg-primary"><?php echo (int)($license['count'] ?? 0); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script type="application/json" id="dashboard-initial-data"><?php echo json_encode($initialPayload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/admin-ui.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
