@extends('layouts.filing')

@section('title', 'RUN-ITC | Semua Grup')

@section('content')
<div class="mx-auto max-w-5xl p-4 sm:p-6">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-gray-800">Semua Grup</h1>
            <p class="text-xs text-gray-400">Grup folder yang Anda ikuti.</p>
        </div>
        <a href="{{ route('filing-system.groups.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
            <i class="fas fa-plus"></i> Buat Grup
        </a>
    </div>

    @if (session('success_msg'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($groups as $group)
            <a href="{{ route('filing-system.groups.show', $group['rec_id']) }}" class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-folder-tree text-xl text-amber-500"></i>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-800" title="{{ $group['group_name'] }}">{{ $group['group_name'] }}</p>
                        <p class="text-[11px] text-gray-400">{{ (int) $group['file_count'] }} file</p>
                    </div>
                </div>
                @if (! empty($group['description']))
                    <p class="mt-2 line-clamp-2 text-xs text-gray-500">{{ $group['description'] }}</p>
                @endif
                <div class="mt-3">
                    @php
                        $roleLabel = ['manager' => 'Pengelola', 'contributor' => 'Kontributor', 'viewer' => 'Penonton'][$group['role']] ?? 'Anggota';
                    @endphp
                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600">{{ $roleLabel }}</span>
                </div>
            </a>
        @empty
            <div class="col-span-full py-16 text-center text-sm text-gray-400">Belum ada grup. Buat grup pertama Anda.</div>
        @endforelse
    </div>
</div>
@endsection
