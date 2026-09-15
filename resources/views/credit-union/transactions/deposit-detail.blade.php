@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Bulan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cu.dashboard') }}"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50"
           title="Kembali ke dashboard">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <p class="text-sm font-semibold text-brand-primary">Detail Bulan</p>
            <h1 class="text-2xl font-bold text-gray-900">Detail Bulan {{ $periodLabel }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">Ringkasan simpanan dan angsuran pinjaman pada periode ini.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-green-700">Total Setoran</p>
            <p class="mt-2 text-2xl font-extrabold text-green-700">Rp {{ number_format($totalSetoran, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-red-700">Total Penarikan</p>
            <p class="mt-2 text-2xl font-extrabold text-red-700">Rp {{ number_format($totalPenarikan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-brand-primary/20 bg-brand-primary/[0.03] p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-primary">Saldo Neto</p>
            <p class="mt-2 text-2xl font-extrabold text-brand-primary">Rp {{ number_format($neto, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Total Pinjaman</p>
            <p class="mt-2 text-2xl font-extrabold text-amber-700">Rp {{ number_format($totalPinjaman, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="flex gap-1 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm">
        @foreach ([['key' => 'setoran', 'label' => 'Transaksi Setoran'], ['key' => 'pinjaman', 'label' => 'Angsuran Pinjaman']] as $tabItem)
            <a href="{{ url()->current() }}?tab={{ $tabItem['key'] }}"
               class="flex-1 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition {{ $tab === $tabItem['key'] ? 'bg-brand-primary text-white shadow-md shadow-brand-primary/30' : 'text-gray-600 hover:bg-gray-50' }}">{{ $tabItem['label'] }}</a>
        @endforeach
    </div>

    @if ($tab === 'setoran')
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <p class="text-sm font-bold text-gray-800">Transaksi Setoran</p>
                <p class="mt-0.5 text-xs text-gray-400">Transaksi debit simpanan anggota pada periode ini.</p>
            </div>
            <p class="text-xs text-gray-400">{{ number_format($trxCount, 0, ',', '.') }} transaksi</p>
        </div>
        <div class="divide-y divide-gray-100 px-5">
            @forelse ($transactions as $trx)
                <div class="flex items-center justify-between gap-3 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-800">{{ $trx->member?->icunm ?? 'Tanpa Anggota' }}</p>
                        <p class="truncate text-[11px] text-gray-400"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->descr ?: 'Setoran' }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-bold text-green-600">+{{ number_format($trx->amount, 0, ',', '.') }}</p>
                        <p class="text-[10px] text-gray-400">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <i class="fas fa-inbox text-lg"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-600">Belum ada setoran pada periode ini</p>
                    <p class="text-xs text-gray-400">Tidak ditemukan transaksi debit anggota untuk {{ $periodLabel }}.</p>
                </div>
            @endforelse
        </div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $transactions->links() }}</div>
    </div>
    @else
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <p class="text-sm font-bold text-gray-800">Angsuran Pinjaman</p>
                <p class="mt-0.5 text-xs text-gray-400">Jadwal angsuran jatuh tempo pada periode ini.</p>
            </div>
            <p class="text-xs text-gray-400">{{ number_format($pinjamanRows->count(), 0, ',', '.') }} baris</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">Anggota</th>
                        <th class="px-5 py-3 font-bold">No. Pinjaman</th>
                        <th class="px-5 py-3 text-center font-bold">Cicilan</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga</th>
                        <th class="px-5 py-3 text-right font-bold">Lain</th>
                        <th class="px-5 py-3 text-right font-bold">Total</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pinjamanRows as $schedule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800">{{ $schedule->loan?->member?->icunm ?? 'Tanpa Anggota' }}</p>
                                <p class="font-mono text-[10px] text-gray-400">{{ $schedule->loan?->member?->icuno ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-primary">{{ $schedule->loan?->trnno ?? '-' }}</td>
                            <td class="px-5 py-3 text-center text-gray-600">{{ $schedule->installmentLabel() }}</td>
                            <td class="px-5 py-3 text-right text-gray-600">{{ number_format($schedule->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-600">{{ number_format($schedule->int_amt, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-600">{{ number_format($schedule->others, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-bold text-gray-800">{{ number_format($schedule->amount + $schedule->int_amt + $schedule->others, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center">
                                @if ($schedule->paidst === 1)
                                    <span class="inline-block rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700">Terbayar</span>
                                @else
                                    <span class="inline-block rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700">Belum Bayar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada angsuran pinjaman pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
