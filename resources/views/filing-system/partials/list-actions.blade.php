{{-- File: resources/views/filing-system/partials/list-actions.blade.php --}}
@php
    $listFolder = $folder ?? ($filters['folder'] ?? \App\Support\FilingSystem\FilingFolders::defaultKey());
    $listIsTrash = $listFolder === \App\Support\FilingSystem\FilingFolders::TRASH;
    $listIsGroup = $listFolder === 'group';
    $listCanBulkGroup = $listIsGroup && ($canContribute ?? false);
@endphp

<div id="bulkActionBar" class="fixed inset-x-0 bottom-0 z-40 translate-y-full border-t bg-white p-4 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] transition-transform duration-300">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div id="bulkCount" class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-lg font-black text-blue-600">0</div>
            <span class="font-bold text-gray-700">File Terpilih</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="clearSelection()" class="rounded-xl px-4 py-2 text-sm font-bold text-gray-500 hover:bg-gray-100">Batal</button>
            @if ($listFolder === \App\Support\FilingSystem\FilingFolders::MY_DRIVE && ! $listIsTrash)
                <button type="button" onclick="executeFilingBulk('archive')" class="inline-flex items-center gap-2 rounded-xl bg-gray-200 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-300"><i class="fas fa-archive"></i> Arsipkan</button>
                <button type="button" onclick="executeFilingBulk('move_trash')" class="inline-flex items-center gap-2 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-200"><i class="fas fa-trash"></i> Sampah</button>
            @endif
            @if ($listFolder === \App\Support\FilingSystem\FilingFolders::ARCHIVE)
                <button type="button" onclick="executeFilingBulk('restore')" class="inline-flex items-center gap-2 rounded-xl bg-green-100 px-4 py-2 text-sm font-bold text-green-600 hover:bg-green-200"><i class="fas fa-box-open"></i> Pulihkan</button>
                <button type="button" onclick="executeFilingBulk('move_trash')" class="inline-flex items-center gap-2 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-200"><i class="fas fa-trash"></i> Pindah ke Sampah</button>
            @endif
            @if ($listIsTrash)
                <button type="button" onclick="executeFilingBulk('restore')" class="inline-flex items-center gap-2 rounded-xl bg-green-100 px-4 py-2 text-sm font-bold text-green-600 hover:bg-green-200"><i class="fas fa-trash-restore"></i> Restore</button>
                <button type="button" onclick="executeFilingBulk('permanent_delete')" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"><i class="fas fa-times-circle"></i> Hapus Permanen</button>
            @endif
            @if ($listCanBulkGroup)
                <button type="button" onclick="executeFilingBulk('move_trash')" class="inline-flex items-center gap-2 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-200"><i class="fas fa-trash"></i> Sampah</button>
            @endif
        </div>
    </div>
</div>

<script>
function toggleSelectAll(element) {
    document.querySelectorAll('.row-checkbox').forEach((checkbox) => {
        checkbox.checked = element.checked;
    });
    updateBulkUI();
}

function clearSelection() {
    const selectAll = document.getElementById('selectAllCheckbox');
    if (selectAll) selectAll.checked = false;
    document.querySelectorAll('.row-checkbox').forEach((checkbox) => {
        checkbox.checked = false;
    });
    updateBulkUI();
}

function updateBulkUI() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const bar = document.getElementById('bulkActionBar');
    const countLabel = document.getElementById('bulkCount');

    if (countLabel) countLabel.innerText = checked.length;
    if (!bar) return;

    if (checked.length > 0) {
        bar.classList.remove('translate-y-full');
    } else {
        bar.classList.add('translate-y-full');
    }
}

let bulkSubmitInFlight = false;

function setBulkBarDisabled(disabled) {
    document.querySelectorAll('#bulkActionBar button').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
}

async function executeFilingBulk(action) {
    if (bulkSubmitInFlight) return;

    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) return;
    if (checked.length > 50) {
        showAlert('Maksimal 50 file untuk aksi massal.');
        return;
    }

    let message = 'Apakah Anda yakin ingin memproses ' + checked.length + ' file ini?';
    if (action === 'permanent_delete') {
        message = 'PERINGATAN: ' + checked.length + ' file akan dihapus permanen dari storage dan tidak bisa dikembalikan. Lanjutkan?';
    }

    bulkSubmitInFlight = true;
    try {
        if (!(await showConfirm(message))) return;

        setBulkBarDisabled(true);

        const fd = new FormData();
        fd.append('action', 'bulk');
        fd.append('bulk_action', action);
        checked.forEach((checkbox) => fd.append('filing_ids[]', checkbox.value));
        fd.append('_token', '{{ csrf_token() }}');

        const response = await fetch('{{ url('/modules/cbt_ops/filing_system/action.php') }}', { method: 'POST', body: fd });
        const result = await response.json();

        if (result.success) {
            let detail = '';
            (result.items || []).forEach((item) => {
                if (item.status === 'success') return;
                const label = item.status === 'failed' ? 'gagal' : 'dilewati';
                detail += `\n#${item.filing_id} (${label}): ${item.message || '-'}`;
            });
            if (detail !== '') {
                detail = '\n\nRincian item:' + detail;
            }
            showAlert(`${result.message}\nBerhasil diproses: ${result.processed}\nDilewati: ${result.skipped}\nGagal: ${result.failed}${detail}`, { onClose: function () { window.location.reload(); } });
            return;
        }
        showAlert('Gagal: ' + result.message);
    } catch {
        showAlert('Terjadi kesalahan koneksi.');
    } finally {
        bulkSubmitInFlight = false;
        setBulkBarDisabled(false);
    }
}

function toggleAdvancedFilter() {
    const panel = document.getElementById('advancedFilterPanel');
    if (!panel) return;
    const isOpen = panel.classList.contains('flex');
    if (!isOpen) {
        const modalSearchInput = panel.querySelector('input[name="search"]');
        if (modalSearchInput) {
            modalSearchInput.value = new URLSearchParams(window.location.search).get('search') || '';
        }
    }
    panel.classList.toggle('flex', !isOpen);
    panel.classList.toggle('hidden', isOpen);
}
</script>
