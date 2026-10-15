@extends('layouts.guest')

@section('title', 'RUN-ITC | Akses File')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gradient-to-br from-blue-50 to-white p-6 text-center">
            <img src="{{ asset('assets/images/RUNITC_LOGO.png') }}" alt="RUN-ITC" class="mx-auto mb-3 h-10 object-contain">

            @if ($state === 'ready')
                <div class="mb-1 flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-wide text-green-700">
                    <i class="fas fa-check-circle"></i> Tautan Valid
                </div>
                <h1 class="truncate text-lg font-extrabold text-gray-900">{{ $fileName }}</h1>
                <p class="mt-1 text-xs text-gray-500">{{ $fileSize }} &middot; Pemilik: {{ $ownerName }}</p>
            @elseif ($state === 'password')
                <div class="mb-1 flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-wide text-amber-600">
                    <i class="fas fa-lock"></i> Dilindungi Password
                </div>
                <h1 class="text-lg font-extrabold text-gray-900">File Ini Terkunci</h1>
                <p class="mt-1 text-xs text-gray-500">Masukkan password dari pembuat tautan untuk melihat file.</p>
            @elseif ($state === 'no_access')
                <div class="mb-1 flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-wide text-amber-600">
                    <i class="fas fa-user-lock"></i> Belum Punya Akses
                </div>
                <h1 class="truncate text-lg font-extrabold text-gray-900">{{ $fileName }}</h1>
                <p class="mt-1 text-xs text-gray-500">Pemilik: {{ $ownerName }}</p>
            @elseif ($state === 'unavailable')
                <div class="mb-1 flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-wide text-red-600">
                    <i class="fas fa-circle-exclamation"></i> Tidak Tersedia
                </div>
                <h1 class="text-lg font-extrabold text-gray-900">File Sudah Tidak Tersedia</h1>
            @else
                <div class="mb-1 flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-wide text-red-600">
                    <i class="fas fa-triangle-exclamation"></i> Tautan Tidak Valid
                </div>
                <h1 class="text-lg font-extrabold text-gray-900">Tautan Tidak Berlaku</h1>
            @endif
        </div>

        <div class="space-y-4 p-6">
            @if ($state === 'ready')
                <p class="text-sm text-gray-600">Klik tombol di bawah untuk mengunduh file. Unduhan tidak dimulai otomatis.</p>

                @if ($canDownload)
                    <a href="{{ $downloadUrl }}" class="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                        <i class="fas fa-download"></i> Unduh File
                    </a>
                @else
                    <p class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs text-amber-700">Tautan ini hanya mengizinkan akses informasi file. Unduhan tidak diaktifkan oleh pembuat tautan.</p>
                @endif
            @elseif ($state === 'password')
                @if (! empty($error))
                    <p class="rounded-xl border border-red-100 bg-red-50 p-3 text-xs font-medium text-red-700">{{ $error }}</p>
                @endif

                <form method="POST" action="{{ route('filing-system.link.password', ['token' => request()->route('token')]) }}" class="space-y-3">
                    @csrf
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="off"
                        placeholder="Password"
                        class="block w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    >
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                        <i class="fas fa-unlock-alt"></i> Buka File
                    </button>
                </form>
            @elseif ($state === 'no_access')
                <p class="text-sm text-gray-600">Anda belum memiliki hak untuk membuka file ini. Silakan hubungi pemilik file untuk meminta akses.</p>

                @if (session('success_msg'))
                    <p class="rounded-xl border border-green-100 bg-green-50 p-3 text-xs font-medium text-green-700">{{ session('success_msg') }}</p>
                @endif
                @if (session('error_msg'))
                    <p class="rounded-xl border border-red-100 bg-red-50 p-3 text-xs font-medium text-red-700">{{ session('error_msg') }}</p>
                @endif

                @if (($requestState ?? '') === 'guest')
                    <a href="{{ route('filing-system.link.login', ['token' => request()->route('token')]) }}" class="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                        <i class="fas fa-right-to-bracket"></i> Masuk untuk Minta Akses
                    </a>
                @elseif (($requestState ?? '') === 'form')
                    <form method="POST" action="{{ route('filing-system.link.request', ['token' => request()->route('token')]) }}" class="space-y-3">
                        @csrf
                        <textarea
                            name="message"
                            rows="3"
                            maxlength="500"
                            placeholder="Pesan untuk pemilik (opsional)"
                            class="block w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >{{ old('message') }}</textarea>
                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                            <i class="fas fa-paper-plane"></i> Minta Akses
                        </button>
                    </form>
                @elseif (($requestState ?? '') === 'pending')
                    <p class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs font-medium text-blue-700">Permintaan Anda sedang menunggu keputusan pemilik file.</p>
                @elseif (($requestState ?? '') === 'blocked')
                    <p class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-xs font-medium text-gray-600">File ini tidak menerima permintaan akses. Hubungi pemilik file secara langsung.</p>
                @endif
            @elseif ($state === 'unavailable')
                <p class="text-sm text-gray-600">File ini sudah tidak aktif (terarsip, dipindahkan, atau dihapus). Silakan hubungi pemilik file.</p>
            @else
                <p class="text-sm text-gray-600">Tautan tidak valid, sudah dicabut, atau sudah tidak berlaku.</p>
            @endif
        </div>
    </div>
</div>
@endsection
