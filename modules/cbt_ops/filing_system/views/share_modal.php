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

        </div>
    </div>
</div>

<script>
function openShareModal(filingId, isRestricted = false) {
    document.getElementById('shareFilingId').value = filingId;
    document.getElementById('formShareCreate').reset();
    document.getElementById('shareResult').classList.add('hidden');
    document.getElementById('pwdWrap').classList.add('hidden');
    
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
    if (tab === 'create') {
        document.getElementById('tabShareCreateBtn').classList.add('border-green-500', 'text-green-600');
        document.getElementById('tabShareCreateBtn').classList.remove('border-transparent', 'text-gray-400');
        document.getElementById('tabShareListBtn').classList.add('border-transparent', 'text-gray-400');
        document.getElementById('tabShareListBtn').classList.remove('border-green-500', 'text-green-600');
        
        document.getElementById('tabShareCreate').classList.remove('hidden');
        document.getElementById('tabShareList').classList.add('hidden');
    } else {
        document.getElementById('tabShareListBtn').classList.add('border-green-500', 'text-green-600');
        document.getElementById('tabShareListBtn').classList.remove('border-transparent', 'text-gray-400');
        document.getElementById('tabShareCreateBtn').classList.add('border-transparent', 'text-gray-400');
        document.getElementById('tabShareCreateBtn').classList.remove('border-green-500', 'text-green-600');
        
        document.getElementById('tabShareCreate').classList.add('hidden');
        document.getElementById('tabShareList').classList.remove('hidden');
        loadShareList();
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
</script>
