@extends('layouts.app')

@section('title', 'RUN-ITC | Verify Email')
@section('page_title', 'Verify Email')

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-extrabold text-gray-900">Verifikasi Email Baru</h1>
    <p class="mt-2 text-sm text-gray-500">Masukkan kode OTP yang dikirim/dibuat untuk email baru: <b>{{ session('change_email_new') }}</b></p>
    @if (session('success_msg'))<div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('success_msg') }}</div>@endif
    @if (session('error_msg'))<div class="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700">{{ session('error_msg') }}</div>@endif
    <form action="{{ route('profile.email.verify') }}" method="POST" class="mt-5 space-y-4">
        @csrf
        <input name="full_token" maxlength="5" required class="w-full rounded-xl border border-gray-300 px-4 py-3 text-center text-2xl font-black tracking-[0.5em]" placeholder="00000">
        <button class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-extrabold text-white hover:bg-brand-primaryHover">Verifikasi</button>
        <a href="{{ route('profile.index') }}" class="block text-center text-sm font-semibold text-gray-500 hover:text-gray-800">Batal</a>
    </form>
</div>
@endsection
