<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/permissions.php');
requireSuperadmin($pdo_run);

$model = new SystemAccess($pdo_run);

// Get permission overview data
$permissionData = $model->getPermissionOverview();
$allRoles = $model->getRoles();

// Build parent-child structure for display
$menuMap = [];
foreach ($permissionData as $item) {
    $menuMap[(int)$item['menu_id']] = $item;
}

// Search filter (client-side fallback, but also server-friendly)
$search = trim($_GET['search'] ?? '');

$pageTitle = 'Permission Overview';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / Permission</div>
            <h1 class="text-2xl font-bold text-gray-900">Permission Overview</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Halaman ini menampilkan daftar semua menu di sistem beserta role yang memiliki akses. Permission dikelola melalui tabel <code>sys_menu_access</code> — tidak ada tabel permission terpisah.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="role_menu.php" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover border border-brand-primary shadow-sm">
                <i class="fa-solid fa-list-check mr-2"></i>Manage Menu Access
            </a>
            <a href="roles.php" class="text-sm text-gray-500 hover:text-brand-primary font-semibold flex items-center px-4">
                <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
            </a>
        </div>
    </div>

    <!-- Info Card -->
    <div class="p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-800">
        <strong><i class="fa-solid fa-circle-info mr-1"></i>Info:</strong>
        Sistem ini menggunakan <strong>menu-based access control</strong>. Permission = akses ke menu tertentu melalui role.
        Untuk mengubah akses, gunakan halaman <a href="role_menu.php" class="underline font-bold">Role to Menu Permission</a>.
    </div>

    <!-- Role Legend -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h2 class="font-bold text-gray-900 mb-3">Active Roles</h2>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($allRoles as $r): ?>
                <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                    <?= htmlspecialchars($r['grpdesc'] ?? $r['grpacc']) ?>
                    <span class="text-gray-400 ml-1">(<?= (int)$r['menu_count'] ?> menus)</span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Permission Table -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Menu Access Matrix</h2>
                <p class="text-sm text-gray-500">Daftar menu dan role yang memiliki akses.</p>
            </div>
            <div class="relative w-full md:w-80">
                <input type="text" id="permSearchInput" onkeyup="filterPermissions()" placeholder="Search menu..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="permTable">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold w-8">#</th>
                        <th class="px-5 py-3 text-left font-bold">Menu</th>
                        <th class="px-5 py-3 text-left font-bold">URL</th>
                        <th class="px-5 py-3 text-center font-bold">Global</th>
                        <th class="px-5 py-3 text-left font-bold">Accessible by Roles</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($permissionData)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 italic">Belum ada data menu.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($permissionData as $idx => $menu):
                        $isParent = ((int)$menu['mst_id'] === 0);
                        $isGlobal = ((int)$menu['is_global'] === 1);
                        $roleNames = $menu['role_names'] ?? '';
                        $indent = $isParent ? '' : 'pl-10';
                    ?>
                    <tr class="hover:bg-gray-50 perm-row" data-search="<?= strtolower(htmlspecialchars($menu['title'] . ' ' . ($menu['url'] ?? '') . ' ' . $roleNames)) ?>">
                        <td class="px-5 py-3 text-gray-400 text-xs"><?= $idx + 1 ?></td>
                        <td class="px-5 py-3 <?= $indent ?>">
                            <?php if ($isParent): ?>
                                <span class="font-bold text-gray-900"><i class="fa-solid fa-folder text-yellow-500 mr-2"></i><?= htmlspecialchars($menu['title']) ?></span>
                            <?php else: ?>
                                <span class="font-semibold text-gray-700"><i class="fa-regular fa-file text-gray-400 mr-2"></i><?= htmlspecialchars($menu['title']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs font-mono"><?= htmlspecialchars($menu['url'] ?? '#') ?></td>
                        <td class="px-5 py-3 text-center">
                            <?php if ($isGlobal): ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Yes</span>
                            <?php else: ?>
                                <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <?php if ($isGlobal): ?>
                                <span class="text-xs text-green-600 font-semibold">All Users</span>
                            <?php elseif ($roleNames): ?>
                                <?php foreach (explode(', ', $roleNames) as $rn): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 mr-1 mb-1 inline-block"><?= htmlspecialchars($rn) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-xs text-red-400 italic">No access assigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterPermissions() {
    const keyword = document.getElementById('permSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.perm-row').forEach(row => {
        const data = row.dataset.search || '';
        row.style.display = keyword === '' || data.includes(keyword) ? '' : 'none';
    });
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
