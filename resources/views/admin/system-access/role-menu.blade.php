@extends('layouts.app')

@section('title', 'RUN-ITC | Role to Menu Permission')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / Permission</div>
            <h1 class="text-2xl font-bold text-gray-900">Role to Menu Permission</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Tentukan menu apa saja yang boleh muncul dan diakses oleh role tertentu.</p>
        </div>
        <a href="{{ url('/modules/admin/system_access/roles.php') }}" class="text-sm font-semibold text-gray-500 hover:text-brand-primary">
            <i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <div class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-bold text-gray-900">Roles</h2>
                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-bold text-gray-500">{{ count($roles) }} roles</span>
            </div>
            <div class="relative mb-4">
                <input type="text" id="roleSearchInput" onkeyup="filterRoleList()" placeholder="Search role..." class="w-full rounded-xl border border-gray-300 py-2.5 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
            </div>
            <div class="space-y-2" id="roleListContainer">
                @foreach ($roles as $role)
                    @php $isActive = (int) $role->rec_id === (int) $selectedRoleId; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['role_id' => $role->rec_id]) }}" class="role-item block rounded-xl px-4 py-3 text-left transition {{ $isActive ? 'bg-brand-primary text-white' : 'bg-brand-primary/10 text-brand-primary hover:bg-brand-primary/20' }}" data-name="{{ strtolower($role->grpdesc ?: $role->grpacc) }}">
                        <div class="font-bold">{{ $role->grpdesc ?: $role->grpacc }}</div>
                        <div class="text-xs {{ $isActive ? 'text-blue-100' : 'text-brand-primary/70' }}">Code: {{ $role->grpaccess }}/{{ $role->grpacc }} &middot; {{ $role->menu_count }} menus</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm lg:col-span-3">
            <form method="POST" action="{{ route('admin.role-menu.save') }}" id="menuAccessForm">
                @csrf
                <input type="hidden" name="grpacc_id" value="{{ $selectedRoleId }}">
                <div class="flex flex-col gap-3 border-b p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="font-bold text-gray-900">Menu Permission</h2>
                        <p class="text-sm text-gray-500">Currently editing: <span class="font-bold text-slate-900">{{ $selectedRole->grpdesc ?? 'Pilih role' }}</span></p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" onclick="checkAllMenus(true)" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">Select All</button>
                        <button type="button" onclick="checkAllMenus(false)" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">Clear</button>
                        @if ($selectedRole)
                            <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fa-solid fa-floppy-disk mr-2"></i>Save Access</button>
                        @endif
                    </div>
                </div>

                <div class="p-5">
                    @if (! $selectedRole)
                        <div class="py-10 text-center text-gray-400"><i class="fa-solid fa-hand-pointer mb-3 text-4xl"></i><p>Pilih role di sidebar untuk mengatur akses menu.</p></div>
                    @else
                        @if (! empty($adminMenuIds))
                            <div class="mb-4 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800"><strong>Superadmin:</strong> Menu admin bersifat protected dan otomatis tetap tersimpan.</div>
                        @else
                            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800"><strong>Catatan:</strong> Jika child menu dicentang, parent menu akan otomatis ikut tersimpan agar sidebar tetap muncul.</div>
                        @endif

                        <div class="space-y-4">
                            @include('admin.system-access.partials.menu-tree', ['menus' => $menuTree, 'checkedMenuIds' => $checkedMenuIds, 'adminMenuIds' => $adminMenuIds, 'level' => 0])
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function checkAllMenus(isChecked) {
    document.querySelectorAll('.menu-checkbox:not(:disabled)').forEach(cb => cb.checked = isChecked);
}

function toggleGroup(group, isChecked) {
    document.querySelectorAll('[data-parent-group="' + group + '"]:not(:disabled)').forEach(cb => cb.checked = isChecked);
}

function filterRoleList() {
    const keyword = document.getElementById('roleSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.role-item').forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(keyword) ? '' : 'none';
    });
}
</script>
@endsection
