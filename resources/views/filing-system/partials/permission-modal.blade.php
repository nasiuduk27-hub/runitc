<!-- File: modules/cbt_ops/filing_system/views/permission_modal.php -->
<div id="modalPermission" class="fixed inset-0 z-[60] hidden bg-black/50 items-center justify-center backdrop-blur-sm transition-opacity p-2 md:p-4">
<div class="bg-white rounded-xl shadow-2xl w-[98vw] max-w-[1400px] overflow-hidden border-t-4 border-yellow-500 h-[94dvh] max-h-[94dvh] flex flex-col">        
        <div class="fs-modal-header fs-modal-header-accent fs-modal-header-yellow px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
            <div class="flex items-center gap-3">
                <span class="fs-icon-box fs-icon-box-yellow"><i class="fas fa-user-shield"></i></span>
                <div>
                    <h3 class="fs-modal-title font-bold text-lg">Atur Hak Akses File</h3>
                    <p class="fs-modal-subtitle">Kelola mode akses dan rule spesifik pengguna.</p>
                </div>
            </div>
            <button type="button" onclick="closePermissionModal()" class="fs-btn fs-close-btn"><i class="fas fa-times"></i></button>
        </div>
        
<div class="fs-scrollbar min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 md:p-6 flex flex-col gap-5">            <input type="hidden" id="perm_filing_id">

            <!-- Access Mode -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Share To</label>
                <select id="perm_access_mode" class="fs-select w-full md:w-1/2 px-4 py-2 text-sm border border-gray-300 rounded focus:ring-yellow-500 focus:border-yellow-500 bg-white">
                    <option value="private">Hanya Saya (Private)</option>
                    <option value="custom">User / Divisi / Semua Karyawan</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Atur siapa yang dapat mengakses file ini. Ubah aturan spesifik di bawah.</p>
            </div>

<!-- Rules Table -->
<div class="fs-card border border-gray-200 rounded-lg overflow-hidden shrink-0">
    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
        <h4 class="text-sm font-bold text-gray-700">Aturan Spesifik (Access Rules)</h4>
    </div>
    <div class="max-h-[280px] md:max-h-[320px] overflow-y-auto overflow-x-auto custom-scrollbar bg-white">
        <table class="fs-table w-full min-w-[980px] text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-100 sticky top-0 z-10">                                <th class="px-4 py-3 font-bold w-40">Tipe Akses</th>
                                <th class="px-4 py-3 font-bold min-w-[260px]">Nilai Akses</th>
                                <th class="px-4 py-3 font-bold text-center w-28">View</th>
                                <th class="px-4 py-3 font-bold text-center w-28">Download</th>
                                <th class="px-4 py-3 font-bold text-center w-28">Share</th>
                                <th class="px-4 py-3 font-bold text-center w-28">Manage</th>
                                <th class="px-4 py-3 font-bold text-right w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="perm_rules_body" class="divide-y divide-gray-100">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Rule Form -->
            <div class="fs-card fs-soft-panel bg-yellow-50/50 border border-yellow-100 rounded-lg p-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3">Tambah Aturan Baru</h4>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tipe</label>
                        <select id="new_rule_type" class="fs-select w-full px-3 py-2 text-xs border border-gray-300 rounded focus:ring-yellow-500 focus:border-yellow-500 bg-white">
                            <option value="user">User</option>
                            <option value="role">Role</option>
                            <option value="department">Department</option>
                            <option value="company">Company</option>
                            <option value="custom_group">Custom Group</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nilai Akses</label>
                        <select id="new_rule_value" class="fs-select w-full px-3 py-2 text-xs border border-gray-300 rounded focus:ring-yellow-500 focus:border-yellow-500 bg-white">
                            <option value="">Pilih tipe akses dulu</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex items-center justify-between gap-1 pb-1">
                        <label class="flex flex-col items-center cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-600 mb-1">View</span>
                            <input type="checkbox" id="new_rule_view" checked class="w-4 h-4 text-yellow-600 rounded border-gray-300 focus:ring-yellow-500">
                        </label>
                        <label class="flex flex-col items-center cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-600 mb-1">DL</span>
                            <input type="checkbox" id="new_rule_download" class="w-4 h-4 text-yellow-600 rounded border-gray-300 focus:ring-yellow-500">
                        </label>
                        <label class="flex flex-col items-center cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-600 mb-1">Share</span>
                            <input type="checkbox" id="new_rule_share" class="w-4 h-4 text-yellow-600 rounded border-gray-300 focus:ring-yellow-500">
                        </label>
                        <label class="flex flex-col items-center cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-600 mb-1">Mng</span>
                            <input type="checkbox" id="new_rule_manage" class="w-4 h-4 text-red-600 rounded border-gray-300 focus:ring-red-500">
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <button type="button" onclick="addRuleRow()" class="fs-btn fs-btn-yellow w-full px-3 py-2 bg-yellow-500 text-white rounded text-xs font-bold hover:bg-yellow-600 shadow-sm transition">Tambah</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="fs-modal-footer px-6 py-4 border-t border-gray-100 bg-gray-50 flex flex-col-reverse sm:flex-row justify-end gap-3 shrink-0">
            <button type="button" onclick="closePermissionModal()" class="fs-btn px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">Batal</button>
            <button type="button" onclick="savePermissions()" id="btnPermSave" class="fs-btn fs-btn-yellow px-5 py-2 text-sm font-bold text-white bg-yellow-600 rounded hover:bg-yellow-700 shadow-sm transition">Simpan Hak Akses</button>
        </div>
    </div>
</div>

<script>
let currentRules = [];
let currentSecurityLevel = 'normal';
let currentUserIsAdmin = false;
let accessOptions = {};

function openPermissionModal(filingId) {
    document.getElementById('perm_filing_id').value = filingId;
    currentRules = [];
    document.getElementById('perm_rules_body').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-sm text-gray-400">Loading...</td></tr>';
    
    document.getElementById('modalPermission').classList.remove('hidden');
    document.getElementById('modalPermission').classList.add('flex');

    fetch(`permission.php?action=get&filing_id=${filingId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('perm_access_mode').value = res.data.access_mode;
            currentSecurityLevel = res.data.security_level;
            currentUserIsAdmin = res.is_admin;
            currentRules = res.data.rules || [];
            accessOptions = res.data.access_options || {};
            populateAccessValues();
            renderRulesTable();
        } else {
            alert('Gagal mengambil data permission: ' + res.message);
            closePermissionModal();
        }
    }).catch(err => {
        alert('Terjadi kesalahan koneksi.');
        closePermissionModal();
    });
}

function closePermissionModal() {
    document.getElementById('modalPermission').classList.remove('flex');
    document.getElementById('modalPermission').classList.add('hidden');
}

function renderRulesTable() {
    const tbody = document.getElementById('perm_rules_body');
    tbody.innerHTML = '';

    if (currentRules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-gray-400 italic text-xs">Belum ada aturan spesifik. Akses mengikuti pilihan Share To.</td></tr>';
        return;
    }

    currentRules.forEach((rule, index) => {
        const shareDisabled = currentSecurityLevel === 'confidential' && !currentUserIsAdmin;
        const toggleBtn = (field, value, color = 'yellow', disabled = false) => {
            const activeClass = color === 'red'
                ? 'bg-red-600 border-red-600 text-white'
                : 'bg-yellow-500 border-yellow-500 text-white';
            const inactiveClass = 'bg-white border-gray-300 text-gray-300 hover:border-gray-400 hover:text-gray-500';
            const disabledClass = disabled ? 'opacity-40 cursor-not-allowed hover:border-gray-300 hover:text-gray-300' : 'cursor-pointer';
            const nextValue = value ? 'false' : 'true';
            const icon = value ? 'fa-check' : 'fa-minus';

            return `
                <button type="button"
                    ${disabled ? 'disabled title="Confidential"' : `onclick="updateRule(${index}, '${field}', ${nextValue})"`}
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border-2 text-xs shadow-sm transition ${value ? activeClass : inactiveClass} ${disabledClass}">
                    <i class="fas ${icon}"></i>
                </button>
            `;
        };
        
        tbody.innerHTML += `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-bold text-gray-700 uppercase text-xs whitespace-nowrap">${formatAccessType(rule.access_type)}</td>
                <td class="px-4 py-3 text-gray-700 text-xs break-all">
                    <div class="font-semibold">${getAccessLabel(rule.access_type, String(rule.access_value))}</div>
                    <div class="font-mono text-[10px] text-gray-400">${rule.access_value}</div>
                </td>
                <td class="px-4 py-3 text-center">${toggleBtn('can_view', !!Number(rule.can_view))}</td>
                <td class="px-4 py-3 text-center">${toggleBtn('can_download', !!Number(rule.can_download))}</td>
                <td class="px-4 py-3 text-center">${toggleBtn('can_share', !!Number(rule.can_share), 'yellow', shareDisabled)}</td>
                <td class="px-4 py-3 text-center">${toggleBtn('can_manage', !!Number(rule.can_manage), 'red')}</td>
                <td class="px-4 py-3 text-right">
                    <button type="button" onclick="removeRule(${index})" class="px-3 py-2 text-xs font-bold text-red-500 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition whitespace-nowrap">
                        <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                </td>
            </tr>
        `;
    });
}

function populateAccessValues() {
    const type = document.getElementById('new_rule_type').value;
    const valueSelect = document.getElementById('new_rule_value');
    const options = accessOptions[type] || [];

    valueSelect.innerHTML = '<option value="">Pilih ' + formatAccessType(type) + '</option>';
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

function formatAccessType(type) {
    const labels = {
        user: 'User',
        role: 'Role',
        department: 'Department',
        company: 'Company',
        custom_group: 'Custom Group'
    };
    return labels[type] || type;
}

function getAccessLabel(type, value) {
    const option = (accessOptions[type] || []).find(opt => String(opt.value) === String(value));
    return option ? option.label : value;
}

function updateRule(index, field, value) {
    currentRules[index][field] = value ? 1 : 0;
    
    // Auto-escalate logic
    if (field === 'can_manage' && value) {
        currentRules[index]['can_view'] = 1;
        currentRules[index]['can_download'] = 1;
        if (currentSecurityLevel !== 'confidential' || currentUserIsAdmin) {
            currentRules[index]['can_share'] = 1;
        }
    } else if (field === 'can_share' && value) {
        currentRules[index]['can_view'] = 1;
    } else if (field === 'can_download' && value) {
        currentRules[index]['can_view'] = 1;
    }

    renderRulesTable(); // Re-render to show auto-checked boxes
}

function addRuleRow() {
    const type = document.getElementById('new_rule_type').value;
    const value = document.getElementById('new_rule_value').value;
    
    if (!value) {
        alert("Nilai akses wajib diisi.");
        return;
    }

    // Check duplicate
    const exists = currentRules.some(r => r.access_type === type && r.access_value === value);
    if (exists) {
        alert("Aturan untuk kombinasi tipe dan nilai ini sudah ada.");
        return;
    }

    let shareVal = document.getElementById('new_rule_share').checked ? 1 : 0;
    if (currentSecurityLevel === 'confidential' && !currentUserIsAdmin) {
        shareVal = 0; // Force false
    }

    let manageVal = document.getElementById('new_rule_manage').checked ? 1 : 0;
    let dlVal = document.getElementById('new_rule_download').checked ? 1 : 0;
    let viewVal = document.getElementById('new_rule_view').checked ? 1 : 0;

    if (manageVal) { viewVal = 1; dlVal = 1; }
    if (dlVal || shareVal) { viewVal = 1; }

    currentRules.push({
        access_type: type,
        access_value: value,
        can_view: viewVal,
        can_download: dlVal,
        can_share: shareVal,
        can_manage: manageVal
    });

    document.getElementById('new_rule_value').value = '';
    renderRulesTable();
}

document.getElementById('new_rule_type').addEventListener('change', populateAccessValues);

function removeRule(index) {
    currentRules.splice(index, 1);
    renderRulesTable();
}

function savePermissions() {
    const btn = document.getElementById('btnPermSave');
    btn.disabled = true;
    btn.innerHTML = 'Menyimpan...';

    const filingId = document.getElementById('perm_filing_id').value;
    const mode = document.getElementById('perm_access_mode').value;

    const fd = new FormData();
    fd.append('action', 'save_all');
    fd.append('filing_id', filingId);
    fd.append('access_mode', mode);
    fd.append('rules', JSON.stringify(currentRules));

    fetch('permission.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    }).catch(err => {
        alert('Terjadi kesalahan jaringan.');
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = 'Simpan Hak Akses';
    });
}
</script>
