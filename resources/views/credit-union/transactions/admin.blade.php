@extends('layouts.app')

@section('title', 'RUN-ITC | Seluruh Transaksi Koperasi')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-primary">Koperasi</p>
            <h1 class="text-2xl font-bold text-gray-900">Seluruh Transaksi Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Transaksi seluruh anggota, diurutkan dari yang terbaru.</p>
        </div>
        <a href="{{ route('cooperative.dashboard') }}" class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 shadow-sm hover:border-brand-primary/40 hover:text-brand-primary sm:self-auto">Kembali ke dashboard</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Transaksi Anggota</p>
            <p class="mt-0.5 text-xs text-gray-400">Menampilkan seluruh jenis transaksi.</p>
        </div>
        <div class="divide-y divide-gray-100 px-5">
            @forelse ($transactions as $trx)
                <div class="flex items-center justify-between gap-3 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-800">{{ $trx->member?->icunm ?? 'Tanpa Anggota' }}</p>
                        <p class="truncate text-[11px] text-gray-400"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->descr }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}">{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                        <p class="text-[10px] text-gray-400">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-400">Belum ada transaksi.</p>
            @endforelse
        </div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
