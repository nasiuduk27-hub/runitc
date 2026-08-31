@extends('layouts.app')

@section('title', 'RUN-ITC | Anggota Koperasi')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Anggota Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Data keanggotaan koperasi (read-only dari sistem lama).</p>
        </div>
        @if ($isCoopAdmin)
            <a href="{{ route('cooperative.members.create') }}"
               class="inline-flex items-center gap-2 self-start rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover sm:self-auto">
                <i class="fas fa-user-plus"></i> Tambah Anggota
            </a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Anggota</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($stats['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Regular Member</p>
            <p class="mt-1 text-lg font-extrabold text-green-600">{{ number_format($stats['regular'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Outstanding Member</p>
            <p class="mt-1 text-lg font-extrabold text-amber-600">{{ number_format($stats['outstanding'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Non-Active</p>
            <p class="mt-1 text-lg font-extrabold text-red-500">{{ number_format($stats['non_active'], 0, ',', '.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('cooperative.members.index') }}"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nomor anggota, nama, atau refno pegawai..."
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="w-full sm:w-56">
            <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status</label>
            <select id="status" name="status"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Semua Status</option>
                @foreach ($statusLabels as $code => $label)
                    <option value="{{ $code }}" {{ $filters['status'] === (string) $code ? 'selected' : '' }}>{{ $code }} - {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-search"></i> Filter
        </button>
        @if ($filters['q'] !== '' || $filters['status'] !== '')
            <a href="{{ route('cooperative.members.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Daftar Anggota</p>
            <p class="text-xs text-gray-400">{{ $members->total() }} anggota</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">No. Anggota</th>
                        <th class="px-5 py-3 font-bold">Nama</th>
                        <th class="px-5 py-3 font-bold">Bergabung</th>
                        <th class="px-5 py-3 text-right font-bold">Simpanan Wajib (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Outstanding (Rp)</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                        <th class="px-5 py-3 font-bold">Akun RUNITC</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($members as $member)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-primary">{{ $member->icuno }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800">{{ $member->icunm }}</p>
                                @if (! empty($member->alias_nm) && $member->alias_nm !== $member->icunm)
                                    <p class="text-xs text-gray-400">{{ $member->alias_nm }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $member->joindt ? \Carbon\Carbon::parse($member->joindt)->format('d M Y') : '-' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($member->swajib, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($member->outstanding, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $member->statusBadgeClass() }}">{{ $member->statusLabel() }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if ($member->itc_user_id > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700">
                                        <i class="fas fa-link"></i> #{{ $member->itc_user_id }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700">
                                        <i class="fas fa-unlink"></i> Belum tersinkron
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($member->itc_user_id === 0)
                                        <a href="{{ route('cooperative.members.sync', ['member' => $member->rec_id]) }}"
                                           class="rounded-lg border border-brand-primary/30 bg-brand-primary/5 px-3 py-1.5 text-xs font-semibold text-brand-primary transition hover:bg-brand-primary/10">Sinkron</a>
                                    @endif
                                    <a href="{{ route('cooperative.members.detail', ['rec_id' => $member->rec_id]) }}"
                                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-brand-primary transition hover:bg-blue-50">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada anggota yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-5 py-3">
            {{ $members->links() }}
        </div>
    </div>
</div>
@endsection
