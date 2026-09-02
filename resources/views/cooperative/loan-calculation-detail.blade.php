@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Kalkulasi Pinjaman')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-primary">Ringkasan Koperasi</p>
            <h1 class="text-2xl font-bold text-gray-900">Detail Kalkulasi Pinjaman Berjalan</h1>
            <p class="mt-0.5 text-sm text-gray-500">Rincian pinjaman yang belum lunas berdasarkan jadwal angsuran.</p>
        </div>
        <a href="{{ route('cooperative.dashboard') }}" class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 shadow-sm transition hover:border-brand-primary/40 hover:text-brand-primary sm:self-auto">
            Kembali ke dashboard
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Total Pinjaman Berjalan</p>
            <p class="mt-2 text-2xl font-extrabold text-gray-800">Rp {{ number_format($loanCalculation['total_pinjaman'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">Akumulasi principle pinjaman aktif</p>
        </div>
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-green-700">Sudah Dibayar</p>
            <p class="mt-2 text-2xl font-extrabold text-green-700">Rp {{ number_format($loanCalculation['total_dibayar'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-green-700/70">Jadwal dengan paidst = 1</p>
        </div>
        <div class="rounded-2xl border border-brand-primary/20 bg-brand-primary/[0.03] p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-primary">Sisa Keseluruhan</p>
            <p class="mt-2 text-2xl font-extrabold text-brand-primary">Rp {{ number_format($loanCalculation['sisa_keseluruhan'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">Total pinjaman dikurangi pembayaran</p>
        </div>
    </div>
</div>
@endsection
