@extends('layouts.app')

@section('title', 'RUN-ITC | Laporan Koperasi')

@php
    $exportUrl = fn (string $tabKey) => url()->current().'?'.http_build_query(array_filter([
        'tab' => $tabKey,
        ...($tabKey === 'savings' ? ['from' => $tab === 'savings' ? ($range['from'] ?? '') : '', 'to' => $tab === 'savings' ? ($range['to'] ?? '') : '', 'q' => $tab === 'savings' ? ($keyword ?? '') : ''] : []),
        ...($tabKey === 'due' ? ['as_of' => $tab === 'due' ? ($as_of ?? '') : '', 'q' => $tab === 'due' ? ($keyword ?? '') : ''] : []),
        ...($tabKey === 'loans' ? ['status' => $tab === 'loans' ? ($status ?? '') : '', 'q' => $tab === 'loans' ? ($keyword ?? '') : ''] : []),
        'export' => 'csv',
    ], fn ($v) => $v !== ''));
@endphp
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laporan Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Laporan dasar simpanan, jatuh tempo, dan rekap pinjaman (read-only).</p>
        </div>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 print:hidden">
            <i class="fas fa-print"></i> Cetak / PDF
        </button>
    </div>

    <div class="hidden print:block">
        <h1 class="text-xl font-bold text-gray-900">Laporan Koperasi — {{ ['savings' => 'Simpanan', 'due' => 'Jatuh Tempo', 'loans' => 'Rekap Pinjaman'][$tab] }}</h1>
        <p class="mt-1 text-xs text-gray-500">Dicetak {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="flex gap-1 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm print:hidden">
        @foreach ([['key' => 'savings', 'label' => 'Simpanan'], ['key' => 'due', 'label' => 'Jatuh Tempo'], ['key' => 'loans', 'label' => 'Rekap Pinjaman']] as $tabItem)
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
                Periode {{ \App\Services\Cooperative\CooperativePeriod::label($range['from']) }} s.d. {{ \App\Services\Cooperative\CooperativePeriod::label($range['to']) }} | {{ number_format($totals['members'], 0, ',', '.') }} anggota | Setoran Rp {{ number_format($totals['debit'], 0, ',', '.') }} | Penarikan Rp {{ number_format($totals['credit'], 0, ',', '.') }}
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
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ \App\Services\Cooperative\CooperativePeriod::label($row['periode']) }}</td>
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
