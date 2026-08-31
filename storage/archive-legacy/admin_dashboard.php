<?php
$basePath = dirname(__DIR__, 2);
require_once $basePath.'/config.php';

require_once BASE_PATH.'/includes/menu_guard.php';
/** @var PDO $pdo_run */
requireMenuAccess($pdo_run, 'modules/admin/dashboard.php');

requireSuperadmin($pdo_run);

$model = new SystemAccess($pdo_run);

$totalUsers = $model->countTotalUsers();
$activeUsers = $model->countActiveUsers();
$inactiveUsers = $model->countInactiveUsers();
$usersWithoutRole = $model->countUsersWithoutRole();
$totalRoles = $model->countTotalRoles();
$activeMenus = $model->countActiveMenus();

$recentActivity = $model->getRecentUserActivity(10);
$recentFailed = $model->getRecentFailedAccess(10);
$filingSummary = $model->getFilingSummary();

$rolePreviewRoles = $model->getRoles();
$rolePreviewId = (int) ($_GET['role_preview_id'] ?? 0);
$rolePreviewSelected = $rolePreviewId > 0 ? $model->getRoleById($rolePreviewId) : null;
$rolePreviewAllowedIds = $rolePreviewId > 0 ? array_map('intval', $model->getMenuAccessByRole($rolePreviewId)) : [];
$rolePreviewMenuTree = $rolePreviewId > 0 ? buildDashboardRoleMenuPreview($model->getMenusTree(), $rolePreviewAllowedIds) : [];

function buildDashboardRoleMenuPreview(array $menus, array $allowedIds): array
{
    $tree = [];

    foreach ($menus as $menu) {
        $children = buildDashboardRoleMenuPreview($menu['children'] ?? [], $allowedIds);
        $isGlobal = (int) ($menu['is_global'] ?? 0) === 1;
        $isAllowed = in_array((int) $menu['rec_id'], $allowedIds, true);

        if (! $isGlobal && ! $isAllowed && empty($children)) {
            continue;
        }

        $menu['children'] = $children;
        $tree[] = $menu;
    }

    return $tree;
}

function countDashboardRoleMenuPreview(array $menus): int
{
    $count = 0;
    foreach ($menus as $menu) {
        $count++;
        $count += countDashboardRoleMenuPreview($menu['children'] ?? []);
    }

    return $count;
}

function renderDashboardRoleMenuPreview(array $menus, int $level = 0): void
{
    foreach ($menus as $menu) {
        $hasChildren = ! empty($menu['children']);
        $icon = htmlspecialchars($menu['icon'] ?: ($hasChildren ? 'fa-solid fa-folder' : 'fa-regular fa-file-lines'));
        $title = htmlspecialchars($menu['title'] ?? 'Menu');
        $url = htmlspecialchars($menu['url'] ?? '#');
        $padding = 10 + ($level * 16);
        ?>
        <div class="role-preview-menu-row flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 transition" style="padding-left: <?php echo $padding ?>px">
            <i class="<?php echo $icon ?> w-4 text-center <?php echo $hasChildren ? 'text-blue-600' : 'text-gray-400' ?> text-xs"></i>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-gray-700 truncate"><?php echo $title ?></div>
                <div class="text-[10px] text-gray-400 truncate"><?php echo $url ?></div>
            </div>
        </div>
        <?php
            if ($hasChildren) {
                renderDashboardRoleMenuPreview($menu['children'], $level + 1);
            }
    }
}

// CBT operational data (room monitoring)
$cbtSummary = [];
try {
    $stmtRooms = $pdo_war->prepare("
        SELECT
            a.rec_id,
            a.admin_no,
            a.testdt,
            a.statrec,
            (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_participants,
            (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.sub_adm_id != 0) AS assigned_participants,
            (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id AND t.statrec IN ('7','8','9','c','C')) AS finished_participants
        FROM t3sTAdm1n a
        WHERE DATE(a.testdt) = CURDATE()
        ORDER BY a.admin_no ASC
    ");
    $stmtRooms->execute();
    $cbtSummary = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $cbtSummary = [];
}

$activeRoomCount = 0;
$completedRoomCount = 0;
$totalParticipantsToday = 0;
$totalAssigned = 0;
$totalUnassigned = 0;
foreach ($cbtSummary as $room) {
    $activeRoomCount++;
    if ((int) $room['total_participants'] > 0 && (int) $room['total_participants'] === (int) $room['finished_participants']) {
        $completedRoomCount++;
    }
    $totalParticipantsToday += (int) $room['total_participants'];
    $totalAssigned += (int) $room['assigned_participants'];
}
$totalUnassigned = $totalParticipantsToday - $totalAssigned;

// CRC pending count
$crcPending = 0;
try {
    $stmtCrc = $pdo_run->prepare('
        SELECT COUNT(DISTINCT f.rec_id)
        FROM runit_filing_system f
        LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id
        WHERE ff.file_id IS NULL
    ');
    $stmtCrc->execute();
    $crcPending = (int) $stmtCrc->fetchColumn();
} catch (Throwable $e) {
    $crcPending = 0;
}

// Berita Acara pending count
$baPending = 0;
try {
    $stmtBa = $pdo_run->prepare("
        SELECT COUNT(DISTINCT f.rec_id)
        FROM runit_filing_system f
        WHERE f.keterangan LIKE '%Berita Acara%'
    ");
    $stmtBa->execute();
    $baPending = (int) $stmtBa->fetchColumn();
} catch (Throwable $e) {
    $baPending = 0;
}

// System Health checks
$healthStatus = [];

try {
    $pdo->query('SELECT 1');
    $healthStatus['Main DB'] = ['status' => 'ok', 'note' => ''];
} catch (Throwable $e) {
    $healthStatus['Main DB'] = ['status' => 'down', 'note' => 'Koneksi gagal'];
}

if ($pdo_bot) {
    try {
        $pdo_bot->query('SELECT 1');
        $healthStatus['Bot DB'] = ['status' => 'ok', 'note' => ''];
    } catch (Throwable $e) {
        $healthStatus['Bot DB'] = ['status' => 'down', 'note' => 'Koneksi gagal'];
    }
} else {
    $healthStatus['Bot DB'] = ['status' => 'warn', 'note' => 'Tidak terkonfigurasi'];
}

try {
    $pdo_run->query('SELECT 1');
    $healthStatus['Run DB'] = ['status' => 'ok', 'note' => ''];
} catch (Throwable $e) {
    $healthStatus['Run DB'] = ['status' => 'down', 'note' => 'Koneksi gagal'];
}

if ($pdo_war) {
    try {
        $pdo_war->query('SELECT 1');
        $healthStatus['War DB'] = ['status' => 'ok', 'note' => ''];
    } catch (Throwable $e) {
        $healthStatus['War DB'] = ['status' => 'down', 'note' => 'Koneksi gagal'];
    }
} else {
    $healthStatus['War DB'] = ['status' => 'warn', 'note' => 'Tidak terkonfigurasi'];
}

$ftpNote = '';
try {
    $ftp_config_check = $ftp_config ?? [];
    if (! empty($ftp_config_check['host'])) {
        $conn = @ftp_connect($ftp_config_check['host'], (int) ($ftp_config_check['port'] ?? 21), 5);
        if ($conn) {
            $login = @ftp_login($conn, $ftp_config_check['user'] ?? '', $ftp_config_check['pass'] ?? '');
            if ($login) {
                $healthStatus['FTP Connection'] = ['status' => 'ok', 'note' => 'Terhubung'];
                @ftp_close($conn);
            } else {
                $healthStatus['FTP Connection'] = ['status' => 'warn', 'note' => 'Login gagal'];
                @ftp_close($conn);
            }
        } else {
            $healthStatus['FTP Connection'] = ['status' => 'down', 'note' => 'Tidak dapat terhubung'];
        }
    } else {
        $healthStatus['FTP Connection'] = ['status' => 'warn', 'note' => 'Tidak terkonfigurasi'];
    }
} catch (Throwable $e) {
    $healthStatus['FTP Connection'] = ['status' => 'down', 'note' => 'Error: '.$e->getMessage()];
}

try {
    $storagePath = BASE_PATH.'/storage';
    if (is_dir($storagePath)) {
        $total = @disk_total_space($storagePath);
        $free = @disk_free_space($storagePath);
        if ($total > 0 && $free !== false) {
            $usedPct = round((($total - $free) / $total) * 100);
            $healthStatus['Storage / Log Size'] = ['status' => $usedPct > 85 ? 'warn' : 'ok', 'note' => $usedPct.'% terpakai'];
        } else {
            $healthStatus['Storage / Log Size'] = ['status' => 'unknown', 'note' => 'Tidak dapat dibaca'];
        }
    } else {
        $healthStatus['Storage / Log Size'] = ['status' => 'unknown', 'note' => 'Folder storage tidak ditemukan'];
    }
} catch (Throwable $e) {
    $healthStatus['Storage / Log Size'] = ['status' => 'unknown', 'note' => 'Error'];
}

try {
    $errorLog = BASE_PATH.'/storage/php-error.log';
    if (file_exists($errorLog) && is_readable($errorLog)) {
        $logContent = @file_get_contents($errorLog, false, null, -1, 65536);
        if ($logContent !== false) {
            $recentErrors = 0;
            $cutoff = time() - 3600;
            foreach (explode("\n", $logContent) as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                    if (strtotime($m[1]) >= $cutoff) {
                        $recentErrors++;
                    }

                }
            }
            $healthStatus['Recent PHP Errors'] = ['status' => $recentErrors > 0 ? 'warn' : 'ok', 'note' => $recentErrors.' error dalam 1 jam terakhir'];
        } else {
            $healthStatus['Recent PHP Errors'] = ['status' => 'ok', 'note' => 'Tidak terbaca'];
        }
    } else {
        $healthStatus['Recent PHP Errors'] = ['status' => 'ok', 'note' => 'Tidak ada log'];
    }
} catch (Throwable $e) {
    $healthStatus['Recent PHP Errors'] = ['status' => 'unknown', 'note' => 'Error membaca log'];
}

$pageTitle = 'Superadmin Overview';
include BASE_PATH.'/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL ?>assets/css/compact-admin.css">
<style>
.role-preview-select {
  appearance: none;
  -webkit-appearance: none;
  min-width: 150px;
}

.role-preview-select option {
  background: #ffffff;
  color: #1f2937;
}
</style>

<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
  <div class="max-w-7xl mx-auto space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Superadmin Overview</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kondisi user, akses, dan operasional CBT lintas sistem RUNITC.</p>
      </div>
      <div class="text-xs text-gray-400" id="updatedTag">Diperbarui — <span id="updateTime"></span></div>
    </div>

    <div class="flex justify-center">
      <div class="flex items-center gap-2">
        <?php if ($rolePreviewId > 0) { ?>
          <a href="<?php echo rtrim(BASE_URL, '/') ?>/dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-600 hover:text-brand-primary hover:border-blue-200 transition shadow-sm">
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            Back
          </a>
        <?php } ?>

        <form method="GET" class="relative">
          <div class="inline-flex items-center gap-2 rounded-xl bg-brand-primary text-white shadow-md shadow-blue-200 px-3 py-2">
            <i class="fa-solid fa-users-gear text-sm"></i>
            <select name="role_preview_id" onchange="this.form.submit()" class="role-preview-select bg-brand-primary text-white text-xs font-bold focus:outline-none cursor-pointer pr-6">
              <option value="0"><?php echo $rolePreviewId > 0 ? 'Superadmin View' : 'Preview role/division' ?></option>
              <?php foreach ($rolePreviewRoles as $role) { ?>
                <option value="<?php echo (int) $role['rec_id'] ?>" <?php echo (int) $role['rec_id'] === $rolePreviewId ? 'selected' : '' ?>>
                  <?php echo htmlspecialchars($role['grpdesc'] ?? $role['grpacc']) ?>
                </option>
              <?php } ?>
            </select>
            <i class="fa-solid fa-chevron-down text-[10px] opacity-80 pointer-events-none"></i>
          </div>
        </form>
      </div>
    </div>

    <?php if ($rolePreviewId > 0) { ?>
      <div class="mx-auto max-w-xl rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-center">
        <p class="text-xs font-bold text-brand-primary">Previewing as <?php echo htmlspecialchars($rolePreviewSelected['grpdesc'] ?? 'selected role') ?></p>
        <p class="text-[11px] text-blue-500 mt-0.5">Sidebar dan menu mengikuti permission role ini. <?php echo countDashboardRoleMenuPreview($rolePreviewMenuTree) ?> menu tersedia.</p>
      </div>
    <?php } ?>

    <!-- User & Access Stats -->
    <div class="grid grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-green-500">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Users</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($totalUsers) ?></div>
        <div class="text-xs text-gray-400 mt-1"><?php echo number_format($activeUsers) ?> aktif &middot; <?php echo number_format($inactiveUsers) ?> nonaktif</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-amber-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Users Without Role</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($usersWithoutRole) ?></div>
        <div class="text-xs text-gray-400 mt-1">Perlu ditinjau superadmin</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-brand-primary">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Roles</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($totalRoles) ?></div>
        <div class="text-xs text-gray-400 mt-1">1 role kritikal (Superadmin)</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-indigo-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Active Menus</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($activeMenus) ?></div>
        <div class="text-xs text-gray-400 mt-1">Menu terdaftar aktif</div>
      </div>
    </div>

    <!-- Operational Stats -->
    <div class="grid grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-green-500">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Peserta CBT Hari Ini</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($totalParticipantsToday) ?></div>
        <div class="text-xs text-gray-400 mt-1"><?php echo number_format($totalAssigned) ?> assigned &middot; <?php echo number_format($totalUnassigned) ?> unassigned</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-brand-primary">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Room Aktif</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $activeRoomCount - $completedRoomCount ?> / <?php echo $activeRoomCount ?></div>
        <div class="text-xs text-gray-400 mt-1"><?php echo $completedRoomCount ?> room selesai hari ini</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-amber-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">CRC Belum Diunggah</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($crcPending) ?></div>
        <div class="text-xs text-gray-400 mt-1"><?php echo $crcPending > 0 ? 'Perlu ditindaklanjuti' : 'Semua sudah diunggah' ?></div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-red-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Berita Acara Pending</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($baPending) ?></div>
        <div class="text-xs text-gray-400 mt-1">dari <?php echo $activeRoomCount ?> room aktif</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
      <div class="mb-2 text-xs font-bold text-gray-500 uppercase tracking-wide">Role & Security Access</div>
      <div class="flex flex-wrap gap-2 mb-3">
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/users.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-users mr-1"></i>User Management</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/roles.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-shield mr-1"></i>Manage Roles</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/user_role.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-user-gear mr-1"></i>User Role Assignment</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/role_menu.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-list-check mr-1"></i>Menu Permission</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/menu_management.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-bars mr-1"></i>Menu Management</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/permissions.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-eye mr-1"></i>Permission Overview</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/user_list_active.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-user-check mr-1"></i>Active User List</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_access/audit_log.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Audit Log</a>
      </div>
      <div class="mb-2 text-xs font-bold text-gray-500 uppercase tracking-wide">Admin Tools</div>
      <div class="flex flex-wrap gap-2">
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/operational_dashboard.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-gauge-high mr-1"></i>Operational Dashboard</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/reporting.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-chart-bar mr-1"></i>Reporting</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_settings.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-gear mr-1"></i>System Settings</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/admin/system_health.php" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-heart-pulse mr-1"></i>System Health</a>
        <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/cbt_ops/test_admin/index.php" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded-lg hover:bg-brand-primaryHover transition"><i class="fa-solid fa-chart-simple mr-1"></i>CBT Ops Summary</a>
      </div>
    </div>

    <!-- Room Monitoring Grid -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold text-gray-900 text-sm">Monitoring Room — Live</h2>
        <span class="text-xs text-gray-400"><?php echo $activeRoomCount ?> room &middot; data hari ini</span>
      </div>
      <div class="p-4">
        <?php if (empty($cbtSummary)) { ?>
        <div class="text-center py-8 text-gray-400"><i class="fa-solid fa-clock text-3xl mb-2"></i><br>Belum ada data room untuk hari ini.</div>
        <?php } else { ?>
        <div class="grid grid-cols-6 gap-2">
          <?php foreach ($cbtSummary as $room) {
              $roomCode = htmlspecialchars($room['admin_no'] ?? 'R-'.$room['rec_id']);
              $total = (int) ($room['total_participants'] ?? 0);
              $finished = (int) ($room['finished_participants'] ?? 0);
              $assigned = (int) ($room['assigned_participants'] ?? 0);

              if ($total > 0 && $total === $finished) {
                  $statusClass = 'bg-green-100 text-green-700 border-green-300';
                  $statusLabel = 'Selesai';
                  $meta = 'Selesai';
              } elseif ($total > 0 && $assigned < $total) {
                  $statusClass = 'bg-amber-100 text-amber-700 border-amber-300';
                  $statusLabel = 'Perhatian';
                  $meta = $assigned.'/'.$total.' assigned';
              } else {
                  $statusClass = 'bg-blue-100 text-blue-700 border-blue-300';
                  $statusLabel = 'Berjalan';
                  $meta = $total.' peserta';
              }
              ?>
          <div class="<?php echo $statusClass ?> rounded-lg border p-2 text-center text-[11px]">
            <div class="font-bold text-xs"><?php echo $roomCode ?></div>
            <div class="text-[10px] opacity-75"><?php echo $statusLabel ?></div>
            <div class="text-[9px] opacity-60"><?php echo $meta ?></div>
          </div>
          <?php } ?>
        </div>
        <?php } ?>
      </div>
      <div class="px-5 py-2 border-t border-gray-100 flex gap-4 text-[10px] text-gray-400">
        <span><span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1"></span>Berjalan normal</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1"></span>Perlu perhatian</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Bermasalah</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Selesai</span>
      </div>
    </div>

    <!-- Activity + Failed Access + Health + Filing -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Recent User Activity -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-bold text-gray-900 text-sm">Recent User Activity</h2>
          <span class="text-xs text-gray-400">10 terbaru</span>
        </div>
        <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
          <?php if (empty($recentActivity)) { ?>
          <div class="p-6 text-center text-gray-400"><i class="fa-solid fa-square text-gray-200 text-xl mb-2"></i><br>Fitur log audit belum aktif.</div>
          <?php } else { ?>
          <?php foreach ($recentActivity as $act) { ?>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 bg-green-500"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              <b><?php echo htmlspecialchars($act['account_nm'] ?? 'System') ?></b> <?php echo htmlspecialchars($act['action'] ?? '') ?>
              <span class="text-xs text-gray-400 ml-2"><?php echo htmlspecialchars(date('H:i', strtotime($act['created_at'] ?? 'now'))) ?></span>
            </div>
          </div>
          <?php } ?>
          <?php } ?>
        </div>
      </div>

      <!-- Failed / Blocked Access -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-bold text-gray-900 text-sm">Failed / Blocked Access</h2>
          <span class="text-xs text-gray-400">24 jam terakhir</span>
        </div>
        <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
          <?php if (empty($recentFailed)) { ?>
          <div class="p-6 text-center text-gray-400"><i class="fa-solid fa-triangle-exclamation text-gray-200 text-xl mb-2"></i><br>Belum ada data failed access.</div>
          <?php } else { ?>
          <?php foreach ($recentFailed as $fail) { ?>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 bg-red-500"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              <b><?php echo htmlspecialchars($fail['account_nm'] ?? 'Unknown') ?></b> <?php echo htmlspecialchars($fail['action'] ?? '') ?>
              <span class="text-xs text-gray-400 ml-2"><?php echo htmlspecialchars(date('H:i', strtotime($fail['created_at'] ?? 'now'))) ?></span>
            </div>
          </div>
          <?php } ?>
          <?php } ?>
        </div>
      </div>

      <!-- System Health Summary -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-bold text-gray-900 text-sm">System Health Summary</h2>
          <span class="text-xs text-gray-400">Cek langsung dari server</span>
        </div>
        <div class="divide-y divide-gray-100">
          <?php foreach ($healthStatus as $name => $status) {
              $pillClass = match ($status['status']) {
                  'ok' => 'bg-green-100 text-green-700',
                  'warn' => 'bg-amber-100 text-amber-700',
                  'down' => 'bg-red-100 text-red-700',
                  default => 'bg-gray-100 text-gray-500',
              };
              $pillLabel = match ($status['status']) {
                  'ok' => 'Normal',
                  'warn' => 'Perhatian',
                  'down' => 'Gangguan',
                  default => 'Unknown',
              };
              $dotClass = match ($status['status']) {
                  'ok' => 'bg-green-500',
                  'warn' => 'bg-amber-400',
                  'down' => 'bg-red-500',
                  default => 'bg-gray-400',
              };
              ?>
          <div class="px-4 py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-gray-700">
              <span class="w-2 h-2 rounded-full shrink-0 <?php echo $dotClass ?>"></span>
              <span><?php echo htmlspecialchars($name) ?></span>
              <?php if (! empty($status['note'])) { ?>
              <span class="text-xs text-gray-400 font-mono">— <?php echo htmlspecialchars($status['note']) ?></span>
              <?php } ?>
            </div>
            <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold <?php echo $pillClass ?>"><?php echo $pillLabel ?></span>
          </div>
          <?php } ?>
        </div>
      </div>

      <!-- Filing / Upload Summary -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-bold text-gray-900 text-sm">Filing / Upload Summary</h2>
          <span class="text-xs text-gray-400">Hari ini</span>
        </div>
        <div class="divide-y divide-gray-100">
          <?php
                  $todayUploads = (int) ($filingSummary['today_uploads'] ?? 0);
$totalUploads = (int) ($filingSummary['total_uploads'] ?? 0);
?>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 bg-green-500"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              <b><?php echo number_format($todayUploads) ?></b> berkas diunggah hari ini
              <span class="text-xs text-gray-400 ml-2">Sistem filing</span>
            </div>
          </div>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 <?php echo $crcPending > 0 ? 'bg-amber-400' : 'bg-green-500' ?>"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              <b><?php echo number_format($crcPending) ?></b> CRC belum diunggah
              <span class="text-xs text-gray-400 ml-2"><?php echo $crcPending > 0 ? 'Perlu tindak lanjut' : 'Semua lengkap' ?></span>
            </div>
          </div>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 <?php echo $baPending > 0 ? 'bg-amber-400' : 'bg-green-500' ?>"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              <b><?php echo number_format($baPending) ?></b> Berita Acara masih berstatus pending
              <span class="text-xs text-gray-400 ml-2">dari <?php echo $activeRoomCount ?> room aktif</span>
            </div>
          </div>
          <div class="px-4 py-2.5 flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 bg-green-500"></div>
            <div class="flex-1 min-w-0 text-sm text-gray-700">
              Total <b><?php echo number_format($totalUploads) ?></b> berkas tersimpan
              <span class="text-xs text-gray-400 ml-2">Sepanjang masa</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <footer class="text-center text-[10px] text-gray-400 py-4 border-t border-gray-100">RUNITC SUPERADMIN CONTROL CENTER &mdash; modules/admin/dashboard.php</footer>
  </div>
</div>

<script>
document.getElementById('updateTime').textContent =
  new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

tailwind.config = {
  theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6' } } } }
}
</script>

<?php include BASE_PATH.'/includes/layout_footer.php'; ?>
