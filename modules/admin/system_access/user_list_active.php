<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/user_list_active.php');
requireSuperadmin($pdo_run);

$controller = new SystemAccessController($pdo_run);
$controller->handleAction();

$model = $controller->getModel();

// Filters
$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role_id'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'active');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 15;
$offset     = ($page - 1) * $limit;

$filters = [];
if ($search !== '')       $filters['search'] = $search;
if ($roleFilter !== '')   $filters['role_id'] = $roleFilter;
if ($statusFilter !== '') $filters['status'] = $statusFilter;

$result = $model->getUsersWithRoles($filters, $limit, $offset);
$users = $result['users'];
$totalUsers = $result['total'];
$totalPages = (int)ceil($totalUsers / $limit);

// Get summary counts
$allData    = $model->getUsersWithRoles([], 99999, 0);
$activeData = $model->getUsersWithRoles(['status' => 'active'], 99999, 0);
$inactiveData = $model->getUsersWithRoles(['status' => 'inactive'], 99999, 0);
$unassignedData = $model->getUsersWithRoles(['unassigned' => true], 99999, 0);

$totalAll     = $allData['total'];
$totalActive  = $activeData['total'];
$totalInactive = $inactiveData['total'];
$totalUnassigned = $unassignedData['total'];

// Get roles for filter dropdown
$allRoles = $model->getRoles();

$pageTitle = 'Active User List';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / User Access</div>
            <h1 class="text-2xl font-bold text-gray-900">Active User List</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Halaman ini digunakan untuk melihat user aktif, role yang digunakan, status akun, dan akses yang terhubung ke sistem.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="user_role.php" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover border border-brand-primary shadow-sm text-center">
                <i class="fa-solid fa-user-gear mr-2"></i>Manage User Role
            </a>
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

    <!-- User Table -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Users</h2>
                    <p class="text-sm text-gray-500 mt-1">Filter user berdasarkan role, status, dan keyword pencarian.</p>
                </div>
            <a href="roles.php" class="text-sm text-gray-500 hover:text-brand-primary font-semibold">
                    <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
                </a>
            </div>

            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-5">
                <div class="relative md:col-span-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user, email, role..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
                </div>

                <select name="role_id" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="">All Roles</option>
                    <?php foreach ($allRoles as $r): ?>
                        <option value="<?= (int)$r['rec_id'] ?>" <?= $roleFilter == $r['rec_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['grpdesc'] ?? $r['grpacc']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Filter</button>
                    <a href="user_list_active.php" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover flex items-center">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="userTable">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role</th>
                        <th class="px-5 py-3 text-left font-bold">Company</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 italic">Tidak ada user ditemukan.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user):
                        $initial = strtoupper(substr($user['account_nm'] ?? 'U', 0, 1));
                        $isActive = (int)$user['status'] === 1;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm font-bold">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($user['account_nm']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['email_id'] ?? '') ?></div>
                                    <div class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($user['account_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <?php if (!empty($user['roles'])): ?>
                                <?php foreach ($user['roles'] as $ur): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 mr-1 mb-1 inline-block"><?= htmlspecialchars($ur['grpdesc'] ?? $ur['grpacc']) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-gray-500"><?= htmlspecialchars($user['cmpcd'] ?? '-') ?></td>
                        <td class="px-5 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
    <a href="user_role.php?search=<?= urlencode($user['account_nm']) ?>" 
       class="text-brand-primary hover:text-brand-primaryHover mr-3" 
       title="Manage role">
        <i class="fa-solid fa-user-gear"></i>
    </a>

    <?php if ($isActive): ?>
        <button type="button"
                onclick="openUserStatusModal(
                    <?= (int)$user['rec_id'] ?>,
                    '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>',
                    0
                )"
                class="text-brand-primary hover:text-brand-primaryHover"
                title="Deactivate user">
            <i class="fa-solid fa-user-slash"></i>
        </button>
    <?php else: ?>
        <button type="button"
                onclick="openUserStatusModal(
                    <?= (int)$user['rec_id'] ?>,
                    '<?= htmlspecialchars($user['account_nm'], ENT_QUOTES) ?>',
                    1
                )"
                class="text-brand-primary hover:text-brand-primaryHover"
                title="Activate user">
            <i class="fa-solid fa-user-check"></i>
        </button>
    <?php endif; ?>
</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-5 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="text-sm text-gray-500">Showing <?= count($users) ?> of <?= $totalUsers ?> users</div>
            <?php if ($totalPages > 1): ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>
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

                <h2 class="text-lg font-bold text-gray-900" id="statusModalTitle">
                    Nonaktifkan User?
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Apakah kamu yakin ingin <span id="statusActionText" class="font-semibold">menonaktifkan</span>
                    user <span id="statusUserName" class="font-bold text-gray-900"></span>?
                </p>

                <p class="text-xs text-gray-400 mt-3">
                    Data user tidak akan dihapus. Sistem hanya akan mengubah status akun.
                </p>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2">
                <button type="button"
                        onclick="closeUserStatusModal()"
                        class="px-4 py-2 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover border border-brand-primary">
                    Batal
                </button>

                <button type="submit"
                        id="statusSubmitBtn"
                        class="px-4 py-2 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">
                    Ya, Nonaktifkan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserStatusModal(userId, userName, status) {
    const modal = document.getElementById('userStatusModal');
    const title = document.getElementById('statusModalTitle');
    const actionText = document.getElementById('statusActionText');
    const submitBtn = document.getElementById('statusSubmitBtn');
    const icon = document.getElementById('statusModalIcon');

    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusUserName').textContent = userName;
    document.getElementById('statusValue').value = status;

    if (status === 1) {
        title.textContent = 'Aktifkan User?';
        actionText.textContent = 'mengaktifkan';
        submitBtn.textContent = 'Ya, Aktifkan';
        submitBtn.className = 'px-4 py-2 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover';
        icon.className = 'fa-solid fa-user-check text-xl';
    } else {
        title.textContent = 'Nonaktifkan User?';
        actionText.textContent = 'menonaktifkan';
        submitBtn.textContent = 'Ya, Nonaktifkan';
        submitBtn.className = 'px-4 py-2 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover';
        icon.className = 'fa-solid fa-user-slash text-xl';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeUserStatusModal() {
    const modal = document.getElementById('userStatusModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>

