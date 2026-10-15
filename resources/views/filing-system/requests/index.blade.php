@extends('layouts.filing')

@section('title', 'RUN-ITC | Permintaan Akses')

@section('content')
<div class="mx-auto max-w-3xl p-4 sm:p-6">
    <h1 class="mb-1 text-lg font-bold text-gray-800">Permintaan Akses</h1>
    <p class="mb-5 text-xs text-gray-400">Permintaan yang menunggu keputusan Anda sebagai pemilik file atau pengelola grup. Notifikasi menaut ke halaman ini.</p>

    @if (session('success_msg'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    @forelse ($requests as $accessRequest)
        @php $isGroup = ($accessRequest['target_type'] ?? '') === 'group'; @endphp
        <div class="mb-3 rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $isGroup ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $isGroup ? 'Grup' : 'File' }}
                </span>
                <p class="min-w-0 flex-1 truncate text-sm font-bold text-gray-800">{{ $accessRequest['target_name'] ?? '-' }}</p>
                <span class="text-[10px] font-medium text-gray-400">{{ $accessRequest['created_at'] ?? '' }}</span>
            </div>

            <p class="text-xs text-gray-600">
                Peminta: <span class="font-semibold text-gray-800">{{ $accessRequest['requester_name'] ?? ('User #'.($accessRequest['requester_id'] ?? 0)) }}</span>
            </p>

            @if (! empty($accessRequest['message']))
                <p class="mt-2 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-700">{{ $accessRequest['message'] }}</p>
            @else
                <p class="mt-2 text-xs italic text-gray-400">Tanpa pesan.</p>
            @endif

            <div class="mt-3 flex items-center justify-end gap-2">
                <form method="POST" action="{{ route('filing-system.requests.reject', (int) $accessRequest['rec_id']) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-600 transition hover:bg-red-50">Tolak</button>
                </form>
                <form method="POST" action="{{ route('filing-system.requests.approve', (int) $accessRequest['rec_id']) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700">
                        {{ $isGroup ? 'Setujui (Penonton)' : 'Setujui' }}
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-400">Tidak ada permintaan yang menunggu keputusan.</div>
    @endforelse
</div>
@endsection
