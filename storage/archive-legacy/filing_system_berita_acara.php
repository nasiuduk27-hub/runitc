<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__, 3));

require_once BASE_PATH.'/config.php';
require_once BASE_PATH.'/nisnlib.php';
require_once BASE_PATH.'/includes/tad_access.php';
require_once BASE_PATH.'/controllers/FilingSystemController.php';

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
        'host' => getenv('FTP_HOST') ?: '',
        'user' => getenv('FTP_USER') ?: '',
        'pass' => getenv('FTP_PASS') ?: '',
        'port' => (int) (getenv('FTP_PORT') ?: 21),
        'path' => getenv('FTP_PATH') ?: '',
        'root_path' => getenv('FTP_ROOT_PATH') ?: '',
        'ssl' => filter_var(getenv('FTP_SSL') ?: false, FILTER_VALIDATE_BOOLEAN),
        'timeout' => (int) (getenv('FTP_TIMEOUT') ?: 60),
    ];
}

// Debug FTP (optional)
if (isset($_GET['debug_ftp']) && $_GET['debug_ftp'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ftpTest = new FtpStorage($ftp_config);
        $ftpTest->connect();
        echo json_encode([
            'status' => 'success',
            'message' => 'FTP connection OK',
            'host' => $ftp_config['host'],
            'user' => substr($ftp_config['user'], 0, -10).'****',
            'port' => $ftp_config['port'],
            'ssl' => $ftp_config['ssl'],
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}

require_once BASE_PATH.'/classes/FtpStorage.php';

$controller = new FilingSystemController($pdo, $pdo_run, $pdo_war, $ftp_config, $pdo_collector ?? null);

$data = $controller->handle();

extract($data);

$search = $search ?? ($_GET['search'] ?? '');
$f_date = $f_date ?? ($_GET['f_date'] ?? '');
$f_spv = $f_spv ?? ($_GET['f_spv'] ?? '');
$f_client = $f_client ?? ($_GET['f_client'] ?? '');
$f_admin = $f_admin ?? ($_GET['f_admin'] ?? '');
$activeView = $_GET['view'] ?? 'main';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$canManageBeritaAcara = userHasTadRole($pdo_run, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])
|| ! userHasTadRole($pdo_run, $userId, ['TAD SPV']);
$canUploadBeritaAcaraFiles = $canManageBeritaAcara || userHasTadRole($pdo_run, $userId, ['TAD SPV']);
$uploadForId = (int) ($_GET['upload_for'] ?? 0);
$uploadTarget = null;
$testDocumentAllRows = is_array($data_list ?? null) ? $data_list : [];
$testDocumentPage = max(1, (int) ($_GET['page'] ?? 1));
$testDocumentPerPage = 25;
$testDocumentTotal = count($testDocumentAllRows);
$testDocumentTotalPages = max(1, (int) ceil($testDocumentTotal / $testDocumentPerPage));
if ($testDocumentPage > $testDocumentTotalPages) {
    $testDocumentPage = $testDocumentTotalPages;
}
$data_list = array_slice($testDocumentAllRows, ($testDocumentPage - 1) * $testDocumentPerPage, $testDocumentPerPage);
$pageQueryBase = $_GET;
unset($pageQueryBase['page']);
if ($uploadForId > 0) {
    foreach ($testDocumentAllRows as $candidateRow) {
        if ((int) ($candidateRow['rec_id'] ?? 0) === $uploadForId) {
            $uploadTarget = $candidateRow;
            break;
        }
    }
}

function get_file_icon($ext)
{
    $icons = [
        'pdf' => '<i class="fas fa-file-pdf text-red-500"></i>',
        'doc' => '<i class="fas fa-file-word text-blue-500"></i>',
        'docx' => '<i class="fas fa-file-word text-blue-500"></i>',
        'xls' => '<i class="fas fa-file-excel text-green-500"></i>',
        'xlsx' => '<i class="fas fa-file-excel text-green-500"></i>',
        'ppt' => '<i class="fas fa-file-powerpoint text-orange-500"></i>',
        'pptx' => '<i class="fas fa-file-powerpoint text-orange-500"></i>',
        'zip' => '<i class="fas fa-file-archive text-yellow-600"></i>',
        'jpg' => '<i class="fas fa-file-image text-purple-500"></i>',
        'jpeg' => '<i class="fas fa-file-image text-purple-500"></i>',
        'png' => '<i class="fas fa-file-image text-purple-500"></i>',
        'txt' => '<i class="fas fa-file-alt text-gray-500"></i>',
    ];

    return $icons[strtolower($ext)] ?? '<i class="fas fa-file text-gray-400"></i>';
}

require_once BASE_PATH.'/includes/layout_header.php';

$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

?>

<?php if ($successMsg || $errorMsg) { ?>
    <div id="uploadToast" style="position:fixed;right:16px;top:88px;z-index:999999;background:#fff;border-left:4px solid <?php echo $successMsg ? '#10b981' : '#ef4444' ?>;box-shadow:0 18px 40px rgba(15,23,42,.18);border-radius:12px;padding:14px 16px;max-width:360px;transition:opacity .3s ease, transform .3s ease;">
        <button type="button" onclick="dismissUploadToast()" style="position:absolute;top:8px;right:8px;background:transparent;border:0;color:#9ca3af;font-size:14px;line-height:1;cursor:pointer;padding:4px;">&times;</button>
        <div style="font-size:11px;font-weight:900;color:<?php echo $successMsg ? '#059669' : '#dc2626' ?>;text-transform:uppercase;margin-bottom:4px;"><?php echo $successMsg ? 'Upload Berhasil' : 'Upload Gagal' ?></div>
        <div style="font-size:13px;color:#374151;font-weight:700;line-height:1.4;"><?php echo htmlspecialchars($successMsg ?: $errorMsg) ?></div>
    </div>
    <script>
        function dismissUploadToast() {
            var toast = document.getElementById('uploadToast');
            if (!toast) return;
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(function () { toast.remove(); }, 300);
        }
        setTimeout(dismissUploadToast, 5000);
    </script>
<?php } ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .select2-container .select2-selection--single { height: 42px !important; border-color: #D1D5DB !important; border-radius: 0.5rem !important; background-color: #F9FAFB !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px !important; padding-left: 12px !important; color: #111827 !important; font-size: 0.875rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    #modalInput.hidden { display: none; }
    .view-transition-overlay { opacity: 0; pointer-events: none; transition: opacity 180ms ease; }
    .view-transition-overlay.is-active { opacity: 1; pointer-events: auto; }
    .view-transition-content { animation: viewFadeIn 180ms ease-out; }
    @keyframes viewFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    .ba-card-panel { background:#fff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(15,23,42,.04); margin-bottom:24px; }
    .ba-card-header { padding:16px 20px; background:#f9fafb; border-bottom:1px solid #eef2f7; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .ba-card-title { font-size:14px; font-weight:900; color:#1f2937; text-transform:uppercase; letter-spacing:-.01em; }
    .ba-card-meta { font-size:10px; font-weight:800; color:#9ca3af; text-transform:uppercase; margin-top:2px; }
    .ba-card-list { display:flex; flex-direction:column; }
    .ba-card-item { padding:18px 20px; border-bottom:1px solid #f3f4f6; background:#fff; }
    .ba-card-item:last-child { border-bottom:0; }
    .ba-card-item:hover { background:#f8fafc; }
    .ba-card-row { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
    .ba-card-main { min-width:0; flex:1; }
    .ba-card-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; flex-shrink:0; }
    .ba-admin-line { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ba-admin-no { font-size:18px; line-height:1.2; font-weight:900; color:#111827; letter-spacing:-.02em; }
    .ba-pill { display:inline-flex; align-items:center; border-radius:999px; padding:3px 8px; font-size:10px; line-height:1; font-weight:800; white-space:nowrap; }
    .ba-pill-gray { background:#f3f4f6; color:#6b7280; }
    .ba-pill-amber { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
    .ba-pill-red { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .ba-info-grid { display:grid; grid-template-columns:2fr 120px 1.4fr; gap:14px; margin-top:12px; }
    .ba-info-label { font-size:9px; line-height:1; font-weight:900; color:#9ca3af; text-transform:uppercase; margin-bottom:5px; }
    .ba-info-value { font-size:12px; font-weight:700; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ba-note { margin-top:10px; font-size:12px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ba-action { display:inline-flex; align-items:center; justify-content:center; border-radius:10px; padding:9px 12px; font-size:10px; font-weight:900; text-transform:uppercase; text-decoration:none; border:1px solid transparent; cursor:pointer; }
    .ba-action-primary { background:#eef2ff; color:#4f46e5; }
    .ba-action-primary:hover { background:#e0e7ff; }
    .ba-action-neutral { background:#fff; border-color:#e5e7eb; color:#4b5563; }
    .ba-action-neutral:hover { background:#f9fafb; }
    .ba-action-danger { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .ba-action-danger:hover { background:#fee2e2; }
    .ba-action-amber { background:#fffbeb; border-color:#fde68a; color:#b45309; }
    .ba-action-amber:hover { background:#fef3c7; }
    .ba-detail-panel { display:none; margin-top:16px; padding:14px; border:1px solid #c7d2fe; border-radius:14px; background:#eef2ff55; }
    .ba-detail-panel:not(.hidden) { display:block; }
    .view-transition-content table { display:none !important; }
    .view-transition-content tr[id^="submenu_"] { display:none !important; }
    .view-transition-content .ba-card-panel, .view-transition-content .ba-card-panel * { box-sizing:border-box; }
    @media (max-width: 900px) {
        .ba-card-row { flex-direction:column; }
        .ba-card-actions { justify-content:flex-start; }
        .ba-info-grid { grid-template-columns:1fr; gap:10px; }
    }
</style>

<div id="viewTransitionOverlay" class="view-transition-overlay fixed inset-0 z-[9999] bg-white/80 backdrop-blur-sm items-center justify-center" style="display: none;">
    <div class="bg-white border border-gray-100 shadow-xl rounded-2xl px-6 py-5 flex items-center gap-4">
        <div class="w-9 h-9 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
        <div>
            <div class="text-sm font-black text-gray-800 uppercase tracking-tight">Memuat halaman</div>
            <div class="text-[10px] text-gray-400 font-bold uppercase mt-0.5">Menyiapkan view...</div>
        </div>
    </div>
</div>
<script>
    (function() {
        const overlay = document.getElementById('viewTransitionOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            overlay.classList.remove('is-active');
        }
    })();
</script>

<div class="py-8 md:py-12 view-transition-content">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight uppercase italic tracking-tighter">TEST DOCUMENT</h2>
                <p class="text-[10px] text-gray-500 font-bold uppercase mt-1">Supervisor: <span class="text-indigo-600"><?php echo htmlspecialchars($spv_name_active) ?></span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="berita_acara" data-view-transition class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest border transition <?php echo $activeView === 'main' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
                    Test Document
                </a>
                <a href="berita_acara?view=crc_b2" data-view-transition class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest border transition <?php echo $activeView === 'crc_b2' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-100' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
                    CRC Offline
                </a>
            </div>
        </div>

        <?php if ($activeView === 'crc_b2') { ?>
            <div class="bg-sky-50/70 border border-sky-100 rounded-xl p-5 mb-6 shadow-sm">
                <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                    <div class="flex-1">
                        <div class="text-[10px] font-black text-sky-600 uppercase tracking-widest mb-1">Download Hasil Collect CRC</div>
                        <h3 class="text-lg font-black text-gray-800">CRC Offline</h3>
                        <p class="text-xs text-gray-500 mt-1">Data berasal dari program CRC Collector eksternal yang memproses/upload hasil CBT Offline ke FTP/database.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2 w-full lg:w-auto">
                        <input type="text" id="crcB2AdminInput" placeholder="Cari Nomor Admin" oninput="debouncedLoadCrcB2Folders()" class="bg-white border border-sky-200 rounded-lg p-2.5 text-sm font-bold text-gray-800 focus:ring-sky-500 focus:border-sky-500 uppercase xl:col-span-2">
                        <input type="date" id="crcB2DateInput" onchange="loadCrcB2FoldersLite(1)" class="bg-white border border-sky-200 rounded-lg p-2.5 text-sm font-bold text-gray-800 focus:ring-sky-500 focus:border-sky-500" title="Filter tanggal proses CRC Offline">
                        <button type="button" onclick="loadCrcB2Folders(1)" class="px-4 py-2.5 bg-sky-600 text-white rounded-lg text-sm font-bold hover:bg-sky-700 transition flex items-center justify-center gap-2">
                            <i class="fas fa-sync-alt"></i> REFRESH
                        </button>
                    </div>
                </div>
                <button type="button" onclick="resetCrcB2Filters()" class="mt-3 text-[10px] font-bold text-sky-600 hover:underline uppercase">Reset Filter</button>
                <div style="margin-top:12px;background:#fff;border:1px solid #e0f2fe;border-radius:12px;padding:12px;font-size:11px;color:#0369a1;line-height:1.5;">
                    <strong>Catatan:</strong> CRC Offline tidak mengambil data dari upload Test Document. File muncul setelah diproses oleh aplikasi CRC Collector eksternal.
                </div>
                <div id="crcB2CollectResult" class="mt-4"></div>
                <div id="crcB2Pagination" class="mt-4"></div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    loadCrcB2FoldersLite(1);
                });

                function loadCrcB2FoldersLite(page) {
                    const result = document.getElementById('crcB2CollectResult');
                    const pagination = document.getElementById('crcB2Pagination');
                    if (result) result.innerHTML = '<div style="background:#fff;border:1px solid #bae6fd;border-radius:14px;padding:16px;font-size:12px;color:#0284c7;font-weight:800;">Memuat folder dari CRC Collector...</div>';
                    if (pagination) pagination.innerHTML = '';

                    const params = new URLSearchParams({
                        ajax_crc_b2_folders: '1',
                        search: document.getElementById('crcB2AdminInput')?.value || '',
                        date_start: document.getElementById('crcB2DateInput')?.value || '',
                        date_end: document.getElementById('crcB2DateInput')?.value || '',
                        page: String(page || 1),
                    });

                    fetch('berita_acara.php?' + params.toString())
                        .then(response => response.json())
                        .then(data => {
                            if (!result) return;
                            if (data.status !== 'success') throw new Error(data.message || 'Gagal memuat CRC Offline.');
                            const folders = data.folders || [];
                            if (folders.length === 0) {
                                result.innerHTML = '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;"><div style="font-size:13px;color:#374151;font-weight:900;">Belum ada data CRC Offline</div><div style="font-size:12px;color:#6b7280;margin-top:4px;line-height:1.5;">Data akan muncul setelah CRC Collector eksternal memproses dan mengirim hasil ke FTP/database. Coba ubah filter nomor admin atau tanggal proses.</div></div>';
                                return;
                            }
                            result.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">' + folders.map(folder => `
                                <div style="background:#fff;border:1px solid #e0f2fe;border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);">
                                    <div style="font-size:10px;font-weight:900;color:#0284c7;text-transform:uppercase;">CRC Collector</div>
                                    <div style="font-size:18px;font-weight:900;color:#111827;margin-top:2px;">${escapeHtml(folder.admin_no || '-')}</div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:4px;">${folder.count === null ? 'Klik lihat file untuk detail' : (folder.count || 0) + ' file .CRC'}${folder.processed_at ? ' &middot; ' + escapeHtml(folder.processed_at) : ''}</div>
                                    <div style="display:flex;gap:8px;margin-top:12px;">
                                        <button type="button" onclick="loadCrcB2FilesLite('${escapeAttr(folder.admin_no || '')}')" class="ba-action ba-action-primary">Lihat File</button>
                                        ${folder.download_url ? `<a href="${escapeAttr(folder.download_url)}" class="ba-action ba-action-neutral">Download</a>` : ''}
                                    </div>
                                </div>
                            `).join('') + '</div>';
                        })
                        .catch(error => {
                            if (result) result.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:18px;"><div style="font-size:13px;color:#dc2626;font-weight:900;">Gagal memuat CRC Offline</div><div style="font-size:12px;color:#991b1b;margin-top:4px;line-height:1.5;">' + escapeHtml(error.message) + '</div></div>';
                        });
                }

                function debouncedLoadCrcB2Folders() { loadCrcB2FoldersLite(1); }
                function loadCrcB2Folders(page) { loadCrcB2FoldersLite(page); }
                function resetCrcB2Filters() {
                    const input = document.getElementById('crcB2AdminInput');
                    if (input) input.value = '';
                    const dateInput = document.getElementById('crcB2DateInput');
                    if (dateInput) dateInput.value = '';
                    loadCrcB2FoldersLite(1);
                }
                function loadCrcB2FilesLite(adminNo) {
                    const result = document.getElementById('crcB2CollectResult');
                    if (result) result.innerHTML = '<div style="background:#fff;border:1px solid #bae6fd;border-radius:14px;padding:16px;font-size:12px;color:#0284c7;font-weight:800;">Memuat file CRC Collector untuk ' + escapeHtml(adminNo) + '...</div>';
                    fetch('berita_acara.php?ajax_crc_b2_files=1&admin_no=' + encodeURIComponent(adminNo))
                        .then(response => response.json())
                        .then(data => {
                            const files = data.files || [];
                            if (!result) return;
                            result.innerHTML = '<div style="background:#fff;border:1px solid #e0f2fe;border-radius:14px;padding:16px;">'
                                + '<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;"><div><div style="font-size:10px;font-weight:900;color:#0284c7;text-transform:uppercase;">File CRC Collector</div><div style="font-size:18px;font-weight:900;color:#111827;">' + escapeHtml(adminNo) + '</div><div style="font-size:11px;color:#6b7280;margin-top:3px;">' + files.length + ' file ditemukan</div></div><button type="button" onclick="loadCrcB2FoldersLite(1)" class="ba-action ba-action-neutral">Kembali</button></div>'
                                + (files.length === 0 ? '<div style="font-size:12px;color:#6b7280;line-height:1.5;">Tidak ada file .CRC untuk admin ini. Pastikan proses collector sudah selesai dan filter tanggal sesuai.</div>' : '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;">' + files.map(file => '<div style="border:1px solid #f1f5f9;border-radius:10px;padding:10px;font-size:12px;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escapeAttr(file.relative_path || file.file_name || '') + '">' + escapeHtml(file.file_name || '-') + '</div>').join('') + '</div>')
                                + '</div>';
                        });
                }
                function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char])); }
                function escapeAttr(value) { return escapeHtml(value); }
            </script>
        <?php } elseif ($uploadForId > 0) { ?>

        <div class="ba-card-panel">
            <div class="ba-card-header">
                <div>
                    <div class="ba-card-title">Upload File Test Document</div>
                    <div class="ba-card-meta">Receiver test untuk Filing ID <?php echo (int) $uploadForId ?></div>
                </div>
                <a href="berita_acara.php" class="ba-action ba-action-neutral">Kembali</a>
            </div>
            <div class="ba-card-item">
                <?php if (! $uploadTarget) { ?>
                    <div style="color:#dc2626;font-size:13px;font-weight:800;">Data filing tidak ditemukan atau tidak bisa diakses.</div>
                <?php } elseif (! $canUploadBeritaAcaraFiles) { ?>
                    <div style="color:#dc2626;font-size:13px;font-weight:800;">Anda tidak memiliki akses upload file.</div>
                <?php } else { ?>
                    <div class="ba-admin-line" style="margin-bottom:16px;">
                        <div class="ba-admin-no"><?php echo htmlspecialchars($uploadTarget['nomor_admin']) ?></div>
                        <span class="ba-pill ba-pill-gray"><?php echo htmlspecialchars(date('d M Y', strtotime($uploadTarget['tanggal']))) ?></span>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="max-width:520px;display:grid;gap:12px;">
                        <input type="hidden" name="file_action" value="upload">
                        <input type="hidden" name="filing_id" value="<?php echo (int) $uploadTarget['rec_id'] ?>">
                        <input type="hidden" name="nomor_admin" value="<?php echo htmlspecialchars($uploadTarget['nomor_admin']) ?>">

                        <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                            Kategori File
                            <select name="file_category" required style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                                <option value="berita_acara">Berita Acara Test PDF</option>
                                <option value="attendance">Absensi Final</option>
                                <option value="dokumen_support">Dokumen Support</option>
                                <option value="crc_individual">CRC Individu</option>
                            </select>
                        </label>

                        <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                            File
                            <input type="file" name="file" required style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                        </label>

                        <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                            Nama Custom Opsional
                            <input type="text" name="custom_file_name" placeholder="Kosongkan untuk pakai nama file asli" style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                        </label>

                        <button type="submit" class="ba-action ba-action-primary" style="width:max-content;">Upload File</button>
                    </form>
                    <div style="margin-top:14px;font-size:11px;color:#9ca3af;line-height:1.5;">Setelah submit, halaman akan kembali ke Test Document dan menampilkan notifikasi hasil upload.</div>
                <?php } ?>
            </div>

            <?php if ($testDocumentTotalPages > 1) { ?>
                <div style="padding:14px 20px;border-top:1px solid #f3f4f6;background:#f9fafb;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
                    <?php if ($testDocumentPage > 1) {
                        $prevQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage - 1]); ?>
                        <a href="berita_acara.php?<?php echo htmlspecialchars(http_build_query($prevQuery)) ?>" class="ba-action ba-action-neutral">Prev</a>
                    <?php } ?>
                    <span style="font-size:11px;font-weight:900;color:#374151;padding:0 6px;">Halaman <?php echo $testDocumentPage ?> dari <?php echo $testDocumentTotalPages ?></span>
                    <?php if ($testDocumentPage < $testDocumentTotalPages) {
                        $nextQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage + 1]); ?>
                        <a href="berita_acara.php?<?php echo htmlspecialchars(http_build_query($nextQuery)) ?>" class="ba-action ba-action-neutral">Next</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php } else { ?>

        <!-- FILTERS -->
        <div class="mb-6 flex flex-col sm:flex-row gap-2">
            <form method="GET" class="flex-1 relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search) ?>" placeholder="Cari nomor admin, nama sekolah, SPV, atau keterangan..." class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3 pl-10 shadow-sm">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400"><i class="fas fa-search"></i></div>
            </form>
            <?php if ($search || $f_date || $f_spv || $f_client) { ?>
                <a href="berita_acara" class="px-6 py-2.5 bg-red-50 text-red-600 rounded-lg text-sm font-bold hover:bg-red-100 transition flex items-center gap-2 shadow-sm"><i class="fas fa-times"></i> RESET</a>
            <?php } ?>
        </div>

        <div class="ba-card-panel">
            <div class="ba-card-header">
                <div>
                    <div class="ba-card-title">Daftar Nomor Admin</div>
                    <div class="ba-card-meta">Halaman <?php echo $testDocumentPage ?> / <?php echo $testDocumentTotalPages ?> &middot; <?php echo $testDocumentTotal ?> total data</div>
                </div>
                <?php if ($canManageBeritaAcara) { ?>
                    <a href="berita_acara.php?sync_assignments=1" class="ba-action ba-action-neutral">Sync Assignment</a>
                <?php } ?>
            </div>

            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;background:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div style="font-size:11px;font-weight:800;color:#6b7280;">Menampilkan <?php echo count($data_list) ?> dari <?php echo $testDocumentTotal ?> data</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($testDocumentPage > 1) {
                        $prevQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage - 1]); ?>
                        <a href="berita_acara.php?<?php echo htmlspecialchars(http_build_query($prevQuery)) ?>" class="ba-action ba-action-neutral">Prev</a>
                    <?php } ?>
                    <span style="font-size:11px;font-weight:900;color:#374151;">Page <?php echo $testDocumentPage ?></span>
                    <?php if ($testDocumentPage < $testDocumentTotalPages) {
                        $nextQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage + 1]); ?>
                        <a href="berita_acara.php?<?php echo htmlspecialchars(http_build_query($nextQuery)) ?>" class="ba-action ba-action-neutral">Next</a>
                    <?php } ?>
                </div>
            </div>

            <div class="ba-card-list">
                <?php if (empty($data_list)) { ?>
                    <div class="ba-card-item" style="text-align:center;color:#9ca3af;">Data tidak tampil untuk filter/akses saat ini.</div>
                <?php } ?>

                <?php foreach ($data_list as $row) {
                    $hasFilingRecord = (int) ($row['rec_id'] ?? 0) > 0;
                    $all_files = $row['files'] ?? [];
                    $checked_count = (int) ($row['issues_count'] ?? 0);
                    $finished_count = (int) ($row['finished_count'] ?? 0);
                    $total_count = (int) ($row['total_count'] ?? 0);
                    ?>
                    <div class="ba-card-item">
                        <div class="ba-card-row">
                            <div class="ba-card-main">
                                <div class="ba-admin-line">
                                    <?php if ($hasFilingRecord) { ?>
                                        <button type="button" onclick="openDetailModal(<?php echo $row['rec_id'] ?>, '<?php echo htmlspecialchars($row['nomor_admin'], ENT_QUOTES) ?>')" class="ba-admin-no" style="background:none;border:0;padding:0;cursor:pointer;text-align:left;"><?php echo htmlspecialchars($row['nomor_admin']) ?></button>
                                    <?php } else { ?>
                                        <div class="ba-admin-no"><?php echo htmlspecialchars($row['nomor_admin']) ?></div>
                                    <?php } ?>
                                    <?php if (! $hasFilingRecord) { ?>
                                        <span class="ba-pill ba-pill-amber">assignment</span>
                                    <?php } ?>
                                    <span class="ba-pill ba-pill-gray"><?php echo $finished_count ?>/<?php echo $total_count ?> peserta</span>
                                    <?php if ($checked_count > 0) { ?>
                                        <span class="ba-pill ba-pill-red"><?php echo $checked_count ?> issue</span>
                                    <?php } ?>
                                </div>
                                <div class="ba-info-grid">
                                    <div>
                                        <div class="ba-info-label">Klien</div>
                                        <div class="ba-info-value" title="<?php echo htmlspecialchars($row['client_name']) ?>"><?php echo htmlspecialchars($row['client_name']) ?></div>
                                    </div>
                                    <div>
                                        <div class="ba-info-label">Tanggal</div>
                                        <div class="ba-info-value"><?php echo date('d M Y', strtotime($row['tanggal'])) ?></div>
                                    </div>
                                    <div>
                                        <div class="ba-info-label">SPV</div>
                                        <div class="ba-info-value" title="<?php echo htmlspecialchars($row['spv_name']) ?>"><?php echo htmlspecialchars($row['spv_name']) ?></div>
                                    </div>
                                </div>
                                <?php if (! empty($row['keterangan'])) { ?>
                                    <div class="ba-note" title="<?php echo htmlspecialchars($row['keterangan']) ?>"><?php echo htmlspecialchars($row['keterangan']) ?></div>
                                <?php } ?>
                            </div>

                            <div class="ba-card-actions">
                                <?php if ($hasFilingRecord) { ?>
                                    <button onclick="openDetailModal(<?php echo $row['rec_id'] ?>, '<?php echo htmlspecialchars($row['nomor_admin'], ENT_QUOTES) ?>')" class="ba-action ba-action-primary">Detail File</button>
                                    <?php if ($canUploadBeritaAcaraFiles) { ?>
                                        <button type="button" onclick="openUploadModal(<?php echo (int) $row['rec_id'] ?>, '<?php echo htmlspecialchars($row['nomor_admin'], ENT_QUOTES) ?>')" class="ba-action ba-action-neutral">Upload File</button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <a href="berita_acara.php?sync_assignments=1" class="ba-action ba-action-amber">Sync Untuk Files</a>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($hasFilingRecord) { ?>
                            <div id="submenu_<?php echo $row['rec_id'] ?>" class="ba-detail-panel hidden">
                                <div id="file_list_collect_date_<?php echo $row['rec_id'] ?>" style="font-size:12px;color:#6b7280;">Klik Detail File untuk memuat data file.</div>
                                <div id="file_list_outbound_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <div id="file_list_crc_raw_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <div id="file_list_crc_individual_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <div id="file_list_crc_gabungan_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <div id="file_list_berita_acara_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <div id="file_list_dokumen_support_<?php echo $row['rec_id'] ?>" class="hidden"></div>
                                <span id="count_outbound_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_crc_raw_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_crc_individual_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_crc_gabungan_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_berita_acara_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_dokumen_support_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                                <span id="count_collect_date_<?php echo $row['rec_id'] ?>" class="hidden">0</span>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div id="uploadModalLite" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:20px;">
            <div style="width:min(520px,100%);background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.32);overflow:hidden;">
                <div style="padding:18px 20px;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-size:14px;font-weight:900;text-transform:uppercase;">Upload File</div>
                        <div id="uploadModalAdmin" style="font-size:11px;opacity:.8;margin-top:2px;">-</div>
                    </div>
                    <button type="button" onclick="closeUploadModal()" style="background:rgba(255,255,255,.16);border:0;color:#fff;border-radius:10px;width:34px;height:34px;cursor:pointer;">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding:20px;display:grid;gap:12px;">
                    <input type="hidden" name="file_action" value="upload">
                    <input type="hidden" name="filing_id" id="uploadModalFilingId" value="">
                    <input type="hidden" name="nomor_admin" id="uploadModalNomorAdmin" value="">
                    <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                        Kategori File
                        <select name="file_category" required style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                            <option value="berita_acara">Berita Acara Test PDF</option>
                            <option value="attendance">Absensi Final</option>
                            <option value="dokumen_support">Dokumen Support</option>
                            <option value="crc_individual">CRC Individu</option>
                        </select>
                    </label>
                    <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                        File
                        <input type="file" name="file" required style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                    </label>
                    <label style="display:grid;gap:6px;font-size:11px;font-weight:900;color:#6b7280;text-transform:uppercase;">
                        Nama Custom Opsional
                        <input type="text" name="custom_file_name" placeholder="Kosongkan untuk pakai nama file asli" style="border:1px solid #d1d5db;border-radius:10px;padding:10px;font-size:13px;color:#111827;text-transform:none;font-weight:600;">
                    </label>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
                        <button type="button" onclick="closeUploadModal()" class="ba-action ba-action-neutral">Batal</button>
                        <button type="submit" class="ba-action ba-action-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="detailModalLite" style="display:none;position:fixed;inset:0;z-index:999998;background:rgba(15,23,42,.58);align-items:center;justify-content:center;padding:20px;">
            <div style="width:min(980px,100%);max-height:88vh;background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.34);overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:18px 20px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-size:14px;font-weight:900;text-transform:uppercase;">Detail File Test Document</div>
                        <div id="detailModalAdmin" style="font-size:11px;opacity:.78;margin-top:2px;">-</div>
                    </div>
                    <button type="button" onclick="closeDetailModal()" style="background:rgba(255,255,255,.12);border:0;color:#fff;border-radius:10px;width:34px;height:34px;cursor:pointer;">&times;</button>
                </div>
                <div id="detailModalBody" style="padding:18px;overflow:auto;">
                    <div style="font-size:12px;color:#6b7280;font-weight:800;">Memuat detail file...</div>
                </div>
            </div>
        </div>

        <script>
            function toggleSubMenu(id) {
                const row = document.getElementById('submenu_' + id);
                if (!row) return;
                row.classList.toggle('hidden');
                if (!row.classList.contains('hidden') && row.dataset.loaded !== '1') {
                    loadDetailFiles(id, row);
                }
            }

            function loadDetailFiles(id, row) {
                const target = document.getElementById('file_list_collect_date_' + id);
                if (target) target.innerHTML = 'Memuat detail file...';
                fetch('berita_acara.php?ajax_get_submenu=' + encodeURIComponent(id))
                    .then(response => response.json())
                    .then(data => {
                        row.dataset.loaded = '1';
                        renderDetailFiles(id, data.files_by_category || {});
                    })
                    .catch(error => {
                        if (target) target.innerHTML = '<span style="color:#dc2626;font-weight:800;">Gagal memuat detail file: ' + escapeHtmlLite(error.message) + '</span>';
                    });
            }

            function renderDetailFiles(id, filesByCategory) {
                const target = document.getElementById('file_list_collect_date_' + id);
                if (!target) return;
                target.innerHTML = buildDetailFilesHtml(filesByCategory);
            }

            function openDetailModal(id, nomorAdmin) {
                const modal = document.getElementById('detailModalLite');
                const title = document.getElementById('detailModalAdmin');
                const body = document.getElementById('detailModalBody');
                if (!modal || !title || !body) return;
                detailModalCurrentId = id;
                title.textContent = 'ADMIN: ' + nomorAdmin;
                body.innerHTML = '<div style="font-size:12px;color:#6b7280;font-weight:800;">Memuat detail file...</div>';
                modal.style.display = 'flex';

                fetch('berita_acara.php?ajax_get_submenu=' + encodeURIComponent(id))
                    .then(response => response.json())
                    .then(data => {
                        body.innerHTML = buildDetailFilesHtml(data.files_by_category || {});
                    })
                    .catch(error => {
                        body.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px;color:#dc2626;font-size:12px;font-weight:800;">Gagal memuat detail file: ' + escapeHtmlLite(error.message) + '</div>';
                    });
            }

            function closeDetailModal() {
                document.getElementById('detailModalLite').style.display = 'none';
            }

            const BA_CAN_DELETE_FILES = <?php echo $canManageBeritaAcara ? 'true' : 'false'; ?>;
            let detailModalCurrentId = 0;

            function buildDetailFilesHtml(filesByCategory) {
                const labels = {
                    outbound: 'Outbound',
                    crc_raw: 'CRC RAW',
                    crc_individual: 'CRC Individu',
                    crc_gabungan: 'CRC Gabungan',
                    berita_acara: 'BA Test',
                    dokumen_support: 'Dokumen Support',
                };
                const categories = Object.keys(labels);
                const html = categories.map(category => {
                    const files = filesByCategory[category] || [];
                    return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">'
                        + '<div style="font-size:10px;font-weight:900;color:#4f46e5;text-transform:uppercase;margin-bottom:8px;">' + labels[category] + ' (' + files.length + ')</div>'
                        + (files.length === 0 ? '<div style="font-size:11px;color:#9ca3af;">Belum ada file.</div>' : files.map(file => {
                            const downloadUrl = file.download_url || (file.file_id ? 'berita_acara.php?download_file=' + encodeURIComponent(file.file_id) : '#');
                            let actions = '<a href="' + escapeAttrLite(downloadUrl) + '" class="ba-action ba-action-neutral">Download</a>';
                            if (BA_CAN_DELETE_FILES && file.file_id) {
                                actions += ' <button type="button" onclick="deleteDetailFile(this)" data-file-id="' + escapeAttrLite(file.file_id) + '" data-file-name="' + escapeAttrLite(file.file_name || '-') + '" class="ba-action ba-action-danger">Hapus</button>';
                            }
                            return '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid #f3f4f6;padding-top:8px;margin-top:8px;">'
                                + '<div style="min-width:0;"><div style="font-size:12px;font-weight:800;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + escapeAttrLite(file.file_name || '-') + '">' + escapeHtmlLite(file.file_name || '-') + '</div><div style="font-size:10px;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + escapeAttrLite(file.relative_path || file.file_path || '') + '">' + escapeHtmlLite(file.relative_path || file.file_path || '') + '</div></div>'
                                + '<div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">' + actions + '</div>'
                                + '</div>';
                        }).join(''))
                        + '</div>';
                }).join('');

                return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">' + html + '</div>';
            }

            function deleteDetailFile(button) {
                const fileId = button ? button.getAttribute('data-file-id') : '';
                const fileName = button ? button.getAttribute('data-file-name') : '';
                if (!fileId) return;
                if (!confirm('Hapus file "' + fileName + '"?')) return;
                const body = new URLSearchParams();
                body.set('file_action', 'delete');
                body.set('file_id', fileId);
                fetch('berita_acara.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            alert('Gagal menghapus file: ' + (data.msg || 'Kesalahan tidak diketahui.'));
                            return;
                        }
                        const panel = button ? button.closest('.ba-detail-panel') : null;
                        const panelId = panel ? panel.id.replace(/^submenu_/, '') : '';
                        if (panel && panelId) {
                            loadDetailFiles(panelId, panel);
                        }
                        const modal = document.getElementById('detailModalLite');
                        if (modal && modal.style.display === 'flex' && detailModalCurrentId) {
                            const nomorAdmin = document.getElementById('detailModalAdmin').textContent.replace(/^ADMIN:\s*/, '').trim();
                            openDetailModal(detailModalCurrentId, nomorAdmin);
                        }
                    })
                    .catch(error => alert('Gagal menghapus file: ' + error.message));
            }

            function escapeHtmlLite(value) { return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char])); }
            function escapeAttrLite(value) { return escapeHtmlLite(value); }

            function openUploadModal(filingId, nomorAdmin) {
                document.getElementById('uploadModalFilingId').value = filingId;
                document.getElementById('uploadModalNomorAdmin').value = nomorAdmin;
                document.getElementById('uploadModalAdmin').textContent = 'ADMIN: ' + nomorAdmin;
                document.getElementById('uploadModalLite').style.display = 'flex';
            }

            function closeUploadModal() {
                document.getElementById('uploadModalLite').style.display = 'none';
            }
        </script>

        </div>
    </div>
</div>

<?php require_once BASE_PATH.'/includes/layout_footer.php'; exit; ?>

        <?php if (false) { ?>
        <div>
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <div class="text-sm font-black text-gray-800 uppercase tracking-tight">Daftar Nomor Admin</div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase mt-0.5"><?php echo count($data_list) ?> data ditampilkan</div>
                </div>
                <?php if ($canManageBeritaAcara) { ?>
                    <a href="berita_acara.php?sync_assignments=1" class="px-3 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-[10px] font-black uppercase hover:bg-indigo-50 hover:text-indigo-600 transition">Sync Assignment</a>
                <?php } ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm text-left text-gray-500 table-fixed">
                    <thead class="text-[10px] text-gray-500 uppercase bg-white border-b border-gray-100">
                        <tr>
                            <th class="w-[210px] px-5 py-3">Nomor Admin</th>
                            <th class="w-[260px] px-5 py-3">Klien</th>
                            <th class="w-[180px] px-5 py-3">Keterangan</th>
                            <th class="w-[110px] px-5 py-3">Tanggal</th>
                            <th class="w-[90px] px-5 py-3">Issue</th>
                            <th class="w-[130px] px-5 py-3">File</th>
                            <th class="w-[120px] px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($data_list)) { ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="max-w-xl mx-auto bg-amber-50 border border-amber-100 rounded-2xl p-6">
                                        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <div class="text-sm font-black text-gray-800 uppercase tracking-tight">Data tidak tampil untuk filter/akses saat ini</div>
                                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                            Database Filing System memiliki data, tetapi daftar ini mengikuti filter tanggal/search/client dan akses SPV yang sedang login.
                                        </p>
                                        <div class="flex flex-wrap justify-center gap-2 mt-4">
                                            <a href="berita_acara.php" class="px-4 py-2 bg-white border border-amber-200 text-amber-700 rounded-lg text-xs font-bold hover:bg-amber-100 transition">
                                                RESET FILTER
                                            </a>
                                            <?php if ($canManageBeritaAcara) { ?>
                                                <a href="berita_acara.php?sync_assignments=1" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-xs font-bold hover:bg-amber-700 transition">
                                                    SYNC ASSIGNMENT
                                                </a>
                                            <?php } ?>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-3 font-mono">
                                            User ID: <?php echo (int) $userId ?> &middot; SPV: <?php echo htmlspecialchars((string) $spv_name_active) ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } ?>
                        <?php foreach ($data_list as $row) {
                            $hasFilingRecord = (int) ($row['rec_id'] ?? 0) > 0;
                            $all_files = $row['files'] ?? [];
                            $checked_count = (int) ($row['issues_count'] ?? 0);
                            $finished_count = (int) ($row['finished_count'] ?? 0);
                            $total_count = (int) ($row['total_count'] ?? 0);
                            ?>
                            <tr class="hover:bg-indigo-50/30 transition border-b border-gray-100 align-top">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <?php if ($hasFilingRecord) { ?>
                                            <button onclick="toggleSubMenu(<?php echo $row['rec_id'] ?>)" class="flex items-center gap-2 hover:text-indigo-600 transition text-left group">
                                                <i id="icon_<?php echo $row['rec_id'] ?>" class="fas fa-chevron-right text-[10px] text-gray-300 group-hover:text-indigo-400 transition-transform"></i>
                                                <span class="font-black text-gray-900 tracking-tight"><?php echo htmlspecialchars($row['nomor_admin']) ?></span>
                                            </button>
                                        <?php } else { ?>
                                            <span class="font-black text-gray-900 tracking-tight"><?php echo htmlspecialchars($row['nomor_admin']) ?></span>
                                            <span class="text-[9px] bg-amber-50 text-amber-600 border border-amber-100 px-1.5 py-0.5 rounded font-bold uppercase">assignment</span>
                                        <?php } ?>
                                        <span class="shrink-0 text-[9px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-bold">
                                            <?php echo $finished_count ?>/<?php echo $total_count ?>
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase mt-1 truncate" title="<?php echo htmlspecialchars($row['spv_name']) ?>">SPV: <?php echo htmlspecialchars($row['spv_name']) ?></div>
                                </td>
                                <td class="px-5 py-3 font-semibold text-gray-700 truncate" title="<?php echo htmlspecialchars($row['client_name']) ?>"><?php echo htmlspecialchars($row['client_name']) ?></td>
                                <td class="px-5 py-3">
                                    <p class="truncate text-[11px] text-gray-500" title="<?php echo htmlspecialchars($row['keterangan']) ?>"><?php echo htmlspecialchars($row['keterangan'] ?: '-') ?></p>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-xs font-bold"><?php echo date('d M Y', strtotime($row['tanggal'])) ?></div>
                                </td>
                                <td class="px-5 py-3">
                                    <?php if ($checked_count > 0) { ?>
                                        <div class="flex items-center gap-1">
                                            <span class="px-2 py-1 bg-red-50 text-red-600 text-[10px] font-bold rounded border border-red-100"><?php echo $checked_count ?> ISSUE</span>
                                            <a href="berita_acara?download_issues=<?php echo $row['rec_id'] ?>" class="p-1 bg-white border border-red-200 text-red-600 rounded hover:bg-red-50 transition" title="Download Report"><i class="fas fa-download text-[10px]"></i></a>
                                        </div>
                                    <?php } else { ?>
                                        <span class="text-gray-300 text-[10px]">-</span>
                                    <?php } ?>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-col gap-1">
                                        <?php
                                            if (empty($all_files)) { ?>
                                            <span class="text-[10px] text-gray-400 italic">No files</span>
                                        <?php } else { ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($all_files as $f) { ?>
                                                    <a href="berita_acara?download_file=<?php echo $f['file_id'] ?>" class="p-1 hover:bg-indigo-50 rounded transition group relative" title="<?php echo htmlspecialchars($f['file_name']) ?>">
                                                        <?php echo get_file_icon($f['file_type']) ?>
                                                        <span class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-800 text-white text-[9px] rounded whitespace-nowrap z-10"><?php echo htmlspecialchars($f['file_name']) ?></span>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                        <?php if ($canUploadBeritaAcaraFiles && $hasFilingRecord) { ?>
                                            <button onclick="openFileManager(<?php echo $row['rec_id'] ?>, '<?php echo $row['nomor_admin'] ?>')" class="text-[9px] font-bold text-indigo-600 hover:underline text-left mt-1 tracking-tighter uppercase">MANAGE FILES</button>
                                        <?php } elseif ($canUploadBeritaAcaraFiles) { ?>
                                            <a href="berita_acara.php?sync_assignments=1" class="text-[9px] font-bold text-amber-600 hover:underline text-left mt-1 tracking-tighter uppercase">SYNC UNTUK MANAGE FILES</a>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <?php if ($hasFilingRecord) { ?>
                                            <a href="berita_acara?download_admin_folder=<?php echo $row['rec_id'] ?>" class="p-2 bg-green-50 text-green-600 rounded hover:bg-green-100 transition" title="Download Folder Admin ZIP"><i class="fas fa-download"></i></a>
                                        <?php } ?>
                                        <?php if ($canManageBeritaAcara && $hasFilingRecord) { ?>
                                            <button onclick="editEntry(<?php echo $row['rec_id'] ?>)" class="p-2 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100 transition"><i class="fas fa-edit"></i></button>
                                            <a href="berita_acara?delete_entry=<?php echo $row['rec_id'] ?>" onclick="return confirm('Hapus data?')" class="p-2 bg-red-50 text-red-500 rounded hover:bg-red-100 transition"><i class="fas fa-trash-alt"></i></a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                            <!-- SUB MENU ROW -->
                            <?php if ($hasFilingRecord) { ?>
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
                                            */ ?>
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
                                        */ ?>

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
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>
        <?php } ?>
    </div>
</div>

<!-- MODAL CRC B2 FILES -->
<div id="modalCrcB2Files" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center backdrop-blur-sm transition-opacity p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden border-t-4 border-sky-600">
        <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] font-black text-sky-600 uppercase tracking-widest">CRC Collector Files</div>
                <h3 id="crcB2ModalTitle" class="text-xl font-black text-gray-800 mt-1">-</h3>
                <p id="crcB2ModalMeta" class="text-xs text-gray-400 mt-1">Memuat data...</p>
            </div>
            <button type="button" onclick="closeCrcB2FilesModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div id="crcB2ModalBody" class="p-5 max-h-[60vh] overflow-y-auto">
            <div class="text-xs text-sky-600 font-bold"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat file...</div>
        </div>
        <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" onclick="closeCrcB2FilesModal()" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 transition">TUTUP</button>
            <a id="crcB2ModalDownload" href="#" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-xs font-bold hover:bg-sky-700 transition flex items-center gap-2">
                <i class="fas fa-download"></i> DOWNLOAD ZIP
            </a>
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
foreach ($assigned_admins as $adm) {
    if (in_array($adm['admin_no'], $seen_admin)) {
        continue;
    }

    $seen_admin[] = $adm['admin_no'];
    ?>
                            <option value="<?php echo htmlspecialchars($adm['admin_no']) ?>" <?php echo $f_admin == $adm['admin_no'] ? 'selected' : '' ?>><?php echo htmlspecialchars($adm['admin_no']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase">Nama Klien / Institusi</label>
                    <select name="f_client" id="f_client" class="select2-modal w-full">
                        <option value="">-- Semua Klien --</option>
                        <?php foreach ($client_list as $c) { ?>
                            <option value="<?php echo htmlspecialchars($c) ?>" <?php echo $f_client == $c ? 'selected' : '' ?>><?php echo htmlspecialchars($c) ?></option>
                        <?php } ?>
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
                            <?php foreach ($assigned_admins as $adm) { ?>
                                <option value="<?php echo $adm['admin_id'].'|'.$adm['sub_admin_id'] ?>"><?php echo htmlspecialchars($adm['admin_no']) ?></option>
                            <?php } ?>
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

                    <div id="filingDropZone" class="border-2 border-dashed border-indigo-200 bg-indigo-50/40 rounded-xl p-4 text-center cursor-pointer hover:bg-indigo-50 transition">
                        <input type="file" name="files[]" id="fileInput" multiple required class="hidden">
                        <div class="text-indigo-600 text-xl mb-1"><i class="fas fa-file-upload"></i></div>
                        <div class="text-xs font-bold text-gray-800">Drop banyak file di sini</div>
                        <div class="text-[9px] text-gray-400 mt-1">Untuk CRC Individu, drop maksimal 500 file .CRC sekaligus.</div>
                        <div id="filingSelectedFiles" class="text-[10px] text-indigo-600 font-bold mt-2">Belum ada file dipilih.</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="text" name="custom_file_name" id="custom_file_name" placeholder="Nama file (opsional, contoh: Laporan Sesi 1)" class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                        <button type="submit" id="btnUpload" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 shadow-sm transition flex items-center gap-2">
                            <i class="fas fa-cloud-upload-alt"></i> UPLOAD
                        </button>
                    </div>
                    <p class="text-[9px] text-gray-400 font-medium">Khusus CRC Individu: maksimal 500 file, 1MB per file, total 10MB, disimpan ke <span class="text-indigo-500 italic">YYYYMMDD/CRC</span>.</p>
                </form>
            </div>
            <div class="max-h-60 overflow-y-auto"><table class="w-full text-xs text-left text-gray-500"><thead class="text-[9px] text-gray-400 uppercase bg-gray-50 border-b border-gray-200"><tr><th class="px-4 py-2">Nama File</th><th class="px-4 py-2 text-right">Aksi</th></tr></thead><tbody id="fileListBody" class="divide-y divide-gray-100"></tbody></table></div>
        </div>
    </div>
</div>

<script>

const CAN_DELETE_BERITA_ACARA_FILES = <?php echo $canManageBeritaAcara ? 'true' : 'false' ?>;
const ACTIVE_BERITA_ACARA_VIEW = '<?php echo htmlspecialchars($activeView, ENT_QUOTES) ?>';
let CRC_B2_FOLDERS = [];
let CRC_B2_SEARCH_TIMER = null;
let CRC_B2_CURRENT_PAGE = 1;

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

function getCrcStatusBadge(status) {
    const classes = {
        done: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        failed: 'bg-red-50 text-red-600 border-red-100',
        processing: 'bg-blue-50 text-blue-600 border-blue-100',
        queued: 'bg-gray-50 text-gray-500 border-gray-100',
    };
    const label = status || 'unknown';
    return `<span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full border ${classes[label] || 'bg-gray-50 text-gray-500 border-gray-100'}">${label}</span>`;
}

function formatFileSize(bytes) {
    const size = parseInt(bytes || 0, 10);
    if (size <= 0) return '';
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / 1024 / 1024).toFixed(1) + ' MB';
}

function initViewTransitions() {
    const overlay = document.getElementById('viewTransitionOverlay');
    if (!overlay) return;
    overlay.style.display = 'none';
    overlay.classList.remove('is-active');

    document.querySelectorAll('[data-view-transition]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const targetUrl = new URL(link.href, window.location.href);
            const currentUrl = new URL(window.location.href);
            if (targetUrl.href === currentUrl.href) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            overlay.style.display = 'flex';
            overlay.classList.add('is-active');
            setTimeout(() => {
                window.location.href = targetUrl.href;
            }, 120);
        });
    });
}

function normalizeAdminSearch(value) {
    return String(value || '').toLowerCase().replace(/^b0*/i, '').replace(/^0+/, '');
}

function renderCrcB2Folders(folders) {
    const result = document.getElementById('crcB2CollectResult');
    if (!result) return;

    if (!folders || folders.length === 0) {
        result.innerHTML = '<div class="bg-white border border-sky-100 rounded-xl p-4 text-xs text-gray-500">Belum ada folder nomor admin yang terbaca dari CRC Collector.</div>';
        return;
    }

    result.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            ${folders.map((folder, index) => `
                <div class="bg-white border border-sky-100 rounded-xl p-4 shadow-sm" data-crc-b2-card data-admin-no="${folder.admin_no}" data-download-url="${folder.download_url || ''}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black text-sky-600 uppercase tracking-widest">Nomor Admin</div>
                            <div class="text-lg font-black text-gray-800 truncate">${folder.admin_no}</div>
                            <div class="text-xs text-gray-500 mt-1">${folder.count === null ? 'Klik Lihat File untuk hitung .CRC' : (folder.count || 0) + ' file .CRC'}${folder.processed_at ? ' &middot; ' + folder.processed_at : ''}</div>
                            ${folder.source === 'db' ? `
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span class="text-[9px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full px-2 py-0.5">DONE ${folder.done_files || 0}</span>
                                    <span class="text-[9px] font-bold bg-blue-50 text-blue-600 border border-blue-100 rounded-full px-2 py-0.5">PROCESS ${folder.processing_files || 0}</span>
                                    <span class="text-[9px] font-bold bg-gray-50 text-gray-500 border border-gray-100 rounded-full px-2 py-0.5">QUEUE ${folder.queued_files || 0}</span>
                                    <span class="text-[9px] font-bold bg-red-50 text-red-600 border border-red-100 rounded-full px-2 py-0.5">FAILED ${folder.failed_files || 0}</span>
                                </div>
                            ` : ''}
                        </div>
                        <a href="${folder.download_url}" class="shrink-0 px-3 py-2 bg-sky-600 text-white rounded-lg text-[10px] font-bold hover:bg-sky-700 transition">
                            <i class="fas fa-download mr-1"></i> ZIP
                        </a>
                    </div>
                    <button type="button" class="mt-3 text-[10px] font-bold text-sky-600 hover:underline uppercase" data-crc-b2-open>Lihat File</button>
                </div>
            `).join('')}
        </div>
    `;
    initCrcB2FolderToggles();
}

function getCrcB2DateRange() {
    const value = (document.getElementById('crcB2DateInput')?.value || '').trim();
    if (!value) return { start: '', end: '' };
    const parts = value.split(' to ');
    return { start: parts[0] || '', end: parts[1] || parts[0] || '' };
}

function debouncedLoadCrcB2Folders() {
    clearTimeout(CRC_B2_SEARCH_TIMER);
    CRC_B2_SEARCH_TIMER = setTimeout(() => loadCrcB2Folders(1), 400);
}

function resetCrcB2Filters() {
    const search = document.getElementById('crcB2AdminInput');
    const date = document.getElementById('crcB2DateInput');
    if (search) search.value = '';
    if (date) date.value = '';
    loadCrcB2Folders(1);
}

function loadCrcB2Folders(page = CRC_B2_CURRENT_PAGE) {
    CRC_B2_CURRENT_PAGE = parseInt(page || 1, 10);
    const result = document.getElementById('crcB2CollectResult');
    const paginationEl = document.getElementById('crcB2Pagination');
    if (result) {
        result.innerHTML = '<div class="bg-white border border-sky-100 rounded-xl p-4 text-xs text-sky-600 font-bold"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat folder CRC...</div>';
    }
    if (paginationEl) paginationEl.innerHTML = '';

    const dateRange = getCrcB2DateRange();
    const params = new URLSearchParams({
        ajax_crc_b2_folders: '1',
        search: document.getElementById('crcB2AdminInput')?.value || '',
        date_start: dateRange.start,
        date_end: dateRange.end,
        page: String(CRC_B2_CURRENT_PAGE),
    });

    fetch('berita_acara.php?' + params.toString())
        .then(parseJsonResponse)
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message || 'Gagal memuat folder CRC.');
            CRC_B2_FOLDERS = data.folders || [];
            renderCrcB2Folders(CRC_B2_FOLDERS);
            renderCrcB2Pagination(data.pagination || null);
            if (CRC_B2_FOLDERS.length === 0 && data.diagnostics) {
                renderCrcB2Diagnostics(data.diagnostics);
            }
        })
        .catch(err => {
            console.error(err);
            if (result) result.innerHTML = '<div class="bg-red-50 border border-red-100 rounded-xl p-4 text-xs text-red-600 font-bold">Gagal memuat folder CRC: ' + err.message + '</div>';
        });
}

function renderCrcB2Diagnostics(diagnostics) {
    const result = document.getElementById('crcB2CollectResult');
    if (!result) return;

    const attempts = diagnostics.ftp && diagnostics.ftp.attempts
        ? diagnostics.ftp.attempts.map(attempt => `${attempt.path} (${attempt.nlist_ok ? 'ok' : 'fail'}, ${attempt.nlist_count || 0} item)`).join('<br>')
        : '-';

    result.innerHTML = `
        <div class="bg-white border border-amber-100 rounded-xl p-4 text-xs text-gray-600">
            <div class="font-bold text-amber-600 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>Folder collector/final belum terbaca.</div>
            <div>Local root: <span class="font-mono">${diagnostics.local_root || '-'}</span></div>
            <div>Local exists/readable: ${diagnostics.local_root_exists ? 'yes' : 'no'} / ${diagnostics.local_root_readable ? 'yes' : 'no'}</div>
            <div>Receiver: <span class="font-mono">${diagnostics.receiver_url || '-'}</span> (${diagnostics.receiver_available ? 'available' : 'not available'})</div>
            <div class="mt-2">FTP path yang dicoba:</div>
            <div class="font-mono text-[10px] bg-gray-50 border border-gray-100 rounded p-2 mt-1 max-h-40 overflow-y-auto">${attempts}</div>
            ${diagnostics.ftp_error ? `<div class="mt-2 text-red-500 font-bold">FTP error: ${diagnostics.ftp_error}</div>` : ''}
        </div>
    `;
}

function initCrcB2FolderToggles() {
    const result = document.getElementById('crcB2CollectResult');
    if (!result || result.dataset.toggleBound === '1') return;

    result.dataset.toggleBound = '1';
    result.addEventListener('click', event => {
        const button = event.target.closest('[data-crc-b2-open]');
        if (!button || !result.contains(button)) return;

        event.preventDefault();
        const card = button.closest('[data-crc-b2-card]');
        openCrcB2FilesModal(card?.dataset.adminNo || '', card?.dataset.downloadUrl || '#');
    });
}

function openCrcB2FilesModal(adminNo, downloadUrl) {
    if (!adminNo) return;

    const modal = document.getElementById('modalCrcB2Files');
    const title = document.getElementById('crcB2ModalTitle');
    const meta = document.getElementById('crcB2ModalMeta');
    const body = document.getElementById('crcB2ModalBody');
    const download = document.getElementById('crcB2ModalDownload');
    if (!modal || !title || !meta || !body || !download) return;

    title.textContent = adminNo;
    meta.textContent = 'Memuat daftar file .CRC...';
    body.innerHTML = '<div class="text-xs text-sky-600 font-bold"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat file...</div>';
    download.href = downloadUrl || '#';
    modal.classList.remove('hidden');

    fetch('berita_acara.php?ajax_crc_b2_files=1&admin_no=' + encodeURIComponent(adminNo))
        .then(parseJsonResponse)
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message || 'Gagal memuat file.');
            const files = data.files || [];
            meta.textContent = files.length + ' file .CRC tersedia.';
            body.innerHTML = `
                <div class="flex flex-wrap gap-2">
                    ${files.length === 0 ? '<div class="text-[10px] text-gray-400 italic">Tidak ada file .CRC.</div>' : files.map(f => `
                    <div class="max-w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2" title="${f.relative_path || f.file_name}">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="shrink-0">${getFileTypeIcon('crc')}</span>
                            <span class="text-[11px] font-semibold text-gray-700 truncate max-w-[220px]">${f.file_name}</span>
                            ${f.status ? getCrcStatusBadge(f.status) : ''}
                        </div>
                        <div class="text-[9px] text-gray-400 mt-1 flex flex-wrap gap-x-2 gap-y-0.5">
                            ${f.authorize_no ? `<span>AUTH: ${f.authorize_no}</span>` : ''}
                            ${f.file_size ? `<span>${formatFileSize(f.file_size)}</span>` : ''}
                            ${f.uploaded_at ? `<span>${f.uploaded_at}</span>` : ''}
                        </div>
                        ${f.error_message ? `<div class="text-[9px] text-red-500 mt-1 max-w-[260px] truncate" title="${f.error_message}">${f.error_message}</div>` : ''}
                    </div>
                    `).join('')}
                </div>
            `;
        })
        .catch(err => {
            meta.textContent = 'Gagal memuat file.';
            body.innerHTML = '<div class="text-xs text-red-500 font-bold">' + err.message + '</div>';
        });
}

function renderCrcB2Pagination(pagination) {
    const el = document.getElementById('crcB2Pagination');
    if (!el || !pagination) return;

    const page = parseInt(pagination.page || 1, 10);
    const totalPages = parseInt(pagination.total_pages || 1, 10);
    const total = parseInt(pagination.total || 0, 10);
    const perPage = parseInt(pagination.per_page || 20, 10);

    if (total <= perPage && totalPages <= 1) {
        el.innerHTML = `<div class="text-[10px] text-gray-400 font-bold uppercase">Total ${total} nomor admin</div>`;
        return;
    }

    const prevDisabled = page <= 1;
    const nextDisabled = page >= totalPages;
    el.innerHTML = `
        <div class="bg-white border border-sky-100 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-[10px] text-gray-500 font-bold uppercase">Page ${page} / ${totalPages} &middot; Total ${total} nomor admin</div>
            <div class="flex items-center gap-2">
                <button type="button" ${prevDisabled ? 'disabled' : ''} onclick="loadCrcB2Folders(${page - 1})" class="px-3 py-1.5 rounded-lg text-[10px] font-bold border ${prevDisabled ? 'bg-gray-50 text-gray-300 border-gray-100 cursor-not-allowed' : 'bg-white text-sky-600 border-sky-100 hover:bg-sky-50'}">PREV</button>
                <button type="button" ${nextDisabled ? 'disabled' : ''} onclick="loadCrcB2Folders(${page + 1})" class="px-3 py-1.5 rounded-lg text-[10px] font-bold border ${nextDisabled ? 'bg-gray-50 text-gray-300 border-gray-100 cursor-not-allowed' : 'bg-white text-sky-600 border-sky-100 hover:bg-sky-50'}">NEXT</button>
            </div>
        </div>
    `;
}

function closeCrcB2FilesModal() {
    const modal = document.getElementById('modalCrcB2Files');
    if (modal) modal.classList.add('hidden');
}

function renderCrcB2CollectResult(files, summary) {
    const result = document.getElementById('crcB2CollectResult');
    if (!result) return;

    if (!files || files.length === 0) {
        result.innerHTML = '<div class="bg-white border border-sky-100 rounded-xl p-4 text-xs text-gray-500">CRC belum ditemukan.</div>';
        return;
    }

    const previewFiles = files.slice(0, 12).map(f => `
        <div class="bg-white border border-gray-100 rounded-lg p-2 flex items-start gap-2">
            <div class="shrink-0 mt-0.5">${getFileTypeIcon('crc')}</div>
            <div class="flex-1 min-w-0">
                <div class="text-[11px] font-semibold text-gray-700 truncate" title="${f.file_name}">${f.file_name}</div>
                <div class="text-[9px] text-gray-400 truncate" title="${f.relative_path || ''}">${f.relative_path || ''}</div>
            </div>
        </div>
    `).join('');

    result.innerHTML = `
        <div class="bg-white border border-sky-100 rounded-xl p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <div>
                    <div class="text-[10px] font-black text-sky-600 uppercase tracking-widest">Hasil ditemukan</div>
                    <div class="text-lg font-black text-gray-800 mt-1">${summary.admin_no || '-'}</div>
                    <div class="text-xs text-gray-500 mt-1">${files.length} file .CRC${summary.processed_at ? ' &middot; Terakhir diproses ' + summary.processed_at : ''}</div>
                </div>
                <a href="${summary.download_url || '#'}" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-xs font-bold hover:bg-sky-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i> DOWNLOAD ZIP
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">${previewFiles}</div>
            ${files.length > 12 ? `<div class="text-[10px] text-gray-400 italic mt-3">Menampilkan 12 dari ${files.length} file. Download ZIP untuk mengambil semua file.</div>` : ''}
        </div>
    `;
}

async function parseJsonResponse(response) {
    const text = await response.text();

    console.log('HTTP STATUS:', response.status);
    console.log('RAW RESPONSE TEXT:', text);

    try {
        return JSON.parse(text);
    } catch (e) {
        const start = text.indexOf('{');
        const end = text.lastIndexOf('}');
        if (start >= 0 && end > start) {
            return JSON.parse(text.substring(start, end + 1));
        }

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

function updateFilingSelectedFilesLabel() {
    const input = document.getElementById('fileInput');
    const label = document.getElementById('filingSelectedFiles');
    if (!input || !label) return;

    const files = Array.from(input.files || []);
    label.textContent = files.length > 0
        ? files.length + ' file siap diupload: ' + files.map(file => file.name).slice(0, 3).join(', ') + (files.length > 3 ? ', ...' : '')
        : 'Belum ada file dipilih.';
}

function setFilingDropFiles(files) {
    const input = document.getElementById('fileInput');
    const category = document.getElementById('file_category');
    if (!input) return;

    let selectedFiles = Array.from(files || []);
    if (category && category.value === 'crc_individual') {
        selectedFiles = selectedFiles.filter(file => file.name.toLowerCase().endsWith('.crc'));
    }

    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
    updateFilingSelectedFilesLabel();

    if (selectedFiles.length === 0) {
        alert(category && category.value === 'crc_individual' ? 'Kategori CRC Individu hanya menerima file .CRC.' : 'Tidak ada file valid yang dipilih.');
    }
}

function buildFilingChunkFormData(form, files) {
    const data = new FormData();
    form.querySelectorAll('input[type="hidden"], input[type="text"], select').forEach(input => {
        if (input.name) data.append(input.name, input.value);
    });
    files.forEach(file => data.append('files[]', file, file.name));
    return data;
}

$(document).ready(function() {
    initViewTransitions();
    initCrcB2FolderToggles();
    $('.select2-modal').select2({ dropdownParent: $('#modalFilter'), width: '100%' });
    $('.select2-input').select2({ dropdownParent: $('#modalInput'), width: '100%' });
    flatpickr("#f_date", { mode: "range", dateFormat: "Y-m-d" });
    flatpickr("#crcB2DateInput", {
        mode: "range",
        dateFormat: "Y-m-d",
        onClose: () => {
            if (ACTIVE_BERITA_ACARA_VIEW === 'crc_b2') loadCrcB2Folders(1);
        }
    });
    const filingDropZone = document.getElementById('filingDropZone');
    const fileInput = document.getElementById('fileInput');
    if (filingDropZone && fileInput) {
        filingDropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', updateFilingSelectedFilesLabel);
        ['dragenter', 'dragover'].forEach(eventName => {
            filingDropZone.addEventListener(eventName, event => {
                event.preventDefault();
                filingDropZone.classList.add('border-indigo-500', 'bg-indigo-100');
            });
        });
        ['dragleave', 'drop'].forEach(eventName => {
            filingDropZone.addEventListener(eventName, event => {
                event.preventDefault();
                filingDropZone.classList.remove('border-indigo-500', 'bg-indigo-100');
            });
        });
        filingDropZone.addEventListener('drop', event => setFilingDropFiles(event.dataTransfer.files));
    }
    if (ACTIVE_BERITA_ACARA_VIEW === 'crc_b2') {
        loadCrcB2Folders();
    }
    $('#formUpload').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = $('#btnUpload');
        const originalHtml = btn.html();
        const category = $('#file_category').val();
        const files = Array.from(document.getElementById('fileInput')?.files || []);

        if (category === 'crc_individual' && files.length > 500) {
            alert('Maksimal 500 file .CRC sekali upload.');
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> UPLOADING...');

        const uploadPromise = category === 'crc_individual' && files.length > 20
            ? (async () => {
                let uploadedCount = 0;
                let failedCount = 0;
                const chunks = [];
                for (let i = 0; i < files.length; i += 20) {
                    chunks.push(files.slice(i, i + 20));
                }

                for (let i = 0; i < chunks.length; i++) {
                    btn.html('<i class="fas fa-spinner fa-spin"></i> BATCH ' + (i + 1) + '/' + chunks.length + '...');
                    const response = await fetch('berita_acara.php', { method: 'POST', body: buildFilingChunkFormData(form, chunks[i]) });
                    const result = await parseJsonResponse(response);
                    if (result.status !== 'success') {
                        throw new Error(result.msg || 'Upload gagal pada batch ' + (i + 1));
                    }
                    uploadedCount += parseInt(result.uploaded_count || 0, 10);
                    failedCount += parseInt(result.failed_count || 0, 10);
                }

                return {
                    status: 'success',
                    msg: 'Upload selesai. Berhasil: ' + uploadedCount + ', gagal: ' + failedCount + '.',
                };
            })()
            : fetch('berita_acara.php', { method: 'POST', body: new FormData(form) }).then(parseJsonResponse);

        uploadPromise
        .then(res => {
            btn.prop('disabled', false).html(originalHtml);
            if(res.status === 'success') {
                $('#fileInput').val('');
                $('#custom_file_name').val('');
                updateFilingSelectedFilesLabel();
                loadFileList($('#upload_filing_id').val());
                alert(res.msg || 'File berhasil diupload!');
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

<?php require_once BASE_PATH.'/includes/layout_footer.php'; ?>
