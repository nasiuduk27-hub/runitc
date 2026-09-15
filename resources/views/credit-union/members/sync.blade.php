@extends('layouts.app')

@section('title', 'RUN-ITC | Sinkron Akun Anggota')

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
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sinkron Akun RUNITC</h1>
            <p class="mt-0.5 text-sm text-gray-500">Member <strong class="text-brand-primary">{{ $member->icuno }}</strong> ({{ $member->icunm }})</p>
        </div>
        @if ($member->itc_user_id > 0)
            <div class="ml-4 rounded-xl border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700">
                Sudah tersinkron: #{{ $member->itc_user_id }}
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
        <p class="text-sm font-bold text-blue-800"><i class="fas fa-info-circle mr-1"></i> Data CU lama belum ditautkan ke akun RUNITC</p>
        <p class="mt-1 text-sm text-blue-700">Riwayat CU hanya bisa dilihat setelah data ditautkan. Pastikan <strong>{{ $member->icunm }}</strong> ({{ $member->icuno }}) <strong>sudah mendaftar akun RUNITC</strong> melalui halaman <code>Register</code> terlebih dahulu — daftar akun di bawah hanya menampilkan akun aktif yang belum terhubung. Setelah akun tersedia, pilih di sini dan kirim OTP ke email pemiliknya.</p>
    </div>

    <form method="POST" action="{{ route('cooperative.members.sync.store', ['member' => $member->rec_id]) }}" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf

        <div>
            <label for="target_user_id" class="block text-xs font-bold uppercase tracking-wide text-gray-500">Pilih Akun RUNITC <span class="text-red-500">*</span></label>
            <select id="target_user_id" name="target_user_id" required
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">-- Pilih akun --</option>
                @foreach ($userOptions as $userOption)
                    <option value="{{ $userOption['rec_id'] }}" {{ old('target_user_id') == $userOption['rec_id'] ? 'selected' : '' }}>{{ $userOption['label'] }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-gray-400">Ketik untuk mencari nama atau username akun. Akun ini akan ditautkan ke member {{ $member->icuno }} dan akan menerima kode verifikasi (OTP) via email.</p>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                <i class="fas fa-send"></i> Kirim OTP Verifikasi
            </button>
            <a href="{{ route('cooperative.members.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <i class="fas fa-times"></i> Batal
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#target_user_id').select2({
            placeholder: '-- Cari & pilih akun RUNITC --',
            allowClear: true,
            width: '100%',
        });
    });
</script>
@endpush