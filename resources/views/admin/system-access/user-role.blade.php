@extends('layouts.app')

@section('title', 'RUN-ITC | User Role Assignment')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / Access Control</div>
            <h1 class="text-2xl font-bold text-gray-900">User Role Assignment</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Assign atau ubah role untuk setiap user. Role menentukan menu apa saja yang bisa diakses user di sidebar.</p>
        </div>
        <a href="{{ url('/modules/admin/system_access/roles.php') }}" class="text-sm font-semibold text-gray-500 hover:text-brand-primary">
            <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="relative md:col-span-2">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search user, username, email..." class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
            <select name="role_id" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->rec_id }}" @selected((string) $filters['role_id'] === (string) $role->rec_id)>{{ $role->grpdesc ?: $role->grpacc }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>Filter
                </button>
                <a href="{{ route('admin.user-role.index') }}" class="flex items-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b p-5">
            <h2 class="text-lg font-bold text-gray-900">Users</h2>
            <p class="text-sm text-gray-500">Showing {{ count($users) }} of {{ $totalUsers }} users</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Current Role(s)</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-left font-bold">Assign Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-primary text-sm font-bold text-white">{{ strtoupper(substr($user->account_nm ?: 'U', 0, 1)) }}</div>
                                    <div>
                                        <div class="font-bold text-gray-900">{{ $user->account_nm }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->account_id }}</div>
                                        <div class="text-xs text-gray-400">{{ $user->email_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @forelse ($user->roles as $userRole)
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">{{ $userRole->grpdesc ?: $userRole->grpacc }}</span>
                                        <span class="text-xs text-gray-400">({{ $userRole->grpaccess }})</span>
                                        <form method="POST" action="{{ route('admin.user-role.remove') }}" class="inline" onsubmit="return confirm('Hapus role ini dari user?')">
                                            @csrf
                                            <input type="hidden" name="user_rec_id" value="{{ $user->rec_id }}">
                                            <input type="hidden" name="access_code" value="{{ $userRole->grpaccess }}">
                                            <input type="hidden" name="access_account" value="{{ $userRole->grpacc }}">
                                            <button type="submit" class="text-xs text-brand-primary hover:text-brand-primaryHover"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">No Role</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ (int) $user->status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ (int) $user->status === 1 ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <form method="POST" action="{{ route('admin.user-role.assign') }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="user_rec_id" value="{{ $user->rec_id }}">
                                    <select name="grpacc_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200" required>
                                        <option value="">-- Pilih Role --</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->rec_id }}">{{ $role->grpdesc ?: $role->grpacc }} ({{ $role->grpaccess }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="shrink-0 rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center italic text-gray-400">Tidak ada data user ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalPages > 1)
            <div class="flex flex-col gap-3 border-t p-5 md:flex-row md:items-center md:justify-between">
                <div class="text-sm text-gray-500">Page {{ $page }} of {{ $totalPages }} ({{ $totalUsers }} users)</div>
                <div class="flex items-center gap-2">
                    @for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}" class="rounded-lg px-3 py-2 text-sm {{ $p === $page ? 'bg-brand-primary text-white' : 'border border-brand-primary bg-brand-primary text-white hover:bg-brand-primaryHover' }}">{{ $p }}</a>
                    @endfor
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
