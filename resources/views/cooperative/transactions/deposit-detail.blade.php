@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Setoran')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-primary">Setoran Anggota</p>
            <h1 class="text-2xl font-bold text-gray-900">Detail Setoran {{ $periodLabel }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">Seluruh transaksi debit anggota pada periode ini.</p>
        </div>
        <a href="{{ route('cooperative.dashboard') }}" class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 shadow-sm hover:border-brand-primary/40 hover:text-brand-primary sm:self-auto">Kembali ke dashboard</a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Total Setoran</p>
            <p class="mt-2 text-2xl font-extrabold text-brand-primary">Rp {{ number_format($totalSetoran, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Jumlah Transaksi</p>
            <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ number_format($trxCount, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Transaksi Setoran</p>
            <p class="mt-0.5 text-xs text-gray-400">Diurutkan dari transaksi terbaru.</p>
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
                <p class="py-8 text-center text-sm text-gray-400">Belum ada setoran pada periode ini.</p>
            @endforelse
        </div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
