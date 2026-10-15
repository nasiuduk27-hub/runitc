@extends('layouts.filing')

@section('title', 'RUN-ITC | Kelola Tipe File')

@section('content')
<div class="py-8 md:py-12">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        {{-- Flash Messages --}}
        @if (session('success_msg'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success_msg') }}
            </div>
        @endif
        @if (session('error_msg'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error_msg') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="mb-6 flex flex-col items-start justify-between gap-4 border-b border-gray-200 pb-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-3 text-2xl font-black tracking-tight text-gray-900">
                    <i class="fas fa-tags text-amber-500"></i>
                    Kelola Tipe File
                </h2>
                <p class="mt-1 text-xs text-gray-500">Master kategori bisnis dokumen Filing System (tbl_code 82). Tipe aktif dapat dipilih saat upload.</p>
            </div>
            <button type="button" onclick="openAddModal()" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow transition hover:bg-blue-700">
                <i class="fas fa-plus"></i> Tambah Tipe
            </button>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-center w-12">No</th>
                        <th class="px-4 py-3 w-28">Kode</th>
                        <th class="px-4 py-3">Nama Tipe</th>
                        <th class="px-4 py-3 text-center w-24">Urutan</th>
                        <th class="px-4 py-3 text-center w-28">Status</th>
                        <th class="px-4 py-3 text-right w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($types as $idx => $t)
                        @php
                            $isActive = (int) $t['statrec'] === 1;
                        @endphp
                        <tr class="hover:bg-gray-50/60 {{ $isActive ? '' : 'bg-gray-50/30 opacity-75' }}">
                            <td class="px-4 py-3 text-center text-xs text-gray-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs font-bold text-gray-800">{{ $t['code'] }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                {{ $t['descr'] }}
                                @if (! $isActive)
                                    <span class="ml-2 text-[10px] italic text-gray-400">(Nonaktif)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-600">{{ $t['urutan'] }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($isActive)
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-[10px] font-bold text-green-700">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold text-gray-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button"
                                        onclick="openEditModal({{ $t['rec_id'] }}, '{{ addslashes($t['code']) }}', '{{ addslashes($t['descr']) }}', {{ (int) $t['urutan'] }})"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                                        title="Ubah nama / urutan">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <form method="POST" action="{{ route('filing-system.admin.file-types.toggle', $t['rec_id']) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="rounded px-2.5 py-1 text-xs font-semibold {{ $isActive ? 'text-amber-600 hover:bg-amber-50' : 'text-green-600 hover:bg-green-50' }}"
                                            title="{{ $isActive ? 'Nonaktifkan tipe ini' : 'Aktifkan tipe ini' }}">
                                            @if ($isActive)
                                                <i class="fas fa-ban mr-1"></i> Nonaktifkan
                                            @else
                                                <i class="fas fa-check mr-1"></i> Aktifkan
                                            @endif
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada tipe file di master.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Tambah --}}
<div id="modalAddType" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-4">
            <h3 class="text-sm font-bold text-gray-800"><i class="fas fa-plus text-blue-600 mr-2"></i> Tambah Tipe File Baru</h3>
            <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('filing-system.admin.file-types.store') }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Kode Tipe <span class="text-red-500">*</span></label>
                <input type="text" name="code" required maxlength="5" placeholder="Contoh: INV" class="w-full uppercase font-mono rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <p class="text-[10px] text-gray-400 mt-1 italic">Maksimal 5 huruf/angka kapital. Tidak dapat diubah setelah dibuat.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Tipe File <span class="text-red-500">*</span></label>
                <input type="text" name="descr" required maxlength="50" placeholder="Contoh: INVOICE" class="w-full uppercase rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Urutan Tampilan</label>
                <input type="number" name="urutan" value="0" min="0" max="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <p class="text-[10px] text-gray-400 mt-1 italic">Nomor urut pada daftar dropdown (angka kecil tampil lebih dulu).</p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeAddModal()" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit --}}
<div id="modalEditType" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-4">
            <h3 class="text-sm font-bold text-gray-800"><i class="fas fa-edit text-amber-500 mr-2"></i> Edit Tipe File</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="formEditType" method="POST" action="" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Kode Tipe</label>
                <input type="text" id="edit_type_code" disabled class="w-full uppercase font-mono rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                <p class="text-[10px] text-gray-400 mt-1 italic">Kode terkunci karena tersimpan pada file fisik.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Tipe File <span class="text-red-500">*</span></label>
                <input type="text" name="descr" id="edit_type_descr" required maxlength="50" class="w-full uppercase rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Urutan Tampilan</label>
                <input type="number" name="urutan" id="edit_type_urutan" min="0" max="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeEditModal()" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    const modal = document.getElementById('modalAddType');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAddModal() {
    const modal = document.getElementById('modalAddType');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function openEditModal(id, code, descr, urutan) {
    const form = document.getElementById('formEditType');
    form.action = '{{ url('/filing-system/admin/file-types') }}/' + id;
    document.getElementById('edit_type_code').value = code;
    document.getElementById('edit_type_descr').value = descr;
    document.getElementById('edit_type_urutan').value = urutan;

    const modal = document.getElementById('modalEditType');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEditModal() {
    const modal = document.getElementById('modalEditType');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}
</script>
@endsection
