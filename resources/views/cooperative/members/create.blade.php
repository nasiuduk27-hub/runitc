@extends('layouts.app')

@section('title', 'RUN-ITC | Tambah Anggota Koperasi')

@section('title', 'RUN-ITC | Tambah Anggota Koperisi')

<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single {
        height: 42px !important;
        border-color: #E5E7EB !important;
        border-radius: 0.75rem !important;
        background-color: #F9FAFB !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px !important;
        padding-left: 12px !important;
        color: #1F2937 !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6B7280 !important; font-weight: 500 !important; }
    .select2-dropdown { border-radius: 0.75rem !important; border-color: #E5E7EB !important; }
    .select2-search--dropdown .select2-search__field {
        border-radius: 0.5rem !important;
        border-color: #E5E7EB !important;
        outline: none !important;
        font-size: 0.875rem !important;
    }
</style>

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.members.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tambah Anggota Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Pilih akun RUNITC karyawan, sistem membuat data anggota baru dan memberikan role CU Member otomatis.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('cooperative.members.store') }}" class="space-y-5">
        @csrf
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-blue-50/60 px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Nomor Anggota Berikutnya</p>
                <p class="font-mono text-lg font-extrabold text-brand-primary">{{ $nextIcuno }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="user_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Akun RUNITC <span class="text-red-500">*</span></label>
                    <select id="user_rec_id" name="user_rec_id" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value=""></option>
                        @foreach ($userOptions as $userOption)
                            <option value="{{ $userOption['rec_id'] }}" {{ old('user_rec_id') == $userOption['rec_id'] ? 'selected' : '' }}>{{ $userOption['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Ketik untuk mencari nama atau username karyawan. Akun ini akan otomatis terhubung ke anggota dan mendapat role CU Member.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="icunm" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" id="icunm" name="icunm" maxlength="40" value="{{ old('icunm') }}"
                           placeholder="contoh: BUDI SANTOSO"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div>
                    <label for="alias_nm" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Alias / Nama Panggilan</label>
                    <input type="text" id="alias_nm" name="alias_nm" maxlength="10" value="{{ old('alias_nm') }}"
                           placeholder="contoh: Budi"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>

                <div>
                    <label for="joindt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Bergabung <span class="text-red-500">*</span></label>
                    <input type="date" id="joindt" name="joindt" value="{{ old('joindt', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div>
                    <label for="swajib" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Simpanan Wajib (Rupiah) <span class="text-red-500">*</span></label>
                    <input type="number" id="swajib" name="swajib" min="0" step="1000" value="{{ old('swajib', $defaultSwajib) }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div>
                    <label for="refno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Refno Pegawai</label>
                    <input type="text" id="refno" name="refno" maxlength="15" value="{{ old('refno') }}"
                           placeholder="contoh: 11.1.04-002"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                Status awal: <span class="font-bold text-gray-700">Regular Member</span> | Outstanding awal: Rp 0 |
                Data ditulis ke sistem lama (icu_member) dan tercatat di audit log.
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit"
                        onclick="return confirm('Simpan anggota baru ke sistem lama?')"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                    <i class="fas fa-user-plus"></i> Simpan Anggota
                </button>
                <a href="{{ route('cooperative.members.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</a>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#user_rec_id').select2({
            placeholder: '-- Cari & pilih karyawan --',
            allowClear: true,
            width: '100%',
        });
    });
</script>
@endpush
