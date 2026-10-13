@extends('layouts.filing')

@section('title', 'RUN-ITC | Buat Grup')

@section('content')
<div class="mx-auto max-w-xl p-4 sm:p-6">
    <a href="{{ route('filing-system.groups.index') }}" class="mb-4 inline-flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>

    <h1 class="mb-1 text-lg font-bold text-gray-800">Buat Grup Baru</h1>
    <p class="mb-5 text-xs text-gray-400">Anda akan menjadi Pengelola grup ini.</p>

    @if (session('error_msg'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    <form method="POST" action="{{ route('filing-system.groups.store') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        <div>
            <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Nama Grup</label>
            <input type="text" name="group_name" value="{{ old('group_name') }}" maxlength="100" required placeholder="Contoh: PROJECT2026"
                   class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Deskripsi (opsional)</label>
            <textarea name="description" rows="3" maxlength="1000" placeholder="Deskripsi singkat grup"
                      class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500">{{ old('description') }}</textarea>
        </div>
        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('filing-system.groups.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50">Batal</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Buat Grup</button>
        </div>
    </form>
</div>
@endsection
