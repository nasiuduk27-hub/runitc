<?php

ob_start();

require_once dirname(__DIR__, 3).'/config.php';
require_once BASE_PATH.'/includes/layout_header.php';
require_once BASE_PATH.'/controllers/TestAdminController.php';

$controller = new TestAdminController($pdo, $pdo_run, $pdo_war);

$data = $controller->handle();

extract($data);

function buildPageUrl($newPage)
{
    global $controller;

    return $controller->buildPageUrl((int) $newPage);
}

function isHybridMonitoringAdmin($row): bool
{
    return (int) ($row['conn_type'] ?? 1) === 2;
}

function getMonitoringModeByConnType($row): string
{
    return isHybridMonitoringAdmin($row) ? 'hybrid' : 'online';
}

function getMonitoringEndpointByConnType($row): string
{
    // Satu pintu menu: semua masuk lewat monitoring.php.
    // monitoring.php yang akan redirect otomatis ke monitoring_hybrid.php jika conn_type=2.
    return '../test_watching/monitoring.php';
}

function buildMonitoringBatchUrl($row, $batch)
{
    $date = ! empty($row['testdt']) ? date('Y-m-d', strtotime($row['testdt'])) : '';
    $adminNo = trim((string) ($row['admin_no'] ?? ''));

    if ($date === '' || $adminNo === '') {
        return '#';
    }

    return getMonitoringEndpointByConnType($row).'?'.http_build_query([
        'date' => $date,
        'admin' => $adminNo,
        'batch_qty' => (int) ($batch['authorize_amt'] ?? 0),
        'batch_no' => (string) ($batch['batch_no'] ?? ''),
        'monitoring_mode' => getMonitoringModeByConnType($row),
    ]);
}

function hasMonitoringRoom($row)
{
    return ! empty($row['testdt']) && ! empty($row['admin_no']);
}

function getPaginationPages(int $currentPage, int $totalPages): array
{
    $pages = [1, $totalPages];

    for ($i = $currentPage - 1; $i <= $currentPage + 1; $i++) {
        if ($i >= 1 && $i <= $totalPages) {
            $pages[] = $i;
        }
    }

    $pages = array_values(array_unique($pages));
    sort($pages);

    return $pages;
}

?>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Styling Select2 (Mirip Tailwind) */
    .select2-container .select2-selection--single {
        height: 42px !important;
        border-color: #D1D5DB !important;
        border-radius: 0.5rem !important;
        background-color: #F9FAFB !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 42px !important;
        padding-left: 12px !important;
        color: #111827 !important;
        font-size: 0.875rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }

    /* Styling Custom Flatpickr (Warna Indigo & Mode Range) */
    .flatpickr-day.no-task {
        color: #d1d5db !important;
        font-weight: 400;
    }

    .flatpickr-day.has-task {
        color: #4f46e5 !important;
        font-weight: bold !important;
        background-color: #e0e7ff;
        border-radius: 50%;
    }

    .flatpickr-day.has-task:hover {
        background-color: #c7d2fe !important;
    }

    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange,
    .flatpickr-day.selected.inRange,
    .flatpickr-day.startRange.inRange,
    .flatpickr-day.endRange.inRange,
    .flatpickr-day.selected:focus,
    .flatpickr-day.startRange:focus,
    .flatpickr-day.endRange:focus,
    .flatpickr-day.selected:hover,
    .flatpickr-day.startRange:hover,
    .flatpickr-day.endRange:hover,
    .flatpickr-day.selected.prevMonthDay,
    .flatpickr-day.startRange.prevMonthDay,
    .flatpickr-day.endRange.prevMonthDay,
    .flatpickr-day.selected.nextMonthDay,
    .flatpickr-day.startRange.nextMonthDay,
    .flatpickr-day.endRange.nextMonthDay {
        background-color: #4f46e5 !important;
        border-color: #4f46e5 !important;
        color: #ffffff !important;
    }

    .flatpickr-day.inRange {
        background-color: #e0e7ff !important;
        border-color: #e0e7ff !important;
        box-shadow: -5px 0 0 #e0e7ff, 5px 0 0 #e0e7ff !important;
    }
</style>

<div class="py-8 md:py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex flex-col mb-6">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight"><?php echo $isAssignedOnlyView ? 'Penugasan Saya' : 'Distribusi Jadwal Tes' ?></h2>
            <?php if ($isAssignedOnlyView) { ?>
                <p class="text-xs text-gray-500 mt-1">Data yang tampil hanya nomor admin dan batch yang ditugaskan ke akun Anda.</p>
            <?php } ?>
        </div>

        <form method="GET" action="" id="filterForm" class="mb-6 bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wide">Cari Data</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search) ?>" placeholder="Admin No / Klien..." class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide">Tanggal Tes</label>
                        <label class="flex items-center gap-1.5 cursor-pointer" title="Centang untuk memilih rentang">
                            <input type="checkbox" id="toggleRangeMode" class="w-3.5 h-3.5 text-indigo-600 bg-gray-50 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer" <?php echo strpos($filterDate, ' to ') !== false ? 'checked' : '' ?>>
                            <span class="text-[10px] font-bold text-gray-500 uppercase">Date range</span>
                        </label>
                    </div>

                    <input type="hidden" id="filter_date_real" name="date" value="<?php echo htmlspecialchars($filterDate) ?>">

                    <div id="singleDateContainer" class="<?php echo strpos($filterDate, ' to ') !== false ? 'hidden' : 'relative' ?>">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 z-10"><i class="far fa-calendar-alt"></i></div>
                        <input type="text" id="uiSingleDate" placeholder="dd/mm/yyyy" class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 pl-10 cursor-text">
                    </div>

                    <div id="rangeDateContainer" class="<?php echo strpos($filterDate, ' to ') !== false ? 'flex' : 'hidden' ?> gap-2">
                        <div class="relative w-1/2">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-gray-400 z-10"><i class="far fa-calendar-alt text-xs"></i></div>
                            <input type="text" id="uiStartDate" placeholder="dd/mm/yyyy" class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 pl-8 cursor-text">
                        </div>
                        <div class="relative w-1/2">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-gray-400 z-10"><i class="far fa-calendar-alt text-xs"></i></div>
                            <input type="text" id="uiEndDate" placeholder="dd/mm/yyyy" class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 pl-8 cursor-text">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wide">Status Kuota</label>
                    <select name="dist_status" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        <option value="">Semua Status</option>
                        <option value="0" <?php echo $distStatus === '0' ? 'selected' : '' ?>>In Progress</option>
                        <option value="1" <?php echo $distStatus === '1' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <?php if (! $isAssignedOnlyView) { ?>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wide">Pengawas</label>
                        <select name="spv_id" id="filter_spv" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                            <option value="">Semua Pengawas</option>
                            <?php foreach ($supervisors as $s) { ?>
                                <option value="<?php echo $s['rec_id'] ?>" <?php echo (string) $filterSpv === (string) $s['rec_id'] ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($s['spv_name']) ?><?php echo ! empty($s['account_id']) ? ' - '.htmlspecialchars($s['account_id']) : '' ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>

            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center mt-5 pt-4 border-t border-gray-100 gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wide">Tampilkan:</label>
                    <select name="limit" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block py-1.5 px-2">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : '' ?>>10 baris</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : '' ?>>25 baris</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : '' ?>>50 baris</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : '' ?>>100 baris</option>
                    </select>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <a href="?" class="w-full sm:w-auto text-center px-5 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-bold hover:bg-gray-200 transition">RESET</a>
                    <button type="submit" class="w-full sm:w-auto px-8 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow-sm shadow-indigo-200 hover:bg-indigo-700 transition">FILTER DATA</button>
                </div>
            </div>
        </form>

        <?php if ($db_error) { ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-sm font-semibold text-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($db_error) ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['success'])) { ?>
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-sm font-semibold text-sm">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['error'])) { ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-sm font-semibold text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="w-10 px-4 py-4 text-center">#</th>
                            <th class="px-6 py-4">Admin No</th>
                            <th class="px-6 py-4">Klien / Institusi</th>
                            <th class="px-6 py-4">Tanggal Tes</th>
                            <th class="px-6 py-4 w-56">Distribusi Kuota</th>
                            <th class="px-6 py-4 w-44">Peserta Selesai</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <?php if (count($testAdmins) > 0) { ?>
                        <?php foreach ($testAdmins as $row) { ?>
                            <?php
                                $percentage = $row['total_takers'] > 0 ? round(($row['assigned_takers'] / $row['total_takers']) * 100) : 0;
                            $finishedPercentage = $row['total_takers'] > 0 ? round(($row['finished_takers'] / $row['total_takers']) * 100) : 0;
                            $sisaKuota = $row['total_takers'] - $row['assigned_takers'];
                            $isComplete = $row['total_takers'] > 0 && $sisaKuota == 0;
                            $isFinishedComplete = $row['total_takers'] > 0 && (int) $row['finished_takers'] >= (int) $row['total_takers'];
                            $isHybridAdmin = isHybridMonitoringAdmin($row);
                            $monitoringModeLabel = $isHybridAdmin ? 'HYBRID' : 'ONLINE';
                            ?>
                            <tbody x-data="{ expanded: false }" class="border-b border-gray-100 hover:bg-indigo-50/30 transition">
                                <tr @click="expanded = !expanded" class="cursor-pointer">
                                    <td class="px-4 py-4 text-center">
                                        <button type="button" @click.stop="expanded = !expanded" class="text-gray-400 hover:text-indigo-600 focus:outline-none transition-transform duration-200" :class="{'rotate-90 text-indigo-600': expanded}">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <?php echo htmlspecialchars($row['admin_no'] ?? '') ?>
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black border <?php echo $isHybridAdmin ? 'bg-purple-100 text-purple-700 border-purple-300' : 'bg-blue-100 text-blue-700 border-blue-300' ?>">
                                                <?php echo $monitoringModeLabel ?>
                                            </span>
                                            <?php if ($row['total_issues'] > 0) { ?>
                                                <span class="inline-flex px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-bold border border-yellow-300" title="<?php echo $row['total_issues'] ?> Masalah">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo $row['total_issues'] ?>
                                                </span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($row['client_nm'] ?? '') ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-600"><?php echo $row['testdt'] ? date('d M Y', strtotime($row['testdt'])) : '-' ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-between text-[11px] mb-1.5 uppercase tracking-wide">
                                            <span class="font-bold <?php echo $isComplete ? 'text-green-600' : 'text-gray-500' ?>"><?php echo $row['assigned_takers'] ?> / <?php echo $row['total_takers'] ?> Peserta</span>
                                            <span class="font-bold text-indigo-600"><?php echo $percentage ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="<?php echo $isComplete ? 'bg-green-500' : 'bg-indigo-500' ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $percentage ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-between text-[11px] mb-1.5 uppercase tracking-wide">
                                            <span class="font-bold <?php echo $isFinishedComplete ? 'text-green-600' : 'text-gray-500' ?>"><?php echo $row['finished_takers'] ?> / <?php echo $row['total_takers'] ?> Selesai</span>
                                            <span class="font-bold text-emerald-600"><?php echo $finishedPercentage ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden" title="Peserta dengan statrec 7, 8, 9, c, atau C">
                                            <div class="<?php echo $isFinishedComplete ? 'bg-green-500' : 'bg-emerald-500' ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $finishedPercentage ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if (! empty($row['batches'])) { ?>
                                                <span class="hidden xl:inline text-[10px] text-gray-400 font-semibold max-w-[160px] text-right leading-tight">
                                                    Monitoring tersedia di rincian pengawas
                                                </span>
                                            <?php } ?>

                                            <?php if ($canManageDistribution) { ?>
                                            <form method="POST" @click.stop onsubmit="return confirm('Yakin ingin mereset dan menghapus seluruh distribusi admin ini?');" class="inline">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
                                                <input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?php echo $row['rec_id'] ?>">
                                                <button type="submit" class="p-2 bg-red-50 text-red-500 rounded hover:bg-red-100 transition"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                            <?php } ?>
                                            <?php if ($canManageDistribution && $row['total_takers'] > 0 && ! $isComplete) { ?>
                                                <button type="button" @click.stop="openBatchModal(<?php echo $row['rec_id'] ?>, <?php echo $sisaKuota ?>)" class="px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded shadow-sm hover:bg-indigo-700 transition"><i class="fas fa-share-alt mr-1"></i> Bagikan Kuota</button>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="expanded" x-transition.opacity class="bg-indigo-50/40" style="display: none;">
                                    <td colspan="7" class="px-6 py-5 border-t border-indigo-100">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                            <h4 class="text-xs font-bold text-indigo-800 uppercase tracking-wider"><i class="fas fa-users-cog mr-1"></i> Rincian Penugasan Pengawas</h4>
                                            <span class="text-[10px] text-gray-500 font-medium"><i class="fas fa-info-circle mr-1 text-indigo-500"></i>Buka Monitoring dari kartu pengawas yang ditugaskan.</span>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <?php if (empty($row['batches'])) { ?>
                                                <div class="text-xs text-gray-500 italic">Belum ada pengawas yang ditugaskan.</div>
                                            <?php } else { ?>
                                                <?php foreach ($row['batches'] as $batch) { ?>
                                                    <?php $canOpenMonitoring = $canManageDistribution || (int) ($batch['itc_usr_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0); ?>
                                                    <div class="bg-white p-3.5 rounded-xl border border-indigo-100 shadow-sm flex items-center justify-between relative hover:border-indigo-300 transition">
                                                        <?php if ($batch['total_issues'] > 0) { ?>
                                                            <div class="absolute -top-2 -right-2 w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-md border-2 border-white" title="<?php echo $batch['total_issues'] ?> Masalah"><?php echo $batch['total_issues'] ?></div>
                                                        <?php } ?>
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-black border border-indigo-200"><?php echo htmlspecialchars($batch['batch_no'] ?? '') ?></div>
                                                            <div>
                                                                <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($batch['spv_name'] ?? '') ?></p>
                                                                <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-600"><?php echo $batch['captain'] == 1 ? '<i class="fas fa-star text-yellow-500 mr-0.5"></i> CAPTAIN' : 'SUPERVISOR' ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-col items-end border-l border-gray-100 pl-3 ml-2">
                                                            <span class="block text-2xl font-black text-gray-700 leading-none"><?php echo $batch['authorize_amt'] ?></span>
                                                            <?php if ($canOpenMonitoring) { ?>
                                                                <?php $monitoringBatchUrl = buildMonitoringBatchUrl($row, $batch); ?>
                                                                <a href="<?php echo htmlspecialchars($monitoringBatchUrl) ?>"
                                                                   target="_blank"
                                                                   class="text-[10px] uppercase font-bold text-green-600 hover:text-green-800 mt-1">
                                                                    <i class="fas fa-desktop mr-0.5"></i> Monitoring
                                                                </a>
                                                            <?php } else { ?>
                                                                <button type="button"
                                                                        onclick="alert('Salah masuk ruangan. Monitoring ini ditugaskan untuk <?php echo htmlspecialchars($batch['spv_name'] ?? 'SPV lain', ENT_QUOTES) ?>.')"
                                                                        class="text-[10px] uppercase font-bold text-gray-400 mt-1 cursor-not-allowed">
                                                                    <i class="fas fa-lock mr-0.5"></i> Monitoring
                                                                </button>
                                                            <?php } ?>
                                                            <?php if ($canManageDistribution) { ?>
                                                                <button onclick="openEditBatchModal(<?php echo $batch['rec_id'] ?>, <?php echo $batch['spv_recid'] ?>, <?php echo $batch['authorize_amt'] ?>, <?php echo $batch['authorize_amt'] + $sisaKuota ?>)" class="text-[10px] uppercase font-bold text-blue-600 hover:text-blue-800 mt-1"><i class="fas fa-edit mr-0.5"></i> Edit / Ganti</button>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        <?php } ?>
                    <?php } else { ?>
                        <tbody>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-gray-400 text-4xl mb-3"><i class="fas fa-box-open"></i></div>
                                    <p class="text-gray-500 text-sm font-medium">Data tidak ditemukan.</p>
                                    <p class="text-xs text-gray-400 mt-1">Coba sesuaikan filter pencarian di atas.</p>
                                </td>
                            </tr>
                        </tbody>
                    <?php } ?>
                </table>
            </div>

            <?php if ($total_pages > 1) { ?>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <span class="text-xs text-gray-600 font-medium">Menampilkan halaman <b class="text-gray-900"><?php echo $page ?></b> dari <b class="text-gray-900"><?php echo $total_pages ?></b> (Total <?php echo $total_rows ?> Data)</span>
                    <nav class="flex flex-wrap gap-1">
                        <?php if ($page > 1) { ?>
                            <a href="<?php echo buildPageUrl($page - 1) ?>" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-600 rounded text-xs font-bold hover:bg-gray-50 transition">Prev</a>
                        <?php } ?>

                        <?php $paginationPages = getPaginationPages((int) $page, (int) $total_pages); ?>
                        <?php $previousPaginationPage = null; ?>
                        <?php foreach ($paginationPages as $paginationPage) { ?>
                            <?php if ($previousPaginationPage !== null && $paginationPage > $previousPaginationPage + 1) { ?>
                                <span class="px-2 py-1.5 text-xs font-bold text-gray-400">...</span>
                            <?php } ?>
                            <a href="<?php echo buildPageUrl($paginationPage) ?>" class="px-3 py-1.5 border <?php echo $paginationPage == $page ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' ?> rounded text-xs font-bold transition"><?php echo $paginationPage ?></a>
                            <?php $previousPaginationPage = $paginationPage; ?>
                        <?php } ?>

                        <?php if ($page < $total_pages) { ?>
                            <a href="<?php echo buildPageUrl($page + 1) ?>" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-600 rounded text-xs font-bold hover:bg-gray-50 transition">Next</a>
                        <?php } ?>
                    </nav>
                </div>
            <?php } else { ?>
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    <span class="text-xs text-gray-500">Menampilkan seluruh data (Total <?php echo $total_rows ?>).</span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div id="batchModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border-t-4 border-indigo-600">
        <form method="POST" class="p-6 space-y-5">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
            <input type="hidden" name="action" value="assign_batch"><input type="hidden" name="admin_id" id="modal_admin_id">
            <h3 class="font-bold text-lg text-gray-800 border-b border-gray-100 pb-2"><i class="fas fa-share-alt text-indigo-600 mr-2"></i>Bagikan Kuota Peserta</h3>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Pilih Pengawas</label>
                <select name="spv_recid" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                    <option value="">-- Pilih SPV --</option>
                    <?php foreach ($supervisors as $spv) { ?>
                        <option value="<?php echo $spv['rec_id'] ?>">
                            <?php echo htmlspecialchars($spv['spv_name']) ?>
                            <?php echo ! empty($spv['spv_alias']) ? '('.htmlspecialchars($spv['spv_alias']).')' : '' ?>
                            <?php echo ! empty($spv['account_id']) ? ' - '.htmlspecialchars($spv['account_id']) : '' ?>
                            <?php echo ! empty($spv['tad_roles']) ? '['.htmlspecialchars($spv['tad_roles']).']' : '' ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Jumlah Peserta</label>
                <input type="number" name="amount" id="amount_input" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeBatchModal()" class="px-5 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-bold hover:bg-gray-200 transition">Batal</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-sm transition">Proses</button>
            </div>
        </form>
    </div>
</div>

<div id="editBatchModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border-t-4 border-blue-500">
        <form method="POST" class="p-6 space-y-5">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
            <input type="hidden" name="action" value="update_batch"><input type="hidden" name="batch_id" id="edit_modal_batch_id">
            <h3 class="font-bold text-lg text-gray-800 border-b border-gray-100 pb-2"><i class="fas fa-edit text-blue-500 mr-2"></i>Edit Pengawas & Kuota</h3>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Pilih Pengawas Baru</label>
                <select id="edit_spv_select" name="spv_recid" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                    <option value="">-- Pilih SPV --</option>
                    <?php foreach ($supervisors as $spv) { ?>
                        <option value="<?php echo $spv['rec_id'] ?>">
                            <?php echo htmlspecialchars($spv['spv_name']) ?><?php echo ! empty($spv['account_id']) ? ' - '.htmlspecialchars($spv['account_id']) : '' ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Jumlah Kuota</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="amount" id="edit_amount_input" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 text-sm font-bold" required>
                    <span class="text-xs text-gray-500 whitespace-nowrap bg-gray-100 px-3 py-2.5 rounded-lg border border-gray-200">Max: <span id="edit_max_label" class="font-bold text-gray-800"></span></span>
                </div>
                <p class="text-[10px] text-red-500 mt-1.5 italic font-medium">*Tips: Set angka menjadi 0 untuk menghapus tugas pengawas ini (peserta akan kembali ke daftar tunggu).</p>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeEditBatchModal()" class="px-5 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-bold hover:bg-gray-200 transition">Batal</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-sm transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fungsi Buka/Tutup Modal
    function openBatchModal(id, max) {
        document.getElementById('modal_admin_id').value = id;
        const inp = document.getElementById('amount_input');
        inp.max = max;
        inp.value = max;
        document.getElementById('batchModal').classList.remove('hidden');
    }

    function closeBatchModal() {
        document.getElementById('batchModal').classList.add('hidden');
    }

    function openEditBatchModal(id, spv, currentAmt, maxAllowed) {
        document.getElementById('edit_modal_batch_id').value = id;
        document.getElementById('edit_spv_select').value = spv;

        const amtInput = document.getElementById('edit_amount_input');
        amtInput.value = currentAmt;
        amtInput.max = maxAllowed;
        amtInput.min = 0;

        document.getElementById('edit_max_label').innerText = maxAllowed;
        document.getElementById('editBatchModal').classList.remove('hidden');
    }

    function closeEditBatchModal() {
        document.getElementById('editBatchModal').classList.add('hidden');
    }

    $(document).ready(function() {
        // 1. Inisialisasi Select2
        $('#filter_spv').select2({
            placeholder: "Ketik nama pengawas...",
            allowClear: true
        });

        // 2. Setup Logika Flatpickr
        const activeDates = <?php echo $active_dates_json ?>;
        const currentFilter = "<?php echo htmlspecialchars($filterDate) ?>";
        let defaultSingle = "",
            defaultStart = "",
            defaultEnd = "";

        if (currentFilter.includes(' to ')) {
            const parts = currentFilter.split(' to ');
            defaultStart = parts[0];
            defaultEnd = parts[1];
        } else {
            defaultSingle = currentFilter;
        }

        const commonConfig = {
            altInput: true,
            altFormat: "d/m/Y",
            dateFormat: "Y-m-d",
            allowInput: true,
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                const dateStr = new Date(dayElem.dateObj.getTime() - (dayElem.dateObj.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
                if (activeDates.includes(dateStr)) dayElem.classList.add('has-task');
                else dayElem.classList.add('no-task');
            }
        };

        let fpSingle = flatpickr("#uiSingleDate", {
            ...commonConfig,
            defaultDate: defaultSingle,
            onChange: function(dates, str) {
                $('#filter_date_real').val(str);
            }
        });

        let fpStart = flatpickr("#uiStartDate", {
            ...commonConfig,
            defaultDate: defaultStart,
            onChange: function(dates, str) {
                fpEnd.set('minDate', str);
                updateHiddenRangeInput();
            }
        });

        let fpEnd = flatpickr("#uiEndDate", {
            ...commonConfig,
            defaultDate: defaultEnd,
            onChange: function(dates, str) {
                fpStart.set('maxDate', str);
                updateHiddenRangeInput();
            }
        });

        function updateHiddenRangeInput() {
            const startStr = fpStart.input.value;
            const endStr = fpEnd.input.value;
            if (startStr && endStr) {
                $('#filter_date_real').val(startStr + ' to ' + endStr);
            } else if (startStr) {
                $('#filter_date_real').val(startStr);
            } else {
                $('#filter_date_real').val('');
            }
        }

        // 3. Logika Centang / Toggle Rentang Waktu
        const toggleRangeBtn = document.getElementById('toggleRangeMode');
        const singleContainer = document.getElementById('singleDateContainer');
        const rangeContainer = document.getElementById('rangeDateContainer');

        toggleRangeBtn.addEventListener('change', function() {
            if (this.checked) {
                singleContainer.classList.add('hidden');
                singleContainer.classList.remove('relative');
                rangeContainer.classList.remove('hidden');
                rangeContainer.classList.add('flex');
                fpSingle.clear();
                updateHiddenRangeInput();
            } else {
                rangeContainer.classList.add('hidden');
                rangeContainer.classList.remove('flex');
                singleContainer.classList.remove('hidden');
                singleContainer.classList.add('relative');
                fpStart.clear();
                fpEnd.clear();
                fpStart.set('maxDate', null);
                fpEnd.set('minDate', null);
                $('#filter_date_real').val(fpSingle.input.value);
            }
        });
    });
</script>

<?php require_once dirname(__DIR__, 3).'/includes/layout_footer.php'; ?>
