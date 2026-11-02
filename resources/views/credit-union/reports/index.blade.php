@extends('layouts.app')

@section('title', 'RUN-ITC | Laporan Credit Union')

@php
    $exportUrl = fn (string $tabKey) => url()->current().'?'.http_build_query(array_filter([
        'tab' => $tabKey,
        ...($tabKey === 'savings' ? ['from' => $tab === 'savings' ? ($range['from'] ?? '') : '', 'to' => $tab === 'savings' ? ($range['to'] ?? '') : '', 'q' => $tab === 'savings' ? ($keyword ?? '') : ''] : []),
        ...($tabKey === 'due' ? ['as_of' => $tab === 'due' ? ($as_of ?? '') : '', 'q' => $tab === 'due' ? ($keyword ?? '') : ''] : []),
        ...($tabKey === 'loans' ? ['status' => $tab === 'loans' ? ($status ?? '') : '', 'q' => $tab === 'loans' ? ($keyword ?? '') : ''] : []),
        ...($tabKey === 'interest' ? ['from' => $tab === 'interest' ? ($range['from'] ?? '') : '', 'to' => $tab === 'interest' ? ($range['to'] ?? '') : ''] : []),
        ...($tabKey === 'bank' ? ['from' => $tab === 'bank' ? ($range['from'] ?? '') : '', 'to' => $tab === 'bank' ? ($range['to'] ?? '') : '', 'account' => $tab === 'bank' ? ($account ?? '') : ''] : []),
        'export' => 'csv',
    ], fn ($v) => $v !== ''));
@endphp
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laporan Credit Union</h1>
            <p class="mt-0.5 text-sm text-gray-500">Laporan dasar simpanan, jatuh tempo, dan rekap pinjaman (hanya dapat dilihat).</p>
        </div>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 print:hidden">
            <i class="fas fa-print"></i> Cetak / PDF
        </button>
    </div>

    <div class="hidden print:block">
        <h1 class="text-xl font-bold text-gray-900">Laporan Credit Union — {{ ['savings' => 'Simpanan', 'due' => 'Jatuh Tempo', 'loans' => 'Rekap Pinjaman', 'interest' => 'Pendapatan Bunga', 'bank' => 'Bank'][$tab] }}</h1>
        <p class="mt-1 text-xs text-gray-500">Dicetak {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="flex gap-1 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm print:hidden">
        @foreach ([['key' => 'savings', 'label' => 'Simpanan'], ['key' => 'due', 'label' => 'Jatuh Tempo'], ['key' => 'loans', 'label' => 'Rekap Pinjaman'], ['key' => 'interest', 'label' => 'Pendapatan Bunga'], ['key' => 'bank', 'label' => 'Bank']] as $tabItem)
            <a href="{{ url()->current() }}?tab={{ $tabItem['key'] }}"
               class="flex-1 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition {{ $tab === $tabItem['key'] ? 'bg-brand-primary text-white shadow-md shadow-brand-primary/30' : 'text-gray-600 hover:bg-gray-50' }}">{{ $tabItem['label'] }}</a>
        @endforeach
    </div>

    @if ($tab === 'savings')
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="savings">
            <div>
                <label for="from" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Awal (YYYYMM)</label>
                <input type="text" id="from" name="from" value="{{ $range['from'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div>
                <label for="to" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Akhir</label>
                <input type="text" id="to" name="to" value="{{ $range['to'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="flex-1">
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota</label>
                <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Nomor atau nama anggota..."
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">Tampilkan</button>
            <a href="{{ $exportUrl('savings') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-green-700 transition hover:bg-green-50"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-xs text-gray-400">
                Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }} | {{ number_format($totals['members'], 0, ',', '.') }} anggota | Setoran Rp {{ number_format($totals['debit'], 0, ',', '.') }} | Penarikan Rp {{ number_format($totals['credit'], 0, ',', '.') }}
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">No. Anggota</th><th class="px-5 py-3 font-bold">Nama</th>
                        <th class="px-5 py-3 text-right font-bold">Setoran / Debit (Rp)</th><th class="px-5 py-3 text-right font-bold">Penarikan / Kredit (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Neto (Rp)</th><th class="px-5 py-3 text-center font-bold">Trx D/C</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5 font-mono text-xs font-semibold text-brand-primary">{{ $row->icuno }}</td>
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $row->icunm }}</td>
                                <td class="px-5 py-2.5 text-right text-green-600">{{ number_format($row->debit_total, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right text-red-500">{{ number_format($row->credit_total, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($row->debit_total - $row->credit_total, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-center text-xs text-gray-500">{{ $row->debit_count }} / {{ $row->credit_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Tidak ada transaksi pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->count())
                        <tfoot><tr class="bg-gray-50 font-bold text-gray-800">
                            <td colspan="2" class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['debit'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['credit'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['debit'] - $totals['credit'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    @elseif ($tab === 'due')
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="due">
            <div>
                <label for="as_of" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Acuan (YYYYMM)</label>
                <input type="text" id="as_of" name="as_of" value="{{ $as_of }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="flex-1">
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
                <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Nama, nomor anggota, atau nomor pinjaman..."
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">Tampilkan</button>
            <a href="{{ $exportUrl('due') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-green-700 transition hover:bg-green-50"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-red-500">Terlewat (&lt; periode acuan)</p>
                <p class="mt-1 text-lg font-extrabold text-red-600">{{ number_format($totals['overdue_count'], 0, ',', '.') }} baris</p>
                <p class="text-xs text-red-500">Rp {{ number_format($totals['overdue_sum'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-blue-500">Jatuh Tempo Kini</p>
                <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($totals['now_count'], 0, ',', '.') }} baris</p>
                <p class="text-xs text-blue-500">Rp {{ number_format($totals['now_sum'], 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-xs text-gray-400">
                Catatan: sistem lama tidak mencatat pembayaran angsuran secara elektronik, sehingga baris lama yang belum ditandai tampil sebagai terlewat. Maksimal 2.000 baris.
            </div>
            <div class="max-h-[32rem] overflow-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3 font-bold">No Anggota</th><th class="px-4 py-3 font-bold">Nama</th><th class="px-4 py-3 font-bold">Pinjaman</th>
                        <th class="px-4 py-3 font-bold">Angsuran ke</th><th class="px-4 py-3 font-bold">Periode</th>
                        <th class="px-4 py-3 text-right font-bold">Total (Rp)</th><th class="px-4 py-3 font-bold">Klasifikasi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50 {{ $row['classification'] === 'overdue' ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-2 font-mono text-xs text-brand-primary">{{ $row['icuno'] }}</td>
                                <td class="px-4 py-2 text-gray-800">{{ $row['member_name'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $row['trnno'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['installment'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($row['periode']) }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-800">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2"><span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $row['classification'] === 'overdue' ? 'border-red-200 bg-red-50 text-red-600' : 'border-blue-200 bg-blue-50 text-blue-600' }}">{{ $reports->dueLabel($row['classification']) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada cicilan tertunggak.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($tab === 'interest')
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="interest">
            <div>
                <label for="from" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Awal (YYYYMM)</label>
                <input type="text" id="from" name="from" value="{{ $range['from'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div>
                <label for="to" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Akhir</label>
                <input type="text" id="to" name="to" value="{{ $range['to'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="flex-1"></div>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">Tampilkan</button>
            <a href="{{ $exportUrl('interest') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-green-700 transition hover:bg-green-50"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Total Pendapatan Bunga</p>
                <p class="mt-1 text-lg font-extrabold text-emerald-700">Rp {{ number_format($totals['interest'], 0, ',', '.') }}</p>
                <p class="text-xs text-emerald-600">Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }}</p>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-blue-500">Total Cicilan Terbayar</p>
                <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($totals['installments'], 0, ',', '.') }} cicilan</p>
                <p class="text-xs text-blue-500">Cicilan lunas (paidst = 1)</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-xs text-gray-400">
                Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }} | {{ number_format($totals['installments'], 0, ',', '.') }} cicilan terbayar | Total Pendapatan Bunga Rp {{ number_format($totals['interest'], 0, ',', '.') }}
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 text-right font-bold">Jumlah Cicilan Terbayar</th>
                        <th class="px-5 py-3 text-right font-bold">Pendapatan Bunga (Rp)</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5 font-medium text-gray-800">
                                    {{ \App\Services\CreditUnion\CreditUnionPeriod::label((string) $row->periode) }}
                                    <span class="ml-1 font-mono text-xs text-gray-400">({{ $row->periode }})</span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($row->installment_count, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold text-emerald-600">{{ number_format($row->interest_total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">Tidak ada data pendapatan bunga pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->count())
                        <tfoot><tr class="bg-gray-50 font-bold text-gray-800">
                            <td class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['installments'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-emerald-600">{{ number_format($totals['interest'], 0, ',', '.') }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Bagian Total Bunga per Anggota --}}
        <div class="space-y-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Total Bunga per Anggota</h2>
                    <p class="text-xs text-gray-500">
                        Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }}
                        @if ($detail['total_count'] > 0)
                            &middot; {{ number_format($detail['totals']['member_count'], 0, ',', '.') }} anggota &middot; {{ number_format($detail['displayed_count'], 0, ',', '.') }} cicilan &middot; Klik baris anggota untuk melihat rincian cicilannya.
                        @endif
                    </p>
                </div>
            </div>

            @if ($detail['is_truncated'])
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-800 shadow-sm print:hidden">
                    <div class="flex items-start gap-2.5">
                        <i class="fas fa-exclamation-triangle mt-0.5 text-amber-600"></i>
                        <div>
                            <span class="font-bold">Batas Tampilan Layar:</span>
                            Detail ditampilkan {{ number_format($detail['displayed_count'], 0, ',', '.') }} dari {{ number_format($detail['total_count'], 0, ',', '.') }} cicilan pada rentang ini. Persempit rentang periode untuk melihat seluruhnya. Ekspor Excel memuat seluruh data pada rentang ini.
                        </div>
                    </div>
                </div>
            @endif

            @if (empty($detail['groups']))
                <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-400">
                    Tidak ada data detail cicilan terbayar pada rentang ini.
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/80 text-[11px] uppercase tracking-wide text-gray-400">
                                    <th class="px-5 py-3 font-semibold">No. Anggota</th>
                                    <th class="px-5 py-3 font-semibold">Nama Anggota</th>
                                    <th class="px-5 py-3 text-center font-semibold">Jumlah Pinjaman</th>
                                    <th class="px-5 py-3 text-center font-semibold">Jumlah Cicilan Terbayar</th>
                                    <th class="px-5 py-3 text-right font-semibold">Total Bunga (Rp)</th>
                                    <th class="px-5 py-3 text-center font-semibold print:hidden">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($detail['groups'] as $index => $group)
                                    <tr onclick="openMemberDetail({{ $index }})"
                                        tabindex="0"
                                        onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); openMemberDetail({{ $index }}); }"
                                        class="group cursor-pointer transition hover:bg-gray-50/80 focus:bg-gray-50 focus:outline-none">
                                        <td class="px-5 py-2.5 font-mono text-xs font-semibold text-gray-700">
                                            <span class="inline-flex rounded-lg bg-gray-100 px-2 py-0.5 font-mono text-xs font-bold text-gray-700 group-hover:bg-gray-200">
                                                {{ $group['icuno'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-2.5 font-medium text-gray-900 {{ $group['is_unlinked'] ? 'italic text-amber-700' : '' }}">
                                            {{ $group['icunm'] }}
                                        </td>
                                        <td class="px-5 py-2.5 text-center font-mono text-xs text-gray-700">
                                            {{ number_format($group['loan_count'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-2.5 text-center font-mono text-xs text-gray-700">
                                            {{ number_format($group['installment_count'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-2.5 text-right font-mono text-xs font-semibold text-emerald-600">
                                            {{ number_format($group['interest_subtotal'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-2.5 text-center print:hidden" onclick="event.stopPropagation()">
                                            <button type="button"
                                                    onclick="openMemberDetail({{ $index }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                                                <i class="fas fa-eye text-gray-400"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 bg-gray-50/90 text-xs font-extrabold text-gray-900">
                                    <td colspan="2" class="px-5 py-3">
                                        @if ($detail['is_truncated'])
                                            Grand Total (Ditampilkan {{ number_format($detail['displayed_count'], 0, ',', '.') }} dari {{ number_format($detail['total_count'], 0, ',', '.') }} Cicilan)
                                        @else
                                            Grand Total
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center font-mono">
                                        {{ number_format($detail['totals']['loan_count'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-center font-mono">
                                        {{ number_format($detail['totals']['installments'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono text-emerald-700">
                                        {{ number_format($detail['totals']['interest'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-center text-gray-400 print:hidden">&minus;</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Modal Detail Cicilan Anggota --}}
        <div id="memberDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4 print:hidden" onclick="if(event.target===this)closeMemberDetail()">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                {{-- Kepala Modal --}}
                <div class="flex items-center justify-between border-b border-gray-100 bg-white px-5 py-4 sm:px-6">
                    <h2 class="text-base font-bold text-gray-900">Detail Cicilan Anggota</h2>
                    <button type="button" onclick="closeMemberDetail()" class="flex h-8 w-8 items-center justify-center rounded-full text-xl leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none" aria-label="Tutup modal">
                        &times;
                    </button>
                </div>

                {{-- Badan Modal --}}
                <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
                    {{-- Kepala Kelompok Anggota --}}
                    <div class="flex flex-col gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <span id="modalMemberNo" class="inline-flex rounded-lg bg-gray-200 px-2 py-0.5 font-mono text-xs font-bold text-gray-700"></span>
                            <span id="modalMemberName" class="font-bold text-gray-900"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                            <span id="modalLoanCount"></span>
                            <span>&middot;</span>
                            <span id="modalInstallmentCount"></span>
                            <span>&middot;</span>
                            <span id="modalInterestSubtotal" class="font-semibold text-emerald-700"></span>
                        </div>
                    </div>

                    {{-- Tabel Cicilan Milik Anggota --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full min-w-[600px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-white text-[11px] uppercase tracking-wide text-gray-400">
                                    <th class="px-5 py-2.5 font-semibold">Periode</th>
                                    <th class="px-5 py-2.5 font-semibold">No. Pinjaman</th>
                                    <th class="px-5 py-2.5 text-center font-semibold">Cicilan ke-</th>
                                    <th class="px-5 py-2.5 text-right font-semibold">Pokok Cicilan (Rp)</th>
                                    <th class="px-5 py-2.5 text-right font-semibold">Bunga (Rp)</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody" class="divide-y divide-gray-50">
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-100 bg-gray-50/50 text-xs font-bold text-gray-800">
                                    <td colspan="3" id="modalFootSubtotalLabel" class="px-5 py-2 text-gray-500">
                                        Subtotal
                                    </td>
                                    <td id="modalFootPrincipalSubtotal" class="px-5 py-2 text-right font-mono">
                                        0
                                    </td>
                                    <td id="modalFootInterestSubtotal" class="px-5 py-2 text-right font-mono text-emerald-700">
                                        0
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Kaki Modal --}}
                <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50 px-5 py-3 sm:px-6">
                    <button type="button" onclick="closeMemberDetail()" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 focus:outline-none">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const memberGroups = @json($detail['groups'] ?? []);

            function formatNumber(val) {
                return Number(val || 0).toLocaleString('id-ID');
            }

            window.openMemberDetail = function(index) {
                const group = memberGroups[index];
                if (!group) return;

                const modal = document.getElementById('memberDetailModal');
                if (!modal) return;

                const noEl = document.getElementById('modalMemberNo');
                const nameEl = document.getElementById('modalMemberName');
                const loansEl = document.getElementById('modalLoanCount');
                const instEl = document.getElementById('modalInstallmentCount');
                const interestSubEl = document.getElementById('modalInterestSubtotal');

                noEl.textContent = group.icuno || '-';
                nameEl.textContent = group.icunm || '-';
                if (group.is_unlinked) {
                    nameEl.className = 'font-bold italic text-amber-700';
                } else {
                    nameEl.className = 'font-bold text-gray-900';
                }

                loansEl.textContent = formatNumber(group.loan_count) + ' pinjaman';
                instEl.textContent = formatNumber(group.installment_count) + ' cicilan';
                interestSubEl.textContent = 'Subtotal Bunga: Rp ' + formatNumber(group.interest_subtotal);

                const tbody = document.getElementById('modalTableBody');
                tbody.innerHTML = '';

                if (Array.isArray(group.items)) {
                    const frag = document.createDocumentFragment();
                    group.items.forEach(function(item) {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50/50';

                        const tdPeriode = document.createElement('td');
                        tdPeriode.className = 'px-5 py-2 font-mono text-xs text-gray-700';
                        tdPeriode.textContent = item.periode || '-';
                        tr.appendChild(tdPeriode);

                        const tdTrnno = document.createElement('td');
                        tdTrnno.className = 'px-5 py-2 font-mono text-xs font-medium text-gray-800';
                        tdTrnno.textContent = item.trnno || '-';
                        tr.appendChild(tdTrnno);

                        const tdSeq = document.createElement('td');
                        tdSeq.className = 'px-5 py-2 text-center font-mono text-xs text-gray-600';
                        tdSeq.textContent = item.installment_label || '-';
                        tr.appendChild(tdSeq);

                        const tdPrincipal = document.createElement('td');
                        tdPrincipal.className = 'px-5 py-2 text-right font-mono text-xs text-gray-700';
                        tdPrincipal.textContent = formatNumber(item.principal);
                        tr.appendChild(tdPrincipal);

                        const tdInterest = document.createElement('td');
                        tdInterest.className = 'px-5 py-2 text-right font-mono text-xs font-semibold text-emerald-600';
                        tdInterest.textContent = formatNumber(item.interest);
                        tr.appendChild(tdInterest);

                        frag.appendChild(tr);
                    });
                    tbody.appendChild(frag);
                }

                const footLabel = document.getElementById('modalFootSubtotalLabel');
                const footPrincipal = document.getElementById('modalFootPrincipalSubtotal');
                const footInterest = document.getElementById('modalFootInterestSubtotal');

                footLabel.textContent = 'Subtotal ' + (group.icunm || '');
                footPrincipal.textContent = formatNumber(group.principal_subtotal);
                footInterest.textContent = formatNumber(group.interest_subtotal);

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            window.closeMemberDetail = function() {
                const modal = document.getElementById('memberDetailModal');
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.closeMemberDetail();
                }
            });
        })();
        </script>
    @elseif ($tab === 'bank')
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="bank">
            <div>
                <label for="from" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Awal (YYYYMM)</label>
                <input type="text" id="from" name="from" value="{{ $range['from'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div>
                <label for="to" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode Akhir</label>
                <input type="text" id="to" name="to" value="{{ $range['to'] }}" maxlength="6" pattern="[0-9]{6}"
                       class="w-32 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="flex-1">
                <label for="account" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Rekening Bank</label>
                <select id="account" name="account"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">Semua Rekening</option>
                    @foreach ($accountOptions as $opt)
                        <option value="{{ $opt->bank_account_no }}" {{ $account === $opt->bank_account_no ? 'selected' : '' }}>
                            {{ $opt->bank_account_bank }} &middot; {{ $opt->bank_account_no }} (a.n. {{ $opt->bank_account_name }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">Tampilkan</button>
            <a href="{{ $exportUrl('bank') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-green-700 transition hover:bg-green-50"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-rose-600">Total Biaya Admin Bank</p>
                <p class="mt-1 text-lg font-extrabold text-rose-700">Rp {{ number_format($totals['admin_fee'], 0, ',', '.') }}</p>
                <p class="text-xs text-rose-600">Pengeluaran beban administrasi bank</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Total Bunga Bank Neto</p>
                <p class="mt-1 text-lg font-extrabold text-emerald-700">Rp {{ number_format($totals['net_interest'], 0, ',', '.') }}</p>
                <p class="text-xs text-emerald-600">Bruto Rp {{ number_format($totals['gross_interest'], 0, ',', '.') }} &middot; Pajak Rp {{ number_format($totals['tax_interest'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-blue-600">Selisih Bank (Neto &minus; Admin)</p>
                <p class="mt-1 text-lg font-extrabold {{ $totals['net_difference'] >= 0 ? 'text-blue-700' : 'text-amber-700' }}">Rp {{ number_format($totals['net_difference'], 0, ',', '.') }}</p>
                <p class="text-xs text-blue-600">Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-xs text-gray-400">
                Periode {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['from']) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($range['to']) }}
                @if ($account) | Rekening: {{ $account }} @else | Semua Rekening @endif
                | Total Admin Rp {{ number_format($totals['admin_fee'], 0, ',', '.') }} | Total Bunga Neto Rp {{ number_format($totals['net_interest'], 0, ',', '.') }} | Selisih Rp {{ number_format($totals['net_difference'], 0, ',', '.') }}
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 text-right font-bold">Biaya Admin Bank (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga Bruto (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Pajak Bunga (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga Neto (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Selisih Bank (Rp)</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5 font-medium text-gray-800">
                                    {{ \App\Services\CreditUnion\CreditUnionPeriod::label((string) $row->periode) }}
                                    <span class="ml-1 font-mono text-xs text-gray-400">({{ $row->periode }})</span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-semibold text-rose-600">{{ number_format($row->admin_fee, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($row->gross_interest, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right text-gray-500">{{ number_format($row->tax_interest, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold text-emerald-600">{{ number_format($row->net_interest, 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right font-bold {{ $row->net_difference >= 0 ? 'text-blue-700' : 'text-amber-700' }}">
                                    {{ number_format($row->net_difference, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Tidak ada mutasi biaya admin atau bunga bank pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->count())
                        <tfoot><tr class="bg-gray-50 font-bold text-gray-800">
                            <td class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right text-rose-600">{{ number_format($totals['admin_fee'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['gross_interest'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['tax_interest'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-emerald-600">{{ number_format($totals['net_interest'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right {{ $totals['net_difference'] >= 0 ? 'text-blue-700' : 'text-amber-700' }}">{{ number_format($totals['net_difference'], 0, ',', '.') }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="loans">
            <div class="flex-1">
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
                <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Nomor pinjaman, anggota, keterangan..."
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="w-full lg:w-48">
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status Indikatif</label>
                <select id="status" name="status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">Semua</option>
                    <option value="running" {{ $status === 'running' ? 'selected' : '' }}>Berjalan</option>
                    <option value="settled" {{ $status === 'settled' ? 'selected' : '' }}>Lunas</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">Tampilkan</button>
            <a href="{{ $exportUrl('loans') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-green-700 transition hover:bg-green-50"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-xs text-gray-400">
                {{ number_format($totals['count'], 0, ',', '.') }} pinjaman | Pokok Rp {{ number_format($totals['principle'], 0, ',', '.') }} | Terbayar Rp {{ number_format($totals['paid'], 0, ',', '.') }} | Sisa Pokok Indikatif Rp {{ number_format($totals['outstanding'], 0, ',', '.') }} (maks 500 baris)
            </div>
            <div class="max-h-[32rem] overflow-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3 font-bold">Pinjaman</th><th class="px-4 py-3 font-bold">Anggota</th>
                        <th class="px-4 py-3 text-right font-bold">Pokok (Rp)</th><th class="px-4 py-3 text-right font-bold">Tagihan (Rp)</th>
                        <th class="px-4 py-3 text-right font-bold">Terbayar (Rp)</th><th class="px-4 py-3 text-right font-bold">Sisa Pokok (Rp)</th>
                        <th class="px-4 py-3 text-center font-bold">Cicilan L/U</th><th class="px-4 py-3 font-bold">Status LN</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs font-semibold text-brand-primary">{{ $row['trnno'] }}</td>
                                <td class="px-4 py-2"><span class="text-gray-800">{{ $row['member_name'] }}</span> <span class="font-mono text-[10px] text-gray-400">{{ $row['member_icuno'] }}</span></td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['principle'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['totalloan'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['paid'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ number_format($row['indicative_outstanding'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-center text-xs text-gray-500">{{ $row['paid_count'] }} / {{ $row['unpaid_count'] }}</td>
                                <td class="px-4 py-2 text-xs {{ $row['ln_status'] === 'Loan Completed' ? 'font-bold text-green-600' : 'text-gray-600' }}">{{ $row['ln_status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada pinjaman cocok filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
    @media print {
        body { display: block !important; height: auto !important; overflow: visible !important; }
        body > header, aside#sidebar, #sidebarOverlay, #global-loader { display: none !important; }
        main#main-content-area { overflow: visible !important; padding: 0 !important; }
        .max-h-\[32rem\], .max-h-56 { max-height: none !important; overflow: visible !important; }
    }
</style>
@endsection
