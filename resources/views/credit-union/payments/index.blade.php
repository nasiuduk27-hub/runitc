@extends('layouts.app')

@section('title', 'RUN-ITC | Bayar Angsuran')

@section('content')
@php
    use App\Services\Cooperative\CooperativePeriod;
    $periodLabel = CooperativePeriod::label($period);
    $monthInput = substr($period, 0, 4).'-'.substr($period, 4, 2);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Bayar Angsuran & Simpanan</h1>
        <p class="mt-0.5 text-sm text-gray-500">Entry bulanan: pilih anggota lalu centang angsuran jatuh tempo & simpanan bulanan, posting otomatis (autodebit gaji tanggal 28).</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('cooperative.payments.index') }}"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="w-full sm:w-48">
            <label for="period" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode</label>
            <input type="month" id="period" name="period" value="{{ $monthInput }}"
                   onchange="this.form.submit()"
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="flex-1">
            <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari Anggota</label>
            <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Nama atau nomor anggota..."
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-search"></i> Filter
        </button>
        @if ($keyword !== '' || $period !== CooperativePeriod::current())
            <a href="{{ route('cooperative.payments.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Anggota</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($totals['members'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Cicilan Jatuh Tempo</p>
            <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($totals['installments'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Simpanan (Rp)</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">{{ number_format($totals['savings_inflow'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Pinjaman (Rp)</p>
            <p class="mt-1 text-lg font-extrabold text-green-600">{{ number_format($totals['loan_inflow'], 0, ',', '.') }}</p>
        </div>
    </div>

    <form id="bulkPostAll" method="POST" action="{{ route('cooperative.payments.store') }}"
          class="flex flex-col gap-3 rounded-2xl border border-blue-200 bg-blue-50/60 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <div class="flex items-center gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-blue-900">
                <input type="checkbox" id="selectAllItems" class="h-4 w-4 accent-brand-primary">
                Pilih Semua
            </label>
            <span id="selectedCount" class="text-xs text-blue-700">0 angsuran · 0 simpanan dipilih</span>
        </div>
        <button type="submit" id="postAllButton" disabled
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover disabled:cursor-not-allowed disabled:opacity-50">
            <i class="fas fa-check-double"></i> Posting Semua yang Dicentang
        </button>
    </form>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[300px_1fr]">
        {{-- Panel kiri: daftar anggota (klik untuk pilih) --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <p class="text-sm font-bold text-gray-800">Daftar Anggota</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ count($grouped) }} anggota · klik untuk lihat detail</p>
            </div>
            <div class="max-h-[32rem] overflow-y-auto">
                <ul id="memberList" class="divide-y divide-gray-100">
                    @forelse ($grouped as $memberRow)
                        @php
                            $dueCount = count($memberRow['installments']);
                            $hasDue = $dueCount > 0;
                            $hasSavings = $memberRow['savings'] !== null;
                        @endphp
                        <li>
                            <button type="button" data-member-uid="{{ $memberRow['rec_id'] }}" data-member-row="{{ $loop->index }}"
                                    class="member-row flex w-full items-center justify-between gap-2 px-4 py-3 text-left transition hover:bg-gray-50 focus:outline-none">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-gray-800">{{ $memberRow['icunm'] }}</span>
                                    <span class="block font-mono text-xs text-gray-400">{{ $memberRow['icuno'] }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    @if ($hasDue)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ $dueCount }} angsuran</span>
                                    @endif
                                    @if ($hasSavings && $memberRow['savings']['posted'])
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold text-green-700">Simpanan ✓</span>
                                    @elseif ($hasSavings)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">Simpanan</span>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada anggota pada periode ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Panel kanan: detail anggota terpilih (form posting per anggota) --}}
        <div>
            @forelse ($grouped as $memberRow)
                <form method="POST" action="{{ route('cooperative.payments.store') }}"
                      data-member-form="{{ $memberRow['rec_id'] }}"
                      class="member-detail rounded-2xl border border-gray-200 bg-white shadow-sm {{ $loop->first ? '' : 'hidden' }}">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">

                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ $memberRow['icunm'] }}</p>
                            <p class="font-mono text-xs text-gray-400">{{ $memberRow['icuno'] }} — {{ $periodLabel }}</p>
                        </div>
                        <p class="text-[11px] text-gray-400">Posting memakai autodebit potong gaji tanggal <b>28 {{ $periodLabel }}</b>.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-3 font-bold">Centang & Pinjaman yang Diesuaikan</th>
                                    <th class="px-5 py-3 font-bold">Detail</th>
                                    <th class="px-5 py-3 text-right font-bold">Jumlah (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($memberRow['installments'] as $inst)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <label class="inline-flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                                <input type="checkbox" name="installments[]" value="{{ $inst['dloan_rec_id'] }}" class="h-4 w-4 accent-brand-primary">
                                                <span class="text-sm font-medium text-gray-700">Centang angsuran ini</span>
                                            </label>
                                        </td>
                                        <td class="px-5 py-3">
                                            <p class="font-mono text-xs font-semibold text-gray-700">{{ $inst['trnno'] }}</p>
                                            <p class="text-[11px] text-gray-400">Angsuran {{ $inst['seqno'] }} dari {{ $inst['totseqno'] }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($inst['remaining'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr class="text-gray-400">
                                        <td class="px-5 py-3 text-sm">Tidak ada angsuran jatuh tempo.</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                @endforelse

                                <tr>
                                    <td class="px-5 py-3">
                                        @if ($memberRow['savings'])
                                            @if ($memberRow['savings']['posted'])
                                                <span class="inline-block whitespace-nowrap rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700">Simpanan sudah diposting</span>
                                            @else
                                                <label class="inline-flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                                    <input type="checkbox" name="savings[]" value="{{ $memberRow['rec_id'] }}" class="h-4 w-4 accent-brand-primary">
                                                    <span class="text-sm font-medium text-gray-700">Centang simpanan bulanan</span>
                                                </label>
                                            @endif
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($memberRow['savings'])
                                            <p class="text-sm font-medium text-gray-700">Simpanan Bulanan</p>
                                        @else
                                            <p class="text-sm text-gray-300">Tidak ada simpanan wajib</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-amber-700">
                                        {{ $memberRow['savings'] ? number_format($memberRow['savings']['amount'], 0, ',', '.') : '-' }}
                                    </td>
                                </tr>

                                <tr class="bg-gray-50/60">
                                    <td class="px-5 py-3" colspan="2">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-bold text-gray-700">Total Simpanan (bulan ini)</span>
                                            <span class="font-bold text-brand-primary">Rp {{ number_format($memberRow['totals']['savings'], 0, ',', '.') }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50/60">
                                    <td class="px-5 py-3" colspan="2">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-bold text-gray-700">Total Pinjaman (bulan ini)</span>
                                            <span class="font-bold text-green-600">Rp {{ number_format($memberRow['totals']['loan_principal'], 0, ',', '.') }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-6 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                            <i class="fas fa-check-double"></i> Posting untuk {{ $memberRow['icunm'] }}
                        </button>
                    </div>
                </form>
            @empty
                <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-400">Tidak ada anggota pada periode ini.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const rows = Array.from(document.querySelectorAll('.member-row'));
    const forms = Array.from(document.querySelectorAll('.member-detail'));
    const bulkForm = document.getElementById('bulkPostAll');
    const selectAll = document.getElementById('selectAllItems');
    const selectedCount = document.getElementById('selectedCount');
    const postAllButton = document.getElementById('postAllButton');
    const itemCheckboxes = function () {
        return Array.from(document.querySelectorAll('.member-detail input[type="checkbox"][name="installments[]"], .member-detail input[type="checkbox"][name="savings[]"]'));
    };

    function updateBulkState() {
        const checkboxes = itemCheckboxes();
        const checked = checkboxes.filter(function (checkbox) { return checkbox.checked; });
        const installmentCount = checked.filter(function (checkbox) { return checkbox.name === 'installments[]'; }).length;
        const savingsCount = checked.filter(function (checkbox) { return checkbox.name === 'savings[]'; }).length;
        const allChecked = checkboxes.length > 0 && checked.length === checkboxes.length;

        selectAll.checked = allChecked;
        selectAll.indeterminate = checked.length > 0 && !allChecked;
        selectedCount.textContent = installmentCount + ' angsuran · ' + savingsCount + ' simpanan dipilih';
        postAllButton.disabled = checked.length === 0;
    }

    selectAll.addEventListener('change', function () {
        itemCheckboxes().forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateBulkState();
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('.member-detail input[type="checkbox"][name="installments[]"], .member-detail input[type="checkbox"][name="savings[]"]')) {
            updateBulkState();
        }
    });

    bulkForm.addEventListener('submit', function (event) {
        const checked = itemCheckboxes().filter(function (checkbox) { return checkbox.checked; });
        if (checked.length === 0) {
            event.preventDefault();
            return;
        }

        const installmentCount = checked.filter(function (checkbox) { return checkbox.name === 'installments[]'; }).length;
        const savingsCount = checked.filter(function (checkbox) { return checkbox.name === 'savings[]'; }).length;
        if (!confirm('Posting ' + installmentCount + ' angsuran dan ' + savingsCount + ' simpanan yang dicentang?')) {
            event.preventDefault();
            return;
        }

        checked.forEach(function (checkbox) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = checkbox.name;
            hidden.value = checkbox.value;
            bulkForm.appendChild(hidden);
        });

        postAllButton.disabled = true;
        postAllButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memposting...';
    });

    function select(index, uid) {
        rows.forEach(function (r) {
            const active = r.dataset.memberUid === String(uid);
            if (active) {
                r.classList.add('bg-brand-primary/10');
                r.classList.remove('hover:bg-gray-50');
            } else {
                r.classList.remove('bg-brand-primary/10');
                r.classList.add('hover:bg-gray-50');
            }
        });
        forms.forEach(function (f) {
            const active = f.dataset.memberForm === String(uid);
            if (active) {
                f.classList.remove('hidden');
            } else {
                f.classList.add('hidden');
            }
        });
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            select(Number(row.dataset.memberRow), row.dataset.memberUid);
        });
    });

    if (rows.length > 0) {
        select(0, rows[0].dataset.memberUid);
    }

    updateBulkState();
})();
</script>
@endpush
