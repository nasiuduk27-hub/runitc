@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Total Simpanan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-primary">Ringkasan Koperasi</p>
            <h1 class="text-2xl font-bold text-gray-900">Detail Total Simpanan</h1>
            <p class="mt-0.5 text-sm text-gray-500">Rincian seluruh transaksi simpanan anggota.</p>
        </div>
        <a href="{{ route('cooperative.dashboard') }}" class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 shadow-sm transition hover:border-brand-primary/40 hover:text-brand-primary sm:self-auto">
            Kembali ke dashboard
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-green-700">Total Setoran</p>
            <p class="mt-2 text-2xl font-extrabold text-green-700">Rp {{ number_format($savingsSummary['setoran'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-green-700/70">Seluruh transaksi debit</p>
        </div>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-red-700">Total Penarikan</p>
            <p class="mt-2 text-2xl font-extrabold text-red-700">Rp {{ number_format($savingsSummary['penarikan'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-red-700/70">Seluruh transaksi kredit</p>
        </div>
        <div class="rounded-2xl border border-brand-primary/20 bg-brand-primary/[0.03] p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-primary">Saldo Neto</p>
            <p class="mt-2 text-2xl font-extrabold text-brand-primary">Rp {{ number_format($savingsSummary['neto'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">Setoran dikurangi penarikan</p>
        </div>
    </div>
</div>
@endsection
