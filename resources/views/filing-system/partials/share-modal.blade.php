<!-- File: modules/cbt_ops/filing_system/views/share_modal.php -->
<div id="modalShare" class="fixed inset-0 z-50 hidden bg-black/50 items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border-t-4 border-green-500">
        <div class="p-6 space-y-4">
            <div class="fs-modal-header fs-modal-header-accent fs-modal-header-green -m-6 mb-0 px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div class="flex items-center gap-3">
                    <span class="fs-icon-box fs-icon-box-green"><i class="fas fa-share-alt"></i></span>
                    <div>
                        <h3 class="fs-modal-title font-bold text-lg">Bagikan File</h3>
                        <p class="fs-modal-subtitle">Buat tautan akses sementara untuk file ini.</p>
                    </div>
                </div>
                <button type="button" onclick="closeShareModal()" class="fs-btn fs-close-btn"><i class="fas fa-times"></i></button>
            </div>
            
            <input type="hidden" id="shareFilingId">

            <!-- TAB HEADERS -->
            <div class="fs-tabs flex gap-4 border-b border-gray-200 text-sm font-bold">
                <button id="tabShareCreateBtn" onclick="switchShareTab('create')" class="pb-2 border-b-2 border-green-500 text-green-600">Buat Tautan</button>
                <button id="tabShareListBtn" onclick="switchShareTab('list')" class="pb-2 border-b-2 border-transparent text-gray-400 hover:text-green-500">Daftar Tautan</button>
                <button id="tabShareInternalBtn" onclick="switchShareTab('internal')" class="pb-2 border-b-2 border-transparent text-gray-400 hover:text-green-500">User / Departemen</button>
            </div>

            <!-- CREATE TAB -->
            <div id="tabShareCreate">
                <form id="formShareCreate" class="space-y-4 pt-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Berlaku Sampai</label>
                            <input type="datetime-local" name="expired_at" id="share_expired_at" class="fs-input w-full text-xs px-3 py-2 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Max Akses</label>
                            <input type="number" name="max_access" placeholder="Kosongkan jika tak terbatas" class="fs-input w-full text-xs px-3 py-2 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>
                    
                    <div class="fs-field-card flex items-center justify-between p-3 bg-gray-50 rounded border border-gray-100">
                        <span class="text-xs font-bold text-gray-700">Izinkan Download</span>
                        <input type="checkbox" name="allow_download" value="1" checked class="w-4 h-4 text-green-500 rounded focus:ring-green-400 cursor-pointer">
                    </div>

                    <div class="fs-field-card flex items-center justify-between p-3 bg-gray-50 rounded border border-gray-100">
                        <span class="text-xs font-bold text-gray-700">Gunakan Password</span>
                        <input type="checkbox" name="requires_password" id="chkRequiresPassword" value="1" class="w-4 h-4 text-green-500 rounded focus:ring-green-400 cursor-pointer" onchange="document.getElementById('pwdWrap').classList.toggle('hidden', !this.checked)">
                    </div>

                    <div id="pwdWrap" class="hidden">
                        <input type="text" name="share_password" placeholder="Masukkan password rahasia..." class="fs-input w-full text-xs px-3 py-2 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" id="btnShareSubmit" class="fs-btn fs-btn-green px-5 py-2 bg-green-500 text-white rounded text-xs font-bold hover:bg-green-600 shadow-sm transition">Generate Tautan</button>
                    </div>
                </form>

                <div id="shareResult" class="fs-soft-panel hidden mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-xs font-bold text-green-800 mb-2">Tautan berhasil dibuat! Simpan kode ini, tidak akan ditampilkan lagi.</p>
                    <div class="flex items-center gap-2">
                        <input type="text" id="generatedShareCode" readonly class="fs-input w-full text-center font-mono font-bold text-lg p-2 bg-white border border-green-300 rounded text-gray-800">
                        <button type="button" onclick="copyShareCode()" class="fs-btn p-2 bg-green-600 text-white rounded hover:bg-green-700" title="Copy"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <!-- LIST TAB -->
            <div id="tabShareList" class="hidden pt-2">
                <div class="fs-scrollbar max-h-60 overflow-y-auto">
                    <table class="fs-table w-full text-xs text-left">
                        <thead class="text-gray-400 uppercase bg-gray-50">
                            <tr>
                                <th class="px-3 py-2">Kode</th>
                                <th class="px-3 py-2">Akses</th>
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="shareListBody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <!-- INTERNAL TAB (User / Departemen) -->
            <div id="tabShareInternal" class="hidden pt-2 space-y-4">
                <p class="text-[11px] text-gray-500 italic">Bagikan file ini ke user atau departemen tertentu secara internal.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tambah User</label>
                        <input type="text" id="internal_user_search" placeholder="Cari nama user..." class="fs-input w-full text-xs px-3 py-2 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                        <div id="internal_user_list" class="fs-scrollbar mt-1.5 max-h-40 overflow-y-auto rounded-lg border border-gray-100 bg-white p-1.5">
                            <p class="px-2 py-2 text-[11px] italic text-gray-400">Memuat daftar user...</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tambah Departemen</label>
                        <input type="text" id="internal_dept_search" placeholder="Cari divisi..." class="fs-input w-full text-xs px-3 py-2 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
                        <div id="internal_dept_list" class="fs-scrollbar mt-1.5 max-h-40 overflow-y-auto rounded-lg border border-gray-100 bg-white p-1.5">
                            <p class="px-2 py-2 text-[11px] italic text-gray-400">Memuat daftar divisi...</p>
                        </div>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-gray-700">
                    <input type="checkbox" id="internal_all_check" class="w-4 h-4 text-green-500 rounded focus:ring-green-400 cursor-pointer">
                    Semua Karyawan (seluruh divisi/departemen)
                </label>

                <div id="internalRulesList" class="fs-scrollbar max-h-48 overflow-y-auto space-y-1.5 rounded-lg border border-gray-100 bg-gray-50 p-2">
                    <p class="text-[11px] italic text-gray-400">Memuat daftar pembagian...</p>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" onclick="saveInternalShares()" id="btnInternalSave" class="fs-btn fs-btn-green px-5 py-2 bg-green-500 text-white rounded text-xs font-bold hover:bg-green-600 shadow-sm transition">Simpan Pembagian</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function openShareModal(filingId, isRestricted = false) {
    document.getElementById('shareFilingId').value = filingId;
    document.getElementById('formShareCreate').reset();
    document.getElementById('shareResult').classList.add('hidden');
    document.getElementById('pwdWrap').classList.add('hidden');
    internalRules = [];
    internalAccessOptions = {};
    
    const expInput = document.getElementById('share_expired_at');
    if (isRestricted) {
        expInput.required = true;
        expInput.classList.add('border-red-300', 'bg-red-50');
    } else {
        expInput.required = false;
        expInput.classList.remove('border-red-300', 'bg-red-50');
    }

    switchShareTab('create');
    document.getElementById('modalShare').classList.remove('hidden');
    document.getElementById('modalShare').classList.add('flex');
}

function closeShareModal() {
    document.getElementById('modalShare').classList.remove('flex');
    document.getElementById('modalShare').classList.add('hidden');
}

function switchShareTab(tab) {
    const tabBtns = [
        { id: 'tabShareCreateBtn', active: 'create' },
        { id: 'tabShareListBtn', active: 'list' },
        { id: 'tabShareInternalBtn', active: 'internal' },
    ];

    tabBtns.forEach(btn => {
        const el = document.getElementById(btn.id);
        if (!el) return;
        if (tab === btn.active) {
            el.classList.add('border-green-500', 'text-green-600');
            el.classList.remove('border-transparent', 'text-gray-400');
        } else {
            el.classList.add('border-transparent', 'text-gray-400');
            el.classList.remove('border-green-500', 'text-green-600');
        }
    });

    document.getElementById('tabShareCreate').classList.toggle('hidden', tab !== 'create');
    document.getElementById('tabShareList').classList.toggle('hidden', tab !== 'list');
    document.getElementById('tabShareInternal').classList.toggle('hidden', tab !== 'internal');

    if (tab === 'list') {
        loadShareList();
    } else if (tab === 'internal') {
        loadInternalShares();
    }
}

document.getElementById('formShareCreate').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnShareSubmit');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Memproses...';

    const fd = new FormData(this);
    fd.append('action', 'create');
    fd.append('filing_id', document.getElementById('shareFilingId').value);

    fetch('share.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('generatedShareCode').value = res.raw_code;
            document.getElementById('shareResult').classList.remove('hidden');
            this.reset();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(err => alert('Terjadi kesalahan jaringan.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

function loadShareList() {
    const filingId = document.getElementById('shareFilingId').value;
    const body = document.getElementById('shareListBody');
    body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-xs text-gray-400">Loading...</td></tr>';
    
    fetch('share.php?action=list&filing_id=' + filingId)
    .then(r => r.json())
    .then(res => {
        if (!res.success || res.data.length === 0) {
            body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-xs text-gray-400">Belum ada tautan dibagikan.</td></tr>';
            return;
        }
        body.innerHTML = '';
        res.data.forEach(s => {
            const active = (s.is_active == 1) ? '<span class="text-[9px] bg-green-100 text-green-600 px-1 rounded">Aktif</span>' : '<span class="text-[9px] bg-red-100 text-red-600 px-1 rounded">Revoked</span>';
            const accesses = s.max_access ? `${s.access_count}/${s.max_access}` : s.access_count;
            body.innerHTML += `
                <tr class="border-b border-gray-50">
                    <td class="px-3 py-2 font-mono text-gray-700">${s.share_code_preview}<br>${active}</td>
                    <td class="px-3 py-2 text-gray-500">${accesses} akses</td>
                    <td class="px-3 py-2 text-right">
                        ${s.is_active == 1 ? `<button onclick="revokeShare(${s.rec_id})" class="text-red-500 hover:text-red-700" title="Cabut Akses"><i class="fas fa-ban"></i></button>` : ''}
                    </td>
                </tr>
            `;
        });
    });
}

function revokeShare(id) {
    if(!confirm('Cabut tautan ini secara permanen?')) return;
    const fd = new FormData();
    fd.append('action', 'revoke');
    fd.append('share_id', id);
    fetch('share.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) loadShareList();
        else alert('Error: ' + res.message);
    });
}

function copyShareCode() {
    const input = document.getElementById('generatedShareCode');
    input.select();
    document.execCommand("copy");
    alert("Share code disalin ke clipboard!");
}

// ===== Internal Share (User / Departemen / Semua Karyawan) =====
let internalRules = [];
let internalAccessOptions = {};

function internalTypeLabel(rule) {
    if (rule.share_cat == 4) return 'Semua Karyawan';
    if (rule.share_cat == 2) return 'Departemen';
    if (rule.share_cat == 3) return 'Company';
    return 'User';
}

function loadInternalShares() {
    const filingId = document.getElementById('shareFilingId').value;
    const box = document.getElementById('internalRulesList');
    box.innerHTML = '<p class="text-[11px] italic text-gray-400">Memuat daftar pembagian...</p>';

    fetch('share.php?action=internal_get&filing_id=' + filingId)
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            box.innerHTML = '<p class="text-[11px] italic text-red-400">Gagal memuat: ' + escapeHtml(res.message || '') + '</p>';
            return;
        }

        internalAccessOptions = res.data.access_options || {};
        internalRules = res.data.rules || [];

        populateInternalSelects();
        renderInternalRules();
    })
    .catch(() => {
        box.innerHTML = '<p class="text-[11px] italic text-red-400">Terjadi kesalahan koneksi.</p>';
    });
}

function renderInternalUserList(query) {
    const box = document.getElementById('internal_user_list');
    if (!box) return;

    const users = internalAccessOptions.user || [];
    const q = String(query || '').trim().toLowerCase();
    const filtered = q === '' ? users : users.filter(o => String(o.label).toLowerCase().includes(q));

    if (filtered.length === 0) {
        box.innerHTML = '<p class="px-2 py-2 text-[11px] italic text-gray-400">' + (q !== '' ? 'User tidak ditemukan.' : 'Tidak ada user tersedia') + '</p>';

        return;
    }

    box.innerHTML = filtered.map(opt => {
        const checked = internalRules.some(r => Number(r.share_cat) === 1 && String(r.othercode) === String(opt.value));

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                <input type="checkbox" class="internal-user-check h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500" value="${escapeHtml(String(opt.value))}" ${checked ? 'checked' : ''} onchange="onInternalUserToggle(this)">
                <span class="truncate">${escapeHtml(opt.label)}</span>
            </label>
        `;
    }).join('');
}

function renderInternalDeptList(query) {
    const box = document.getElementById('internal_dept_list');
    if (!box) return;

    const departments = internalAccessOptions.department || [];
    const q = String(query || '').trim().toLowerCase();
    const filtered = q === '' ? departments : departments.filter(o => String(o.label).toLowerCase().includes(q));

    if (filtered.length === 0) {
        box.innerHTML = '<p class="px-2 py-2 text-[11px] italic text-gray-400">' + (q !== '' ? 'Divisi tidak ditemukan.' : 'Tidak ada divisi tersedia') + '</p>';

        return;
    }

    box.innerHTML = filtered.map(opt => {
        const checked = internalRules.some(r => Number(r.share_cat) === 2 && String(r.othercode) === String(opt.value));

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                <input type="checkbox" class="internal-dept-check h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500" value="${escapeHtml(String(opt.value))}" ${checked ? 'checked' : ''} onchange="onInternalDeptToggle(this)">
                <span class="truncate">${escapeHtml(opt.label)}</span>
            </label>
        `;
    }).join('');
}

function populateInternalSelects() {
    renderInternalUserList(document.getElementById('internal_user_search')?.value || '');
    renderInternalDeptList(document.getElementById('internal_dept_search')?.value || '');
    const allCheck = document.getElementById('internal_all_check');
    if (allCheck) {
        allCheck.checked = internalRules.some(r => Number(r.share_cat) === 4);
    }
}

function onInternalUserToggle(cb) {
    const opt = (internalAccessOptions.user || []).find(o => String(o.value) === String(cb.value));
    const label = opt ? opt.label : cb.value;
    if (cb.checked) {
        addInternalRule(1, cb.value, label);
    } else {
        removeInternalRuleByCat(1, cb.value);
    }
}

function onInternalDeptToggle(cb) {
    const opt = (internalAccessOptions.department || []).find(o => String(o.value) === String(cb.value));
    const label = opt ? opt.label : cb.value;
    if (cb.checked) {
        addInternalRule(2, cb.value, label);
    } else {
        removeInternalRuleByCat(2, cb.value);
    }
}

function removeInternalRuleByCat(cat, value) {
    const idx = internalRules.findIndex(r => Number(r.share_cat) === cat && String(r.othercode) === String(value));
    if (idx !== -1) {
        internalRules.splice(idx, 1);
        refreshInternalLists();
    }
}

function internalHasRule(cat, value) {
    return internalRules.some(r => Number(r.share_cat) === cat && String(r.othercode) === String(value));
}

function addInternalRule(cat, value, label) {
    if (!value || internalHasRule(cat, value)) return;
    internalRules.push({ share_cat: cat, othercode: value, label: label });
    refreshInternalLists();
}

function removeInternalRule(index) {
    internalRules.splice(index, 1);
    refreshInternalLists();
}

function refreshInternalLists() {
    renderInternalUserList(document.getElementById('internal_user_search')?.value || '');
    renderInternalDeptList(document.getElementById('internal_dept_search')?.value || '');
    renderInternalRules();
}

function renderInternalRules() {
    const box = document.getElementById('internalRulesList');
    const allCheck = document.getElementById('internal_all_check');
    if (allCheck) {
        allCheck.checked = internalRules.some(r => Number(r.share_cat) === 4);
    }

    if (internalRules.length === 0) {
        box.innerHTML = '<p class="text-[11px] italic text-gray-400">Belum ada target. File hanya dapat diakses oleh pemilik.</p>';
        return;
    }

    box.innerHTML = internalRules.map((rule, index) => `
        <div class="flex items-center justify-between gap-2 rounded-lg bg-white border border-gray-100 px-3 py-2">
            <div class="min-w-0">
                <p class="text-[10px] font-bold text-gray-400 uppercase">${internalTypeLabel(rule)}</p>
                <p class="text-xs font-bold text-gray-700 truncate">${escapeHtml(rule.label || rule.othercode)}</p>
            </div>
            <button type="button" onclick="removeInternalRule(${index})" class="text-red-400 hover:text-red-600" title="Hapus">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

document.getElementById('internal_user_search')?.addEventListener('input', function () { renderInternalUserList(this.value); });
document.getElementById('internal_dept_search')?.addEventListener('input', function () { renderInternalDeptList(this.value); });

document.getElementById('internal_all_check')?.addEventListener('change', function () {
    if (this.checked) {
        addInternalRule(4, '0', 'Semua Karyawan');
    } else {
        const idx = internalRules.findIndex(r => Number(r.share_cat) === 4);
        if (idx !== -1) internalRules.splice(idx, 1);
        renderInternalRules();
    }
});

function saveInternalShares() {
    const btn = document.getElementById('btnInternalSave');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Menyimpan...';

    const fd = new FormData();
    fd.append('action', 'internal_save');
    fd.append('filing_id', document.getElementById('shareFilingId').value);
    fd.append('rules', JSON.stringify(internalRules));

    fetch('share.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            loadInternalShares();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(err => alert('Terjadi kesalahan jaringan.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>
