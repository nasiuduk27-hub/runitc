@extends('layouts.app')

@section('title', 'RUN-ITC | Berita Acara')

@section('content')

<?php $baBase = url('filing-system/berita-acara'); ?>

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
                <a href="<?php echo $baBase ?>" data-view-transition class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest border transition <?php echo $activeView === 'main' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
                    Test Document
                </a>
                <a href="<?php echo $baBase ?>?view=crc_b2" data-view-transition class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest border transition <?php echo $activeView === 'crc_b2' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-100' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' ?>">
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

                    fetch('<?php echo $baBase ?>?' + params.toString())
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
                    fetch('<?php echo $baBase ?>?ajax_crc_b2_files=1&admin_no=' + encodeURIComponent(adminNo))
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
                <a href="<?php echo $baBase ?>" class="ba-action ba-action-neutral">Kembali</a>
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
                        @csrf
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
                        <a href="<?php echo $baBase ?>?<?php echo htmlspecialchars(http_build_query($prevQuery)) ?>" class="ba-action ba-action-neutral">Prev</a>
                    <?php } ?>
                    <span style="font-size:11px;font-weight:900;color:#374151;padding:0 6px;">Halaman <?php echo $testDocumentPage ?> dari <?php echo $testDocumentTotalPages ?></span>
                    <?php if ($testDocumentPage < $testDocumentTotalPages) {
                        $nextQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage + 1]); ?>
                        <a href="<?php echo $baBase ?>?<?php echo htmlspecialchars(http_build_query($nextQuery)) ?>" class="ba-action ba-action-neutral">Next</a>
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
                <a href="<?php echo $baBase ?>" class="px-6 py-2.5 bg-red-50 text-red-600 rounded-lg text-sm font-bold hover:bg-red-100 transition flex items-center gap-2 shadow-sm"><i class="fas fa-times"></i> RESET</a>
            <?php } ?>
        </div>

        <div class="ba-card-panel">
            <div class="ba-card-header">
                <div>
                    <div class="ba-card-title">Daftar Nomor Admin</div>
                    <div class="ba-card-meta">Halaman <?php echo $testDocumentPage ?> / <?php echo $testDocumentTotalPages ?> &middot; <?php echo $testDocumentTotal ?> total data</div>
                </div>
                <?php if ($canManageBeritaAcara) { ?>
                    <a href="<?php echo $baBase ?>?sync_assignments=1" class="ba-action ba-action-neutral">Sync Assignment</a>
                <?php } ?>
            </div>

            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;background:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div style="font-size:11px;font-weight:800;color:#6b7280;">Menampilkan <?php echo count($data_list) ?> dari <?php echo $testDocumentTotal ?> data</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($testDocumentPage > 1) {
                        $prevQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage - 1]); ?>
                        <a href="<?php echo $baBase ?>?<?php echo htmlspecialchars(http_build_query($prevQuery)) ?>" class="ba-action ba-action-neutral">Prev</a>
                    <?php } ?>
                    <span style="font-size:11px;font-weight:900;color:#374151;">Page <?php echo $testDocumentPage ?></span>
                    <?php if ($testDocumentPage < $testDocumentTotalPages) {
                        $nextQuery = array_merge($pageQueryBase, ['page' => $testDocumentPage + 1]); ?>
                        <a href="<?php echo $baBase ?>?<?php echo htmlspecialchars(http_build_query($nextQuery)) ?>" class="ba-action ba-action-neutral">Next</a>
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
                                    <a href="<?php echo $baBase ?>?sync_assignments=1" class="ba-action ba-action-amber">Sync Untuk Files</a>
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
                    @csrf
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
                fetch('<?php echo $baBase ?>?ajax_get_submenu=' + encodeURIComponent(id))
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

                fetch('<?php echo $baBase ?>?ajax_get_submenu=' + encodeURIComponent(id))
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
                            const downloadUrl = file.download_url || (file.file_id ? '<?php echo $baBase ?>?download_file=' + encodeURIComponent(file.file_id) : '#');
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
                body.set('_token', '<?php echo csrf_token() ?>');
                body.set('file_action', 'delete');
                body.set('file_id', fileId);
                fetch('<?php echo $baBase ?>', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

        <?php } ?>

        </div>
    </div>
</div>

@endsection
