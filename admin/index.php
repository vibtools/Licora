<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/database.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$system = new LicenseSystem();
$stats = $system->getStats();

// API keys stats
$db = Database::getInstance();
$apiStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(is_active = 1) as active,
        SUM(is_active = 0) as inactive,
        SUM(request_count) as total_requests
    FROM api_keys
")->fetch();

// Recent API calls
$chartDaily = $db->query("SELECT DATE(created_at) d, COUNT(*) c FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
$chartExpired = $db->query("SELECT DATE(expires_at) d, COUNT(*) c FROM licenses WHERE expires_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY) GROUP BY DATE(expires_at) ORDER BY d ASC")->fetchAll();
$topLicenses = $db->query("SELECT l.license_key, COUNT(al.id) c FROM api_logs al LEFT JOIN licenses l ON al.license_key = l.license_key GROUP BY al.license_key, l.license_key ORDER BY c DESC LIMIT 5")->fetchAll();

$recentCalls = $db->query("
    SELECT l.license_key, a.name as api_key_name, 
           al.endpoint, al.response_code, al.created_at
    FROM api_logs al
    LEFT JOIN api_keys a ON al.api_key_id = a.id
    LEFT JOIN licenses l ON al.license_key = l.license_key
    ORDER BY al.created_at DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - License System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind = window.tailwind || {}; tailwind.config = { corePlugins: { preflight: false }, darkMode: 'class' };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
    <style>
        .stat-card { 
            transition: transform 0.3s, box-shadow 0.3s; 
            border-radius: 10px;
            overflow: hidden;
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .bg-license { background: linear-gradient(45deg, #4e54c8, #8f94fb); }
        .bg-device { background: linear-gradient(45deg, #11998e, #38ef7d); }
        .bg-api { background: linear-gradient(45deg, #f46b45, #eea849); }
        .bg-log { background: linear-gradient(45deg, #8e2de2, #4a00e0); }
    </style>
</head>
<body class="admin-ui">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid admin-shell">
        <div class="page-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($auth->getUsername()); ?>. Monitor licenses, devices, API usage, and system health.</p>
            </div>
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
                                <h2 class="stat-number"><?php echo $stats['total_licenses']; ?></h2>
                                <small>Active: <?php echo $stats['active_licenses']; ?></small>
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
                                <h6 class="card-title">Active Devices</h6>
                                <h2 class="stat-number"><?php echo $stats['active_devices']; ?></h2>
                                <small>Total: <?php echo $stats['total_devices']; ?></small>
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
                                <h2 class="stat-number"><?php echo $stats['expired_licenses']; ?></h2>
                                <small>Suspended: <?php echo $stats['suspended_licenses']; ?></small>
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
                                <small>Requests: <?php echo number_format($apiStats['total_requests']); ?></small>
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
                                <h6 class="card-title">System Status</h6>
                                <h2 class="stat-number"><?php echo ENVIRONMENT === 'production' ? 'Live' : 'Dev'; ?></h2>
                                <small>Version: <?php echo APP_VERSION; ?></small>
                            </div>
                            <i class="bi bi-shield-check stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        <div class="row g-3 mb-4">
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Daily API Requests</h5></div><div class="card-body"><canvas id="dailyApiChart" height="180"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Expired Trend</h5></div><div class="card-body"><canvas id="expiredTrendChart" height="180"></canvas></div></div></div>
            <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Top Used Licenses</h5></div><div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($topLicenses as $tl): ?><li class="list-group-item d-flex justify-content-between"><span><code><?php echo Security::escape(substr($tl['license_key'] ?? 'Unknown',0,18)); ?></code></span><span class="badge bg-primary"><?php echo (int)$tl['c']; ?></span></li><?php endforeach; ?></ul></div></div></div>
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
                        <h5 class="mb-0"><i class="bi bi-activity"></i> Recent API Calls</h5>
                        <a href="logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
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
                                        <td colspan="4"><div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-activity"></i></div><h6>No API calls yet</h6><p class="mb-0">Recent verification traffic will appear here.</p></div></td>
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
                        <?php if (!empty($recentCalls)): ?>
                        <nav class="mt-2" aria-label="Recent API calls pagination"><ul class="pagination pagination-sm justify-content-end mb-0" data-ui-pager-for="recent-calls-table"></ul></nav>
                        <?php endif; ?>
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
                                    <div class="me-3">
                                        <div class="p-2 bg-success rounded-circle">
                                            <i class="bi bi-database text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Database</h6>
                                        <small class="text-muted">Connected</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <div class="p-2 bg-success rounded-circle">
                                            <i class="bi bi-shield-check text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Security</h6>
                                        <small class="text-muted">Active</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <div class="p-2 bg-info rounded-circle">
                                            <i class="bi bi-clock-history text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">API Server</h6>
                                        <small class="text-muted">Running</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <div class="p-2 bg-warning rounded-circle">
                                            <i class="bi bi-gear text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Environment</h6>
                                        <small class="text-muted"><?php echo ENVIRONMENT; ?></small>
                                    </div>
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
const dailyLabels=<?php echo json_encode(array_column($chartDaily,'d')); ?>; const dailyData=<?php echo json_encode(array_map('intval', array_column($chartDaily,'c'))); ?>;
const expLabels=<?php echo json_encode(array_column($chartExpired,'d')); ?>; const expData=<?php echo json_encode(array_map('intval', array_column($chartExpired,'c'))); ?>;
if(document.getElementById('dailyApiChart')) new Chart(document.getElementById('dailyApiChart'),{type:'line',data:{labels:dailyLabels,datasets:[{label:'Requests',data:dailyData}]},options:{responsive:true,plugins:{legend:{display:false}}}});
if(document.getElementById('expiredTrendChart')) new Chart(document.getElementById('expiredTrendChart'),{type:'bar',data:{labels:expLabels,datasets:[{label:'Expired',data:expData}]},options:{responsive:true,plugins:{legend:{display:false}}}});
</script>
</body>
</html>