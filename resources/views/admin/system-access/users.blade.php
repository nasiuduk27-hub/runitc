@extends('layouts.app')

@section('title', 'RUN-ITC | User Management')

@section('content')
@if (session('success_msg'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success_msg') }}</div>
@endif
@if (session('error_msg'))
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error_msg') }}</div>
@endif

<div class="space-y-6">
    <div class="flex flex-col gap-4">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / User Access</div>
            <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Kelola semua user, assign role, aktif/nonaktifkan akun, dan reset password.</p>
        </div>
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

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div class="relative md:col-span-2">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search name, username, email..." class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
            <select name="role_id" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->rec_id }}" @selected((string) $filters['role_id'] === (string) $role->rec_id)>{{ $role->grpdesc ?: $role->grpacc }}</option>
                @endforeach
            </select>
            <select name="status" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All Status</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>Filter
                </button>
                <a href="{{ route('admin.users.index') }}" class="flex items-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['unassigned' => 1, 'page' => null]) }}" class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold {{ $filters['unassigned'] ? 'bg-amber-500 text-white' : 'bg-brand-primary text-white hover:bg-brand-primaryHover' }}" title="Show unassigned only">
                    <i class="fa-solid fa-user-clock"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b p-5">
            <h2 class="text-lg font-bold text-gray-900">Users</h2>
            <p class="mt-1 text-sm text-gray-500">Showing {{ count($users) }} of {{ $totalUsers }} users</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role(s)</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                        <th class="px-5 py-3 text-center font-bold">Last Login</th>
                        <th class="px-5 py-3 text-center font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($users as $user)
                        @php
                            $isActive = (int) $user->status === 1;
                            $hasRoles = ! empty($user->roles);
                            $lastLogin = ! empty($user->lastlogin) && $user->lastlogin !== '0000-00-00 00:00:00'
                                ? date('d M Y H:i', strtotime($user->lastlogin))
                                : '-';
                        @endphp
                        <tr class="{{ $hasRoles ? '' : 'bg-red-50/30 ' }}hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-primary text-sm font-bold text-white">{{ strtoupper(substr($user->account_nm ?: 'U', 0, 1)) }}</div>
                                    <div>
                                        <div class="font-bold text-gray-900">{{ $user->account_nm }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->email_id ?: $user->account_id }}</div>
                                        <div class="mt-0.5 text-[11px] text-gray-400">{{ $user->account_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @forelse ($user->roles as $role)
                                    <span class="mb-1 mr-1 inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">{{ $role->grpdesc ?: $role->grpacc }}</span>
                                @empty
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">No Role</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-4 text-center font-mono text-xs text-gray-500">{{ $lastLogin }}</td>
                            <td class="px-5 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" onclick="openUserDetailModal({{ $user->rec_id }}, '{{ addslashes($user->account_nm) }}')" class="text-brand-primary hover:text-brand-primaryHover" title="Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    @if ($isActive)
                                        <button type="button" onclick="openStatusModal({{ $user->rec_id }}, '{{ addslashes($user->account_nm) }}', 0)" class="text-amber-500 hover:text-amber-600" title="Nonaktifkan">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </button>
                                    @else
                                        <button type="button" onclick="openStatusModal({{ $user->rec_id }}, '{{ addslashes($user->account_nm) }}', 1)" class="text-green-500 hover:text-green-600" title="Aktifkan">
                                            <i class="fa-solid fa-user-check"></i>
                                        </button>
                                    @endif
                                    <button type="button" onclick="openResetPasswordModal({{ $user->rec_id }}, '{{ addslashes($user->account_nm) }}')" class="text-brand-primary hover:text-brand-primaryHover" title="Reset Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <button type="button" onclick="openDeleteUserModal({{ $user->rec_id }}, '{{ addslashes($user->account_nm) }}')" class="text-red-500 hover:text-red-600" title="Hapus User">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center italic text-gray-400">Tidak ada user ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalPages > 1)
            <div class="flex flex-col gap-3 border-t p-5 md:flex-row md:items-center md:justify-between">
                <div class="text-sm text-gray-500">Page {{ $page }} of {{ $totalPages }} ({{ $totalUsers }} users)</div>
                <div class="flex items-center gap-2">
                    @if ($page > 1)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white hover:bg-brand-primaryHover">Previous</a>
                    @else
                        <span class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400">Previous</span>
                    @endif
                    @for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}" class="rounded-lg px-3 py-2 text-sm {{ $p === $page ? 'bg-brand-primary text-white' : 'bg-brand-primary text-white hover:bg-brand-primaryHover' }}">{{ $p }}</a>
                    @endfor
                    @if ($page < $totalPages)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white hover:bg-brand-primaryHover">Next</a>
                    @else
                        <span class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<!-- ============ User Status Modal ============ -->
<div id="userStatusModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <form method="POST" action="{{ route('admin.users.status') }}">
            @csrf
            <input type="hidden" name="user_rec_id" id="statusUserId">
            <input type="hidden" name="status" id="statusValue">
            <div class="p-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-orange-100 text-orange-700">
                    <i id="statusModalIcon" class="fa-solid fa-user-slash text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900" id="statusModalTitle">Nonaktifkan User?</h2>
                <p class="mt-2 text-sm text-gray-500">
                    Apakah kamu yakin ingin <span id="statusActionText" class="font-semibold">menonaktifkan</span>
                    user <span id="statusUserName" class="font-bold text-gray-900"></span>?
                </p>
                <p class="mt-3 text-xs text-gray-400">Data user tidak akan dihapus. Sistem hanya akan mengubah status akun.</p>
            </div>
            <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
                <button type="button" onclick="closeModal('userStatusModal')" class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Batal</button>
                <button type="submit" id="statusSubmitBtn" class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover">Ya, Nonaktifkan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Reset Password Modal ============ -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <form method="POST" action="{{ route('admin.users.reset-password') }}" onsubmit="return confirm('Yakin ingin mereset password user ini?')">
            @csrf
            <input type="hidden" name="user_rec_id" id="resetUserId">
            <div class="p-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <i class="fa-solid fa-key text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Reset Password</h2>
                <p class="mt-2 text-sm text-gray-500">
                    Password user <span id="resetUserName" class="font-bold text-gray-900"></span> akan di-reset ke password sementara.
                </p>
                <p class="mt-3 text-xs text-gray-400">Password baru akan ditampilkan setelah konfirmasi.</p>
            </div>
            <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
                <button type="button" onclick="closeModal('resetPasswordModal')" class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Batal</button>
                <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Ya, Reset</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Delete User Modal ============ -->
<div id="deleteUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <form method="POST" action="{{ route('admin.users.destroy') }}" onsubmit="return confirm('YAKIN ingin menghapus user ini secara permanen? Semua data role akan ikut terhapus.')">
            @csrf
            <input type="hidden" name="user_rec_id" id="deleteUserId">
            <div class="p-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-700">
                    <i class="fa-solid fa-trash-can text-xl"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Hapus User?</h2>
                <p class="mt-2 text-sm text-gray-500">
                    User <span id="deleteUserName" class="font-bold text-gray-900"></span> akan dihapus secara permanen beserta semua role assignments.
                </p>
                <p class="mt-3 text-xs font-semibold text-red-400">Aksi ini tidak bisa dibatalkan!</p>
            </div>
            <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
                <button type="button" onclick="closeModal('deleteUserModal')" class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Batal</button>
                <button type="submit" class="rounded-xl bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ User Detail Modal ============ -->
<div id="userDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-4 border-b p-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900" id="detailUserName">User Detail</h2>
                <p class="text-sm text-gray-500" id="detailUserAccount">Loading...</p>
            </div>
            <button type="button" onclick="closeModal('userDetailModal')" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="space-y-4 p-6" id="detailContent">
            <div class="py-4 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-xl"></i></div>
        </div>
    </div>
</div>
<script>
function openStatusModal(userId, userName, status) {
    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusUserName').textContent = userName;
    document.getElementById('statusValue').value = status;

    const title = document.getElementById('statusModalTitle');
    const actionText = document.getElementById('statusActionText');
    const submitBtn = document.getElementById('statusSubmitBtn');
    const icon = document.getElementById('statusModalIcon');

    if (status === 1) {
        title.textContent = 'Aktifkan User?';
        actionText.textContent = 'mengaktifkan';
        submitBtn.textContent = 'Ya, Aktifkan';
        icon.className = 'fa-solid fa-user-check text-xl';
    } else {
        title.textContent = 'Nonaktifkan User?';
        actionText.textContent = 'menonaktifkan';
        submitBtn.textContent = 'Ya, Nonaktifkan';
        icon.className = 'fa-solid fa-user-slash text-xl';
    }
    showModal('userStatusModal');
}

function openResetPasswordModal(userId, userName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    showModal('resetPasswordModal');
}

function openDeleteUserModal(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    showModal('deleteUserModal');
}

function openUserDetailModal(userId, userName) {
    document.getElementById('detailUserName').textContent = userName;
    document.getElementById('detailContent').innerHTML = '<div class="py-4 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-xl"></i><p class="mt-2">Memuat detail...</p></div>';
    showModal('userDetailModal');

    fetch('{{ route('admin.users.detail') }}?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                document.getElementById('detailContent').innerHTML = '<div class="py-4 text-center text-red-500">' + data.error + '</div>';
                return;
            }
            let rolesHtml = '';
            if (data.roles && data.roles.length > 0) {
                data.roles.forEach(r => {
                    rolesHtml += '<span class="mb-1 mr-1 inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">' + (r.grpdesc || '') + ' (' + (r.grpaccess || '') + '/' + (r.grpacc || '') + ')</span>';
                });
            } else {
                rolesHtml = '<span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">No Role</span>';
            }

            document.getElementById('detailUserAccount').textContent = (data.account_id || '') + ' \u00b7 ' + (data.email_id || '-');

            const statusHtml = data.status == 1
                ? '<span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">Active</span>'
                : '<span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-600">Inactive</span>';

            document.getElementById('detailContent').innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div><span class="block text-xs text-gray-400">Name</span><span class="font-semibold text-gray-900">${data.account_nm || '-'}</span></div>
                    <div><span class="block text-xs text-gray-400">Alias</span><span class="font-semibold text-gray-900">${data.alias_nm || '-'}</span></div>
                    <div><span class="block text-xs text-gray-400">Email</span><span class="text-gray-900">${data.email_id || '-'}</span></div>
                    <div><span class="block text-xs text-gray-400">Account ID</span><span class="font-mono text-gray-900">${data.account_id || '-'}</span></div>
                    <div><span class="block text-xs text-gray-400">Status</span><span class="text-sm">${statusHtml}</span></div>
                    <div><span class="block text-xs text-gray-400">Last Login</span><span class="font-mono text-gray-900">${data.lastlogin || '-'}</span></div>
                </div>
                <div class="border-t pt-4">
                    <span class="mb-2 block text-xs text-gray-400">Roles</span>
                    <div>${rolesHtml}</div>
                </div>
                <div class="border-t pt-4">
                    <span class="mb-2 block text-xs text-gray-400">Quick Actions</span>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.users.index') }}?search=${encodeURIComponent(data.account_nm || '')}" class="rounded-xl bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-primaryHover">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>Lihat di List
                        </a>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('detailContent').innerHTML = '<div class="py-4 text-center text-red-500">Gagal memuat detail user.</div>';
        });
}

function showModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}

document.addEventListener('click', function(e) {
    ['userStatusModal', 'resetPasswordModal', 'deleteUserModal', 'userDetailModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && modal.classList.contains('flex') && e.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });
});
</script>
@endsection