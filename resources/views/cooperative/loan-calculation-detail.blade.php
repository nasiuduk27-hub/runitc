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
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Total Pinjaman</p>
            <p class="mt-2 text-2xl font-extrabold text-gray-800">Rp {{ number_format($loanCalculation['total_pinjaman'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">Akumulasi principle pinjaman aktif</p>
        </div>
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-green-700">Total Pinjaman Terbayar/Angsuran</p>
            <p class="mt-2 text-2xl font-extrabold text-green-700">Rp {{ number_format($loanCalculation['total_dibayar'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-green-700/70">Jadwal dengan paidst = 1</p>
        </div>
        <div class="rounded-2xl border border-brand-primary/20 bg-brand-primary/[0.03] p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-primary">Total Pinjaman Berjalan</p>
            <p class="mt-2 text-2xl font-extrabold text-brand-primary">Rp {{ number_format($loanCalculation['sisa_keseluruhan'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">Total pinjaman dikurangi pembayaran</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Detail Angsuran Terbayar</p>
            <p class="mt-0.5 text-xs text-gray-400">Daftar angsuran yang sudah tercatat sebagai terbayar.</p>
        </div>

        <form method="GET" action="{{ route('cooperative.loan-calculation.detail') }}" class="grid grid-cols-1 gap-3 border-b border-gray-100 px-5 py-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto_auto] md:items-end">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Search</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Anggota / nomor pinjaman" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Periode</span>
                <select name="period" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                    <option value="">Semua Periode</option>
                    @foreach ($periodOptions as $period)
                        <option value="{{ $period }}" @selected($filters['period'] === $period)>{{ \App\Services\Cooperative\CooperativePeriod::label($period) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Tanggal Pembayaran</span>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
            </label>
            <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-primaryHover">Cari</button>
            <a href="{{ route('cooperative.loan-calculation.detail') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-xs font-semibold text-gray-600 transition hover:border-brand-primary/40 hover:text-brand-primary">Reset</a>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 font-bold">Anggota/Member</th>
                        <th class="px-5 py-3 text-right font-bold">Angsuran</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga</th>
                        <th class="px-5 py-3 text-right font-bold">Sisa Pinjaman</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($paidInstallments as $row)
                        <tr class="text-gray-700">
                            <td class="whitespace-nowrap px-5 py-3">{{ \App\Services\Cooperative\CooperativePeriod::label((string) $row->periode) }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800">{{ $row->icunm }}</p>
                                <p class="font-mono text-[10px] text-gray-400">{{ $row->icuno }} | {{ $row->trnno }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold">Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">Rp {{ number_format($row->int_amt, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-brand-primary">Rp {{ number_format($row->outstand, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada angsuran terbayar yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-700">
                    <tr>
                        <td colspan="2" class="px-5 py-3">Total</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">Rp {{ number_format($paidTotals['amount'], 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">Rp {{ number_format($paidTotals['interest'], 0, ',', '.') }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($paidInstallments->hasPages())
            <div class="border-t border-gray-100 px-5 py-4">
                {{ $paidInstallments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
