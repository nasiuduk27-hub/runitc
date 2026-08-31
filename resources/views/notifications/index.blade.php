@extends('layouts.app')

@section('title', 'RUN-ITC | Notifications')
@section('page_title', 'Notifications')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>
            <p class="mt-1 text-sm text-gray-500">Semua notifikasi akun Anda.</p>
        </div>
        @if (($unreadCount ?? 0) > 0)
            <a href="{{ route('notifications.mark-all-read', ['redirect' => route('notifications.index')]) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">
                <i class="fas fa-check-double text-xs"></i>
                Tandai semua dibaca
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        @forelse ($notifications as $notification)
            @php $isUnread = (int) ($notification->is_read ?? 0) === 0; @endphp
            <a href="{{ route('notifications.read', ['id' => (int) $notification->rec_id]) }}" class="block border-b border-gray-100 px-5 py-4 transition hover:bg-gray-50 {{ $isUnread ? 'bg-blue-50/60' : 'bg-white' }}">
                <div class="flex gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $isUnread ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                        <i class="fas fa-user-check text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-bold text-gray-800">{{ $notification->title ?? 'Notifikasi' }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $isUnread ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">{{ $isUnread ? 'Baru' : 'Dibaca' }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-relaxed text-gray-500">{{ $notification->message ?? '' }}</p>
                        <p class="mt-2 text-xs font-medium text-gray-400">{{ !empty($notification->created_at) ? date('d M Y H:i', strtotime((string) $notification->created_at)) : '-' }}</p>
                    </div>
                </div>
            </a>
        @empty
            <div class="px-6 py-16 text-center text-gray-400">
                <i class="far fa-bell-slash mb-3 text-4xl"></i>
                <p class="text-sm font-medium">Belum ada notifikasi.</p>
            </div>
        @endforelse
    </div>

    @if (($totalPages ?? 1) > 1)
        <div class="flex items-center justify-between gap-3 text-sm">
            <a href="{{ route('notifications.index', ['page' => max(1, $page - 1)]) }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-600 hover:bg-gray-50 {{ $page <= 1 ? 'pointer-events-none opacity-50' : '' }}">Sebelumnya</a>
            <span class="text-gray-500">Halaman {{ $page }} dari {{ $totalPages }}</span>
            <a href="{{ route('notifications.index', ['page' => min($totalPages, $page + 1)]) }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-600 hover:bg-gray-50 {{ $page >= $totalPages ? 'pointer-events-none opacity-50' : '' }}">Berikutnya</a>
        </div>
    @endif
</div>
@endsection
