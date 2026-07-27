<!-- File: modules/cbt_ops/filing_system/views/info_drawer.php -->
<div id="drawerInfo" class="fixed inset-0 z-[70] hidden overflow-hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" onclick="closeInfoDrawer()"></div>
    <div class="absolute inset-y-0 right-0 max-w-full flex">
        <div class="w-screen max-w-xl flex flex-col bg-white shadow-2xl animate-slide-in-right">
            
            <!-- HEADER -->
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-blue-700 to-indigo-600 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-info-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-lg leading-none">Informasi File</h3>
                        <p class="text-blue-100 text-[10px] uppercase font-bold mt-1 tracking-widest" id="drawer_file_code">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeInfoDrawer()" class="fs-btn w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- CONTENT -->
            <div class="fs-scrollbar flex-1 overflow-y-auto custom-scrollbar bg-gray-50/30 p-6 space-y-8">
                
                <!-- BASIC SECTION -->
                <section>
                    <div class="flex justify-between items-start mb-4">
                        <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest">Detail Dasar</h4>
                        <div id="drawer_status_badge"></div>
                    </div>
                    <div class="fs-card bg-white rounded-2xl border border-gray-100 p-5 shadow-sm space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Nama Tampilan</p>
                                <p class="text-sm font-bold text-gray-800 break-words" id="drawer_display_name">---</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Pemilik</p>
                                <p class="text-sm font-bold text-blue-600" id="drawer_owner">---</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Ukuran ZIP</p>
                                <p class="text-sm font-bold text-gray-800" id="drawer_size">---</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Jumlah File</p>
                                <p class="text-sm font-bold text-gray-800" id="drawer_file_count">---</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-50 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Nama File Asli</p>
                            <p class="text-xs font-medium text-gray-500 italic break-all" id="drawer_original_name">---</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-gray-50 pt-4">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Tipe (User)</p>
                                <p class="text-xs font-bold text-gray-600" id="drawer_type_user">---</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Tipe (System)</p>
                                <p class="text-xs font-bold text-gray-600" id="drawer_type_system">---</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SECURITY & EXPIRY -->
                <section>
                    <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Keamanan & Masa Aktif</h4>
                    <div class="fs-card bg-white rounded-2xl border border-gray-100 p-5 shadow-sm space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Level Keamanan</p>
                                <div id="drawer_security_badge" class="mt-1"></div>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Mode Akses</p>
                                <p class="text-xs font-black text-gray-700 mt-1 uppercase" id="drawer_access_mode">---</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-50 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Informasi Kadaluarsa</p>
                            <div id="drawer_expiry_info" class="text-xs text-gray-600 space-y-1">---</div>
                        </div>
                    </div>
                </section>

                <!-- PERMISSION SUMMARY -->
                <section>
                    <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Hak Akses Khusus</h4>
                    <div id="drawer_permissions" class="space-y-2">
                        <!-- Rules -->
                    </div>
                </section>

                <!-- SHARES SUMMARY -->
                <section>
                    <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Ringkasan Berbagi (Share)</h4>
                    <div class="fs-card bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-center flex-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Tautan Aktif</p>
                                <p class="text-xl font-black text-green-600" id="drawer_share_active">0</p>
                            </div>
                            <div class="w-px h-8 bg-gray-100"></div>
                            <div class="text-center flex-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase">Total Akses</p>
                                <p class="text-xl font-black text-blue-600" id="drawer_share_access">0</p>
                            </div>
                        </div>
                        <div id="drawer_shares_list" class="space-y-2 border-t border-gray-50 pt-4">
                            <!-- Share details -->
                        </div>
                    </div>
                </section>

                <!-- AUDIT TIMELINE -->
                <section>
                    <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Aktivitas Terbaru</h4>
                    <div class="relative space-y-4 before:absolute before:inset-0 before:ml-4 before:-translate-x-px before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-200 before:to-transparent" id="drawer_audit_timeline">
                        <!-- Timeline items -->
                    </div>
                </section>

                <!-- ADMIN ONLY SECTION -->
                <section id="drawer_admin_section" class="hidden">
                    <h4 class="text-xs font-black text-red-400 uppercase tracking-widest mb-4 italic">Metadata Server (Admin Only)</h4>
                        <div class="fs-card bg-red-50/50 rounded-2xl border border-red-100 p-5 space-y-3 font-mono text-[10px]">
                        <p><span class="font-bold text-red-600">Storage Root:</span> <span id="drawer_admin_root"></span></p>
                        <p><span class="font-bold text-red-600">Path Fisik:</span> <span id="drawer_admin_path" class="break-all"></span></p>
                        <p><span class="font-bold text-red-600">Nama Fisik:</span> <span id="drawer_admin_name" class="break-all"></span></p>
                    </div>
                </section>

            </div>

            <!-- FOOTER -->
            <div class="fs-drawer-footer px-6 py-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between sm:items-center gap-3 shrink-0">
                <div class="text-[10px] text-gray-400">
                    Dibuat pada: <span id="drawer_created_at">---</span><br>
                    Diubah pada: <span id="drawer_updated_at">---</span>
                </div>
                <button type="button" onclick="closeInfoDrawer()" class="fs-btn px-5 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-bold hover:bg-gray-300 transition-colors">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes slide-in-right {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.animate-slide-in-right { animation: slide-in-right 0.3s ease-out; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #D1D5DB; }
</style>

<script>
function openInfoDrawer(id) {
    const drawer = document.getElementById('drawerInfo');
    drawer.classList.remove('hidden');
    drawer.classList.add('flex');

    fetch('info.php?id=' + id)
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.message);
            closeInfoDrawer();
            return;
        }

        const f = res.file;
        
        // Basic Info
        document.getElementById('drawer_file_code').innerText = res.can_see_full_info && f.file_code ? f.file_code : 'DETAIL DASAR';
        document.getElementById('drawer_display_name').innerText = f.display_name;
        document.getElementById('drawer_owner').innerText = f.owner_name + (f.owner_alias ? ' ('+f.owner_alias+')' : '');
        document.getElementById('drawer_size').innerText = f.formatted_size;
        document.getElementById('drawer_file_count').innerText = f.file_count + ' File';
        document.getElementById('drawer_original_name').innerText = f.original_name;
        document.getElementById('drawer_type_user').innerText = f.declared_file_type || f.detected_file_type || 'Mixed';
        document.getElementById('drawer_type_system').innerText = f.detected_file_type || 'Unknown';
        document.getElementById('drawer_access_mode').innerText = f.access_mode ? f.access_mode.replace('_', ' ') : '-';
        document.getElementById('drawer_created_at').innerText = f.created_at;
        document.getElementById('drawer_updated_at').innerText = f.updated_at || '-';

        // Badges
        document.getElementById('drawer_status_badge').innerHTML = `<span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-full uppercase">${f.status_label}</span>`;
        
        let secColor = f.security_level === 'confidential' ? 'red' : (f.security_level === 'restricted' ? 'orange' : 'blue');
        document.getElementById('drawer_security_badge').innerHTML = `<span class="bg-${secColor}-50 text-${secColor}-600 border border-${secColor}-100 text-[10px] font-black px-2 py-1 rounded uppercase tracking-tighter">${f.security_label}</span>`;

        const fullInfoSections = [
            document.getElementById('drawer_permissions').closest('section'),
            document.getElementById('drawer_share_active').closest('section'),
            document.getElementById('drawer_audit_timeline').closest('section')
        ];

        fullInfoSections.forEach(section => {
            if (res.can_see_full_info) {
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        });

        // Expiry
        let expHtml = 'Hanya ditampilkan untuk pemilik/admin.';
        if (res.can_see_full_info) {
            expHtml = f.expired_at ? `<p><b>Waktu:</b> ${f.expired_at}</p><p><b>Aksi:</b> ${f.expired_action.toUpperCase()}</p>` : 'Tidak ada batasan waktu (Permanen)';
            if (f.expired_processed_at) expHtml += `<p class="text-red-500 mt-1 font-bold italic">Sudah diproses pada ${f.expired_processed_at}</p>`;
        }
        document.getElementById('drawer_expiry_info').innerHTML = expHtml;

        if (!res.can_see_full_info) {
            document.getElementById('drawer_access_mode').innerText = '-';
            document.getElementById('drawer_admin_section').classList.add('hidden');
            return;
        }

        // Inspect button is available from the file row dropdown. Keep drawer load safe if no drawer button exists.
        const btnInspect = document.getElementById('btn_drawer_inspect');
        if (btnInspect) {
            if (f.status === 'active' || f.status === 'archived') {
                btnInspect.classList.remove('hidden');
                btnInspect.onclick = () => openInspectModal(f.rec_id);
            } else {
                btnInspect.classList.add('hidden');
            }
        }

        // Permissions
        const permDiv = document.getElementById('drawer_permissions');
        permDiv.innerHTML = '';
        if (res.permissions.length === 0) {
            permDiv.innerHTML = '<p class="text-xs text-gray-400 italic bg-white p-3 rounded-lg border border-dashed border-gray-200">Belum ada aturan akses spesifik.</p>';
        } else {
            res.permissions.forEach(p => {
                permDiv.innerHTML += `
                    <div class="bg-white p-3 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">${p.access_type}</p>
                            <p class="text-xs font-black text-gray-700">${p.access_value}</p>
                        </div>
                        <div class="flex gap-1">
                            ${p.can_view ? '<span class="w-4 h-4 bg-blue-100 text-blue-600 rounded flex items-center justify-center text-[8px]" title="View">V</span>' : ''}
                            ${p.can_download ? '<span class="w-4 h-4 bg-green-100 text-green-600 rounded flex items-center justify-center text-[8px]" title="Download">D</span>' : ''}
                            ${p.can_share ? '<span class="w-4 h-4 bg-yellow-100 text-yellow-600 rounded flex items-center justify-center text-[8px]" title="Share">S</span>' : ''}
                            ${p.can_manage ? '<span class="w-4 h-4 bg-red-100 text-red-600 rounded flex items-center justify-center text-[8px]" title="Manage">M</span>' : ''}
                        </div>
                    </div>
                `;
            });
        }

        // Shares
        document.getElementById('drawer_share_active').innerText = res.shares.stats.active_links;
        document.getElementById('drawer_share_access').innerText = res.shares.stats.total_access || 0;
        const shareList = document.getElementById('drawer_shares_list');
        shareList.innerHTML = '';
        if (res.shares.details.length === 0) {
            shareList.innerHTML = '<p class="text-center text-[10px] text-gray-400 py-2">Belum pernah dibagikan.</p>';
        } else {
            res.shares.details.slice(0, 3).forEach(s => {
                const statusColor = s.is_active == 1 ? 'green' : 'red';
                shareList.innerHTML += `
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="font-mono text-gray-500">${s.share_code_preview}</span>
                        <span class="font-bold text-${statusColor}-600">${s.access_count} Akses</span>
                    </div>
                `;
            });
        }

        // Audit Timeline
        const auditTimeline = document.getElementById('drawer_audit_timeline');
        auditTimeline.innerHTML = '';
        res.audits.forEach(a => {
            auditTimeline.innerHTML += `
                <div class="relative pl-8">
                    <div class="absolute left-0 top-1 w-4 h-4 rounded-full border-2 border-white shadow-sm ring-2 ring-gray-100 bg-indigo-500"></div>
                    <div class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-[9px] font-bold text-gray-400 uppercase">${a.created_at}</span>
                            <span class="text-[8px] font-black bg-indigo-50 text-indigo-600 px-1 rounded uppercase">${a.action_label}</span>
                        </div>
                        <p class="text-[10px] text-gray-700 font-bold mb-1">${a.user_name}</p>
                        <p class="text-[10px] text-gray-500 italic">${a.notes}</p>
                    </div>
                </div>
            `;
        });

        // Admin Section
        const adminSection = document.getElementById('drawer_admin_section');
        if (res.is_admin) {
            adminSection.classList.remove('hidden');
            document.getElementById('drawer_admin_root').innerText = f.storage_root;
            document.getElementById('drawer_admin_path').innerText = f.storage_path;
            document.getElementById('drawer_admin_name').innerText = f.storage_name;
        } else {
            adminSection.classList.add('hidden');
        }

    }).catch(err => {
        alert('Gagal memuat informasi file.');
        closeInfoDrawer();
    });
}

function closeInfoDrawer() {
    const drawer = document.getElementById('drawerInfo');
    drawer.classList.add('hidden');
    drawer.classList.remove('flex');
}
</script>
