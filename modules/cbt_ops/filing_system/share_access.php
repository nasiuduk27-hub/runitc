<?php
// File: modules/cbt_ops/filing_system/share_access.php

require_once __DIR__ . '/../../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

require_once BASE_PATH . '/includes/layout_header.php';
?>

<div class="py-8 md:py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-3xl text-gray-900 tracking-tight flex items-center gap-3">
                    <i class="fas fa-key text-green-600"></i>
                    Akses Share Code
                </h2>
                <p class="text-gray-500 text-sm mt-1">Masukkan kode yang diberikan untuk membuka file yang dibagikan.</p>
            </div>
            <a href="main.php" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl text-sm font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2">
                <i class="fas fa-arrow-left text-gray-400"></i> Kembali
            </a>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-6 md:p-8 border-b border-gray-100 bg-gradient-to-br from-green-50 to-white">
                <form id="formShareAccess" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2 uppercase tracking-wide">Share Code</label>
                        <input
                            type="text"
                            name="share_code"
                            id="shareCodeInput"
                            required
                            placeholder="RFS-XXXX-XXXX"
                            autocomplete="off"
                            class="w-full bg-white border border-gray-300 text-gray-900 text-lg font-mono rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 block p-4 uppercase tracking-widest shadow-sm"
                        >
                        <p class="text-xs text-gray-400 mt-2">Format kode: <span class="font-mono">RFS-AB12-CD34</span></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2 uppercase tracking-wide">Password Share</label>
                        <input
                            type="password"
                            name="share_password"
                            id="sharePasswordInput"
                            placeholder="Kosongkan jika kode tidak memakai password"
                            class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 block p-3 shadow-sm"
                        >
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" id="btnValidateShare" class="px-6 py-3 bg-green-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-green-100 hover:bg-green-700 transition flex items-center gap-2">
                            <i class="fas fa-unlock-alt"></i> Buka File
                        </button>
                    </div>
                </form>
            </div>

            <div id="shareMessage" class="hidden mx-6 md:mx-8 mt-6 p-4 rounded-xl text-sm font-medium"></div>

            <div id="shareResult" class="hidden p-6 md:p-8">
                <div class="rounded-2xl border border-green-100 bg-green-50/60 p-5">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-green-700 font-bold text-xs uppercase tracking-wide mb-2">
                                <i class="fas fa-check-circle"></i>
                                Share Code Valid
                            </div>
                            <h3 id="fileName" class="font-extrabold text-xl text-gray-900 truncate"></h3>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                                <span id="fileSize"></span>
                                <span id="fileCreated"></span>
                            </div>
                        </div>

                        <a id="btnDownloadShare" href="#" class="hidden shrink-0 px-5 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition items-center justify-center gap-2">
                            <i class="fas fa-download"></i> Download File
                        </a>
                    </div>

                    <p id="downloadNotAllowed" class="hidden mt-4 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3">
                        Share Code ini hanya mengizinkan akses informasi file. Download tidak diaktifkan oleh pembuat share.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (size >= 1073741824) return (size / 1073741824).toFixed(2) + ' GB';
    if (size >= 1048576) return (size / 1048576).toFixed(2) + ' MB';
    if (size >= 1024) return (size / 1024).toFixed(2) + ' KB';
    return size + ' bytes';
}

function showShareMessage(type, text) {
    const box = document.getElementById('shareMessage');
    box.className = 'mx-6 md:mx-8 mt-6 p-4 rounded-xl text-sm font-medium ' + (type === 'error' ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100');
    box.textContent = text;
    box.classList.remove('hidden');
}

document.getElementById('shareCodeInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

document.getElementById('formShareAccess').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnValidateShare');
    const originalHtml = btn.innerHTML;
    const result = document.getElementById('shareResult');
    const message = document.getElementById('shareMessage');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memeriksa...';
    result.classList.add('hidden');
    message.classList.add('hidden');

    const fd = new FormData(this);
    fd.append('action', 'validate');

    fetch('share.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                showShareMessage('error', res.message || 'Share Code tidak valid.');
                return;
            }

            const file = res.file_info || {};
            document.getElementById('fileName').textContent = file.display_name || 'File tanpa nama';
            document.getElementById('fileSize').textContent = 'Ukuran: ' + formatBytes(file.zip_size);
            document.getElementById('fileCreated').textContent = file.created_at ? 'Diupload: ' + file.created_at : '';

            const downloadBtn = document.getElementById('btnDownloadShare');
            const downloadNote = document.getElementById('downloadNotAllowed');

            if (res.allow_download) {
                downloadBtn.href = 'download.php?id=' + encodeURIComponent(res.filing_id) + '&share_hash=' + encodeURIComponent(res.share_hash);
                downloadBtn.classList.remove('hidden');
                downloadBtn.classList.add('flex');
                downloadNote.classList.add('hidden');
            } else {
                downloadBtn.classList.add('hidden');
                downloadBtn.classList.remove('flex');
                downloadNote.classList.remove('hidden');
            }

            result.classList.remove('hidden');
            showShareMessage('success', 'Share Code berhasil diverifikasi.');
        })
        .catch(() => {
            showShareMessage('error', 'Terjadi kesalahan jaringan atau server.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});
</script>

<?php require_once BASE_PATH . '/includes/layout_footer.php'; ?>
