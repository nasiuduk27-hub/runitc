@extends('layouts.app')

@section('title', 'RUN-ITC | Test Plan')

@section('content')
@php
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-gray-900">Data Master TAD</h1>
            <p class="mt-1 text-sm text-gray-500">Supervisor dan Captain untuk operasional CBT.</p>
        </div>
        @if ($canManageSupervisor)
            <a href="{{ route('cbt-ops.test-plan.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-gray-800">
                <i class="fas fa-plus"></i> Tambah Data
            </a>
        @endif
    </div>

    @if ($error)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $error }}</div>
    @endif
    @if (session('success_msg'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <form method="GET" action="{{ route('cbt-ops.test-plan.index') }}" class="grid gap-3 md:grid-cols-4">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama / alias" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            <select name="type" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <option value="">Semua Jabatan</option>
                <option value="SPV" @selected($filters['type'] === 'SPV')>Supervisor</option>
                <option value="CAP" @selected($filters['type'] === 'CAP')>Captain</option>
            </select>
            <select name="status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <option value="ALL" @selected($filters['status'] === 'ALL')>ALL Status</option>
                <option value="1" @selected($filters['status'] === '1')>Active</option>
                <option value="0" @selected($filters['status'] === '0')>Suspend</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-blue-700">Filter</button>
                <a href="{{ route('cbt-ops.test-plan.index') }}" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black uppercase tracking-wide text-gray-600 hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase text-blue-700">Total: {{ number_format($totalRows) }} Personel</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nama Lengkap</th>
                        <th class="px-4 py-3 text-center">Jabatan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Level</th>
                        <th class="px-4 py-3">Pengalaman/Kemampuan</th>
                        @if ($canManageSupervisor)<th class="px-4 py-3 text-right">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $tad)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-xs font-black text-gray-500">
                                        @if (! empty($tad['photo_path']))
                                            <img src="{{ url('/'.ltrim($tad['photo_path'], '/')) }}" alt="{{ $tad['spv_name'] }}" class="h-full w-full object-cover">
                                        @else
                                            {{ substr($tad['spv_name'] ?? '-', 0, 1) }}
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-black text-gray-900">{{ $tad['spv_name'] ?? '-' }}</p>
                                        <p class="text-[11px] font-semibold text-gray-400">{{ $tad['spv_alias'] ?? '-' }} · {{ $tad['kota_nama'] ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase text-indigo-700">{{ (int) ($tad['captain'] ?? 0) === 1 ? 'Captain' : 'Supervisor' }}</span></td>
                            <td class="px-4 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ (int) ($tad['status'] ?? 0) === 1 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ (int) ($tad['status'] ?? 0) === 1 ? 'Active' : 'Suspend' }}</span></td>
                            <td class="px-4 py-3 text-center font-black text-gray-600">{{ $tad['lvl_spv'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $tad['skills_notes'] ?? '-' }}</td>
                            @if ($canManageSupervisor)
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('cbt-ops.test-plan.edit', (int) $tad['rec_id']) }}" class="rounded-lg bg-indigo-50 px-3 py-1.5 font-bold text-indigo-700 hover:bg-indigo-100">Edit</a>
                                        <form method="POST" action="{{ route('cbt-ops.test-plan.destroy', (int) $tad['rec_id']) }}" onsubmit="return confirm('Hapus role TAD SPV TEST untuk {{ addslashes($tad['spv_name'] ?? '') }}? Akun ITC dan role lain tidak akan dihapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 font-bold text-red-700 hover:bg-red-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageSupervisor ? 6 : 5 }}" class="px-6 py-16 text-center text-gray-400">Tidak ada data TAD ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalPages > 1)
            <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                <span>Halaman {{ $filters['page'] }} dari {{ $totalPages }}</span>
                <div class="flex gap-2">
                    @if ($filters['page'] > 1)
                        <a href="{{ route('cbt-ops.test-plan.index', $queryFor(['page' => $filters['page'] - 1])) }}" class="rounded-lg border px-3 py-2 hover:bg-gray-50">Sebelumnya</a>
                    @endif
                    @if ($filters['page'] < $totalPages)
                        <a href="{{ route('cbt-ops.test-plan.index', $queryFor(['page' => $filters['page'] + 1])) }}" class="rounded-lg border px-3 py-2 hover:bg-gray-50">Berikutnya</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
