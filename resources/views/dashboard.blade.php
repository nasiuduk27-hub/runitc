@extends('layouts.app')

@section('title', 'RUN-ITC | Dashboard')
@section('page_title', 'Dashboard Overview')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-gray-900">Dashboard Overview</h1>
        <p class="mt-0.5 text-sm text-gray-500">Welcome back, {{ session('user_name', 'User') }}!</p>
    </div>
</div>

@if (($profileCompletionPercentage ?? 100) < 100)
    <div class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-extrabold text-gray-900">Lengkapi data diri Anda</h2>
                        <span class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-700">Profile Completion {{ number_format($profileCompletionPercentage) }}%</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-600">Data profil belum lengkap. Lengkapi sekarang agar akun Anda siap digunakan.</p>
                    <div class="mt-3 h-1.5 w-full max-w-xl overflow-hidden rounded-full bg-amber-100">
                        <div class="h-1.5 rounded-full bg-amber-500" style="width: {{ max(0, min(100, $profileCompletionPercentage)) }}%"></div>
                    </div>
                </div>
            </div>
            <a href="{{ route('profile.index') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-amber-600">
                Lengkapi Sekarang <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>
@endif

<div id="widgetContainer" class="grid grid-cols-1 gap-6 pb-10 md:grid-cols-2 lg:grid-cols-4 lg:items-start">
    <div class="widget-card relative col-span-1 flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm group" data-widget-id="kpi-active-rooms">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50">
            <i class="fas fa-building text-lg text-blue-600"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Active Rooms Today</p>
            <p class="mt-0.5 text-2xl font-bold text-gray-900">{{ number_format($activeRooms ?? 0) }}</p>
        </div>
    </div>

    <div class="widget-card relative col-span-1 flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm group" data-widget-id="kpi-participants">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
            <i class="fas fa-user-graduate text-lg text-emerald-600"></i>
        </div>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Participants Today</p>
            <p class="mt-0.5 text-2xl font-bold text-gray-900">{{ number_format($totalParticipants ?? 0) }}</p>
        </div>
    </div>

    <div class="widget-card relative col-span-1 flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm group" data-widget-id="kpi-crc-pending">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50">
            <i class="fas fa-cloud-upload-alt text-lg text-amber-600"></i>
        </div>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">CRC Pending</p>
            <p class="mt-0.5 text-2xl font-bold text-gray-900">{{ number_format($crcPending ?? 0) }}</p>
        </div>
    </div>

    <div class="widget-card relative col-span-1 flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm group" data-widget-id="kpi-system-modules">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-50">
            <i class="fas fa-cube text-lg text-purple-600"></i>
        </div>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">System Modules</p>
            <p class="mt-0.5 text-2xl font-bold text-gray-900">Active</p>
        </div>
    </div>

    @if (! empty($tadWidgetMode))
    <div class="widget-card relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm group md:col-span-2 lg:col-span-2" data-widget-id="tad-upcoming">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">TAD Dashboard</p>
                <h2 class="text-base font-extrabold text-gray-900">{{ $tadWidgetMode === 'assigned' ? 'Tugas Saya' : 'Nomor Admin Mendatang' }}</h2>
            </div>
            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ number_format($tadWidgetTotal) }} total</span>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($tadWidgetRows as $row)
                <div class="px-5 py-3 transition hover:bg-gray-50">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-extrabold text-gray-900">{{ $row['admin_no'] ?? '-' }}</p>
                                @if ($tadWidgetMode === 'assigned')
                                    <span class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Batch {{ $row['batch_no'] ?? '-' }}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-xs text-gray-500">{{ $row['client_nm'] ?? '-' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-bold text-gray-700">{{ ! empty($row['testdt']) ? \Carbon\Carbon::parse($row['testdt'])->format('d M Y') : '-' }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Upcoming</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-[11px] text-gray-500">
                        @if ($tadWidgetMode === 'assigned')
                            <i class="fas fa-user-check text-emerald-500"></i>
                            <span>{{ number_format((int) ($row['assigned_takers'] ?? $row['authorize_amt'] ?? 0)) }} peserta ditugaskan</span>
                        @else
                            <i class="fas fa-users-cog text-indigo-500"></i>
                            <span class="truncate">{{ ! empty($row['spv_names']) ? $row['spv_names'] : 'Belum ada SPV' }}</span>
                            <span class="text-gray-300">/</span>
                            <span>{{ number_format((int) ($row['total_takers'] ?? 0)) }} peserta</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-400"><i class="fas fa-calendar-check"></i></div>
                    <p class="text-sm font-semibold text-gray-600">Belum ada jadwal mendatang.</p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-gray-100 bg-gray-50 px-5 py-3">
            <a href="{{ url('/modules/cbt_ops/test_admin/index.php') }}" class="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                Lihat semua <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>
    @endif

    @if ($isSuperAdmin)
    <div class="widget-card relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm group md:col-span-2 lg:col-span-2" data-widget-id="system-info">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="grid h-full grid-cols-1 md:grid-cols-2">
            <div class="border-b border-gray-100 p-6 md:border-b-0 md:border-r">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Migration Status</p>
                        <h2 class="mt-1 text-base font-extrabold text-gray-900">Laravel Shell Aktif</h2>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Auth OK</span>
                </div>
                <p class="mt-4 text-sm leading-6 text-gray-600">Dashboard ini sudah berjalan di Laravel dan mengambil data KPI dari database legacy. Modul lama tetap bisa diakses melalui menu sambil dimigrasikan bertahap.</p>
            </div>

            <div class="p-6">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Session</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Account</span>
                        <span class="truncate font-bold text-gray-900">{{ session('account_nm', session('user_name', 'Unknown')) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Auth DB</span>
                        <span class="font-bold text-gray-900">{{ session('auth_db', '-') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if ($canViewTadRecap)
    <div class="widget-card relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm group md:col-span-2 lg:col-span-4" data-widget-id="tad-participant-recap">
        <div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>
            <button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>
        </div>
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Rekap TAD</p>
                <h2 class="mt-1 text-base font-extrabold text-gray-900">Rekap Peserta per Nomor Admin</h2>
                <p class="mt-0.5 text-xs text-gray-500">Detail nomor admin dengan subtotal per tanggal.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-bold text-gray-700">{{ number_format((int) ($tadRecapTotals['total_dates'] ?? 0)) }} tanggal</span>
                <span class="rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ number_format((int) ($tadRecapTotals['total_admins'] ?? 0)) }} nomor admin</span>
                <span class="rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ number_format((int) ($tadRecapTotals['total_participants'] ?? 0)) }} peserta</span>
            </div>
        </div>

        <form method="GET" class="border-b border-gray-100 bg-gray-50 px-5 py-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-6 md:items-end">
                <div id="recapSingleDateField" @class(['md:col-span-1', 'hidden' => ! empty($tadRecapFilters['is_range'])])>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <span class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Tanggal</span>
                        <label class="inline-flex cursor-pointer items-center gap-1 whitespace-nowrap text-[10px] font-bold text-gray-500">
                            <input type="checkbox" name="recap_is_range" value="1" id="recapRangeToggle" class="h-3 w-3 rounded border-gray-300 text-indigo-600" @checked(! empty($tadRecapFilters['is_range']))> Range
                        </label>
                    </div>
                    <input type="date" name="recap_date" value="{{ $tadRecapFilters['date'] ?? '' }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div id="recapRangeStartField" @class(['md:col-span-2', 'hidden' => empty($tadRecapFilters['is_range'])])>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <span class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Dari Tanggal</span>
                        <label class="inline-flex cursor-pointer items-center gap-1 whitespace-nowrap text-[10px] font-bold text-gray-500">
                            <input type="checkbox" value="1" id="recapRangeToggle2" class="h-3 w-3 rounded border-gray-300 text-indigo-600" @checked(! empty($tadRecapFilters['is_range']))> Range
                        </label>
                    </div>
                    <input type="date" name="recap_start_date" value="{{ $tadRecapFilters['start_date'] ?? '' }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div id="recapRangeEndField" @class(['md:col-span-2', 'hidden' => empty($tadRecapFilters['is_range'])])>
                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Sampai Tanggal</span>
                    <input type="date" name="recap_end_date" value="{{ $tadRecapFilters['end_date'] ?? '' }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Kecualikan Data</span>
                    <div class="relative">
                        <button type="button" id="recapExcludeTrigger" class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-500 transition hover:bg-gray-50">
                            <span id="recapExcludeSummary">{{ count($tadRecapExcludeIds) }} data dipilih</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                        </button>
                        <div id="recapExcludeFields" class="absolute left-0 right-0 top-full z-30 mt-2 hidden space-y-2 rounded-2xl border border-gray-200 bg-white p-3 shadow-xl">
                            <div class="overflow-hidden rounded-xl border border-gray-300">
                                <div class="flex flex-col gap-2 border-b border-gray-100 p-2 sm:flex-row sm:items-center sm:justify-between">
                                    <input type="text" id="recapExcludeSearch" placeholder="Cari sekolah/kode..." class="w-full flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span id="recapExcludeCount" class="whitespace-nowrap text-[10px] font-bold text-gray-500">0 dipilih</span>
                                        <button type="button" id="recapExcludeSelectAll" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-[10px] font-extrabold text-indigo-700 transition hover:bg-indigo-100">Pilih Semua</button>
                                    </div>
                                </div>
                                <div id="recapExcludeList" class="max-h-56 divide-y divide-gray-100 overflow-y-auto">
                                    @if (empty($tadRecapExcludeOptions))
                                        <div class="px-3 py-4 text-center text-xs text-gray-400">Tidak ada data untuk tanggal ini.</div>
                                    @else
                                        @foreach ($tadRecapExcludeOptions as $option)
                                            <label class="recap-exclude-item flex cursor-pointer items-start gap-2 px-3 py-2 hover:bg-gray-50" data-search="{{ strtolower($option['label']) }}">
                                                <input type="checkbox" name="recap_exclude_admin_ids[]" value="{{ (int) $option['rec_id'] }}" class="recap-exclude-checkbox mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-indigo-600" @checked(in_array((int) $option['rec_id'], $tadRecapExcludeIds, true))>
                                                <span class="text-xs leading-snug text-gray-700">{{ $option['label'] }}</span>
                                            </label>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <input type="text" name="recap_exclude" value="{{ $tadRecapFilters['exclude'] ?? '' }}" placeholder="Keyword tambahan: tester, dummy, trial" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 md:col-span-1">
                    <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-extrabold text-white transition hover:bg-indigo-700">Filter</button>
                    <a href="{{ route('dashboard') }}" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-extrabold text-gray-600 transition hover:bg-gray-100">Reset</a>
                </div>
            </div>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[10px] text-gray-400">Centang Range untuk rentang tanggal. Centang Kecualikan Data untuk memilih sekolah/kode atau keyword yang tidak ikut total/cetak.</p>
                <button type="submit" form="recapPrintForm" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-xs font-extrabold text-white transition hover:bg-gray-800"><i class="fas fa-print text-[10px]"></i> Cetak Rekap</button>
            </div>
        </form>

        <form id="recapPrintForm" method="GET" action="{{ route('cbt-ops.test-admin.participant-recap-print') }}" target="_blank">

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-[11px] uppercase text-gray-500">
                    <tr>
                        <th class="w-40 px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Daftar Sekolah / Kode</th>
                        <th class="w-36 px-5 py-3 text-right">Jumlah Peserta</th>
                        <th class="w-36 px-5 py-3 text-right">Peserta Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tadRecapRows as $row)
                        @foreach (($row['items'] ?? []) as $index => $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3 align-top">
                                    @if ($index === 0)
                                        <a href="{{ url('/modules/cbt_ops/test_admin/index.php?date='.urlencode($row['date'])) }}" class="font-extrabold text-indigo-600 hover:text-indigo-800">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</a>
                                    @else
                                        <span class="text-gray-300">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs leading-relaxed text-gray-600">{{ $item['item_text'] ?? '-' }}</td>
                                <td class="px-5 py-3 text-right font-extrabold text-gray-900">{{ number_format((int) ($item['total_participants'] ?? 0)) }}</td>
                                <td class="px-5 py-3 text-right font-extrabold text-emerald-700">{{ number_format((int) ($item['finished_participants'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-emerald-100 bg-emerald-50/80">
                            <td class="px-5 py-2 text-xs font-black uppercase text-emerald-800">Total {{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-5 py-2 text-xs font-bold text-emerald-800">{{ number_format((int) ($row['admin_count'] ?? 0)) }} nomor admin</td>
                            <td class="px-5 py-2 text-right text-xs font-black text-emerald-900">{{ number_format((int) ($row['total_participants'] ?? 0)) }}</td>
                            <td class="px-5 py-2 text-right text-xs font-black text-emerald-900">{{ number_format((int) ($row['finished_participants'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-sm font-semibold text-gray-500">Belum ada jadwal peserta mendatang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-xs text-gray-500">Menampilkan maksimal 10 tanggal terdekat. Cetak rekap untuk seluruh data mendatang.</span>
            <span class="text-xs font-bold text-emerald-700">Selesai: {{ number_format((int) ($tadRecapTotals['finished_participants'] ?? 0)) }} / {{ number_format((int) ($tadRecapTotals['total_participants'] ?? 0)) }} peserta</span>
        </div>
    </div>
    @endif
</div>

@if ($canViewTadRecap)
<script>
(function () {
    var singleField = document.getElementById('recapSingleDateField');
    var startField = document.getElementById('recapRangeStartField');
    var endField = document.getElementById('recapRangeEndField');
    var toggle = document.getElementById('recapRangeToggle');
    var toggle2 = document.getElementById('recapRangeToggle2');

    function applyRangeState(isRange) {
        if (singleField) singleField.classList.toggle('hidden', !!isRange);
        if (startField) startField.classList.toggle('hidden', !isRange);
        if (endField) endField.classList.toggle('hidden', !isRange);
        if (toggle) toggle.checked = !!isRange;
        if (toggle2) toggle2.checked = !!isRange;
    }

    if (toggle) toggle.addEventListener('change', function () { applyRangeState(this.checked); });
    if (toggle2) toggle2.addEventListener('change', function () { applyRangeState(this.checked); });

    var trigger = document.getElementById('recapExcludeTrigger');
    var fields = document.getElementById('recapExcludeFields');
    var search = document.getElementById('recapExcludeSearch');
    var selectAll = document.getElementById('recapExcludeSelectAll');

    function recapExcludeUpdate() {
        var checkboxes = document.querySelectorAll('.recap-exclude-checkbox');
        var checked = document.querySelectorAll('.recap-exclude-checkbox:checked');
        var summary = document.getElementById('recapExcludeSummary');
        var count = document.getElementById('recapExcludeCount');
        if (summary) summary.textContent = checked.length + ' data dipilih';
        if (count) count.textContent = checked.length + ' dipilih';
        if (selectAll) {
            var visible = Array.prototype.filter.call(checkboxes, function (cb) {
                var item = cb.closest('.recap-exclude-item');
                return item && item.style.display !== 'none';
            });
            selectAll.textContent = visible.length > 0 && visible.every(function (cb) { return cb.checked; }) ? 'Batal Semua' : 'Pilih Semua';
        }
    }

    if (trigger && fields) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            fields.classList.toggle('hidden');
            if (search) { search.value = ''; }
            var items = document.querySelectorAll('.recap-exclude-item');
            items.forEach(function (item) { item.style.display = ''; });
            recapExcludeUpdate();
        });
        document.addEventListener('click', function (e) {
            if (!fields.contains(e.target) && !trigger.contains(e.target)) fields.classList.add('hidden');
        });
    }

    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.recap-exclude-item').forEach(function (item) {
                item.style.display = item.getAttribute('data-search').indexOf(q) !== -1 ? '' : 'none';
            });
            recapExcludeUpdate();
        });
    }

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            var visible = Array.prototype.filter.call(document.querySelectorAll('.recap-exclude-item'), function (item) {
                return item.style.display !== 'none';
            });
            var allChecked = visible.every(function (item) {
                var cb = item.querySelector('.recap-exclude-checkbox');
                return cb && cb.checked;
            });
            visible.forEach(function (item) {
                var cb = item.querySelector('.recap-exclude-checkbox');
                if (cb) cb.checked = !allChecked;
            });
            recapExcludeUpdate();
        });
    }

    document.querySelectorAll('.recap-exclude-checkbox').forEach(function (cb) {
        cb.addEventListener('change', recapExcludeUpdate);
    });

    var printBtn = document.querySelector('button[form="recapPrintForm"]');
    var printForm = document.getElementById('recapPrintForm');

    if (printBtn && printForm) {
        printBtn.addEventListener('click', function () {
            ['recap_date', 'recap_is_range', 'recap_start_date', 'recap_end_date', 'recap_exclude'].forEach(function (name) {
                var src = document.querySelector('[name="' + name + '"]');
                if (!src) return;
                var hidden = printForm.querySelector('[name="' + name + '"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    printForm.appendChild(hidden);
                }
                hidden.value = src.value || '';
            });
            var existing = printForm.querySelectorAll('[name="recap_exclude_admin_ids[]"]');
            existing.forEach(function (el) { el.remove(); });
            document.querySelectorAll('.recap-exclude-checkbox:checked').forEach(function (cb) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'recap_exclude_admin_ids[]';
                hidden.value = cb.value;
                printForm.appendChild(hidden);
            });
        });
    }

    recapExcludeUpdate();
})();
</script>
@endif
@endsection
