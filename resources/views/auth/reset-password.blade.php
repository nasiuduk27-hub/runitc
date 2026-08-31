@extends('layouts.guest')

@section('title', 'RUN-ITC | Buat Password Baru')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 p-4">
    <div class="relative w-full max-w-md rounded-3xl border border-gray-100 bg-white p-10 shadow-xl">
        <div class="absolute left-0 top-0 h-2 w-full bg-gradient-to-r from-green-400 to-emerald-500"></div>

        <div class="mb-8 mt-2">
            <h2 class="mb-2 text-2xl font-bold text-gray-800">Buat Password Baru</h2>
            <p class="text-sm text-gray-500">Silakan buat kata sandi baru untuk akun Anda. Gunakan kombinasi yang kuat.</p>
        </div>

        @if (session('success_msg'))
            <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{!! session('success_msg') !!}</div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('reset-password.submit') }}">
            @csrf
            <div class="mb-5">
                <label for="password_id" class="mb-1 block text-sm font-medium text-gray-700">Password Baru <span class="text-red-500">*</span></label>
                <input id="password_id" name="password_id" type="password" required minlength="6" placeholder="Minimal 6 karakter..." class="w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 outline-none transition-colors focus:bg-white focus:ring-2 focus:ring-green-500">
            </div>

            <div class="mb-8">
                <label for="retype_password" class="mb-1 block text-sm font-medium text-gray-700">Ketik Ulang Password <span class="text-red-500">*</span></label>
                <input id="retype_password" name="retype_password" type="password" required minlength="6" placeholder="Ulangi password di atas..." class="w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 outline-none transition-colors focus:bg-white focus:ring-2 focus:ring-green-500">
            </div>

            <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-green-500 py-3.5 font-semibold text-white shadow-md transition-all hover:bg-green-600">
                <i class="fas fa-save mr-2"></i> Simpan Password Baru
            </button>
        </form>
    </div>
</div>
@endsection
