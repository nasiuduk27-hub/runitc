@extends('layouts.app')

@section('title', 'RUN-ITC | Refinancing')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isAdmin ? 'Refinancing' : 'Refinancing Saya' }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ $isAdmin
                ? 'Daftar seluruh pengajuan skip pokok & percepatan anggota.'
                : 'Pengajuan refinancing untuk anggota '.$member?->icunm.' ('.$member?->icuno.')' }}</p>
        </div>
        <a href="{{ route('cooperative.skips.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-forward"></i> Ajukan Skip
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    @if ($isAdmin)
        <div class="rounded-xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm font-medium text-blue-700">Menampilkan seluruh data koperasi.</div>
    @else
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-600">Menampilkan hanya data pengajuan Anda.</div>
    @endif

    @if ($isAdmin)
        <form method="GET" action="{{ route('cooperative.skips.index') }}" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <input type="text" name="q" value="{{ $filter }}" placeholder="Cari nama atau nomor anggota..."
                   class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">Cari</button>
        </form>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Daftar Pengajuan Skip</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <th class="px-5 py-3 font-bold">#</th><th class="px-5 py-3 font-bold">Jenis</th>
                    <th class="px-5 py-3 font-bold">Anggota</th>
                    <th class="px-5 py-3 font-bold">Rentang</th>
                    <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                    <th class="px-5 py-3 text-right font-bold">Bunga (Rp)</th>
                    <th class="px-5 py-3 text-center font-bold">Tenor Baru</th>
                    <th class="px-5 py-3 font-bold">Status</th><th class="px-5 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($skips as $skip)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs text-gray-400">#{{ $skip->id }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $skip->mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                    {{ $skip->mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE ? 'Percepat' : 'Skip Pokok' }}
                                </span>
                            </td>
                            <td class="px-5 py-3"><p class="font-semibold text-gray-800">{{ $skip->member_name }}</p><p class="font-mono text-xs text-gray-400">{{ $skip->member_icuno }}</p></td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600">{{ $skip->mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE ? '−'.$skip->months_count.' bln' : \App\Services\Cooperative\CooperativePeriod::label($skip->start_period).' +'.$skip->months_count.' bln' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($skip->principal_moved, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-amber-600">{{ number_format($skip->extra_interest, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-gray-600">{{ $skip->new_term }} bln</td>
                            <td class="px-5 py-3"><span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $service->statusBadgeClass($skip->status) }}">{{ $service->statusLabel($skip->status) }}</span></td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('cooperative.skips.detail', ['id' => $skip->id]) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-brand-primary transition hover:bg-blue-50">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada pengajuan refinancing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $skips->links() }}</div>
    </div>
</div>
@endsection
