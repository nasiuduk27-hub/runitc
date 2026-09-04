@extends('layouts.app')

@section('title', 'RUN-ITC | Transaksi Bank')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transaksi Bank</h1>
            <p class="mt-0.5 text-sm text-gray-500">Catatan transaksi transfer antar bank (icu_bank_trx) yang direferensikan ke icu_mtrx2hrd.</p>
        </div>
        @if ($isCoopAdmin)
            <a href="{{ route('cooperative.bank-transactions.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                <i class="fas fa-plus"></i> Tambah Transaksi Bank
            </a>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">Tanggal</th>
                        <th class="px-5 py-3 font-bold">No. Transaksi</th>
                        <th class="px-5 py-3 font-bold">No. Referensi</th>
                        <th class="px-5 py-3 font-bold">Jenis</th>
                        <th class="px-5 py-3 text-right font-bold">Amount (Rp)</th>
                        <th class="px-5 py-3 font-bold">Deskripsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $trx)
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-600">{{ $trx->trndt }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs font-bold text-gray-800">{{ $trx->trnno }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-600">{{ $trx->req_frm_trxno }}</td>
                            <td class="px-5 py-3">
                                @if ($trx->dbocr === 'D')
                                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700">Debit</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700">Kredit</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($trx->amount, 0, ',', '.') }}</td>
                            <td class="max-w-xs truncate px-5 py-3 text-xs text-gray-500">{{ $trx->descr }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada transaksi bank.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection