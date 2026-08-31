@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Pinjaman')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.loans.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="font-mono text-2xl font-bold text-gray-900">{{ $loan->trnno }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                @if ($loan->member)
                    <a href="{{ route('cooperative.members.detail', ['rec_id' => $loan->member->rec_id]) }}" class="font-semibold text-brand-primary hover:underline">{{ $loan->member->icunm }} ({{ $loan->member->icuno }})</a>
                    <span>|</span>
                @endif
                <span>{{ $loan->descr }}</span>
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClass() }}">{{ $loan->statusLabel() }}</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Informasi Pinjaman</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tanggal Pinjam</dt><dd class="font-medium text-gray-800">{{ $loan->trndt ? \Carbon\Carbon::parse($loan->trndt)->format('d M Y') : '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Periode</dt><dd class="font-mono font-medium text-gray-800">{{ $loan->startper }} - {{ $loan->endper }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pokok (Rp)</dt><dd class="font-bold text-gray-900">{{ number_format($loan->principle, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Bunga Tahunan</dt><dd class="font-medium text-gray-800">{{ $loan->interest }}%</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Bunga Awal (Rp)</dt><dd class="font-medium text-gray-800">{{ number_format($loan->interamt, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Tagihan (Rp)</dt><dd class="font-bold text-gray-900">{{ number_format($loan->totalloan, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Angsuran Pokok/Bulan (Rp)</dt><dd class="font-medium text-gray-800">{{ number_format($loan->avgmon, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Bunga/Bulan (Rp)</dt><dd class="font-medium text-gray-800">{{ number_format($loan->avgint, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tagihan/Bulan (Rp)</dt><dd class="font-medium text-gray-800">{{ number_format($loan->monthly, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tenor</dt><dd class="font-medium text-gray-800">{{ $loan->term }} bulan</dd></div>
            </dl>
            @if (! empty($loan->remarks))
                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500"><span class="font-bold">Catatan:</span> {{ $loan->remarks }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Progress Pembayaran</p>
            <div class="mb-1 flex items-baseline justify-between">
                <p class="text-2xl font-extrabold text-brand-primary">{{ number_format($progressPercent, 1, ',', '.') }}%</p>
                <p class="text-xs text-gray-400">indikatif</p>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-brand-primary" style="width: {{ min(100, $progressPercent) }}%"></div>
            </div>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Terbayar (Rp)</dt><dd class="font-bold text-green-600">{{ number_format($loan->paid, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Sisa Pokok Indikatif (Rp)</dt><dd class="font-bold text-gray-900">{{ number_format(max(0, $loan->principle - $loan->paid), 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jumlah Cicilan</dt><dd class="font-medium text-gray-800">{{ number_format($schedules->count(), 0, ',', '.') }} baris jadwal</dd></div>
            </dl>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Jadwal Angsuran (icu_dloan)</p>
            <p class="mt-0.5 text-xs text-gray-400">Outstand = sisa pokok sesuai data existing. Status pembayaran bersifat indikatif (paidst & payno).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">#</th>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 font-bold">Keterangan</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Lain-lain (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Total (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Outstanding (Rp)</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($schedules as $schedule)
                        <tr class="{{ $schedule->isRoundingRow() ? 'bg-amber-50/40' : '' }} hover:bg-gray-50">
                            <td class="px-5 py-2.5 text-gray-500">{{ $schedule->installmentLabel() }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-gray-600">{{ $schedule->periode }}</td>
                            <td class="px-5 py-2.5 text-gray-700">
                                {{ $schedule->descr ?: '-' }}
                                @if ($schedule->isRoundingRow())
                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Rounding</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($schedule->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($schedule->int_amt, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($schedule->others, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($schedule->totalDue(), 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($schedule->outstand, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5">
                                <span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $schedule->paymentStatusBadgeClass() }}">{{ $schedule->paymentStatusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada jadwal angsuran.</td></tr>
                    @endforelse
                </tbody>
                @if ($schedules->count() > 0)
                    <tfoot>
                        <tr class="bg-gray-50 font-bold text-gray-800">
                            <td colspan="3" class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right">{{ number_format($scheduleTotals['principal'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($scheduleTotals['interest'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($scheduleTotals['others'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($scheduleTotals['principal'] + $scheduleTotals['interest'] + $scheduleTotals['others'], 0, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
