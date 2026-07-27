<!-- File: modules/cbt_ops/filing_system/views/edit_metadata_modal.php -->
<div id="modalEditMetadata" class="fixed inset-0 z-50 hidden bg-black/50 items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border-t-4 border-indigo-500 max-h-[90vh] flex flex-col">
        
        <div class="fs-modal-header fs-modal-header-accent fs-modal-header-indigo px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
            <div class="flex items-center gap-3">
                <span class="fs-icon-box fs-icon-box-indigo"><i class="fas fa-edit"></i></span>
                <div>
                    <h3 class="fs-modal-title font-bold text-lg">Edit File Info</h3>
                    <p class="fs-modal-subtitle">Perbarui metadata, keamanan, dan masa aktif file.</p>
                </div>
            </div>
            <button type="button" onclick="closeEditModal()" class="fs-btn fs-close-btn"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="fs-scrollbar overflow-y-auto p-6 grow">
            <form id="formEditMetadata" class="space-y-5">
                <input type="hidden" name="filing_id" id="edit_filing_id">
                <input type="hidden" name="action" value="update_metadata">

                <!-- ROW 1 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Tampilan (Display Name) <span class="text-red-500">*</span></label>
                        <input type="text" name="display_name" id="edit_display_name" required maxlength="255" class="fs-input w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tipe Isi File</label>
                        <select name="declared_file_type" id="edit_declared_file_type" class="fs-select w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                </div>

                <!-- ROW 2 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Level Keamanan</label>
                        <select name="security_level" id="edit_security_level" class="fs-select w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <option value="normal">Normal</option>
                            <option value="restricted">Restricted</option>
                            <option value="confidential">Confidential (No Share)</option>
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1 italic">Mengubah ke Confidential akan mencabut Share Code yang aktif.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Keywords</label>
                        <input type="text" name="keywords" id="edit_keywords" placeholder="Koma terpisah..." class="fs-input w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <!-- EXPIRY SECTION -->
                <div class="fs-field-card border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-4">
                    <div class="flex items-center gap-2 mb-2">
                        <input type="checkbox" id="chkEnableExpiry" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <label for="chkEnableExpiry" class="text-sm font-bold text-gray-700">Aktifkan Kadaluarsa File (Expiry)</label>
                    </div>
                    
                    <div id="expiryWrap" class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Berlaku Sampai <span class="text-red-500">*</span></label>
                            <input type="datetime-local" name="expired_at" id="edit_expired_at" class="fs-input w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Aksi saat Expired</label>
                            <select name="expired_action" id="edit_expired_action" class="fs-select w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                <option value="trash">Pindahkan ke Sampah (Default)</option>
                                <option value="archive">Arsipkan</option>
                                <option value="none">Hanya ubah status</option>
                                <!-- Option Delete injected via JS if admin -->
                            </select>
                        </div>
                    </div>
                </div>

                <!-- NOTES -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan</label>
                    <textarea name="notes" id="edit_notes" rows="3" class="fs-textarea w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
            </form>
        </div>

        <div class="fs-modal-footer px-6 py-4 border-t border-gray-100 bg-gray-50 flex flex-col-reverse sm:flex-row justify-end gap-3 shrink-0">
            <button type="button" onclick="closeEditModal()" class="fs-btn px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">Batal</button>
            <button type="submit" form="formEditMetadata" id="btnEditSubmit" class="fs-btn fs-btn-indigo px-5 py-2 text-sm font-bold text-white bg-indigo-600 rounded hover:bg-indigo-700 shadow-sm transition">Simpan Perubahan</button>
        </div>
    </div>
</div>

<script>
let fileTypesLoaded = false;
let isAdminUser = false; // Akan diset dari global / response fetch

document.getElementById('chkEnableExpiry').addEventListener('change', function() {
    const wrap = document.getElementById('expiryWrap');
    const input = document.getElementById('edit_expired_at');
    if (this.checked) {
        wrap.classList.remove('hidden');
        input.required = true;
    } else {
        wrap.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
});

function openEditModal(filingId) {
    document.getElementById('edit_filing_id').value = filingId;
    document.getElementById('formEditMetadata').reset();
    document.getElementById('expiryWrap').classList.add('hidden');
    
    // Fetch file data
    const fd = new FormData();
    fd.append('action', 'get_file_info');
    fd.append('filing_id', filingId);

    fetch('action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const data = res.data;
            isAdminUser = res.is_admin;

            // Load file types options dynamically
            const selType = document.getElementById('edit_declared_file_type');
            selType.innerHTML = '';
            for(const [code, name] of Object.entries(res.file_types)) {
                selType.innerHTML += `<option value="${code}">${name}</option>`;
            }

            // Load Admin Expired Action
            const selAction = document.getElementById('edit_expired_action');
            if(isAdminUser && !Array.from(selAction.options).some(o => o.value === 'delete')) {
                selAction.innerHTML += `<option value="delete" class="text-red-500 font-bold">Hapus Permanen (Admin)</option>`;
            }

            // Populate form
            document.getElementById('edit_display_name').value = data.display_name;
            selType.value = data.declared_file_type || 'mixed';
            document.getElementById('edit_security_level').value = data.security_level;
            document.getElementById('edit_keywords').value = data.keywords || '';
            document.getElementById('edit_notes').value = data.notes || '';
            
            if (data.expired_at) {
                document.getElementById('chkEnableExpiry').checked = true;
                document.getElementById('expiryWrap').classList.remove('hidden');
                
                // HTML datetime-local requires YYYY-MM-DDThh:mm format
                let dt = data.expired_at.replace(' ', 'T');
                if(dt.length > 16) dt = dt.substring(0, 16);
                document.getElementById('edit_expired_at').value = dt;
                
                document.getElementById('edit_expired_action').value = data.expired_action || 'trash';
            }

            document.getElementById('modalEditMetadata').classList.remove('hidden');
            document.getElementById('modalEditMetadata').classList.add('flex');
        } else {
            alert('Gagal mengambil data: ' + res.message);
        }
    }).catch(err => alert('Error fetching data.'));
}

function closeEditModal() {
    document.getElementById('modalEditMetadata').classList.remove('flex');
    document.getElementById('modalEditMetadata').classList.add('hidden');
}

document.getElementById('formEditMetadata').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnEditSubmit');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Menyimpan...';

    const fd = new FormData(this);

    // Validasi basic path traversal di nama
    const name = fd.get('display_name');
    if(name.includes('../') || name.includes('..\\') || name.includes('/') || name.includes('\\')) {
        alert("Nama Tampilan tidak boleh mengandung karakter path ( /, \\, atau ../ ).");
        btn.disabled = false; btn.innerHTML = originalText;
        return;
    }

    fetch('action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Metadata berhasil diperbarui.');
            location.reload();
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
</script>
