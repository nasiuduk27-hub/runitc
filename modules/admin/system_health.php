<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config.php';
require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
/** @var \PDO $pdo */
/** @var \PDO $pdo_bot */
/** @var \PDO $pdo_war */
/** @var array $ftp_config */
requireMenuAccess($pdo_run, 'modules/admin/system_health.php');
requireSuperadmin($pdo_run);

// API handler
if (isset($_GET['api'])) {
    $ctrl = new SystemHealthController($pdo, $pdo_bot, $pdo_run, $pdo_war, $ftp_config);
    $ctrl->handleApi();
    exit;
}

$ctrl = new SystemHealthController($pdo, $pdo_bot, $pdo_run, $pdo_war, $ftp_config);
$model = $ctrl->getModel();
$health = $model->checkAll();
$perf = $model->getPerformanceMetrics();

$pageTitle = 'System Health';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<style>
.status-ok { border-left-color: #17A34A; }
.status-warning { border-left-color: #D97706; }
.status-critical { border-left-color: #DC2626; }
.status-failed { border-left-color: #DC2626; }
.bg-ok { background-color: #17A34A; }
.bg-warning { background-color: #D97706; }
.bg-critical { background-color: #DC2626; }
.bg-failed { background-color: #DC2626; }
</style>

<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">System Health</h1>
                <p class="text-sm text-gray-500 mt-0.5">Monitor infrastructure & application health</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400" id="lastCheckTime">Last check: <?= date('H:i:s') ?></span>
                <button onclick="runHealthCheck()" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-sm">
                    <i class="fa-solid fa-rotate mr-1"></i>Run Check
                </button>
            </div>
        </div>

        <!-- Overall Status -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <?php
            $allOk = true;
            foreach ($health['databases'] as $db) {
                if ($db['status'] !== 'ok') $allOk = false;
            }
            if ($health['ftp']['status'] !== 'ok') $allOk = false;
            $overallColor = $allOk ? 'green' : 'red';
            ?>
            <div class="flex items-center gap-4">
                <div class="w-4 h-4 rounded-full bg-<?= $overallColor ?>-500"></div>
                <span class="font-bold text-lg text-gray-900">Overall Status: <span class="text-<?= $overallColor ?>-600"><?= $allOk ? 'All Systems Operational' : 'Issues Detected' ?></span></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Section 1: Databases -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900"><i class="fa-solid fa-database mr-2 text-brand-primary"></i>Database Connections</h2>
                </div>
                <div class="divide-y divide-gray-100" id="dbSection">
                    <?php foreach ($health['databases'] as $key => $db): ?>
                    <div class="p-4 border-l-4 status-<?= $db['status'] ?>">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-semibold text-gray-900"><?= htmlspecialchars($db['name']) ?></span>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold
                                    <?= $db['status'] === 'ok' ? 'bg-green-100 text-green-700' : ($db['status'] === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                                    <?= $db['status'] === 'ok' ? '✓ Connected' : ($db['status'] === 'failed' ? '✗ Failed' : '⚠ Slow') ?>
                                </span>
                            </div>
                            <button onclick="testDb('<?= $key ?>')" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Test</button>
                        </div>
                        <?php if ($db['response_time'] !== null): ?>
                        <p class="text-xs text-gray-500 mt-1">Response: <?= $db['response_time'] ?> ms</p>
                        <?php endif; ?>
                        <?php if ($db['error']): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($db['error']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-0.5" id="dbResult_<?= $key ?>"></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 2: FTP -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900"><i class="fa-solid fa-cloud mr-2 text-brand-primary"></i>FTP / File System</h2>
                </div>
                <div class="p-4 space-y-4">
                    <div class="border-l-4 status-<?= $health['ftp']['status'] ?> pl-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-gray-900">FTP Server</span>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold
                                    <?= $health['ftp']['status'] === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= $health['ftp']['status'] === 'ok' ? '✓ Connected' : '✗ Failed' ?>
                                </span>
                            </div>
                            <button onclick="testFtp()" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Test</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">File count: <?= $health['ftp']['file_count'] ?></p>
                        <?php if ($health['ftp']['error']): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($health['ftp']['error']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-0.5" id="ftpResult"></p>
                    </div>
                    <div class="border-l-4 status-ok pl-4">
                        <span class="font-semibold text-gray-900">Local Storage</span>
                        <div class="mt-1 text-xs text-gray-500">
                            <p>Available: <?= $health['storage']['available_space'] ?> MB / <?= $health['storage']['total_space'] ?> MB</p>
                            <p>Writable: <?= $health['storage']['writable'] ? '✓' : '✗' ?></p>
                            <p>Temp usage: <?= $health['storage']['temp_usage'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Performance -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900"><i class="fa-solid fa-gauge-high mr-2 text-brand-primary"></i>Performance</h2>
                </div>
                <div class="p-4 grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">PHP Version</span><br><span class="font-semibold"><?= htmlspecialchars($perf['php_version']) ?></span></div>
                    <div><span class="text-gray-500">Memory (current)</span><br><span class="font-semibold"><?= $perf['memory_usage'] ?> MB</span></div>
                    <div><span class="text-gray-500">Peak Memory</span><br><span class="font-semibold"><?= $perf['peak_memory'] ?> MB</span></div>
                    <div><span class="text-gray-500">Last check</span><br><span class="font-semibold"><?= $health['timestamp'] ?></span></div>
                </div>
            </div>

            <!-- Section 4: Recent Errors -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900"><i class="fa-solid fa-bug mr-2 text-brand-primary"></i>Recent PHP Errors</h2>
                </div>
                <div class="p-4 max-h-48 overflow-y-auto">
                    <?php if (empty($health['logs'])): ?>
                    <p class="text-xs text-gray-400 text-center py-4">No recent errors</p>
                    <?php else: ?>
                    <?php foreach ($health['logs'] as $log): ?>
                    <pre class="text-[10px] text-red-600 bg-red-50 p-2 rounded-lg mb-1 overflow-x-auto"><?= htmlspecialchars($log) ?></pre>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function runHealthCheck() {
    document.querySelectorAll('[id^=dbResult_]').forEach(el => el.textContent = 'Checking...');
    document.getElementById('ftpResult').textContent = 'Checking...';
    fetch('system_health.php?api=1&action=check_all')
        .then(r => r.json())
        .then(data => {
            document.getElementById('lastCheckTime').textContent = 'Last check: ' + new Date().toLocaleTimeString('id-ID');
            location.reload();
        })
        .catch(() => {});
}

function testDb(key) {
    fetch('system_health.php?api=1&action=test_db&db=' + key)
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('dbResult_' + key);
            if (data.status === 'ok') el.innerHTML = '<span class="text-green-600">✓ OK (' + data.response_time + 'ms)</span>';
            else if (data.status === 'failed') el.innerHTML = '<span class="text-red-600">✗ Failed: ' + (data.error || '') + '</span>';
            else el.innerHTML = '<span class="text-amber-600">⚠ Slow (' + data.response_time + 'ms)</span>';
        });
}

function testFtp() {
    fetch('system_health.php?api=1&action=test_ftp')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('ftpResult');
            if (data.status === 'ok') el.innerHTML = '<span class="text-green-600">✓ FTP OK (' + data.file_count + ' files)</span>';
            else el.innerHTML = '<span class="text-red-600">✗ Failed: ' + (data.error || '') + '</span>';
        });
}

tailwind.config = {
    theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6', card: '#ffffff' } } } }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
