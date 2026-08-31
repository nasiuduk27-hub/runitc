@extends('layouts.app')

@section('title', 'RUN-ITC | Permission Overview')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / Permission</div>
            <h1 class="text-2xl font-bold text-gray-900">Permission Overview</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500">Daftar semua menu di sistem beserta role yang memiliki akses. Permission dikelola melalui akses menu per role.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ url('/modules/admin/system_access/role_menu.php') }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primaryHover"><i class="fa-solid fa-list-check mr-2"></i>Manage Menu Access</a>
            <a href="{{ url('/modules/admin/system_access/roles.php') }}" class="flex items-center px-4 text-sm font-semibold text-gray-500 hover:text-brand-primary"><i class="fa-solid fa-arrow-left mr-1"></i>Back to Roles</a>
        </div>
    </div>

    <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800"><strong><i class="fa-solid fa-circle-info mr-1"></i>Info:</strong> Permission = akses ke menu tertentu melalui role. Untuk mengubah akses, gunakan halaman <a href="{{ url('/modules/admin/system_access/role_menu.php') }}" class="font-bold underline">Role to Menu Permission</a>.</div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-bold text-gray-900">Active Roles</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($roles as $role)
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">{{ $role->grpdesc ?: $role->grpacc }} <span class="ml-1 text-gray-400">({{ $role->menu_count }} menus)</span></span>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b p-5 md:flex-row md:items-center md:justify-between">
            <div><h2 class="text-lg font-bold text-gray-900">Menu Access Matrix</h2><p class="text-sm text-gray-500">Daftar menu dan role yang memiliki akses.</p></div>
            <div class="relative w-full md:w-80"><input type="text" id="permSearchInput" onkeyup="filterPermissions()" placeholder="Search menu..." class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200"><i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600"><tr><th class="w-8 px-5 py-3 text-left font-bold">#</th><th class="px-5 py-3 text-left font-bold">Menu</th><th class="px-5 py-3 text-left font-bold">URL</th><th class="px-5 py-3 text-center font-bold">Global</th><th class="px-5 py-3 text-left font-bold">Accessible by Roles</th></tr></thead>
                <tbody class="divide-y">
                    @forelse ($permissions as $idx => $menu)
                        @php
                            $isParent = (int) $menu->mst_id === 0;
                            $isGlobal = (int) $menu->is_global === 1;
                            $roleNames = (string) ($menu->role_names ?? '');
                        @endphp
                        <tr class="perm-row hover:bg-gray-50" data-search="{{ strtolower($menu->title.' '.($menu->url ?? '').' '.$roleNames) }}">
                            <td class="px-5 py-3 text-xs text-gray-400">{{ $idx + 1 }}</td>
                            <td class="px-5 py-3 {{ $isParent ? '' : 'pl-10' }}">@if($isParent)<span class="font-bold text-gray-900"><i class="fa-solid fa-folder mr-2 text-yellow-500"></i>{{ $menu->title }}</span>@else<span class="font-semibold text-gray-700"><i class="fa-regular fa-file mr-2 text-gray-400"></i>{{ $menu->title }}</span>@endif</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $menu->url ?: '#' }}</td>
                            <td class="px-5 py-3 text-center">@if($isGlobal)<span class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700">Yes</span>@else<span class="text-gray-300">-</span>@endif</td>
                            <td class="px-5 py-3">@if($isGlobal)<span class="text-xs font-semibold text-green-600">All Users</span>@elseif($roleNames !== '')@foreach(explode(', ', $roleNames) as $roleName)<span class="mb-1 mr-1 inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-700">{{ $roleName }}</span>@endforeach @else <span class="text-xs italic text-red-400">No access assigned</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center italic text-gray-400">Belum ada data menu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterPermissions() {
    const keyword = document.getElementById('permSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.perm-row').forEach(row => {
        const data = row.dataset.search || '';
        row.style.display = keyword === '' || data.includes(keyword) ? '' : 'none';
    });
}
</script>
@endsection
