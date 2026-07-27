<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/audit_log.php');
requireSuperadmin($pdo_run);

require_once BASE_PATH . '/models/AuditLog.php';
$auditLog = new AuditLog($pdo_run);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$filters = [
    'action'       => $_GET['action'] ?? '',
    'search'       => $_GET['search'] ?? '',
    'date_from'    => $_GET['date_from'] ?? '',
    'date_to'      => $_GET['date_to'] ?? '',
    'actor_user_id' => $_GET['actor_user_id'] ?? '',
];

$result = $auditLog->getFiltered($filters, $page, $perPage);
$logs = $result['rows'];
$total = $result['total'];
$totalPages = max(1, ceil($total / $perPage));

$distinctActions = $auditLog->getDistinctActions();

function auditActionClass(?string $action): string
{
    $action = $action ?? '';

    if (str_starts_with($action, 'TEST_PLAN_')) {
        return 'bg-emerald-100 text-emerald-700';
    }
    if (str_starts_with($action, 'TEST_ADMIN_')) {
        return 'bg-indigo-100 text-indigo-700';
    }
    if (str_starts_with($action, 'LOGIN_')) {
        return $action === 'LOGIN_SUCCESS' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    }
    if ($action === 'LOGOUT') {
        return 'bg-slate-100 text-slate-700';
    }
    if (str_starts_with($action, 'USER_')) {
        return 'bg-blue-100 text-blue-700';
    }
    if (str_starts_with($action, 'ROLE_')) {
        return 'bg-purple-100 text-purple-700';
    }
    if (str_starts_with($action, 'MENU_')) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-gray-100 text-gray-700';
}

function auditActivitySummary(array $log): string
{
    $meta = [];
    if (!empty($log['metadata_json'])) {
        $decoded = json_decode((string) $log['metadata_json'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $action = (string) ($log['action'] ?? '');
    if (str_starts_with($action, 'TEST_PLAN_')) {
        $actor = $meta['input_by_name'] ?? $meta['updated_by_name'] ?? $meta['deleted_by_name'] ?? $log['account_nm'] ?? 'Unknown';
        $target = $meta['tad_name'] ?? $meta['old_tad_name'] ?? ('TAD #' . ($log['target_id'] ?? '-'));

        return $actor . ' input/ubah data untuk ' . $target;
    }

    if ($action === 'TEST_ADMIN_DISTRIBUTE') {
        $actor = $meta['distributed_by_name'] ?? $log['account_nm'] ?? 'Unknown';
        $recipient = $meta['recipient_name'] ?? 'Unknown';
        $amount = isset($meta['assigned_amount']) ? ' (' . (int) $meta['assigned_amount'] . ' peserta)' : '';

        return $actor . ' mendistribusikan ke ' . $recipient . $amount;
    }

    if ($action === 'TEST_ADMIN_DISTRIBUTION_UPDATE') {
        $actor = $meta['updated_by_name'] ?? $log['account_nm'] ?? 'Unknown';
        $recipient = $meta['new_recipient_name'] ?? 'Unknown';

        return $actor . ' mengubah distribusi ke ' . $recipient;
    }

    if ($action === 'TEST_ADMIN_DISTRIBUTION_DELETE') {
        $actor = $meta['deleted_by_name'] ?? $log['account_nm'] ?? 'Unknown';

        return $actor . ' menghapus/reset distribusi';
    }

    if ($action === 'LOGIN_SUCCESS') {
        $account = $meta['account_id'] ?? $log['account_nm'] ?? 'Unknown';

        return 'Login sukses: ' . $account;
    }

    if (str_starts_with($action, 'LOGIN_FAILED')) {
        $account = $meta['account_id'] ?? 'Unknown';
        $reason = $meta['reason'] ?? str_replace('LOGIN_FAILED_', '', $action);

        return 'Login gagal: ' . $account . ($reason ? ' (' . strtolower((string) $reason) . ')' : '');
    }

    if ($action === 'LOGOUT') {
        return 'Logout: ' . ($log['account_nm'] ?? 'Unknown');
    }

    return '';
}

$pageTitle = 'Audit Log';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Audit Log</h1>
                <p class="text-sm text-gray-500 mt-1">Riwayat aktivitas sistem</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
                    <input type="text" name="search" placeholder="Action / target / actor..." value="<?= htmlspecialchars($filters['search']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Action</label>
                    <select name="action"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                        <option value="">Semua</option>
                        <?php foreach ($distinctActions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-md">
                        <i class="fa-solid fa-filter mr-1"></i>Filter
                    </button>
                    <a href="audit_log.php" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Log Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-4 py-3 font-semibold text-gray-600">Waktu</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Aktor</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Action</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Target</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">IP</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Detail</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogBody" class="divide-y divide-gray-100">
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">Tidak ada data audit log.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap font-mono text-xs">
                                <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                <?= htmlspecialchars($log['account_nm'] ?? 'System / Unknown') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= auditActionClass($log['action'] ?? '') ?>"><?= htmlspecialchars($log['action']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                <?= htmlspecialchars($log['target_type'] ?? '-') ?>
                                <?php if ($log['target_id']): ?>
                                <span class="text-gray-400">#<?= (int) $log['target_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap font-mono text-xs">
                                <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php $summary = auditActivitySummary($log); ?>
                                <?php if ($summary !== ''): ?>
                                <div class="text-xs text-gray-700 mb-1 max-w-sm"><?= htmlspecialchars($summary) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($log['metadata_json'])): ?>
                                <button onclick='openDetailModal(<?= htmlspecialchars(json_encode($log, JSON_UNESCAPED_UNICODE)) ?>)'
                                        class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">
                                    Lihat Detail
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
                <span class="text-xs text-gray-500">Total <?= $total ?> record(s)</span>
                <span id="auditRefreshStatus" class="text-xs text-gray-400 ml-3">Auto refresh aktif</span>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="px-3 py-1.5 text-xs font-semibold bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">&larr;</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?= $i === $page ? 'bg-brand-primary text-white shadow-sm' : 'bg-white border border-gray-300 hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="px-3 py-1.5 text-xs font-semibold bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">&rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closeModal('detailModal')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">Detail Audit Log</h2>
            <button onclick="closeModal('detailModal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4" id="detailContent">
        </div>
    </div>
</div>

<script>
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[char];
    });
}

function formatMetaValue(value) {
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '-';
        }

        return '<ul class="list-disc pl-4 space-y-1">' + value.map(item => {
            if (item && typeof item === 'object') {
                return '<li>' + Object.entries(item).map(([k, v]) =>
                    '<span class="font-semibold">' + escapeHtml(k.replace(/_/g, ' ')) + ':</span> ' + escapeHtml(v || '-')
                ).join(', ') + '</li>';
            }

            return '<li>' + escapeHtml(item || '-') + '</li>';
        }).join('') + '</ul>';
    }

    if (value && typeof value === 'object') {
        return '<div class="space-y-1">' + Object.entries(value).map(([k, v]) =>
            '<div><span class="font-semibold">' + escapeHtml(k.replace(/_/g, ' ')) + ':</span> ' + escapeHtml(v || '-') + '</div>'
        ).join('') + '</div>';
    }

    return escapeHtml(value || '-');
}

function openDetailModal(log) {
    const content = document.getElementById('detailContent');
    let metaHtml = '';
    if (log.metadata_json) {
        try {
            const meta = JSON.parse(log.metadata_json);
            metaHtml = '<table class="w-full text-sm">' +
                Object.entries(meta).map(([k, v]) =>
                    '<tr><td class="px-3 py-1.5 font-semibold text-gray-600 capitalize align-top">' + escapeHtml(k.replace(/_/g, ' ')) +
                    '</td><td class="px-3 py-1.5 text-gray-800">' + formatMetaValue(v) + '</td></tr>'
                ).join('') +
                '</table>';
        } catch (e) {
            metaHtml = '<pre class="text-xs text-gray-600 bg-gray-50 p-3 rounded-lg overflow-x-auto">' + escapeHtml(log.metadata_json) + '</pre>';
        }
    }
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="block text-xs font-semibold text-gray-500">Waktu</span>
                <span class="text-gray-800">${escapeHtml(log.created_at || '-')}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500">Aktor</span>
                <span class="text-gray-800">${escapeHtml(log.account_nm || 'Unknown')}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500">Action</span>
                <span class="text-gray-800">${escapeHtml(log.action || '-')}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500">Target</span>
                <span class="text-gray-800">${escapeHtml(log.target_type || '-')} ${log.target_id ? '#' + escapeHtml(log.target_id) : ''}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500">IP Address</span>
                <span class="text-gray-800 font-mono">${escapeHtml(log.ip_address || '-')}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500">User Agent</span>
                <span class="text-gray-800 text-xs truncate block max-w-[200px]" title="${escapeHtml(log.user_agent || '')}">${escapeHtml(log.user_agent || '-')}</span>
            </div>
        </div>
        ${metaHtml ? '<div class="border-t border-gray-100 pt-4"><span class="block text-xs font-semibold text-gray-500 mb-2">Metadata</span>' + metaHtml + '</div>' : ''}
    `;
    document.getElementById('detailModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

const auditLogBody = document.getElementById('auditLogBody');
const auditRefreshStatus = document.getElementById('auditRefreshStatus');

async function refreshAuditLogTable() {
    if (!auditLogBody || document.hidden) {
        return;
    }

    try {
        const response = await fetch(window.location.href, {
            headers: {'X-Requested-With': 'fetch'},
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const freshBody = doc.getElementById('auditLogBody');

        if (freshBody) {
            auditLogBody.innerHTML = freshBody.innerHTML;
        }

        if (auditRefreshStatus) {
            auditRefreshStatus.textContent = 'Auto refresh aktif, terakhir: ' + new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            auditRefreshStatus.className = 'text-xs text-emerald-500 ml-3';
        }
    } catch (error) {
        if (auditRefreshStatus) {
            auditRefreshStatus.textContent = 'Auto refresh gagal, mencoba lagi...';
            auditRefreshStatus.className = 'text-xs text-red-500 ml-3';
        }
    }
}

setInterval(refreshAuditLogTable, 10000);

tailwind.config = {
    theme: {
        extend: {
            colors: {
                brand: {
                    primary: '#1D4ED8',
                    primaryHover: '#1E40AF',
                    bg: '#f3f4f6',
                    card: '#ffffff',
                }
            }
        }
    }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
