<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config.php';
require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
/** @var \PDO $pdo */
/** @var \PDO $pdo_war */
requireMenuAccess($pdo_run, 'modules/admin/reporting.php');
requireSuperadmin($pdo_run);

require_once BASE_PATH . '/controllers/ReportingController.php';

// API: preview data
if (isset($_GET['preview'])) {
    $ctrl = new ReportingController($pdo, $pdo_run, $pdo_war);
    $ctrl->handlePreview();
    exit;
}

// Export handler
if (isset($_GET['export'])) {
    $ctrl = new ReportingController($pdo, $pdo_run, $pdo_war);
    $ctrl->handleExport();
    exit;
}

require_once BASE_PATH . '/models/AuditLog.php';
$auditLog = new AuditLog($pdo_run);
$distinctActions = $auditLog->getDistinctActions();

$defaultFrom = date('Y-m-d', strtotime('-7 days'));
$defaultTo = date('Y-m-d');

$pageTitle = 'Reporting';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Reporting & Export</h1>
            <p class="text-sm text-gray-500 mt-0.5">Generate & export laporan audit, operasional, dan aktivitas user</p>
        </div>

        <!-- Tab Navigation -->
        <div class="flex gap-1 bg-white rounded-2xl shadow-sm border border-gray-200 p-1" id="tabNav">
            <button onclick="switchTab('audit_log')" class="tab-btn active flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl transition" data-tab="audit_log">Audit Log</button>
            <button onclick="switchTab('operational')" class="tab-btn flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl transition" data-tab="operational">Operational</button>
            <button onclick="switchTab('user_activity')" class="tab-btn flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl transition" data-tab="user_activity">User Activity</button>
        </div>

        <!-- Audit Log Tab -->
        <div class="tab-content" id="tab_audit_log">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                <h2 class="font-bold text-gray-900">Audit Log Export</h2>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date From</label>
                        <input type="date" id="al_date_from" value="<?= $defaultFrom ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date To</label>
                        <input type="date" id="al_date_to" value="<?= $defaultTo ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Action</label>
                        <select id="al_action"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">Semua</option>
                            <?php foreach ($distinctActions as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="previewReport('audit_log')" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
                            <i class="fa-solid fa-eye mr-1"></i>Preview
                        </button>
                        <button onclick="exportReport('audit_log', 'csv')" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-sm">
                            <i class="fa-solid fa-download mr-1"></i>CSV
                        </button>
                    </div>
                </div>
                <div id="al_preview" class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-200 rounded-xl hidden">
                    <table class="w-full text-xs">
                        <thead><tr class="bg-gray-50 font-semibold text-gray-500 sticky top-0">
                            <th class="px-3 py-2 text-left">Timestamp</th><th class="px-3 py-2 text-left">Actor</th>
                            <th class="px-3 py-2 text-left">Action</th><th class="px-3 py-2 text-left">Target</th>
                        </tr></thead>
                        <tbody id="al_preview_body" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Operational Tab -->
        <div class="tab-content hidden" id="tab_operational">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                <h2 class="font-bold text-gray-900">Operational Summary Report</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date</label>
                        <input type="date" id="op_date" value="<?= date('Y-m-d') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div></div>
                    <div>
                        <button onclick="exportReport('operational', 'html')" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-sm">
                            <i class="fa-solid fa-file-export mr-1"></i>View / Export HTML
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Activity Tab -->
        <div class="tab-content hidden" id="tab_user_activity">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                <h2 class="font-bold text-gray-900">User Activity Report</h2>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date From</label>
                        <input type="date" id="ua_date_from" value="<?= $defaultFrom ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date To</label>
                        <input type="date" id="ua_date_to" value="<?= $defaultTo ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Action</label>
                        <select id="ua_action"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">Semua</option>
                            <?php foreach ($distinctActions as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="previewReport('user_activity')" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
                            <i class="fa-solid fa-eye mr-1"></i>Preview
                        </button>
                        <button onclick="exportReport('user_activity', 'csv')" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-sm">
                            <i class="fa-solid fa-download mr-1"></i>CSV
                        </button>
                    </div>
                </div>
                <div id="ua_preview" class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-200 rounded-xl hidden">
                    <table class="w-full text-xs">
                        <thead><tr class="bg-gray-50 font-semibold text-gray-500 sticky top-0">
                            <th class="px-3 py-2 text-left">Timestamp</th><th class="px-3 py-2 text-left">User</th>
                            <th class="px-3 py-2 text-left">Action</th><th class="px-3 py-2 text-left">Resource</th>
                        </tr></thead>
                        <tbody id="ua_preview_body" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active', 'bg-brand-primary', 'text-white'));
    document.getElementById('tab_' + tab).classList.remove('hidden');
    document.querySelector('.tab-btn[data-tab="' + tab + '"]')?.classList.add('active', 'bg-brand-primary', 'text-white');
}

// Set initial active tab
document.querySelector('.tab-btn.active')?.classList.add('bg-brand-primary', 'text-white');

function previewReport(type) {
    const params = new URLSearchParams();
    params.set('preview', '1');
    params.set('type', type);
    if (type === 'audit_log' || type === 'user_activity') {
        params.set('date_from', document.getElementById(type === 'audit_log' ? 'al_date_from' : 'ua_date_from').value);
        params.set('date_to', document.getElementById(type === 'audit_log' ? 'al_date_to' : 'ua_date_to').value);
        params.set('action', document.getElementById(type === 'audit_log' ? 'al_action' : 'ua_action').value);
    }

    const container = document.getElementById(type === 'audit_log' ? 'al_preview' : 'ua_preview');
    const body = document.getElementById(type === 'audit_log' ? 'al_preview_body' : 'ua_preview_body');
    container.classList.remove('hidden');
    body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Loading...</td></tr>';

    fetch('reporting.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.rows || !data.rows.length) {
                body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Tidak ada data</td></tr>';
                return;
            }
            body.innerHTML = data.rows.map(r => `<tr>
                <td class="px-3 py-2 text-gray-600">${r.created_at || '-'}</td>
                <td class="px-3 py-2 text-gray-800">${r.account_nm || 'Unknown'}</td>
                <td class="px-3 py-2"><span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-700">${r.action || '-'}</span></td>
                <td class="px-3 py-2 text-gray-500">${(r.target_type || '') + (r.target_id ? '#' + r.target_id : '')}</td>
            </tr>`).join('');
        })
        .catch(() => body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-red-500">Error loading preview</td></tr>');
}

function exportReport(type, format) {
    const params = new URLSearchParams();
    params.set('export', '1');
    params.set('type', type);
    params.set('format', format);

    if (type === 'audit_log') {
        params.set('date_from', document.getElementById('al_date_from').value);
        params.set('date_to', document.getElementById('al_date_to').value);
        params.set('action', document.getElementById('al_action').value);
    } else if (type === 'user_activity') {
        params.set('date_from', document.getElementById('ua_date_from').value);
        params.set('date_to', document.getElementById('ua_date_to').value);
        params.set('action', document.getElementById('ua_action').value);
    } else if (type === 'operational') {
        params.set('date', document.getElementById('op_date').value);
    }

    window.open('reporting.php?' + params.toString(), '_blank');
}

tailwind.config = {
    theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6', card: '#ffffff' } } } }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
