<!-- File: modules/cbt_ops/filing_system/views/upload_modal.php -->
<div id="uploadModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-3">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[92dvh] overflow-hidden flex flex-col">
        <div class="fs-modal-header fs-modal-header-accent fs-modal-header-blue px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div class="flex items-center gap-3">
                    <span class="fs-icon-box"><i class="fas fa-cloud-upload-alt"></i></span>
                    <div>
                        <h3 class="fs-modal-title text-lg font-bold">Upload ke Filing System</h3>
                        <p class="fs-modal-subtitle">Pilih satu atau beberapa file. Bisa dikemas ZIP atau diupload terpisah.</p>
                    </div>
                </div>
            <button onclick="closeUploadModal()" class="fs-btn fs-close-btn transition-colors" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="uploadForm" class="p-6 bg-white overflow-y-auto">
            <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
            <?php // echo Csrf::html();?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih File <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="file" name="raw_files" id="raw_files" required multiple
                                class="fs-file w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-lg p-1">
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1 italic">Pilih satu atau beberapa file (docx, pdf, jpg, png, xlsx, dst).</p>
                        <div id="zipOptionWrap" class="hidden mt-2">
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-gray-700">
                                <input type="checkbox" name="zip_files" id="zip_files" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Jadikan ZIP
                            </label>
                            <p id="zipOptionHint" class="mt-0.5 text-[10px] italic text-gray-400">File terpilih dikemas menjadi 1 file ZIP di browser, lalu di-upload.</p>
                        </div>
                        <div id="selectedFilesPreview" class="hidden mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-600"></div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Share To</label>
                        <div class="flex flex-wrap gap-2">
                            <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                <input type="radio" name="share_mode" id="share_mode_private" value="private" checked class="h-3.5 w-3.5 text-blue-600">
                                Hanya Saya
                            </label>
                            <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                <input type="radio" name="share_mode" id="share_mode_targets" value="targets" class="h-3.5 w-3.5 text-blue-600">
                                Bagikan
                            </label>
                        </div>

                        <div id="shareTargetsPanel" class="hidden mt-3 space-y-3 rounded-lg border border-blue-100 bg-blue-50/40 p-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500">Pilih User</label>
                                    <input type="text" id="shareUserSearch" placeholder="Cari nama user..." class="mb-1.5 w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500">
                                    <div id="share_users" class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1.5">
                                        <p class="px-2 py-2 text-[11px] italic text-gray-400">Memuat daftar user...</p>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-1 flex items-center justify-between">
                                        <label class="block text-[10px] font-bold uppercase tracking-wide text-gray-500">Pilih Divisi / Departemen</label>
                                        <button type="button" id="btnMyDepartment" class="text-[10px] font-bold text-blue-600 hover:text-blue-700">+ Divisi Saya</button>
                                    </div>
                                    <input type="text" id="shareDeptSearch" placeholder="Cari divisi..." class="mb-1.5 w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500">
                                    <div id="share_departments" class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1.5">
                                        <p class="px-2 py-2 text-[11px] italic text-gray-400">Memuat daftar divisi...</p>
                                    </div>
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-gray-700">
                                <input type="checkbox" id="share_all" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Semua Karyawan (seluruh divisi/departemen)
                            </label>

                            <div id="shareChips" class="flex flex-wrap gap-1.5">
                                <span class="text-[10px] italic text-gray-400">Belum ada target. Jika kosong, hanya Anda yang dapat mengakses.</span>
                            </div>
                        </div>

                        <input type="hidden" name="share_targets" id="share_targets">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Masa Aktif File (Expiry)</label>
                        <input type="date" name="expired_at" class="fs-input w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        <p class="text-[10px] text-gray-400 mt-1 italic">Kosongkan jika tidak ada expiry</p>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Catatan Tambahan / Keywords</label>
                <textarea name="notes" rows="2" placeholder="Tulis catatan atau keyword untuk pencarian..." 
                    class="fs-textarea w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm"></textarea>
            </div>

            <div class="mt-8 flex flex-col-reverse sm:flex-row justify-end gap-3">
                <button type="button" onclick="closeUploadModal()" class="fs-btn px-6 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Batal</button>
                <button type="submit" id="btnSubmitUpload" class="fs-btn fs-btn-primary px-6 py-2 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                    <span id="btnText">Mulai Upload</span>
                    <i class="fas fa-paper-plane text-xs"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="filingToastContainer" class="fixed top-5 right-5 z-[70] space-y-3 w-[calc(100%-2rem)] max-w-sm pointer-events-none"></div>

<!-- Queue Upload: popup pojok kanan bawah ala Google Drive -->
<div id="uploadQueuePanel" class="fixed bottom-4 right-4 z-[80] hidden w-[calc(100%-2rem)] max-w-sm flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl">
    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2.5">
        <div class="flex items-center gap-2">
            <i class="fas fa-cloud-upload-alt text-blue-600"></i>
            <span class="text-sm font-bold text-gray-800">Mengunggah</span>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" onclick="collapseUploadQueue()" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600" title="Minimalkan">
                <i id="uploadQueueChevron" class="fas fa-chevron-down text-xs"></i>
            </button>
            <button type="button" onclick="closeUploadQueue()" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-red-600" title="Tutup panel">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    </div>
    <div id="uploadQueueList" class="max-h-80 overflow-y-auto p-2">
        <!-- items dimasukkan via JS -->
    </div>
    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2">
        <p id="uploadQueueSummary" class="text-xs font-semibold text-gray-600">Menunggu upload...</p>
    </div>
</div>

<script>
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showFilingToast(type, title, message) {
    const container = document.getElementById('filingToastContainer');
    if (!container) return;

    const styles = {
        success: ['bg-green-50 border-green-200 text-green-700', 'fa-check-circle text-green-500'],
        error: ['bg-red-50 border-red-200 text-red-700', 'fa-times-circle text-red-500'],
        info: ['bg-blue-50 border-blue-200 text-blue-700', 'fa-info-circle text-blue-500']
    };
    const style = styles[type] || styles.info;
    const toast = document.createElement('div');
    toast.className = `pointer-events-auto rounded-xl border ${style[0]} shadow-lg p-4 flex gap-3 transform transition-all duration-300 translate-x-4 opacity-0`;
    toast.innerHTML = `
        <i class="fas ${style[1]} mt-0.5"></i>
        <div class="min-w-0 flex-1">
            <div class="font-black text-sm">${escapeHtml(title)}</div>
            <div class="text-xs mt-0.5 break-words">${escapeHtml(message)}</div>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-600" aria-label="Tutup"><i class="fas fa-times"></i></button>
    `;

    toast.querySelector('button').addEventListener('click', () => removeFilingToast(toast));
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-4', 'opacity-0'));
    setTimeout(() => removeFilingToast(toast), 6000);
}

function removeFilingToast(toast) {
    toast.classList.add('translate-x-4', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
}

// ===== Queue Upload (Google Drive style) =====
let uploadQueue = [];
let uploadQueueRunning = false;

function showUploadQueue() {
    const panel = document.getElementById('uploadQueuePanel');
    if (panel) panel.classList.remove('hidden');
}

function collapseUploadQueue() {
    const list = document.getElementById('uploadQueueList');
    const chevron = document.getElementById('uploadQueueChevron');
    if (!list || !chevron) return;
    const isHidden = list.classList.toggle('hidden');
    chevron.classList.toggle('fa-chevron-down', isHidden);
    chevron.classList.toggle('fa-chevron-up', !isHidden);
}

function closeUploadQueue() {
    const panel = document.getElementById('uploadQueuePanel');
    if (panel) panel.classList.add('hidden');
}

function addQueueItem(file, index) {
    const list = document.getElementById('uploadQueueList');
    if (!list) return;

    const item = document.createElement('div');
    item.id = 'uploadQueueItem_' + index;
    item.className = 'mb-2 rounded-lg border border-gray-100 bg-white p-2.5';
    item.innerHTML = `
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold text-gray-700" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</p>
                <p id="queueStatus_${index}" class="mt-0.5 text-[10px] text-gray-400">Menunggu...</p>
            </div>
            <span id="queuePercent_${index}" class="shrink-0 text-[11px] font-black text-gray-500">0%</span>
        </div>
        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
            <div id="queueBar_${index}" class="h-full w-0 rounded-full bg-blue-600 transition-all duration-200"></div>
        </div>
    `;
    list.appendChild(item);
    return item;
}

function updateQueueItem(index, percent, text, state) {
    const bar = document.getElementById('queueBar_' + index);
    const pct = document.getElementById('queuePercent_' + index);
    const status = document.getElementById('queueStatus_' + index);
    if (!bar || !pct || !status) return;

    const safe = Math.max(0, Math.min(100, Math.round(percent)));
    bar.style.width = safe + '%';
    pct.innerText = safe + '%';

    if (state === 'done') {
        bar.className = 'h-full w-full rounded-full bg-green-500 transition-all duration-200';
        pct.className = 'shrink-0 text-[11px] font-black text-green-600';
        status.innerText = 'Selesai';
        status.className = 'mt-0.5 text-[10px] font-bold text-green-600';
    } else if (state === 'error') {
        bar.className = 'h-full w-full rounded-full bg-red-500 transition-all duration-200';
        pct.className = 'shrink-0 text-[11px] font-black text-red-600';
        status.innerText = 'Gagal: ' + text;
        status.className = 'mt-0.5 text-[10px] font-bold text-red-600';
    } else {
        status.innerText = text || 'Mengupload...';
    }
}

function updateQueueSummary() {
    const summary = document.getElementById('uploadQueueSummary');
    if (!summary) return;
    const total = uploadQueue.length;
    const done = uploadQueue.filter(q => q.done || q.failed).length;
    const failed = uploadQueue.filter(q => q.failed).length;
    summary.innerText = done >= total
        ? (failed > 0 ? 'Selesai. ' + failed + ' file gagal.' : 'Semua file berhasil diunggah.')
        : 'Mengunggah ' + done + ' dari ' + total + ' file...';
}

function uploadNextInQueue() {
    if (uploadQueueRunning) return;
    const next = uploadQueue.find(q => !q.done && !q.failed);
    if (!next) {
        updateQueueSummary();
        // Refresh hanya tabel file (tanpa reload halaman)
        setTimeout(() => refreshFileList(), 800);
        return;
    }
    uploadQueueRunning = true;
    uploadSingleFile(next);
}

function refreshFileList() {
    const container = document.getElementById('fileListContainer');
    if (!container) {
        window.location.reload();
        return;
    }

    const url = new URL(window.location.href, window.location.origin);
    url.searchParams.set('partial', '1');

    fetch(url.toString(), { headers: { Accept: 'text/html' }, cache: 'no-store' })
        .then(r => (r.ok ? r.text() : Promise.reject('HTTP ' + r.status)))
        .then(html => {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newContainer = temp.querySelector('#fileListContainer');
            if (!newContainer) throw new Error('Container tidak ditemukan');
            container.innerHTML = newContainer.innerHTML;
            updateBulkUI();
        })
        .catch(err => {
            window.location.reload();
        });
}

function uploadSingleFile(queueItem) {
    const index = queueItem.index;
    const file = queueItem.file;
    updateQueueItem(index, 2, 'Mengirim ke server...');

    const formData = new FormData();
    formData.append('raw_files', file, file.name);
    formData.append('share_targets', JSON.stringify(uploadShareTargets));
    formData.append('notes', document.querySelector('textarea[name="notes"]')?.value || '');
    formData.append('expired_at', document.querySelector('input[name="expired_at"]')?.value || '');
    const mode = document.querySelector('input[name="share_mode"]:checked');
    formData.append('share_mode', mode ? mode.value : 'private');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload.php', true);
    xhr.responseType = 'text';

    xhr.upload.addEventListener('progress', function (event) {
        if (event.lengthComputable) {
            const percent = Math.min(90, (event.loaded / event.total) * 90);
            updateQueueItem(index, percent, 'Mengirim file ke server...');
        }
    });

    xhr.addEventListener('load', function () {
        let data = null;
        try {
            data = JSON.parse(xhr.responseText || '{}');
        } catch (err) {
            queueItem.failed = true;
            updateQueueItem(index, 100, 'Respons tidak valid', 'error');
            uploadQueueRunning = false;
            updateQueueSummary();
            uploadNextInQueue();
            return;
        }

        if (xhr.status >= 200 && xhr.status < 300 && data.success) {
            queueItem.done = true;
            updateQueueItem(index, 100, '', 'done');
        } else {
            queueItem.failed = true;
            updateQueueItem(index, 100, data.message || 'Upload gagal', 'error');
        }

        uploadQueueRunning = false;
        updateQueueSummary();
        uploadNextInQueue();
    });

    xhr.addEventListener('error', function () {
        queueItem.failed = true;
        updateQueueItem(index, 100, 'Koneksi gagal', 'error');
        uploadQueueRunning = false;
        updateQueueSummary();
        uploadNextInQueue();
    });

    xhr.addEventListener('timeout', function () {
        queueItem.failed = true;
        updateQueueItem(index, 100, 'Timeout', 'error');
        uploadQueueRunning = false;
        updateQueueSummary();
        uploadNextInQueue();
    });

    xhr.timeout = 600000;
    xhr.send(formData);
}

function startUploadQueue(files) {
    uploadQueue = [];
    const list = document.getElementById('uploadQueueList');
    if (list) list.innerHTML = '';

    files.forEach((file, index) => {
        uploadQueue.push({ index: index, file: file, done: false, failed: false });
        addQueueItem(file, index);
    });

    showUploadQueue();
    updateQueueSummary();
    uploadNextInQueue();
}

let shareTargets = [];
let uploadAccessOptions = {};
// Snapshot target share saat submit, karena closeUploadModal() mereset shareTargets
// sedangkan proses upload berjalan lewat queue (berjalan setelah modal ditutup).
let uploadShareTargets = [];

function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').classList.add('flex');
    loadUploadAccessOptions();
    renderShareChips();
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadModal').classList.remove('flex');
    document.getElementById('uploadForm').reset();
    shareTargets = [];
    document.getElementById('share_targets').value = '';
    renderShareChips();
    updateSelectedFilesPreview();
    const userSearch = document.getElementById('shareUserSearch');
    if (userSearch) userSearch.value = '';
    const deptSearch = document.getElementById('shareDeptSearch');
    if (deptSearch) deptSearch.value = '';
}

function loadUploadAccessOptions() {
    if (Object.keys(uploadAccessOptions).length > 0) {
        populateShareSelects();
        return;
    }

    fetch('upload.php?action=access_options')
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            uploadAccessOptions = res.data || {};
            populateShareSelects();
        } else {
            showFilingToast('error', 'Hak akses gagal dimuat', res.message || 'Tidak bisa mengambil daftar akses.');
        }
    })
    .catch(() => showFilingToast('error', 'Hak akses gagal dimuat', 'Terjadi kesalahan koneksi saat mengambil daftar akses.'));
}

function renderShareUserList(query) {
    const userBox = document.getElementById('share_users');
    if (!userBox) return;

    const users = uploadAccessOptions.user || [];
    const q = String(query || '').trim().toLowerCase();
    const filtered = q === '' ? users : users.filter(o => String(o.label).toLowerCase().includes(q));

    if (filtered.length === 0) {
        userBox.innerHTML = '<p class="px-2 py-2 text-[11px] italic text-gray-400">' + (q !== '' ? 'User tidak ditemukan.' : 'Tidak ada user tersedia') + '</p>';

        return;
    }

    userBox.innerHTML = filtered.map(opt => {
        const checked = shareTargets.some(t => t.type === 'user' && String(t.value) === String(opt.value));

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                <input type="checkbox" class="share-user-check h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${escapeHtml(String(opt.value))}" ${checked ? 'checked' : ''} onchange="onShareUserToggle(this)">
                <span class="truncate">${escapeHtml(opt.label)}</span>
            </label>
        `;
    }).join('');
}

function renderShareDeptList(query) {
    const deptBox = document.getElementById('share_departments');
    if (!deptBox) return;

    const departments = uploadAccessOptions.department || [];
    const q = String(query || '').trim().toLowerCase();
    const filtered = q === '' ? departments : departments.filter(o => String(o.label).toLowerCase().includes(q));

    if (filtered.length === 0) {
        deptBox.innerHTML = '<p class="px-2 py-2 text-[11px] italic text-gray-400">' + (q !== '' ? 'Divisi tidak ditemukan.' : 'Tidak ada divisi tersedia') + '</p>';

        return;
    }

    deptBox.innerHTML = filtered.map(opt => {
        const checked = shareTargets.some(t => t.type === 'department' && String(t.value) === String(opt.value));

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                <input type="checkbox" class="share-dept-check h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${escapeHtml(String(opt.value))}" ${checked ? 'checked' : ''} onchange="onShareDeptToggle(this)">
                <span class="truncate">${escapeHtml(opt.label)}</span>
            </label>
        `;
    }).join('');
}

function populateShareSelects() {
    renderShareUserList(document.getElementById('shareUserSearch')?.value || '');
    renderShareDeptList(document.getElementById('shareDeptSearch')?.value || '');
}

function onShareUserToggle(cb) {
    if (cb.checked) {
        if (!shareTargets.some(t => t.type === 'user' && String(t.value) === String(cb.value))) {
            shareTargets.push({ type: 'user', value: cb.value });
        }
    } else {
        shareTargets = shareTargets.filter(t => !(t.type === 'user' && String(t.value) === String(cb.value)));
    }
    syncShareState();
}

function onShareDeptToggle(cb) {
    if (cb.checked) {
        if (!shareTargets.some(t => t.type === 'department' && String(t.value) === String(cb.value))) {
            shareTargets.push({ type: 'department', value: cb.value });
        }
    } else {
        shareTargets = shareTargets.filter(t => !(t.type === 'department' && String(t.value) === String(cb.value)));
    }
    syncShareState();
}

function syncShareState() {
    document.getElementById('share_targets').value = JSON.stringify(shareTargets);
    renderShareChips();
}

function selectMyDepartments() {
    const myDepts = uploadAccessOptions.current_departments || [];
    if (myDepts.length === 0) return;

    document.querySelectorAll('#share_departments .share-dept-check').forEach(cb => {
        if (myDepts.some(code => String(code) === String(cb.value))) {
            cb.checked = true;
        }
    });
    collectShareTargets();
}

function collectShareTargets() {
    const mode = document.querySelector('input[name="share_mode"]:checked');
    const isPrivate = mode && mode.value === 'private';

    if (isPrivate) {
        shareTargets = [];
        syncShareState();

        return;
    }

    // Pertahankan pilihan yang mungkin tersembunyi oleh filter pencarian.
    const targets = shareTargets.slice();

    document.querySelectorAll('#share_users .share-user-check:checked').forEach(cb => {
        if (!targets.some(t => t.type === 'user' && String(t.value) === String(cb.value))) {
            targets.push({ type: 'user', value: cb.value });
        }
    });
    document.querySelectorAll('#share_departments .share-dept-check:checked').forEach(cb => {
        if (!targets.some(t => t.type === 'department' && String(t.value) === String(cb.value))) {
            targets.push({ type: 'department', value: cb.value });
        }
    });

    const allCheck = document.getElementById('share_all');
    if (allCheck && allCheck.checked) {
        if (!targets.some(t => t.type === 'all')) {
            targets.push({ type: 'all', value: '0' });
        }
    } else {
        shareTargets = targets.filter(t => t.type !== 'all');
        syncShareState();

        return;
    }

    shareTargets = targets;
    syncShareState();
}

function renderShareChips() {
    const chipBox = document.getElementById('shareChips');
    if (!chipBox) return;

    const mode = document.querySelector('input[name="share_mode"]:checked');
    const isPrivate = mode && mode.value === 'private';

    if (isPrivate || shareTargets.length === 0) {
        chipBox.innerHTML = '<span class="text-[10px] italic text-gray-400">' + (isPrivate ? 'Hanya Anda yang dapat mengakses file ini.' : 'Belum ada target. Jika kosong, hanya Anda yang dapat mengakses.') + '</span>';
        return;
    }

    chipBox.innerHTML = shareTargets.map((target, index) => {
        let label = target.type === 'all' ? 'Semua Karyawan' : '';
        if (target.type === 'user' || target.type === 'department') {
            const list = uploadAccessOptions[target.type] || [];
            const opt = list.find(o => String(o.value) === String(target.value));
            label = opt ? opt.label : target.value;
        }
        const icon = target.type === 'all' ? 'fa-users' : (target.type === 'department' ? 'fa-building' : 'fa-user');
        return `
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold text-blue-700">
                <i class="fas ${icon}"></i> ${escapeHtml(label)}
                <button type="button" onclick="removeShareTarget(${index})" class="text-blue-400 hover:text-red-500"><i class="fas fa-times"></i></button>
            </span>
        `;
    }).join('');
}

function removeShareTarget(index) {
    const target = shareTargets[index];
    if (! target) return;

    if (target.type === 'all') {
        document.getElementById('share_all').checked = false;
    } else if (target.type === 'user') {
        document.querySelectorAll('#share_users .share-user-check').forEach(cb => {
            if (String(cb.value) === String(target.value)) cb.checked = false;
        });
    } else if (target.type === 'department') {
        document.querySelectorAll('#share_departments .share-dept-check').forEach(cb => {
            if (String(cb.value) === String(target.value)) cb.checked = false;
        });
    }

    shareTargets.splice(index, 1);
    document.getElementById('share_targets').value = JSON.stringify(shareTargets);
    renderShareChips();
}

function toggleShareTargetsPanel() {
    const mode = document.querySelector('input[name="share_mode"]:checked');
    const panel = document.getElementById('shareTargetsPanel');
    if (!panel || !mode) return;
    panel.classList.toggle('hidden', mode.value !== 'targets');
    if (mode.value === 'targets') {
        selectMyDepartments();
        collectShareTargets();
    } else {
        shareTargets = [];
        document.getElementById('share_targets').value = '';
        renderShareChips();
    }
}

function updateSelectedFilesPreview() {
    const input = document.getElementById('raw_files');
    const preview = document.getElementById('selectedFilesPreview');
    const zipOption = document.getElementById('zipOptionWrap');
    const zipCheck = document.getElementById('zip_files');
    if (!input || !preview) return;

    const files = Array.from(input.files || []);
    if (files.length === 0) {
        preview.classList.add('hidden');
        preview.innerHTML = '';
        if (zipOption) zipOption.classList.add('hidden');
        return;
    }

    const totalBytes = files.reduce((sum, f) => sum + f.size, 0);
    const formatter = new Intl.NumberFormat('id-ID');

    // Opsi ZIP tampil saat lebih dari 1 file dipilih (dikemas di browser lalu di-upload sebagai 1 file).
    if (zipOption) zipOption.classList.toggle('hidden', files.length <= 1);

    const zipActive = zipCheck ? zipCheck.checked : true;
    const modeLabel = files.length > 1
        ? (zipActive ? ' - akan dikemas menjadi ZIP' : ' - akan diupload terpisah')
        : '';

    preview.innerHTML = `
        <div class="font-bold text-gray-700 mb-1">${files.length} file dipilih (${formatter.format(Math.ceil(totalBytes / 1024))} KB)${modeLabel}</div>
        <ul class="space-y-0.5">${files.map(f => `<li class="truncate">${escapeHtml(f.name)}</li>`).join('')}</ul>
    `;
    preview.classList.remove('hidden');
}

document.getElementById('zip_files')?.addEventListener('change', updateSelectedFilesPreview);
document.getElementById('raw_files').addEventListener('change', updateSelectedFilesPreview);
document.querySelectorAll('input[name="share_mode"]').forEach(el => el.addEventListener('change', toggleShareTargetsPanel));
document.getElementById('share_all')?.addEventListener('change', collectShareTargets);
document.getElementById('btnMyDepartment')?.addEventListener('click', selectMyDepartments);
document.getElementById('shareUserSearch')?.addEventListener('input', function () { renderShareUserList(this.value); });
document.getElementById('shareDeptSearch')?.addEventListener('input', function () { renderShareDeptList(this.value); });

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();

    collectShareTargets();
    // Simpan snapshot target sebelum modal ditutup; queue upload memakai snapshot ini.
    uploadShareTargets = shareTargets.slice();

    const rawFilesInput = document.getElementById('raw_files');
    const selectedFiles = Array.from(rawFilesInput.files || []);
    if (selectedFiles.length === 0) {
        showFilingToast('error', 'Belum ada file', 'Pilih minimal satu file terlebih dahulu.');
        return;
    }

    const btn = document.getElementById('btnSubmitUpload');
    const btnText = document.getElementById('btnText');
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    btnText.innerHTML = 'Mengunggah...';

    const zipCheck = document.getElementById('zip_files');
    const wantZip = selectedFiles.length > 1 && zipCheck && zipCheck.checked;

    // Tutup modal upload, proses berlanjut di queue pojok kanan bawah.
    closeUploadModal();

    if (wantZip) {
        zipSelectedFiles(selectedFiles).then(function (zipFile) {
            startUploadQueue([zipFile]);
        }).catch(function (err) {
            showFilingToast('error', 'Gagal membuat ZIP', err && err.message ? err.message : 'Terjadi kesalahan.');
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            btnText.innerHTML = 'Mulai Upload';
        });
        return;
    }

    startUploadQueue(selectedFiles);
});

function zipSelectedFiles(files) {
    return new Promise(function (resolve, reject) {
        if (typeof JSZip === 'undefined') {
            reject(new Error('Pustaka ZIP tidak dimuat. Upload terpisah saja.'));
            return;
        }

        const zip = new JSZip();
        const usedNames = {};
        const tasks = [];

        files.forEach(function (file) {
            let name = file.name;
            if (usedNames[name]) {
                const dot = name.lastIndexOf('.');
                const base = dot > 0 ? name.slice(0, dot) : name;
                const ext = dot > 0 ? name.slice(dot) : '';
                let i = 2;
                while (usedNames[name]) {
                    name = base + '_' + i + ext;
                    i++;
                }
            }
            usedNames[name] = true;
            tasks.push(file.arrayBuffer().then(function (buf) {
                zip.file(name, buf);
            }));
        });

        Promise.all(tasks).then(function () {
            return zip.generateAsync({ type: 'blob' });
        }).then(function (blob) {
            const baseName = (files[0].name || 'file').replace(/\.[^.]+$/, '') || 'file';
            const zipName = baseName + '_dan_' + (files.length - 1) + '_file_lainnya.zip';
            const zipFile = new File([blob], zipName, { type: 'application/zip' });
            resolve(zipFile);
        }).catch(reject);
    });
}
</script>
