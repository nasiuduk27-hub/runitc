@extends('layouts.app')

@section('title', 'RUN-ITC | Dashboard Koperasi')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Koperasi</h1>
        <p class="mt-0.5 text-sm text-gray-500">Ringkasan kondisi koperasi (read-only dari sistem lama). Periode berjalan: {{ $currentPeriodLabel }}.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
        <a href="{{ route('cooperative.savings.detail') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Simpanan</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">Rp {{ number_format($savingsSummary['neto'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Saldo neto | lihat rincian</p>
        </a>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Anggota</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($memberStats['total'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Regular {{ $memberStats['regular'] }} | Outstanding {{ $memberStats['outstanding'] }}</p>
        </div>
        <a href="{{ route('cooperative.members.index', ['status' => 6]) }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Anggota Non-Aktif</p>
            <p class="mt-1 text-lg font-extrabold text-red-500">{{ number_format($memberStats['non_active'], 0, ',', '.') }}</p>
        </a>
        <a href="{{ route('cooperative.loans.index', ['status' => 'running']) }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Pinjaman Berjalan</p>
            <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($loanStats['running'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">dari {{ number_format($loanStats['total'], 0, ',', '.') }} pinjaman (indikatif)</p>
        </a>
        <a href="{{ route('cooperative.loans.index') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Sisa Pokok Indikatif (Rp)</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">{{ number_format($loanStats['indicative_outstanding'], 0, ',', '.') }}</p>
        </a>
        <a href="{{ route('cooperative.loan-calculation.detail') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Kalkulasi Pinjaman Berjalan</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">Rp {{ number_format($loanCalculation['sisa_keseluruhan'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Sisa keseluruhan | lihat rincian</p>
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <p class="text-sm font-bold text-gray-800">Setoran Anggota per Bulan</p>
                <p class="mt-0.5 text-xs text-gray-400">Total transaksi debit anggota, 12 periode terakhir yang memiliki transaksi.</p>
            </div>
        </div>
        <div class="px-5 py-5">
            @if (count($depositSeries) > 0)
                @php
                    $grandTotal = array_sum(array_column($depositSeries, 'total'));
                @endphp
                <div class="flex h-40 items-end gap-1.5 sm:gap-2">
                    @foreach ($depositSeries as $point)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <span class="hidden text-[9px] font-bold text-gray-500 sm:block" title="{{ $point['label'] }}">{{ $point['percent'] >= 55 ? number_format($point['total'] / 1000000, 1, ',', '.') . 'jt' : '' }}</span>
                            <a href="{{ route('cooperative.deposits.detail', ['period' => $point['periode']]) }}"
                               class="block w-full rounded-t-md bg-brand-primary/80 transition hover:bg-brand-primaryHover"
                               style="height: {{ $point['percent'] }}%"
                               title="Klik untuk melihat detail {{ $point['label'] }}: Rp {{ number_format($point['total'], 0, ',', '.') }} ({{ $point['trx_count'] }} transaksi)"></a>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-1.5 sm:gap-2">
                    @foreach ($depositSeries as $point)
                        <span class="flex-1 text-center text-[9px] font-medium text-gray-400" title="{{ $point['label'] }}">{{ $point['short'] }}</span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-400">Total periode tertampil: <span class="font-bold text-gray-600">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span> dari {{ number_format(array_sum(array_column($depositSeries, 'trx_count')), 0, ',', '.') }} transaksi.</p>
            @else
                <p class="py-8 text-center text-sm text-gray-400">Belum ada data setoran.</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Cicilan Jatuh Tempo Periode {{ $currentPeriodLabel }}</p>
                <p class="mt-0.5 text-xs text-gray-400">Menurut jadwal icu_dloan; status bayar belum tercatat di sistem lama.</p>
            </div>
            <div class="px-5 py-3">
                <div class="mb-3 flex items-baseline gap-2">
                    <p class="text-xl font-extrabold text-gray-900">{{ number_format($dueSummary['count'], 0, ',', '.') }} baris jadwal</p>
                    <p class="text-xs text-gray-400">senilai Rp {{ number_format($dueSummary['total_due'], 0, ',', '.') }}</p>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($dueSummary['rows'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <a href="{{ route('cooperative.loans.detail', ['rec_id' => $row->loan_rec_id]) }}" class="block truncate font-semibold text-gray-800 hover:text-brand-primary">{{ $row->icunm }}</a>
                                <p class="font-mono text-[10px] text-gray-400">{{ $row->trnno }} | cicilan {{ $row->seqno }}/{{ $row->totseqno }}</p>
                            </div>
                            <p class="whitespace-nowrap text-right text-sm font-bold text-gray-700">Rp {{ number_format($row->amount + $row->int_amt + $row->others, 0, ',', '.') }}</p>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-400">Tidak ada jadwal pada periode ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-gray-800">Transaksi Terbaru</p>
                    <p class="mt-0.5 text-xs text-gray-400">10 transaksi terakhir seluruh anggota.</p>
                </div>
                <a href="{{ route('cooperative.transactions.index') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <ul class="divide-y divide-gray-100 px-5 text-sm">
                @forelse ($recentTransactions as $trx)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-800">{{ $trx->member?->icunm ?? 'Tanpa Anggota' }}</p>
                            <p class="truncate text-[11px] text-gray-400"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->descr }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}">{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-400">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
