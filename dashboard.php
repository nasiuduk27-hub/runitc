<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

require_once __DIR__ . '/includes/menu_guard.php';
require_once __DIR__ . '/includes/tad_access.php';
require_once __DIR__ . '/includes/tad_participant_recap.php';
/** @var \PDO $pdo_run */

// Superadmin tetap memakai URL dashboard.php, tetapi render tampilan dashboard admin.
if (isSuperadmin($pdo_run)) {
    require __DIR__ . '/modules/admin/dashboard.php';
    exit;
}

// --- KPI data ---
$activeRooms = 0;
$totalParticipants = 0;
$crcPending = 0;
$baPending = 0;
$tadWidgetMode = null;
$tadWidgetRows = [];
$tadWidgetTotal = 0;
$tadParticipantRecapRows = [];
$tadParticipantRecapTotals = [];
$tadParticipantRecapExcludeOptions = [];
$canViewTadParticipantRecap = false;
$tadParticipantRecapFilters = [
    'date' => $_GET['recap_date'] ?? '',
    'is_range' => isset($_GET['recap_is_range']) ? 1 : 0,
    'start_date' => $_GET['recap_start_date'] ?? '',
    'end_date' => $_GET['recap_end_date'] ?? '',
    'exclude_admin_ids' => $_GET['recap_exclude_admin_ids'] ?? [],
    'exclude' => $_GET['recap_exclude'] ?? '',
];
$tadParticipantRecapExcludeIds = normalizeTadRecapAdminIds($tadParticipantRecapFilters['exclude_admin_ids']);
$profileCompletionPercentage = 100;
$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $stmt = $pdo_war->prepare("SELECT COUNT(*) FROM t3sTAdm1n WHERE DATE(testdt) = CURDATE()");
    $stmt->execute();
    $activeRooms = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

try {
    $stmt = $pdo_war->prepare("SELECT COUNT(*) FROM t3sTt4keR5 t JOIN t3sTAdm1n a ON a.rec_id = t.admin_id WHERE DATE(a.testdt) = CURDATE()");
    $stmt->execute();
    $totalParticipants = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

try {
    $stmt = $pdo_run->prepare("SELECT COUNT(DISTINCT f.rec_id) FROM runit_filing_system f LEFT JOIN runit_filing_files ff ON ff.filing_id = f.rec_id WHERE ff.file_id IS NULL");
    $stmt->execute();
    $crcPending = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

try {
    $stmtUser = $pdo_run->prepare("
        SELECT log.account_id, mst.*
        FROM sysitc_users mst
        JOIN sysitc_login log ON mst.login_rec_id = log.rec_id
        WHERE mst.rec_id = ?
        LIMIT 1
    ");
    $stmtUser->execute([$userId]);
    $profileData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($profileData) {
        $totalProfileFields = 0;
        $filledProfileFields = 0;
        $fieldsToCheck = [
            'account_id', 'account_nm', 'dob', 'sexmf',
            'whatsapp', 'address', 'prov_cd', 'kotakabupaten',
        ];

        foreach ($fieldsToCheck as $field) {
            $totalProfileFields++;
            if (!empty($profileData[$field]) && trim((string) $profileData[$field]) !== '-') {
                $filledProfileFields++;
            }
        }

        $totalProfileFields++;
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            if (file_exists(BASE_PATH . '/assets/personal/user_' . $userId . '.' . $ext)) {
                $filledProfileFields++;
                break;
            }
        }

        $stmtEmail = $pdo_run->prepare("SELECT email FROM sysitc_usermail WHERE user_recid = ? ORDER BY asdefault DESC, email LIMIT 1");
        $stmtEmail->execute([$userId]);
        $primaryEmail = trim((string) $stmtEmail->fetchColumn());

        $totalProfileFields++;
        if ($primaryEmail !== '') {
            $filledProfileFields++;
        }

        $stmtBank = $pdo_run->prepare("SELECT bnkcd, accnm, accno FROM sysitc_userbank WHERE user_recid = ? ORDER BY asdefault DESC, bnkcd, accno LIMIT 1");
        $stmtBank->execute([$userId]);
        $primaryBank = $stmtBank->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach (['bnkcd', 'accnm', 'accno'] as $field) {
            $totalProfileFields++;
            if (!empty($primaryBank[$field]) && trim((string) $primaryBank[$field]) !== '-') {
                $filledProfileFields++;
            }
        }

        $profileCompletionPercentage = $totalProfileFields > 0
            ? (int) round(($filledProfileFields / $totalProfileFields) * 100)
            : 0;
    }
} catch (Throwable $e) {
    $profileCompletionPercentage = 100;
}

try {
    if (canManageTadDistribution($pdo_run, $userId)) {
        $tadWidgetMode = 'manage';

        $stmt = $pdo_war->prepare("
            SELECT
                a.rec_id,
                a.admin_no,
                a.client_id,
                a.testdt,
                (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.admin_id = a.rec_id) AS total_takers,
                GROUP_CONCAT(DISTINCT sub.spv_recid ORDER BY sub.batch_no ASC SEPARATOR ',') AS spv_ids,
                GROUP_CONCAT(DISTINCT CONCAT(sub.batch_no, ':', sub.authorize_amt) ORDER BY sub.batch_no ASC SEPARATOR ', ') AS batch_summary
            FROM t3sTAdm1n a
            LEFT JOIN t3sT5ub4dm1n sub ON sub.admin_id = a.rec_id
            WHERE a.statrec = '0'
              AND DATE(a.testdt) >= CURDATE()
            GROUP BY a.rec_id, a.admin_no, a.client_id, a.testdt
            ORDER BY a.testdt ASC, a.admin_no ASC
            LIMIT 5
        ");
        $stmt->execute();
        $tadWidgetRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $pdo_war->prepare("
            SELECT COUNT(*)
            FROM t3sTAdm1n a
            WHERE a.statrec = '0'
              AND DATE(a.testdt) >= CURDATE()
        ");
        $stmtTotal->execute();
        $tadWidgetTotal = (int) $stmtTotal->fetchColumn();
    } elseif (userHasTadRole($pdo_run, $userId, ['TAD SPV TEST', 'TAD SPV'])) {
        $spvId = getTadSupervisorIdForUser($pdo_run, $userId);

        if ($spvId > 0) {
            $tadWidgetMode = 'assigned';

            $stmt = $pdo_war->prepare("
                SELECT
                    a.rec_id,
                    a.admin_no,
                    a.client_id,
                    a.testdt,
                    sub.batch_no,
                    sub.authorize_amt,
                    (SELECT COUNT(*) FROM t3sTt4keR5 t WHERE t.sub_adm_id = sub.rec_id) AS assigned_takers
                FROM t3sT5ub4dm1n sub
                JOIN t3sTAdm1n a ON a.rec_id = sub.admin_id
                WHERE a.statrec = '0'
                  AND sub.spv_recid = ?
                  AND DATE(a.testdt) >= CURDATE()
                ORDER BY a.testdt ASC, a.admin_no ASC, sub.batch_no ASC
                LIMIT 5
            ");
            $stmt->execute([$spvId]);
            $tadWidgetRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtTotal = $pdo_war->prepare("
                SELECT COUNT(*)
                FROM t3sT5ub4dm1n sub
                JOIN t3sTAdm1n a ON a.rec_id = sub.admin_id
                WHERE a.statrec = '0'
                  AND sub.spv_recid = ?
                  AND DATE(a.testdt) >= CURDATE()
            ");
            $stmtTotal->execute([$spvId]);
            $tadWidgetTotal = (int) $stmtTotal->fetchColumn();
        }
    }

    if (!empty($tadWidgetRows)) {
        $clientIds = array_values(array_unique(array_filter(array_map('intval', array_column($tadWidgetRows, 'client_id')))));
        $clientMap = [];

        if (!empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $stmtClients = $pdo->prepare("SELECT rec_id, clientnm FROM sys_mstclient WHERE rec_id IN ({$placeholders})");
            $stmtClients->execute($clientIds);

            foreach ($stmtClients->fetchAll(PDO::FETCH_ASSOC) as $client) {
                $clientMap[(int) $client['rec_id']] = $client['clientnm'];
            }
        }

        $spvMap = [];
        $spvIds = [];

        foreach ($tadWidgetRows as $row) {
            foreach (explode(',', (string) ($row['spv_ids'] ?? '')) as $spvIdValue) {
                $spvIdValue = (int) $spvIdValue;
                if ($spvIdValue > 0) {
                    $spvIds[] = $spvIdValue;
                }
            }
        }

        $spvIds = array_values(array_unique($spvIds));
        if (!empty($spvIds)) {
            $placeholders = implode(',', array_fill(0, count($spvIds), '?'));
            $stmtSpv = $pdo_run->prepare("SELECT rec_id, spv_name FROM tad_supervisor WHERE rec_id IN ({$placeholders})");
            $stmtSpv->execute($spvIds);

            foreach ($stmtSpv->fetchAll(PDO::FETCH_ASSOC) as $spv) {
                $spvMap[(int) $spv['rec_id']] = $spv['spv_name'];
            }
        }

        foreach ($tadWidgetRows as &$row) {
            $row['client_nm'] = $clientMap[(int) ($row['client_id'] ?? 0)] ?? '-';
            $assignedSpvNames = [];

            foreach (explode(',', (string) ($row['spv_ids'] ?? '')) as $spvIdValue) {
                $spvIdValue = (int) $spvIdValue;
                if ($spvIdValue > 0 && isset($spvMap[$spvIdValue])) {
                    $assignedSpvNames[] = $spvMap[$spvIdValue];
                }
            }

            $row['spv_names'] = implode(', ', array_unique($assignedSpvNames));
        }
        unset($row);
    }
} catch (Throwable $e) {
    $tadWidgetMode = null;
    $tadWidgetRows = [];
    $tadWidgetTotal = 0;
}

try {
    $tadParticipantRecap = getTadParticipantRecap($pdo, $pdo_run, $pdo_war, $userId, 10, $tadParticipantRecapFilters);
    $tadParticipantRecapRows = $tadParticipantRecap['rows'];
    $tadParticipantRecapTotals = $tadParticipantRecap['totals'];
    $canViewTadParticipantRecap = (bool) $tadParticipantRecap['can_view'];
    $tadParticipantRecapExcludeOptions = getTadParticipantRecapExcludeOptions($pdo, $pdo_run, $pdo_war, $userId, $tadParticipantRecapFilters);
} catch (Throwable $e) {
    $tadParticipantRecapRows = [];
    $tadParticipantRecapTotals = [];
    $tadParticipantRecapExcludeOptions = [];
    $canViewTadParticipantRecap = false;
}

require_once __DIR__ . '/includes/layout_header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Dashboard Overview</h1>
        <p class="text-sm text-gray-500 mt-0.5">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!</p>
    </div>
</div>

<?php if ($profileCompletionPercentage < 100): ?>
    <div class="mb-6 bg-gradient-to-r from-amber-50 to-white border border-amber-200 rounded-2xl shadow-sm p-4 sm:p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-sm font-extrabold text-gray-900">Lengkapi data diri Anda</h2>
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black border border-amber-200">Profile Completion <?= number_format($profileCompletionPercentage) ?>%</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-1">Data profil belum lengkap. Lengkapi sekarang agar akun Anda siap digunakan.</p>
                    <div class="mt-3 w-full max-w-xl bg-amber-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-amber-500 h-1.5 rounded-full" style="width: <?= max(0, min(100, $profileCompletionPercentage)) ?>%"></div>
                    </div>
                </div>
            </div>
            <a href="<?= rtrim(BASE_URL, '/') ?>/modules/profile/index.php" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-xs font-extrabold hover:bg-amber-600 shadow-sm transition shrink-0">
                Lengkapi Sekarang <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
            <i class="fas fa-building text-blue-600 text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Active Rooms Today</p>
            <p class="text-2xl font-bold text-gray-900 mt-0.5"><?= number_format($activeRooms) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="fas fa-user-graduate text-emerald-600 text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Participants Today</p>
            <p class="text-2xl font-bold text-gray-900 mt-0.5"><?= number_format($totalParticipants) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
            <i class="fas fa-cloud-upload-alt text-amber-600 text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">CRC Pending</p>
            <p class="text-2xl font-bold text-gray-900 mt-0.5"><?= number_format($crcPending) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center shrink-0">
            <i class="fas fa-cube text-purple-600 text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">System Modules</p>
            <p class="text-2xl font-bold text-gray-900 mt-0.5">Active</p>
        </div>
    </div>
</div>

<div id="widgetContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-start pb-10">
    <?php if ($tadWidgetMode !== null): ?>
        <div class="widget-card col-span-1 lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden relative group" data-widget-id="tad-upcoming">
            <div class="absolute top-3 right-3 flex gap-1.5 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button" class="widget-drag-handle bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-indigo-600 flex items-center justify-center shadow-sm cursor-grab" title="Geser widget"><i class="fas fa-grip-vertical text-sm"></i></button>
                <button type="button" onclick="resizeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-blue-600 flex items-center justify-center shadow-sm" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-sm"></i></button>
            </div>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">TAD Dashboard</p>
                    <h2 class="text-base font-extrabold text-gray-900"><?= $tadWidgetMode === 'assigned' ? 'Tugas Saya' : 'Nomor Admin Mendatang' ?></h2>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold"><?= number_format($tadWidgetTotal) ?> total</span>
            </div>

            <div class="divide-y divide-gray-100">
                <?php if (empty($tadWidgetRows)): ?>
                    <div class="px-5 py-8 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-gray-50 flex items-center justify-center text-gray-400 mb-2"><i class="fas fa-calendar-check"></i></div>
                        <p class="text-sm font-semibold text-gray-600">Belum ada jadwal mendatang.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tadWidgetRows as $row): ?>
                        <div class="px-5 py-3 hover:bg-gray-50 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-extrabold text-gray-900 truncate"><?= htmlspecialchars($row['admin_no'] ?? '-') ?></p>
                                        <?php if ($tadWidgetMode === 'assigned'): ?>
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black border border-emerald-100">Batch <?= htmlspecialchars($row['batch_no'] ?? '-') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate mt-0.5"><?= htmlspecialchars($row['client_nm'] ?? '-') ?></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-bold text-gray-700"><?= !empty($row['testdt']) ? date('d M Y', strtotime($row['testdt'])) : '-' ?></p>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">Upcoming</p>
                                </div>
                            </div>
                            <div class="mt-2 text-[11px] text-gray-500 flex items-center gap-2">
                                <?php if ($tadWidgetMode === 'assigned'): ?>
                                    <i class="fas fa-user-check text-emerald-500"></i>
                                    <span><?= number_format((int) ($row['assigned_takers'] ?? $row['authorize_amt'] ?? 0)) ?> peserta ditugaskan</span>
                                <?php else: ?>
                                    <i class="fas fa-users-cog text-indigo-500"></i>
                                    <span class="truncate"><?= !empty($row['spv_names']) ? htmlspecialchars($row['spv_names']) : 'Belum ada SPV' ?></span>
                                    <span class="text-gray-300">/</span>
                                    <span><?= number_format((int) ($row['total_takers'] ?? 0)) ?> peserta</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                <a href="<?= rtrim(BASE_URL, '/') ?>/modules/cbt_ops/test_admin/index.php" class="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                    Lihat semua <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canViewTadParticipantRecap): ?>
        <div class="widget-card col-span-1 lg:col-span-4 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden relative group" data-widget-id="tad-participant-recap">
            <div class="absolute top-3 right-3 flex gap-1.5 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button" class="widget-drag-handle bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-indigo-600 flex items-center justify-center shadow-sm cursor-grab" title="Geser widget"><i class="fas fa-grip-vertical text-sm"></i></button>
                <button type="button" onclick="resizeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-blue-600 flex items-center justify-center shadow-sm" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-sm"></i></button>
            </div>
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider">Rekap TAD</p>
                    <h2 class="text-base font-extrabold text-gray-900">Rekap Peserta per Tanggal</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Ringkasan nomor admin mendatang berdasarkan tanggal tes.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full bg-gray-50 text-gray-700 text-xs font-bold border border-gray-200"><?= number_format((int) ($tadParticipantRecapTotals['total_dates'] ?? 0)) ?> tanggal</span>
                    <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100"><?= number_format((int) ($tadParticipantRecapTotals['total_admins'] ?? 0)) ?> nomor admin</span>
                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100"><?= number_format((int) ($tadParticipantRecapTotals['total_participants'] ?? 0)) ?> peserta</span>
                    <?php $recapPrintParams = array_filter([
                        'recap_date' => $tadParticipantRecapFilters['date'] ?? '',
                        'recap_is_range' => !empty($tadParticipantRecapFilters['is_range']) ? 1 : '',
                        'recap_start_date' => $tadParticipantRecapFilters['start_date'] ?? '',
                        'recap_end_date' => $tadParticipantRecapFilters['end_date'] ?? '',
                        'recap_exclude' => $tadParticipantRecapFilters['exclude'] ?? '',
                    ], static fn($value) => trim((string) $value) !== ''); ?>
                    <?php foreach ($tadParticipantRecapExcludeIds as $excludeId): ?>
                        <?php $recapPrintParams['recap_exclude_admin_ids'][] = $excludeId; ?>
                    <?php endforeach; ?>
                    <a href="<?= rtrim(BASE_URL, '/') ?>/modules/cbt_ops/test_admin/participant_recap_print.php<?= !empty($recapPrintParams) ? '?' . http_build_query($recapPrintParams) : '' ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gray-900 text-white text-xs font-extrabold hover:bg-gray-800 transition">
                        <i class="fas fa-print text-[10px]"></i> Cetak Rekap
                    </a>
                </div>
            </div>

            <form method="GET" class="px-5 py-4 bg-gray-50 border-b border-gray-100" id="tadRecapFilterForm">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    <div id="recapSingleDateField" class="<?= !empty($tadParticipantRecapFilters['is_range']) ? 'hidden' : '' ?>">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider">Tanggal</label>
                            <label class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 cursor-pointer whitespace-nowrap">
                                <input type="checkbox" name="recap_is_range" value="1" id="recapRangeToggleA" class="w-3 h-3 text-indigo-600 border-gray-300 rounded" <?= !empty($tadParticipantRecapFilters['is_range']) ? 'checked' : '' ?>> Date range
                            </label>
                        </div>
                        <input type="date" name="recap_date" value="<?= htmlspecialchars((string) ($tadParticipantRecapFilters['date'] ?? '')) ?>" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div id="recapRangeStartField" class="<?= !empty($tadParticipantRecapFilters['is_range']) ? '' : 'hidden' ?>">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider">Dari Tanggal</label>
                            <label class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 cursor-pointer whitespace-nowrap">
                                <input type="checkbox" id="recapRangeToggleB" class="w-3 h-3 text-indigo-600 border-gray-300 rounded" <?= !empty($tadParticipantRecapFilters['is_range']) ? 'checked' : '' ?>> Date range
                            </label>
                        </div>
                        <input type="date" name="recap_start_date" value="<?= htmlspecialchars((string) ($tadParticipantRecapFilters['start_date'] ?? '')) ?>" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div id="recapRangeEndField" class="<?= !empty($tadParticipantRecapFilters['is_range']) ? '' : 'hidden' ?>">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                        <input type="date" name="recap_end_date" value="<?= htmlspecialchars((string) ($tadParticipantRecapFilters['end_date'] ?? '')) ?>" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="md:col-span-2 relative">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Kecualikan Data</label>
                        <button type="button" id="recapExcludeTrigger" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center justify-between hover:bg-gray-50 transition">
                            <span id="recapExcludeSummary"><?= count($tadParticipantRecapExcludeIds) ?> data dipilih</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                        </button>
                        <div id="recapExcludeFields" class="hidden absolute z-30 left-0 right-0 top-full mt-2 space-y-2 bg-white border border-gray-200 rounded-2xl shadow-xl p-3">
                            <div class="border border-gray-300 rounded-xl overflow-hidden">
                                <div class="p-2 border-b border-gray-100 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                                    <input type="text" id="recapExcludeSearch" placeholder="Cari sekolah/kode..." class="w-full sm:flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span id="recapExcludeCount" class="text-[10px] font-bold text-gray-500 whitespace-nowrap">0 dipilih</span>
                                        <button type="button" id="recapExcludeSelectAll" class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-extrabold hover:bg-indigo-100 transition">Pilih Semua</button>
                                    </div>
                                </div>
                                <div id="recapExcludeList" class="max-h-56 overflow-y-auto divide-y divide-gray-100">
                                    <?php if (empty($tadParticipantRecapExcludeOptions)): ?>
                                        <div class="px-3 py-4 text-xs text-gray-400 text-center">Tidak ada data untuk tanggal ini.</div>
                                    <?php else: ?>
                                        <?php foreach ($tadParticipantRecapExcludeOptions as $option): ?>
                                            <label class="recap-exclude-item flex items-start gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer" data-search="<?= htmlspecialchars(strtolower($option['label'])) ?>">
                                                <input type="checkbox" name="recap_exclude_admin_ids[]" value="<?= (int) $option['rec_id'] ?>" class="recap-exclude-checkbox mt-0.5 w-3.5 h-3.5 text-indigo-600 border-gray-300 rounded" <?= in_array((int) $option['rec_id'], $tadParticipantRecapExcludeIds, true) ? 'checked' : '' ?>>
                                                <span class="text-xs text-gray-700 leading-snug"><?= htmlspecialchars($option['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="text" name="recap_exclude" value="<?= htmlspecialchars((string) ($tadParticipantRecapFilters['exclude'] ?? '')) ?>" placeholder="Keyword tambahan: tester, dummy, trial" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-extrabold hover:bg-indigo-700 transition">Filter</button>
                        <a href="<?= rtrim(BASE_URL, '/') ?>/dashboard.php" class="px-3 py-2 bg-white border border-gray-300 text-gray-600 rounded-lg text-xs font-extrabold hover:bg-gray-100 transition">Reset</a>
                    </div>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">Centang Date range untuk rentang tanggal. Centang Kecualikan Data untuk memilih sekolah/kode atau keyword yang tidak ikut total/cetak.</p>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] text-gray-500 uppercase bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 w-40">Tanggal</th>
                            <th class="px-5 py-3">Daftar Sekolah / Kode</th>
                            <th class="px-5 py-3 text-right w-36">Jumlah Peserta</th>
                            <th class="px-5 py-3 text-right w-36">Peserta Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($tadParticipantRecapRows)): ?>
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm font-semibold text-gray-500">Belum ada jadwal peserta mendatang.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tadParticipantRecapRows as $row): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-3 align-top">
                                        <a href="<?= rtrim(BASE_URL, '/') ?>/modules/cbt_ops/test_admin/index.php?date=<?= urlencode($row['date']) ?>" class="font-extrabold text-indigo-600 hover:text-indigo-800">
                                            <?= date('d M Y', strtotime($row['date'])) ?>
                                        </a>
                                        <div class="text-[10px] text-gray-400 mt-0.5"><?= number_format((int) ($row['admin_count'] ?? 0)) ?> nomor admin</div>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-600 leading-relaxed align-top"><?= htmlspecialchars($row['items_text'] ?? '-') ?></td>
                                    <td class="px-5 py-3 text-right align-top font-extrabold text-gray-900"><?= number_format((int) ($row['total_participants'] ?? 0)) ?></td>
                                    <td class="px-5 py-3 text-right align-top font-extrabold text-emerald-700"><?= number_format((int) ($row['finished_participants'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <span class="text-xs text-gray-500">Menampilkan maksimal 10 tanggal terdekat. Cetak rekap untuk seluruh data mendatang.</span>
                <span class="text-xs font-bold text-emerald-700">Selesai: <?= number_format((int) ($tadParticipantRecapTotals['finished_participants'] ?? 0)) ?> / <?= number_format((int) ($tadParticipantRecapTotals['total_participants'] ?? 0)) ?> peserta</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="widget-card col-span-1 lg:col-span-2 bg-white rounded-2xl border border-dashed border-gray-300 p-2 relative group flex flex-col items-center justify-center text-center min-h-[350px]" data-widget-id="image-placeholder">
        <div class="absolute top-3 right-3 flex gap-1.5 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
            <button type="button" class="widget-drag-handle bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-indigo-600 flex items-center justify-center shadow-sm cursor-grab" title="Geser widget"><i class="fas fa-grip-vertical text-sm"></i></button>
            <button onclick="resizeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-blue-600 flex items-center justify-center shadow-sm"><i class="fas fa-expand-alt text-sm"></i></button>
            <button onclick="removeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-red-500 flex items-center justify-center shadow-sm"><i class="fas fa-times text-sm"></i></button>
        </div>
        <img class="widget-preview hidden w-full h-auto object-contain rounded-xl shadow-sm z-0" src="" alt="Preview">
        <div class="empty-state flex flex-col items-center justify-center p-6 z-10 w-full h-full">
            <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mb-3"><i class="fas fa-image text-xl"></i></div>
            <h3 class="font-bold text-gray-700 mb-1">Widget Baru</h3>
            <p class="text-xs text-gray-500 mb-4 px-4">Upload gambar metrik atau grafik dari lokal.</p>
            <label class="cursor-pointer bg-gray-100 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg hover:bg-blue-600 hover:text-white border border-gray-200">
                <i class="fas fa-upload mr-1"></i> Pilih Gambar
                <input type="file" class="hidden" accept="image/*" onchange="previewLocalImage(event, this)">
            </label>
        </div>
    </div>
</div>

<script>
    (function () {
        const singleField = document.getElementById('recapSingleDateField');
        const startField = document.getElementById('recapRangeStartField');
        const endField = document.getElementById('recapRangeEndField');
        const rangeToggleA = document.getElementById('recapRangeToggleA');
        const rangeToggleB = document.getElementById('recapRangeToggleB');
        const excludeTrigger = document.getElementById('recapExcludeTrigger');
        const excludeFields = document.getElementById('recapExcludeFields');
        const excludeSearch = document.getElementById('recapExcludeSearch');
        const excludeSelectAll = document.getElementById('recapExcludeSelectAll');
        const excludeCount = document.getElementById('recapExcludeCount');
        const excludeSummary = document.getElementById('recapExcludeSummary');

        function getExcludeCheckboxes() {
            return Array.from(document.querySelectorAll('.recap-exclude-checkbox'));
        }

        function updateExcludeCount() {
            const selected = getExcludeCheckboxes().filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (excludeCount) excludeCount.textContent = selected + ' dipilih';
            if (excludeSummary) excludeSummary.textContent = selected > 0 ? selected + ' data dipilih' : 'Klik untuk memilih data';
            updateSelectAllLabel();
        }

        function getVisibleExcludeCheckboxes() {
            return Array.from(document.querySelectorAll('.recap-exclude-item:not(.hidden) .recap-exclude-checkbox'));
        }

        function updateSelectAllLabel() {
            if (!excludeSelectAll) return;

            const visible = getVisibleExcludeCheckboxes();
            const allVisibleSelected = visible.length > 0 && visible.every(function (checkbox) {
                return checkbox.checked;
            });

            excludeSelectAll.textContent = allVisibleSelected ? 'Hapus Semua' : 'Pilih Semua';
        }

        function setRangeMode(enabled) {
            if (!singleField || !startField || !endField || !rangeToggleA || !rangeToggleB) return;

            singleField.classList.toggle('hidden', enabled);
            startField.classList.toggle('hidden', !enabled);
            endField.classList.toggle('hidden', !enabled);
            rangeToggleA.checked = enabled;
            rangeToggleB.checked = enabled;
        }

        function setExcludeMode(enabled) {
            if (!excludeFields) return;

            excludeFields.classList.toggle('hidden', !enabled);
            updateExcludeCount();
        }

        if (rangeToggleA) {
            rangeToggleA.addEventListener('change', function () {
                setRangeMode(this.checked);
            });
        }

        if (rangeToggleB) {
            rangeToggleB.addEventListener('change', function () {
                setRangeMode(this.checked);
            });
        }

        if (excludeTrigger) {
            excludeTrigger.addEventListener('click', function (event) {
                event.stopPropagation();
                setExcludeMode(excludeFields.classList.contains('hidden'));
            });
        }

        if (excludeFields) {
            excludeFields.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        document.addEventListener('click', function () {
            setExcludeMode(false);
        });

        if (excludeSearch) {
            excludeSearch.addEventListener('input', function () {
                const keyword = this.value.trim().toLowerCase();

                document.querySelectorAll('.recap-exclude-item').forEach(function (item) {
                    const text = item.getAttribute('data-search') || '';
                    item.classList.toggle('hidden', keyword !== '' && !text.includes(keyword));
                });
                updateSelectAllLabel();
            });
        }

        if (excludeSelectAll) {
            excludeSelectAll.addEventListener('click', function () {
                const visible = getVisibleExcludeCheckboxes();
                const allVisibleSelected = visible.length > 0 && visible.every(function (checkbox) {
                    return checkbox.checked;
                });

                visible.forEach(function (checkbox) {
                    checkbox.checked = !allVisibleSelected;
                });
                updateExcludeCount();
            });
        }

        getExcludeCheckboxes().forEach(function (checkbox) {
            checkbox.addEventListener('change', updateExcludeCount);
        });

        updateExcludeCount();
    })();
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
