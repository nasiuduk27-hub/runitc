<?php
// File: modules/cbt_ops/filing_system/audit.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/models/FilingAudit.php';

// Validasi Login
if (! isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$permService = new FilingPermissionService($pdo_run);
$auditModel = new FilingAudit($pdo_run);
$isAdmin = $permService->isAdmin($userId);

// Params
$filters = [
    'filing_id' => $_GET['filing_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'keyword' => $_GET['keyword'] ?? '',
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Security Base Rule
// Admin sees all. Owner/Manager sees their files.
if (! $isAdmin) {
    if (! empty($filters['filing_id'])) {
        // Specific file check
        $stmt = $pdo_run->prepare('SELECT * FROM sys_filing WHERE rec_id = ?');
        $stmt->execute([(int) $filters['filing_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $file || ! $permService->canViewAudit($file, $userId)) {
            exit('Anda tidak memiliki izin untuk melihat audit file ini.');
        }
    } else {
        // Global view for non-admin: Only show files they uploaded or manage
        // We use the same buildVisibleFilesWhereClause but stricter: only owner or explicit manage
        $attrs = $permService->resolveUserAttributes($userId);

        $accessOrs = ["(fa.access_type = 'user' AND fa.access_value = ?)"];
        $baseParams = [(string) $userId];

        $typeMapping = [
            'company' => 'company',
            'department' => 'department',
            'division' => 'division',
            'role' => 'role',
            'custom_group' => 'custom_groups',
        ];

        foreach ($typeMapping as $dbType => $attrKey) {
            $vals = $attrs[$attrKey] ?? [];
            if (! empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $accessOrs[] = "(fa.access_type = '$dbType' AND fa.access_value IN ($placeholders))";
                foreach ($vals as $v) {
                    $baseParams[] = (string) $v;
                }
            }
        }
        $accessWhere = implode(' OR ', $accessOrs);

        $filters['base_security_where'] = "(
            f.uploaded_by = ? 
            OR EXISTS (
                SELECT 1 FROM sys_filing_access fa 
                WHERE fa.filing_id = f.rec_id 
                AND fa.can_manage = 1 
                AND ($accessWhere)
            )
        )";
        $filters['base_security_params'] = array_merge([$userId], $baseParams);
    }
}

// Fetch Data
$totalItems = $auditModel->countAuditLogs($filters);
$totalPages = ceil($totalItems / $limit);
$logs = $auditModel->getAuditLogs($filters, $limit, $offset);

// Available actions for dropdown
$availableActions = $auditModel->getAuditActions();

// File title if filtering by specific file
$fileTitle = null;
if (! empty($filters['filing_id']) && ! empty($logs)) {
    $fileTitle = $logs[0]['file_name'];
} elseif (! empty($filters['filing_id'])) {
    $stmt = $pdo_run->prepare('SELECT display_name FROM sys_filing WHERE rec_id = ?');
    $stmt->execute([(int) $filters['filing_id']]);
    $fileTitle = $stmt->fetchColumn();
}

require_once BASE_PATH.'/includes/layout_header.php';

function getActionBadge($action)
{
    if (strpos($action, 'denied') !== false || strpos($action, 'error') !== false) {
        return 'bg-red-50 text-red-600 border-red-200';
    }
    if (strpos($action, 'share') !== false) {
        return 'bg-green-50 text-green-600 border-green-200';
    }
    if (strpos($action, 'trash') !== false || strpos($action, 'delete') !== false) {
        return 'bg-orange-50 text-orange-600 border-orange-200';
    }
    if (strpos($action, 'archive') !== false) {
        return 'bg-gray-100 text-gray-600 border-gray-200';
    }

    return 'bg-blue-50 text-blue-600 border-blue-200';
}
?>

<div class="py-8 md:py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-gray-900 tracking-tight flex items-center gap-3">
                    <i class="fas fa-history text-indigo-600"></i>
                    Audit Log Aktivitas
                </h2>
                <?php if ($fileTitle) { ?>
                    <p class="text-gray-500 text-sm mt-1">
                        Menampilkan histori untuk file: <strong class="text-gray-800"><?= htmlspecialchars($fileTitle) ?></strong>
                    </p>
                <?php } else { ?>
                    <p class="text-gray-500 text-sm mt-1">Log rekam jejak sistem penyimpanan dokumen</p>
                <?php } ?>
            </div>
            <div>
                <a href="index.php" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl text-sm font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Kembali ke Drive
                </a>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <?php if (! empty($filters['filing_id'])) { ?>
                    <input type="hidden" name="filing_id" value="<?= htmlspecialchars($filters['filing_id']) ?>">
                <?php } ?>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Cari Keyword / Catatan</label>
                    <input type="text" name="keyword" value="<?= htmlspecialchars($filters['keyword']) ?>" placeholder="Pencarian bebas..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Jenis Aksi</label>
                    <select name="action" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($availableActions as $k => $v) { ?>
                            <option value="<?= $k ?>" <?= $filters['action'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="flex gap-2">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-bold shadow-sm transition"><i class="fas fa-search"></i></button>
                    <?php if (array_filter($filters)) { ?>
                        <a href="audit<?= ! empty($filters['filing_id']) ? '?filing_id='.$filters['filing_id'] : '' ?>" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm font-bold transition"><i class="fas fa-times"></i></a>
                    <?php } ?>
                </div>
            </form>
        </div>

        <!-- TABLE CARD -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] text-gray-500 uppercase bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 font-bold tracking-wider">Waktu</th>
                            <?php if (empty($filters['filing_id'])) { ?>
                                <th class="px-6 py-4 font-bold tracking-wider">Dokumen</th>
                            <?php } ?>
                            <th class="px-6 py-4 font-bold tracking-wider">User</th>
                            <th class="px-6 py-4 font-bold tracking-wider">Aksi</th>
                            <th class="px-6 py-4 font-bold tracking-wider">Catatan</th>
                            <?php if ($isAdmin) { ?>
                                <th class="px-6 py-4 font-bold tracking-wider">Security (Admin)</th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($logs)) { ?>
                            <tr>
                                <td colspan="<?= $isAdmin ? ($filters['filing_id'] ? 5 : 6) : ($filters['filing_id'] ? 4 : 5) ?>" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300 block"></i>
                                    Tidak ada data audit yang ditemukan.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($logs as $log) { ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-600 font-mono text-xs">
                                        <?= date('Y-m-d', strtotime($log['created_at'])) ?><br>
                                        <span class="text-gray-400"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                    </td>
                                    
                                    <?php if (empty($filters['filing_id'])) { ?>
                                        <td class="px-6 py-3 max-w-[200px] truncate">
                                            <a href="audit.php?filing_id=<?= $log['filing_id'] ?>" class="font-bold text-indigo-600 hover:underline" title="<?= htmlspecialchars($log['file_name']) ?>">
                                                <?= htmlspecialchars($log['file_name']) ?>
                                            </a><br>
                                            <span class="text-[10px] text-gray-400 font-mono"><?= $log['file_code'] ?></span>
                                        </td>
                                    <?php } ?>

                                    <td class="px-6 py-3">
                                        <?php if ($log['user_id']) { ?>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?></span>
                                        <?php } else { ?>
                                            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded border border-purple-100">SYSTEM / CRON</span>
                                        <?php } ?>
                                    </td>

                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="text-[10px] font-bold px-2 py-1 rounded border <?= getActionBadge($log['action']) ?>">
                                            <?= htmlspecialchars($auditModel->formatActionLabel($log['action'])) ?>
                                        </span>
                                    </td>

                                    <td class="px-6 py-3 text-gray-600 text-xs">
                                        <?= htmlspecialchars($log['notes']) ?>
                                    </td>

                                    <?php if ($isAdmin) { ?>
                                        <td class="px-6 py-3 text-[10px] text-gray-400 font-mono">
                                            IP: <?= $log['ip_address'] ?? '-' ?><br>
                                            <span class="truncate inline-block max-w-[150px]" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= htmlspecialchars($log['user_agent']) ?></span>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($totalPages > 1) { ?>
                <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500 font-medium">Menampilkan <?= count($logs) ?> dari <?= $totalItems ?> record</p>
                    <div class="flex gap-1">
                        <?php
                            $q = $_GET;
                unset($q['page']);
                $qs = http_build_query($q);
                $qs = $qs ? '&'.$qs : '';
                ?>
                        <?php if ($page > 1) { ?>
                            <a href="?page=<?= $page - 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-200 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                        <?php } ?>
                        
                        <?php
                    $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++) {
                    ?>
                            <a href="?page=<?= $i ?><?= $qs ?>" class="px-3 py-1 rounded text-xs font-bold transition-colors <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php } ?>

                        <?php if ($page < $totalPages) { ?>
                            <a href="?page=<?= $page + 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-200 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>

<?php require_once BASE_PATH.'/includes/layout_footer.php'; ?>
