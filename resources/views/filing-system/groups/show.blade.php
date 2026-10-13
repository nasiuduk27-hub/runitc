@extends('layouts.filing')

@section('title', 'RUN-ITC | '.$group['group_name'])

@section('content')
@php
    $roleLabel = ['manager' => 'Pengelola', 'contributor' => 'Kontributor', 'viewer' => 'Penonton'][$role] ?? 'Anggota';
@endphp

<div class="flex h-full flex-col">
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
        <i class="fas fa-folder-tree text-amber-500"></i>
        <span class="text-sm font-bold text-gray-800">{{ $group['group_name'] }}</span>
        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600">{{ $roleLabel }}</span>
        <span class="text-xs text-gray-400">· {{ (int) $group['file_count'] }} file</span>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            @if ($canContribute)
                <button type="button" onclick="document.getElementById('groupUploadModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">
                    <i class="fas fa-upload"></i> Unggah File
                </button>
            @endif
            <button type="button" onclick="document.getElementById('groupMoveModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50">
                <i class="fas fa-folder-plus"></i> Pindah File
            </button>
            <a href="{{ route('filing-system.groups.members', $group['rec_id']) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50">
                <i class="fas fa-users"></i> Anggota
            </a>
            @if ($canManage)
                <button type="button" onclick="document.getElementById('groupRenameModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-pen"></i> Ganti Nama
                </button>
                <form method="POST" action="{{ route('filing-system.groups.archive', $group['rec_id']) }}" onsubmit="return confirm('Arsipkan grup ini? Grup tidak akan tampil di sidebar.')">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50"><i class="fas fa-archive"></i> Arsipkan</button>
                </form>
            @else
                <form method="POST" action="{{ route('filing-system.groups.leave', $group['rec_id']) }}" onsubmit="return confirm('Keluar dari grup ini?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success_msg'))
        <div class="m-4 mb-0 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="m-4 mb-0 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    @include('filing-system._list', [
        'items' => $items,
        'pagination' => $pagination,
        'filters' => $filters,
        'folder' => 'group',
        'groupId' => $group['rec_id'],
        'canManage' => $canManage,
        'viewMode' => $viewMode,
        'pageTitleOverride' => $group['group_name'],
    ])
</div>

{{-- Modal Unggah --}}
<div id="groupUploadModal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="mb-3 text-sm font-bold text-gray-800">Unggah ke {{ $group['group_name'] }}</h3>
        <form id="groupUploadForm" method="POST" action="{{ route('filing-system.groups.files.upload', $group['rec_id']) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="file" name="raw_files[]" multiple required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
            <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="zip_files" value="1" class="h-4 w-4 rounded border-gray-300"> Jadikan satu ZIP</label>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('groupUploadModal').classList.add('hidden')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Unggah</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Pindah File --}}
<div id="groupMoveModal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="mb-3 text-sm font-bold text-gray-800">Pindahkan File ke {{ $group['group_name'] }}</h3>
        <form method="POST" action="{{ route('filing-system.groups.files.move', $group['rec_id']) }}" class="space-y-3">
            @csrf
            <select name="filing_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <option value="">Pilih file dari My Drive…</option>
                @foreach ($myFiles as $file)
                    <option value="{{ $file['rec_id'] }}">{{ $file['file_name'] }}</option>
                @endforeach
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('groupMoveModal').classList.add('hidden')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Pindahkan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Ganti Nama --}}
@if ($canManage)
<div id="groupRenameModal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="mb-3 text-sm font-bold text-gray-800">Ganti Nama / Deskripsi</h3>
        <form method="POST" action="{{ route('filing-system.groups.update', $group['rec_id']) }}" class="space-y-3">
            @csrf
            <input type="text" name="group_name" value="{{ $group['group_name'] }}" maxlength="100" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
            <textarea name="description" rows="3" maxlength="1000" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">{{ $group['description'] }}</textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('groupRenameModal').classList.add('hidden')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function switchFilingView(mode) {
    document.cookie = 'filing_view=' + mode + '; path=/; max-age=31536000; SameSite=Lax';
    window.location.reload();
}

document.getElementById('groupUploadForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Mengunggah…'; }

    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
                return;
            }
            if (button) { button.disabled = false; button.textContent = 'Unggah'; }
            alert('Gagal: ' + (result.message || 'Upload gagal.'));
        })
        .catch(() => {
            if (button) { button.disabled = false; button.textContent = 'Unggah'; }
            alert('Terjadi kesalahan koneksi.');
        });
});
</script>
@endsection
