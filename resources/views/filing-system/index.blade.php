@extends('layouts.filing')

@section('title', 'RUN-ITC | Filing System')

@section('content')
@php
    $currentFolder = $folder ?? 'my_drive';
    $formatSize = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' bytes';
    };
    $pageTitle = \App\Support\FilingSystem\FilingFolders::title($currentFolder);
    $isTrash = $currentFolder === 'trash';
    $hasAdvancedFilter = ! empty($filters['file_format'])
        || ! empty($filters['file_type_code'])
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

    {{-- Searchrow & Chips Filter Cepat (satu kotak cari untuk semua viewport) --}}
    <div class="border-b border-gray-200 bg-white px-4 py-4 sm:px-6">
        <form method="GET" action="{{ route('filing-system.index') }}" class="flex items-center gap-2.5">
            <input type="hidden" name="folder" value="{{ $currentFolder }}">
            @if (! empty($filters['file_type_code']))
                <input type="hidden" name="file_type_code" value="{{ $filters['file_type_code'] }}">
            @endif
            @if (! empty($selectedFormats))
                <input type="hidden" name="file_format" value="{{ is_array($filters['file_format']) ? implode(',', $filters['file_format']) : $filters['file_format'] }}">
            @endif
            @if (! empty($filters['owner_id']))
                <input type="hidden" name="owner_id" value="{{ $filters['owner_id'] }}">
            @endif
            @if (($filters['location'] ?? '') !== '')
                <input type="hidden" name="location" value="{{ $filters['location'] }}">
            @endif
            @if (($filters['has_words'] ?? '') !== '')
                <input type="hidden" name="has_words" value="{{ $filters['has_words'] }}">
            @endif
            @if (($filters['date_modify'] ?? 'anytime') !== 'anytime')
                <input type="hidden" name="date_modify" value="{{ $filters['date_modify'] }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            @endif
            @if (($filters['share_to'] ?? '') !== '')
                <input type="hidden" name="share_to" value="{{ $filters['share_to'] }}">
            @endif

            <div class="relative flex flex-1 items-center rounded-xl border border-gray-300 bg-white transition-all focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary/10">
                <i class="fas fa-search absolute left-4 text-sm text-gray-400"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama file, kode FSY, atau kata kunci..."
                    class="w-full rounded-xl border-none bg-transparent py-2.5 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 outline-none">
            </div>

            <button type="button" onclick="toggleAdvancedFilter()" title="Filter Lanjutan"
                class="flex shrink-0 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 {{ $hasAdvancedFilter ? 'border-brand-primary text-brand-primary bg-blue-50/50' : '' }}">
                <i class="fas fa-sliders-h text-xs"></i>
                <span class="hidden sm:inline">Filter Lanjutan</span>
            </button>
        </form>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">TIPE:</span>
            @php
                $isAllType = empty($filters['file_type_code']);
            @endphp
            <a href="{{ request()->fullUrlWithQuery(['file_type_code' => null, 'page' => 1]) }}"
               class="rounded-full px-3.5 py-1.5 font-medium transition {{ $isAllType ? 'bg-brand-primary text-white shadow-sm font-semibold' : 'border border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-gray-50' }}">
                Semua
            </a>

            @foreach (($filterOptions['file_types'] ?? []) as $ft)
                @php
                    $isTypeActive = ($filters['file_type_code'] ?? '') === $ft['value'];
                @endphp
                <a href="{{ request()->fullUrlWithQuery(['file_type_code' => $ft['value'], 'page' => 1]) }}"
                   class="rounded-full px-3.5 py-1.5 font-medium transition {{ $isTypeActive ? 'bg-brand-primary text-white shadow-sm font-semibold' : 'border border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-gray-50' }}">
                    {{ $ft['label'] }}
                </a>
            @endforeach

            <span class="mx-1 h-4 w-px bg-gray-200"></span>

            <span class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">FORMAT:</span>
            @php
                $activeFormat = ! empty($selectedFormats) ? strtoupper($selectedFormats[0]) : null;
            @endphp
            <details class="group relative inline-block text-left">
                <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-full px-3.5 py-1.5 font-medium transition [&::-webkit-details-marker]:hidden {{ $activeFormat ? 'bg-brand-primary text-white shadow-sm font-semibold' : 'border border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-gray-50' }}">
                    <span>{{ $activeFormat ?: 'Semua' }}</span>
                    <i class="fas fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 {{ $activeFormat ? 'text-white' : '' }}"></i>
                </summary>
                <div class="absolute left-0 z-30 mt-1 max-h-56 w-44 overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
                    <a href="{{ request()->fullUrlWithQuery(['file_format' => null, 'page' => 1]) }}"
                       class="flex items-center gap-2 px-3 py-2 text-xs font-medium hover:bg-gray-50 {{ empty($activeFormat) ? 'bg-blue-50 text-brand-primary font-bold' : 'text-gray-700' }}">
                        <i class="fas fa-file text-gray-400 w-4"></i> Semua Format
                    </a>
                    @foreach (($filterOptions['formats'] ?? []) as $fmt)
                        @php
                            $fmtVal = strtoupper($fmt['value']);
                            $isFmtActive = $activeFormat === $fmtVal;
                        @endphp
                        <a href="{{ request()->fullUrlWithQuery(['file_format' => $fmtVal, 'page' => 1]) }}"
                           class="flex items-center gap-2 px-3 py-2 text-xs font-medium hover:bg-gray-50 {{ $isFmtActive ? 'bg-blue-50 text-brand-primary font-bold' : 'text-gray-700' }}">
                            <span class="w-4 text-center font-bold text-[10px] text-gray-500">{{ substr($fmtVal, 0, 3) }}</span>
                            <span class="uppercase">{{ $fmtVal }}</span>
                        </a>
                    @endforeach
                </div>
            </details>

            @php
                $isAnyFilterActive = ! empty($filters['search'])
                    || ! empty($filters['file_type_code'])
                    || ! empty($selectedFormats)
                    || $hasAdvancedFilter;
            @endphp
            @if ($isAnyFilterActive)
                <a href="{{ route('filing-system.index', ['folder' => $currentFolder]) }}"
                   class="ml-auto flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1.5 text-[11px] font-bold text-gray-600 transition hover:bg-gray-200" title="Reset semua filter">
                    <i class="fas fa-undo text-[10px]"></i> Reset
                </a>
            @endif
        </div>
    </div>

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
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Format</label>
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
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Tipe File</label>
                        <select name="file_type_code" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 outline-none focus:border-blue-500">
                            <option value="">Semua Tipe</option>
                            @foreach (($filterOptions['file_types'] ?? []) as $ft)
                                <option value="{{ $ft['value'] }}" @selected(($filters['file_type_code'] ?? '') === $ft['value'])>{{ $ft['label'] }}</option>
                            @endforeach
                        </select>
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
                            <x-date-input name="date_from" id="filing_date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500" />
                            <span class="text-xs text-gray-400">-</span>
                            <x-date-input name="date_to" id="filing_date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 outline-none focus:border-blue-500" />
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
                            <option value="5" @selected(($filters['share_to'] ?? '') === '5')>Grup</option>
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
                        <button type="submit" class="rounded-lg bg-brand-primary px-4 py-2 text-xs font-bold text-white hover:bg-brand-primaryHover">Terapkan Filter</button>
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
        'viewMode' => $viewMode ?? 'list',
    ])
</div>

@include('filing-system.partials.list-actions')

{!! $modalHtml !!}

<script>
function switchFilingView(mode) {
    document.cookie = 'filing_view=' + mode + '; path=/; max-age=31536000; SameSite=Lax';
    refreshFileList();
}

async function executeFilingAction(filingId, action) {
    if (action === 'permanent_delete') {
        if (!(await showConfirm('PERINGATAN: File fisik akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?'))) return;
    } else if (action === 'move_trash') {
        if (!(await showConfirm('Pindahkan file ini ke Sampah?'))) return;
    }

    const fd = new FormData();
    fd.append('filing_id', filingId);
    fd.append('action', action);
    fd.append('_token', '{{ csrf_token() }}');

    fetch('{{ url('/modules/cbt_ops/filing_system/action.php') }}', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert(result.message, { onClose: function () { window.location.reload(); } });
                return;
            }
            showAlert('Gagal: ' + result.message);
        })
        .catch(() => showAlert('Terjadi kesalahan koneksi.'));
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
