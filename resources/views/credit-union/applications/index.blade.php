@extends('layouts.app')

@section('title', 'RUN-ITC | Pengajuan Pinjaman')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pengajuan Pinjaman</h1>
            <p class="mt-0.5 text-sm text-gray-500">Workflow persetujuan dengan maker-checker. Tabel RUNITC baru, data lama tidak tersentuh.</p>
            @if (! $isAdmin)
                <p class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700">
                    <i class="fas fa-lock"></i> Hanya menampilkan pengajuan milik Anda.
                </p>
            @endif
        </div>
        <a href="{{ route('cooperative.applications.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-plus"></i> Pengajuan Baru
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Menunggu Persetujuan</p>
            <p class="mt-1 text-lg font-extrabold text-amber-600">{{ number_format($stats['submitted'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Disetujui</p>
            <p class="mt-1 text-lg font-extrabold text-green-600">{{ number_format($stats['approved'], 0, ',', '.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('cooperative.applications.index') }}"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama anggota, nomor anggota, atau keperluan..."
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="w-full sm:w-56">
            <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status</label>
            <select id="status" name="status"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Semua Status</option>
                @foreach ($statusLabels as $code => $label)
                    <option value="{{ $code }}" {{ $filters['status'] === $code ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-search"></i> Filter
        </button>
        @if ($filters['q'] !== '' || $filters['status'] !== '')
            <a href="{{ route('cooperative.applications.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Daftar Pengajuan</p>
            <p class="text-xs text-gray-400">{{ $applications->total() }} pengajuan</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">#</th>
                        <th class="px-5 py-3 font-bold">Anggota</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-center font-bold">Tenor / Bunga</th>
                        <th class="px-5 py-3 text-right font-bold">Cicilan Bulan I (Rp)</th>
                        <th class="px-5 py-3 font-bold">Keperluan</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($applications as $application)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs text-gray-400">#{{ $application->id }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800">{{ $application->member_name }}</p>
                                <p class="font-mono text-xs text-gray-400">{{ $application->member_icuno }}</p>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($application->principal_amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-center text-gray-600">{{ $application->tenor_months }} bln | {{ number_format($application->annual_rate_percent, 2, ',', '.') }}%<span class="block text-[10px] uppercase text-gray-400">{{ $application->calculation_method }}</span></td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($application->monthly_installment, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 max-w-40 truncate text-gray-600" title="{{ $application->descr }}">{{ $application->descr ?: '-' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $service->statusBadgeClass($application->status) }}">{{ $service->statusLabel($application->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('cooperative.applications.detail', ['id' => $application->id]) }}"
                                   class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-brand-primary transition hover:bg-blue-50">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada pengajuan pinjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $applications->links() }}
        </div>
    </div>
</div>
@endsection
