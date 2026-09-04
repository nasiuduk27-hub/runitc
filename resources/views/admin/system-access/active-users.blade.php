@extends('layouts.app')

@section('title', 'RUN-ITC | Active User List')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / User Access</div>
            <h1 class="text-2xl font-bold text-gray-900">Active User List</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Halaman ini digunakan untuk melihat user aktif, role yang digunakan, status akun, dan akses yang terhubung ke sistem.</p>
        </div>
        <a href="{{ route('admin.user-role.index') }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-brand-primaryHover">
            <i class="fa-solid fa-user-gear mr-2"></i>Manage User Role
        </a>
    </div>

    @php
        $cards = [
            ['label' => 'Total Users', 'value' => $summary['totalAll'], 'note' => 'Registered accounts', 'icon' => 'fa-users', 'badge' => 'All'],
            ['label' => 'Active Users', 'value' => $summary['totalActive'], 'note' => 'Status akun aktif', 'icon' => 'fa-user-check', 'badge' => $summary['totalAll'] > 0 ? round(($summary['totalActive'] / $summary['totalAll']) * 100).'%' : '0%'],
            ['label' => 'Inactive Users', 'value' => $summary['totalInactive'], 'note' => 'Temporarily disabled', 'icon' => 'fa-user-slash', 'badge' => $summary['totalInactive'] > 0 ? 'Need review' : 'OK'],
            ['label' => 'Unassigned Role', 'value' => $summary['totalUnassigned'], 'note' => 'Belum punya role', 'icon' => 'fa-user-clock', 'badge' => $summary['totalUnassigned'] > 0 ? 'Action needed' : 'OK'],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-2 flex items-center gap-2">
                            <h2 class="text-2xl font-bold text-gray-900">{{ $card['value'] }}</h2>
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-bold text-gray-500">{{ $card['badge'] }}</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">{{ $card['note'] }}</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-700">
                        <i class="fa-solid {{ $card['icon'] }} text-lg"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Users</h2>
                    <p class="mt-1 text-sm text-gray-500">Filter user berdasarkan role, status, dan keyword pencarian.</p>
                </div>
                <a href="{{ route('admin.roles.index') }}" class="text-sm font-semibold text-gray-500 hover:text-brand-primary">
                    <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
                </a>
            </div>

            <form method="GET" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="relative">
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search user, email, role..." class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
                </div>
                <select name="role_id" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->rec_id }}" @selected((string) $filters['role_id'] === (string) $role->rec_id)>{{ $role->grpdesc ?: $role->grpacc }}</option>
                    @endforeach
                </select>
                <select name="status" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="" @selected($filters['status'] === '')>All Status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">Filter</button>
                    <a href="{{ route('admin.active-users.index') }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role</th>
                        <th class="px-5 py-3 text-left font-bold">Company</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($users as $user)
                        @php $isActive = (int) $user->status === 1; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-primary text-sm font-bold text-white">{{ strtoupper(substr($user->account_nm ?: 'U', 0, 1)) }}</div>
                                    <div>
                                        <div class="font-bold text-gray-900">{{ $user->account_nm }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->email_id }}</div>
                                        <div class="mt-0.5 text-[11px] text-gray-400">{{ $user->account_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @forelse ($user->roles as $role)
                                    <span class="mb-1 mr-1 inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">{{ $role->grpdesc ?: $role->grpacc }}</span>
                                @empty
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">Unassigned</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-4 text-gray-500">{{ $user->cmpcd ?: '-' }}</td>
                            <td class="px-5 py-4 text-center">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('admin.active-users.status') }}" class="inline" onsubmit="return confirm('Ubah status user {{ addslashes($user->account_nm) }}?')">
                                    @csrf
                                    <input type="hidden" name="user_rec_id" value="{{ $user->rec_id }}">
                                    <input type="hidden" name="status" value="{{ $isActive ? 0 : 1 }}">
                                    <button type="submit" class="text-brand-primary hover:text-brand-primaryHover" title="{{ $isActive ? 'Deactivate user' : 'Activate user' }}">
                                        <i class="fa-solid {{ $isActive ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center italic text-gray-400">Tidak ada user ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t p-5 md:flex-row md:items-center md:justify-between">
            <div class="text-sm text-gray-500">Showing {{ count($users) }} of {{ $totalUsers }} users</div>
            @if ($totalPages > 1)
                <div class="flex items-center gap-2">
                    @for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}" class="rounded-lg px-3 py-2 text-sm {{ $p === $page ? 'bg-brand-primary text-white' : 'border border-brand-primary bg-brand-primary text-white hover:bg-brand-primaryHover' }}">{{ $p }}</a>
                    @endfor
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
