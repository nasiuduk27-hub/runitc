<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/role_menu.php');
requireSuperadmin($pdo_run);

$controller = new SystemAccessController($pdo_run);
$controller->handleAction();
$model = $controller->getModel();

// Get all roles from database
$roles = $model->getRoles();

// Determine selected role
$selectedRoleId = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;
if ($selectedRoleId === 0 && !empty($roles)) {
    $selectedRoleId = (int) $roles[0]['rec_id'];
}

$selectedRole = $selectedRoleId > 0 ? $model->getRoleById($selectedRoleId) : null;

// Get menu tree and current access
$menuTree = $model->getMenusTree();
$checkedMenuIds = $selectedRoleId > 0 ? $model->getMenuAccessByRole($selectedRoleId) : [];

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'Role to Menu Permission';
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
            <p class="text-sm font-medium text-gray-600"><?= htmlspecialchars($successMsg ?: $errorMsg) ?></p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
</div>
<script>setTimeout(() => { const t = document.getElementById('flashToast'); if(t) { t.style.opacity='0'; setTimeout(()=>t.remove(),500); } }, 5000);</script>
<?php endif; ?>

<div class="dashboard-wrapper p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / Permission</div>
            <h1 class="text-2xl font-bold text-gray-900">Role to Menu Permission</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Halaman ini digunakan untuk menentukan menu apa saja yang boleh muncul dan diakses oleh role tertentu.
            </p>
        </div>
        <a href="roles.php" class="text-sm text-gray-500 hover:text-brand-primary font-semibold">
            <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Role List Sidebar -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 h-fit">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-gray-900">Roles</h2>
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-500 font-bold"><?= count($roles) ?> roles</span>
            </div>

            <div class="relative mb-4">
                <input type="text" id="roleSearchInput" onkeyup="filterRoleList()" placeholder="Search role..." class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>

            <div class="space-y-2" id="roleListContainer">
                <?php foreach ($roles as $role):
                    $isActive = ((int)$role['rec_id'] === $selectedRoleId);
                ?>
                <a href="?role_id=<?= (int)$role['rec_id'] ?>"
                   class="role-item block w-full text-left px-4 py-3 rounded-xl <?= $isActive ? 'bg-brand-primary text-white' : 'bg-brand-primary/10 hover:bg-brand-primary/20 text-brand-primary' ?> transition"
                   data-name="<?= strtolower(htmlspecialchars($role['grpdesc'] ?? $role['grpacc'])) ?>">
                    <div class="font-bold"><?= htmlspecialchars($role['grpdesc'] ?? $role['grpacc']) ?></div>
                    <div class="text-xs <?= $isActive ? 'text-blue-100' : 'text-brand-primary/70' ?>">
                        Code: <?= htmlspecialchars($role['grpaccess']) ?>/<?= htmlspecialchars($role['grpacc']) ?>
                        Â· <?= (int)$role['menu_count'] ?> menus
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Menu Permission Panel -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <form method="POST" id="menuAccessForm">
    <?php echo Csrf::html(); ?>
                <input type="hidden" name="action" value="save_menu_access">
                <input type="hidden" name="grpacc_id" value="<?= $selectedRoleId ?>">

                <div class="p-5 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-gray-900">Menu Permission</h2>
                        <p class="text-sm text-gray-500">Currently editing: <span class="font-bold text-slate-900"><?= htmlspecialchars($selectedRole['grpdesc'] ?? 'Pilih role') ?></span></p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button type="button" onclick="checkAllMenus(true)" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Select All</button>
                        <button type="button" onclick="checkAllMenus(false)" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Clear</button>
                        <?php if ($selectedRole): ?>
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">
                            <i class="fa-solid fa-floppy-disk mr-2"></i>Save Access
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-5">
                    <?php if (!$selectedRole): ?>
                    <div class="text-center py-10 text-gray-400">
                        <i class="fa-solid fa-hand-pointer text-4xl mb-3"></i>
                        <p>Pilih role di sidebar untuk mengatur akses menu.</p>
                    </div>
                    <?php else: ?>
                    <div class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-800">
                        <strong>Catatan:</strong> Jika child menu dicentang, parent menu akan otomatis ikut tersimpan agar sidebar tetap muncul.
                        <?php if ($isSuperadmin ?? false): ?>
                        <br><br><strong>Superadmin:</strong> Menu admin (diawali <code>/modules/admin/</code>) bersifat read-only dan tidak bisa dicopot.
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4">
                        <?php
                        // Cari tahu menu apa saja yang termasuk admin (dilindungi untuk superadmin)
                        $adminMenuIds = [];
                        if ($isSuperadmin ?? false) {
                            $adminMenuIds = array_unique(array_map('intval', $model->getAdminMenuIds()));
                        }

                        // Render menu tree with checkboxes
                        function renderMenuTree(array $menus, array $checkedIds, array $adminMenuIds, bool $isSuperadmin, int $level = 0): void {
                            foreach ($menus as $menu) {
                                $menuId = (int)$menu['rec_id'];
                                $isChecked = in_array($menuId, $checkedIds);
                                $hasChildren = !empty($menu['children']);
                                $isGlobal = (int)($menu['is_global'] ?? 0) === 1;
                                $isProtected = $isSuperadmin && in_array($menuId, $adminMenuIds);

                                if ($level === 0): ?>
                                    <div class="border rounded-xl overflow-hidden">
                                        <label class="bg-gray-50 px-5 py-3 flex items-center gap-3 <?= $isProtected ? 'cursor-not-allowed' : 'cursor-pointer' ?>">
                                            <input type="checkbox" name="menu_ids[]" value="<?= $menuId ?>"
                                                   class="menu-checkbox parent-cb w-4 h-4" <?= $isChecked ? 'checked' : '' ?>
                                                   <?= $isProtected ? 'disabled' : '' ?>
                                                   data-group="grp-<?= $menuId ?>"
                                                   onchange="toggleGroup('grp-<?= $menuId ?>', this.checked)">
                                            <span class="font-bold text-gray-900">
                                                <i class="fa-solid fa-folder mr-2 text-yellow-500"></i><?= htmlspecialchars($menu['title']) ?>
                                            </span>
                                            <?php if ($isGlobal): ?>
                                                <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-bold">Global</span>
                                            <?php endif; ?>
                                            <?php if ($isProtected): ?>
                                                <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-bold">Protected</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php if ($hasChildren): ?>
                                        <div class="divide-y">
                                            <?php renderMenuTree($menu['children'], $checkedIds, $adminMenuIds, $isSuperadmin, $level + 1); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <label class="px-9 py-4 flex items-center justify-between <?= $isProtected ? 'cursor-not-allowed bg-amber-50' : 'cursor-pointer hover:bg-gray-50' ?>">
                                        <div>
                                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($menu['title']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($menu['url'] ?? '#') ?></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($isGlobal): ?>
                                                <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-bold">Global</span>
                                            <?php endif; ?>
                                            <?php if ($isProtected): ?>
                                                <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-bold">Protected</span>
                                            <?php endif; ?>
                                            <input type="checkbox" name="menu_ids[]" value="<?= $menuId ?>"
                                                   class="menu-checkbox child-cb w-4 h-4" <?= $isChecked ? 'checked' : '' ?>
                                                   <?= $isProtected ? 'disabled' : '' ?>
                                                   data-parent-group="grp-<?= (int)$menu['mst_id'] ?>">
                                        </div>
                                    </label>
                                    <?php if ($hasChildren): ?>
                                    <div class="divide-y border-t">
                                        <?php renderMenuTree($menu['children'], $checkedIds, $adminMenuIds, $isSuperadmin, $level + 1); ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif;
                            }
                        }

                        // Cek apakah role terpilih adalah superadmin
                        $isSuperadmin = $selectedRole ? $model->isSuperadminRole($selectedRole) : false;

                        renderMenuTree($menuTree, $checkedMenuIds, $adminMenuIds, $isSuperadmin);
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function checkAllMenus(isChecked) {
    document.querySelectorAll('.menu-checkbox').forEach(cb => cb.checked = isChecked);
}

function toggleGroup(group, isChecked) {
    document.querySelectorAll('[data-parent-group="' + group + '"]').forEach(cb => cb.checked = isChecked);
}

function filterRoleList() {
    const keyword = document.getElementById('roleSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.role-item').forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(keyword) ? '' : 'none';
    });
}
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

