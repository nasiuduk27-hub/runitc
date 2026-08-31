@extends('layouts.filing')

@section('title', 'RUN-ITC | Filing System')

@section('filing-sidebar')
@php
    $currentFolder = $folder ?? 'my_drive';
    $folderDefs = [
        'my_drive' => ['label' => 'My Drive', 'icon' => 'fa-folder-open', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
        'shared' => ['label' => 'Share With Me', 'icon' => 'fa-share-alt', 'color' => 'text-green-600', 'bg' => 'bg-green-50'],
        'department' => ['label' => 'Divisi / Departemen', 'icon' => 'fa-building', 'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
        'company' => ['label' => 'Company / Staff', 'icon' => 'fa-users', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50'],
    ];
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
@endphp

    <div class="mb-3 px-1">
        <button type="button" onclick="openUploadModal()" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
            <i class="fas fa-plus"></i> NEW
        </button>
    </div>

    <div class="space-y-0.5">
        @foreach ($folderDefs as $key => $def)
            <a href="{{ route('filing-system.index', $queryFor(['folder' => $key, 'page' => 1])) }}"
               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $currentFolder === $key ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas {{ $def['icon'] }} w-5 text-center {{ $def['color'] }}"></i>
                <span class="flex-1 truncate">{{ $def['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-2 border-t border-gray-100 pt-2">
        <a href="{{ route('filing-system.index', $queryFor(['folder' => 'trash', 'page' => 1])) }}"
           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $currentFolder === 'trash' ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-trash w-5 text-center {{ $currentFolder === 'trash' ? 'text-red-500' : 'text-gray-400' }}"></i>
            <span class="flex-1 truncate">Sampah</span>
        </a>
    </div>

    <div class="absolute inset-x-0 bottom-0 border-t border-gray-100 px-4 py-3">
        <div class="flex items-center gap-2">
            <img src="{{ $layoutPhotoUrl ?? asset('assets/personal/nopicture.png') }}" alt="Profile" class="h-8 w-8 rounded-full object-cover">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold text-gray-700">{{ $layoutUserName ?? 'User' }}</p>
                <p class="truncate text-[10px] text-gray-400">{{ $layoutRoleDivision ?? 'Role belum diatur' }}</p>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    $formatSize = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' bytes';
    };
    $folderTitles = [
        'my_drive' => 'My Drive',
        'shared' => 'Share With Me',
        'department' => 'Divisi / Departemen',
        'company' => 'Company / Staff',
        'trash' => 'Sampah',
    ];
    $pageTitle = $folderTitles[$currentFolder] ?? 'My Drive';
    $isTrash = $currentFolder === 'trash';
    $hasAdvancedFilter = ! empty($filters['file_format'])
        || ! empty($filters['owner_id'])
        || ($filters['has_words'] ?? '') !== ''
        || ($filters['location'] ?? '') !== ''
        || ($filters['date_modify'] ?? 'anytime') !== 'anytime'
        || ($filters['share_to'] ?? '') !== '';
    $selectedFormats = (array) ($filters['file_format'] ?? []);
    $formatIcons = [
        'PDF' => ['fa-file-pdf', 'text-red-500'],
        'DOC' => ['fa-file-word', 'text-blue-500'],
        'DOCX' => ['fa-file-word', 'text-blue-500'],
        'XLS' => ['fa-file-excel', 'text-green-600'],
        'XLSX' => ['fa-file-excel', 'text-green-600'],
        'CSV' => ['fa-file-csv', 'text-green-600'],
        'PPT' => ['fa-file-powerpoint', 'text-orange-500'],
        'PPTX' => ['fa-file-powerpoint', 'text-orange-500'],
        'ZIP' => ['fa-file-archive', 'text-amber-500'],
        'RAR' => ['fa-file-archive', 'text-amber-500'],
        '7Z' => ['fa-file-archive', 'text-amber-500'],
        'JPG' => ['fa-file-image', 'text-purple-500'],
        'JPEG' => ['fa-file-image', 'text-purple-500'],
        'PNG' => ['fa-file-image', 'text-purple-500'],
        'GIF' => ['fa-file-image', 'text-purple-500'],
        'WEBP' => ['fa-file-image', 'text-purple-500'],
        'IMG' => ['fa-file-image', 'text-purple-500'],
        'FSY' => ['fa-file', 'text-gray-500'],
    ];
@endphp

<div class="flex h-full flex-col">
    @if (session('success_msg'))
        <div class="m-4 mb-0 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="m-4 mb-0 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    @if ($isTrash)
        <div class="border-b border-red-100 bg-red-50 px-4 py-2 text-xs font-semibold text-red-700 sm:px-6">
            <i class="fas fa-clock mr-1"></i>
            File di sampah akan dihapus permanen otomatis setelah {{ $trashRetentionDays }} hari.
        </div>
    @endif

    <form method="GET" action="{{ route('filing-system.index') }}" class="border-b border-gray-200 px-4 py-2.5 md:hidden">
        <input type="hidden" name="folder" value="{{ $currentFolder }}">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari..." class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
        </div>
    </form>

    <div id="advancedFilterPanel" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 p-4">        <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-2">
                    <i class="fas fa-sliders-h text-blue-500"></i>
                    <h3 class="text-sm font-bold text-gray-800">Advance Search / Filter</h3>
                </div>
                <button type="button" onclick="toggleAdvancedFilter()" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="GET" action="{{ route('filing-system.index') }}" class="p-5">
                <input type="hidden" name="folder" value="{{ $currentFolder }}">
                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Type</label>
                        <input type="hidden" name="file_format" id="advTypeInput" value="{{ $selectedFormats ? $selectedFormats[0] : '' }}">
                        <div class="relative">
                            <button type="button" id="advTypeToggle" onclick="toggleAdvTypeDropdown()" class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                                <span id="advTypeIcon" class="flex w-4 items-center justify-center">
                                    @php
                                        $selFmt = $selectedFormats[0] ?? '';
                                        $selIcon = $formatIcons[$selFmt] ?? ['fa-file', 'text-gray-400'];
                                    @endphp
                                    <i class="fas {{ $selIcon[0] }} {{ $selIcon[1] }}"></i>
                                </span>
                                <span id="advTypeLabel" class="flex-1 text-left uppercase">{{ $selFmt !== '' ? $selFmt : 'Any' }}</span>
                                <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                            </button>
                            <div id="advTypeMenu" class="absolute left-0 top-full z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                <button type="button" data-type="" class="adv-type-option flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-file w-4 text-gray-400"></i> Any
                                </button>
                                @foreach (($filterOptions['formats'] ?? []) as $fmt)
                                    @php
                                        $icon = $formatIcons[$fmt['value']] ?? ['fa-file', 'text-gray-400'];
                                    @endphp
                                    <button type="button" data-type="{{ $fmt['value'] }}" class="adv-type-option flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50">
                                        <i class="fas {{ $icon[0] }} {{ $icon[1] }} w-4"></i>
                                        <span class="uppercase">{{ $fmt['value'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Owner</label>
                        <select name="owner_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <option value="">Semua Owner</option>
                            @foreach (($filterOptions['owners'] ?? []) as $owner)
                                <option value="{{ $owner['value'] }}" @selected((int) ($filters['owner_id'] ?? 0) === (int) $owner['value'])>{{ $owner['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Has the Words</label>
                        <input type="text" name="has_words" value="{{ $filters['has_words'] ?? '' }}" placeholder="Kata kunci..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Location</label>
                        <select name="location" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <option value="">Semua Lokasi</option>
                            @foreach (($filterOptions['locations'] ?? []) as $loc)
                                <option value="{{ $loc['value'] }}" @selected(($filters['location'] ?? '') === $loc['value'])>{{ $loc['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Date Modified</label>
                        <select name="date_modify" id="advDateModify" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <option value="anytime" @selected(($filters['date_modify'] ?? 'anytime') === 'anytime')>Anytime</option>
                            <option value="today" @selected(($filters['date_modify'] ?? '') === 'today')>Today</option>
                            <option value="yesterday" @selected(($filters['date_modify'] ?? '') === 'yesterday')>Yesterday</option>
                            <option value="last_7" @selected(($filters['date_modify'] ?? '') === 'last_7')>Last 7 Days</option>
                            <option value="last_30" @selected(($filters['date_modify'] ?? '') === 'last_30')>Last 30 Days</option>
                            <option value="last_90" @selected(($filters['date_modify'] ?? '') === 'last_90')>Last 90 Days</option>
                            <option value="custom" @selected(($filters['date_modify'] ?? '') === 'custom')>Custom Range</option>
                        </select>
                        <div id="advCustomRange" class="{{ ($filters['date_modify'] ?? '') === 'custom' ? '' : 'hidden' }} mt-2 flex items-center gap-2">
                            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <span class="text-xs text-gray-400">-</span>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Share To</label>
                        <select name="share_to" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <option value="">Semua</option>
                            <option value="0" @selected(($filters['share_to'] ?? '') === '0')>Private (Owner)</option>
                            <option value="1" @selected(($filters['share_to'] ?? '') === '1')>Personal</option>
                            <option value="2" @selected(($filters['share_to'] ?? '') === '2')>Department</option>
                            <option value="3" @selected(($filters['share_to'] ?? '') === '3')>Company</option>
                            <option value="4" @selected(($filters['share_to'] ?? '') === '4')>All User</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between gap-2">
                    @if ($hasAdvancedFilter)
                        <a href="{{ route('filing-system.index', ['folder' => $currentFolder]) }}" class="rounded-lg bg-gray-100 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-200">Reset</a>
                    @else
                        <span></span>
                    @endif
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="toggleAdvancedFilter()" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Terapkan Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('filing-system._list', [
        'items' => $items,
        'pagination' => $pagination,
        'filters' => $filters,
        'folder' => $currentFolder,
    ])
</div>

<div id="bulkActionBar" class="fixed inset-x-0 bottom-0 z-40 translate-y-full border-t bg-white p-4 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] transition-transform duration-300">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div id="bulkCount" class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-lg font-black text-blue-600">0</div>
            <span class="font-bold text-gray-700">File Terpilih</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="clearSelection()" class="rounded-xl px-4 py-2 text-sm font-bold text-gray-500 hover:bg-gray-100">Batal</button>
            @if ($currentFolder === 'my_drive' && ! $isTrash)
                <button type="button" onclick="executeFilingBulk('archive')" class="inline-flex items-center gap-2 rounded-xl bg-gray-200 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-300"><i class="fas fa-archive"></i> Arsipkan</button>
                <button type="button" onclick="executeFilingBulk('move_trash')" class="inline-flex items-center gap-2 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-200"><i class="fas fa-trash"></i> Sampah</button>
            @endif
            @if ($isTrash)
                <button type="button" onclick="executeFilingBulk('restore')" class="inline-flex items-center gap-2 rounded-xl bg-green-100 px-4 py-2 text-sm font-bold text-green-600 hover:bg-green-200"><i class="fas fa-trash-restore"></i> Restore</button>
                <button type="button" onclick="executeFilingBulk('permanent_delete')" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"><i class="fas fa-times-circle"></i> Hapus Permanen</button>
            @endif
        </div>
    </div>
</div>

{!! $modalHtml !!}

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

function executeFilingBulk(action) {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) return;
    if (checked.length > 50) {
        alert('Maksimal 50 file untuk aksi massal.');
        return;
    }

    let message = 'Apakah Anda yakin ingin memproses ' + checked.length + ' file ini?';
    if (action === 'permanent_delete') {
        message = 'PERINGATAN: ' + checked.length + ' file akan dihapus permanen dari storage dan tidak bisa dikembalikan. Lanjutkan?';
    }
    if (!confirm(message)) return;

    const fd = new FormData();
    fd.append('action', 'bulk');
    fd.append('bulk_action', action);
    checked.forEach((checkbox) => fd.append('filing_ids[]', checkbox.value));

    fetch('{{ url('/modules/cbt_ops/filing_system/action.php') }}', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(`${result.message}\nBerhasil diproses: ${result.processed}\nDilewati: ${result.skipped}\nGagal: ${result.failed}`);
                window.location.reload();
                return;
            }
            alert('Gagal: ' + result.message);
        })
        .catch(() => alert('Terjadi kesalahan koneksi.'));
}

function executeFilingAction(filingId, action) {
    if (action === 'permanent_delete') {
        if (!confirm('PERINGATAN: File fisik akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?')) return;
    } else if (action === 'move_trash') {
        if (!confirm('Pindahkan file ini ke Sampah?')) return;
    }

    const fd = new FormData();
    fd.append('filing_id', filingId);
    fd.append('action', action);

    fetch('{{ url('/modules/cbt_ops/filing_system/action.php') }}', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                window.location.reload();
                return;
            }
            alert('Gagal: ' + result.message);
        })
        .catch(() => alert('Terjadi kesalahan koneksi.'));
}

function toggleAdvancedFilter() {
    const panel = document.getElementById('advancedFilterPanel');
    if (!panel) return;
    const isOpen = panel.classList.contains('flex');
    panel.classList.toggle('flex', !isOpen);
    panel.classList.toggle('hidden', isOpen);
}

document.getElementById('advDateModify')?.addEventListener('change', function () {
    const customRange = document.getElementById('advCustomRange');
    if (customRange) customRange.classList.toggle('hidden', this.value !== 'custom');
});

function toggleAdvTypeDropdown() {
    const menu = document.getElementById('advTypeMenu');
    if (menu) menu.classList.toggle('hidden');
}

document.addEventListener('click', function (event) {
    const menu = document.getElementById('advTypeMenu');
    const toggle = document.getElementById('advTypeToggle');
    if (menu && toggle && !toggle.contains(event.target) && !menu.contains(event.target)) {
        menu.classList.add('hidden');
    }
    if (event.target.closest('.adv-type-option')) {
        const opt = event.target.closest('.adv-type-option');
        const type = opt.getAttribute('data-type');
        const label = opt.querySelector('span');
        const icon = opt.querySelector('i');
        document.getElementById('advTypeInput').value = type;
        document.getElementById('advTypeLabel').textContent = label ? label.textContent : (type === '' ? 'Any' : type);
        document.getElementById('advTypeIcon').innerHTML = icon ? icon.outerHTML : '<i class="fas fa-file text-gray-400"></i>';
        menu.classList.add('hidden');
    }
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('details.dropdown-container')) {
        document.querySelectorAll('details.dropdown-container[open]').forEach((element) => element.removeAttribute('open'));
    }
});
</script>
@endsection
