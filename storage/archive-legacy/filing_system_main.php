<?php
// File: modules/cbt_ops/filing_system/index.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/controllers/FilingDriveController.php';

// Validasi Login
if (! isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'index.php');
    exit;
}

$controller = new FilingDriveController($pdo_run, $ftp_config);
$filters = [
    'search' => $_GET['search'] ?? '',
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'limit' => max(10, min(100, (int) ($_GET['limit'] ?? 20))),
    'status' => $_GET['status'] ?? 'active',
    'sort' => $_GET['sort'] ?? 'latest',
    'declared_file_type' => $_GET['declared_file_type'] ?? '',
    'security_level' => $_GET['security_level'] ?? '',
    'access_mode' => $_GET['access_mode'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

$data = $controller->fetchList($filters);
$items = $data['items'];
$pagin = $data['pagination'];
$trashRetentionDays = max(1, (int) env('FILING_TRASH_RETENTION_DAYS', 30));

require_once BASE_PATH.'/includes/layout_header.php';

function formatSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2).' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2).' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2).' KB';
    }

    return $bytes.' bytes';
}

function getSecurityBadge($level)
{
    switch ($level) {
        case 'restricted': return '<span class="bg-orange-50 text-orange-600 text-[10px] font-bold px-2 py-0.5 rounded border border-orange-100 uppercase">Restricted</span>';
        case 'confidential': return '<span class="bg-red-50 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded border border-red-100 uppercase">Confidential</span>';
        default: return '<span class="bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded border border-blue-100 uppercase">Normal</span>';
    }
}
?>

<div class="py-4 md:py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
            <div>
                <h2 class="font-extrabold text-2xl text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="fas fa-folder-open text-blue-600"></i>
                    RUNITC FILING SYSTEM
                </h2>
                <p class="text-gray-500 text-xs mt-0.5">Sistem Pengarsipan Dokumen Terpusat</p>
            </div>
            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <a href="share_access" class="px-3 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2">
                    <i class="fas fa-key text-green-500"></i> Gunakan Share Code
                </a>
                <button onclick="openUploadModal()" class="flex-1 md:flex-none px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-bold shadow-md shadow-blue-100 hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i> UPLOAD FILE
                </button>
            </div>
        </div>

        <!-- ADVANCED SEARCH, FILTERS & STATS -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
            <div class="lg:col-span-3">
                <form method="GET" id="fileSearchForm" class="relative group">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status']) ?>">
                    <?php
                    $hasFilters = $filters['search'] || $filters['declared_file_type'] || $filters['security_level'] || $filters['access_mode'] || ($filters['sort'] !== 'latest');
?>
                    
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" 
                            placeholder="Cari nama file, keyword, atau kode file..." 
                            class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block py-2.5 pr-28 pl-10 shadow-sm transition-all outline-none">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 group-focus-within:text-blue-500 transition-colors">
                            <i class="fas fa-search text-sm"></i>
                        </div>

                        <details class="absolute inset-y-1 right-1 z-30 dropdown-container">
                            <summary class="list-none h-full px-3 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold cursor-pointer transition flex items-center gap-2 [&::-webkit-details-marker]:hidden">
                                <i class="fas fa-sliders-h text-blue-500"></i>
                                Filter<?= $hasFilters ? ' aktif' : '' ?>
                            </summary>
                            <div class="absolute right-0 top-full mt-2 w-[min(92vw,520px)] bg-white border border-gray-100 rounded-xl shadow-xl p-3 z-50">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                        Urutkan
                                        <select name="sort" class="mt-1 w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 shadow-sm">
                                            <option value="latest" <?= $filters['sort'] == 'latest' ? 'selected' : '' ?>>Terbaru</option>
                                            <option value="oldest" <?= $filters['sort'] == 'oldest' ? 'selected' : '' ?>>Terlama</option>
                                            <option value="name_asc" <?= $filters['sort'] == 'name_asc' ? 'selected' : '' ?>>Nama (A-Z)</option>
                                            <option value="name_desc" <?= $filters['sort'] == 'name_desc' ? 'selected' : '' ?>>Nama (Z-A)</option>
                                            <option value="size_largest" <?= $filters['sort'] == 'size_largest' ? 'selected' : '' ?>>Ukuran (Terbesar)</option>
                                            <option value="size_smallest" <?= $filters['sort'] == 'size_smallest' ? 'selected' : '' ?>>Ukuran (Terkecil)</option>
                                        </select>
                                    </label>

                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                        Tipe File
                                        <select name="declared_file_type" class="mt-1 w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 shadow-sm">
                                            <option value="">Semua Tipe</option>
                                            <option value="document" <?= $filters['declared_file_type'] == 'document' ? 'selected' : '' ?>>Document</option>
                                            <option value="spreadsheet" <?= $filters['declared_file_type'] == 'spreadsheet' ? 'selected' : '' ?>>Spreadsheet</option>
                                            <option value="image" <?= $filters['declared_file_type'] == 'image' ? 'selected' : '' ?>>Image</option>
                                            <option value="archive" <?= $filters['declared_file_type'] == 'archive' ? 'selected' : '' ?>>Archive</option>
                                            <option value="mixed" <?= $filters['declared_file_type'] == 'mixed' ? 'selected' : '' ?>>Mixed</option>
                                        </select>
                                    </label>

                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                        Keamanan
                                        <select name="security_level" class="mt-1 w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 shadow-sm">
                                            <option value="">Semua Keamanan</option>
                                            <option value="normal" <?= $filters['security_level'] == 'normal' ? 'selected' : '' ?>>Normal</option>
                                            <option value="restricted" <?= $filters['security_level'] == 'restricted' ? 'selected' : '' ?>>Restricted</option>
                                            <option value="confidential" <?= $filters['security_level'] == 'confidential' ? 'selected' : '' ?>>Confidential</option>
                                        </select>
                                    </label>

                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                        Mode Akses
                                        <select name="access_mode" class="mt-1 w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 shadow-sm">
                                            <option value="">Semua Mode Akses</option>
                                            <option value="private" <?= $filters['access_mode'] == 'private' ? 'selected' : '' ?>>Private</option>
                                            <option value="public_internal" <?= $filters['access_mode'] == 'public_internal' ? 'selected' : '' ?>>Public Internal</option>
                                            <option value="custom" <?= $filters['access_mode'] == 'custom' ? 'selected' : '' ?>>Custom</option>
                                            <option value="share_link" <?= $filters['access_mode'] == 'share_link' ? 'selected' : '' ?>>Share Link</option>
                                        </select>
                                    </label>
                                </div>

                                <?php if ($hasFilters) { ?>
                                    <a href="?status=<?= urlencode($filters['status']) ?>" data-ajax-list-link class="mt-3 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-xs font-bold transition flex items-center justify-center gap-1 shadow-sm">
                                        <i class="fas fa-times"></i> Reset Filter
                                    </a>
                                <?php } ?>
                            </div>
                        </details>
                    </div>
                </form>

                <!-- STATUS TABS -->
                <div class="mt-3 flex gap-1 border-b border-gray-200">
                    <?php $currStatus = $filters['status']; ?>
                    <a href="?status=active" class="px-3 py-1.5 text-xs font-bold <?= $currStatus === 'active' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">Aktif</a>
                    <a href="?status=archived" class="px-3 py-1.5 text-xs font-bold <?= $currStatus === 'archived' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">Diarsipkan</a>
                    <a href="?status=trashed" class="px-3 py-1.5 text-xs font-bold <?= $currStatus === 'trashed' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-500 hover:text-gray-700' ?>">Sampah</a>
                </div>
                <?php if ($currStatus === 'trashed') { ?>
                    <div class="mt-2 px-3 py-2 bg-red-50 border border-red-100 text-red-700 rounded-lg text-xs font-semibold flex items-center gap-2">
                        <i class="fas fa-clock"></i>
                        File di sampah akan dihapus permanen otomatis setelah <?= $trashRetentionDays ?> hari. 
                        
                        <!-- Nilai ini bisa diatur melalui FILING_TRASH_RETENTION_DAYS. -->
                    </div>
                <?php } ?>
            </div>
            <div class="bg-blue-600 rounded-xl p-3 text-white shadow-md shadow-blue-100 flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-[10px] font-bold uppercase tracking-wider">Total File (<?= ucfirst($currStatus) ?>)</p>
                    <h4 id="fileTotalCount" class="text-xl font-black leading-tight"><?= $pagin['total_items'] ?></h4>
                </div>
                <i class="fas fa-hdd text-2xl text-blue-400/50"></i>
            </div>
        </div>

        <!-- FILE TABLE -->
        <div id="fileListContainer" class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto min-h-[240px]">
                <table class="w-full min-w-[920px] text-xs text-left">
                    <thead class="text-[11px] text-gray-400 uppercase bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2.5 w-10 text-center border-r border-gray-100">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer">
                            </th>
                            <th class="px-3 py-2.5 font-bold tracking-wider">Nama Dokumen</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider">Pemilik</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider">Tgl Diupload</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider">Ukuran</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider text-center">Keamanan</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider text-center">Akses</th>
                            <th class="px-3 py-2.5 font-bold tracking-wider text-right w-28 whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 pb-20">
                        <?php if (empty($items)) { ?>
                            <tr>
                                <td colspan="8" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-4">
                                            <i class="fas fa-file-excel text-3xl"></i>
                                        </div>
                                        <h3 class="font-bold text-gray-800">Tidak ada file ditemukan</h3>
                                        <p class="text-gray-400 text-xs mt-1">Coba gunakan kata kunci pencarian lain atau pindah tab status.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($items as $f) { ?>
                                <tr class="hover:bg-blue-50/30 transition-colors group">
                                    <td class="px-3 py-2 text-center border-r border-gray-50 bg-gray-50/20 group-hover:bg-blue-50/50 transition-colors">
                                        <input type="checkbox" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" value="<?= $f['rec_id'] ?>" onchange="updateBulkUI()">
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 <?= $currStatus === 'trashed' ? 'bg-red-50 text-red-500' : 'bg-blue-50 text-blue-600' ?> rounded-md flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                                <i class="fas fa-file-archive text-sm"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="font-bold text-gray-800 truncate leading-tight <?= $currStatus === 'trashed' ? 'line-through text-gray-400' : '' ?>" title="<?= htmlspecialchars($f['display_name']) ?>"><?= htmlspecialchars($f['display_name']) ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 bg-gray-100 rounded-full flex items-center justify-center text-[9px] font-bold text-gray-500 uppercase">
                                                <?= substr($f['owner_name'], 0, 1) ?>
                                            </div>
                                            <span class="text-gray-600 font-medium truncate max-w-[140px]"><?= htmlspecialchars($f['owner_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 font-medium whitespace-nowrap">
                                        <?= date('d M Y, H:i', strtotime($f['created_at'])) ?>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 font-bold whitespace-nowrap">
                                        <?= formatSize($f['zip_size']) ?>
                                    </td>
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        <?= getSecurityBadge($f['security_level']) ?>
                                    </td>
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        <span class="text-[11px] text-gray-500 font-bold uppercase"><?= str_replace('_', ' ', $f['access_mode']) ?></span>
                                    </td>
                                     <td class="px-3 py-2 text-right relative whitespace-nowrap">
                                          <div class="flex justify-end gap-1.5 opacity-100 transition-opacity flex-nowrap">
                                            
                                            <?php if ($currStatus === 'active') { ?>
                                                <?php if ($f['permissions']['can_share']) { ?>
                                                    <button onclick="openShareModal(<?= $f['rec_id'] ?>, <?= $f['security_level'] === 'restricted' ? 'true' : 'false' ?>)" class="w-7 h-7 bg-white border border-gray-200 text-green-600 rounded-md hover:bg-green-600 hover:text-white hover:border-green-600 transition-all shadow-sm" title="Bagikan">
                                                        <i class="fas fa-share-alt"></i>
                                                    </button>
                                                <?php } ?>

                                                <?php if ($f['permissions']['can_download']) { ?>
                                                    <a href="download?id=<?= $f['rec_id'] ?>" class="w-7 h-7 bg-white border border-gray-200 text-blue-600 rounded-md hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm inline-flex items-center justify-center" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php } else { ?>
                                                    <button disabled class="w-7 h-7 bg-gray-50 border border-gray-100 text-gray-300 rounded-md cursor-not-allowed shadow-sm" title="Tidak memiliki izin download">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                <?php } ?>
                                            <?php } ?>

                                            <details class="relative inline-block text-left dropdown-container">
                                                <summary class="list-none cursor-pointer w-7 h-7 bg-white border border-gray-200 text-gray-400 rounded-md hover:bg-gray-50 transition-all shadow-sm flex items-center justify-center [&::-webkit-details-marker]:hidden">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </summary>

                                                <div id="dropdown_<?= $f['rec_id'] ?>" class="absolute right-0 top-full mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 z-50">
                                                    <div class="py-1">
                                                        <a href="javascript:void(0)" onclick="openInfoDrawer(<?= $f['rec_id'] ?>)" class="group flex items-center px-4 py-2 text-sm text-blue-700 hover:bg-blue-50 border-b border-gray-50 pb-2 mb-2">
                                                            <i class="fas fa-info-circle mr-3 text-blue-400 group-hover:text-blue-600"></i> Informasi File
                                                        </a>

                                                        <?php $canSeeFullFileMenu = $f['permissions']['can_manage'] || $f['uploaded_by'] == $_SESSION['user_id'] || $f['permissions']['reason'] === 'admin'; ?>

                                                        <?php if (($currStatus === 'active' || $currStatus === 'archived') && $canSeeFullFileMenu) { ?>
                                                            <a href="javascript:void(0)" onclick="openInspectModal(<?= $f['rec_id'] ?>)" class="group flex items-center px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50 border-b border-gray-50 pb-2 mb-2">
                                                                <i class="fas fa-search-plus mr-3 text-indigo-400 group-hover:text-indigo-600"></i> Lihat Isi ZIP
                                                            </a>
                                                        <?php } ?>

                                                        <?php if ($canSeeFullFileMenu) { ?>
                                                            <a href="audit.php?filing_id=<?= $f['rec_id'] ?>" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 border-b border-gray-50 pb-2 mb-2">
                                                                <i class="fas fa-history mr-3 text-indigo-400 group-hover:text-indigo-600"></i> Lihat Audit
                                                            </a>
                                                        <?php } ?>

                                                        <?php if ($f['permissions']['can_manage']) { ?>
                                                            <?php if ($currStatus === 'active' || $currStatus === 'archived') { ?>
                                                                <a href="javascript:void(0)" onclick="openPermissionModal(<?= $f['rec_id'] ?>)" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700">
                                                                    <i class="fas fa-user-shield mr-3 text-gray-400 group-hover:text-yellow-500"></i> Atur Hak Akses
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="openEditModal(<?= $f['rec_id'] ?>)" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">
                                                                    <i class="fas fa-edit mr-3 text-gray-400 group-hover:text-indigo-500"></i> Ganti Nama / Edit Info
                                                                </a>
                                                            <?php } ?>

                                                            <?php if ($currStatus === 'active') { ?>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'archive')" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                    <i class="fas fa-archive mr-3 text-gray-400 group-hover:text-gray-500"></i> Arsipkan
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'move_trash')" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                                    <i class="fas fa-trash mr-3 text-red-400 group-hover:text-red-500"></i> Pindah ke Sampah
                                                                </a>
                                                            <?php } ?>

                                                            <?php if ($currStatus === 'archived') { ?>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'restore_archive')" class="group flex items-center px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                                    <i class="fas fa-undo mr-3 text-blue-400 group-hover:text-blue-500"></i> Kembalikan (Aktif)
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'move_trash')" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                                    <i class="fas fa-trash mr-3 text-red-400 group-hover:text-red-500"></i> Pindah ke Sampah
                                                                </a>
                                                            <?php } ?>

                                                            <?php if ($currStatus === 'trashed') { ?>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'restore')" class="group flex items-center px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                                                    <i class="fas fa-trash-restore mr-3 text-green-400 group-hover:text-green-500"></i> Restore
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="executeAction(<?= $f['rec_id'] ?>, 'permanent_delete')" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-100 font-bold">
                                                                    <i class="fas fa-times-circle mr-3 text-red-500"></i> Hapus Permanen
                                                                </a>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </details>

                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($pagin['total_pages'] > 1) { ?>
                <div class="px-4 py-3 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500 font-medium">Menampilkan halaman <?= $pagin['current_page'] ?> dari <?= $pagin['total_pages'] ?> (Total: <?= $pagin['total_items'] ?> file)</p>
                    <div class="flex gap-1">
                        <?php
        $q = $_GET;
                unset($q['page']);
                $qs = http_build_query($q);
                $qs = $qs ? '&'.$qs : '';
                ?>
                        <?php if ($pagin['current_page'] > 1) { ?>
                            <a href="?page=<?= $pagin['current_page'] - 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-200 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                        <?php } ?>
                        
                        <?php
                    $start = max(1, $pagin['current_page'] - 2);
                $end = min($pagin['total_pages'], $pagin['current_page'] + 2);
                for ($i = $start; $i <= $end; $i++) {
                    ?>
                            <a href="?page=<?= $i ?><?= $qs ?>" 
                                class="px-3 py-1 rounded text-xs font-bold transition-colors <?= $i == $pagin['current_page'] ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php } ?>

                        <?php if ($pagin['current_page'] < $pagin['total_pages']) { ?>
                            <a href="?page=<?= $pagin['current_page'] + 1 ?><?= $qs ?>" class="px-3 py-1 bg-white border border-gray-200 rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

    </div>
</div>

<!-- BULK ACTION BAR -->
<div id="bulkActionBar" class="fixed bottom-0 inset-x-0 z-40 bg-white border-t shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] p-4 transform translate-y-full transition-transform duration-300 flex justify-center">
    <div class="max-w-7xl w-full px-6 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-lg" id="bulkCount">0</div>
            <span class="font-bold text-gray-700">File Terpilih</span>
        </div>
        <div class="flex gap-3">
            <button onclick="clearSelection()" class="px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 transition">Batal</button>
            
            <?php if ($currStatus === 'active') { ?>
                <button onclick="executeBulk('archive')" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-bold shadow-sm hover:bg-gray-300 transition flex items-center gap-2"><i class="fas fa-archive"></i> Arsipkan</button>
                <button onclick="executeBulk('move_trash')" class="px-5 py-2 bg-red-100 text-red-600 rounded-lg text-sm font-bold shadow-sm hover:bg-red-200 transition flex items-center gap-2"><i class="fas fa-trash"></i> Pindah ke Sampah</button>
            <?php } ?>

            <?php if ($currStatus === 'archived') { ?>
                <button onclick="executeBulk('restore')" class="px-5 py-2 bg-blue-100 text-blue-600 rounded-lg text-sm font-bold shadow-sm hover:bg-blue-200 transition flex items-center gap-2"><i class="fas fa-undo"></i> Kembalikan (Aktif)</button>
                <button onclick="executeBulk('move_trash')" class="px-5 py-2 bg-red-100 text-red-600 rounded-lg text-sm font-bold shadow-sm hover:bg-red-200 transition flex items-center gap-2"><i class="fas fa-trash"></i> Pindah ke Sampah</button>
            <?php } ?>

            <?php if ($currStatus === 'trashed') { ?>
                <button onclick="executeBulk('restore')" class="px-5 py-2 bg-green-100 text-green-600 rounded-lg text-sm font-bold shadow-sm hover:bg-green-200 transition flex items-center gap-2"><i class="fas fa-trash-restore"></i> Restore</button>
                <button onclick="executeBulk('permanent_delete')" class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-bold shadow-sm hover:bg-red-700 transition flex items-center gap-2"><i class="fas fa-times-circle"></i> Hapus Permanen</button>
            <?php } ?>
        </div>
    </div>
</div>

<script>
function toggleSelectAll(el) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = el.checked;
    });
    updateBulkUI();
}

function clearSelection() {
    document.getElementById('selectAllCheckbox').checked = false;
    toggleSelectAll(document.getElementById('selectAllCheckbox'));
}

function updateBulkUI() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const bar = document.getElementById('bulkActionBar');
    const countLabel = document.getElementById('bulkCount');
    
    if (checked.length > 0) {
        countLabel.innerText = checked.length;
        bar.classList.remove('translate-y-full');
    } else {
        bar.classList.add('translate-y-full');
    }
}

function executeBulk(action) {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) return;
    if (checked.length > 50) {
        alert('Maksimal 50 file untuk aksi massal.');
        return;
    }

    let msg = 'Apakah Anda yakin ingin memproses ' + checked.length + ' file ini?';
    if (action === 'permanent_delete') {
        msg = 'PERINGATAN: ' + checked.length + ' file akan dihapus permanen dari storage dan tidak bisa dikembalikan. Lanjutkan?';
    }
    if (!confirm(msg)) return;

    const fd = new FormData();
    fd.append('action', 'bulk');
    fd.append('bulk_action', action);
    checked.forEach(cb => {
        fd.append('filing_ids[]', cb.value);
    });

    fetch('action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            let alertMsg = `${res.message}\nBerhasil diproses: ${res.processed}\nDilewati (Tanpa Akses): ${res.skipped}\nGagal: ${res.failed}`;
            alert(alertMsg);
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(err => alert('Terjadi kesalahan koneksi.'));
}

function refreshFileList() {
    location.reload();
}

const fileSearchForm = document.getElementById('fileSearchForm');
const fileSearchInput = fileSearchForm ? fileSearchForm.querySelector('input[name="search"]') : null;
const fileListContainer = document.getElementById('fileListContainer');
const fileTotalCount = document.getElementById('fileTotalCount');
let fileSearchTimer = null;
let fileListAbortController = null;

function buildFileListUrl(page = 1) {
    const params = new URLSearchParams(new FormData(fileSearchForm));
    params.set('page', page);

    for (const [key, value] of [...params.entries()]) {
        if (value === '') params.delete(key);
    }

    return `${window.location.pathname}?${params.toString()}`;
}

function setFileListLoading(isLoading) {
    if (!fileListContainer) return;
    fileListContainer.classList.toggle('opacity-60', isLoading);
    fileListContainer.classList.toggle('pointer-events-none', isLoading);
}

function bindAjaxListLinks() {
    document.querySelectorAll('#fileListContainer a[href^="?"]').forEach(link => {
        if (link.dataset.ajaxBound === '1') return;
        link.dataset.ajaxBound = '1';
        link.addEventListener('click', function(e) {
            e.preventDefault();
            loadFileList(this.getAttribute('href'));
        });
    });

    document.querySelectorAll('a[data-ajax-list-link]').forEach(link => {
        if (link.dataset.ajaxBound === '1') return;
        link.dataset.ajaxBound = '1';
        link.addEventListener('click', function(e) {
            e.preventDefault();
            loadFileList(this.getAttribute('href'));
        });
    });
}

function syncFileSearchForm(doc) {
    const nextForm = doc.getElementById('fileSearchForm');
    if (!fileSearchForm || !nextForm) return;

    fileSearchForm.querySelectorAll('input[name], select[name]').forEach(field => {
        const nextField = nextForm.querySelector(`[name="${field.name}"]`);
        if (nextField) {
            field.value = nextField.value;
        }
    });
}

function loadFileList(url, pushState = true) {
    if (!fileListContainer || !fileSearchForm) return;

    if (fileListAbortController) {
        fileListAbortController.abort();
    }

    fileListAbortController = new AbortController();
    setFileListLoading(true);

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: fileListAbortController.signal
    })
    .then(response => response.text())
    .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextList = doc.getElementById('fileListContainer');
        const nextTotal = doc.getElementById('fileTotalCount');

        if (!nextList) {
            window.location.href = url;
            return;
        }

        fileListContainer.innerHTML = nextList.innerHTML;
        syncFileSearchForm(doc);
        if (fileTotalCount && nextTotal) {
            fileTotalCount.textContent = nextTotal.textContent;
        }

        clearSelection();
        bindAjaxListLinks();

        if (pushState) {
            window.history.replaceState({}, '', url);
        }
    })
    .catch(error => {
        if (error.name !== 'AbortError') {
            console.error(error);
        }
    })
    .finally(() => {
        setFileListLoading(false);
    });
}

if (fileSearchForm) {
    fileSearchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loadFileList(buildFileListUrl(1));
    });

    fileSearchForm.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => loadFileList(buildFileListUrl(1)));
    });
}

if (fileSearchInput) {
    fileSearchInput.addEventListener('input', function() {
        clearTimeout(fileSearchTimer);
        fileSearchTimer = setTimeout(() => {
            loadFileList(buildFileListUrl(1));
        }, 350);
    });
}

bindAjaxListLinks();

document.addEventListener('click', function(e) {
    if (!e.target.closest('details.dropdown-container')) {
        document.querySelectorAll('details.dropdown-container[open]').forEach(el => el.removeAttribute('open'));
    }
});

function executeAction(filingId, action) {
    if(action === 'permanent_delete') {
        if(!confirm('PERINGATAN: File fisik akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?')) return;
    } else if(action === 'move_trash') {
        if(!confirm('Pindahkan file ini ke Sampah?')) return;
    }

    const fd = new FormData();
    fd.append('filing_id', filingId);
    fd.append('action', action);

    fetch('action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(err => alert('Terjadi kesalahan koneksi.'));
}
</script>

<?php
// Filing System Drive Modal
include __DIR__.'/views/upload_modal.php';
include __DIR__.'/views/share_modal.php';
include __DIR__.'/views/edit_metadata_modal.php';
include __DIR__.'/views/permission_modal.php';
include __DIR__.'/views/info_drawer.php';
include __DIR__.'/views/zip_inspection_modal.php';

require_once BASE_PATH.'/includes/layout_footer.php';
?>
