@extends('layouts.app')

@section('title', 'RUN-ITC | Role Management')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / Access Control</div>
            <h1 class="text-2xl font-bold text-gray-900">Roles List</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Role digunakan untuk memberikan akses menu dan fitur tertentu kepada user.</p>
        </div>
        <button type="button" onclick="openRoleModal()" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primaryHover">
            <i class="fa-solid fa-plus mr-2"></i>Add New Role
        </button>
    </div>

    <div id="rolesSliderWrap" class="relative">
        <button id="rolesPrevButton" type="button" onclick="scrollRoles('left')" class="pointer-events-none absolute -left-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-brand-primary bg-brand-primary text-white opacity-0 shadow-md transition hover:bg-brand-primaryHover md:flex">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div id="rolesSlider" class="flex gap-5 overflow-x-auto pb-4 snap-x snap-mandatory scroll-smooth" style="scrollbar-width: none;">
        @forelse ($roles as $role)
            @php
                $isSuperadmin = ($role->grpaccess === '03' && $role->grpacc === '999' && str_contains(strtoupper($role->grpdesc ?? ''), 'SUPER') && str_contains(strtoupper($role->grpdesc ?? ''), 'ADMIN'));
                $canDelete = (int) $role->user_count === 0 && (int) $role->menu_count === 0 && ! $isSuperadmin;
            @endphp
            <div class="min-w-[300px] snap-start rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:min-w-[360px] lg:min-w-[420px]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500">{{ $role->user_count }} users &middot; {{ $role->menu_count }} menus</div>
                        <h2 class="mt-2 text-base font-bold text-gray-900">{{ $role->grpdesc ?: $role->grpacc }}</h2>
                    </div>
                    <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-600">{{ $role->grpaccess }}/{{ $role->grpacc }}</span>
                </div>
                @if ($isSuperadmin)
                    <span class="mt-3 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Superadmin</span>
                @endif
                <div class="mt-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="openRoleModal('{{ $role->rec_id }}', '{{ addslashes($role->grpdesc ?? '') }}')" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Edit Role</button>
                        @if ($canDelete)
                            <form method="POST" action="{{ route('admin.roles.destroy') }}" onsubmit="return confirm('Yakin ingin menghapus role ini?')">
                                @csrf
                                <input type="hidden" name="role_id" value="{{ $role->rec_id }}">
                                <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-600">Delete</button>
                            </form>
                        @else
                            <span class="text-xs font-semibold text-gray-400">Delete</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.role-menu.index', ['role_id' => $role->rec_id]) }}" class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-primary text-white hover:bg-brand-primaryHover" title="Manage menu access">
                        <i class="fa-solid fa-list-check text-sm"></i>
                    </a>
                </div>
            </div>
        @empty
            <div class="min-w-[300px] rounded-2xl border border-gray-200 bg-white p-8 text-center text-gray-400">Belum ada role.</div>
        @endforelse
        </div>

        <button id="rolesNextButton" type="button" onclick="scrollRoles('right')" class="pointer-events-none absolute -right-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-brand-primary bg-brand-primary text-white opacity-0 shadow-md transition hover:bg-brand-primaryHover md:flex">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b bg-gray-50/50 p-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Total users with their roles</h2>
                <p class="text-sm text-gray-500">Daftar ringkas user dan role yang sedang digunakan.</p>
            </div>
            <a href="{{ route('admin.user-role.index') }}" class="text-sm font-semibold text-brand-primary hover:text-brand-primaryHover">
                <i class="fa-solid fa-user-gear mr-1"></i>Manage User Roles
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role</th>
                        <th class="px-5 py-3 text-center font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900">{{ $user->account_nm }}</div>
                                <div class="text-xs text-gray-500">{{ $user->email_id ?: $user->account_id }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @forelse ($user->roles as $role)
                                    <span class="mb-1 mr-1 inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">{{ $role->grpdesc ?: $role->grpacc }}</span>
                                @empty
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">No Role</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ (int) $user->status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ (int) $user->status === 1 ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center italic text-gray-400">Belum ada data user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="roleModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <form method="POST" id="roleForm" action="{{ route('admin.roles.store') }}">
            @csrf
            <input type="hidden" name="role_id" id="roleId">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900" id="roleModalTitle">Add New Role</h2>
                <div id="codeFields" class="mt-5 grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Access Code</label>
                        <input type="text" name="grpaccess" id="grpaccess" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Access Account</label>
                        <input type="text" name="grpacc" id="grpacc" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="text-xs font-semibold text-gray-500">Role Description</label>
                    <input type="text" name="grpdesc" id="grpdesc" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
                <button type="button" onclick="closeRoleModal()" class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Batal</button>
                <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(roleId = '', roleDesc = '') {
    const modal = document.getElementById('roleModal');
    const form = document.getElementById('roleForm');
    const codeFields = document.getElementById('codeFields');
    document.getElementById('roleId').value = roleId;
    document.getElementById('grpdesc').value = roleDesc;

    if (roleId) {
        document.getElementById('roleModalTitle').textContent = 'Edit Role';
        form.action = '{{ route('admin.roles.update') }}';
        codeFields.classList.add('hidden');
        document.getElementById('grpaccess').required = false;
        document.getElementById('grpacc').required = false;
    } else {
        document.getElementById('roleModalTitle').textContent = 'Add New Role';
        form.action = '{{ route('admin.roles.store') }}';
        codeFields.classList.remove('hidden');
        document.getElementById('grpaccess').required = true;
        document.getElementById('grpacc').required = true;
        document.getElementById('grpaccess').value = '';
        document.getElementById('grpacc').value = '';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRoleModal() {
    const modal = document.getElementById('roleModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function scrollRoles(direction) {
    const slider = document.getElementById('rolesSlider');
    if (!slider) return;

    const distance = Math.max(320, slider.clientWidth * 0.8);
    slider.scrollBy({ left: direction === 'left' ? -distance : distance, behavior: 'smooth' });

    window.setTimeout(updateRoleSliderButtons, 300);
}

function updateRoleSliderButtons() {
    const wrap = document.getElementById('rolesSliderWrap');
    const slider = document.getElementById('rolesSlider');
    const prev = document.getElementById('rolesPrevButton');
    const next = document.getElementById('rolesNextButton');
    if (!wrap || !slider || !prev || !next) return;

    const isHovered = wrap.matches(':hover');
    const canScrollLeft = slider.scrollLeft > 2;
    const canScrollRight = slider.scrollLeft + slider.clientWidth < slider.scrollWidth - 2;

    toggleRoleSliderButton(prev, isHovered && canScrollLeft);
    toggleRoleSliderButton(next, isHovered && canScrollRight);
}

function toggleRoleSliderButton(button, show) {
    button.classList.toggle('opacity-0', !show);
    button.classList.toggle('pointer-events-none', !show);
}

document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('rolesSliderWrap');
    const slider = document.getElementById('rolesSlider');
    if (!wrap || !slider) return;

    wrap.addEventListener('mouseenter', updateRoleSliderButtons);
    wrap.addEventListener('mouseleave', updateRoleSliderButtons);
    slider.addEventListener('scroll', updateRoleSliderButtons);
    window.addEventListener('resize', updateRoleSliderButtons);
    updateRoleSliderButtons();
});
</script>
@endsection
