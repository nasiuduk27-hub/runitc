@extends('layouts.filing')

@section('title', 'RUN-ITC | Minta Akses Grup')

@section('content')
<div class="mx-auto max-w-xl p-4 sm:p-6">
    <div class="mb-5 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 bg-gradient-to-br from-amber-50 to-white p-5">
            <div class="mb-1 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-amber-600">
                <i class="fas fa-user-lock"></i> Belum Punya Akses
            </div>
            <h1 class="truncate text-lg font-extrabold text-gray-900">{{ $group['group_name'] ?? 'Grup' }}</h1>
            <p class="mt-1 text-xs text-gray-500">Pemilik: {{ $ownerName }}</p>
        </div>
    </div>

    @if (session('success_msg'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    @if ($requestPending)
        <p class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">Permintaan Anda untuk grup ini sedang menunggu keputusan pengelola.</p>
    @else
        <p class="mb-4 text-sm text-gray-600">Anda belum menjadi anggota grup ini. Kirim permintaan kepada pengelola grup untuk mendapatkan akses.</p>

        <form method="POST" action="{{ route('filing-system.groups.request', $group['rec_id']) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Pesan (opsional)</label>
                <textarea name="message" rows="3" maxlength="500" placeholder="Alasan meminta akses"
                          class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                <i class="fas fa-paper-plane"></i> Minta Akses
            </button>
        </form>
    @endif
</div>
@endsection
