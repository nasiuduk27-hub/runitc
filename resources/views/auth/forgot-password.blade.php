@extends('layouts.guest')

@section('title', 'RUN-ITC | Lupa Password')

@section('content')
<div class="flex min-h-screen bg-white">
    <div class="relative hidden w-2/3 overflow-hidden bg-gray-900 lg:block">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-900 to-gray-900"></div>
        <div class="relative z-10 flex h-full flex-col items-center justify-center px-12 text-center text-white">
            <div class="mb-8 inline-block rounded-full border border-white/20 bg-white/10 p-8 shadow-lg backdrop-blur-sm">
                <i class="fas fa-shield-halved text-7xl text-indigo-300"></i>
            </div>
            <h2 class="mb-4 text-4xl font-bold tracking-wide">Secure Password Recovery</h2>
            <p class="max-w-lg text-lg text-indigo-200">Masukkan kredensial Anda untuk menerima token akses pemulihan yang aman.</p>
        </div>
    </div>

    <div class="flex w-full items-center justify-center overflow-y-auto bg-white p-8 lg:w-1/3">
        <div class="w-full max-w-md">
            <a href="{{ route('login') }}" class="mb-8 inline-flex items-center text-sm text-gray-500 transition-colors hover:text-indigo-600">
                <i class="fas fa-chevron-left mr-2"></i> Kembali ke Login
            </a>

            <div class="mb-8">
                <h2 class="mb-2 text-2xl font-bold text-gray-800">Forgot Password?</h2>
                <p class="text-sm text-gray-500">Masukkan Account ID atau Email yang terdaftar untuk menerima kode pemulihan (OTP).</p>
            </div>

            @if (session('success_msg'))
                <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                    {!! session('success_msg') !!}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('forgot-password.submit') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="login_input" class="mb-1 block text-sm font-medium text-gray-700">Account ID / Email <span class="text-red-500">*</span></label>
                    <input id="login_input" name="login_input" type="text" required autofocus value="{{ old('login_input') }}" placeholder="Masukkan ID atau Email..." class="w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 outline-none transition-colors focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-indigo-600 py-3 font-semibold text-white shadow-md transition-all hover:bg-indigo-700">
                    Kirim Kode OTP <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
