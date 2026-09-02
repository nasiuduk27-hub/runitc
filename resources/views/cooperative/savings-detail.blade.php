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

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Detail Saldo Simpanan</p>
            <p class="mt-0.5 text-xs text-gray-400">Rincian saldo masuk dan keluar per periode dan member.</p>
        </div>

        <form method="GET" action="{{ route('cooperative.savings.detail') }}" class="grid grid-cols-1 gap-3 border-b border-gray-100 px-5 py-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto_auto] md:items-end">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Search</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nomor atau nama member" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
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
                <span class="mb-1 block text-xs font-semibold text-gray-600">Tanggal</span>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
            </label>
            <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-primaryHover">Cari</button>
            <a href="{{ route('cooperative.savings.detail') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-xs font-semibold text-gray-600 transition hover:border-brand-primary/40 hover:text-brand-primary">Reset</a>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 font-bold">Member</th>
                        <th class="px-5 py-3 text-right font-bold">Saldo Masuk</th>
                        <th class="px-5 py-3 text-right font-bold">Saldo Keluar</th>
                        <th class="px-5 py-3 text-right font-bold">Total Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($savingsRows as $row)
                        @php($totalSaldo = (int) $row->saldo_masuk - (int) $row->saldo_keluar)
                        <tr class="text-gray-700">
                            <td class="whitespace-nowrap px-5 py-3">{{ \App\Services\Cooperative\CooperativePeriod::label((string) $row->pprd) }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800">{{ $row->icunm }}</p>
                                <p class="font-mono text-[10px] text-gray-400">{{ $row->icuno }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-green-600">Rp {{ number_format($row->saldo_masuk, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-red-500">Rp {{ number_format($row->saldo_keluar, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-bold text-brand-primary">Rp {{ number_format($totalSaldo, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada data simpanan yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-700">
                    <tr>
                        <td colspan="2" class="px-5 py-3">Total</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right text-green-600">Rp {{ number_format($savingsTotals['masuk'], 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right text-red-500">Rp {{ number_format($savingsTotals['keluar'], 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right text-brand-primary">Rp {{ number_format($savingsTotals['saldo'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($savingsRows->hasPages())
            <div class="border-t border-gray-100 px-5 py-4">
                {{ $savingsRows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
