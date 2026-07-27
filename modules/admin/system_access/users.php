<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/users.php');
requireSuperadmin($pdo_run);

$controller = new SystemAccessController($pdo_run);
$controller->handleAction();

$model = $controller->getModel();

// Filters
$search       = trim($_GET['search'] ?? '');
$roleFilter   = trim($_GET['role_id'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$showUnassigned = isset($_GET['unassigned']);
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 20;
$offset       = ($page - 1) * $limit;

$filters = [];
if ($search !== '')          $filters['search'] = $search;
if ($roleFilter !== '')      $filters['role_id'] = $roleFilter;
if ($statusFilter !== '')    $filters['status'] = $statusFilter;
if ($showUnassigned)         $filters['unassigned'] = true;

$result = $model->getUserList($filters, $limit, $offset);
$users = $result['users'];
$totalUsers = $result['total'];
$totalPages = (int) ceil($totalUsers / $limit);

// Summary counts
$totalAll       = $model->countTotalUsers();
$totalActive    = $model->countActiveUsers();
$totalInactive  = $model->countInactiveUsers();
$totalUnassigned = $model->countUsersWithoutRole();
$allRoles       = $model->getRoles();

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'User Management';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<?php if ($successMsg || $errorMsg): ?>
<div id="flashToast" class="fixed top-24 right-10 z-[100] transition-all duration-500">
    <div class="<?= $successMsg ? 'border-green-500 text-green-800' : 'border-red-500 text-red-800' ?> bg-white border-l-4 rounded-lg shadow-2xl p-4 flex items-center gap-4 min-w-[320px]">
        <div class="<?= $successMsg ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> w-10 h-10 rounded-full flex items-center justify-center shrink-0">
            <i class="fas <?= $successMsg ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-lg"></i>
        </div>
        <div class="flex-1">
            <p class="text-xs font-bold uppercase tracking-widest mb-0.5"><?= $successMsg ? 'Success' : 'Error' ?></p>
            <p class="text-sm font-medium text-gray-600"><?= $successMsg ? $successMsg : htmlspecialchars($errorMsg) ?></p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
</div>
<script>setTimeout(() => { const t = document.getElementById('flashToast'); if(t) { t.style.opacity='0'; setTimeout(()=>t.remove(),500); } }, 8000);</script>
<?php endif; ?>

<div class="dashboard-wrapper p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / User Access</div>
            <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Kelola semua user, assign role, aktif/nonaktifkan akun, dan reset password.
            </p>
        </div>
    </div>

    <!-- Summary Cards -->
    <?php
    $summaryCards = [
        ['label' => 'Total Users', 'value' => $totalAll, 'note' => 'Registered accounts', 'icon' => 'fa-users', 'badge' => 'All', 'color' => 'blue'],
        ['label' => 'Active Users', 'value' => $totalActive, 'note' => 'Status akun aktif', 'icon' => 'fa-user-check', 'badge' => $totalAll > 0 ? round(($totalActive / $totalAll) * 100) . '%' : '0%', 'color' => 'emerald'],
        ['label' => 'Inactive Users', 'value' => $totalInactive, 'note' => 'Temporarily disabled', 'icon' => 'fa-user-slash', 'badge' => $totalInactive > 0 ? 'Need review' : 'OK', 'color' => 'orange'],
        ['label' => 'Unassigned Role', 'value' => $totalUnassigned, 'note' => 'Belum punya role', 'icon' => 'fa-user-clock', 'badge' => $totalUnassigned > 0 ? 'Action needed' : 'OK', 'color' => 'red'],
    ];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <?php foreach ($summaryCards as $card): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500 font-medium"><?= htmlspecialchars($card['label']) ?></div>
                    <div class="flex items-center gap-2 mt-2">
                        <h2 class="text-2xl font-bold text-gray-900 mb-0"><?= $card['value'] ?></h2>
                        <span class="text-xs font-bold px-2 py-1 rounded-full bg-gray-100 text-gray-500"><?= htmlspecialchars($card['badge']) ?></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2"><?= htmlspecialchars($card['note']) ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gray-100 text-gray-700 flex items-center justify-center">
                    <i class="fa-solid <?= htmlspecialchars($card['icon']) ?> text-lg"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div class="relative md:col-span-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, username, email..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
            <select name="role_id" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Roles</option>
                <?php foreach ($allRoles as $r): ?>
                <option value="<?= (int)$r['rec_id'] ?>" <?= $roleFilter == $r['rec_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['grpdesc'] ?? $r['grpacc']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>Filter
                </button>
                <a href="users.php" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover flex items-center" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
                <a href="?unassigned=1" class="px-4 py-2.5 rounded-xl <?= $showUnassigned ? 'bg-amber-500 text-white' : 'bg-brand-primary text-white' ?> text-sm font-semibold hover:bg-amber-500 flex items-center" title="Show unassigned only">
                    <i class="fa-solid fa-user-clock"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b">
            <h2 class="text-lg font-bold text-gray-900">Users</h2>
            <p class="text-sm text-gray-500">Showing <?= count($users) ?> of <?= $totalUsers ?> users</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role(s)</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-center font-bold">Last Login</th>
                        <th class="px-5 py-3 text-center font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 italic">Tidak ada user ditemukan.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user):
                        $initial = strtoupper(substr($user['account_nm'] ?? 'U', 0, 1));
                        $isActive = (int) $user['status'] === 1;
                        $hasRoles = !empty($user['roles']);
                        $lastLogin = !empty($user['lastlogin']) && $user['lastlogin'] !== '0000-00-00 00:00:00'
                            ? date('d M Y H:i', strtotime($user['lastlogin']))
                            : '-';
                    ?>
                    <tr class="hover:bg-gray-50 <?= !$hasRoles ? 'bg-red-50/30' : '' ?>">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm font-bold shrink-0">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($user['account_nm']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['email_id'] ?? $user['account_id']) ?></div>
                                    <div class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($user['account_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <?php if ($hasRoles): ?>
                                <?php foreach ($user['roles'] as $ur): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 mr-1 mb-1 inline-block">
                                    <?= htmlspecialchars($ur['grpdesc'] ?? $ur['grpacc']) ?>
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">No Role</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center text-xs text-gray-500 font-mono"><?= htmlspecialchars($lastLogin) ?></td>
                        <td class="px-5 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" onclick="openUserDetailModal(<?= (int)$user['rec_id'] ?>, '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>')"
                                        class="text-brand-primary hover:text-brand-primaryHover" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <?php if ($isActive): ?>
                                <button type="button" onclick="openStatusModal(<?= (int)$user['rec_id'] ?>, '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>', 0)"
                                        class="text-amber-500 hover:text-amber-600" title="Nonaktifkan">
                                    <i class="fa-solid fa-user-slash"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" onclick="openStatusModal(<?= (int)$user['rec_id'] ?>, '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>', 1)"
                                        class="text-green-500 hover:text-green-600" title="Aktifkan">
                                    <i class="fa-solid fa-user-check"></i>
                                </button>
                                <?php endif; ?>

                                <button type="button" onclick="openResetPasswordModal(<?= (int)$user['rec_id'] ?>, '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>')"
                                        class="text-brand-primary hover:text-brand-primaryHover" title="Reset Password">
                                    <i class="fa-solid fa-key"></i>
                                </button>

                                <button type="button" onclick="openDeleteUserModal(<?= (int)$user['rec_id'] ?>, '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>')"
                                        class="text-red-500 hover:text-red-600" title="Hapus User">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="p-5 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalUsers ?> users)</div>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-2 rounded-lg bg-brand-primary text-white border border-brand-primary text-sm hover:bg-brand-primaryHover">Previous</a>
                <?php else: ?>
                <span class="px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-400 cursor-not-allowed">Previous</span>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="px-3 py-2 rounded-lg text-sm <?= $p === $page ? 'bg-brand-primary text-white' : 'bg-brand-primary text-white border border-brand-primary hover:bg-brand-primaryHover' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-2 rounded-lg bg-brand-primary text-white border border-brand-primary text-sm hover:bg-brand-primaryHover">Next</a>
                <?php else: ?>
                <span class="px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-400 cursor-not-allowed">Next</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ User Status Modal ============ -->
<div id="userStatusModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <form method="POST">
            <?php echo Csrf::html(); ?>
            <input type="hidden" name="action" value="update_user_status">
            <input type="hidden" name="user_rec_id" id="statusUserId">
            <input type="hidden" name="status" id="statusValue">

            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center mb-4">
                    <i id="statusModalIcon" class="fa-solid fa-user-slash text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900" id="statusModalTitle">Nonaktifkan User?</h2>
                <p class="text-sm text-gray-500 mt-2">
                    Apakah kamu yakin ingin <span id="statusActionText" class="font-semibold">menonaktifkan</span>
                    user <span id="statusUserName" class="font-bold text-gray-900"></span>?
                </p>
                <p class="text-xs text-gray-400 mt-3">Data user tidak akan dihapus. Sistem hanya akan mengubah status akun.</p>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2">
                <button type="button" onclick="closeModal('userStatusModal')" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Batal</button>
                <button type="submit" id="statusSubmitBtn" class="px-4 py-2 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Ya, Nonaktifkan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Reset Password Modal ============ -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <form method="POST" onsubmit="return confirm('Yakin ingin mereset password user ini?')">
            <?php echo Csrf::html(); ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_rec_id" id="resetUserId">

            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-key text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Reset Password</h2>
                <p class="text-sm text-gray-500 mt-2">
                    Password user <span id="resetUserName" class="font-bold text-gray-900"></span> akan di-reset ke password sementara.
                </p>
                <p class="text-xs text-gray-400 mt-3">Password baru akan ditampilkan setelah konfirmasi.</p>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2">
                <button type="button" onclick="closeModal('resetPasswordModal')" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">Ya, Reset</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Delete User Modal ============ -->
<div id="deleteUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <form method="POST" onsubmit="return confirm('YAKIN ingin menghapus user ini secara permanen? Semua data role akan ikut terhapus.')">
            <?php echo Csrf::html(); ?>
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_rec_id" id="deleteUserId">

            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-red-100 text-red-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-trash-can text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Hapus User?</h2>
                <p class="text-sm text-gray-500 mt-2">
                    User <span id="deleteUserName" class="font-bold text-gray-900"></span> akan dihapus secara permanen beserta semua role assignments.
                </p>
                <p class="text-xs text-red-400 mt-3 font-semibold">Aksi ini tidak bisa dibatalkan!</p>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2">
                <button type="button" onclick="closeModal('deleteUserModal')" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-500 text-white text-sm font-semibold hover:bg-red-600">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ User Detail Modal ============ -->
<div id="userDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900" id="detailUserName">User Detail</h2>
                <p class="text-sm text-gray-500" id="detailUserAccount">Loading...</p>
            </div>
            <button type="button" onclick="closeModal('userDetailModal')" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 space-y-4" id="detailContent">
            <div class="text-center text-gray-400 py-4"><i class="fa-solid fa-spinner fa-spin text-xl"></i></div>
        </div>
    </div>
</div>

<script>
function openStatusModal(userId, userName, status) {
    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusUserName').textContent = userName;
    document.getElementById('statusValue').value = status;

    const title = document.getElementById('statusModalTitle');
    const actionText = document.getElementById('statusActionText');
    const submitBtn = document.getElementById('statusSubmitBtn');
    const icon = document.getElementById('statusModalIcon');

    if (status === 1) {
        title.textContent = 'Aktifkan User?';
        actionText.textContent = 'mengaktifkan';
        submitBtn.textContent = 'Ya, Aktifkan';
        icon.className = 'fa-solid fa-user-check text-xl';
    } else {
        title.textContent = 'Nonaktifkan User?';
        actionText.textContent = 'menonaktifkan';
        submitBtn.textContent = 'Ya, Nonaktifkan';
        icon.className = 'fa-solid fa-user-slash text-xl';
    }
    document.getElementById('userStatusModal').classList.remove('hidden');
    document.getElementById('userStatusModal').classList.add('flex');
}

function openResetPasswordModal(userId, userName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
    document.getElementById('resetPasswordModal').classList.add('flex');
}

function openDeleteUserModal(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteUserModal').classList.remove('hidden');
    document.getElementById('deleteUserModal').classList.add('flex');
}

function openUserDetailModal(userId, userName) {
    document.getElementById('detailUserName').textContent = userName;
    document.getElementById('detailContent').innerHTML = '<div class="text-center text-gray-400 py-4"><i class="fa-solid fa-spinner fa-spin text-xl"></i><p class="mt-2">Memuat detail...</p></div>';
    document.getElementById('userDetailModal').classList.remove('hidden');
    document.getElementById('userDetailModal').classList.add('flex');

    fetch('ajax_user_detail.php?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                document.getElementById('detailContent').innerHTML = '<div class="text-red-500 text-center py-4">' + data.error + '</div>';
                return;
            }
            let rolesHtml = '';
            if (data.roles && data.roles.length > 0) {
                data.roles.forEach(r => {
                    rolesHtml += '<span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 mr-1 mb-1 inline-block">' + r.grpdesc + ' (' + r.grpaccess + '/' + r.grpacc + ')</span>';
                });
            } else {
                rolesHtml = '<span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">No Role</span>';
            }

            document.getElementById('detailUserAccount').textContent = data.account_id + ' &middot; ' + (data.email_id || '-');

            document.getElementById('detailContent').innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div><span class="text-xs text-gray-400 block">Name</span><span class="text-sm font-semibold text-gray-900">${data.account_nm || '-'}</span></div>
                    <div><span class="text-xs text-gray-400 block">Alias</span><span class="text-sm font-semibold text-gray-900">${data.alias_nm || '-'}</span></div>
                    <div><span class="text-xs text-gray-400 block">Email</span><span class="text-sm text-gray-900">${data.email_id || '-'}</span></div>
                    <div><span class="text-xs text-gray-400 block">Account ID</span><span class="text-sm font-mono text-gray-900">${data.account_id || '-'}</span></div>
                    <div><span class="text-xs text-gray-400 block">Status</span><span class="text-sm">${data.status == 1 ? '<span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Active</span>' : '<span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Inactive</span>'}</span></div>
                    <div><span class="text-xs text-gray-400 block">Last Login</span><span class="text-sm font-mono text-gray-900">${data.lastlogin || '-'}</span></div>
                </div>
                <div class="pt-4 border-t">
                    <span class="text-xs text-gray-400 block mb-2">Roles</span>
                    <div>${rolesHtml}</div>
                </div>
                <div class="pt-4 border-t">
                    <span class="text-xs text-gray-400 block mb-2">Quick Actions</span>
                    <div class="flex gap-2">
                        <a href="users.php?search=${encodeURIComponent(data.account_nm || '')}" class="px-3 py-1.5 rounded-xl bg-brand-primary text-white text-xs font-semibold hover:bg-brand-primaryHover">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>Lihat di List
                        </a>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('detailContent').innerHTML = '<div class="text-red-500 text-center py-4">Gagal memuat detail user.</div>';
        });
}

function closeModal(id) {
    const el = document.getElementById(id);
    el.classList.add('hidden');
    el.classList.remove('flex');
}

// Click outside to close modals
document.addEventListener('click', function(e) {
    ['userStatusModal', 'resetPasswordModal', 'deleteUserModal', 'userDetailModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && modal.classList.contains('flex') && e.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
