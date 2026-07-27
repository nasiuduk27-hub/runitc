<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/roles.php');
requireSuperadmin($pdo_run);

// Handle POST actions
$controller = new SystemAccessController($pdo_run);
$controller->handleAction();

$model = $controller->getModel();

// Get all roles from database
$roles = $model->getRoles();

// Get users with roles for the table
$usersData = $model->getUsersWithRoles([], 20, 0);
$usersWithRoles = $usersData['users'];

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'Role Management';
include BASE_PATH . '/includes/layout_header.php';

// Color palette for role cards
$colorPalette = ['blue', 'emerald', 'orange', 'purple', 'rose', 'cyan', 'amber', 'indigo'];
$bgColors = [
    'blue'    => 'bg-blue-100 text-blue-700',
    'emerald' => 'bg-emerald-100 text-emerald-700',
    'orange'  => 'bg-orange-100 text-orange-700',
    'purple'  => 'bg-purple-100 text-purple-700',
    'rose'    => 'bg-rose-100 text-rose-700',
    'cyan'    => 'bg-cyan-100 text-cyan-700',
    'amber'   => 'bg-amber-100 text-amber-700',
    'indigo'  => 'bg-indigo-100 text-indigo-700',
];
$avatarColors = ['bg-slate-900', 'bg-blue-600', 'bg-emerald-600', 'bg-orange-500', 'bg-purple-600', 'bg-rose-500'];
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

<div class="dashboard-wrapper space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="text-sm text-gray-400 font-semibold mb-1">System Management / Access Control</div>
            <h1 class="text-2xl font-bold text-gray-900">Roles List</h1>
            <p class="text-sm text-gray-500 mt-2 max-w-3xl">
                Role digunakan untuk memberikan akses menu dan fitur tertentu kepada user. Setiap user hanya melihat menu sesuai role yang diberikan.
            </p>
        </div>
        <button type="button" onclick="openRoleModal()" class="px-4 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover shadow-sm shrink-0">
            <i class="fa-solid fa-plus mr-2"></i>Add New Role
        </button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 bg-gray-50 p-4 rounded-2xl border border-gray-200">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" id="roleSearchInput" onkeyup="filterRoles()" placeholder="Search role by name or code..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 bg-white">
        </div>
        <div class="w-full sm:w-56">
            <select id="roleStatusFilter" onchange="filterRoles()" class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 bg-white font-medium text-gray-700">
                <option value="all">All Roles Status</option>
                <option value="active">Active Roles (Has Users)</option>
                <option value="empty">Empty Roles (No Users)</option>
            </select>
        </div>
    </div>

    <div class="relative group">
        <button type="button" onclick="scrollRoles('left')" class="absolute -left-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-brand-primary border border-brand-primary rounded-full shadow-md flex items-center justify-center text-white hover:bg-brand-primaryHover focus:outline-none opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div id="rolesSlider" class="grid grid-rows-2 grid-flow-col auto-cols-[100%] md:auto-cols-[calc(50%-10px)] xl:auto-cols-[calc(33.333%-14px)] gap-5 overflow-x-auto pb-4 snap-x snap-mandatory scroll-smooth scrollbar-none" style="scrollbar-width: none;">
            
            <?php foreach ($roles as $index => $role):
                $color = $colorPalette[$index % count($colorPalette)];
                $badgeClass = $bgColors[$color] ?? 'bg-gray-100 text-gray-600';
                $userCount = (int) ($role['user_count'] ?? 0);
                $menuCount = (int) ($role['menu_count'] ?? 0);
                $isSuperadmin = $model->isSuperadminRole($role);
                
                // Role dengan user > 0 atau superadmin tidak bisa dihapus via UI
                $canDelete = ($userCount === 0 && $menuCount === 0 && !$isSuperadmin);

                // Role '01' tidak muncul di assign dropdown (hanya superadmin)
                $isHiddenRole = ($role['grpaccess'] === '01');
                
                $searchString = strtolower(($role['grpdesc'] ?? '') . ' ' . $role['grpaccess'] . ' ' . $role['grpacc']);
                $statusType = ($userCount > 0) ? 'active' : 'empty';
            ?>
                <div class="role-card bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition snap-start flex flex-col justify-between h-[180px]" 
                     data-search="<?= htmlspecialchars($searchString) ?>" 
                     data-status="<?= $statusType ?>">
                     
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm text-gray-500">
                                <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?> &middot; <?= $menuCount ?> menu<?= $menuCount !== 1 ? 's' : '' ?>
                                <?php if ($isSuperadmin): ?>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Superadmin</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex -space-x-2 mt-2">
                                <?php for ($i = 0; $i < min($userCount, 3); $i++): ?>
                                    <div class="w-8 h-8 rounded-full <?= $avatarColors[$i % count($avatarColors)] ?> text-white flex items-center justify-center text-xs font-bold border-2 border-white">
                                        <?= chr(65 + $i) ?>
                                    </div>
                                <?php endfor; ?>
                                <?php if ($userCount > 3): ?>
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold border-2 border-white">+<?= $userCount - 3 ?></div>
                                <?php endif; ?>
                                <?php if ($userCount === 0): ?>
                                    <div class="text-xs text-gray-400 italic mt-1">No users assigned</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 shrink-0"><?= htmlspecialchars($role['grpaccess']) ?>/<?= htmlspecialchars($role['grpacc']) ?></span>
                    </div>

                    <div class="flex items-end justify-between gap-4 mt-auto">
                        <div>
                            <h2 class="role-title text-base font-bold text-gray-900 line-clamp-1 mb-0.5"><?= htmlspecialchars($role['grpdesc'] ?? $role['grpacc']) ?></h2>
                            <div class="flex items-center gap-2 mt-1">
                                <button type="button" onclick="openRoleModal(<?= (int)$role['rec_id'] ?>, '<?= htmlspecialchars(addslashes($role['grpdesc'] ?? '')) ?>')" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">
                                    Edit Role
                                </button>
                                <?php if ($canDelete): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus role ini?\n\nRole ini tidak dipakai oleh user manapun.')">
    <?php echo Csrf::html(); ?>
                                    <input type="hidden" name="action" value="delete_role">
                                    <input type="hidden" name="role_id" value="<?= (int)$role['rec_id'] ?>">
                                    <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-600">Delete</button>
                                </form>
                                <?php elseif ($isSuperadmin): ?>
                                    <span class="text-xs font-semibold text-gray-400 cursor-not-allowed" title="Role Superadmin tidak bisa dihapus">Delete</span>
                                <?php else: ?>
                                    <span class="text-xs font-semibold text-gray-400 cursor-not-allowed" title="Role masih dipakai oleh <?= $userCount ?> user">Delete</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="role_menu.php?role_id=<?= (int)$role['rec_id'] ?>" title="Manage menu access" class="w-9 h-9 rounded-xl bg-brand-primary text-white hover:bg-brand-primaryHover flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-list-check text-sm"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="addRoleStaticCard" class="bg-white rounded-2xl border border-dashed border-gray-300 shadow-sm p-5 flex items-center justify-between gap-4 hover:border-slate-400 transition snap-start h-[180px]">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Add New Role</h2>
                    <p class="text-xs text-gray-500 mt-1">Tambah role baru ke sistem jika belum ada.</p>
                    <button type="button" onclick="openRoleModal()" class="mt-3 px-3 py-1.5 rounded-xl bg-brand-primary text-white text-xs font-semibold hover:bg-brand-primaryHover">
                        Add Role
                    </button>
                </div>
                <div class="hidden sm:flex w-14 h-14 rounded-xl bg-slate-50 items-center justify-center text-slate-400 text-xl shrink-0">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
            </div>

        </div>

        <button type="button" onclick="scrollRoles('right')" class="absolute -right-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 bg-brand-primary border border-brand-primary rounded-full shadow-md flex items-center justify-center text-white hover:bg-brand-primaryHover focus:outline-none opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-12">
        <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Total users with their roles</h2>
                <p class="text-sm text-gray-500">Daftar user dan role yang sedang digunakan.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <div class="relative w-full sm:w-64">
                    <i class="fa-solid fa-user-gear absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" id="userTableSearch" onkeyup="filterUserTable()" placeholder="Search name or id..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 bg-white">
                </div>
                <div class="w-full sm:w-48">
                    <select id="userRoleFilter" onchange="filterUserTable()" class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 bg-white font-medium text-gray-700">
                        <option value="all">All Roles</option>
                        <?php foreach ($roles as $r): 
                            $roleName = htmlspecialchars($r['grpdesc'] ?? $r['grpacc']);
                        ?>
                            <option value="<?= strtolower($roleName) ?>"><?= $roleName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a href="user_role.php" class="text-sm text-brand-primary hover:text-brand-primaryHover font-semibold flex items-center shrink-0 self-center">
                    <i class="fa-solid fa-user-gear mr-1"></i>Manage User Roles
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody" class="divide-y">
                    <?php if (empty($usersWithRoles)): ?>
                    <tr id="emptyUserStaticRow"><td colspan="4" class="px-5 py-8 text-center text-gray-400 italic">Belum ada data user.</td></tr>
                    <?php else: ?>
                        <?php foreach ($usersWithRoles as $user): 
                            $userDisplayName = $user['account_nm'] ?? 'Unknown';
                            $userSubText = $user['email_id'] ?? $user['account_id'] ?? '-';
                            
                            $assignedRolesString = '';
                            if (!empty($user['roles'])) {
                                foreach ($user['roles'] as $r) {
                                    $assignedRolesString .= strtolower($r['grpdesc'] ?? $r['grpacc']) . '|';
                                }
                            }
                            
                            $searchData = strtolower($userDisplayName . ' ' . $userSubText);
                        ?>
                        <tr class="user-row hover:bg-gray-50 transition-colors" 
                            data-search="<?= htmlspecialchars($searchData) ?>" 
                            data-roles="<?= htmlspecialchars($assignedRolesString) ?>">
                            
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($userDisplayName) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($userSubText) ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <?php if (!empty($user['roles'])): ?>
                                    <?php foreach ($user['roles'] as $ri => $r):
                                        $rc = $bgColors[$colorPalette[$ri % count($colorPalette)]] ?? 'bg-gray-100 text-gray-600';
                                    ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $rc ?> mr-1 inline-block my-0.5"><?= htmlspecialchars($r['grpdesc'] ?? $r['grpacc']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if ((int)($user['status'] ?? 0) === 1): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Active</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="user_role.php?user_id=<?= (int)($user['rec_id'] ?? 0) ?>" class="text-brand-primary hover:text-brand-primaryHover font-semibold">Manage</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <tr id="noUserFoundRow" class="hidden">
                        <td colspan="4" class="px-5 py-10 text-center text-gray-400 italic">
                            <i class="fa-solid fa-user-slash block text-xl mb-1 opacity-60"></i>
                            Tidak ada data user yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="roleModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeRoleModal()"></div>
    <div class="relative max-w-lg mx-auto mt-20 bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b flex items-start justify-between gap-4">
            <div class="text-center w-full">
                <h2 id="roleModalTitle" class="text-xl font-bold text-gray-900">Add New Role</h2>
                <p class="text-sm text-gray-500 mt-1">Isi informasi role baru.</p>
            </div>
            <button type="button" onclick="closeRoleModal()" class="absolute top-5 right-5 text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-5">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
            <input type="hidden" name="action" id="roleFormAction" value="create_role">
            <input type="hidden" name="role_id" id="roleFormId" value="">

            <div id="roleCodeFields">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Group Access Code</label>
                        <input name="grpaccess" id="modalGrpaccess" type="text" placeholder="e.g. 03" maxlength="10" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Access Account Code</label>
                        <input name="grpacc" id="modalGrpacc" type="text" placeholder="e.g. ADM01" maxlength="50" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Role Name / Description</label>
                <input name="grpdesc" id="modalGrpdesc" type="text" placeholder="Enter role name" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>

            <div class="flex justify-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Submit</button>
                <button type="button" onclick="closeRoleModal()" class="px-5 py-2.5 rounded-xl bg-brand-primary text-white text-sm font-semibold hover:bg-brand-primaryHover">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(roleId = 0, roleName = '') {
    const modal = document.getElementById('roleModal');
    const title = document.getElementById('roleModalTitle');
    const action = document.getElementById('roleFormAction');
    const idField = document.getElementById('roleFormId');
    const codeFields = document.getElementById('roleCodeFields');
    const descInput = document.getElementById('modalGrpdesc');

    if (roleId > 0) {
        title.textContent = 'Edit Role';
        action.value = 'update_role';
        idField.value = roleId;
        descInput.value = roleName;
        codeFields.style.display = 'none';
    } else {
        title.textContent = 'Add New Role';
        action.value = 'create_role';
        idField.value = '';
        descInput.value = '';
        document.getElementById('modalGrpaccess').value = '';
        document.getElementById('modalGrpacc').value = '';
        codeFields.style.display = 'block';
    }

    modal.classList.remove('hidden');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.add('hidden');
}

function scrollRoles(direction) {
    const slider = document.getElementById('rolesSlider');
    const scrollAmount = slider.clientWidth; 
    
    if (direction === 'left') {
        slider.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function filterRoles() {
    const searchInput = document.getElementById('roleSearchInput').value.toLowerCase();
    const statusFilter = document.getElementById('roleStatusFilter').value;
    const cards = document.querySelectorAll('.role-card');
    
    cards.forEach(card => {
        const searchData = card.getAttribute('data-search');
        const statusData = card.getAttribute('data-status');
        
        const matchesSearch = searchData.includes(searchInput);
        const matchesStatus = (statusFilter === 'all' || statusData === statusFilter);
        
        if (matchesSearch && matchesStatus) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterUserTable() {
    const searchInput = document.getElementById('userTableSearch').value.toLowerCase();
    const roleFilter = document.getElementById('userRoleFilter').value;
    const rows = document.querySelectorAll('.user-row');
    const noResultRow = document.getElementById('noUserFoundRow');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        const rolesData = row.getAttribute('data-roles');
        
        const matchesSearch = searchData.includes(searchInput);
        const matchesRole = (roleFilter === 'all' || rolesData.includes(roleFilter));
        
        if (matchesSearch && matchesRole) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (visibleCount === 0 && rows.length > 0) {
        noResultRow.classList.remove('hidden');
    } else {
        noResultRow.classList.add('hidden');
    }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>
