<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/system_access/menu_management.php');
requireSuperadmin($pdo_run);

$controller = new SystemAccessController($pdo_run);
$controller->handleAction();
$model = $controller->getModel();

// Ambil semua menu (termasuk non-aktif) untuk management
$allMenus = $model->getAllMenusFlat(); // hanya yg is_active=1

// Ambil juga menu non-aktif
$stmt = $pdo_run->query("
    SELECT rec_id, mst_id, title, url, icon, is_global, is_active
    FROM sys_menus
    ORDER BY mst_id ASC, sort_order ASC, rec_id ASC
");
$allMenusRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build full tree (termasuk non-aktif)
function buildFullTree(array $menus, int $parentId): array
{
    $branch = [];
    foreach ($menus as $m) {
        if ((int) $m['mst_id'] === $parentId) {
            $m['children'] = buildFullTree($menus, (int) $m['rec_id']);
            $branch[] = $m;
        }
    }
    return $branch;
}
$menuTree = buildFullTree($allMenusRaw, 0);

// Daftar parent options untuk dropdown
$parentOptions = $model->getAllMenusFlat();

// Protected menu IDs (admin)
$adminMenuIds = array_map('intval', $model->getAdminMenuIds());

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'Menu Management';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">
<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <?php if ($successMsg || $errorMsg): ?>
        <div id="flashToast" class="fixed top-24 right-10 z-[100] transition-all duration-500">
            <div class="<?= $successMsg ? 'border-green-500 text-green-800' : 'border-red-500 text-red-800' ?> bg-white border-l-4 rounded-lg shadow-2xl p-4 flex items-center gap-4 min-w-[320px]">
                <span class="text-xl"><?= $successMsg ? '&#10003;' : '&#10007;' ?></span>
                <span class="font-semibold"><?= $successMsg ?: htmlspecialchars($errorMsg) ?></span>
                <button onclick="document.getElementById('flashToast').remove()" class="ml-auto text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
        </div>
        <script>setTimeout(() => { const t = document.getElementById('flashToast'); if (t) t.remove(); }, 6000);</script>
        <?php endif; ?>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Menu Management</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola struktur menu sidebar</p>
            </div>
            <button onclick="openAddModal()" class="px-5 py-2.5 bg-brand-primary text-white font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-md">
                <i class="fa-solid fa-plus mr-2"></i>Tambah Menu
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="relative flex-1 max-w-md">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="menuSearchInput" placeholder="Cari menu..." oninput="filterMenuTree()"
                               class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                    </div>
                </div>

                <div class="space-y-3">
                    <?php if (empty($menuTree)): ?>
                    <p class="text-gray-400 text-center py-8">Belum ada menu.</p>
                    <?php else: ?>
                    <?php
                    $menuCounter = 0;
                    function renderMenuManagementTree(array $menus, array $adminMenuIds, int &$counter, int $level = 0): void {
                        foreach ($menus as $m) {
                            $counter++;
                            $id = (int) $m['rec_id'];
                            $isActive = (int) ($m['is_active'] ?? 1) === 1;
                            $isGlobal = (int) ($m['is_global'] ?? 0) === 1;
                            $isProtected = in_array($id, $adminMenuIds);
                            $hasChildren = !empty($m['children']);
                            $indent = $level * 24;
                            ?>
                            <div class="menu-item flex items-center justify-between px-4 py-3 rounded-xl transition hover:bg-gray-50 border border-transparent hover:border-gray-200"
                                 data-name="<?= strtolower(htmlspecialchars($m['title'] ?? '')) ?>">
                                <div class="flex items-center gap-3" style="margin-left: <?= $indent ?>px">
                                    <?php if ($level === 0): ?>
                                        <i class="fa-solid fa-folder text-yellow-500"></i>
                                    <?php elseif ($level === 1): ?>
                                        <i class="fa-regular fa-file-lines text-blue-500"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-ellipsis-h text-gray-400"></i>
                                    <?php endif; ?>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-gray-900"><?= htmlspecialchars($m['title'] ?? '') ?></span>
                                            <?php if (!$isActive): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500">Inactive</span>
                                            <?php endif; ?>
                                            <?php if ($isGlobal): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Global</span>
                                            <?php endif; ?>
                                            <?php if ($isProtected): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($m['url'] ?? '#') ?></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick='openEditModal(<?= json_encode($m, JSON_UNESCAPED_UNICODE) ?>)'
                                            class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
                                        <i class="fa-solid fa-pen mr-1"></i>Edit
                                    </button>
                                    <?php if (!$isProtected): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus menu &quot;<?= htmlspecialchars($m['title'] ?? '') ?>&quot;?\n\nSemua akses menu ini akan dihapus. Menu anak akan diangkat ke root.')">
                                        <input type="hidden" name="action" value="delete_menu">
                                        <input type="hidden" name="rec_id" value="<?= $id ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                            <i class="fa-solid fa-trash mr-1"></i>Hapus
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="px-3 py-1.5 text-xs font-semibold bg-gray-50 text-gray-400 rounded-lg cursor-not-allowed"
                                          title="Menu admin dilindungi">Hapus</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                            if ($hasChildren) {
                                renderMenuManagementTree($m['children'], $adminMenuIds, $counter, $level + 1);
                            }
                        }
                    }
                    renderMenuManagementTree($menuTree, $adminMenuIds, $menuCounter);
                    ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addMenuModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closeModal('addMenuModal')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">Tambah Menu</h2>
            <button onclick="closeModal('addMenuModal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_menu">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Menu <span class="text-red-500">*</span></label>
                <input type="text" name="title" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">URL</label>
                <input type="text" name="url" placeholder="#"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Icon (Font Awesome class)</label>
                <input type="text" name="icon" placeholder="fa-solid fa-link"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Parent Menu</label>
                <select name="mst_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                    <option value="0">-- Root (menu utama) --</option>
                    <?php foreach ($parentOptions as $p): ?>
                    <option value="<?= (int) $p['rec_id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_global" value="0">
                    <input type="checkbox" name="is_global" value="1"
                           class="w-4 h-4 text-brand-primary rounded border-gray-300 focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Global</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked
                           class="w-4 h-4 text-brand-primary rounded border-gray-300 focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Aktif</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="0" min="0"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('addMenuModal')"
                        class="px-5 py-2 text-sm font-semibold bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">Batal</button>
                <button type="submit"
                        class="px-5 py-2 text-sm font-semibold bg-brand-primary text-white rounded-lg hover:bg-brand-primaryHover transition shadow-md">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editMenuModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closeModal('editMenuModal')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">Edit Menu</h2>
            <button onclick="closeModal('editMenuModal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_menu">
            <input type="hidden" name="rec_id" id="edit_rec_id">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Menu <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="edit_title" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">URL</label>
                <input type="text" name="url" id="edit_url"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Icon</label>
                <input type="text" name="icon" id="edit_icon"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Parent Menu</label>
                <select name="mst_id" id="edit_mst_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
                    <option value="0">-- Root (menu utama) --</option>
                    <?php foreach ($parentOptions as $p): ?>
                    <option value="<?= (int) $p['rec_id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_global" value="0">
                    <input type="checkbox" name="is_global" id="edit_is_global" value="1"
                           class="w-4 h-4 text-brand-primary rounded border-gray-300 focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Global</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1"
                           class="w-4 h-4 text-brand-primary rounded border-gray-300 focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Aktif</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Sort Order</label>
                <input type="number" name="sort_order" id="edit_sort_order" value="0" min="0"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('editMenuModal')"
                        class="px-5 py-2 text-sm font-semibold bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">Batal</button>
                <button type="submit"
                        class="px-5 py-2 text-sm font-semibold bg-brand-primary text-white rounded-lg hover:bg-brand-primaryHover transition shadow-md">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addMenuModal').classList.remove('hidden');
}

function openEditModal(menu) {
    document.getElementById('edit_rec_id').value = menu.rec_id;
    document.getElementById('edit_title').value = menu.title || '';
    document.getElementById('edit_url').value = menu.url || '';
    document.getElementById('edit_icon').value = menu.icon || '';
    document.getElementById('edit_mst_id').value = menu.mst_id || 0;
    document.getElementById('edit_is_global').checked = parseInt(menu.is_global) === 1;
    document.getElementById('edit_is_active').checked = parseInt(menu.is_active) !== 0;
    document.getElementById('edit_sort_order').value = menu.sort_order || 0;
    document.getElementById('editMenuModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function filterMenuTree() {
    const keyword = document.getElementById('menuSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.menu-item').forEach(item => {
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
