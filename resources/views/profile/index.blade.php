@extends('layouts.app')

@section('title', 'RUN-ITC | Profile')
@section('page_title', 'Profile')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-4">
            <img src="{{ $photoUrl }}" alt="Profile" class="h-20 w-20 rounded-2xl object-cover ring-4 ring-blue-50">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">{{ $user->account_nm ?? 'User' }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $user->account_id ?? '-' }}</p>
                <p class="mt-1 text-xs font-semibold text-blue-600">{{ $user->email_id ?? '' }}</p>
            </div>
        </div>
    </div>

    @if (session('success_msg'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg') || $errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error_msg') ?: $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
            @csrf
            <h2 class="text-lg font-extrabold text-gray-900">Data Diri</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-sm font-bold text-gray-700">Nama Lengkap
                    <input name="account_nm" value="{{ old('account_nm', $user->account_nm ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                </label>
                <label class="block text-sm font-bold text-gray-700">Tanggal Lahir
                    <input type="date" name="dob" value="{{ old('dob', isset($user->dob) ? substr((string) $user->dob, 0, 10) : '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="block text-sm font-bold text-gray-700">Gender
                    <select name="sexmf" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih</option>
                        <option value="M" @selected(old('sexmf', $user->sexmf ?? '') === 'M')>Male</option>
                        <option value="F" @selected(old('sexmf', $user->sexmf ?? '') === 'F')>Female</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-gray-700">WhatsApp
                    <input name="whatsapp" value="{{ old('whatsapp', $user->whatsapp ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="block text-sm font-bold text-gray-700 md:col-span-2">Alamat
                    <textarea name="address" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $user->address ?? '') }}</textarea>
                </label>
                <label class="block text-sm font-bold text-gray-700">Provinsi
                    <select name="prov_cd" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih provinsi</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->rec_id }}" @selected((string) old('prov_cd', $user->prov_cd ?? '') === (string) $province->rec_id)>{{ $province->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-bold text-gray-700">Kota/Kabupaten
                    <select name="kotakabupaten" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih kota</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->rec_id }}" @selected((string) old('kotakabupaten', $user->kotakabupaten ?? '') === (string) $city->rec_id)>{{ $city->nama }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @php $primaryBank = $banks->first(); @endphp
            <div class="border-t border-gray-100 pt-5">
                <h2 class="mb-4 text-lg font-extrabold text-gray-900">Rekening Bank</h2>
                <input type="hidden" name="bank_rec_id" value="{{ old('bank_rec_id', $primaryBank->rec_id ?? '') }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <select name="bank_code" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih bank</option>
                        @foreach ($bankOptions as $code => $label)
                            <option value="{{ $code }}" @selected((string) old('bank_code', $primaryBank->bnkcd ?? '') === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input name="account_name" placeholder="Nama rekening" value="{{ old('account_name', $primaryBank->accnm ?? '') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <input name="account_no" placeholder="Nomor rekening" value="{{ old('account_no', $primaryBank->accno ?? '') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <h2 class="mb-4 text-lg font-extrabold text-gray-900">Foto Profil</h2>
                <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-gray-600">
                <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-red-600"><input type="checkbox" name="delete_photo" value="1"> Hapus foto saat ini</label>
            </div>

            <button class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-extrabold text-white hover:bg-brand-primaryHover">Simpan Profile</button>
        </form>

        <div class="space-y-6">
            <form action="{{ route('profile.password') }}" method="POST" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-extrabold text-gray-900">Ganti Password</h2>
                <input type="password" name="new_password" placeholder="Password baru" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <input type="password" name="confirm_password" placeholder="Konfirmasi password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-extrabold text-white hover:bg-gray-700">Update Password</button>
            </form>

            <form action="{{ route('profile.email.request') }}" method="POST" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-extrabold text-gray-900">Ganti Email</h2>
                <input type="email" name="new_email" placeholder="Email baru" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-amber-600">Kirim OTP</button>
            </form>
        </div>
    </div>
</div>
@endsection
