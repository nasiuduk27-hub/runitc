<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath.'/config.php';

require_once BASE_PATH.'/includes/menu_guard.php';
/** @var PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/user_role.php');
requireSuperadmin($pdo_run);

$controller = new SystemAccessController($pdo_run);
$controller->handleAction();
$model = $controller->getModel();

// Get all roles for dropdown
$allRoles = $model->getRoles();

// Filters
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role_id'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$filters = [];
if ($search !== '') {
    $filters['search'] = $search;
}
if ($roleFilter !== '') {
    $filters['role_id'] = $roleFilter;
}

$result = $model->getUsersWithRoles($filters, $limit, $offset);
$users = $result['users'];
$totalUsers = $result['total'];
$totalPages = (int) ceil($totalUsers / $limit);

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'User Role Assignment';
include BASE_PATH.'/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<?php if ($successMsg || $errorMsg) { ?>
<div id="flashToast" class="fixed top-24 right-10 z-[100] transition-all duration-500">
    <div class="<?= $successMsg ? 'border-green-500 text-green-800' : 'border-red-500 text-red-800' ?> bg-white border-l-4 rounded-lg shadow-2xl p-4 flex items-center gap-4 min-w-[320px]">
        <div class="<?= $successMsg ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> w-10 h-10 rounded-full flex items-center justify-center shrink-0">
            <i class="fas <?= $successMsg ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-lg"></i>
        </div>
        <div class="flex-1">
            <p class="text-xs font-bold uppercase tracking-widest mb-0.5"><?= $successMsg ? 'Success' : 'Error' ?></p>
            <p class="text-sm font-medium text-gray-600"><?= htmlspecialchars($successMsg ?: $errorMsg) ?></p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
</div>
<script>setTimeout(() => { const t = document.getElementById('flashToast'); if(t) { t.style.opacity='0'; setTimeout(()=>t.remove(),500); } }, 5000);</script>
<?php } ?>

<div class="dashboard-wrapper p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / Access Control</div>
            <h1 class="text-2xl font-bold text-gray-900">User Role Assignment</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Assign atau ubah role untuk setiap user. Role menentukan menu apa saja yang bisa diakses user di sidebar.
            </p>
        </div>
        <a href="roles.php" class="text-sm text-gray-500 hover:text-brand-primary font-semibold">
            <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="relative md:col-span-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user, username, email..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
            <select name="role_id" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Roles</option>
                <?php foreach ($allRoles as $r) { ?>
                    <option value="<?= (int) $r['rec_id'] ?>" <?= $roleFilter == $r['rec_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['grpdesc'] ?? $r['grpacc']) ?></option>
                <?php } ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>Filter
                </button>
                <a href="user_role.php" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover flex items-center">
                    <i class="fa-solid fa-rotate-left"></i>
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
                        <th class="px-5 py-3 text-left font-bold">Current Role(s)</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-left font-bold">Assign Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($users)) { ?>
                    <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400 italic">Tidak ada data user ditemukan.</td></tr>
                    <?php } ?>

                    <?php foreach ($users as $user) {
                        $initial = strtoupper(substr($user['account_nm'] ?? 'U', 0, 1));
                        ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-primary text-white flex items-center justify-center text-sm font-bold shrink-0">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($user['account_nm']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['account_id']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($user['email_id'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <?php if (! empty($user['roles'])) { ?>
                                <?php foreach ($user['roles'] as $ur) { ?>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700"><?= htmlspecialchars($ur['grpdesc'] ?? $ur['grpacc']) ?></span>
                                    <span class="text-xs text-gray-400">(<?= htmlspecialchars($ur['grpaccess']) ?>)</span>
                                    <form method="POST" class="inline" onsubmit="return confirm('Hapus role ini dari user?')">
    <?php echo Csrf::html(); ?>
                                        <input type="hidden" name="action" value="remove_user_role">
                                        <input type="hidden" name="user_rec_id" value="<?= (int) $user['rec_id'] ?>">
                                        <input type="hidden" name="access_code" value="<?= htmlspecialchars($ur['grpaccess']) ?>">
                                        <input type="hidden" name="access_account" value="<?= htmlspecialchars($ur['grpacc']) ?>">
                                        <button type="submit" class="text-brand-primary hover:text-brand-primaryHover text-xs"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">No Role</span>
                            <?php } ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ((int) $user['status'] === 1) { ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Active</span>
                            <?php } else { ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Inactive</span>
                            <?php } ?>
                        </td>
                        <td class="px-5 py-4">
                            <form method="POST" class="flex items-center gap-2">
    <?php echo Csrf::html(); ?>
                                <input type="hidden" name="action" value="assign_user_role">
                                <input type="hidden" name="user_rec_id" value="<?= (int) $user['rec_id'] ?>">
                                <select name="grpacc_id" class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200" required>
                                    <option value="">-- Pilih Role --</option>
                                    <?php foreach ($allRoles as $ar) { ?>
                                        <option value="<?= (int) $ar['rec_id'] ?>"><?= htmlspecialchars($ar['grpdesc'] ?? $ar['grpacc']) ?> (<?= htmlspecialchars($ar['grpaccess']) ?>)</option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="px-3 py-2 rounded-lg bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover shrink-0">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1) { ?>
        <div class="p-5 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalUsers ?> users)</div>
            <div class="flex items-center gap-2">
                <?php if ($page > 1) { ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-2 rounded-lg bg-brand-primary text-white border border-brand-primary text-sm hover:bg-brand-primaryHover">Previous</a>
                <?php } ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++) { ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="px-3 py-2 rounded-lg text-sm <?= $p === $page ? 'bg-brand-primary text-white' : 'bg-brand-primary text-white border border-brand-primary hover:bg-brand-primaryHover' ?>">
                    <?= $p ?>
                </a>
                <?php } ?>

                <?php if ($page < $totalPages) { ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-2 rounded-lg bg-brand-primary text-white border border-brand-primary text-sm hover:bg-brand-primaryHover">Next</a>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<?php include BASE_PATH.'/includes/layout_footer.php'; ?>

