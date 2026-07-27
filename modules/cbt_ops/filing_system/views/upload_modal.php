<!-- File: modules/cbt_ops/filing_system/views/upload_modal.php -->
<div id="uploadModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-3">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[92dvh] overflow-hidden flex flex-col">
        <div class="fs-modal-header fs-modal-header-accent fs-modal-header-blue px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <div class="flex items-center gap-3">
                <span class="fs-icon-box"><i class="fas fa-cloud-upload-alt"></i></span>
                <div>
                    <h3 class="fs-modal-title text-lg font-bold">Upload ke Filing System</h3>
                    <p class="fs-modal-subtitle">Pilih satu file, sistem akan menyimpan sebagai ZIP otomatis.</p>
                </div>
            </div>
            <button onclick="closeUploadModal()" class="fs-btn fs-close-btn transition-colors" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="uploadForm" class="p-6 bg-white overflow-y-auto">
            <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
            <?php // echo Csrf::html(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih File <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="file" name="raw_files" id="raw_files" required
                                class="fs-file w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-lg p-1">
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1 italic">Pilih satu file seperti docx, pdf, jpg, png, xlsx, atau dokumen lain. File akan disimpan sebagai ZIP di FTP.</p>
                        <div id="selectedFilesPreview" class="hidden mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-600"></div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Mode Akses</label>
                        <select name="access_mode" id="upload_access_mode" class="fs-select w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-white">
                            <option value="private">Private (Hanya Saya)</option>
                            <option value="public_internal">Public Internal (Semua Staff)</option>
                            <option value="custom">Custom (Atur User / Role)</option>
                            <option value="share_link">Share Link</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Level Keamanan</label>
                        <select name="security_level" class="fs-select w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-white">
                            <option value="normal">Normal</option>
                            <option value="restricted">Restricted (Audit Wajib)</option>
                            <option value="confidential">Confidential (Sangat Rahasia)</option>
                        </select>
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

            <div id="uploadCustomAccessPanel" class="hidden mt-6 border border-blue-100 rounded-xl bg-blue-50/40 p-4">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h4 class="text-sm font-black text-gray-800">Hak Akses Awal</h4>
                        <p class="text-xs text-gray-500 mt-0.5">Tambahkan user, role, company, atau group yang langsung mendapat akses setelah upload.</p>
                    </div>
                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-1 rounded uppercase">Custom</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tipe</label>
                        <select id="upload_rule_type" class="fs-select w-full px-3 py-2 text-xs border border-gray-300 rounded bg-white">
                            <option value="user">User</option>
                            <option value="role">Role</option>
                            <option value="company">Company</option>
                            <option value="custom_group">Custom Group</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Target Akses</label>
                        <select id="upload_rule_value" class="fs-select w-full px-3 py-2 text-xs border border-gray-300 rounded bg-white">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex items-center justify-between gap-1 pb-1">
                        <label class="flex flex-col items-center cursor-pointer"><span class="text-[10px] font-bold text-gray-600 mb-1">View</span><input type="checkbox" id="upload_rule_view" checked class="w-4 h-4 text-blue-600 rounded border-gray-300"></label>
                        <label class="flex flex-col items-center cursor-pointer"><span class="text-[10px] font-bold text-gray-600 mb-1">DL</span><input type="checkbox" id="upload_rule_download" class="w-4 h-4 text-blue-600 rounded border-gray-300"></label>
                        <label class="flex flex-col items-center cursor-pointer"><span class="text-[10px] font-bold text-gray-600 mb-1">Share</span><input type="checkbox" id="upload_rule_share" class="w-4 h-4 text-blue-600 rounded border-gray-300"></label>
                        <label class="flex flex-col items-center cursor-pointer"><span class="text-[10px] font-bold text-gray-600 mb-1">Mng</span><input type="checkbox" id="upload_rule_manage" class="w-4 h-4 text-red-600 rounded border-gray-300"></label>
                    </div>
                    <div class="md:col-span-2">
                        <button type="button" onclick="addUploadInitialRule()" class="fs-btn w-full px-3 py-2 bg-blue-600 text-white rounded text-xs font-bold hover:bg-blue-700 shadow-sm transition">Tambah</button>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto bg-white border border-blue-100 rounded-lg">
                    <table class="w-full min-w-[720px] text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Tipe</th>
                                <th class="px-3 py-2">Target</th>
                                <th class="px-3 py-2 text-center">View</th>
                                <th class="px-3 py-2 text-center">Download</th>
                                <th class="px-3 py-2 text-center">Share</th>
                                <th class="px-3 py-2 text-center">Manage</th>
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="upload_rules_body">
                            <tr><td colspan="7" class="px-3 py-4 text-center text-gray-400 italic">Belum ada aturan. Owner tetap otomatis punya akses penuh.</td></tr>
                        </tbody>
                    </table>
                </div>
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

<div id="uploadProgressOverlay" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-[60] px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-blue-100">
        <div class="flex items-center gap-4 mb-5">
            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                <i class="fas fa-cloud-upload-alt animate-pulse text-xl"></i>
            </div>

            <div>
                <h4 class="font-black text-gray-900">Mengupload file...</h4>
                <p id="uploadProgressText" class="text-xs text-gray-500 font-semibold">Menyiapkan upload</p>
            </div>
        </div>
        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
            <div id="uploadProgressBar" class="h-full w-0 bg-blue-600 rounded-full transition-all duration-200"></div>
        </div>
        <div class="mt-3 flex justify-between text-[11px] font-bold text-gray-400 uppercase tracking-wide">
            <span>Progress</span>
            <span id="uploadProgressPercent">0%</span>
        </div>
        <p class="mt-4 text-xs text-gray-500 leading-relaxed">Jangan tutup halaman ini sampai proses selesai. Setelah file terkirim, server akan memproses file dan mengirimkannya ke FTP.</p>
    </div>
</div>

<script>
let uploadInitialRules = [];
let uploadAccessOptions = {};

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

function setUploadProgress(percent, text) {
    const overlay = document.getElementById('uploadProgressOverlay');
    const bar = document.getElementById('uploadProgressBar');
    const label = document.getElementById('uploadProgressPercent');
    const progressText = document.getElementById('uploadProgressText');
    const safePercent = Math.max(0, Math.min(100, Math.round(percent)));

    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    bar.style.width = safePercent + '%';
    label.innerText = safePercent + '%';
    progressText.innerText = text || 'Mengupload file';
}

function hideUploadProgress() {
    const overlay = document.getElementById('uploadProgressOverlay');
    const bar = document.getElementById('uploadProgressBar');
    const label = document.getElementById('uploadProgressPercent');
    const progressText = document.getElementById('uploadProgressText');
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    bar.style.width = '0%';
    label.innerText = '0%';
    progressText.innerText = 'Menyiapkan upload';
}

function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').classList.add('flex');
    loadUploadAccessOptions();
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadModal').classList.remove('flex');
    document.getElementById('uploadForm').reset();
    uploadInitialRules = [];
    updateSelectedFilesPreview();
    toggleUploadCustomAccess();
    renderUploadInitialRules();
}

function toggleUploadCustomAccess() {
    const mode = document.getElementById('upload_access_mode').value;
    const panel = document.getElementById('uploadCustomAccessPanel');
    if (mode === 'custom') {
        panel.classList.remove('hidden');
        loadUploadAccessOptions();
    } else {
        panel.classList.add('hidden');
    }
}

function loadUploadAccessOptions() {
    if (Object.keys(uploadAccessOptions).length > 0) {
        populateUploadAccessValues();
        return;
    }

    fetch('upload.php?action=access_options')
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            uploadAccessOptions = res.data || {};
            populateUploadAccessValues();
        } else {
            showFilingToast('error', 'Hak akses gagal dimuat', res.message || 'Tidak bisa mengambil daftar akses.');
        }
    })
    .catch(() => showFilingToast('error', 'Hak akses gagal dimuat', 'Terjadi kesalahan koneksi saat mengambil daftar akses.'));
}

function populateUploadAccessValues() {
    const type = document.getElementById('upload_rule_type').value;
    const valueSelect = document.getElementById('upload_rule_value');
    const options = uploadAccessOptions[type] || [];

    valueSelect.innerHTML = '<option value="">Pilih ' + formatUploadAccessType(type) + '</option>';
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = String(opt.value);
        option.textContent = opt.label;
        valueSelect.appendChild(option);
    });

    if (options.length === 0) {
        valueSelect.innerHTML = '<option value="">Tidak ada data tersedia</option>';
    }
}

function formatUploadAccessType(type) {
    const labels = { user: 'User', role: 'Role', company: 'Company', custom_group: 'Custom Group' };
    return labels[type] || type;
}

function getUploadAccessLabel(type, value) {
    const option = (uploadAccessOptions[type] || []).find(opt => String(opt.value) === String(value));
    return option ? option.label : value;
}

function addUploadInitialRule() {
    const type = document.getElementById('upload_rule_type').value;
    const value = document.getElementById('upload_rule_value').value;
    if (!value) {
        showFilingToast('error', 'Target belum dipilih', 'Pilih target hak akses terlebih dahulu.');
        return;
    }

    if (uploadInitialRules.some(rule => rule.access_type === type && String(rule.access_value) === String(value))) {
        showFilingToast('error', 'Aturan sudah ada', 'Target tersebut sudah ditambahkan.');
        return;
    }

    let canManage = document.getElementById('upload_rule_manage').checked ? 1 : 0;
    let canDownload = document.getElementById('upload_rule_download').checked ? 1 : 0;
    let canShare = document.getElementById('upload_rule_share').checked ? 1 : 0;
    let canView = document.getElementById('upload_rule_view').checked ? 1 : 0;

    if (canManage) { canView = 1; canDownload = 1; }
    if (canDownload || canShare) { canView = 1; }

    uploadInitialRules.push({
        access_type: type,
        access_value: value,
        can_view: canView,
        can_download: canDownload,
        can_share: canShare,
        can_manage: canManage
    });

    document.getElementById('upload_rule_value').value = '';
    renderUploadInitialRules();
}

function removeUploadInitialRule(index) {
    uploadInitialRules.splice(index, 1);
    renderUploadInitialRules();
}

function renderUploadInitialRules() {
    const tbody = document.getElementById('upload_rules_body');
    if (!tbody) return;

    if (uploadInitialRules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-center text-gray-400 italic">Belum ada aturan. Owner tetap otomatis punya akses penuh.</td></tr>';
        return;
    }

    tbody.innerHTML = uploadInitialRules.map((rule, index) => `
        <tr class="border-t border-gray-100">
            <td class="px-3 py-2 font-bold uppercase text-gray-600">${escapeHtml(formatUploadAccessType(rule.access_type))}</td>
            <td class="px-3 py-2"><div class="font-semibold text-gray-700">${escapeHtml(getUploadAccessLabel(rule.access_type, String(rule.access_value)))}</div><div class="font-mono text-[10px] text-gray-400">${escapeHtml(rule.access_value)}</div></td>
            <td class="px-3 py-2 text-center">${rule.can_view ? '<i class="fas fa-check text-green-600"></i>' : '<i class="fas fa-minus text-gray-300"></i>'}</td>
            <td class="px-3 py-2 text-center">${rule.can_download ? '<i class="fas fa-check text-green-600"></i>' : '<i class="fas fa-minus text-gray-300"></i>'}</td>
            <td class="px-3 py-2 text-center">${rule.can_share ? '<i class="fas fa-check text-green-600"></i>' : '<i class="fas fa-minus text-gray-300"></i>'}</td>
            <td class="px-3 py-2 text-center">${rule.can_manage ? '<i class="fas fa-check text-green-600"></i>' : '<i class="fas fa-minus text-gray-300"></i>'}</td>
            <td class="px-3 py-2 text-right"><button type="button" onclick="removeUploadInitialRule(${index})" class="text-red-500 font-bold hover:text-red-700">Hapus</button></td>
        </tr>
    `).join('');
}

function updateSelectedFilesPreview() {
    const input = document.getElementById('raw_files');
    const preview = document.getElementById('selectedFilesPreview');
    if (!input || !preview) return;

    const files = Array.from(input.files || []);
    if (files.length === 0) {
        preview.classList.add('hidden');
        preview.innerHTML = '';
        return;
    }

    const file = files[0];
    const formatter = new Intl.NumberFormat('id-ID');

    preview.innerHTML = `
        <div class="font-bold text-gray-700 mb-1">1 file dipilih (${formatter.format(Math.ceil(file.size / 1024))} KB)</div>
        <ul class="space-y-0.5"><li class="truncate">${escapeHtml(file.name)}</li></ul>
    `;
    preview.classList.remove('hidden');
}

document.getElementById('raw_files').addEventListener('change', updateSelectedFilesPreview);
document.getElementById('upload_access_mode').addEventListener('change', toggleUploadCustomAccess);
document.getElementById('upload_rule_type').addEventListener('change', populateUploadAccessValues);

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('initial_rules', JSON.stringify(uploadInitialRules));
    const rawFilesInput = document.getElementById('raw_files');
    const selectedBytes = Array.from(rawFilesInput.files || []).reduce((total, file) => total + file.size, 0);
    const uploadTimeoutMs = Math.max(300000, Math.ceil(selectedBytes / 1024) * 1500);
    const btn = document.getElementById('btnSubmitUpload');
    const btnText = document.getElementById('btnText');
    
    const finishUploadState = () => {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        btnText.innerHTML = 'Mulai Upload';
        hideUploadProgress();
    };

    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    btnText.innerHTML = 'Sedang Upload...';
    setUploadProgress(1, 'Menyiapkan file');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload.php', true);
    xhr.responseType = 'text';

    xhr.upload.addEventListener('progress', function(event) {
        if (event.lengthComputable) {
            const percent = Math.min(90, (event.loaded / event.total) * 90);
            setUploadProgress(percent, 'Mengirim file ke server');
        } else {
            setUploadProgress(25, 'Mengirim file ke server');
        }
    });

    xhr.addEventListener('load', function() {
        setUploadProgress(95, 'Server memproses file dan mengirim ke FTP');

        let data = null;
        try {
            data = JSON.parse(xhr.responseText || '{}');
        } catch (error) {
            console.error('Upload response is not JSON:', xhr.responseText);
            finishUploadState();
            const raw = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            const detail = raw ? raw.substring(0, 180) : 'Respons server kosong.';
            showFilingToast('error', 'Upload gagal', 'Respons server tidak valid: ' + detail);
            return;
        }

        finishUploadState();

        if (xhr.status >= 200 && xhr.status < 300 && data.success) {
            showFilingToast('success', 'Upload berhasil', data.message || 'File berhasil diupload.');
            closeUploadModal();
            setTimeout(() => {
                if(typeof refreshFileList === 'function') refreshFileList();
            }, 700);
        } else {
            showFilingToast('error', 'Upload gagal', data.message || 'Terjadi kesalahan saat upload.');
        }
    });

    xhr.addEventListener('error', function() {
        finishUploadState();
        showFilingToast('error', 'Koneksi gagal', 'Tidak bisa menghubungi server upload. Periksa koneksi atau coba lagi.');
    });

    xhr.addEventListener('timeout', function() {
        finishUploadState();
        showFilingToast('error', 'Upload timeout', 'Upload terlalu lama dan dihentikan. Koneksi FTP kemungkinan sedang lambat, silakan coba ulangi.');
    });

    xhr.timeout = uploadTimeoutMs;
    xhr.send(formData);
});
</script>
