<!-- File: modules/cbt_ops/filing_system/views/zip_inspection_modal.php -->
<div id="modalZipInspection" class="fixed inset-0 z-[80] hidden bg-black/50 items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl overflow-hidden border-t-4 border-indigo-500 max-h-[90vh] flex flex-col">
        
        <div class="fs-modal-header fs-modal-header-accent fs-modal-header-indigo px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <span class="fs-icon-box fs-icon-box-indigo"><i class="fas fa-search"></i></span>
                <div class="min-w-0">
                    <h3 class="fs-modal-title font-bold text-lg leading-none">Inspect ZIP Contents</h3>
                    <p class="fs-modal-subtitle truncate" id="inspect_file_title">Loading...</p>
                </div>
            </div>
            <button type="button" onclick="closeInspectModal()" class="fs-btn fs-close-btn transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="fs-scrollbar overflow-y-auto p-6 grow bg-gray-50 flex flex-col gap-6 custom-scrollbar" id="inspect_content">
            <!-- Loading State -->
            <div id="inspect_loading" class="flex flex-col items-center justify-center py-20 text-indigo-500">
                <i class="fas fa-circle-notch fa-spin text-4xl mb-4"></i>
                <p class="font-bold">Membuka file dari storage server...</p>
                <p class="text-xs text-gray-400 mt-1 text-center">Bergantung pada ukuran file, proses ini mungkin membutuhkan waktu beberapa saat.<br>Tidak ada file yang di-extract ke public directory.</p>
            </div>

            <!-- Result State -->
            <div id="inspect_result" class="hidden flex-col gap-6">
                
                <!-- Global Warnings -->
                <div id="inspect_warnings_box" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 mr-3"></i>
                        <div>
                            <h3 class="text-red-800 font-bold text-sm">Peringatan Keamanan Terdeteksi</h3>
                            <ul id="inspect_warnings_list" class="list-disc list-inside text-xs text-red-700 mt-1"></ul>
                        </div>
                    </div>
                </div>

                <!-- Summaries -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="fs-card bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-center">
                        <i class="fas fa-file-archive text-gray-300 text-2xl mb-2"></i>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total File</p>
                        <p class="text-xl font-black text-gray-800" id="inspect_stat_count">0</p>
                    </div>
                    <div class="fs-card bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-center">
                        <i class="fas fa-weight-hanging text-gray-300 text-2xl mb-2"></i>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Ukuran ZIP</p>
                        <p class="text-xl font-black text-gray-800" id="inspect_stat_comp">0</p>
                    </div>
                    <div class="fs-card bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-center">
                        <i class="fas fa-expand-arrows-alt text-blue-300 text-2xl mb-2"></i>
                        <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Ukuran Diekstrak</p>
                        <p class="text-xl font-black text-blue-600" id="inspect_stat_uncomp">0</p>
                    </div>
                    <div class="fs-card bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-center">
                        <i class="fas fa-fingerprint text-indigo-300 text-2xl mb-2"></i>
                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Tipe Dominan</p>
                        <p class="text-xl font-black text-indigo-600 uppercase" id="inspect_stat_type">-</p>
                    </div>
                </div>

                <!-- Extensions Chart -->
                <div class="fs-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <h4 class="fs-section-title text-xs font-black text-gray-400 uppercase tracking-widest mb-3">Distribusi Ekstensi</h4>
                    <div class="flex flex-wrap gap-2" id="inspect_ext_badges">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- File List Table -->
                <div class="fs-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-widest">Daftar File</h4>
                        <span class="text-[10px] font-bold bg-blue-100 text-blue-600 px-2 py-1 rounded" id="inspect_list_info">Menampilkan x dari y file</span>
                    </div>
                    <div class="overflow-x-auto max-h-96 custom-scrollbar">
                        <table class="fs-table w-full text-xs text-left">
                            <thead class="text-[10px] text-gray-400 uppercase bg-white sticky top-0 shadow-sm z-10">
                                <tr>
                                    <th class="px-5 py-3 font-bold">Nama File / Path</th>
                                    <th class="px-5 py-3 font-bold text-center">Ext</th>
                                    <th class="px-5 py-3 font-bold text-right">Compressed</th>
                                    <th class="px-5 py-3 font-bold text-right">Uncompressed</th>
                                </tr>
                            </thead>
                            <tbody id="inspect_files_body" class="divide-y divide-gray-50">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="fs-modal-footer px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end shrink-0">
            <button type="button" onclick="closeInspectModal()" class="fs-btn px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition shadow-sm">Tutup</button>
        </div>
    </div>
</div>

<script>
function formatBytes(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

function openInspectModal(id) {
    document.getElementById('modalZipInspection').classList.remove('hidden');
    document.getElementById('modalZipInspection').classList.add('flex');
    
    document.getElementById('inspect_loading').classList.remove('hidden');
    document.getElementById('inspect_result').classList.add('hidden');
    document.getElementById('inspect_file_title').innerText = 'Mengambil data...';

    fetch('inspect.php?id=' + id)
    .then(r => r.json())
    .then(res => {
        document.getElementById('inspect_loading').classList.add('hidden');
        
        if (!res.success) {
            alert('Gagal Inspeksi ZIP: ' + res.message);
            closeInspectModal();
            return;
        }

        document.getElementById('inspect_result').classList.remove('hidden');
        document.getElementById('inspect_result').classList.add('flex');
        
        // Header
        document.getElementById('inspect_file_title').innerText = `${res.file_info.display_name} (${res.file_info.file_code})`;

        // Warnings
        const warnBox = document.getElementById('inspect_warnings_box');
        const warnList = document.getElementById('inspect_warnings_list');
        if (res.warnings && res.warnings.length > 0) {
            warnBox.classList.remove('hidden');
            warnList.innerHTML = res.warnings.map(w => `<li>${w}</li>`).join('');
        } else {
            warnBox.classList.add('hidden');
        }

        // Stats
        document.getElementById('inspect_stat_count').innerText = res.file_count;
        document.getElementById('inspect_stat_comp').innerText = formatBytes(res.total_compressed_size);
        document.getElementById('inspect_stat_uncomp').innerText = formatBytes(res.total_uncompressed_size);
        document.getElementById('inspect_stat_type').innerText = res.detected_file_type;

        // Extensions
        const extBadges = document.getElementById('inspect_ext_badges');
        extBadges.innerHTML = '';
        if (Object.keys(res.extensions).length === 0) {
            extBadges.innerHTML = '<span class="text-xs text-gray-400 italic">Tidak ada ekstensi terdeteksi.</span>';
        } else {
            for (const [ext, count] of Object.entries(res.extensions)) {
                extBadges.innerHTML += `<span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-1 rounded border border-gray-200 uppercase">${ext}: <span class="text-indigo-600">${count}</span></span>`;
            }
        }

        // List Info
        document.getElementById('inspect_list_info').innerText = `Menampilkan ${res.listed_count} dari ${res.file_count} file`;

        // Files Table
        const tbody = document.getElementById('inspect_files_body');
        tbody.innerHTML = '';
        res.files.forEach(f => {
            let warnHtml = '';
            let rowClass = 'hover:bg-gray-50';
            let nameClass = 'text-gray-700 font-medium';

            if (f.warnings.length > 0) {
                rowClass = 'bg-red-50/50 hover:bg-red-50';
                nameClass = 'text-red-700 font-bold';
                warnHtml = `<br><span class="text-[9px] font-bold text-red-500 uppercase bg-red-100 px-1 rounded">${f.warnings.join(', ')}</span>`;
            }

            tbody.innerHTML += `
                <tr class="${rowClass} transition-colors border-b border-gray-50">
                    <td class="px-5 py-3">
                        <div class="${nameClass} break-all font-mono">${f.name}</div>
                        ${warnHtml}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="text-[9px] font-bold text-gray-500 uppercase bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">${f.extension || '?'}</span>
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-gray-500">${formatBytes(f.compressed_size)}</td>
                    <td class="px-5 py-3 text-right font-mono font-bold text-indigo-600">${formatBytes(f.uncompressed_size)}</td>
                </tr>
            `;
        });
    })
    .catch(err => {
        alert('Terjadi kesalahan jaringan saat menginspeksi ZIP.');
        closeInspectModal();
    });
}

function closeInspectModal() {
    document.getElementById('modalZipInspection').classList.add('hidden');
    document.getElementById('modalZipInspection').classList.remove('flex');
}
</script>
