<?php

    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ob_start();

    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }

    define('BASE_PATH', dirname(__DIR__, 3));

    require_once BASE_PATH . '/config.php';
    require_once BASE_PATH . '/nisnlib.php';
    require_once BASE_PATH . '/includes/tad_access.php';
    require_once BASE_PATH . '/controllers/FilingSystemController.php';

    date_default_timezone_set('Asia/Jakarta');

    /*
|--------------------------------------------------------------------------
| FTP CONFIG
|--------------------------------------------------------------------------
| Jangan override FTP dari config.php/.env di file menu.
| Upload dan download Filing System harus memakai FTP yang sama.
*/
    if (! isset($ftp_config) || ! is_array($ftp_config)) {
    $ftp_config = [
        'host'      => getenv('FTP_HOST') ?: '',
        'user'      => getenv('FTP_USER') ?: '',
        'pass'      => getenv('FTP_PASS') ?: '',
        'port'      => (int) (getenv('FTP_PORT') ?: 21),
        'path'      => getenv('FTP_PATH') ?: '',
        'root_path' => getenv('FTP_ROOT_PATH') ?: '',
        'ssl'       => filter_var(getenv('FTP_SSL') ?: false, FILTER_VALIDATE_BOOLEAN),
        'timeout'   => (int) (getenv('FTP_TIMEOUT') ?: 60),
    ];
    }

    // Debug FTP (optional)
    if (isset($_GET['debug_ftp']) && $_GET['debug_ftp'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ftpTest = new FtpStorage($ftp_config);
        $ftpTest->connect();
        echo json_encode([
            'status'  => 'success',
            'message' => 'FTP connection OK',
            'host'    => $ftp_config['host'],
            'user'    => substr($ftp_config['user'], 0, -10) . '****',
            'port'    => $ftp_config['port'],
            'ssl'     => $ftp_config['ssl'],
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ]);
        exit;
    }
    }

    require_once BASE_PATH . '/classes/FtpStorage.php';

    $controller = new FilingSystemController($pdo, $pdo_run, $pdo_war, $ftp_config);

    $data = $controller->handle();

    extract($data);

    $f_client             = $f_client ?? ($_GET['f_client'] ?? '');
    $userId               = (int) ($_SESSION['user_id'] ?? 0);
    $canManageBeritaAcara = userHasTadRole($pdo_run, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])
    || ! userHasTadRole($pdo_run, $userId, ['TAD SPV']);
    $canUploadBeritaAcaraFiles = $canManageBeritaAcara || userHasTadRole($pdo_run, $userId, ['TAD SPV']);

    function get_file_icon($ext)
    {
    $icons = [
        'pdf'  => '<i class="fas fa-file-pdf text-red-500"></i>',
        'doc'  => '<i class="fas fa-file-word text-blue-500"></i>',
        'docx' => '<i class="fas fa-file-word text-blue-500"></i>',
        'xls'  => '<i class="fas fa-file-excel text-green-500"></i>',
        'xlsx' => '<i class="fas fa-file-excel text-green-500"></i>',
        'ppt'  => '<i class="fas fa-file-powerpoint text-orange-500"></i>',
        'pptx' => '<i class="fas fa-file-powerpoint text-orange-500"></i>',
        'zip'  => '<i class="fas fa-file-archive text-yellow-600"></i>',
        'jpg'  => '<i class="fas fa-file-image text-purple-500"></i>',
        'jpeg' => '<i class="fas fa-file-image text-purple-500"></i>',
        'png'  => '<i class="fas fa-file-image text-purple-500"></i>',
        'txt'  => '<i class="fas fa-file-alt text-gray-500"></i>',
    ];

    return $icons[strtolower($ext)] ?? '<i class="fas fa-file text-gray-400"></i>';
    }

    require_once BASE_PATH . '/includes/layout_header.php';

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .select2-container .select2-selection--single { height: 42px !important; border-color: #D1D5DB !important; border-radius: 0.5rem !important; background-color: #F9FAFB !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px !important; padding-left: 12px !important; color: #111827 !important; font-size: 0.875rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    #modalInput.hidden { display: none; }
</style>

<div class="py-8 md:py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight uppercase italic tracking-tighter">TEST DOCUMENT</h2>
                <p class="text-[10px] text-gray-500 font-bold uppercase mt-1">Supervisor: <span class="text-indigo-600"><?php echo htmlspecialchars($spv_name_active) ?></span></p>
            </div>
            <?php if ($canManageBeritaAcara): ?>
                <button onclick="openInputModal()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow-sm shadow-indigo-200 hover:bg-indigo-700 transition flex items-center gap-2">
                    <i class="fas fa-plus-circle"></i> INPUT DATA
                </button>
            <?php endif; ?>
        </div>

        <!-- FILTERS -->
        <div class="mb-6 flex gap-2">
            <form method="GET" class="flex-1 relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search) ?>" placeholder="Cari Nomor Admin atau Keterangan..." class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3 pl-10 shadow-sm">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400"><i class="fas fa-search"></i></div>
            </form>
            <button onclick="toggleModal('modalFilter', true)" class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition flex items-center gap-2 shadow-sm">
                <i class="fas fa-filter"></i> FILTER
            </button>
            <?php if ($search || $f_date || $f_spv || $f_client): ?>
                <a href="berita_acara" class="px-6 py-2.5 bg-red-50 text-red-600 rounded-lg text-sm font-bold hover:bg-red-100 transition flex items-center gap-2 shadow-sm"><i class="fas fa-times"></i> RESET</a>
            <?php endif; ?>
        </div>

        <!-- TABLE -->
        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Nomor Admin</th>
                            <th class="px-6 py-4">Klien</th>
                            <th class="px-6 py-4">Keterangan</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Issue</th>
                            <th class="px-6 py-4">File</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data_list as $row):
                                $stmt_f = $pdo_run->prepare("SELECT file_type FROM runit_filing_files WHERE filing_id = ?");
                                $stmt_f->execute([$row['rec_id']]);
                                $files = $stmt_f->fetchAll(PDO::FETCH_COLUMN);

                                // Count Checked Participants (Issues)
                                $stmt_i = $pdo_run->prepare("SELECT * FROM runit_filing_issues WHERE filing_id = ?");
                                $stmt_i->execute([$row['rec_id']]);
                                $issues        = $stmt_i->fetchAll();
                                $checked_count = count($issues);

                                // Count realtime participants in this admin/subadmin
                                $finished_count = 0;
                                $total_count    = 0;
                                if ($row['sub_admin_id']) {
                                    try {
                                        $stmt_t = $pdo_war->prepare("
	                                        SELECT
	                                            COUNT(*) AS total_count,
	                                            SUM(CASE WHEN t.statrec IN ('7','8','9','c','C') THEN 1 ELSE 0 END) AS finished_count
	                                        FROM t3sTt4keR5 t
	                                        INNER JOIN t3sTAdm1n a ON t.admin_id = a.rec_id
	                                        WHERE a.admin_no = ? AND t.sub_adm_id = ?
	                                    ");
                                        $stmt_t->execute([$row['nomor_admin'], $row['sub_admin_id']]);
                                        $countRow       = $stmt_t->fetch(PDO::FETCH_ASSOC) ?: [];
                                        $total_count    = (int) ($countRow['total_count'] ?? 0);
                                        $finished_count = (int) ($countRow['finished_count'] ?? 0);
                                    } catch (Exception $e) {}
                                }
                        ?>
                            <tr class="hover:bg-indigo-50/10 transition border-b border-gray-100">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="toggleSubMenu(<?php echo $row['rec_id'] ?>)" class="flex items-center gap-2 hover:text-indigo-600 transition text-left group">
                                            <i id="icon_<?php echo $row['rec_id'] ?>" class="fas fa-chevron-right text-[10px] text-gray-300 group-hover:text-indigo-400 transition-transform"></i>
                                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($row['nomor_admin']) ?></span>
                                        </button>
                                        <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded font-bold">
                                            (<?php echo $finished_count ?>/<?php echo $total_count ?> peserta)
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase ml-5">SPV: <?php echo htmlspecialchars($row['spv_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-700"><?php echo htmlspecialchars($row['client_name']) ?></td>
                                <td class="px-6 py-4">
                                    <p class="max-w-xs truncate text-[11px] text-gray-500" title="<?php echo htmlspecialchars($row['keterangan']) ?>"><?php echo htmlspecialchars($row['keterangan']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold"><?php echo date('d M Y', strtotime($row['tanggal'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($checked_count > 0): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="px-2 py-1 bg-red-50 text-red-600 text-[10px] font-bold rounded border border-red-100"><?php echo $checked_count ?> ISSUE</span>
                                            <a href="berita_acara?download_issues=<?php echo $row['rec_id'] ?>" class="p-1 bg-white border border-red-200 text-red-600 rounded hover:bg-red-50 transition" title="Download Report"><i class="fas fa-download text-[10px]"></i></a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-[10px]">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <?php
                                            $stmt_files = $pdo_run->prepare("SELECT file_id, file_name, file_type FROM runit_filing_files WHERE filing_id = ?");
                                            $stmt_files->execute([$row['rec_id']]);
                                            $all_files = $stmt_files->fetchAll();

                                        if (empty($all_files)): ?>
                                            <span class="text-[10px] text-gray-400 italic">No files</span>
                                        <?php else: ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($all_files as $f): ?>
                                                    <a href="berita_acara?download_file=<?php echo $f['file_id'] ?>" class="p-1 hover:bg-indigo-50 rounded transition group relative" title="<?php echo htmlspecialchars($f['file_name']) ?>">
                                                        <?php echo get_file_icon($f['file_type']) ?>
                                                        <span class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-800 text-white text-[9px] rounded whitespace-nowrap z-10"><?php echo htmlspecialchars($f['file_name']) ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($canUploadBeritaAcaraFiles): ?>
                                            <button onclick="openFileManager(<?php echo $row['rec_id'] ?>, '<?php echo $row['nomor_admin'] ?>')" class="text-[9px] font-bold text-indigo-600 hover:underline text-left mt-1 tracking-tighter uppercase">MANAGE FILES</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="berita_acara?download_admin_folder=<?php echo $row['rec_id'] ?>" class="p-2 bg-green-50 text-green-600 rounded hover:bg-green-100 transition" title="Download Folder Admin ZIP"><i class="fas fa-download"></i></a>
                                        <?php if ($canManageBeritaAcara): ?>
                                            <button onclick="editEntry(<?php echo $row['rec_id'] ?>)" class="p-2 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100 transition"><i class="fas fa-edit"></i></button>
                                            <a href="berita_acara?delete_entry=<?php echo $row['rec_id'] ?>" onclick="return confirm('Hapus data?')" class="p-2 bg-red-50 text-red-500 rounded hover:bg-red-100 transition"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <!-- SUB MENU ROW -->
                            <tr id="submenu_<?php echo $row['rec_id'] ?>" class="hidden bg-gray-50/50">
                                <td colspan="7" class="px-10 py-6">
                                    <div class="border-l-2 border-indigo-200 pl-6">
                                        <!-- TAB HEADER -->
                                        <div class="flex gap-4 border-b border-gray-200 mb-4 overflow-x-auto">
                                            <?php /*
                                            <button onclick="switchTab(<?= $row['rec_id'] ?>, 'issues')" id="tab_btn_issues_<?= $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-indigo-500 text-indigo-600 transition-all whitespace-nowrap">
                                                <i class="fas fa-exclamation-circle mr-1"></i> Daftar Issue
                                            </button>
                                            <button onclick="switchTab(<?= $row['rec_id'] ?>, 'live')" id="tab_btn_live_<?= $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-indigo-400 transition-all whitespace-nowrap">
                                                <i class="fas fa-desktop mr-1"></i> Live (Selesai)
                                            </button>
                                            */?>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'outbound')" id="tab_btn_outbound_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-rose-500 text-rose-600 transition-all whitespace-nowrap">
                                                <i class="fas fa-file-archive mr-1"></i> Outbound <span id="count_outbound_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'crc_raw')" id="tab_btn_crc_raw_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-cyan-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-file-code mr-1"></i> CRC RAW <span id="count_crc_raw_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'collect_date')" id="tab_btn_collect_date_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-indigo-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-calendar-check mr-1"></i> Tanggal Collect <span id="count_collect_date_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'crc_individual')" id="tab_btn_crc_individual_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-blue-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-user-check mr-1"></i> CRC Individu <span id="count_crc_individual_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'crc_gabungan')" id="tab_btn_crc_gabungan_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-purple-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-users mr-1"></i> CRC Gabungan <span id="count_crc_gabungan_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'berita_acara')" id="tab_btn_berita_acara_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-amber-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-file-signature mr-1"></i> BA Test <span id="count_berita_acara_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                            <button onclick="switchTab(<?php echo $row['rec_id'] ?>, 'dokumen_support')" id="tab_btn_dokumen_support_<?php echo $row['rec_id'] ?>" class="pb-2 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-gray-400 hover:text-emerald-500 transition-all whitespace-nowrap">
                                                <i class="fas fa-images mr-1"></i> Dokumen Support <span id="count_dokumen_support_<?php echo $row['rec_id'] ?>" class="ml-1 text-[8px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded-full">0</span>
                                            </button>
                                        </div>

                                        <!-- TAB CONTENT: ISSUES -->
                                        <?php /*
                                        <div id="tab_content_issues_<?= $row['rec_id'] ?>" class="tab-pane-<?= $row['rec_id'] ?>">
                                            <div id="issue_list_<?= $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat data issue...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: LIVE MONITORING -->
                                        <div id="tab_content_live_<?= $row['rec_id'] ?>" class="tab-pane-<?= $row['rec_id'] ?> hidden">
                                            <div id="live_list_<?= $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat data live monitoring...</div>
                                            </div>
                                        </div>
                                        */?>

                                        <!-- TAB CONTENT: OUTBOUND -->
                                        <div id="tab_content_outbound_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?>">
                                            <div id="file_list_outbound_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: CRC RAW -->
                                        <div id="tab_content_crc_raw_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_crc_raw_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: TANGGAL COLLECT -->
                                        <div id="tab_content_collect_date_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_collect_date_<?php echo $row['rec_id'] ?>" class="space-y-4">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: CRC INDIVIDU -->
                                        <div id="tab_content_crc_individual_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_crc_individual_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: CRC GABUNGAN -->
                                        <div id="tab_content_crc_gabungan_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_crc_gabungan_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: BERITA ACARA TEST -->
                                        <div id="tab_content_berita_acara_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_berita_acara_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>

                                        <!-- TAB CONTENT: DOKUMEN SUPPORT -->
                                        <div id="tab_content_dokumen_support_<?php echo $row['rec_id'] ?>" class="tab-pane-<?php echo $row['rec_id'] ?> hidden">
                                            <div id="file_list_dokumen_support_<?php echo $row['rec_id'] ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div class="text-xs text-gray-400 animate-pulse italic">Memuat file...</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FILTER -->
<div id="modalFilter" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border-t-4 border-indigo-600">
        <form method="GET" class="p-6 space-y-5">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4">
                <h3 class="font-bold text-lg text-gray-800"><i class="fas fa-filter text-indigo-600 mr-2"></i>Filter Lanjutan</h3>
                <button type="button" onclick="toggleModal('modalFilter', false)" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase">Rentang Tanggal</label>
                    <input type="text" id="f_date" name="f_date" value="<?php echo htmlspecialchars($f_date) ?>" placeholder="Pilih Tanggal..." class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase">Nomor Admin</label>
                    <select name="f_admin" id="f_admin" class="select2-modal w-full">
                        <option value="">-- Semua Admin --</option>
                        <?php
                            $seen_admin = [];
                            foreach ($assigned_admins as $adm):
                                if (in_array($adm['admin_no'], $seen_admin)) {
                                    continue;
                                }

                                $seen_admin[] = $adm['admin_no'];
                        ?>
                            <option value="<?php echo htmlspecialchars($adm['admin_no']) ?>" <?php echo $f_admin == $adm['admin_no'] ? 'selected' : '' ?>><?php echo htmlspecialchars($adm['admin_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase">Nama Klien / Institusi</label>
                    <select name="f_client" id="f_client" class="select2-modal w-full">
                        <option value="">-- Semua Klien --</option>
                        <?php foreach ($client_list as $c): ?>
                            <option value="<?php echo htmlspecialchars($c) ?>" <?php echo $f_client == $c ? 'selected' : '' ?>><?php echo htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow-sm hover:bg-indigo-700 transition">Terapkan Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- NEW MODAL INPUT -->
<div id="modalInput" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-indigo-600 p-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
                <div>
                    <h3 class="font-bold text-lg text-white"><i class="fas fa-file-signature mr-2"></i>INPUT DATA FILING</h3>
                    <p class="text-[10px] text-indigo-100 font-bold uppercase mt-0.5">Laporan Monitoring Tes</p>
                </div>
                <a href="#" id="btnDownloadModal" class="hidden px-3 py-1 bg-white/20 hover:bg-white/30 text-white rounded text-[10px] font-bold border border-white/30 transition flex items-center gap-2">
                    <i class="fas fa-download"></i> DOWNLOAD ISSUE
                </a>
            </div>
            <button type="button" onclick="toggleModal('modalInput', false)" class="text-indigo-200 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="formInput" class="flex flex-col flex-1 overflow-hidden">
            <?php echo Csrf::html(); ?>
            <input type="hidden" name="save_input" value="1">
            <input type="hidden" name="rec_id" id="input_rec_id">

            <div class="flex flex-1 overflow-hidden">
                <!-- LEFT SIDE: FORM -->
                <div class="w-1/3 p-6 border-r border-gray-100 space-y-4 overflow-y-auto bg-gray-50/50">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1.5 uppercase">Nomor Admin</label>
                        <select name="nomor_admin" id="input_nomor_admin" required class="select2-input w-full" onchange="fetchAdminDetails(this.value)">
                            <option value="">-- Pilih Admin --</option>
                            <?php foreach ($assigned_admins as $adm): ?>
                                <option value="<?php echo $adm['admin_id'].'|'.$adm['sub_admin_id'] ?>"><?php echo htmlspecialchars($adm['admin_no']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1.5 uppercase">Testdate</label>
                        <input type="date" name="tanggal" id="input_tanggal" readonly class="w-full bg-gray-100 border border-gray-300 rounded-lg p-2.5 text-sm text-gray-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1.5 uppercase">Klien / Institusi</label>
                        <input type="text" id="input_client_name" readonly class="w-full bg-gray-100 border border-gray-300 rounded-lg p-2.5 text-sm text-gray-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1.5 uppercase">Keterangan Umum</label>
                        <textarea
                            name="keterangan"
                            id="input_keterangan"
                            maxlength="100"
                            rows="3"
                            class="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Catatan tambahan..."
                            oninput="updateCharCount()"
                        ></textarea>
                        <div id="charCounter" class="text-[10px] text-gray-400 mt-1 text-right">
                            0 / 100
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: PARTICIPANTS -->
                <div class="w-2/3 flex flex-col overflow-hidden">
                    <div class="p-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                        <span class="text-[10px] font-bold text-gray-500 uppercase">Daftar Peserta & Issue (Berita Acara)</span>
                        <span id="pCount" class="text-[10px] bg-indigo-100 text-indigo-600 px-2 py-1 rounded-full font-bold">0 PESERTA</span>
                    </div>
                    <div id="participantList" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div class="text-center py-20 text-gray-400 italic text-sm">Pilih Nomor Admin untuk memuat daftar peserta.</div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="toggleModal('modalInput', false)" class="px-6 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition">BATAL</button>
                <button type="submit" class="px-8 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow-sm shadow-indigo-100 hover:bg-indigo-700 transition">SIMPAN DATA</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL FILE MANAGER -->
<div id="modalFiles" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border-t-4 border-indigo-600">
        <div class="p-6 space-y-5">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4">
                <div><h3 class="font-bold text-lg text-gray-800">File Manager</h3><p id="fileMgrAdminNo" class="text-[10px] text-gray-400 font-bold uppercase"></p></div>
                <button type="button" onclick="toggleModal('modalFiles', false)" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-dashed border-gray-300">
                <form id="formUpload" enctype="multipart/form-data" class="space-y-3">
                    <?php echo Csrf::html(); ?>
                    <input type="hidden" name="file_action" value="upload">
                    <input type="hidden" name="filing_id" id="upload_filing_id">
                    <input type="hidden" name="nomor_admin" id="upload_nomor_admin">

                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1.5 uppercase">Kategori File</label>
                        <select name="file_category" id="file_category" required class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Pilih Kategori --</option>
                            <option value="crc_individual">CRC Per Individu/authorize</option>
                            <option value="crc_gabungan">CRC Gabungan/Grab CRC</option>
                            <option value="berita_acara">Berita Acara Test</option>
                            <option value="dokumen_support">Dokumen Support lainnya (Foto, Absen)</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="file" name="file" id="fileInput" required class="flex-1 text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 cursor-pointer">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="text" name="custom_file_name" id="custom_file_name" placeholder="Nama file (opsional, contoh: Laporan Sesi 1)" class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                        <button type="submit" id="btnUpload" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 shadow-sm transition flex items-center gap-2">
                            <i class="fas fa-cloud-upload-alt"></i> UPLOAD
                        </button>
                    </div>
                    <p class="text-[9px] text-gray-400 font-medium">Format nama otomatis: <span class="text-indigo-500 italic">YYYYMMDD_HHMMSS_NomorAdmin_NamaFile.ext</span></p>
                </form>
            </div>
            <div class="max-h-60 overflow-y-auto"><table class="w-full text-xs text-left text-gray-500"><thead class="text-[9px] text-gray-400 uppercase bg-gray-50 border-b border-gray-200"><tr><th class="px-4 py-2">Nama File</th><th class="px-4 py-2 text-right">Aksi</th></tr></thead><tbody id="fileListBody" class="divide-y divide-gray-100"></tbody></table></div>
        </div>
    </div>
</div>

<script>

const CAN_DELETE_BERITA_ACARA_FILES = <?php echo $canManageBeritaAcara ? 'true' : 'false' ?>;

function getCategoryLabel(cat) {
    const labels = {
        'crc_individual': 'CRC Per Individu',
        'crc_raw': 'CRC RAW',
        'collect_date': 'Tanggal Collect',
        'crc_gabungan': 'CRC Gabungan',
        'berita_acara': 'Berita Acara Test',
        'dokumen_support': 'Dokumen Support',
        'outbound': 'Outbound',
    };
    return labels[cat] || cat || 'Lainnya';
}

function getCategoryBadgeClass(cat) {
    const classes = {
        'crc_individual': 'bg-blue-50 text-blue-600 border border-blue-100',
        'crc_raw': 'bg-cyan-50 text-cyan-600 border border-cyan-100',
        'collect_date': 'bg-indigo-50 text-indigo-600 border border-indigo-100',
        'crc_gabungan': 'bg-purple-50 text-purple-600 border border-purple-100',
        'berita_acara': 'bg-amber-50 text-amber-600 border border-amber-100',
        'dokumen_support': 'bg-emerald-50 text-emerald-600 border border-emerald-100',
        'outbound': 'bg-rose-50 text-rose-600 border border-rose-100',
    };
    return classes[cat] || 'bg-gray-50 text-gray-500 border border-gray-100';
}

function getCsrfToken() {
    const token = document.querySelector('input[name="csrf_token"]');
    return token ? token.value : '';
}

async function parseJsonResponse(response) {
    const text = await response.text();

    console.log('HTTP STATUS:', response.status);
    console.log('RAW RESPONSE TEXT:', text);

    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('JSON PARSE ERROR:', e);
        alert(
            'Server response is not valid JSON.\n\n' +
            'HTTP Status: ' + response.status + '\n\n' +
            'Response:\n' + text.substring(0, 300)
        );
        throw e;
    }
}

function updateCharCount() {
    const textarea = document.getElementById('input_keterangan');
    const counter = document.getElementById('charCounter');

    const length = textarea.value.length;
    counter.innerText = length + ' / 100';

    if (length >= 90) {
        counter.classList.remove('text-gray-400');
        counter.classList.add('text-red-500', 'font-bold');
    } else {
        counter.classList.remove('text-red-500', 'font-bold');
        counter.classList.add('text-gray-400');
    }
}

function toggleSubMenu(id) {
    const row = document.getElementById('submenu_' + id);
    const icon = document.getElementById('icon_' + id);
    if(row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        icon.classList.add('rotate-90');
        icon.classList.remove('text-gray-300');
        icon.classList.add('text-indigo-500');
        loadSubMenuData(id);
    } else {
        row.classList.add('hidden');
        icon.classList.remove('rotate-90');
        icon.classList.add('text-gray-300');
        icon.classList.remove('text-indigo-500');
    }
}

function switchTab(id, tab) {
    const tabs = ['outbound', 'crc_raw', 'collect_date', 'crc_individual', 'crc_gabungan', 'berita_acara', 'dokumen_support'];
    const tabStyles = {
        outbound: ['border-rose-500', 'text-rose-600'],
        crc_raw: ['border-cyan-500', 'text-cyan-600'],
        collect_date: ['border-indigo-500', 'text-indigo-600'],
        crc_individual: ['border-blue-500', 'text-blue-600'],
        crc_gabungan: ['border-purple-500', 'text-purple-600'],
        berita_acara: ['border-amber-500', 'text-amber-600'],
        dokumen_support: ['border-emerald-500', 'text-emerald-600'],
    };

    document.querySelectorAll('.tab-pane-' + id).forEach(el => el.classList.add('hidden'));
    const activeContent = document.getElementById('tab_content_' + tab + '_' + id);
    if (activeContent) activeContent.classList.remove('hidden');

    tabs.forEach(t => {
        const btn = document.getElementById('tab_btn_' + t + '_' + id);
        if (!btn) return;
        const [border, text] = tabStyles[t];
        if (t === tab) {
            btn.classList.add(border, text);
            btn.classList.remove('border-transparent', 'text-gray-400');
        } else {
            btn.classList.remove(border, text);
            btn.classList.add('border-transparent', 'text-gray-400');
        }
    });
}

function loadSubMenuData(id) {
    fetch('berita_acara.php?ajax_get_submenu=' + id)
    .then(r => r.json())
    .then(data => {
        const categories = ['outbound', 'crc_raw', 'crc_individual', 'crc_gabungan', 'berita_acara', 'dokumen_support'];
        categories.forEach(cat => {
            const container = document.getElementById('file_list_' + cat + '_' + id);
            const countBadge = document.getElementById('count_' + cat + '_' + id);
            if (!container) return;
            container.innerHTML = '';
            const files = data.files_by_category[cat] || [];
            if (countBadge) countBadge.textContent = files.length;
            if (files.length === 0) {
                container.innerHTML = '<div class="text-xs text-gray-400 italic">Tidak ada file.</div>';
            } else {
                files.forEach(f => {
                    const icon = getFileTypeIcon(f.file_type);
                    const downloadUrl = f.download_url || ('berita_acara.php?download_file=' + f.file_id);
                    container.innerHTML += `
                        <div class="bg-white p-3 rounded border border-gray-100 shadow-sm flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">${icon}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-700 truncate" title="${f.file_name}">${f.file_name}</div>
                                <div class="text-[9px] text-gray-400 mt-0.5">${f.file_type.toUpperCase()} &middot; ${f.uploaded_at || ''}</div>
                                <div class="text-[9px] text-gray-400 mt-0.5 truncate" title="${f.relative_path || f.file_path || ''}">${f.relative_path || f.file_path || ''}</div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a href="${downloadUrl}" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded" title="Download"><i class="fas fa-download text-[10px]"></i></a>
                            </div>
                        </div>
                    `;
                });
            }
        });

        renderCollectDateTab(id, data.files_by_category || {});
    })
    .catch(err => {
        console.error(err);
        const firstTab = document.getElementById('file_list_crc_individual_' + id);
        if (firstTab) firstTab.innerHTML = '<div class="text-xs text-red-500">Gagal memuat data.</div>';
    });
}

function getFilePathForGrouping(file) {
    return String(file.relative_path || file.file_path || '').replace(/\\/g, '/').replace(/^\/+/, '');
}

function getCollectDateKey(file) {
    const path = getFilePathForGrouping(file);
    const parts = path.split('/').filter(Boolean);
    if (parts.length > 0 && /^\d{8}$/.test(parts[0])) return parts[0];
    if (parts.length > 1 && /^\d{8}$/.test(parts[1])) return parts[1];
    if (parts[0] === 'GrabCRC') return 'Legacy GrabCRC';
    return 'Legacy / Folder Lama';
}

function formatCollectDateKey(key) {
    if (!/^\d{8}$/.test(key)) return key;
    return `${key.substring(6, 8)}/${key.substring(4, 6)}/${key.substring(0, 4)}`;
}

function renderFileMiniList(files) {
    if (!files || files.length === 0) {
        return '<div class="text-[10px] text-gray-400 italic">Tidak ada file.</div>';
    }

    return files.map(f => {
        const icon = getFileTypeIcon(f.file_type || 'file');
        const downloadUrl = f.download_url || (f.file_id ? ('berita_acara.php?download_file=' + f.file_id) : '#');
        const path = getFilePathForGrouping(f);
        return `
            <div class="bg-white border border-gray-100 rounded-lg p-2 flex items-start gap-2">
                <div class="shrink-0 mt-0.5">${icon}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-semibold text-gray-700 truncate" title="${f.file_name}">${f.file_name}</div>
                    <div class="text-[9px] text-gray-400 truncate" title="${path}">${path}</div>
                </div>
                <a href="${downloadUrl}" class="p-1 text-blue-500 hover:bg-blue-50 rounded" title="Download"><i class="fas fa-download text-[10px]"></i></a>
            </div>
        `;
    }).join('');
}

function renderCollectDateTab(id, filesByCategory) {
    const container = document.getElementById('file_list_collect_date_' + id);
    const countBadge = document.getElementById('count_collect_date_' + id);
    if (!container) return;

    const collectFiles = []
        .concat(filesByCategory.crc_gabungan || [])
        .concat(filesByCategory.crc_individual || [])
        .concat(filesByCategory.berita_acara || [])
        .concat(filesByCategory.dokumen_support || []);

    const grouped = {};
    collectFiles.forEach(file => {
        const key = getCollectDateKey(file);
        if (!grouped[key]) {
            grouped[key] = {
                crc_gabungan: [],
                crc_individual: [],
                berita_acara: [],
                dokumen_support: [],
            };
        }
        const category = file.file_category || 'dokumen_support';
        if (grouped[key][category]) {
            grouped[key][category].push(file);
        } else {
            grouped[key].dokumen_support.push(file);
        }
    });

    const keys = Object.keys(grouped).sort((a, b) => {
        const aDate = /^\d{8}$/.test(a) ? a : '00000000';
        const bDate = /^\d{8}$/.test(b) ? b : '00000000';
        return bDate.localeCompare(aDate);
    });

    if (countBadge) countBadge.textContent = keys.length;

    if (keys.length === 0) {
        container.innerHTML = '<div class="text-xs text-gray-400 italic">Tidak ada file collect.</div>';
        return;
    }

    container.innerHTML = keys.map(key => {
        const group = grouped[key];
        const totalFiles = group.crc_gabungan.length + group.crc_individual.length + group.berita_acara.length + group.dokumen_support.length;
        return `
            <div class="bg-white border border-indigo-100 rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-indigo-50/70 border-b border-indigo-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-check text-indigo-500"></i>
                        <span class="text-xs font-black text-gray-800 uppercase">${formatCollectDateKey(key)}</span>
                    </div>
                    <span class="text-[10px] font-bold text-indigo-600 bg-white border border-indigo-100 px-2 py-0.5 rounded-full">${totalFiles} file</span>
                </div>
                <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] font-black text-purple-600 uppercase mb-2">CRC Gabungan (${group.crc_gabungan.length})</div>
                        <div class="space-y-2">${renderFileMiniList(group.crc_gabungan)}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-blue-600 uppercase mb-2">CRC Individu (${group.crc_individual.length})</div>
                        <div class="space-y-2 max-h-52 overflow-y-auto pr-1">${renderFileMiniList(group.crc_individual)}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-amber-600 uppercase mb-2">BA Test (${group.berita_acara.length})</div>
                        <div class="space-y-2">${renderFileMiniList(group.berita_acara)}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-emerald-600 uppercase mb-2">Documents (${group.dokumen_support.length})</div>
                        <div class="space-y-2 max-h-52 overflow-y-auto pr-1">${renderFileMiniList(group.dokumen_support)}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getFileTypeIcon(ext) {
    const icons = {
        pdf: '<i class="fas fa-file-pdf text-red-500"></i>',
        doc: '<i class="fas fa-file-word text-blue-500"></i>',
        docx: '<i class="fas fa-file-word text-blue-500"></i>',
        xls: '<i class="fas fa-file-excel text-green-500"></i>',
        xlsx: '<i class="fas fa-file-excel text-green-500"></i>',
        zip: '<i class="fas fa-file-archive text-yellow-600"></i>',
        rar: '<i class="fas fa-file-archive text-yellow-600"></i>',
        jpg: '<i class="fas fa-file-image text-purple-500"></i>',
        jpeg: '<i class="fas fa-file-image text-purple-500"></i>',
        png: '<i class="fas fa-file-image text-purple-500"></i>',
        crc: '<i class="fas fa-file-code text-cyan-500"></i>',
    };
    return icons[ext.toLowerCase()] || '<i class="fas fa-file text-gray-400"></i>';
}

function toggleModal(id, show) {
    const m = document.getElementById(id);
    if(show) m.classList.remove('hidden');
    else m.classList.add('hidden');
}

function openInputModal() {
    document.getElementById('formInput').reset();
    document.getElementById('input_rec_id').value = '';
    document.getElementById('input_client_name').value = '';
    document.getElementById('btnDownloadModal').classList.add('hidden');
    $('#input_nomor_admin').val('').trigger('change').prop('disabled', false);
    document.getElementById('participantList').innerHTML = '<div class="text-center py-20 text-gray-400 italic text-sm">Pilih Nomor Admin untuk memuat daftar peserta.</div>';
    updateCharCount();
    toggleModal('modalInput', true);
}

function editEntry(id) {
    fetch('berita_acara.php?ajax_get_entry=' + id)
    .then(r => r.json())
    .then(async (data) => {
        if(!data) return;
        toggleModal('modalInput', true);
        document.getElementById('input_rec_id').value = data.rec_id;
        document.getElementById('input_tanggal').value = data.tanggal;
        document.getElementById('input_keterangan').value = data.keterangan;
        updateCharCount();

        const btnDownload = document.getElementById('btnDownloadModal');
        if(data.issues && data.issues.length > 0) {
            btnDownload.classList.remove('hidden');
            btnDownload.href = 'berita_acara.php?download_issues=' + data.rec_id;
        } else {
            btnDownload.classList.add('hidden');
        }

        const adminVal = data.admin_id + '|' + data.sub_admin_id;
        $('#input_nomor_admin').val(adminVal).trigger('change').prop('disabled', true);

        await fetchAdminDetails(adminVal, data.issues);
    });
}

async function fetchAdminDetails(val, existingIssues = []) {
    if(!val) return;
    const filingId = document.getElementById('input_rec_id').value;
    const list = document.getElementById('participantList');
    list.innerHTML = '<div class="text-center py-20 text-indigo-500 font-bold"><i class="fas fa-spinner fa-spin mr-2"></i>Loading Participants...</div>';

    try {
        const res = await fetch('berita_acara.php?ajax_admin_info=1&admin_val=' + encodeURIComponent(val) + '&filing_id=' + filingId).then(r => r.json());

       document.getElementById('input_tanggal').value = res.testdate;
document.getElementById('input_client_name').value = res.client_name;
document.getElementById('pCount').innerText = res.participants.length + ' PESERTA';

list.innerHTML = '';

if (!res.participants || res.participants.length === 0) {
    list.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-xl p-5 text-sm">
            <div class="flex items-start gap-3">
                <div class="shrink-0 mt-0.5">
                    <i class="fas fa-info-circle text-blue-500 text-lg"></i>
                </div>
                <div>
                    <div class="font-bold uppercase text-xs tracking-wide mb-1">
                        Informasi
                    </div>
                    <p class="leading-relaxed">
                        Nomor admin ini belum memiliki peserta yang selesai ujian.
                    </p>
                    <p class="text-xs text-blue-500 mt-2">
                        Data peserta akan muncul setelah ada authorize/peserta yang sudah menyelesaikan ujian.
                    </p>
                </div>
            </div>
        </div>
    `;

    return;
}

res.participants.forEach(p => {
            const div = document.createElement('div');
            div.className = "p-4 bg-white border border-gray-200 rounded-lg hover:border-indigo-300 transition-all";

            let hasIssue = false;
            let issueText = '';
            if(existingIssues && existingIssues.length > 0) {
                const found = existingIssues.find(i => i.authorize_id === p.id);
                if(found) {
                    hasIssue = true;
                    issueText = found.issue_text;
                }
            }

            div.innerHTML = `
                <div class="flex items-start gap-4">
                    <div class="pt-1">
                        <input type="checkbox" name="participant_checked[]" value="${p.id}" ${hasIssue ? 'checked' : ''} class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer" onchange="toggleIssue(this, '${p.id}')">
                        <input type="hidden" name="participant_name[${p.id}]" value="${p.name}">
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-gray-800 text-sm">${p.name}</span>
                            <span class="text-[10px] font-mono text-gray-400 bg-gray-50 px-2 py-0.5 rounded border border-gray-100">${p.id}</span>
                        </div>
                        <div id="issue_box_${p.id}" class="mt-3 ${hasIssue ? '' : 'hidden'} animate-slideDown">
                            <label class="block text-[9px] font-bold text-red-400 mb-1 uppercase">Berita Acara / Issue</label>
                            <textarea
                                name="issue_text[${p.id}]"
                                rows="2"
                                maxlength="100"
                                oninput="this.nextElementSibling.innerText = this.value.length + ' / 100'"
                                class="w-full bg-red-50/30 border border-red-100 rounded-lg p-2 text-xs focus:ring-red-300"
                            >${issueText}</textarea>
                            <div class="text-[10px] text-gray-400 mt-1 text-right">
                                ${issueText.length} / 100
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.appendChild(div);
        });
    } catch (e) {
        list.innerHTML = '<div class="text-center py-20 text-red-500 italic text-sm">Gagal memuat peserta.</div>';
    }
}

function toggleIssue(cb, id) {
    const box = document.getElementById('issue_box_' + id);
    if(cb.checked) box.classList.remove('hidden');
    else box.classList.add('hidden');
}

function openFileManager(id, adminNo) {
    document.getElementById('fileMgrAdminNo').innerText = 'ADMIN: ' + adminNo;
    document.getElementById('upload_filing_id').value = id;
    document.getElementById('upload_nomor_admin').value = adminNo;
    loadFileList(id);
    toggleModal('modalFiles', true);
}

function loadFileList(id) {
    const body = document.getElementById('fileListBody');
    body.innerHTML = '<tr><td class="py-4 text-center">Loading...</td></tr>';
    const fd = new FormData();
    fd.append('file_action', 'list');
    fd.append('filing_id', id);
    fd.append('csrf_token', getCsrfToken());
    fetch('berita_acara.php', { method: 'POST', body: fd }).then(parseJsonResponse).then(data => {
        body.innerHTML = '';
        if(data.length === 0) { body.innerHTML = '<tr><td class="py-4 text-center italic text-gray-400">No files.</td></tr>'; return; }
        data.forEach(f => {
            const catLabel = getCategoryLabel(f.file_category);
            const deleteButton = CAN_DELETE_BERITA_ACARA_FILES
                ? `<button onclick="deleteFile(${f.file_id}, ${id})" class="text-red-400"><i class="fas fa-trash-alt"></i></button>`
                : '';
            body.innerHTML += `<tr class="hover:bg-gray-50"><td class="px-4 py-3"><div class="font-medium text-xs">${f.file_name}</div><span class="text-[9px] px-1.5 py-0.5 rounded font-bold ${getCategoryBadgeClass(f.file_category)}">${catLabel}</span></td><td class="px-4 py-3 text-right"><div class="flex justify-end gap-2"><a href="berita_acara?download_file=${f.file_id}" class="text-blue-500"><i class="fas fa-download"></i></a>${deleteButton}</div></td></tr>`;
        });
    });
}

function deleteFile(fid, id) {
    if(!confirm('Hapus file?')) return;
    const fd = new FormData();
    fd.append('file_action', 'delete');
    fd.append('file_id', fid);
    fd.append('csrf_token', getCsrfToken());
    fetch('berita_acara.php', { method: 'POST', body: fd }).then(parseJsonResponse).then(res => {
        if(res.status === 'success') loadFileList(id);
        else alert(res.msg || 'Gagal hapus file.');
    });
}

$(document).ready(function() {
    $('.select2-modal').select2({ dropdownParent: $('#modalFilter'), width: '100%' });
    $('.select2-input').select2({ dropdownParent: $('#modalInput'), width: '100%' });
    flatpickr("#f_date", { mode: "range", dateFormat: "Y-m-d" });
    $('#formUpload').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnUpload');
        const originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> UPLOADING...');

        fetch('berita_acara.php', { method: 'POST', body: new FormData(this) })
        .then(parseJsonResponse)
        .then(res => {
            btn.prop('disabled', false).html(originalHtml);
            if(res.status === 'success') {
                $('#fileInput').val('');
                $('#custom_file_name').val('');
                loadFileList($('#upload_filing_id').val());
                alert('File berhasil diupload!');
            } else {
                alert('Error: ' + (res.msg || 'Upload gagal'));
            }
        })
        .catch(err => {
            btn.prop('disabled', false).html(originalHtml);
            console.error('Upload error:', err);
            alert('Terjadi kesalahan jaringan atau server. Lihat console untuk detail.');
        });
    });
});
</script>

<?php require_once BASE_PATH . '/includes/layout_footer.php'; ?>
