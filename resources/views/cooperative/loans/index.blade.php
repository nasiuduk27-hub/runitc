@extends('layouts.app')

@section('title', 'RUN-ITC | Pinjaman Koperasi')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Pinjaman Koperasi</h1>
        <p class="mt-0.5 text-sm text-gray-500">Daftar pinjaman anggota (read-only dari sistem lama).</p>
        @if (! $isAdmin)
            <p class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700">
                <i class="fas fa-lock"></i> Hanya menampilkan pinjaman milik Anda.
            </p>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Pinjaman</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($stats['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Berjalan (indikatif)</p>
            <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($stats['running'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Lunas (indikatif)</p>
            <p class="mt-1 text-lg font-extrabold text-green-600">{{ number_format($stats['settled'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Sisa Pokok Indikatif (Rp)</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">{{ number_format($stats['indicative_outstanding'], 0, ',', '.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('cooperative.loans.index') }}"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:flex-row lg:items-end">
        <div class="flex-1">
            <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="No. transaksi, keterangan, atau nama anggota..."
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="w-full lg:w-52">
            <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status (indikatif)</label>
            <select id="status" name="status"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Semua Status</option>
                <option value="running" {{ $filters['status'] === 'running' ? 'selected' : '' }}>Berjalan</option>
                <option value="settled" {{ $filters['status'] === 'settled' ? 'selected' : '' }}>Lunas</option>
            </select>
        </div>
        @if ($isAdmin)
            <div class="w-full lg:w-64">
                <label for="member_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota</label>
                <select id="member_id" name="member_id"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">Semua Anggota</option>
                    @foreach ($memberOptions as $memberOption)
                        <option value="{{ $memberOption->rec_id }}" {{ $filters['member_id'] === (string) $memberOption->rec_id ? 'selected' : '' }}>{{ $memberOption->icuno }} - {{ $memberOption->icunm }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-search"></i> Filter
        </button>
        @if ($filters['q'] !== '' || $filters['status'] !== '' || ($isAdmin && $filters['member_id'] !== ''))
            <a href="{{ route('cooperative.loans.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Daftar Pinjaman</p>
            <p class="text-xs text-gray-400">{{ $loans->total() }} pinjaman</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">No. Transaksi</th>
                        <th class="px-5 py-3 font-bold">Tanggal</th>
                        <th class="px-5 py-3 font-bold">Anggota</th>
                        <th class="px-5 py-3 font-bold">Keterangan</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Total Tagihan (Rp)</th>
                        <th class="px-5 py-3 text-center font-bold">Tenor</th>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($loans as $loan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-primary">{{ $loan->trnno }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600">{{ $loan->trndt ? \Carbon\Carbon::parse($loan->trndt)->format('d M Y') : '-' }}</td>
                            <td class="px-5 py-3">
                                @if ($loan->member)
                                    <a href="{{ route('cooperative.members.detail', ['rec_id' => $loan->member->rec_id]) }}" class="font-semibold text-gray-800 hover:text-brand-primary">{{ $loan->member->icunm }}</a>
                                    <p class="font-mono text-xs text-gray-400">{{ $loan->member->icuno }}</p>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 max-w-48 truncate text-gray-700" title="{{ $loan->descr }}">{{ $loan->descr ?: '-' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($loan->principle, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($loan->totalloan, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-gray-600">{{ $loan->term }} bln</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $loan->startper }} - {{ $loan->endper }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClass() }}">{{ $loan->statusLabel() }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('cooperative.loans.detail', ['rec_id' => $loan->rec_id]) }}"
                                   class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-brand-primary transition hover:bg-blue-50">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada pinjaman yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $loans->links() }}
        </div>
    </div>
</div>
@endsection
