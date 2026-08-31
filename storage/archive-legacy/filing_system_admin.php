<?php
// File: modules/cbt_ops/filing_system/admin.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingStorageService.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/services/FilingAdminService.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$storageService = new FilingStorageService($ftp_config);
$permissionService = new FilingPermissionService($pdo_run);
$adminService = new FilingAdminService($pdo_run, $storageService, $permissionService);

// Guard Admin
$adminService->requireAdmin($userId);

// Record admin view
$pdo_run->prepare("INSERT INTO sys_filing_audit (user_id, action, notes, user_agent, ip_address) VALUES (?, 'admin_view', 'Accessed admin panel', ?, ?)")
    ->execute([$userId, $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '']);

$filters = [
    'search' => $_GET['search'] ?? '',
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'limit' => 20,
    'status' => $_GET['status'] ?? '',
    'security_level' => $_GET['security_level'] ?? '',
    'access_mode' => $_GET['access_mode'] ?? '',
    'declared_file_type' => $_GET['declared_file_type'] ?? '',
];

$totalItems = $adminService->countAdminFiles($filters);
$totalPages = ceil($totalItems / $filters['limit']);
$offset = ($filters['page'] - 1) * $filters['limit'];

$items = $adminService->getAdminFiles($filters, $filters['limit'], $offset);

require_once BASE_PATH.'/includes/layout_header.php';

function formatSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2).' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2).' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2).' KB';
    }

    return $bytes.' bytes';
}

function getStatusBadge($status)
{
    $colors = [
        'active' => 'bg-green-100 text-green-700',
        'archived' => 'bg-gray-100 text-gray-700',
        'trashed' => 'bg-orange-100 text-orange-700',
        'deleted' => 'bg-red-100 text-red-700',
        'blocked' => 'bg-red-800 text-white',
        'expired' => 'bg-yellow-100 text-yellow-700',
    ];
    $color = $colors[$status] ?? 'bg-gray-100 text-gray-700';

    return "<span class=\"text-[10px] font-bold px-2 py-1 rounded-full uppercase {$color}\">{$status}</span>";
}
?>

<div class="py-8 md:py-12">
    <div class="max-w-[1400px] mx-auto sm:px-6 lg:px-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b-2 border-red-500 pb-4">
            <div>
                <h2 class="font-extrabold text-3xl text-gray-900 tracking-tight flex items-center gap-3">
                    <i class="fas fa-shield-alt text-red-600"></i>
                    Filing System Admin
                </h2>
                <p class="text-red-500 font-bold text-xs mt-1 uppercase tracking-widest"><i class="fas fa-exclamation-triangle"></i> Super Administrator Area</p>
            </div>
            <div>
                <a href="index.php" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl text-sm font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2">
                    <i class="fas fa-door-open"></i> Keluar Admin
                </a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Cari File</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-red-500 focus:border-red-500">
                </div>
                
                <div class="w-32">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-red-500 focus:border-red-500 bg-white">
                        <option value="">Semua</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        <option value="trashed" <?= $filters['status'] === 'trashed' ? 'selected' : '' ?>>Trashed</option>
                        <option value="deleted" <?= $filters['status'] === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                        <option value="blocked" <?= $filters['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>

                <div class="w-32">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Security</label>
                    <select name="security_level" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-red-500 focus:border-red-500 bg-white">
                        <option value="">Semua</option>
                        <option value="normal" <?= $filters['security_level'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="restricted" <?= $filters['security_level'] === 'restricted' ? 'selected' : '' ?>>Restricted</option>
                        <option value="confidential" <?= $filters['security_level'] === 'confidential' ? 'selected' : '' ?>>Confidential</option>
                    </select>
                </div>

                <button type="submit" class="px-5 py-2 bg-gray-800 text-white rounded text-sm font-bold shadow-sm transition hover:bg-gray-900">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (array_filter(array_diff_key($filters, ['page' => 1, 'limit' => 1]))) { ?>
                    <a href="admin.php" class="px-3 py-2 bg-gray-100 text-gray-600 rounded hover:bg-gray-200 text-xs font-bold transition"><i class="fas fa-times"></i> Reset</a>
                <?php } ?>
            </form>
        </div>

        <div class="flex justify-between items-center mb-4 px-2">
            <span class="text-sm font-bold text-gray-500">Total System Files: <span class="text-red-600"><?= $totalItems ?></span></span>
        </div>

        <!-- ADMIN TABLE -->
        <div class="bg-white rounded-xl shadow-lg border border-red-100 overflow-hidden">
            <div class="overflow-x-auto min-h-[400px]">
                <table class="w-full min-w-[980px] text-sm text-left">
                    <thead class="text-[10px] text-gray-500 uppercase bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 font-black">ID / Code</th>
                            <th class="px-4 py-3 font-black w-1/4">Display Name</th>
                            <th class="px-4 py-3 font-black">Owner</th>
                            <th class="px-4 py-3 font-black text-center">Status</th>
                            <th class="px-4 py-3 font-black text-center">Security</th>
                            <th class="px-4 py-3 font-black text-right">Size</th>
                            <th class="px-4 py-3 font-black text-center">Diagnostics</th>
                            <th class="px-4 py-3 font-black text-right w-28 whitespace-nowrap">Admin Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($items)) { ?>
                            <tr><td colspan="8" class="text-center py-10 text-gray-400">Tidak ada file.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($items as $f) { ?>
                                <tr class="hover:bg-red-50/20 transition-colors <?= $f['status'] === 'deleted' ? 'opacity-50' : '' ?>">
                                    <td class="px-4 py-3 font-mono text-xs">
                                        #<?= $f['rec_id'] ?><br>
                                        <span class="text-[9px] text-gray-400"><?= $f['file_code'] ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-800 break-all"><?= htmlspecialchars($f['display_name']) ?></div>
                                        <div class="text-[10px] text-gray-500 mt-1 font-mono" title="Path Relative">.../<?= htmlspecialchars(basename($f['storage_path'])) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-bold text-gray-600">
                                        <?= htmlspecialchars($f['owner_name']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?= getStatusBadge($f['status']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-[10px] font-bold uppercase text-gray-500">
                                        <?= $f['security_level'] ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                        <?= formatSize($f['zip_size']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center" id="diag_cell_<?= $f['rec_id'] ?>">
                                        <button onclick="runDiagnostic(<?= $f['rec_id'] ?>)" class="text-[10px] bg-gray-100 border border-gray-200 text-gray-600 px-2 py-1 rounded hover:bg-gray-200 font-bold transition">Check</button>
                                    </td>
                                    <td class="px-4 py-3 text-right relative dropdown-container whitespace-nowrap">
                                        <button onclick="toggleAdminDropdown(<?= $f['rec_id'] ?>)" class="p-1.5 bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-100 shadow-sm">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        
                                        <div id="admin_dd_<?= $f['rec_id'] ?>" class="hidden absolute right-0 bottom-full mb-2 w-48 rounded-md shadow-2xl bg-white ring-1 ring-black ring-opacity-10 divide-y divide-gray-100 z-50 overflow-hidden">
                                            <div class="py-1">
                                                <a href="audit.php?filing_id=<?= $f['rec_id'] ?>" class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-50"><i class="fas fa-history w-5 text-gray-400"></i> View Audit</a>
                                                <button onclick="runDiagnostic(<?= $f['rec_id'] ?>)" class="w-full text-left flex items-center px-4 py-2 text-xs text-blue-700 hover:bg-blue-50"><i class="fas fa-stethoscope w-5 text-blue-400"></i> Storage Recheck</button>
                                            </div>
                                            <div class="py-1">
                                                <?php if ($f['status'] !== 'blocked' && $f['status'] !== 'deleted') { ?>
                                                    <button onclick="executeAdminAction(<?= $f['rec_id'] ?>, 'block')" class="w-full text-left flex items-center px-4 py-2 text-xs text-orange-700 hover:bg-orange-50"><i class="fas fa-lock w-5 text-orange-400"></i> Block File</button>
                                                <?php } ?>
                                                
                                                <?php if ($f['status'] === 'blocked') { ?>
                                                    <button onclick="executeAdminAction(<?= $f['rec_id'] ?>, 'unblock')" class="w-full text-left flex items-center px-4 py-2 text-xs text-green-700 hover:bg-green-50"><i class="fas fa-unlock w-5 text-green-400"></i> Unblock File</button>
                                                <?php } ?>
                                                
                                                <?php if (in_array($f['status'], ['archived', 'trashed', 'expired', 'blocked'])) { ?>
                                                    <button onclick="executeAdminAction(<?= $f['rec_id'] ?>, 'restore')" class="w-full text-left flex items-center px-4 py-2 text-xs text-indigo-700 hover:bg-indigo-50"><i class="fas fa-undo w-5 text-indigo-400"></i> Force Restore</button>
                                                <?php } ?>
                                            </div>
                                            <?php if ($f['status'] !== 'deleted') { ?>
                                                <div class="py-1 bg-red-50">
                                                    <button onclick="executePermanentDelete(<?= $f['rec_id'] ?>)" class="w-full text-left flex items-center px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100"><i class="fas fa-dumpster-fire w-5 text-red-500"></i> Permanent Delete</button>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ADMIN PAGINATION -->
            <?php if ($totalPages > 1) { ?>
                <div class="px-6 py-4 bg-gray-100 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-xs text-gray-500 font-bold">Halaman <?= $filters['page'] ?> dari <?= $totalPages ?></p>
                    <div class="flex gap-1">
                        <?php
                            $q = $_GET;
                unset($q['page']);
                $qs = http_build_query($q);
                $qs = $qs ? '&'.$qs : '';
                ?>
                        <?php if ($filters['page'] > 1) { ?>
                            <a href="?page=<?= $filters['page'] - 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                        <?php } ?>
                        
                        <?php if ($filters['page'] < $totalPages) { ?>
                            <a href="?page=<?= $filters['page'] + 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

    </div>
</div>

<script>
// DEBUG ONLY: CSRF token dimatikan sementara.
// const CSRF_TOKEN = '<?= Csrf::getToken() ?>';
const CSRF_TOKEN = '';

function toggleAdminDropdown(id) {
    document.querySelectorAll('[id^="admin_dd_"]').forEach(el => {
        if(el.id !== 'admin_dd_' + id) el.classList.add('hidden');
    });
    document.getElementById('admin_dd_' + id).classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-container')) {
        document.querySelectorAll('[id^="admin_dd_"]').forEach(el => el.classList.add('hidden'));
    }
});

function runDiagnostic(id) {
    const cell = document.getElementById('diag_cell_' + id);
    cell.innerHTML = '<i class="fas fa-circle-notch fa-spin text-gray-400"></i>';

    const fd = new FormData();
    fd.append('action', 'diagnose');
    fd.append('filing_id', id);
    // DEBUG ONLY: fd.append('csrf_token', CSRF_TOKEN);

    fetch('admin_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            let color = res.data.status === 'available' ? 'green' : (res.data.status === 'missing' ? 'red' : 'orange');
            cell.innerHTML = `<span class="text-[9px] font-bold bg-${color}-100 text-${color}-700 px-2 py-1 rounded cursor-help" title="${res.data.message}">${res.data.status.toUpperCase()}</span>`;
        } else {
            cell.innerHTML = `<span class="text-[9px] font-bold bg-red-100 text-red-700 px-2 py-1 rounded">ERROR</span>`;
        }
    })
    .catch(err => {
        cell.innerHTML = `<span class="text-[9px] font-bold bg-gray-200 text-gray-600 px-2 py-1 rounded">FAIL</span>`;
    });
}

function executeAdminAction(id, action) {
    let confirmMsg = {
        'block': 'Blokir file ini? User tidak akan bisa mengaksesnya.',
        'unblock': 'Buka blokir file ini?',
        'restore': 'Paksa restore file ini ke status Active?'
    };

    if(!confirm(confirmMsg[action])) return;

    const fd = new FormData();
    fd.append('action', action);
    fd.append('filing_id', id);
    // DEBUG ONLY: fd.append('csrf_token', CSRF_TOKEN);

    fetch('admin_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(err => alert('Koneksi terputus.'));
}

function executePermanentDelete(id) {
    let input = prompt('PERINGATAN KRITIS: Anda akan menghapus file fisik di storage secara permanen. Record database akan di-mark "deleted".\n\nKetik "DELETE" (tanpa kutip) untuk konfirmasi:');
    
    if (input !== 'DELETE') {
        if (input !== null) alert('Konfirmasi dibatalkan. Teks tidak sesuai.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'permanent_delete');
    fd.append('filing_id', id);
    fd.append('confirm_text', 'DELETE');
    // DEBUG ONLY: fd.append('csrf_token', CSRF_TOKEN);

    fetch('admin_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        alert(res.message);
        if(res.success) location.reload();
    })
    .catch(err => alert('Koneksi terputus.'));
}
</script>

<?php require_once BASE_PATH.'/includes/layout_footer.php'; ?>
