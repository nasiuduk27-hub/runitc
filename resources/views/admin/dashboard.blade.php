@extends('layouts.app')

@section('title', 'RUN-ITC | Admin Dashboard')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Superadmin Overview</h1>
        <p class="mt-1 text-sm text-gray-500">Kondisi user, akses, dan operasional sistem RUNITC.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-green-500 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Users</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['total_users']) }}</div><div class="mt-1 text-xs text-gray-400">{{ number_format($stats['active_users']) }} aktif &middot; {{ number_format($stats['inactive_users']) }} nonaktif</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-amber-400 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Users Without Role</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['users_without_role']) }}</div><div class="mt-1 text-xs text-gray-400">Perlu ditinjau superadmin</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Roles</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['total_roles']) }}</div><div class="mt-1 text-xs text-gray-400">Role akses terdaftar</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-indigo-400 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active Menus</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['active_menus']) }}</div><div class="mt-1 text-xs text-gray-400">Menu aktif di sidebar</div></div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-green-500 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Peserta CBT Hari Ini</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($cbt['participants']) }}</div><div class="mt-1 text-xs text-gray-400">{{ number_format($cbt['assigned']) }} assigned &middot; {{ number_format($cbt['unassigned']) }} unassigned</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Room Aktif</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ max(0, $cbt['rooms'] - $cbt['completed_rooms']) }} / {{ $cbt['rooms'] }}</div><div class="mt-1 text-xs text-gray-400">{{ $cbt['completed_rooms'] }} room selesai hari ini</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-amber-400 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Filing Upload</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format((int) ($filing['total_uploads'] ?? 0)) }}</div><div class="mt-1 text-xs text-gray-400">{{ number_format((int) ($filing['today_uploads'] ?? 0)) }} upload hari ini</div></div>
        <div class="rounded-2xl border border-gray-200 border-l-4 border-l-red-400 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Health Check</div><div class="mt-1 text-2xl font-bold text-gray-900">{{ collect($health)->where('status', 'ok')->count() }} / {{ count($health) }}</div><div class="mt-1 text-xs text-gray-400">Koneksi database operational</div></div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Role & Security Access</div>
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Manage Roles</a>
            <a href="{{ route('admin.user-role.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">User Role Assignment</a>
            <a href="{{ route('admin.role-menu.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Menu Permission</a>
            <a href="{{ route('admin.menu-management.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Menu Management</a>
            <a href="{{ route('admin.active-users.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Active User List</a>
            <a href="{{ route('admin.audit-log.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Audit Log</a>
        </div>
        <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Admin Tools</div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reporting.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">Reporting</a>
            <a href="{{ route('admin.system-settings.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">System Settings</a>
            <a href="{{ route('admin.system-health.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200">System Health</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"><div class="border-b px-5 py-4"><h2 class="font-bold text-gray-900">System Health</h2></div><div class="divide-y">@foreach ($health as $name => $item)<div class="flex items-center justify-between px-5 py-3 text-sm"><span class="font-semibold text-gray-700">{{ $name }}</span><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $item['status'] === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $item['note'] }}</span></div>@endforeach</div></div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"><div class="border-b px-5 py-4"><h2 class="font-bold text-gray-900">Recent Activity</h2></div><div class="divide-y">@forelse ($activities as $activity)<div class="px-5 py-3 text-sm"><div class="font-semibold text-gray-800">{{ $activity->action }}</div><div class="text-xs text-gray-500">{{ $activity->account_nm ?: 'System / Unknown' }} &middot; {{ $activity->target_type }}{{ $activity->target_id ? '#'.$activity->target_id : '' }} &middot; {{ $activity->created_at }}</div></div>@empty<div class="px-5 py-8 text-center text-sm text-gray-400">Belum ada aktivitas.</div>@endforelse</div></div>
    </div>
</div>
@endsection
