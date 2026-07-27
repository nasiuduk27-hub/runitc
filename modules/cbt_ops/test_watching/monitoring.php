<?php

    $date  = $_GET['date'] ?? '';
    $admin = $_GET['admin'] ?? '';

    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }

    $basePath = dirname(__DIR__, 3);

    $configPath  = $basePath . '/config.php';
    $nisnlibPath = $basePath . '/nisnlib.php';

    if (file_exists($configPath)) {
    require_once $configPath;
    } else {
    die('config.php tidak ditemukan di: ' . $configPath);
    }

    if (file_exists($nisnlibPath)) {
    require_once $nisnlibPath;
    } else {
    die('nisnlib.php tidak ditemukan di: ' . $nisnlibPath);
    }

    require_once BASE_PATH . '/classes/StatusHelper.php';
    require_once BASE_PATH . '/classes/ParticipantTableRenderer.php';
    require_once BASE_PATH . '/models/Monitoring.php';
    require_once BASE_PATH . '/classes/FtpStorage.php';
    require_once BASE_PATH . '/models/FilingSystem.php';
    require_once BASE_PATH . '/controllers/MonitoringController.php';

    if (! function_exists('shifting')) {
    die('Function shifting() belum ter-load. Cek isi file nisnlib.php');
    }

    if (! function_exists('deccrypt')) {
    die('Function deccrypt() belum ter-load. Cek isi file nisnlib.php');
    }

    date_default_timezone_set('Asia/Jakarta');

    $controller = new MonitoringController($pdo, $pdo_run, $pdo_war, $ftp_config);
    $pageData   = $controller->handle();

    extract($pageData);

    if (! function_exists('monitoring_mode_from_admin_data')) {
    function monitoring_mode_from_admin_data(array $adminData): string
    {
        if (array_key_exists('conn_type', $adminData)) {
            return (int) $adminData['conn_type'] === 2 ? 'hybrid' : 'online';
        }

        $mode = strtolower(trim((string) ($adminData['monitoring_mode'] ?? '')));
        return $mode === 'hybrid' ? 'hybrid' : 'online';
    }
    }

    $currentMonitoringMode = strtolower(trim((string) ($monitoring_mode ?? ($test_info['monitoring_mode'] ?? 'online'))));
    $currentMonitoringMode = $currentMonitoringMode === 'hybrid' ? 'hybrid' : 'online';
    $is_hybrid_mode        = $currentMonitoringMode === 'hybrid';

    /*
|--------------------------------------------------------------------------
| AJAX TIMER SYNC DARI DATABASE
|--------------------------------------------------------------------------
| Dipakai untuk mencegah timer reset saat F5 / polling.
| Frontend mengambil remindtm langsung dari t3sTt4keR5, lalu menimpa
| data-remaining-seconds yang ada di HTML renderer.
*/
    if (! function_exists('monitoring_decode_admin_key_to_pair')) {
    function monitoring_decode_admin_key_to_pair(string $key): ?array
    {
        $key = trim($key);
        if ($key === '' || strpos($key, '|') === false) {
            return null;
        }

        [$left, $subId] = explode('|', $key, 2);

        // Key bisa berbentuk: 2026-05-25_209|20
        if (strpos($left, '_') !== false) {
            $parts = explode('_', $left);
            $left  = end($parts);
        }

        $adminId = preg_replace('/[^0-9]/', '', (string) $left);
        $subId   = preg_replace('/[^0-9]/', '', (string) $subId);

        if ($adminId === '' || $subId === '') {
            return null;
        }

        return [$adminId, $subId];
    }
    }

    if (! function_exists('monitoring_remindtm_to_seconds')) {
    function monitoring_remindtm_to_seconds($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Format umum: HH:MM:SS atau MM:SS
        if (preg_match('/^(\d{1,3}):(\d{1,2})(?::(\d{1,2}))?$/', $raw, $m)) {
            if (isset($m[3]) && $m[3] !== '') {
                return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
            }

            return ((int) $m[1] * 60) + (int) $m[2];
        }

        // Jika database menyimpan angka, anggap sebagai detik.
        if (is_numeric($raw)) {
            return max(0, (int) floor((float) $raw));
        }

        return null;
    }
    }

    if (! function_exists('monitoring_status_text_from_row')) {
    function monitoring_status_text_from_row($statrec, int $keSuspend = 0): string
    {
        // Tetap memakai mapping status lama, tetapi kalau peserta sedang suspend,
        // kolom Status ditampilkan sebagai Suspend supaya operator langsung melihat kondisinya.
        $stat = (string) trim((string) $statrec);

        // Terminate = End of Test yang dihentikan operator/bermasalah.
        // End of Test normal tetap statrec 7, tetapi ke_suspend bukan 1.
        if ($stat === '7' && $keSuspend === 1) {
            return 'Terminate';
        }

        if ($keSuspend === 1 && in_array($stat, ['3', '4', '5', '6'], true)) {
            return 'Suspend';
        }

        switch ($stat) {
            case '0':
                return 'Raw Authorize';
            case '1':
                return 'Authorize';
            case '2':
                return 'Readiness';
            case '3':
                return 'Ready to enter CBT';
            case '4':
                return 'Questioner';
            case '5':
                return 'Regulation & Confidentiality Agrement';
            case '6':
                return 'Ready to Test';
            case '7':
                return $keSuspend === 1 ? 'Terminate' : 'End of Test';
            case '8':
                return 'Collected';
            case '9':
                return 'Submit';
            default:
                if (strtolower($stat) === 'c') {
                    return 'Completed';
                }

                if (strtolower($stat) === 'a') {
                    return 'Active';
                }

                return $stat === '' ? 'Unknown' : $stat . ' - Unknown';
        }
    }
    }

    if (! empty($_GET['ajax_timer_sync'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (! $pdo_war) {
        echo json_encode(['success' => false, 'message' => 'Koneksi database WAR tidak tersedia']);
        exit;
    }

    $adminKeys = [];

    if (! empty($_GET['admin'])) {
        $adminKeys[] = (string) $_GET['admin'];
    }

    // Fallback untuk URL lama/non-room key.
    if (empty($adminKeys) && ! empty($_GET['admin_rec_id']) && ! empty($_GET['sub_admin_id'])) {
        $adminKeys[] = preg_replace('/[^0-9]/', '', (string) $_GET['admin_rec_id']) . '|' . preg_replace('/[^0-9]/', '', (string) $_GET['sub_admin_id']);
    }

    $accessiblePairs = [];

    foreach (($filtered_admins ?? []) as $accessibleKey => $accessibleAdmin) {
        $accessiblePair = monitoring_decode_admin_key_to_pair((string) $accessibleKey);

        if ($accessiblePair !== null) {
            $accessiblePairs[$accessiblePair[0] . '|' . $accessiblePair[1]] = true;
        }
    }

    $pairs = [];
    foreach (array_unique($adminKeys) as $adminKey) {
        $pair = monitoring_decode_admin_key_to_pair($adminKey);

        if ($pair !== null && isset($accessiblePairs[$pair[0] . '|' . $pair[1]])) {
            $pairs[] = $pair;
        }
    }

    if (empty($pairs)) {
        echo json_encode(['success' => false, 'message' => 'Admin ID tidak valid atau tidak ditugaskan ke akun ini']);
        exit;
    }

    try {
        $items = [];

        foreach ($pairs as [$adminId, $subId]) {
            $stmt = $pdo_war->prepare("\n                SELECT authorize, statrec, ke_suspend, remindtm\n                FROM t3sTt4keR5\n                WHERE admin_id = ?\n                  AND sub_adm_id = ?\n            ");
            $stmt->execute([$adminId, $subId]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $remainingSeconds = monitoring_remindtm_to_seconds($row['remindtm'] ?? null);
                $statrec          = (string) ($row['statrec'] ?? '');
                $keSuspend        = (int) ($row['ke_suspend'] ?? 0);
                // Sisa waktu hanya boleh menjadi "Selesai" jika status DB benar-benar End of Test.
                // Jangan pakai remindtm=0 sebagai patokan selesai, karena beberapa row belum mulai bisa bernilai 0.
                $isFinished = ($statrec === '7');
                $isActive   = ! $isFinished && $keSuspend !== 1 && in_array($statrec, ['3', '4', '5', '6'], true) && $remainingSeconds !== null && $remainingSeconds > 0;

                $items[(string) $row['authorize']] = [
                    'authorize'         => (string) $row['authorize'],
                    'statrec'           => $statrec,
                    'ke_suspend'        => $keSuspend,
                    'remindtm'          => $row['remindtm'],
                    'remaining_seconds' => $remainingSeconds,
                    'active'            => $isActive,
                    'finished'          => $isFinished,
                    'status_text'       => monitoring_status_text_from_row($statrec, $keSuspend),
                ];
            }
        }

        echo json_encode(['success' => true, 'items' => array_values($items)]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    }

    /*
|--------------------------------------------------------------------------
| AUTO RESOLVE DARI MENU TEST DISTRIBUSI
|--------------------------------------------------------------------------
 | Link batch dari test_admin mengirim admin_no + batch_no, bukan key internal
 | room. Di sini admin_no dicocokkan ke $filtered_admins milik SPV login,
 | sehingga direct URL ke room SPV lain tidak akan masuk.
 |
 | Link batch: ?date=2026-05-25&admin=B00932&batch_no=A
 | Redirect:   ?date=2026-05-25&admin=2026-05-25_209%7C20
*/
    if (
    empty($is_in_room)
    && ! empty($_GET['date'])
    && ! empty($_GET['admin'])
    && ! empty($filtered_admins)
    && is_array($filtered_admins)
    ) {
    $requestedDate     = date('Y-m-d', strtotime($_GET['date']));
    $requestedAdmin    = trim((string) $_GET['admin']);
    $requestedBatchQty = isset($_GET['batch_qty']) ? (int) $_GET['batch_qty'] : null;
    $requestedBatchNo  = trim((string) ($_GET['batch_no'] ?? ''));

    $candidateKey = null;
    $fallbackKey  = null;

    foreach ($filtered_admins as $key => $data) {
        $itemDate    = ! empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : '';
        $itemAdminNo = trim((string) ($data['admin_no'] ?? ''));
        $itemQty     = isset($data['qty']) ? (int) $data['qty'] : null;
        $itemBatchNo = trim((string) ($data['batch_no'] ?? ''));

        // Jika admin pada URL sudah berupa key internal dan ada di list, pakai langsung.
        if ((string) $key === $requestedAdmin) {
            $candidateKey = (string) $key;
            break;
        }

        if ($itemDate === $requestedDate && $itemAdminNo === $requestedAdmin) {
            if ($fallbackKey === null) {
                $fallbackKey = (string) $key;
            }

            // Untuk tombol batch, prioritaskan batch_no. Jumlah peserta hanya fallback URL lama.
            if ($requestedBatchNo !== '' && $itemBatchNo === $requestedBatchNo) {
                $candidateKey = (string) $key;
                break;
            }

            if ($requestedBatchNo === '' && $requestedBatchQty !== null && $itemQty === $requestedBatchQty) {
                $candidateKey = (string) $key;
                break;
            }
        }
    }

    if ($candidateKey === null && $requestedBatchNo === '') {
        $candidateKey = $fallbackKey;
    }

    if ($candidateKey === null && $requestedBatchNo !== '') {
        $fatal_error = 'Salah masuk ruangan. Batch ini tidak ditugaskan ke akun Anda.';
    }

    if ($candidateKey !== null) {
        $redirectParams = [
            'date'            => $requestedDate,
            'admin'           => $candidateKey,
            'monitoring_mode' => $currentMonitoringMode,
        ];

        $redirectEndpoint = $currentMonitoringMode === 'hybrid' ? 'monitoring_hybrid.php' : 'monitoring.php';
        header('Location: ' . $redirectEndpoint . '?' . http_build_query($redirectParams));
        exit;
    }
    }

    $current_admin_no_for_decrypt = $current_admin_no;

    $spv_photo_url = BASE_URL . '/assets/personal/nopicture.png';
    if (! empty($spv['id'])) {
    $photo_extensions = ['png', 'jpg', 'jpeg', 'gif'];
    foreach ($photo_extensions as $ext) {
        if (file_exists(BASE_PATH . '/assets/personal/user_' . $spv['id'] . '.' . $ext)) {
            $spv_photo_url = BASE_URL . '/assets/personal/user_' . $spv['id'] . '.' . $ext . '?v=' . time();
            break;
        }
    }
    }

    $isCleanRoom = $is_in_room && ! $fatal_error;

    if (! $isCleanRoom) {
    include_once BASE_PATH . '/includes/layout_header.php';
    } else {

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Monitoring Ruangan - RUN ITC</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js"></script>
        <script src="https://cdn.tailwindcss.com"></script>

        <script>
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

        <style>
            ::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }

            ::-webkit-scrollbar-track {
                background: transparent;
            }

            ::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }

            body {
                background: #f3f4f6;
            }
        </style>
    </head>

    <body class="bg-brand-bg text-gray-600 font-sans min-h-screen overflow-hidden">
        <main id="main-content-area" class="h-screen overflow-x-hidden overflow-y-auto p-4 sm:p-6 md:p-8 relative bg-brand-bg">
    <?php
        }
    ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    #participantsTable {
        font-size: 14px;
    }

    <?php if (! empty($is_hybrid_mode)): ?>
    #participantsTable thead th:last-child,
    #participantsTable tbody td:last-child {
        display: none !important;
    }
    <?php endif; ?>

    #participantsTable td {
        padding-top: 12px;
        padding-bottom: 12px;
    }

    .timer-countdown {
        font-size: 14px !important;
        padding: 4px 8px !important;
    }

    .timer-wrapper {
        gap: 6px;
    }

    .timer-session-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        line-height: 1;
    }

    .timer-session-label.listening {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .timer-session-label.reading {
        background: #fef3c7;
        color: #b45309;
    }

    .participant-row:hover {
        background-color: #eff6ff;
    }

    .flatpickr-day.no-task {
        color: #d1d5db !important;
        font-weight: 400;
    }

    .flatpickr-day.has-task {
        color: #1D4ED8 !important;
        font-weight: bold !important;
        background-color: #eff6ff;
        border-radius: 50%;
    }

    .flatpickr-day.has-task:hover {
        background-color: #dbeafe !important;
    }

    .flatpickr-day.selected {
        background-color: #1D4ED8 !important;
        color: #ffffff !important;
    }

    .flatpickr-custom-input {
        padding-left: 2.5rem !important;
    }

    /* Menampilkan kembali sidebar agar navigasi terkoneksi */
    main#main-content-area {
        padding: 1.5rem !important;
    }
</style>

<div class="w-full relative flex flex-col h-full">

    <?php if ($fatal_error): ?>
        <div class="bg-white border-2 border-dashed border-red-200 rounded-xl p-12 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-2xl mb-4"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 class="text-lg font-bold text-gray-800">Sistem Mendeteksi Masalah</h3>
            <p class="text-red-600 mt-2 text-sm max-w-2xl"><?php echo $fatal_error ?></p>
            <a href="monitoring" class="mt-5 px-6 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-bold text-sm transition">Refresh Halaman</a>
        </div>
    <?php elseif (! $has_spv_access): ?>
        <div class="bg-white border-2 border-dashed border-red-200 rounded-xl p-12 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-2xl mb-4"><i class="fas fa-user-shield"></i></div>
            <h3 class="text-lg font-bold text-gray-800">Akses Pengawas Ditolak</h3>
            <p class="text-gray-500 mt-2 text-sm max-w-md">Akun Anda (ITC ID: <?php echo htmlspecialchars($itc_id) ?>) tidak memiliki akses sebagai Supervisor pada sistem ini.</p>
        </div>
    <?php else: ?>

        <?php if (! $is_in_room): ?>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

        <!-- KIRI: TITLE -->
        <div>
            <div class="inline-flex items-center gap-2 bg-blue-50 text-brand-primary px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide mb-3">
                <i class="fas fa-shield-alt"></i>
                Monitoring Room
            </div>

            <h1 class="text-2xl font-bold text-gray-900">
                Proctoring Dashboard
            </h1>

            <p class="text-sm text-gray-500 mt-1 max-w-xl">
                Welcome back, <?php echo htmlspecialchars($spv['name']) ?>. Pilih jadwal admin untuk memulai sesi pengawasan.
            </p>
        </div>

        <!-- KANAN: SUPERVISOR -->
        <div class="flex items-center gap-4 bg-gray-50 border border-gray-100 rounded-2xl px-5 py-4 min-w-full lg:min-w-[360px]">
            <img src="<?php echo $spv_photo_url ?>"
                 alt="Foto SPV"
                 class="w-14 h-14 rounded-full border border-gray-200 shadow-sm object-cover bg-white">

            <div class="min-w-0">
                <p class="text-[11px] text-gray-400 uppercase tracking-wider font-bold">
                    Supervisor Bertugas
                </p>

                <h2 class="text-base font-bold text-gray-900 truncate">
                    <?php echo htmlspecialchars($user_name) ?>
                </h2>

                <p class="text-xs text-gray-500 font-mono mt-1">
                    <i class="fas fa-id-badge mr-1 text-brand-primary"></i>
                    ID: <?php echo htmlspecialchars($user_id) ?>
                </p>
            </div>
        </div>

    </div>
</div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center justify-center py-10 gap-4 text-center">
                 <div class="w-16 h-16 bg-blue-50 text-brand-primary rounded-full flex items-center justify-center text-2xl mb-2"><i class="fas fa-chalkboard-teacher"></i></div>
                 <div>
                    <h2 class="text-lg font-bold text-gray-800">Mulai Sesi Pengawasan</h2>
                    <p class="text-sm text-gray-500 mt-1 max-w-md">
                        Pilih jadwal admin yang ditugaskan kepada Anda. Tanggal akan otomatis mengikuti jadwal yang dipilih.
                    </p>
                    </div>

                    <form action="" method="GET" id="adminSelectionForm" class="flex flex-col sm:flex-row items-center justify-center gap-3 w-full max-w-2xl mt-4">

    <input type="hidden" name="date" id="realDateInput" value="<?php echo htmlspecialchars($selected_date) ?>">

    <div class="relative w-full sm:w-[420px]" id="customSelectWrapper">
        <input type="hidden" name="admin" id="selectedAdminCode" value="">

        <?php $has_jadwal = count($filtered_admins) > 0; ?>

        <button type="button"
            id="dropdownBtn"
            class="<?php echo $has_jadwal ? 'bg-white hover:bg-gray-50 cursor-pointer border-gray-300 text-gray-800' : 'bg-gray-100 cursor-not-allowed border-gray-200 text-gray-400' ?> border text-sm rounded-xl focus:ring-brand-primary focus:border-brand-primary flex justify-between items-center w-full px-4 py-3 transition"
            <?php echo $has_jadwal ? '' : 'disabled' ?>>

            <span id="dropdownBtnText" class="font-medium truncate <?php echo $has_jadwal ? '' : 'text-gray-400' ?>">
                <?php echo $has_jadwal ? 'Pilih jadwal admin...' : 'Tidak ada jadwal' ?>
            </span>

            <i class="fas fa-chevron-down <?php echo $has_jadwal ? 'text-gray-500' : 'text-gray-300' ?> text-xs transition-transform duration-200" id="dropdownIcon"></i>
        </button>

        <div id="dropdownMenu" class="hidden absolute z-50 w-full md:w-[650px] md:-left-28 mt-2 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden text-left">
            <?php if ($has_jadwal): ?>
                <div class="p-3 border-b border-gray-100 bg-gray-50 sticky top-0 z-10">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        <input type="text"
                               id="dropdownSearch"
                               class="bg-white border border-gray-300 text-gray-800 text-xs rounded-lg focus:ring-brand-primary focus:border-brand-primary block w-full pl-8 p-2"
                               placeholder="Cari Admin / Klien..."
                               autocomplete="off">
                    </div>
                </div>

                <div class="grid grid-cols-[1fr_1fr_1fr_2fr_1fr] gap-2 bg-gray-100 text-xs px-4 py-2 font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                    <div>Tanggal</div>
                    <div>Admin</div>
                    <div>Tipe</div>
                    <div>Klien</div>
                    <div class="text-right">Peserta</div>
                </div>

                <div class="max-h-64 overflow-y-auto" id="dropdownListContainer">
                    <?php foreach ($filtered_admins as $key => $data): ?>
                        <?php $itemMode = monitoring_mode_from_admin_data($data); ?>
                        <div class="dropdown-item grid grid-cols-[1fr_1fr_1fr_2fr_1fr] gap-2 px-4 py-3 text-sm border-b border-gray-50 hover:bg-blue-50 cursor-pointer transition text-gray-700"
                             data-value="<?php echo htmlspecialchars($key) ?>"
                             data-label="<?php echo htmlspecialchars($data['admin_no']) ?>"
                             data-date="<?php echo htmlspecialchars($data['date']) ?>"
                             data-monitoring-mode="<?php echo htmlspecialchars($itemMode) ?>">

                            <div class="truncate text-xs text-gray-500">
                                <?php echo date('d/m/Y', strtotime($data['date'])) ?>
                            </div>

                            <div class="font-bold text-xs text-gray-900">
                                <?php echo htmlspecialchars($data['admin_no']) ?>
                            </div>

                            <div>
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-black border <?php echo $itemMode === 'hybrid' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-blue-100 text-blue-700 border-blue-200' ?>">
                                    <?php echo strtoupper($itemMode) ?>
                                </span>
                            </div>

                            <div class="truncate text-xs text-gray-500" title="<?php echo htmlspecialchars($data['client_nm']) ?>">
                                <?php echo htmlspecialchars($data['client_nm']) ?>
                            </div>

                            <div class="text-right">
                                <span class="bg-gray-100 border border-gray-200 text-gray-600 px-2 py-0.5 rounded-full text-[11px]">
                                    <?php echo htmlspecialchars($data['qty']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-5 text-sm text-gray-500 text-center">
                    Tidak ada jadwal pengawasan.
                </div>
            <?php endif; ?>
        </div>
    </div>
                        <button type="button"
                        onclick="openMonitoring()"
                        id="btnMasukRuang"
                        class="w-full sm:w-auto bg-brand-primary hover:bg-brand-primaryHover text-white px-8 py-3 rounded-xl text-sm font-semibold transition shadow-sm whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                        <?php echo(isset($is_finished) && $is_finished) ? 'disabled' : '' ?>>
                        Masuk Ruang
                    </button>
                    </form>
                 </div>

        <?php else: ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-4 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">

                <div class="flex flex-col">
                    <div class="mb-1.5 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 <?php echo $is_finished ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-blue-50 text-brand-primary border-blue-100' ?> px-2.5 py-1 rounded-md border text-xs px-3 py-1.5 font-bold uppercase tracking-wider">
                            <?php if (! $is_finished): ?>
                                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-primary opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-brand-primary"></span></span> Live Monitoring
                            <?php else: ?>
                                <i class="fas fa-flag-checkered text-[11px]"></i> Session Ended
                            <?php endif; ?>
                        </span>

                        <button onclick="toggleModal('logActivityModal')" class="inline-flex items-center gap-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 px-2.5 py-1 rounded-md text-xs px-3 py-1.5 font-bold uppercase tracking-wider transition shadow-sm">
                            <i class="fas fa-history text-xs px-3 py-1.5"></i> Log Aktivitas
                        </button>

                        <button type="button" onclick="openAttendanceModal()" class="inline-flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-md text-xs px-3 py-1.5 font-bold uppercase tracking-wider transition shadow-sm">
                            <i class="fas fa-clipboard-list text-xs px-3 py-1.5"></i> Generate Absen
                        </button>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-xl font-bold text-gray-800">Admin No: <?php echo htmlspecialchars($test_info['admin_code']) ?></h2>
                        <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-black border <?php echo $is_hybrid_mode ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200' ?>">
                            <?php echo $is_hybrid_mode ? 'HYBRID' : 'ONLINE' ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Klien: <b><?php echo htmlspecialchars($test_info['client_nm']) ?></b> | Tanggal: <?php echo htmlspecialchars($test_info['test_date']) ?> | Login: <span class="text-brand-primary font-bold"><?php echo count($participants) ?>/<?php echo htmlspecialchars($test_info['total_participants']) ?></span></p>
                </div>

                <div class="flex flex-col sm:flex-row w-full lg:w-auto gap-3 items-center">
                    <form action="" method="GET" id="adminSelectionForm" class="flex w-full sm:w-auto items-center gap-2">
                        <input type="hidden" name="date" id="realDateInput" value="<?php echo htmlspecialchars($selected_date) ?>">

                        <div class="relative w-[130px]">
                            <i class="far fa-calendar-alt absolute left-3 top-2.5 text-gray-400 text-xs z-10"></i>
                            <input type="text" id="uiDatePicker" placeholder="dd/mm/yyyy" class="bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-md focus:ring-brand-primary block w-full pl-8 p-2 <?php echo $is_locked ? 'opacity-60 bg-gray-100 pointer-events-none' : 'cursor-text bg-white' ?>" <?php echo $is_locked ? 'readonly tabindex="-1"' : '' ?>>
                        </div>

                        <div class="relative w-[150px]" id="customSelectWrapper">
                            <input type="hidden" name="admin" id="selectedAdminCode" value="<?php echo htmlspecialchars($selected_dropdown) ?>">

                            <?php $has_jadwal = count($filtered_admins) > 0; ?>

                            <button type="button" id="dropdownBtn" <?php echo $is_locked || ! $has_jadwal ? 'disabled' : '' ?> class="<?php echo $is_locked || ! $has_jadwal ? 'opacity-60 bg-gray-100 cursor-not-allowed' : 'cursor-pointer bg-white hover:border-gray-300' ?> border border-gray-200 text-gray-800 text-xs rounded-md flex justify-between items-center w-full p-2 transition">
                                <span id="dropdownBtnText" class="font-semibold truncate"><?php echo htmlspecialchars($current_admin_no) ?></span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs px-3 py-1.5" id="dropdownIcon"></i>
                            </button>

                            <div id="dropdownMenu" class="hidden absolute right-0 z-50 w-[450px] mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden text-left">
                                <?php if ($has_jadwal): ?>
                                    <div class="p-2 border-b border-gray-100 bg-gray-50 sticky top-0 z-10">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                            <input type="text" id="dropdownSearch" class="bg-white border border-gray-300 text-gray-800 text-xs rounded-md focus:ring-brand-primary focus:border-brand-primary block w-full pl-8 p-1.5" placeholder="Ketik Admin / Klien untuk mencari..." autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-[1fr_1fr_1fr_2fr_1fr] gap-2 p-2 bg-gray-100 text-xs px-3 py-1.5 font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                        <div class="pl-2">Tanggal</div>
                                        <div>Admin</div>
                                        <div>Tipe</div>
                                        <div>Klien</div>
                                        <div class="text-right pr-2">Peserta</div>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto" id="dropdownListContainer">
                                        <?php foreach ($filtered_admins as $key => $data): ?>
                                            <?php $itemMode = monitoring_mode_from_admin_data($data); ?>
                                            <div class="dropdown-item grid grid-cols-[1fr_1fr_1fr_2fr_1fr] gap-2 p-2 text-xs border-b border-gray-50 hover:bg-brand-primary/5 cursor-pointer transition <?php echo $selected_dropdown === $key ? 'bg-blue-50 text-brand-primary font-semibold' : 'text-gray-700' ?>" data-value="<?php echo htmlspecialchars($key) ?>" data-label="<?php echo htmlspecialchars($data['admin_no']) ?>" data-date="<?php echo htmlspecialchars($data['date']) ?>" data-monitoring-mode="<?php echo htmlspecialchars($itemMode) ?>">
                                                <div class="truncate pl-1"><?php echo date('d/m/y', strtotime($data['date'])) ?></div>
                                                <div class="font-bold"><?php echo htmlspecialchars($data['admin_no']) ?></div>
                                                <div>
                                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-black border <?php echo $itemMode === 'hybrid' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-blue-100 text-blue-700 border-blue-200' ?>">
                                                        <?php echo strtoupper($itemMode) ?>
                                                    </span>
                                                </div>
                                                <div class="truncate text-gray-500" title="<?php echo htmlspecialchars($data['client_nm']) ?>"><?php echo htmlspecialchars($data['client_nm']) ?></div>
                                                <div class="text-right pr-1"><span class="bg-gray-100 px-1.5 py-0.5 rounded text-xs px-3 py-1.5 border border-gray-200"><?php echo htmlspecialchars($data['qty']) ?></span></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 text-xs text-gray-500 text-center">Tidak ada jadwal pada tanggal ini.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (! $is_locked && $is_finished): ?>
                            <button type="submit" class="bg-brand-primary hover:bg-brand-primaryHover text-white px-3 py-2 rounded-md text-xs font-medium shadow-sm transition"><i class="fas fa-arrow-right"></i></button>
                        <?php endif; ?>
                    </form>

                    <div class="border-l border-gray-200 h-6 mx-1 hidden sm:block"></div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <?php if (! $is_finished): ?>
                            <?php if (! $is_hybrid_mode): ?>
                                <button type="button" id="btnStartAll" onclick="controlAll('play')" class="w-full sm:w-auto bg-brand-primary hover:bg-brand-primaryHover text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm disabled:opacity-30 disabled:cursor-not-allowed"><i class="fas fa-play mr-1.5"></i> Start All</button>
                                <button type="button" id="btnPauseResumeAll" onclick="togglePauseResumeAll()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm"><i class="fas fa-pause mr-1.5"></i> <span>Pause All</span></button>
                            <?php endif; ?>
                            <a href="?date=<?php echo htmlspecialchars($selected_date) ?>&admin=<?php echo urlencode($selected_dropdown) ?>&monitoring_mode=<?php echo urlencode($currentMonitoringMode) ?>&finished=1" class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm whitespace-nowrap"><i class="fas fa-flag-checkered text-xs px-3 py-1.5 mr-1.5"></i> Tes Selesai</a>
                        <?php elseif (! empty($can_collect_crc)): ?>
                            <button type="button" onclick="openCollectDataModal()" class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-md text-xs font-medium transition shadow-sm animate-pulse whitespace-nowrap"><i class="fas fa-download text-xs px-3 py-1.5 mr-1.5"></i> Collect Data</button>
                        <?php else: ?>
                            <button type="button" disabled title="Collect Data CRC hanya untuk pengawas yang ditugaskan ke room ini." class="w-full sm:w-auto bg-gray-200 text-gray-500 px-5 py-2 rounded-md text-xs font-medium cursor-not-allowed whitespace-nowrap"><i class="fas fa-eye text-xs px-3 py-1.5 mr-1.5"></i> Realtime Only</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex-1 flex flex-col min-h-[400px]">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/80 flex justify-between items-center shrink-0">
                    <div class="flex items-center text-xs text-gray-600 font-medium">
                        <span class="mr-2">Tampilkan</span>
                        <select id="tableLimit" class="bg-white border border-gray-300 text-gray-900 text-xs rounded focus:ring-brand-primary focus:border-brand-primary p-1">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="ml-2">baris per halaman</span>
                    </div>
                </div>

                <div class="overflow-y-auto flex-1 h-full">
                    <table class="text-base min-w-full divide-y divide-gray-200" id="participantsTable">

                        <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase w-16">
                                    Online
                                </th>
                                <th class="px-4 py-3 text-left text-base font-semibold text-gray-600 uppercase">
                                    ID / Nama Peserta
                                </th>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase">
                                    Sesi / Part
                                </th>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase">
                                    Kuesioner
                                </th>
                                <th class="px-4 py-3 text-left text-base font-semibold text-gray-600 uppercase min-w-[180px]">
                                    Progress Jawaban
                                </th>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase">
                                    Sisa Waktu
                                </th>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-center text-base font-semibold text-gray-600 uppercase">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white" id="tableBody">
                            <?php echo $participants_html ?>
                        </tbody>

                    </table>
                </div>

                <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex flex-col md:flex-row justify-between items-center gap-4 shrink-0" id="paginationWrapper">
                    <div class="text-xs text-gray-500" id="pageInfo">Showing 1 to 10 of 0 entries</div>
                    <div class="flex space-x-1" id="paginationBtns"></div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<div id="participantDetailModal"
     class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 backdrop-blur-sm px-4">

    <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden border border-gray-200">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    Detail Peserta
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Informasi lengkap peserta berdasarkan data monitoring
                </p>
            </div>

            <button type="button"
                    onclick="closeParticipantDetailModal()"
                    class="w-9 h-9 rounded-full bg-white border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6 max-h-[75vh] overflow-y-auto">

            <div class="flex flex-col items-center text-center">
                <img id="participantDetailPhoto"
                     src=""
                     alt="Foto Peserta"
                     onerror="this.onerror=null; this.src='<?php echo defined('BASE_URL') ? BASE_URL . '/assets/personal/nopicture.png' : 'assets/personal/nopicture.png' ?>'"
                     class="w-40 h-40 rounded-2xl object-contain border border-gray-200 shadow-sm bg-gray-100">

                <h3 id="participantDetailName"
                    class="mt-4 text-base font-bold text-gray-900 uppercase">
                    -
                </h3>

                <p id="participantDetailId"
                   class="mt-1 text-xs font-mono text-gray-500">
                    -
                </p>

                <div class="w-full mt-5 pt-4 border-t border-gray-100 flex flex-col gap-3 text-left">
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Mulai Ujian</div>
                        <div id="participantDetailStartTime" class="mt-0.5 text-sm font-semibold text-gray-700 font-mono">-</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Selesai Ujian</div>
                        <div id="participantDetailEndTime" class="mt-0.5 text-sm font-semibold text-gray-700 font-mono">-</div>
                    </div>
                </div>
            </div>

                <div>
    <h4 class="text-sm font-bold text-gray-800 mb-3">
        Data Peserta
    </h4>

    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <tbody id="participantDetailTable"
                   class="divide-y divide-gray-100">
            </tbody>
        </table>
    </div>
</div>

        </div>

        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end">
            <button type="button"
                    onclick="closeParticipantDetailModal()"
                    class="px-5 py-2 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold transition">
                Tutup
            </button>
        </div>

    </div>
</div>

<div id="toastContainer" class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<div id="logActivityModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all h-[500px] flex flex-col">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center shrink-0">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-history text-brand-primary mr-2"></i>Log Aktivitas Peserta</h3>
            <button onclick="toggleModal('logActivityModal')" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 flex-1 overflow-y-auto bg-white" id="activityLogContainer">
            <div class="text-gray-400 text-xs text-center py-10 italic">Sistem siap merekam aktivitas...</div>
        </div>
    </div>
</div>

<div id="collectDataModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-database text-green-600 mr-2"></i>Collect Data Ujian</h3>
            <button onclick="toggleModal('collectDataModal')" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div class="flex border-b border-gray-200 mb-5">
                <button id="tabBtnOnline" onclick="switchTab('online')" class="w-1/2 py-2.5 text-sm font-bold border-b-2 border-brand-primary text-brand-primary transition">
                    <i class="fas fa-cloud-upload-alt mr-1"></i> Generate & Upload ZIP
                </button>
                <button id="tabBtnOffline" onclick="switchTab('offline')" class="w-1/2 py-2.5 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                    <i class="fas fa-upload mr-1"></i> Upload CRC Manual
                </button>
            </div>

            <div id="tabContentOnline" class="block space-y-4">
                <div class="bg-blue-50 border border-blue-100 p-3 rounded-lg flex gap-3 text-sm">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                    <p class="text-blue-800">Sistem akan men-generate ZIP CRC untuk ruang ujian <b><?php echo htmlspecialchars($test_info['admin_code'] ?? '-') ?></b>, mendownload file, lalu mengupload ZIP ke FTP.</p>
                </div>

                <div id="formGenerateCRC" class="mt-4">


                    <div class="mb-3">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Filter Status:</label>
                        <select id="filterStatusCollect" onchange="renderCollectChecklist(this.value)" class="w-full text-xs border border-gray-200 rounded-md px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500 bg-white">                            <option value="all">Semua (Selesai & Terminate)</option>
                            <option value="selesai">Hanya Selesai</option>
                            <option value="terminated">Hanya Terminate</option>
                        </select>
                    </div>

                    <div class="mb-2 flex justify-between items-center">
    <div>
        <label class="text-xs font-bold text-gray-700 uppercase">Pilih Peserta :</label>
        <div class="text-[10px] text-gray-500 mt-0.5">
            Terpilih: <span id="collectCheckedCounter" class="font-bold text-brand-primary">0/0</span>
        </div>
    </div>

    <label class="text-xs text-brand-primary cursor-pointer hover:underline font-bold whitespace-nowrap">
        <input type="checkbox" id="checkAllTakers" checked class="mr-1"> Pilih Semua
    </label>
</div>
</div>
                    <div id="checklistPeserta" class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 bg-gray-50 space-y-3 mb-4">
                    </div>

                    <button type="button" id="btnSubmitCRC" onclick="downloadCRC()" class="w-full bg-brand-primary hover:bg-brand-primaryHover text-white py-2.5 rounded-lg text-sm font-bold transition flex justify-center items-center gap-2">
                        <i class="fas fa-save"></i> Save ZIP & Upload
                    </button>
                </div>
            </div>

            <div id="tabContentOffline" class="hidden space-y-4">
<form action="upload_crc" method="POST" enctype="multipart/form-data" class="mt-4">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
    <input type="hidden" name="admin_code" value="<?php echo htmlspecialchars($test_info['admin_code'] ?? '') ?>">
    <input type="hidden" name="admin_rec_id" value="<?php echo htmlspecialchars($current_admin_rec_id ?? '') ?>">
    <input type="hidden" name="sub_admin_id" value="<?php echo htmlspecialchars($current_sub_admin_id ?? '') ?>">
    <input type="hidden" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? '') ?>">
    <label class="block mb-2 text-sm font-medium text-gray-900" for="file_input">Upload file .CRC manual</label>
    <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2.5" id="crc_file" name="crc_file" type="file" accept=".crc" required>
    <button type="submit" class="w-full mt-4 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg text-sm font-bold transition flex justify-center items-center gap-2">
        <i class="fas fa-cloud-upload-alt"></i> Upload CRC
    </button>
</form>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL BERITA ACARA (Muncul setelah Download ZIP berhasil)                    -->
<!-- =========================================================================== -->
<div id="beritaAcaraModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all max-h-[95vh] flex flex-col">
        <!-- Header -->
        <div class="bg-gradient-to-r from-brand-primary to-indigo-700 px-6 py-5 flex justify-between items-center shrink-0 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center text-white text-xl">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white tracking-tight">Berita Acara Pelaksanaan Tes</h3>
                    <p class="text-blue-100 text-[10px] font-medium uppercase tracking-widest opacity-80">Official Examination Report</p>
                </div>
            </div>
            <button onclick="closeBeritaAcaraModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-black/10 text-white hover:bg-red-500 transition-all duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-8 flex-1 overflow-y-auto space-y-8 bg-gray-50/50">

            <!-- Section 1: Informasi Utama -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-5 w-1 bg-brand-primary rounded-full"></div>
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Informasi Utama Ujian</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Hari & Tanggal -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-blue-50 text-brand-primary rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Waktu Pelaksanaan</span>
                        </div>
                        <div id="ba_hari" class="text-sm font-bold text-gray-900">-</div>
                        <div id="ba_tanggal" class="text-xs text-gray-500 font-medium">-</div>
                    </div>

                    <!-- Jam Test -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Jam Mulai</span>
                        </div>
                        <input type="time" id="ba_jam_input" class="w-full text-sm font-bold text-gray-800 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-brand-primary focus:border-brand-primary transition-all">
                    </div>

                    <!-- Klien -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow lg:col-span-2">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-sm">
                                <i class="fas fa-building"></i>
                            </div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Klien / Institusi</span>
                        </div>
                        <div id="ba_klien" class="text-sm font-bold text-gray-900 truncate">-</div>
                        <div class="text-[9px] text-gray-400 font-medium italic mt-1">* Pastikan nama instansi sudah sesuai</div>
                    </div>
                </div>
            </section>

            <!-- Section 1B: Detail Berita Acara -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-5 w-1 bg-slate-500 rounded-full"></div>
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Detail Berita Acara</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <label for="ba_lokasi_input" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-2">Lokasi Tes</label>
                        <input type="text" id="ba_lokasi_input" maxlength="120" class="w-full text-sm font-semibold text-gray-800 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:ring-brand-primary focus:border-brand-primary" placeholder="Lokasi pelaksanaan tes">
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <label for="ba_kota_input" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-2">Kota Penandatanganan</label>
                        <input type="text" id="ba_kota_input" maxlength="80" class="w-full text-sm font-semibold text-gray-800 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:ring-brand-primary focus:border-brand-primary" placeholder="Contoh: Jakarta">
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <label for="ba_koordinator_input" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-2">Nama Koordinator Instansi</label>
                        <input type="text" id="ba_koordinator_input" maxlength="100" class="w-full text-sm font-semibold text-gray-800 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:ring-brand-primary focus:border-brand-primary" placeholder="Nama koordinator yang mengetahui">
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <label for="ba_supervisor_input" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-2">Nama Supervisor Tes</label>
                        <input type="text" id="ba_supervisor_input" maxlength="100" class="w-full text-sm font-semibold text-gray-800 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:ring-brand-primary focus:border-brand-primary" placeholder="Nama supervisor ITC">
                    </div>
                </div>
            </section>

            <!-- Section 2: Statistik Peserta -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-5 w-1 bg-emerald-500 rounded-full"></div>
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Statistik Kehadiran</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Hadir -->
                    <div class="bg-gradient-to-br from-white to-blue-50/30 p-4 rounded-xl border border-blue-100 shadow-sm text-center">
                        <div class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-1">Peserta Hadir</div>
                        <div id="ba_jumlah_peserta_hadir" class="text-2xl font-black text-gray-900">-</div>
                        <div class="text-[9px] text-blue-500 font-bold mt-1 uppercase">Total Online</div>
                    </div>
                    <!-- Selesai -->
                    <div class="bg-gradient-to-br from-white to-emerald-50/30 p-4 rounded-xl border border-emerald-100 shadow-sm text-center">
                        <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1">Berhasil Selesai</div>
                        <div id="ba_peserta_selesai_count" class="text-2xl font-black text-emerald-700">-</div>
                        <div class="text-[9px] text-emerald-500 font-bold mt-1 uppercase">Completed Status</div>
                    </div>
                    <!-- Tidak Hadir -->
                    <div class="bg-gradient-to-br from-white to-rose-50/30 p-4 rounded-xl border border-rose-100 shadow-sm text-center">
                        <div class="text-[10px] font-bold text-rose-600 uppercase tracking-widest mb-1">Tidak Hadir</div>
                        <input type="number" id="ba_peserta_tidak_hadir_input" min="0" inputmode="numeric" class="w-full text-center text-2xl font-black text-rose-700 bg-white border border-rose-100 rounded-lg px-3 py-1 focus:ring-rose-500 focus:border-rose-500" placeholder="">
                        <div class="text-[9px] text-rose-500 font-bold mt-1 uppercase">Input Manual</div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- List Peserta Selesai -->
                <div class="flex flex-col h-[400px]">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check-double text-emerald-500"></i>
                            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Daftar Peserta Selesai</h4>
                        </div>
                        <span id="ba_count_selesai" class="bg-emerald-100 text-emerald-700 text-[10px] font-black px-2 py-0.5 rounded-full border border-emerald-200">0</span>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm flex flex-col flex-1">
                        <div class="p-3 bg-gray-50 border-b border-gray-100">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" id="ba_search_selesai" placeholder="Cari nama atau authorize..." class="w-full text-xs bg-white border border-gray-200 rounded-lg pl-8 pr-4 py-2 focus:ring-brand-primary focus:border-brand-primary outline-none transition-all">
                            </div>
                        </div>
                        <div class="px-3 py-1.5 bg-emerald-50/50 border-b border-emerald-100 flex justify-between items-center">
                            <span class="text-[9px] text-emerald-600 font-bold uppercase tracking-tight">Menampilkan <span id="ba_selesai_visible_count">0</span> Data</span>
                            <span class="text-[9px] text-gray-400 italic">Total: <span id="ba_selesai_total_count">0</span></span>
                        </div>
                        <div id="ba_list_selesai" class="overflow-y-auto p-4 space-y-2 flex-1 scrollbar-thin">
                            <div class="text-xs text-gray-400 text-center italic py-10">Memuat data...</div>
                        </div>
                    </div>
                </div>

                <!-- List Peserta Terminated -->
                <div class="flex flex-col h-[400px]">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-user-slash text-rose-500"></i>
                            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Peserta Di-Terminate</h4>
                        </div>
                        <span id="ba_count_terminate" class="bg-rose-100 text-rose-700 text-[10px] font-black px-2 py-0.5 rounded-full border border-rose-200">0</span>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm flex flex-col flex-1">
                        <div class="p-4 bg-rose-50/30 border-b border-rose-100">
                            <p class="text-[10px] text-rose-600 font-medium italic"><i class="fas fa-info-circle mr-1"></i>Daftar peserta yang ujiannya dihentikan oleh pengawas.</p>
                        </div>
                        <div id="ba_list_terminate" class="overflow-y-auto p-4 space-y-2 flex-1 scrollbar-thin">
                            <div class="text-xs text-gray-400 text-center italic py-10">Memuat data...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kejadian/Pelanggaran -->
            <section>
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-5 w-1 bg-amber-500 rounded-full"></div>
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Kejadian / Pelanggaran Selama Ujian</h4>
                </div>

                <div class="bg-amber-50/30 border border-amber-100 rounded-xl p-6 shadow-sm">
                    <div class="relative">
                        <textarea id="ba_kejadian_text" rows="4" maxlength="200" required
                            class="w-full text-sm border-2 border-gray-100 rounded-xl px-4 py-3 focus:ring-amber-500 focus:border-amber-500 bg-white placeholder-gray-300 resize-none transition-all outline-none"
                            placeholder="Tuliskan catatan kejadian atau pelanggaran jika ada. Jika tidak ada, tulis 'TIDAK ADA KEJADIAN KHUSUS'."></textarea>
                        <div class="absolute bottom-3 right-3 flex items-center gap-2 bg-gray-50 px-2 py-1 rounded-md border border-gray-100">
                            <span id="ba_kejadian_counter" class="text-[10px] font-black text-amber-600">0</span>
                            <span class="text-[10px] text-gray-300 font-bold">/ 200</span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-start gap-2">
                        <i class="fas fa-exclamation-circle text-amber-500 text-xs mt-0.5"></i>
                        <p class="text-[10px] text-amber-700 font-medium">Informasi ini akan tercetak pada lembar Berita Acara PDF dan bersifat permanen setelah disimpan.</p>
                    </div>
                </div>
            </section>

        </div>

        <!-- Footer Buttons -->
        <div class="px-8 py-5 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
            <button onclick="closeBeritaAcaraModal()" class="w-full sm:w-auto px-6 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-100 hover:text-gray-800 transition-all shadow-sm flex items-center justify-center gap-2">
                <i class="fas fa-chevron-left text-[10px]"></i> Kembali
            </button>

            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <button onclick="downloadBeritaAcaraPDF()" id="btnDownloadBeritaAcara" class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all shadow-md flex items-center justify-center gap-2 group">
                    <i class="fas fa-file-pdf group-hover:scale-110 transition-transform"></i> Download PDF
                </button>
                <button onclick="saveAndUploadBeritaAcara()" id="btnSaveBeritaAcara" class="w-full sm:w-auto px-8 py-3 bg-brand-primary hover:bg-brand-primaryHover text-white rounded-xl text-sm font-black transition-all shadow-lg shadow-brand-primary/20 flex items-center justify-center gap-2 group">
                    <i class="fas fa-cloud-upload-alt group-hover:translate-y-[-2px] transition-transform"></i> Save and Upload
                </button>
            </div>
        </div>
    </div>
</div>

<div id="attendanceModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl overflow-hidden transform transition-all max-h-[95vh] flex flex-col">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-5 flex justify-between items-center shrink-0 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center text-white text-xl">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white tracking-tight">Generate Absensi Peserta</h3>
                    <p class="text-blue-100 text-[10px] font-medium uppercase tracking-widest opacity-80">Attendance List</p>
                </div>
            </div>
            <button onclick="closeAttendanceModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-black/10 text-white hover:bg-red-500 transition-all duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto bg-gray-50/50">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="h-5 w-1 bg-blue-500 rounded-full"></div>
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Absensi Peserta</h4>
                </div>
                <div class="text-[10px] text-gray-500 font-medium">
                    Review dan koreksi data sebelum generate file absensi.
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <div class="p-3 bg-blue-50/40 border-b border-blue-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="text-xs text-blue-800 font-medium">
                        <i class="fas fa-info-circle mr-1"></i>
                        Status kehadiran otomatis dari monitoring, tetapi bisa dikoreksi manual.
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-gray-500 uppercase">Ringkasan:</span>
                        <span id="attendanceSummary" class="text-[10px] font-black text-blue-700 bg-blue-100 border border-blue-200 rounded-full px-2 py-1">-</span>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-[520px]">
                    <table class="min-w-[1250px] w-full text-xs">
                        <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                            <tr class="text-[10px] uppercase tracking-wider text-gray-500">
                                <th class="px-3 py-2 text-center w-12">No</th>
                                <th class="px-3 py-2 text-left">Authcode</th>
                                <th class="px-3 py-2 text-left min-w-[220px]">Nama</th>
                                <th class="px-3 py-2 text-left">DOB</th>
                                <th class="px-3 py-2 text-left">No. ID</th>
                                <th class="px-3 py-2 text-left">No. HP</th>
                                <th class="px-3 py-2 text-left">Kehadiran</th>
                                <th class="px-3 py-2 text-left min-w-[260px]">Keterangan</th>
                                <th class="px-3 py-2 text-left">Group</th>
                                <th class="px-3 py-2 text-left">Custom 1</th>
                                <th class="px-3 py-2 text-left">Custom 2</th>
                                <th class="px-3 py-2 text-left">Custom 3</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody" class="divide-y divide-gray-100">
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-gray-400 italic">Absensi akan dimuat saat modal dibuka.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
            <button onclick="closeAttendanceModal()" class="w-full sm:w-auto px-6 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-100 hover:text-gray-800 transition-all shadow-sm flex items-center justify-center gap-2">
                <i class="fas fa-chevron-left text-[10px]"></i> Tutup
            </button>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <button onclick="downloadFinalAttendanceFile('xlsx')" id="btnDownloadFinalAttendanceExcel" class="w-full sm:w-auto px-4 py-3 bg-white border border-green-200 text-green-700 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                    <i class="fas fa-cloud-download-alt"></i> Download Full Excel
                </button>
                <button onclick="downloadFinalAttendanceFile('pdf')" id="btnDownloadFinalAttendancePDF" class="w-full sm:w-auto px-4 py-3 bg-white border border-blue-200 text-blue-700 rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                    <i class="fas fa-cloud-download-alt"></i> Download Full PDF
                </button>
                <button onclick="downloadAttendanceExcel()" id="btnDownloadAttendanceExcel" class="w-full sm:w-auto px-5 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                    <i class="fas fa-file-excel"></i> Upload Absensi Excel
                </button>
                <button onclick="downloadAttendancePDF()" id="btnDownloadAttendancePDF" class="w-full sm:w-auto px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all shadow-sm flex items-center justify-center gap-2">
                    <i class="fas fa-file-pdf"></i> Upload Absensi PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jsPDF Library for PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
    // CSRF Token untuk semua AJAX/fetch requests
    // DEBUG ONLY: CSRF token dimatikan sementara.
    // const CSRF_TOKEN = <?php echo json_encode(Csrf::getToken()) ?>;
    const CSRF_TOKEN = '';
    const MONITORING_ENDPOINT = <?php echo json_encode(basename($_SERVER['SCRIPT_NAME'])) ?>;
    const CRC_UPLOAD_TO_FTP = true;
    const ATTENDANCE_XLSX_TEMPLATE_URL = <?php echo json_encode(BASE_URL . '/assets/images/Rizty Utami_IIK BHAKTI WIYATA_7 PAX_SESI2_13022026.xlsx') ?>;
    let attendanceState = {};
    let attendanceGenerationUnlocked = false;
    const BA_TOEIC_LOGO_BASE64 = <?php
                                     $toeicLogoPath = BASE_PATH . '/assets/images/toeic.png';
                                 echo json_encode(file_exists($toeicLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($toeicLogoPath)) : '');
                                 ?>;
    const BA_ETS_LOGO_BASE64 = <?php
                                   $etsLogoPath = BASE_PATH . '/assets/images/ets.png';
                               echo json_encode(file_exists($etsLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($etsLogoPath)) : '');
                               ?>;

    function getMonitoringPostEndpoint() {
        const query = window.location.search || '';
        return query ? `${MONITORING_ENDPOINT}${query}` : MONITORING_ENDPOINT;
    }

    /*
     * Pembeda Selesai Normal vs Terminate:
     * - Selesai normal  : statrec = 7 dan ke_suspend != 1
     * - Terminate       : statrec = 7 dan ke_suspend = 1
     * Jadi jangan lagi membaca statrec 7 otomatis sebagai terminate.
     */
    function getRowKeSuspend(row) {
        return String(
            row?.dataset?.keSuspend
            || row?.dataset?.suspend
            || row?.getAttribute?.('data-ke-suspend')
            || row?.getAttribute?.('data-ke_suspend')
            || row?.getAttribute?.('data-suspend')
            || '0'
        ).trim();
    }

    function isRowTerminated(row) {
        const status = String(row?.dataset?.status || row?.getAttribute?.('data-status') || '').trim().toLowerCase();
        return status === '7' && getRowKeSuspend(row) === '1';
    }

    function isRowFinishedNormalForCollect(row) {
        const status = String(row?.dataset?.status || row?.getAttribute?.('data-status') || '').trim().toLowerCase();
        return !isRowTerminated(row) && status === '7';
    }

    // FUNGSI JAVASCRIPT DOWNLOAD PENGGANTI FORM (DIJAMIN ANTI BLANK SCREEN)
    function downloadCRC() {
        const checkedBoxes = document.querySelectorAll('.takers-checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('Silakan pilih minimal satu peserta untuk didownload!');
            return;
        }

        const btn = document.getElementById('btnSubmitCRC');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Meracik ZIP...';
        btn.disabled = true;

const modal = document.getElementById('collectDataModal');

if (modal && modal.classList.contains('hidden')) {
    modal.classList.remove('hidden');
}        showToast('Memulai pembuatan file .CRC...');

        // DOWNLOAD CRC ZIP FILE
        const formData = new FormData();
        formData.append('admin_code', '<?php echo htmlspecialchars($test_info['admin_code'] ?? '') ?>');
        formData.append('admin_rec_id', '<?php echo htmlspecialchars($current_admin_rec_id ?? '') ?>');
        formData.append('sub_admin_id', '<?php echo htmlspecialchars($current_sub_admin_id ?? '') ?>');
        formData.append('test_type', '<?php echo htmlspecialchars($current_test_type ?? '') ?>');
        formData.append('test_date', '<?php echo htmlspecialchars($test_info['test_date'] ?? '') ?>');

        checkedBoxes.forEach(cb => {
            formData.append('selected_takers[]', cb.value);
        });

        // Tambahkan CSRF token ke FormData
        // DEBUG ONLY: formData.append('csrf_token', CSRF_TOKEN);

        // Mulai Download Latar Belakang (Fetch API)
        fetch('generate_crc.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // DEBUG ONLY: 'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async response => {
                if (!response.ok) {
                    throw new Error('Server merespon dengan kode ' + response.status);
                }

                // Periksa Header apakah isinya benar-benar file ZIP
                const contentType = response.headers.get('content-type') || '';

                if (contentType.includes('application/zip') ||
                    contentType.includes('application/octet-stream') ||
                    contentType.includes('application/x-zip-compressed') ||
                    contentType.includes('binary/octet-stream'))
                {
                    return response.blob();
                } else {
                    // Berarti file PHP mengembalikan pesan ERROR (Akses tidak valid, dsb)
                    const errText = await response.text();
                    const cleanText = errText.replace(/<[^>]*>?/gm, '').trim();
                    throw new Error(cleanText || "Server gagal mengembalikan file ZIP.");
                }
            })
            .then(blob => {
                // PROSES DOWNLOAD FILE KE KOMPUTER
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;

                const adminCode = formData.get('admin_code') || 'CBT';
                a.download = `CRC_KUMPULAN_${adminCode}.zip`;

                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);

                showToast('Download ZIP Berhasil!');

                if (CRC_UPLOAD_TO_FTP) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading ZIP...';

                    uploadGeneratedCrcZipToFtp(blob, adminCode)
                        .then(() => {
                            showToast('ZIP CRC berhasil diupload ke storage. Menandai peserta collected...');
                            return markCrcCollected();
                        })
                        .then(result => {
                            if (result) {
                                const skipped = parseInt(result.skipped_count || 0, 10);
                                showToast(`Collected ditandai: ${result.updated_count || 0}/${result.selected_count || 0}${skipped > 0 ? `, skip ${skipped}` : ''}.`);
                            }
                        })
                        .catch(err => {
                            console.error('Upload/mark CRC gagal:', err);
                            alert('ZIP berhasil terdownload, tapi proses upload storage atau penandaan collected gagal:\n\n' + err.message);
                        })
                        .finally(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;

                            setTimeout(() => openBeritaAcaraModal(), 500);
                        });

                    return;
                }

                btn.innerHTML = originalText;
                btn.disabled = false;

                setTimeout(() => openBeritaAcaraModal(), 500);
            })
            .catch(err => {
                console.error(err);
                alert("GAGAL DOWNLOAD\n\nServer merespon: " + err.message + "\n\nKemungkinan penyebab: Error saat meracik file CRC.");
                showToast('Proses Download Gagal.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    async function uploadGeneratedCrcZipToFtp(blob, adminCode) {
        const fileName = `CRC_KUMPULAN_${adminCode || 'CBT'}.zip`;

        const uploadData = new FormData();
        uploadData.append('action', 'upload_crc_zip_to_ftp');
        uploadData.append('admin_no', adminCode || <?php echo json_encode($test_info['admin_code'] ?? 'CBT') ?>);
        uploadData.append('admin_rec_id', <?php echo json_encode($current_admin_rec_id ?? '') ?>);
        uploadData.append('sub_admin_id', <?php echo json_encode($current_sub_admin_id ?? '') ?>);
        uploadData.append('tanggal', <?php echo json_encode(date('Y-m-d')) ?>);
        // DEBUG ONLY: uploadData.append('csrf_token', CSRF_TOKEN);
        uploadData.append('crc_zip', blob, fileName);

        const response = await fetch(getMonitoringPostEndpoint(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                // DEBUG ONLY: 'X-CSRF-Token': CSRF_TOKEN,
                'Accept': 'application/json'
            },
            body: uploadData
        });

        const text = await response.text();

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Response upload FTP CRC bukan JSON:', text);
            throw new Error('Response upload FTP bukan JSON. Cek Console browser dan php-error.log.');
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || result.msg || 'Upload FTP CRC gagal.');
        }

        return result;
    }

    async function markCrcCollected() {
        const checkedBoxes = document.querySelectorAll('.takers-checkbox:checked');
        const body = new FormData();
        body.append('action', 'mark_crc_collected');
        body.append('admin_rec_id', <?php echo json_encode($current_admin_rec_id ?? '') ?>);
        body.append('sub_admin_id', <?php echo json_encode($current_sub_admin_id ?? '') ?>);

        checkedBoxes.forEach(cb => {
            body.append('selected_takers[]', cb.value);
        });

        const response = await fetch(getMonitoringPostEndpoint(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Response mark collected bukan JSON:', text);
            throw new Error('Response penandaan collected bukan JSON.');
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Penandaan collected gagal.');
        }

        if (typeof reloadParticipantTableFromDatabase === 'function') {
            reloadParticipantTableFromDatabase();
        } else {
            document.querySelectorAll('.takers-checkbox:checked').forEach(cb => {
                const row = document.querySelector(`.participant-row[data-rec-id="${cb.value}"]`);
                if (row && !isRowTerminated(row)) {
                    row.dataset.status = '8';
                    row.dataset.statrec = '8';
                    updateRowStatusVisual(row, 'Collected');
                }
            });
        }

        return result;
    }


    // FUNGSI BUKA MODAL COLLECT DATA (DENGAN CHECKLIST OTOMATIS & FILTER)
    function renderCollectChecklist(filter = 'all') {
    const checklistContainer = document.getElementById('checklistPeserta');
    const btnSubmit = document.getElementById('btnSubmitCRC');
    const checkAll = document.getElementById('checkAllTakers');
    const filterSelect = document.getElementById('filterStatusCollect');

    if (!checklistContainer || !btnSubmit || !checkAll) return;

    if (filterSelect) {
        filterSelect.value = filter;
    }

    checklistContainer.innerHTML = '';
    let finishedCount = 0;

    document.querySelectorAll('.participant-row').forEach(row => {
        const status = (row.getAttribute('data-status') || '').toLowerCase();

        // statrec 7 = End of Test / selesai normal.
        // Terminate hanya untuk peserta yang memang di-stop/ditandai bermasalah.
        const isTerminated = isRowTerminated(row);
        const isFinished = isRowFinishedNormalForCollect(row);

        let show = false;

        if (filter === 'all' && (isTerminated || isFinished)) {
            show = true;
        } else if (filter === 'selesai' && isFinished) {
            show = true;
        } else if (filter === 'terminated' && isTerminated) {
            show = true;
        }

        if (show) {
            finishedCount++;

            const recId = row.getAttribute('data-rec-id');
            const authId = row.getAttribute('data-auth-id');
            const name = row.getAttribute('data-name');

            if (collectCheckedState[recId] === undefined) {
                collectCheckedState[recId] = !isTerminated;
            }

            const isChecked = collectCheckedState[recId] ? 'checked' : '';

            const label = document.createElement('div');
            label.className = 'item-participant-modal flex flex-col gap-1 p-2 rounded border shadow-sm ' +
                (isTerminated ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200');

            label.innerHTML = `
                <div class="flex items-start gap-3 w-full">
                        <input type="checkbox" value="${recId}" data-auth="${authId}" data-name="${name}" data-status="${status}" ${isChecked} ${isTerminated ? 'disabled' : ''} class="takers-checkbox mt-1 text-brand-primary focus:ring-brand-primary rounded disabled:opacity-40 disabled:cursor-not-allowed">
                    <div class="flex-1">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold uppercase ${isTerminated ? 'text-red-700' : 'text-gray-800'}">${name}</span>
                            <div class="flex items-center gap-2">
                                ${isTerminated
                                    ? '<span class="text-[9px] font-bold text-white bg-red-500 px-1.5 py-0.5 rounded uppercase">Terminate</span>'
                                    : '<span class="text-[9px] font-bold text-white bg-green-500 px-1.5 py-0.5 rounded uppercase">Selesai</span>'}
                                <span class="text-[9px] text-gray-400 font-mono">${authId}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            checklistContainer.appendChild(label);
        }
    });

    if (finishedCount === 0) {
        checklistContainer.innerHTML = '<div class="p-3 text-center text-xs text-red-500 font-medium italic">Tidak ada peserta sesuai filter ini.</div>';
        btnSubmit.disabled = true;
        checkAll.disabled = true;
        checkAll.checked = false;
    } else {
        btnSubmit.disabled = false;
        checkAll.disabled = false;
        checkAll.checked = true;
    }

checkAll.onchange = function () {
    document.querySelectorAll('#checklistPeserta .takers-checkbox:not(:disabled)').forEach(cb => {
        cb.checked = this.checked;
        collectCheckedState[cb.value] = this.checked;
    });

    updateCollectCounter();
};

document.querySelectorAll('#checklistPeserta .takers-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
        collectCheckedState[this.value] = this.checked;
        updateCollectCounter();
    });
});

updateCollectCounter();

    checklistContainer.onchange = function (e) {
        if (e.target.classList.contains('takers-checkbox')) {
            const totalChecked = document.querySelectorAll('.takers-checkbox:not(:disabled):checked').length;
            const totalCheckbox = document.querySelectorAll('.takers-checkbox:not(:disabled)').length;

            btnSubmit.disabled = totalChecked === 0;
            checkAll.checked = totalCheckbox > 0 && totalChecked === totalCheckbox;
        }
    };
}

function updateCollectCounter() {
    const counter = document.getElementById('collectCheckedCounter');
    const btnSubmit = document.getElementById('btnSubmitCRC');
    const checkAll = document.getElementById('checkAllTakers');

    const visibleCheckboxes = document.querySelectorAll('#checklistPeserta .takers-checkbox:not(:disabled)');
    const checkedCheckboxes = document.querySelectorAll('#checklistPeserta .takers-checkbox:not(:disabled):checked');

    const totalVisible = visibleCheckboxes.length;
    const totalChecked = checkedCheckboxes.length;

    if (counter) {
        counter.textContent = `${totalChecked}/${totalVisible}`;
    }

    if (btnSubmit) {
        btnSubmit.disabled = totalChecked === 0;
    }

    if (checkAll) {
        checkAll.checked = totalVisible > 0 && totalChecked === totalVisible;
        checkAll.disabled = totalVisible === 0;
    }
}

let collectCheckedState = {};
function openCollectDataModal(filter = 'all') {
    renderCollectChecklist(filter);

    const modal = document.getElementById('collectDataModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

    // Annas ngubah ini tanggal 09 april 2026
    // Tombol start all dan puase all
    function controlAll(action) {
        if (action === 'play') {
            const rows = Array.from(document.querySelectorAll('.participant-row'));
            const totalRows = rows.length;
            const readyRows = rows.filter(row => getRowStatus(row) === '2').length;

            if (totalRows === 0 || readyRows !== totalRows) {
                alert(`Start All tidak bisa dijalankan. Semua peserta harus statrec 2 / Readiness. Saat ini hanya ${readyRows} dari ${totalRows} peserta yang Readiness.`);
                updateGlobalButtonsState();
                return;
            }
        }

        if (!confirm(`Yakin mau ${action.toUpperCase()} semua peserta?`)) return;

        const matchingRows = Array.from(document.querySelectorAll('.participant-row')).filter(row => {
            if (isRowFinished(row)) return false;

            const status = getRowStatus(row);
            const isSuspended = String(row.dataset.keSuspend || row.dataset.suspend || '0') === '1';

            if (action === 'play') return status === '2';
            if (action === 'pause') return !isSuspended && ['3', '4', '5', '6'].includes(status);
            if (action === 'resume') return isSuspended;

            return false;
        });

        if (matchingRows.length === 0) {
            showToast('Tidak ada peserta yang memenuhi kondisi untuk aksi ini.');
            updateGlobalButtonsState();
            return;
        }

        const body = new URLSearchParams();
        body.append('action', action);
        body.append('admin_rec_id', <?php echo json_encode($current_admin_rec_id ?? '') ?>);
        body.append('sub_admin_id', <?php echo json_encode($current_sub_admin_id ?? '') ?>);

        fetch('timer_control_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    // DEBUG ONLY: 'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: body.toString()
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const logAction = action.toUpperCase() + " ROOM";
                    addLog("SYSTEM", logAction);
                    showToast(`Berhasil: ${logAction}. Status diperbarui...`);

              if (typeof reloadParticipantTableFromDatabase === 'function') {
                  reloadParticipantTableFromDatabase();
        }
                    document.querySelectorAll('.participant-row').forEach(row => {
                        if (isRowFinished(row)) return;

                        const timerEl = row.querySelector('.timer-countdown');
                        const currentStatus = getRowStatus(row);

                        if (action === 'play') {
                            // LOGIC LAMA: Start All hanya berlaku untuk peserta Readiness/statrec = 2.
                            // Jangan ubah visual row lain, supaya UI tidak terlihat seolah-olah semua ikut Start.
                            if (currentStatus === '2') {
                                row.dataset.status = '3';
                                row.dataset.keSuspend = '0';
                                if (timerEl) timerEl.dataset.active = 'true';
                            }
                        } else if (action === 'pause') {
                            row.dataset.keSuspend = '1';
                            if (timerEl) timerEl.dataset.active = 'false';
                        } else if (action === 'resume') {
                            row.dataset.keSuspend = '0';
                            if (timerEl) timerEl.dataset.active = 'true';
                        } else if (action === 'stop') {
                            row.dataset.status = '7';
                            row.dataset.keSuspend = '1';
                            if (timerEl) {
                                timerEl.dataset.active = 'false';
                                timerEl.dataset.finished = 'true';
                                const timerValueEl = timerEl.querySelector('.timer-value');
                                if (timerValueEl) timerValueEl.innerText = 'Selesai';
                            }
                        }

                        updateRowStatusVisual(row);
                        updateRowPauseResumeButton(row);
                    });

                    syncTimersFromDatabase();
                    updateGlobalButtonsState();
                    disableActionButtonsWhenFinished();
                } else {
                    alert(res.message || 'Perintah massal gagal diproses.');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Gagal menghubungi server timer_control_room.php');
            });
    }

    function togglePauseResumeAll() {
        const btn = document.getElementById('btnPauseResumeAll');
        const isCurrentlyPause = btn.querySelector('span').innerText.includes('Pause');
        const action = isCurrentlyPause ? 'pause' : 'resume';

        controlAll(action);
    }

    function formatSecondsToHHMMSS(totalSecs) {
        totalSecs = Math.max(0, parseInt(totalSecs || 0));

        if (totalSecs <= 0) {
            return '00:00:00';
        }

        const h = Math.floor(totalSecs / 3600);
        const m = Math.floor((totalSecs % 3600) / 60);
        const sec = totalSecs % 60;

        return [h, m, sec].map(v => String(v).padStart(2, '0')).join(':');
    }

    function getRowStatus(row) {
        return String(row?.dataset?.statrec || row?.dataset?.status || '').trim();
    }

    function getTimerText(row) {
        const timerEl = row?.querySelector?.('.timer-countdown');
        const valueEl = timerEl?.querySelector?.('.timer-value');
        return String(valueEl?.innerText || valueEl?.textContent || timerEl?.innerText || '').trim().toLowerCase();
    }

    function getQuestionnaireText(row) {
        // Kolom ke-4 adalah Kuesioner pada table ini.
        return String(row?.children?.[3]?.innerText || '').trim().toLowerCase();
    }

    function isRowFinished(row) {
        const status = getRowStatus(row).toLowerCase();
        const timerEl = row?.querySelector?.('.timer-countdown');

        // Satu-satunya patokan selesai untuk mengunci aksi adalah status DB End of Test.
        // Jangan mengunci berdasarkan teks timer saja, karena saat F5/finished=1 beberapa timer bisa ter-render "Selesai" sementara.
        return status === '7' || timerEl?.dataset?.finished === 'true';
    }

    function isRowStarted(row) {
        const status = getRowStatus(row);
        const timerEl = row?.querySelector?.('.timer-countdown');
        const timerText = getTimerText(row);
        const questionnaireText = getQuestionnaireText(row);

        return timerEl?.dataset?.active === 'true'
            || ['3', '4', '5', '6'].includes(status)
            || questionnaireText.includes('terisi')
            || (/^\d{1,2}:\d{2}:\d{2}$/.test(timerText) && timerText !== '00:00:00');
    }


    function getStatusTextFromState(status, keSuspend) {
        // Tetap memakai mapping status lama, tetapi jika sedang suspend tampilkan Suspend.
        const stat = String(status || '').trim();
        const lower = stat.toLowerCase();

        if (stat === '7' && parseInt(keSuspend || 0, 10) === 1) {
            return 'Terminate';
        }

        if (parseInt(keSuspend || 0, 10) === 1 && ['3', '4', '5', '6'].includes(stat)) {
            return 'Suspend';
        }

        switch (stat) {
            case '0': return 'Raw Authorize';
            case '1': return 'Authorize';
            case '2': return 'Readiness';
            case '3': return 'Ready to enter CBT';
            case '4': return 'Questioner';
            case '5': return 'Regulation & Confidentiality Agrement';
            case '6': return 'Ready to Test';
            case '7': return 'End of Test';
            case '8': return 'Collected';
            case '9': return 'Submit';
            default:
                if (lower === 'c') return 'Completed';
                if (lower === 'a') return 'Active';
                return stat === '' ? 'Unknown' : `${stat} - Unknown`;
        }
    }


    function getStatusBadgeClass(statusText) {
        const txt = String(statusText || '').toLowerCase();

        if (txt.includes('terminate')) return 'bg-red-100 text-red-700 border-red-200';
        if (txt.includes('suspend')) return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        if (txt.includes('raw')) return 'bg-gray-100 text-gray-700 border-gray-200';
        if (txt === 'authorize') return 'bg-slate-100 text-slate-700 border-slate-200';
        if (txt.includes('readiness')) return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        if (txt.includes('ready to enter') || txt.includes('ready to test')) return 'bg-blue-100 text-blue-700 border-blue-200';
        if (txt.includes('questioner') || txt.includes('regulation')) return 'bg-indigo-100 text-indigo-700 border-indigo-200';
        if (txt.includes('end of test') || txt.includes('collected') || txt.includes('submit') || txt.includes('completed')) return 'bg-green-100 text-green-700 border-green-200';
        if (txt.includes('unknown')) return 'bg-red-100 text-red-700 border-red-200';

        return 'bg-gray-100 text-gray-700 border-gray-200';
    }

    function updateRowStatusVisual(row, forcedText = null) {
        if (!row) return;

        const status = getRowStatus(row);
        const keSuspend = row.dataset.keSuspend || row.dataset.suspend || '0';
        const statusText = forcedText || getStatusTextFromState(status, keSuspend);
        row.dataset.statusText = statusText;

        // Kolom Status adalah kolom ke-7 pada table ini.
        const statusCell = row.children?.[6];
        if (!statusCell) return;

        statusCell.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold ${getStatusBadgeClass(statusText)}">${statusText}</span>`;
    }

    function updateRowPauseResumeButton(row) {
        if (!row || isRowFinished(row)) return;

        const actionCell = row.querySelector('td:last-child');
        if (!actionCell) return;

        const isSuspended = parseInt(row.dataset.keSuspend || row.dataset.suspend || 0, 10) === 1;
        const buttons = actionCell.querySelectorAll('button, a');

        buttons.forEach(btn => {
            const onclick = btn.getAttribute('onclick') || '';
            const text = (btn.innerText || btn.textContent || '').toLowerCase();
            const isPauseResumeButton = onclick.includes("controlTimer('pause'")
                || onclick.includes('controlTimer("pause"')
                || onclick.includes("controlTimer('resume'")
                || onclick.includes('controlTimer("resume"')
                || text.includes('pause')
                || text.includes('suspend')
                || text.includes('resume');

            if (!isPauseResumeButton) return;

            const authId = row.getAttribute('data-auth-id');
            // Endpoint timer_control.php lama memakai action pause sebagai toggle:
            // ke_suspend 0 -> 1 dan 1 -> 0. Jadi tombol Resume tetap memanggil pause.
            const nextAction = 'pause';
            btn.setAttribute('onclick', `controlTimer('${nextAction}', '${authId}')`);
            btn.title = isSuspended ? 'Resume peserta ini' : 'Suspend/Pause peserta ini';

            const icon = btn.querySelector('i');
            if (icon) icon.className = isSuspended ? 'fas fa-play' : 'fas fa-pause';

            const span = btn.querySelector('span');
            if (span) {
                span.innerText = isSuspended ? 'Resume' : 'Suspend';
            } else if ((btn.innerText || '').trim()) {
                // Jaga icon tetap ada, tapi update teks jika tombol memang punya teks.
                const iconHtml = icon ? icon.outerHTML + ' ' : '';
                btn.innerHTML = iconHtml + (isSuspended ? 'Resume' : 'Suspend');
            }

            btn.classList.toggle('bg-blue-600', isSuspended);
            btn.classList.toggle('hover:bg-blue-700', isSuspended);
            btn.classList.toggle('bg-yellow-500', !isSuspended);
            btn.classList.toggle('hover:bg-yellow-600', !isSuspended);
        });
    }

    function updateAllRowsVisualState() {
        document.querySelectorAll('.participant-row').forEach(row => {
            updateRowStatusVisual(row);
            updateRowPauseResumeButton(row);
        });
    }

    function detectSessionCode(row) {
        const sessionText = String(row?.children?.[2]?.innerText || '').toLowerCase();
        const timerEl = row?.querySelector?.('.timer-countdown');
        const existingLabel = String(timerEl?.querySelector?.('.timer-session-label')?.innerText || '').trim().toUpperCase();

        if (existingLabel === 'R' || sessionText.includes('reading') || sessionText.includes(' r')) return 'R';
        return 'L';
    }

    function repairTimerCellIfStarted(row) {
        const timerEl = row?.querySelector?.('.timer-countdown');
        if (!timerEl || isRowFinished(row)) return;

        const timerValueEl = timerEl.querySelector('.timer-value');
        if (!timerValueEl) return;

        const timerText = getTimerText(row);
        const questionnaireText = getQuestionnaireText(row);
        const status = getRowStatus(row);

        /*
         * Backup visual fix:
         * Jika peserta sudah punya tanda mulai (kuisioner terisi / statrec aktif),
         * tetapi renderer masih mencetak "Belum Mulai", tampilkan timer default sesuai sesi.
         * Sumber utama tetap data-renderer/database; ini hanya mencegah row aktif terlihat belum mulai.
         */
        const looksStarted = questionnaireText.includes('terisi') || ['3', '4', '5', '6'].includes(status);
        if (!looksStarted || !timerText.includes('belum mulai')) return;

        const session = detectSessionCode(row);
        let remaining = parseInt(timerEl.dataset.remainingSeconds || timerEl.dataset.remaining || 0);

        if (!remaining || remaining <= 0) {
            // Jangan isi ulang ke 45/75 menit di frontend.
            // Nilai timer harus disinkronkan dari database via ajax_timer_sync.
            timerEl.dataset.active = 'false';
            timerEl.dataset.finished = 'false';
            timerValueEl.innerText = 'Sinkron...';
            if (typeof syncTimersFromDatabase === 'function') syncTimersFromDatabase();
            return;
        }

        timerEl.dataset.active = 'true';
        timerEl.dataset.finished = 'false';
        timerValueEl.innerText = formatSecondsToHHMMSS(remaining);

        if (!timerEl.querySelector('.timer-session-label')) {
            const badge = document.createElement('span');
            badge.className = 'timer-session-label ' + (session === 'R' ? 'reading' : 'listening');
            badge.innerText = session;
            timerEl.prepend(badge);
        }
    }

    function repairAllTimerCells() {
        document.querySelectorAll('.participant-row').forEach(row => repairTimerCellIfStarted(row));
    }

    function updateGlobalButtonsState() {
        const rows = document.querySelectorAll('.participant-row');
        if (rows.length === 0) return;

        let totalEligibleForStart = 0;
        let totalStarted = 0;
        let totalSuspended = 0;
        let totalActive = 0;

        rows.forEach(row => {
            repairTimerCellIfStarted(row);

            const status = getRowStatus(row);
            const keSuspend = parseInt(row.dataset.keSuspend || row.dataset.suspend || 0);
            const timerEl = row.querySelector('.timer-countdown');
            const timerText = getTimerText(row);
            const isFinishedRow = isRowFinished(row);
            const isStartedRow = isRowStarted(row);

            // Start All hanya aktif jika ada peserta dengan status Readiness/statrec = 2.
            // Jangan pakai teks timer "Belum Mulai", karena Raw Authorize/Authorize juga bisa belum mulai.
            if (!isFinishedRow && status === '2') totalEligibleForStart++;
            if (!isFinishedRow && isStartedRow) totalStarted++;

            if (!isFinishedRow && isStartedRow) {
                if (keSuspend === 1) totalSuspended++;
                else totalActive++;
            }
        });

        // 1. Logic Start All:
        // Start All HANYA boleh aktif kalau SEMUA peserta/authorize masih Readiness/statrec = 2.
        // Jika ada 1 saja peserta sudah statrec 3 / sedang berjalan / selesai / status lain,
        // tombol harus disable agar Start All tidak mengubah sebagian data saja.
        const btnStart = document.getElementById('btnStartAll');
        if (btnStart) {
            const totalRows = rows.length;
            const canStartAll = totalRows > 0 && totalEligibleForStart === totalRows;

            btnStart.disabled = !canStartAll;
            btnStart.title = canStartAll
                ? `Start All untuk semua ${totalRows} peserta Readiness`
                : `Start All nonaktif: semua peserta harus statrec 2 / Readiness. Saat ini hanya ${totalEligibleForStart} dari ${totalRows} peserta yang Readiness.`;
        }

        // 2. Logic Pause/Resume All:
        // If some are active, show "Pause All". If everyone is suspended, show "Resume All".
        const btnPR = document.getElementById('btnPauseResumeAll');
        if (btnPR) {
            const span = btnPR.querySelector('span');
            const icon = btnPR.querySelector('i');

            if (totalActive > 0) {
                span.innerText = 'Pause All';
                icon.className = 'fas fa-pause mr-1.5';
                btnPR.className = 'bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm';
            } else if (totalSuspended > 0) {
                span.innerText = 'Resume All';
                icon.className = 'fas fa-play mr-1.5';
                btnPR.className = 'bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm';
            } else {
                // No one in progress
                span.innerText = 'Pause All';
                icon.className = 'fas fa-pause mr-1.5';
                btnPR.className = 'bg-gray-400 cursor-not-allowed text-white px-4 py-2 rounded-md text-xs font-medium transition shadow-sm';
            }
        }
    }


    document.querySelectorAll('.dropdown-item').forEach(item => {
    item.addEventListener('click', function() {
        selectedDate = this.getAttribute('data-date');
        selectedAdmin = this.getAttribute('data-value');
        selectedMonitoringMode = this.getAttribute('data-monitoring-mode') || 'online';

        const realDateInput = document.getElementById('realDateInput');
        const selectedAdminCode = document.getElementById('selectedAdminCode');
        const btnText = document.getElementById('dropdownBtnText');

        if (realDateInput) realDateInput.value = selectedDate;
        if (selectedAdminCode) selectedAdminCode.value = selectedAdmin;
        if (btnText) btnText.textContent = 'Admin No : ' + this.getAttribute('data-label');

        console.log('Selected:', selectedDate, selectedAdmin);
    });
});

    let selectedDate = '<?php echo $_GET['date'] ?? '' ?>';
    let selectedAdmin = '<?php echo $_GET['admin'] ?? '' ?>';
    let selectedMonitoringMode = <?php echo json_encode($currentMonitoringMode) ?>;

    function openMonitoring() {
        if (!selectedDate || !selectedAdmin) {
            alert('Pilih jadwal dulu!');
            return;
        }
        const endpoint = selectedMonitoringMode === 'hybrid' ? 'monitoring_hybrid.php' : 'monitoring.php';
        const url = `${endpoint}?date=${encodeURIComponent(selectedDate)}&admin=${encodeURIComponent(selectedAdmin)}&monitoring_mode=${encodeURIComponent(selectedMonitoringMode)}`;
        window.open(url, '_blank');
    }

    // Tombol Masuk Ruang New Tab
    function openRuang() {
        const selected = document.querySelector('.participant-row.selected');

        if (!selected) {
            alert('Pilih peserta dulu!');
            return;
        }

        const id = selected.getAttribute('data-auth-id');

        window.open(`ruang.php?id=${id}`, '_blank');
    }


    // Disable tombol pada kolom Aksi jika Sisa Waktu sudah Selesai
    function disableActionButtonsWhenFinished() {
        document.querySelectorAll('.participant-row').forEach(row => {
            const timerEl = row.querySelector('.timer-countdown');
            if (!timerEl) return;

            const status = String(row.dataset.status || row.dataset.statrec || '').trim().toLowerCase();
            const isFinishedByTimer = status === '7' || timerEl.dataset.finished === 'true';

            // Kolom Aksi adalah kolom terakhir pada baris peserta
            const actionCell = row.querySelector('td:last-child');
            if (!actionCell) return;

            actionCell.querySelectorAll('button').forEach(btn => {
                btn.disabled = isFinishedByTimer;
                btn.classList.toggle('opacity-40', isFinishedByTimer);
                btn.classList.toggle('cursor-not-allowed', isFinishedByTimer);
                btn.classList.toggle('pointer-events-none', isFinishedByTimer);
                btn.title = isFinishedByTimer ? 'Peserta sudah selesai. Aksi tidak tersedia.' : '';
            });

            actionCell.querySelectorAll('a').forEach(link => {
                if (isFinishedByTimer) {
                    if (!link.dataset.originalHref && link.getAttribute('href')) {
                        link.dataset.originalHref = link.getAttribute('href');
                    }
                    if (!link.dataset.originalOnclick && link.getAttribute('onclick')) {
                        link.dataset.originalOnclick = link.getAttribute('onclick');
                    }
                    link.removeAttribute('href');
                    link.removeAttribute('onclick');
                    link.setAttribute('aria-disabled', 'true');
                    link.title = 'Peserta sudah selesai. Aksi tidak tersedia.';
                } else {
                    if (link.dataset.originalHref) link.setAttribute('href', link.dataset.originalHref);
                    if (link.dataset.originalOnclick) link.setAttribute('onclick', link.dataset.originalOnclick);
                    link.removeAttribute('aria-disabled');
                    link.title = '';
                }

                link.classList.toggle('opacity-40', isFinishedByTimer);
                link.classList.toggle('cursor-not-allowed', isFinishedByTimer);
                link.classList.toggle('pointer-events-none', isFinishedByTimer);
            });
        });
    }


    function controlTimer(action, userId) {
        const row = document.querySelector(`[data-auth-id="${userId}"]`);
        const timerEl = row?.querySelector('.timer-countdown'); // Ambil elemen timernya
        const timerValueEl = timerEl?.querySelector('.timer-value');
        const name = row?.dataset.name || userId;
        const status = String(row?.dataset?.status || row?.dataset?.statrec || '').trim().toLowerCase();
        const isFinishedByTimer = status === '7' || timerEl?.dataset.finished === 'true';

        if (timerEl && isFinishedByTimer) {
            showToast(`${name} sudah selesai. Tombol aksi tidak dapat digunakan.`);
            disableActionButtonsWhenFinished();
            return;
        }

        const wasSuspended = parseInt(row?.dataset?.keSuspend || row?.dataset?.suspend || 0, 10) === 1;

        fetch('timer_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    // DEBUG ONLY: 'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                // DEBUG ONLY: body: `action=${action}&user_id=${userId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
                body: `action=${action}&user_id=${userId}`
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    // Endpoint lama: action pause adalah toggle Suspend <-> Resume.
                    if (timerEl) {
                        if (action === 'play') {
                            timerEl.dataset.active = 'true';
                            timerEl.dataset.finished = 'false';
                            row.dataset.status = '3';
                            row.dataset.keSuspend = '0';
                        }
                        if (action === 'pause') {
                            const newSuspendValue = wasSuspended ? 0 : 1;
                            row.dataset.keSuspend = String(newSuspendValue);
                            timerEl.dataset.active = newSuspendValue === 1 ? 'false' : 'true';
                            timerEl.dataset.finished = 'false';
                        }
                        if (action === 'resume') {
                            timerEl.dataset.active = 'true';
                            timerEl.dataset.finished = 'false';
                            row.dataset.keSuspend = '0';
                            if (['0', '1', '2', ''].includes(row.dataset.status || '')) {
                                row.dataset.status = '3';
                            }
                        }
                        if (action === 'stop') {
                            timerEl.dataset.active = 'false';
                            timerEl.dataset.finished = 'true';
                            row.dataset.status = '7';
                            row.dataset.keSuspend = '1';
                            if (timerValueEl) timerValueEl.innerText = 'Selesai';
                        }
                        repairTimerCellIfStarted(row);
                        updateRowStatusVisual(row);
                        updateRowPauseResumeButton(row);
                    }
                    const logAction = (action === 'pause' && wasSuspended) ? 'RESUME' : action.toUpperCase();
                    addLog(name, logAction);
                    showToast(`${name} → ${logAction}`);

                    // Refresh global buttons state dan tarik ulang data DB supaya status/timer tetap realtime.
                    syncTimersFromDatabase();
                    updateGlobalButtonsState();
                    disableActionButtonsWhenFinished();
                } else {
                    alert(res.message);
                }
            });
    }

    // ARRAY PENYIMPANAN LOG AKTIVITAS (Real-Time Memory)
    let activityLogs = [];

    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'bg-gray-800 border-l-4 border-brand-primary text-white px-4 py-4 rounded shadow-lg text-xs transform transition-all duration-300 translate-x-full opacity-0 flex items-center gap-3 pointer-events-auto';
        toast.innerHTML = `<i class="fas fa-bell text-brand-primary"></i> <div>${message}</div>`;
        document.getElementById('toastContainer').appendChild(toast);

        setTimeout(() => toast.classList.remove('translate-x-full', 'opacity-0'), 10);
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    function addLog(name, statusText) {
        const colorMap = {
            START: 'text-green-600',
            PAUSE: 'text-yellow-600',
            STOP: 'text-red-600'
        };
        const time = new Date().toLocaleTimeString('id-ID');
        activityLogs.unshift({
            time,
            name,
            statusText
        });

        if (activityLogs.length > 100) activityLogs.pop();

        const container = document.getElementById('activityLogContainer');
        if (container) {
            container.innerHTML = activityLogs.map(log => `
                <div class="border-b border-gray-100 py-2 hover:bg-gray-50 px-2 transition rounded">
                    <div class="text-xs px-3 py-1.5 text-gray-400 font-mono mb-0.5"><i class="far fa-clock mr-1"></i>${log.time}</div>
                    <div class="text-xs text-gray-700"><b>${log.name}</b> <i class="fas fa-arrow-right text-[8px] mx-1 text-gray-400"></i> <span class="${colorMap[log.statusText] || 'text-brand-primary'} font-bold">${log.statusText}</span></div>
                </div>
            `).join('');
        }
    }

    // SCRIPT INJEKSI HEADER & AJAX POLLING REALTIME
    <?php if ($is_in_room && ! $fatal_error): ?>
        // 1. Render SPV Profile
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('header');
            if (header) {
                const spvProfileHtml = `
                <div class="flex items-center gap-3 pr-2 ml-auto" id="spv-focus-profile">
                    <div class="text-right hidden sm:block">
                        <p class="text-[9px] text-gray-400 uppercase tracking-widest font-bold mb-0.5">SPV Bertugas</p>
                        <h2 class="text-sm font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars($spv['name']) ?></h2>
                    </div>
                    <div class="relative">
                        <img src="<?php echo htmlspecialchars($spv['photo_url']) ?>" alt="SPV" class="w-9 h-9 rounded-full border-2 border-brand-primary shadow-sm bg-white">
                        <span class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full bg-green-500 ring-2 ring-white"></span>
                    </div>
                </div>
            `;
                header.insertAdjacentHTML('beforeend', spvProfileHtml);
            }

            // 2. Real-Time AJAX Engine
            const isFinished = <?php echo $is_finished ? 'true' : 'false' ?>;
const admId = <?php echo json_encode($current_admin_rec_id) ?>;
const subId = <?php echo json_encode($current_sub_admin_id) ?>;
const admNo = <?php echo json_encode($current_admin_no_for_decrypt ?? $current_admin_no) ?>;
const testType = <?php echo json_encode($current_test_type) ?>;


            function applyTimerSnapshot(items) {
                if (!Array.isArray(items)) return;

                items.forEach(item => {
                    const authId = item.authorize;
                    if (!authId) return;

                    const row = document.querySelector(`[data-auth-id="${CSS.escape(authId)}"]`);
                    if (!row) return;

                    const timerEl = row.querySelector('.timer-countdown');
                    if (!timerEl) return;

                    let timerValueEl = timerEl.querySelector('.timer-value');
                    if (!timerValueEl) {
                        timerValueEl = document.createElement('span');
                        timerValueEl.className = 'timer-value';
                        timerEl.innerHTML = '';
                        timerEl.appendChild(timerValueEl);
                    }

                    const remaining = parseInt(item.remaining_seconds ?? -1, 10);
                    const statrec = String(item.statrec ?? row.dataset.status ?? '');
                    const keSuspend = String(item.ke_suspend ?? row.dataset.keSuspend ?? '0');
                    const isFinishedByDb = item.finished === true || statrec === '7';

                    row.dataset.status = statrec;
                    row.dataset.statrec = statrec;
                    row.dataset.keSuspend = keSuspend;
                    updateRowStatusVisual(row, item.status_text || null);
                    updateRowPauseResumeButton(row);

                    if (isFinishedByDb) {
                        timerEl.dataset.remainingSeconds = '0';
                        timerEl.dataset.active = 'false';
                        timerEl.dataset.finished = 'true';
                        timerValueEl.innerText = 'Selesai';

                        const sessionLabelEl = timerEl.querySelector('.timer-session-label');
                        if (sessionLabelEl) sessionLabelEl.remove();
                        updateRowStatusVisual(row, item.status_text || null);
                        updateRowPauseResumeButton(row);
                        return;
                    }

                    if (!Number.isNaN(remaining) && remaining > 0) {
                        timerEl.dataset.remainingSeconds = String(remaining);
                        timerEl.dataset.finished = 'false';
                        timerEl.dataset.active = (item.active === true && keSuspend !== '1') ? 'true' : 'false';
                        timerValueEl.innerText = formatSecondsToHHMMSS(remaining);

                        if (!timerEl.querySelector('.timer-session-label')) {
                            const session = detectSessionCode(row);
                            const badge = document.createElement('span');
                            badge.className = 'timer-session-label ' + (session === 'R' ? 'reading' : 'listening');
                            badge.innerText = session;
                            timerEl.prepend(badge);
                        }
                    } else if (!Number.isNaN(remaining) && remaining <= 0) {
                        timerEl.dataset.remainingSeconds = '0';
                        timerEl.dataset.finished = 'false';
                        timerEl.dataset.active = 'false';
                        timerValueEl.innerText = '00:00:00';
                    }
                });

                updateGlobalButtonsState();
                disableActionButtonsWhenFinished();
            }

            window.syncTimersFromDatabase = function syncTimersFromDatabase() {
                const params = new URLSearchParams(window.location.search);
                params.set('ajax_timer_sync', '1');

                // Fallback untuk URL lama/non-room key.
                if (!params.get('admin') && admId && subId) {
                    params.set('admin_rec_id', admId);
                    params.set('sub_admin_id', subId);
                }

                return fetch(`monitoring.php?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res && res.success) {
                            applyTimerSnapshot(res.items || []);
                        }
                    })
                    .catch(err => console.error('Timer sync gagal:', err));
            };

            // Ambil nilai timer aktual dari database segera setelah F5 / load.
            syncTimersFromDatabase();

if (!isFinished) {
        let isRealtimeLoading = false;

        window.reloadParticipantTableFromDatabase = function reloadParticipantTableFromDatabase() {
        if (isRealtimeLoading) return;

        isRealtimeLoading = true;

        const tbody = document.getElementById('tableBody');
        if (!tbody) {
            isRealtimeLoading = false;
            return;
        }

        // Simpan posisi pagination saat ini agar tidak reset ke page 1.
        const oldCurrentPage = typeof currentPage !== 'undefined' ? currentPage : 1;
        const oldRowsPerPage = typeof rowsPerPage !== 'undefined' ? rowsPerPage : 25;

        // Simpan status lama untuk notifikasi perubahan.
        const oldStatuses = {};
        document.querySelectorAll('.participant-row').forEach(row => {
            const id = row.getAttribute('data-auth-id');
            const status = row.getAttribute('data-status');

            if (id) {
                oldStatuses[id] = status;
            }
        });

        const params = new URLSearchParams(window.location.search);
        params.set('ajax_realtime', '1');
        params.set('admin_rec_id', admId);
        params.set('sub_admin_id', subId);
        params.set('admin_no', admNo || '');
        params.set('test_type', testType || '');
        params.set('finished', '0');

        fetch(`monitoring.php?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
            .then(res => res.text())
            .then(html => {
                if (!html || !html.includes('participant-row')) {
                    return;
                }

                tbody.innerHTML = html;

                // Cek perubahan status setelah data baru masuk.
                document.querySelectorAll('.participant-row').forEach(row => {
                    const id = row.getAttribute('data-auth-id');
                    const newStatus = row.getAttribute('data-status');
                    const newStatusText = row.getAttribute('data-status-text');
                    const name = row.getAttribute('data-name');

                    if (id && oldStatuses[id] && oldStatuses[id] !== newStatus) {
                        if (typeof showToast === 'function') {
                            showToast(`Status <b>${name}</b>: ${newStatusText}`);
                        }

                        if (typeof addLog === 'function') {
                            addLog(name, newStatusText);
                        }
                    }
                });

                // Kembalikan pagination supaya tabel tidak reset.
                if (typeof currentPage !== 'undefined') currentPage = oldCurrentPage;
                if (typeof rowsPerPage !== 'undefined') rowsPerPage = oldRowsPerPage;

                if (typeof repairAllTimerCells === 'function') repairAllTimerCells();
                if (typeof updateAllRowsVisualState === 'function') updateAllRowsVisualState();
                if (typeof syncTimersFromDatabase === 'function') syncTimersFromDatabase();
                if (typeof updateGlobalButtonsState === 'function') updateGlobalButtonsState();
                if (typeof disableActionButtonsWhenFinished === 'function') disableActionButtonsWhenFinished();
                if (typeof refreshPagination === 'function') refreshPagination();

                // Kalau modal Collect Data sedang terbuka, list-nya ikut realtime.
                const collectModal = document.getElementById('collectDataModal');
                const filterSelect = document.getElementById('filterStatusCollect');

                if (
                    collectModal &&
                    !collectModal.classList.contains('hidden') &&
                    filterSelect &&
                    typeof renderCollectChecklist === 'function'
                ) {
                    renderCollectChecklist(filterSelect.value);
                }
            })
            .catch(err => console.error('Realtime table gagal:', err))
            .finally(() => {
                isRealtimeLoading = false;
            });
    }

    reloadParticipantTableFromDatabase();

    setInterval(reloadParticipantTableFromDatabase, 5000);
}

            function formatSecondsToHHMMSS(totalSecs) {
                totalSecs = Math.max(0, parseInt(totalSecs || 0));

                if (totalSecs <= 0) {
                    return '00:00:00';
                }

                const h = Math.floor(totalSecs / 3600);
                const m = Math.floor((totalSecs % 3600) / 60);
                const s = totalSecs % 60;

                return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
            }

            // 3. Visual Timer Countdown
            repairAllTimerCells();
            updateAllRowsVisualState();
            syncTimersFromDatabase();
            setInterval(syncTimersFromDatabase, 5000);
            updateGlobalButtonsState();
            disableActionButtonsWhenFinished();

            if (!isFinished) {
                setInterval(() => {
                    document.querySelectorAll('.timer-countdown').forEach(el => {
                        if (el.dataset.active !== 'true') return;

                        const timerValueEl = el.querySelector('.timer-value');
                        if (!timerValueEl) return;

                        let totalSecs = parseInt(el.dataset.remainingSeconds || 0);

                        if (totalSecs > 0) {
                            totalSecs--;
                            el.dataset.remainingSeconds = totalSecs;
                            timerValueEl.innerText = formatSecondsToHHMMSS(totalSecs);
                        } else {
                            el.dataset.remainingSeconds = 0;
                            el.dataset.active = 'false';
                            el.dataset.finished = 'false';
                            timerValueEl.innerText = '00:00:00';
                        }
                    });
                    disableActionButtonsWhenFinished();
                }, 1000);
            }
        });
    <?php endif; ?>

    // FUNGSI MODAL
    function toggleModal(modalID) {
        document.getElementById(modalID).classList.toggle('hidden');
    }

    function switchTab(tab) {
        const cOn = document.getElementById('tabContentOnline'),
            cOff = document.getElementById('tabContentOffline');
        const bOn = document.getElementById('tabBtnOnline'),
            bOff = document.getElementById('tabBtnOffline');
        if (tab === 'online') {
            cOn.classList.remove('hidden');
            cOff.classList.add('hidden');
            bOn.classList.add('border-brand-primary', 'text-brand-primary');
            bOn.classList.remove('border-transparent', 'text-gray-500');
            bOff.classList.remove('border-brand-primary', 'text-brand-primary');
            bOff.classList.add('border-transparent', 'text-gray-500');
        } else {
            cOff.classList.remove('hidden');
            cOn.classList.add('hidden');
            bOff.classList.add('border-brand-primary', 'text-brand-primary');
            bOff.classList.remove('border-transparent', 'text-gray-500');
            bOn.classList.remove('border-brand-primary', 'text-brand-primary');
            bOn.classList.add('border-transparent', 'text-gray-500');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {

        // 1. Inisialisasi Flatpickr
        const activeDates = <?php echo $active_dates_json ?>;
        const dateInputUI = document.getElementById('uiDatePicker');
        const realDateInput = document.getElementById('realDateInput');
        const isLocked = <?php echo $is_locked ? 'true' : 'false' ?>;

        if (dateInputUI && !isLocked) {
            flatpickr(dateInputUI, {
                altInput: true,
                altFormat: "d/m/Y",
                dateFormat: "Y-m-d",
                allowInput: true,
                defaultDate: "<?php echo htmlspecialchars($selected_date) ?>",
                parseDate: function(datestr, format) {
                    if (datestr.includes('/')) {
                        const parts = datestr.split('/');
                        if (parts.length === 3) return new Date(parts[2], parts[1] - 1, parts[0]);
                    }
                    return flatpickr.parseDate(datestr, format);
                },
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    const localDate = new Date(dayElem.dateObj.getTime() - (dayElem.dateObj.getTimezoneOffset() * 60000));
                    const dateString = localDate.toISOString().split('T')[0];
                    if (activeDates.includes(dateString)) dayElem.classList.add('has-task');
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        const newDate = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('realDateInput').value = newDate;
                        document.getElementById('adminSelectionForm').submit();
                    }
                }
            });

            if (dateInputUI.nextElementSibling) {
                dateInputUI.nextElementSibling.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        setTimeout(() => {
                            const realVal = document.getElementById('realDateInput').value;
                            if (realVal) document.getElementById('adminSelectionForm').submit();
                        }, 200);
                    }
                });
            }
        }

        // 2. Logika Custom Dropdown & Fitur Live Search
        const btn = document.getElementById('dropdownBtn');
        const menu = document.getElementById('dropdownMenu');
        const icon = document.getElementById('dropdownIcon');
        const input = document.getElementById('selectedAdminCode');
        const btnText = document.getElementById('dropdownBtnText');
        const btnMasuk = document.getElementById('btnMasukRuang');
        const searchInput = document.getElementById('dropdownSearch');

        if (btn && !btn.disabled) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                menu.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
                if (!menu.classList.contains('hidden') && searchInput) {
                    setTimeout(() => searchInput.focus(), 50);
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                let count = 0;
                document.querySelectorAll('.dropdown-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        item.style.display = '';
                        count++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                let noRes = document.getElementById('noSearchResult');
                if (!noRes) {
                    noRes = document.createElement('div');
                    noRes.id = 'noSearchResult';
                    noRes.className = 'p-4 text-xs text-gray-500 text-center italic';
                    noRes.textContent = 'Hasil pencarian tidak ada.';
                    document.getElementById('dropdownListContainer').appendChild(noRes);
                }
                noRes.style.display = count === 0 ? 'block' : 'none';
            });
        }

        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                input.value = this.getAttribute('data-value');
                selectedAdmin = this.getAttribute('data-value');
                selectedDate = this.getAttribute('data-date') || selectedDate;
                selectedMonitoringMode = this.getAttribute('data-monitoring-mode') || 'online';
                const isRoomView = <?php echo $is_in_room ? 'true' : 'false' ?>;
                btnText.textContent = isRoomView ? this.getAttribute('data-label') : "Admin No : " + this.getAttribute('data-label');

                menu.classList.add('hidden');
                icon.classList.remove('rotate-180');

                if (searchInput) {
                    searchInput.value = '';
                    document.querySelectorAll('.dropdown-item').forEach(i => i.style.display = '');
                    let noRes = document.getElementById('noSearchResult');
                    if (noRes) noRes.style.display = 'none';
                }

                document.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('bg-blue-50', 'text-brand-primary', 'font-semibold'));
                this.classList.add('bg-blue-50', 'text-brand-primary', 'font-semibold');

                if (btnMasuk) btnMasuk.disabled = false;
                if (isRoomView && <?php echo $is_finished ? 'true' : 'false' ?>) document.getElementById('adminSelectionForm').submit();
            });
        });

        document.addEventListener('click', function(event) {
            const isInside = document.getElementById('customSelectWrapper')?.contains(event.target);
            if (!isInside && menu && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
                if (icon) icon.classList.remove('rotate-180');

                if (searchInput) {
                    searchInput.value = '';
                    document.querySelectorAll('.dropdown-item').forEach(i => i.style.display = '');
                    let noRes = document.getElementById('noSearchResult');
                    if (noRes) noRes.style.display = 'none';
                }
            }
        });

        // 3. Logika JS Table Pagination & Limit
        let currentPage = 1;
        let rowsPerPage = parseInt(document.getElementById('tableLimit')?.value || 25);

        window.refreshPagination = function() {
            const tableRows = document.querySelectorAll('.participant-row');
            const totalRows = tableRows.length;
            const paginationWrapper = document.getElementById('paginationWrapper');

            if (totalRows > 0) {
                if (paginationWrapper) paginationWrapper.style.display = 'flex';
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                tableRows.forEach((row, index) => {
                    row.style.display = (index >= start && index < end) ? '' : 'none';
                });

                document.getElementById('pageInfo').textContent = `Showing ${start + 1} to ${Math.min(end, totalRows)} of ${totalRows} entries`;
                renderPaginationButtons(totalRows);
            } else {
                if (paginationWrapper) paginationWrapper.style.display = 'none';
            }
        };

        function renderPaginationButtons(totalRows) {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const paginationBtns = document.getElementById('paginationBtns');
            paginationBtns.innerHTML = '';

            const btnPrev = document.createElement('button');
            btnPrev.className = `px-2.5 py-1 border border-gray-200 rounded text-xs font-medium ${currentPage === 1 ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-100 hover:text-brand-primary'}`;
            btnPrev.textContent = 'Prev';
            btnPrev.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    refreshPagination();
                }
            };
            paginationBtns.appendChild(btnPrev);

            for (let i = 1; i <= totalPages; i++) {
                const btnPage = document.createElement('button');
                btnPage.className = `px-2.5 py-1 border border-gray-200 rounded text-xs font-medium ${currentPage === i ? 'bg-brand-primary text-white border-brand-primary' : 'bg-white text-gray-600 hover:bg-gray-100 hover:text-brand-primary'}`;
                btnPage.textContent = i;
                btnPage.onclick = () => {
                    currentPage = i;
                    refreshPagination();
                };
                paginationBtns.appendChild(btnPage);
            }

            const btnNext = document.createElement('button');
            btnNext.className = `px-2.5 py-1 border border-gray-200 rounded text-xs font-medium ${currentPage === totalPages ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-100 hover:text-brand-primary'}`;
            btnNext.textContent = 'Next';
            btnNext.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    refreshPagination();
                }
            };
            paginationBtns.appendChild(btnNext);
        }

        const limitSelect = document.getElementById('tableLimit');
        if (limitSelect) {
            limitSelect.addEventListener('change', function() {
                rowsPerPage = parseInt(this.value);
                currentPage = 1;
                refreshPagination();
            });
        }

        // Initial update for global buttons
        updateGlobalButtonsState();
        disableActionButtonsWhenFinished();
    });

    // =========================================================================
    // BERITA ACARA MODAL FUNCTIONS
    // =========================================================================

    // Mapping hari Inggris ke Indonesia
    const hariMap = {
        'Sunday': 'Minggu', 'Monday': 'Senin', 'Tuesday': 'Selasa',
        'Wednesday': 'Rabu', 'Thursday': 'Kamis', 'Friday': 'Jumat', 'Saturday': 'Sabtu'
    };

    function openBeritaAcaraModal() {
        // 1. Populate Info dari PHP (sudah tersedia di page)
        const testDay = <?php echo json_encode($test_info['test_day'] ?? '') ?>;
        const testDate = <?php echo json_encode($test_info['test_date'] ?? '') ?>;
        const testTime = <?php echo json_encode($test_info['test_time'] ?? '') ?>;
        const clientNm = <?php echo json_encode($test_info['client_nm'] ?? '-') ?>;
        const defaultLokasiTes = <?php echo json_encode($test_info['test_site'] ?? $test_info['location'] ?? 'RUN ITC - Test Center') ?>;
        const defaultKota = <?php echo json_encode($test_info['city'] ?? '') ?>;
        const defaultSupervisor = <?php echo json_encode($user_name ?? ($spv['name'] ?? '')) ?>;
        const kejadianCounter = document.getElementById('ba_kejadian_counter');
        const kejadianText = document.getElementById('ba_kejadian_text');

        if (kejadianText && kejadianCounter) {
            kejadianCounter.textContent = kejadianText.value.length;
        }

        document.getElementById('beritaAcaraModal').classList.remove('hidden');
        document.getElementById('ba_hari').textContent = hariMap[testDay] || testDay;
        document.getElementById('ba_tanggal').textContent = testDate;

        // Jam Test menggunakan input time (default jam sekarang atau jam test)
        const baJamInput = document.getElementById('ba_jam_input');
        if (baJamInput && (!baJamInput.value || baJamInput.value === '')) {
            const now = new Date();
            const currentHHMM = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            baJamInput.value = testTime || currentHHMM;
        }

        document.getElementById('ba_klien').textContent = clientNm;

        const baLokasiInput = document.getElementById('ba_lokasi_input');
        const baKotaInput = document.getElementById('ba_kota_input');
        const baSupervisorInput = document.getElementById('ba_supervisor_input');

        if (baLokasiInput && !baLokasiInput.value.trim()) {
            baLokasiInput.value = defaultLokasiTes || '';
        }

        if (baKotaInput && !baKotaInput.value.trim()) {
            baKotaInput.value = defaultKota || '';
        }

        if (baSupervisorInput && !baSupervisorInput.value.trim()) {
            baSupervisorInput.value = defaultSupervisor || '';
        }

        // 2. Hitung peserta dari tabel
        const allRows = document.querySelectorAll('.participant-row');
        let totalHadir = 0;
        let totalSelesai = 0;

        let pesertaSelesai = [];
        let pesertaTerminate = [];

        allRows.forEach(row => {
            const status = String(row.dataset.status || row.getAttribute('data-status') || '').trim().toLowerCase();
            const name = row.getAttribute('data-name');
            const authId = row.getAttribute('data-auth-id');
            const timerEl = row.querySelector('.timer-countdown');
            const timerValueEl = timerEl?.querySelector('.timer-value');
            const sisaWaktu = timerValueEl ? timerValueEl.innerText.trim() : (timerEl ? timerEl.innerText.trim() : '-');

            if (['0', '1', '2', '3'].includes(status)) {
                // Belum masuk/Raw
            } else {
                // Status 4 keatas dianggap Hadir
                totalHadir++;
            }

            // Selesai & Terminate (mengambil data untuk list)
            // statrec 7 adalah End of Test, jadi masuk peserta selesai normal.
            // Peserta terminate hanya yang di-stop/ditandai bermasalah oleh operator.
            if (isRowTerminated(row)) {
                pesertaTerminate.push({ name, authId, sisaWaktu });
            } else if (['7', '8', '9', 'c'].includes(status)) {
                totalSelesai++;
                pesertaSelesai.push({ name, authId, sisaWaktu });
            } else if (['4', '5', '6'].includes(status)) {
                // Masih ujian
            }
        });

        document.getElementById('ba_jumlah_peserta_hadir').textContent = totalHadir + ' Peserta';
        document.getElementById('ba_peserta_selesai_count').textContent = totalSelesai + ' Peserta';

        const tidakHadirInput = document.getElementById('ba_peserta_tidak_hadir_input');
        if (tidakHadirInput && tidakHadirInput.value.trim() === '') {
            tidakHadirInput.value = '';
        }

        // 3. Render list peserta selesai
      const listSelesai = document.getElementById('ba_list_selesai');
document.getElementById('ba_count_selesai').textContent = pesertaSelesai.length;

if (pesertaSelesai.length > 0) {
    listSelesai.innerHTML = pesertaSelesai.map((p, i) => `
        <div class="ba-item-selesai flex flex-col gap-1.5 p-3 bg-green-50/50 rounded-lg border border-green-100" data-auth="${p.authId}" data-name="${p.name}">

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <input type="checkbox"
                           class="ba-selesai-check w-4 h-4 text-green-600 focus:ring-green-500 rounded shrink-0">

                    <span class="text-[10px] font-bold text-white bg-green-600 w-5 h-5 rounded-full flex items-center justify-center shrink-0">
                        ${i + 1}
                    </span>

                    <span class="text-xs font-bold text-gray-800 uppercase truncate">
                        ${p.name}
                    </span>
                </div>

                <span class="text-[10px] text-gray-400 font-mono shrink-0">
                    ${p.authId}
                </span>
            </div>

                    <input type="text" maxlength="100" disabled
                           placeholder="Centang peserta untuk mengisi catatan..."
                           class="ba-note-selesai w-full text-xs border border-gray-200 rounded-md px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500 bg-gray-100 text-gray-400 placeholder-gray-400"
                           data-auth="${p.authId}" data-name="${p.name}">
                </div>
    `).join('');

    document.querySelectorAll('.ba-item-selesai').forEach(item => {
        const checkbox = item.querySelector('.ba-selesai-check');
        const noteInput = item.querySelector('.ba-note-selesai');

        if (!checkbox || !noteInput) return;

        checkbox.addEventListener('change', function () {
            if (this.checked) {
                noteInput.required = true;
                noteInput.disabled = false;
                noteInput.classList.remove('bg-gray-100', 'text-gray-400');
                noteInput.classList.add('bg-white');
                noteInput.placeholder = 'Wajib diisi jika peserta dicentang...';
            } else {
                noteInput.required = false;
                noteInput.disabled = true;
                noteInput.value = '';
                noteInput.classList.remove('bg-white');
                noteInput.classList.add('bg-gray-100', 'text-gray-400');
                noteInput.placeholder = 'Tidak ikut dilaporkan';
            }
        });
    });
} else {
    listSelesai.innerHTML = '<div class="col-span-full text-xs text-gray-400 text-center italic py-4">Tidak ada peserta yang selesai.</div>';
}

const baSelesaiVisibleCount = document.getElementById('ba_selesai_visible_count');
const baSelesaiTotalCount = document.getElementById('ba_selesai_total_count');
const baSearchSelesai = document.getElementById('ba_search_selesai');

if (baSelesaiVisibleCount) baSelesaiVisibleCount.textContent = pesertaSelesai.length;
if (baSelesaiTotalCount) baSelesaiTotalCount.textContent = pesertaSelesai.length;
if (baSearchSelesai) baSearchSelesai.value = '';
setupSearchPesertaSelesai();

        // 4. Render list peserta terminated
        const listTerminate = document.getElementById('ba_list_terminate');
        document.getElementById('ba_count_terminate').textContent = pesertaTerminate.length;

        if (pesertaTerminate.length > 0) {
            listTerminate.innerHTML = pesertaTerminate.map((p, i) => `
                <div class="ba-item-terminate flex flex-col gap-1.5 p-3 bg-red-50/50 rounded-lg border border-red-100" data-auth="${p.authId}" data-name="${p.name}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-white bg-red-600 w-5 h-5 rounded-full flex items-center justify-center">${i+1}</span>
                            <span class="text-xs font-bold text-gray-800 uppercase">${p.name}</span>
                        </div>
                        <span class="text-[10px] text-gray-400 font-mono">${p.authId}</span>
                    </div>
                    <input type="text" maxlength="100" placeholder="Catatan berita acara terminasi..."
                           class="ba-note-terminate w-full text-xs border border-gray-200 rounded-md px-3 py-1.5 focus:ring-red-500 focus:border-red-500 bg-white placeholder-gray-400"
                           data-auth="${p.authId}" data-name="${p.name}">
                </div>
            `).join('');
        } else {
listTerminate.innerHTML = '<div class="col-span-full text-xs text-gray-400 text-center italic py-4">Tidak ada peserta yang di-terminate.</div>';        }

        renderAttendanceTable();

        // 5. Tampilkan modal
        document.getElementById('beritaAcaraModal').classList.remove('hidden');
    }

    function closeBeritaAcaraModal() {
        document.getElementById('beritaAcaraModal').classList.add('hidden');
    }

    function openAttendanceModal() {
        const modal = document.getElementById('attendanceModal');
        if (!modal) return;

        attendanceGenerationUnlocked = true;
        renderAttendanceTable();
        modal.classList.remove('hidden');
    }

    function closeAttendanceModal() {
        const modal = document.getElementById('attendanceModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    document.addEventListener('input', function(e) {
    if (e.target && e.target.id === 'ba_kejadian_text') {
        const counter = document.getElementById('ba_kejadian_counter');
        if (counter) {
            counter.textContent = e.target.value.length;
        }
    }
});

function setupSearchPesertaSelesai() {
    const searchInput = document.getElementById('ba_search_selesai');
    const visibleCount = document.getElementById('ba_selesai_visible_count');
    const items = document.querySelectorAll('.ba-item-selesai');

    if (!searchInput) return;

    searchInput.oninput = function () {
        const keyword = this.value.toLowerCase().trim();
        let totalVisible = 0;

        items.forEach(item => {
            const name = (item.getAttribute('data-name') || '').toLowerCase();
            const auth = (item.getAttribute('data-auth') || '').toLowerCase();

            const isMatch = name.includes(keyword) || auth.includes(keyword);

            if (isMatch) {
                item.classList.remove('hidden');
                totalVisible++;
            } else {
                item.classList.add('hidden');
            }
        });

        if (visibleCount) {
            visibleCount.textContent = totalVisible;
        }
    };
}

function getAttendanceDefaultStatus(row) {
    const status = String(row?.dataset?.status || row?.getAttribute?.('data-status') || '').trim().toLowerCase();

    if (['4', '5', '6', '7', '8', '9', 'c'].includes(status)) {
        return 'Hadir';
    }

    return 'Tidak Hadir';
}

function readParticipantJsonFromRow(row) {
    try {
        return JSON.parse(row.getAttribute('data-participant-json') || '{}');
    } catch (e) {
        return {};
    }
}

function buildAttendanceRowData(row, index) {
    const data = readParticipantJsonFromRow(row);
    const authcode = data.id || data.authorize || row.getAttribute('data-auth-id') || '-';
    const existing = attendanceState[authcode] || {};

    return {
        no: index + 1,
        authcode,
        name: existing.name || data.regnm || data.name || row.getAttribute('data-name') || '-',
        dob: data.dob || '-',
        idno: data.idno || data.nisn || '-',
        hpno: data.hpno || '-',
        attendance: existing.attendance || getAttendanceDefaultStatus(row),
        note: existing.note || '',
        groupcd: data.groupcd || '-',
        custom1: data.custom1 || '',
        custom2: data.custom2 || '',
        custom3: data.custom3 || '',
    };
}

function renderAttendanceTable() {
    const tbody = document.getElementById('attendanceTableBody');
    if (!tbody) return;

    const rows = Array.from(document.querySelectorAll('.participant-row'));

    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-gray-400 italic">Tidak ada data peserta.</td></tr>';
        updateAttendanceSummary([]);
        return;
    }

    const items = rows.map((row, index) => buildAttendanceRowData(row, index));

    tbody.innerHTML = items.map(item => `
        <tr class="attendance-row hover:bg-blue-50/40" data-auth="${escapeHtml(item.authcode)}">
            <td class="px-3 py-2 text-center font-bold text-gray-500">${item.no}</td>
            <td class="px-3 py-2 font-mono text-gray-700">${escapeHtml(item.authcode)}</td>
            <td class="px-3 py-2">
                <input type="text" value="${escapeHtml(item.name)}" data-field="name" maxlength="120" class="attendance-input w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs font-bold uppercase focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.dob)}</td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.idno)}</td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.hpno)}</td>
            <td class="px-3 py-2">
                <select data-field="attendance" class="attendance-input w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs font-bold focus:ring-blue-500 focus:border-blue-500 bg-white">
                    <option value="Hadir" ${item.attendance === 'Hadir' ? 'selected' : ''}>Hadir</option>
                    <option value="Tidak Hadir" ${item.attendance === 'Tidak Hadir' ? 'selected' : ''}>Tidak Hadir</option>
                    <option value="Reschedule" ${item.attendance === 'Reschedule' ? 'selected' : ''}>Reschedule</option>
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="text" value="${escapeHtml(item.note)}" data-field="note" maxlength="200" placeholder="Keterangan absensi..." class="attendance-input w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.groupcd)}</td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.custom1 || '-')}</td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.custom2 || '-')}</td>
            <td class="px-3 py-2 text-gray-700">${escapeHtml(item.custom3 || '-')}</td>
        </tr>
    `).join('');

    document.querySelectorAll('#attendanceTableBody .attendance-input').forEach(input => {
        input.addEventListener('input', syncAttendanceStateFromDom);
        input.addEventListener('change', syncAttendanceStateFromDom);
    });

    syncAttendanceStateFromDom();
}

function syncAttendanceStateFromDom() {
    document.querySelectorAll('#attendanceTableBody .attendance-row').forEach(row => {
        const auth = row.getAttribute('data-auth') || '';
        if (!auth) return;

        const get = (field) => row.querySelector(`[data-field="${field}"]`)?.value?.trim() || '';
        const cells = row.children;

        attendanceState[auth] = {
            authcode: auth,
            name: get('name'),
            dob: cells[3]?.textContent?.trim() || '-',
            idno: cells[4]?.textContent?.trim() || '-',
            hpno: cells[5]?.textContent?.trim() || '-',
            attendance: get('attendance') || 'Hadir',
            note: get('note'),
            groupcd: cells[8]?.textContent?.trim() || '-',
            custom1: cells[9]?.textContent?.trim() || '',
            custom2: cells[10]?.textContent?.trim() || '',
            custom3: cells[11]?.textContent?.trim() || '',
        };
    });

    updateAttendanceSummary(getAttendanceData());
}

function getAttendanceData() {
    syncAttendanceStateFromDomNoSummary();

    return Array.from(document.querySelectorAll('#attendanceTableBody .attendance-row')).map((row, index) => {
        const auth = row.getAttribute('data-auth') || '';
        const data = attendanceState[auth] || {};

        return {
            no: index + 1,
            authcode: data.authcode || auth || '-',
            name: data.name || '-',
            dob: data.dob || '-',
            idno: data.idno || '-',
            hpno: data.hpno || '-',
            attendance: data.attendance || 'Hadir',
            note: data.note || '',
            groupcd: data.groupcd || '-',
            custom1: data.custom1 || '',
            custom2: data.custom2 || '',
            custom3: data.custom3 || '',
        };
    });
}

function syncAttendanceStateFromDomNoSummary() {
    document.querySelectorAll('#attendanceTableBody .attendance-row').forEach(row => {
        const auth = row.getAttribute('data-auth') || '';
        if (!auth) return;

        const get = (field) => row.querySelector(`[data-field="${field}"]`)?.value?.trim() || '';
        const cells = row.children;

        attendanceState[auth] = {
            authcode: auth,
            name: get('name'),
            dob: cells[3]?.textContent?.trim() || '-',
            idno: cells[4]?.textContent?.trim() || '-',
            hpno: cells[5]?.textContent?.trim() || '-',
            attendance: get('attendance') || 'Hadir',
            note: get('note'),
            groupcd: cells[8]?.textContent?.trim() || '-',
            custom1: cells[9]?.textContent?.trim() || '',
            custom2: cells[10]?.textContent?.trim() || '',
            custom3: cells[11]?.textContent?.trim() || '',
        };
    });
}

function updateAttendanceSummary(items) {
    const summary = document.getElementById('attendanceSummary');
    if (!summary) return;

    const total = items.length;
    const hadir = items.filter(item => item.attendance === 'Hadir').length;
    const tidakHadir = items.filter(item => item.attendance === 'Tidak Hadir').length;
    const reschedule = items.filter(item => item.attendance === 'Reschedule').length;

    summary.textContent = `${total} Total | ${hadir} Hadir | ${tidakHadir} Tidak Hadir | ${reschedule} Reschedule`;
}

function unlockAttendanceGeneration() {
    attendanceGenerationUnlocked = true;

    ['btnDownloadAttendanceExcel', 'btnDownloadAttendancePDF'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;

        btn.disabled = false;
        btn.classList.remove('bg-gray-200', 'text-gray-500', 'disabled:cursor-not-allowed');
        btn.classList.add(id.includes('Excel') ? 'bg-green-600' : 'bg-blue-600', 'hover:opacity-90', 'text-white');
    });
}

function assertAttendanceCanGenerate() {
    const items = getAttendanceData();
    if (items.length === 0) {
        throw new Error('Data absensi kosong.');
    }

    return items;
}

function getAttendanceMeta() {
    const startTime = document.getElementById('ba_jam_input')?.value || <?php echo json_encode($test_info['test_time'] ?? '') ?> || '';
    const fallbackInstitution = <?php echo json_encode($test_info['client_nm'] ?? '-') ?>;
    const fallbackTestDate = <?php echo json_encode($test_info['test_date'] ?? '-') ?>;
    const institutionText = (document.getElementById('ba_klien')?.textContent || '').trim();
    const testDateText = (document.getElementById('ba_tanggal')?.textContent || '').trim();

    return {
        institution: institutionText && institutionText !== '-' ? institutionText : fallbackInstitution,
        testDate: testDateText && testDateText !== '-' ? testDateText : fallbackTestDate,
        testTime: typeof formatAttendanceTimeRange === 'function' ? formatAttendanceTimeRange(startTime) : startTime,
        testAdministrator: (document.getElementById('ba_supervisor_input')?.value || <?php echo json_encode($user_name ?? ($spv['name'] ?? '')) ?> || '-').trim(),
        adminCode: <?php echo json_encode($test_info['admin_code'] ?? 'CBT') ?>,
    };
}

function formatAttendanceTimeRange(startTime) {
    if (!startTime) return '-';

    const parts = startTime.split(':');
    const h = parseInt(parts[0] || '0', 10);
    const m = parseInt(parts[1] || '0', 10);

    if (!Number.isFinite(h) || !Number.isFinite(m)) return startTime + ' WIB';

    const start = new Date();
    start.setHours(h, m, 0, 0);
    const end = new Date(start.getTime() + (150 * 60 * 1000));
    const fmt = d => `${String(d.getHours()).padStart(2, '0')}.${String(d.getMinutes()).padStart(2, '0')}`;

    return `${fmt(start)} - ${fmt(end)} WIB`;
}

function getAttendanceFileName(ext) {
    const meta = getAttendanceMeta();
    const safeAdmin = String(meta.adminCode || 'CBT').replace(/[^A-Za-z0-9_-]/g, '_');
    const safeDate = String(meta.testDate || '').replace(/[^A-Za-z0-9_-]/g, '_');
    return `Absensi_${safeAdmin}_${safeDate}.${ext}`;
}

function getAttendanceRequestData(action) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('admin_no', <?php echo json_encode($test_info['admin_code'] ?? '') ?>);
    formData.append('admin_rec_id', <?php echo json_encode($current_admin_rec_id ?? '') ?>);
    formData.append('sub_admin_id', <?php echo json_encode($current_sub_admin_id ?? '') ?>);
    formData.append('test_type', <?php echo json_encode($current_test_type ?? '') ?>);
    formData.append('tanggal', <?php echo json_encode($selected_date ?? date('Y-m-d')) ?>);
    return formData;
}

async function saveAttendanceUpdateToServer() {
    const rows = getAttendanceData();
    const formData = getAttendanceRequestData('save_attendance_update');
    formData.append('attendance_rows', JSON.stringify(rows));

    const response = await fetch(getMonitoringPostEndpoint(), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Gagal menyimpan update absensi.');
    }

    return result;
}

async function fetchFinalAttendanceItems() {
    const formData = getAttendanceRequestData('get_final_attendance_data');
    const response = await fetch(getMonitoringPostEndpoint(), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Gagal mengambil data final absensi.');
    }

    return result.items || [];
}

async function uploadFinalAttendanceBlob(blob, format) {
    const formData = getAttendanceRequestData('upload_final_attendance_file');
    formData.append('format', format);
    formData.append('attendance_file', blob, getAttendanceFileName(format));

    const response = await fetch(getMonitoringPostEndpoint(), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Gagal upload final absensi ke FTP.');
    }

    return result;
}

function triggerBlobDownload(blob, fileName) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

async function downloadFinalAttendanceFile(format) {
    try {
        const formData = getAttendanceRequestData('download_final_attendance_file');
        formData.append('format', format);

        const response = await fetch(getMonitoringPostEndpoint(), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });

        if (!response.ok) {
            throw new Error(await response.text() || 'File final absensi belum tersedia.');
        }

        const blob = await response.blob();
        triggerBlobDownload(blob, getAttendanceFileName(format));
    } catch (err) {
        alert(err.message);
    }
}

function excelXmlEscape(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function buildExcelInlineCell(ref, style, value) {
    const text = excelXmlEscape(value);
    const preserve = /^\s|\s$/.test(String(value ?? '')) ? ' xml:space="preserve"' : '';
    return `<c r="${ref}" s="${style}" t="inlineStr"><is><t${preserve}>${text}</t></is></c>`;
}

function shiftExcelRowXml(rowXml, delta) {
    if (delta === 0) return rowXml;

    return rowXml
        .replace(/<row r="(\d+)"/g, (_, row) => `<row r="${parseInt(row, 10) + delta}"`)
        .replace(/r="([A-Z]+)(\d+)"/g, (_, col, row) => `r="${col}${parseInt(row, 10) + delta}"`);
}

function shiftExcelMergeCells(xml, delta, minRow) {
    if (delta === 0) return xml;

    return xml.replace(/<mergeCell ref="([^"]+)"\/>/g, (match, ref) => {
        const shiftedRef = ref.replace(/([A-Z]+)(\d+)/g, (cellMatch, col, row) => {
            const rowNumber = parseInt(row, 10);
            return rowNumber >= minRow ? `${col}${rowNumber + delta}` : cellMatch;
        });

        return `<mergeCell ref="${shiftedRef}"/>`;
    });
}

function buildAttendanceTemplateRows(items, meta, sheetXml) {
    const originalRows = Array.from(sheetXml.matchAll(/<row r="(\d+)"[^>]*>[\s\S]*?<\/row>/g)).map(match => ({
        row: parseInt(match[1], 10),
        xml: match[0],
    }));

    const rowsBeforeTable = originalRows.filter(item => item.row >= 1 && item.row <= 10).map(item => {
        if (item.row === 5) {
            return `<row r="5" ht="30" customHeight="1" spans="1:8"><c r="A5" s="9"/>${buildExcelInlineCell('B5', 9, 'Institusi')}${buildExcelInlineCell('C5', 10, meta.institution)}<c r="D5" s="11"/><c r="E5" s="11"/><c r="F5" s="11"/><c r="G5" s="12"/><c r="H5" s="12"/></row>`;
        }

        if (item.row === 6) {
            return `<row r="6" ht="30" customHeight="1" spans="1:8"><c r="A6" s="9"/>${buildExcelInlineCell('B6', 9, 'Tanggal Tes')}${buildExcelInlineCell('C6', 10, meta.testDate)}<c r="D6" s="10"/><c r="E6" s="10"/><c r="F6" s="14"/><c r="G6" s="12"/><c r="H6" s="12"/></row>`;
        }

        if (item.row === 7) {
            return `<row r="7" ht="30" customHeight="1" spans="1:8"><c r="A7" s="9"/>${buildExcelInlineCell('B7', 9, 'Waktu Tes')}${buildExcelInlineCell('C7', 10, meta.testTime)}<c r="D7" s="10"/><c r="E7" s="10"/><c r="F7" s="14"/><c r="G7" s="12"/><c r="H7" s="12"/></row>`;
        }

        if (item.row === 8) {
            return `<row r="8" ht="30" customHeight="1" spans="1:8"><c r="A8" s="9"/>${buildExcelInlineCell('B8', 9, 'Test Administrator')}${buildExcelInlineCell('C8', 10, meta.testAdministrator)}<c r="D8" s="10"/><c r="E8" s="10"/><c r="F8" s="14"/><c r="G8" s="12"/><c r="H8" s="12"/></row>`;
        }

        return item.xml;
    });

    const tableRows = items.map((item, index) => {
        const rowNumber = 11 + index;
        const height = String(item.note || '').length > 80 ? '118' : '24.75';
        const values = [
            index + 1,
            item.authcode,
            item.name,
            item.dob,
            item.idno,
            item.hpno,
            item.attendance,
            item.note,
            item.groupcd,
            item.custom1,
            item.custom2,
            item.custom3,
        ];
        const styles = [17, 25, 26, 27, 28, 29, 23, 24, 24, 24, 24, 24];
        const cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        const cells = values.map((value, colIndex) => buildExcelInlineCell(`${cols[colIndex]}${rowNumber}`, styles[colIndex], value)).join('');

        return `<row r="${rowNumber}" ht="${height}" customHeight="1" spans="1:12">${cells}</row>`;
    });

    const footerShift = Math.max(items.length, 20) - 20;
    const footerRows = originalRows
        .filter(item => item.row >= 31)
        .map(item => {
            let xml = shiftExcelRowXml(item.xml, footerShift);

            if (item.row === 43) {
                xml = xml.replace(/<v>[\s\S]*?<\/v>/, `<v>( ${excelXmlEscape(meta.testAdministrator)} )</v>`);
            }

            return xml;
        });

    return rowsBeforeTable.concat(tableRows, footerRows).join('');
}

async function generateAttendanceExcelBlob(items, meta) {
    if (!window.JSZip) {
        throw new Error('Library JSZip belum termuat. Refresh halaman lalu coba lagi.');
    }

    const response = await fetch(ATTENDANCE_XLSX_TEMPLATE_URL, { cache: 'no-store' });

    if (!response.ok) {
        throw new Error('Template Excel absensi tidak bisa dibaca.');
    }

    const buffer = await response.arrayBuffer();
    const zip = await JSZip.loadAsync(buffer);
    const sheetPath = 'xl/worksheets/sheet1.xml';
    const tablePath = 'xl/tables/table1.xml';
    let sheetXml = await zip.file(sheetPath).async('string');
    let tableXml = await zip.file(tablePath).async('string');

    const sheetRows = buildAttendanceTemplateRows(items, meta, sheetXml);
    const lastTableRow = 10 + items.length;
    const footerShift = Math.max(items.length, 20) - 20;

    sheetXml = sheetXml.replace(/<sheetData>[\s\S]*?<\/sheetData>/, `<sheetData>${sheetRows}</sheetData>`);
    sheetXml = sheetXml.replace(/<dimension ref="[^"]+"\/>/, `<dimension ref="A1:O${1000 + footerShift}"/>`);
    sheetXml = shiftExcelMergeCells(sheetXml, footerShift, 31);
    tableXml = tableXml.replace(/ref="A10:L\d+"/, `ref="A10:L${lastTableRow}"`);

    zip.file(sheetPath, sheetXml);
    zip.file(tablePath, tableXml);

    return zip.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
}

async function downloadAttendanceExcel() {
    const btn = document.getElementById('btnDownloadAttendanceExcel');
    const originalText = btn?.innerHTML;

    try {
        assertAttendanceCanGenerate();
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generate...';
        }

        await saveAttendanceUpdateToServer();
        const items = await fetchFinalAttendanceItems();
        const blob = await generateAttendanceExcelBlob(items, getAttendanceMeta());
        await uploadFinalAttendanceBlob(blob, 'xlsx');
        triggerBlobDownload(blob, getAttendanceFileName('xlsx'));
        showToast('Absensi final Excel berhasil diupload dan didownload.');
    } catch (err) {
        console.error(err);
        alert(err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

function generateAttendancePdfBlob(items, meta) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4');
    const pageW = doc.internal.pageSize.getWidth();

    if (BA_TOEIC_LOGO_BASE64) {
        try { doc.addImage(BA_TOEIC_LOGO_BASE64, 'PNG', 14, 10, 40, 18); } catch (e) {}
    }

    if (BA_ETS_LOGO_BASE64) {
        try { doc.addImage(BA_ETS_LOGO_BASE64, 'PNG', 46, 8, 18, 8); } catch (e) {}
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text('ATTENDANCE LIST', pageW / 2, 18, { align: 'center' });

    doc.setFontSize(10);
    doc.text('Institusi', 14, 32);
    doc.text(meta.institution, 52, 32);
    doc.text('Tanggal Tes', 14, 39);
    doc.text(meta.testDate, 52, 39);
    doc.text('Waktu Tes', 14, 46);
    doc.text(meta.testTime, 52, 46);
    doc.text('Test Administrator', 14, 53);
    doc.text(meta.testAdministrator, 52, 53);

    doc.autoTable({
        startY: 62,
        head: [['No.', 'Authcode', 'Nama', 'Date of Birth', 'No. ID', 'No. HP', 'Kehadiran', 'Keterangan', 'Group', 'Custom 1', 'Custom 2', 'Custom 3']],
        body: items.map(item => [item.no, item.authcode, item.name, item.dob, item.idno, item.hpno, item.attendance, item.note, item.groupcd, item.custom1, item.custom2, item.custom3]),
        styles: { fontSize: 7, cellPadding: 1.6, overflow: 'linebreak' },
        headStyles: { fillColor: [49, 103, 81], textColor: 255, fontStyle: 'bold' },
        columnStyles: { 0: { cellWidth: 10 }, 2: { cellWidth: 42 }, 7: { cellWidth: 55 } },
        margin: { left: 10, right: 10 },
    });

    const finalY = doc.lastAutoTable.finalY || 170;
    const signY = Math.min(finalY + 18, 188);
    doc.setFontSize(10);
    doc.text('( ' + meta.testAdministrator + ' )', pageW - 70, signY + 22, { align: 'center' });
    doc.text('Test Administrator', pageW - 70, signY + 29, { align: 'center' });

    return doc.output('blob');
}

async function downloadAttendancePDF() {
    const btn = document.getElementById('btnDownloadAttendancePDF');
    const originalText = btn?.innerHTML;

    try {
        assertAttendanceCanGenerate();
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generate...';
        }

        await saveAttendanceUpdateToServer();
        const items = await fetchFinalAttendanceItems();
        const blob = generateAttendancePdfBlob(items, getAttendanceMeta());
        await uploadFinalAttendanceBlob(blob, 'pdf');
        triggerBlobDownload(blob, getAttendanceFileName('pdf'));
        showToast('Absensi final PDF berhasil diupload dan didownload.');
    } catch (err) {
        alert(err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

    // Reusable PDF generator - Template Berita Acara / Minutes of Test
function generateBeritaAcaraPDF() {
    const kejadianInput = document.getElementById('ba_kejadian_text');
    const kejadianValue = kejadianInput ? kejadianInput.value.trim() : '';

    function requireInput(id, label) {
        const el = document.getElementById(id);
        const value = el ? el.value.trim() : '';

        if (!value) {
            el?.focus();
            throw new Error(`${label} wajib diisi.`);
        }

        return value;
    }

    if (kejadianValue === '') {
        kejadianInput?.focus();
        throw new Error('Kolom Kejadian/Pelanggaran Selama Ujian wajib diisi.');
    }

    if (kejadianValue.length > 200) {
        kejadianInput?.focus();
        throw new Error('Kolom Kejadian/Pelanggaran maksimal 200 karakter.');
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    const pageW = doc.internal.pageSize.getWidth();
    const margin = 20;

    let y = 32;

    function getText(id, fallback = '-') {
        return (document.getElementById(id)?.textContent || fallback).trim();
    }

    function getValue(id, fallback = '-') {
        return (document.getElementById(id)?.value || fallback).trim();
    }

    function extractNumber(value) {
        const n = parseInt(String(value || '0').replace(/[^\d]/g, ''), 10);
        return Number.isFinite(n) ? n : 0;
    }

    function formatDateOnly(dateText) {
        return dateText || '-';
    }

    function formatTimeRange(startTime) {
        if (!startTime || startTime === '-') return '-';

        const parts = startTime.split(':');
        if (parts.length < 2) return startTime + ' WIB';

        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);

        if (!Number.isFinite(h) || !Number.isFinite(m)) return startTime + ' WIB';

        const start = new Date();
        start.setHours(h, m, 0, 0);

        // Mengikuti contoh PDF: 09.00 - 11.30 WIB = durasi 2 jam 30 menit.
        const end = new Date(start.getTime() + (150 * 60 * 1000));

        const fmt = (d) => {
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            return `${hh}.${mm}`;
        };

        return `${fmt(start)} - ${fmt(end)} WIB`;
    }

    function drawLabelValue(label, value) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor(20, 20, 20);
        doc.text(label, margin, y);

        doc.text(':', 78, y);

        doc.setFont('helvetica', 'bold');
        doc.text(String(value || '-'), 82, y);

        y += 8;
    }

    function addWrappedText(text, x, yStart, maxWidth, lineHeight = 4.5) {
        const lines = doc.splitTextToSize(text, maxWidth);
        doc.text(lines, x, yStart);
        return yStart + (lines.length * lineHeight);
    }

    function drawNumberedLines(items, startY) {
        let localY = startY;

        const safeItems = items && items.length ? items : ['-'];

        safeItems.forEach((item, index) => {
            if (localY > 260) {
                doc.addPage();
                localY = 24;
            }

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.text(`${index + 1}.`, margin + 8, localY);

            doc.setFont('helvetica', 'normal');
            localY = addWrappedText(String(item), margin + 16, localY, pageW - margin * 2 - 20, 4.5);
            localY += 5;
        });

        if (safeItems.length === 1) {
            doc.setFont('helvetica', 'bold');
            doc.text('2.', margin + 8, localY);
            localY += 12;
        }

        return localY;
    }

    const baTanggal = getText('ba_tanggal');
    const baJam = requireInput('ba_jam_input', 'Jam mulai');
    const baKlien = getText('ba_klien');
    const lokasiTes = requireInput('ba_lokasi_input', 'Lokasi tes');
    const kota = requireInput('ba_kota_input', 'Kota penandatanganan');
    const koordinatorName = requireInput('ba_koordinator_input', 'Nama koordinator instansi');
    const supervisorName = requireInput('ba_supervisor_input', 'Nama supervisor tes');

    const baHadirText = getText('ba_jumlah_peserta_hadir', '0 Peserta');
    const baTidakHadirValue = getValue('ba_peserta_tidak_hadir_input', '');

    const totalHadir = extractNumber(baHadirText);
    const totalTidakHadir = baTidakHadirValue === '' ? '' : String(extractNumber(baTidakHadirValue));

    const todayText = formatDateOnly(baTanggal);

    const terminateData = [];
    const selesaiCatatanData = [];

    document.querySelectorAll('.ba-item-selesai').forEach((item) => {
        const checked = item.querySelector('.ba-selesai-check')?.checked;
        const noteInput = item.querySelector('.ba-note-selesai');

        if (!checked) return;

        const name = item.getAttribute('data-name') || '-';
        const authId = item.getAttribute('data-auth') || '-';
        const note = noteInput?.value?.trim() || '';

        if (!note) {
            noteInput?.focus();
            throw new Error(`Catatan peserta ${name} wajib diisi karena peserta dicentang.`);
        }

        selesaiCatatanData.push(`${name.toUpperCase()} (${authId}) - ${note}`);
    });

    document.querySelectorAll('.ba-item-terminate').forEach((item, i) => {
        const name = item.getAttribute('data-name') || '-';
        const authId = item.getAttribute('data-auth') || '-';
        const noteInput = item.querySelector('.ba-note-terminate');
        const note = noteInput?.value?.trim() || '';

        if (!note) {
            noteInput?.focus();
            throw new Error(`Catatan terminasi peserta ${name} wajib diisi.`);
        }

        terminateData.push(`${name.toUpperCase()} (${authId}) - ${note}`);
    });

    const kejadianLower = kejadianValue.toLowerCase();
    const noIssue =
        kejadianLower.includes('tidak ada') ||
        kejadianLower.includes('tidak terdapat') ||
        kejadianLower.includes('no issue') ||
        kejadianLower.includes('no incident') ||
        kejadianLower === '-';

    const statusPelaksanaan = (!noIssue || terminateData.length > 0 || selesaiCatatanData.length > 0) ? 'tidak lancar' : 'lancar';

    const kendalaList = noIssue && terminateData.length === 0 && selesaiCatatanData.length === 0
        ? ['Tidak ada kendala.']
        : [kejadianValue];

    const pelanggaranData = [...selesaiCatatanData, ...terminateData];

    const pelanggaranList = pelanggaranData.length > 0
        ? pelanggaranData
        : ['Tidak ada pelanggaran tes yang terjadi.'];

    // ======================
    // HEADER
    // ======================

    const headerLogoX = 12;
    const headerLogoY = y - 11;
    const headerLogoW = 48;
    const headerLogoH = 23;
    const headerTitleX = pageW / 2;

    if (BA_TOEIC_LOGO_BASE64) {
        try {
            doc.addImage(BA_TOEIC_LOGO_BASE64, 'PNG', headerLogoX, headerLogoY, headerLogoW, headerLogoH);
        } catch (e) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(24);
            doc.text('*toeic®', headerLogoX, y + 8);
        }
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(24);
        doc.text('*toeic®', headerLogoX, y + 8);
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(0, 0, 0);
    doc.text('BERITA ACARA PELAKSANAAN', headerTitleX, y + 2, { align: 'center' });

    doc.setFontSize(10);
    doc.text('TOEIC (Test of English for International Communication)', headerTitleX, y + 13, { align: 'center' });

    y += 38;

    // ======================
    // OPENING
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('Sehubungan dengan pelaksanaan TOEIC dengan detail sebagai berikut:', margin, y);
    y += 9;

    drawLabelValue('Instansi/Institusi', baKlien);
    drawLabelValue('Lokasi tes', lokasiTes);
    drawLabelValue('Tanggal pelaksanaan', todayText);
    drawLabelValue('Waktu pelaksanaan', formatTimeRange(baJam));

    // Jumlah peserta line
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('Jumlah peserta', margin, y);
    doc.text(':', 78, y);

    doc.setFont('helvetica', 'bold');
    doc.text(String(totalHadir), 96, y, { align: 'center' });
    doc.line(86, y + 1.5, 106, y + 1.5);

    doc.text('hadir,', 110, y);

    doc.text(totalTidakHadir, 144, y, { align: 'center' });
    doc.line(134, y + 1.5, 154, y + 1.5);

    doc.text('tidak hadir.', 158, y);

    y += 9;

    // ======================
    // STATUS
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);

    const statusText = `Kami laporkan bahwa pelaksanaan TOEIC berjalan ${statusPelaksanaan} dengan keterangan sebagai berikut:`;
    y = addWrappedText(statusText, margin, y, pageW - margin * 2, 4.5);
    y += 6;

    // ======================
    // KENDALA
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.text('Kendala yang dihadapi', margin, y);
    doc.text(':', 78, y);

    y += 7;
    y = drawNumberedLines(kendalaList, y);

    y += 1;

    // ======================
    // PELANGGARAN
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.text('Pelanggaran tes yang terjadi', margin, y);
    doc.text(':', 78, y);

    y += 7;
    y = drawNumberedLines(pelanggaranList, y);

    y += 3;

    // ======================
    // STATEMENT
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);

    const statement = 'Demikian Berita Acara Pelaksanaan TOEIC ini dibuat dengan sebenar-benarnya sesuai kondisi yang ada dan telah diketahui oleh Supervisor Tes dan Koordinator Instansi/Institusi.';
    y = addWrappedText(statement, margin, y, pageW - margin * 2, 4.5);

    y += 9;

    // ======================
    // SIGNATURE
    // ======================

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text(`${kota}, ${todayText}`, margin, y);

    y += 10;

    doc.text('Mengetahui,', margin, y);

    const sigTopY = y + 12;
    const leftX = margin;
    const rightCenterX = pageW - margin - 45;

    // Optional tanda tangan kiri.
    // Kalau nanti ada tanda tangan base64:
    // window.BA_COORDINATOR_SIGNATURE_BASE64 = 'data:image/png;base64,...';
    if (window.BA_COORDINATOR_SIGNATURE_BASE64) {
        try {
            doc.addImage(window.BA_COORDINATOR_SIGNATURE_BASE64, 'PNG', leftX, sigTopY, 35, 22);
        } catch (e) {
            // ignore
        }
    }

    doc.setFont('helvetica', 'bold');
    doc.text('PT. International Test Center', rightCenterX, sigTopY, { align: 'center' });

    const nameY = sigTopY + 34;

    doc.setFont('helvetica', 'bold');
    doc.text(`(${koordinatorName})`, leftX, nameY);
    doc.text(`(${supervisorName})`, rightCenterX, nameY, { align: 'center' });

    y = nameY + 9;

    doc.text('Koordinator Instansi/Institusi', leftX, y);
    doc.text('Supervisor Tes', rightCenterX, y, { align: 'center' });

    return doc;
}

    // Download PDF ke komputer user
    function downloadBeritaAcaraPDF() {
        const btn = document.getElementById('btnDownloadBeritaAcara');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        try {
            const doc = generateBeritaAcaraPDF();
            const adminCode = <?php echo json_encode($test_info['admin_code'] ?? 'CBT') ?>;
            doc.save(`BeritaAcara_${adminCode}.pdf`);
            showToast('✅ PDF Berita Acara berhasil didownload!');
        } catch (err) {
            console.error('Error generating PDF:', err);
            alert('Gagal membuat PDF: ' + err.message);
        }
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }

    // Save PDF & Upload ke Filing System
    function saveAndUploadBeritaAcara() {
        const btn = document.getElementById('btnSaveBeritaAcara');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        btn.disabled = true;
        try {
            const doc = generateBeritaAcaraPDF();

            // DOWNLOAD ke lokal juga agar user punya copy langsung
            const adminCode = <?php echo json_encode($test_info['admin_code'] ?? 'CBT') ?>;
            doc.save(`BeritaAcara_${adminCode}.pdf`);

            const pdfBase64 = doc.output('datauristring').split(',')[1];

            const uploadData = new FormData();
            uploadData.append('action', 'upload_berita_acara_pdf');
            uploadData.append('admin_no', <?php echo json_encode($test_info['admin_code'] ?? '') ?>);
            uploadData.append('admin_rec_id', <?php echo json_encode($current_admin_rec_id ?? '') ?>);
            uploadData.append('sub_admin_id', <?php echo json_encode($current_sub_admin_id ?? '') ?>);
            uploadData.append('tanggal', <?php echo json_encode($selected_date ?? date('Y-m-d')) ?>);
            uploadData.append('pdf_data', pdfBase64);
            // DEBUG ONLY: uploadData.append('csrf_token', CSRF_TOKEN);

            fetch(getMonitoringPostEndpoint(), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // DEBUG ONLY: 'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: uploadData
            })
            .then(async response => {
                const text = await response.text();

                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Response bukan JSON:', text);
                    throw new Error('Server mengembalikan response bukan JSON. Cek Console browser dan php-error.log.');
                }

                if (!response.ok) {
                    throw new Error(result.message || result.msg || 'HTTP Error ' + response.status);
                }

                return result;
            })
            .then(res => {
                if (res.success) {
                    showToast('✅ Berita Acara berhasil disimpan & diupload ke FTP Filing System!');
                    console.log('BA uploaded:', res);
                    unlockAttendanceGeneration();
                } else {
                    alert('Gagal menyimpan Berita Acara: ' + (res.message || 'Unknown error'));
                }

                btn.innerHTML = originalHTML;
                btn.disabled = false;
            })
            .catch(err => {
                console.error('Error uploading Berita Acara:', err);
                alert('Terjadi kesalahan saat mengupload Berita Acara: ' + err.message);

                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        } catch (err) {
            console.error('Error generating PDF:', err);
            alert('Gagal membuat PDF: ' + err.message);
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }


function openParticipantDetailModal(button) {
    const row = button.closest('.participant-row');

    if (!row) {
        return;
    }

    let data = {};

    try {
        data = JSON.parse(row.getAttribute('data-participant-json') || '{}');
    } catch (error) {
        console.error('Gagal membaca data peserta:', error);
        data = {};
    }

    // Fallback dari atribut row. Jadi kalau JSON gagal/field detail kosong,
    // Nama dan ID tetap muncul di table modal.
    data.name = data.name || row.getAttribute('data-name') || '';
    data.id = data.id || row.getAttribute('data-auth-id') || '';
    data.regnm = data.regnm || data.name || '';
    data.idno = data.idno || '';

    const modal = document.getElementById('participantDetailModal');

    if (!modal) {
        return;
    }

    const photoUrl = row.getAttribute('data-photo-url') || '';
    const name = data.regnm || data.name || data.std_name || data.nama || row.dataset.name || '-';
    const id = data.id || data.authorize || data.auth_id || row.dataset.authId || '-';
    const status = data.status_text || data.q_status || row.dataset.statusText || '-';
    // const part = data.partno || data.part || '-';

    // const progressPct = data.progress_pct ?? 0;
    // const ansFilled = data.ans_filled ?? 0;
    // const ansTotal = data.ans_total ?? 0;

    // const progress = `${progressPct}% (${ansFilled}/${ansTotal} soal)`;
    // const online = data.is_online ? 'Online' : 'Offline';

    // const timerEl = row.querySelector('.timer-value');
    // const timeText = timerEl ? timerEl.textContent.trim() : '-';

    document.getElementById('participantDetailPhoto').src = photoUrl;
    document.getElementById('participantDetailName').textContent = name;
    document.getElementById('participantDetailId').textContent = id;

    // Set Mulai Ujian & Selesai Ujian
    const startTime = data.start_time || '-';
    const endTime = data.end_time || '-';
    document.getElementById('participantDetailStartTime').textContent = startTime;
    document.getElementById('participantDetailEndTime').textContent = endTime;
    // document.getElementById('participantDetailProgress').textContent = progress;
    // document.getElementById('participantDetailTime').textContent = timeText;
    // document.getElementById('participantDetailOnline').textContent = online;

    renderParticipantDetailTable(data);

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeParticipantDetailModal() {
    const modal = document.getElementById('participantDetailModal');

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function renderParticipantDetailTable(data) {
    const tbody = document.getElementById('participantDetailTable');

    if (!tbody) {
        return;
    }

    const detailRows = [
        {
            label: 'Nama',
            value: data.regnm || data.name || data.std_name || data.nama || '-'
        },
        {
            label: 'ID Number / NIK / NISN',
            value: data.idno || data.id_number || data.nik || data.nisn || '-'
        },
        {
            label: 'Date of Birth / Tanggal Lahir',
            value: data.dob || data.date_of_birth || data.tanggal_lahir || '-'
        },
        {
            label: 'Gender',
            value: formatGender(data.sexmf || data.gender || data.jenis_kelamin || '-')
        },
        {
            label: 'Email Address & Phone Number',
            value: `${formatParticipantValue(data.email)} / ${formatParticipantValue(data.hpno || data.phone || data.phone_number)}`
        },
        {
            label: 'Country Code',
            value: data.countcd || data.country_code || '-'
        },
        {
            label: 'Language Code',
            value: data.langcode || data.language_code || '-'
        },
        {
            label: 'Group Code',
            value: data.groupcd || data.group_code || '-'
        },
        {
            label: 'Custom Code 1',
            value: data.custom1 || '-'
        },
        {
            label: 'Custom Code 2',
            value: data.custom2 || '-'
        },
        {
            label: 'Custom Code 3',
            value: data.custom3 || '-'
        }
    ];

    const rows = detailRows.map(item => {
        return `
            <tr>
                <td class="w-1/3 px-4 py-3 bg-gray-50 text-xs font-bold text-gray-500 uppercase align-top">
                    ${escapeHtml(item.label)}
                </td>
                <td class="px-4 py-3 text-sm text-gray-800 break-words">
                    ${escapeHtml(formatParticipantValue(item.value))}
                </td>
            </tr>
        `;
    }).join('');

    tbody.innerHTML = rows;
}

function formatGender(value) {
    const gender = String(value || '').trim().toUpperCase();

    if (gender === 'M' || gender === 'L' || gender === 'MALE' || gender === 'LAKI-LAKI') {
        return 'Male / Laki-laki';
    }

    if (gender === 'F' || gender === 'P' || gender === 'FEMALE' || gender === 'PEREMPUAN') {
        return 'Female / Perempuan';
    }

    return value || '-';
}

function formatParticipantKey(key) {
    return String(key)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
}

function formatParticipantValue(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeParticipantDetailModal();
    }
});

document.addEventListener('click', function(event) {
    const modal = document.getElementById('participantDetailModal');

    if (!modal || modal.classList.contains('hidden')) {
        return;
    }

    if (event.target === modal) {
        closeParticipantDetailModal();

    }
});

</script>

<?php include_once BASE_PATH . '/includes/layout_footer.php'; ?>
