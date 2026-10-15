@php
    $currentFolder = $folder ?? \App\Support\FilingSystem\FilingFolders::defaultKey();
    $folderDefs = \App\Support\FilingSystem\FilingFolders::driveDefs();
    $trashDef = \App\Support\FilingSystem\FilingFolders::trashDef();
    $trashKey = \App\Support\FilingSystem\FilingFolders::TRASH;
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
@endphp

@if ($showUploadButton ?? false)
    <div class="mb-3 px-1">
        <button type="button" onclick="openUploadModal()" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
            <i class="fas fa-plus"></i> NEW
        </button>
    </div>
@endif

{{-- DRIVE --}}
<div class="mb-1">
    <p class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">Drive</p>
    <div class="space-y-0.5">
        @foreach ($folderDefs as $key => $def)
            @if (($def['gated'] ?? false) && ! config('runitc.filing_use_new_tables'))
                @continue
            @endif
            @php $isActive = $currentFolder === $key; @endphp
            <a href="{{ route('filing-system.index', $queryFor(['folder' => $key, 'page' => 1])) }}" title="{{ $def['title'] }}"
               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas {{ $def['icon'] }} w-5 text-center {{ $def['color'] }}"></i>
                <span class="flex-1 truncate">{{ $def['label'] }}</span>
                @if (($folderCounts[$key] ?? 0) > 0)
                    <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold {{ $isActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">{{ $folderCounts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

{{-- GRUP SAYA --}}
<div class="mb-1 mt-3">
    <div class="flex items-center justify-between px-3 pb-1 pt-1">
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Grup Saya</p>
        <a href="{{ route('filing-system.groups.create') }}" title="Buat Grup" class="text-gray-400 transition hover:text-blue-600"><i class="fas fa-plus text-[10px]"></i></a>
    </div>
    <div class="space-y-0.5">
        @forelse (($myGroups['groups'] ?? []) as $grp)
            @php $activeGroup = request()->routeIs('filing-system.groups.show') && (int) request()->route('group') === (int) $grp['rec_id']; @endphp
            <a href="{{ route('filing-system.groups.show', $grp['rec_id']) }}" title="{{ $grp['group_name'] }}"
               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $activeGroup ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-folder-tree w-5 text-center text-amber-500"></i>
                <span class="flex-1 truncate">{{ $grp['group_name'] }}</span>
                @if ((int) $grp['file_count'] > 0)
                    <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold {{ $activeGroup ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">{{ $grp['file_count'] }}</span>
                @endif
            </a>
        @empty
            <p class="px-3 py-1 text-[11px] text-gray-400">Belum ada grup.</p>
        @endforelse
        @if (($myGroups['more'] ?? false) === true)
            <a href="{{ route('filing-system.groups.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50">
                <i class="fas fa-ellipsis-h w-5 text-center"></i>
                <span class="flex-1 truncate">Lihat semua grup</span>
            </a>
        @endif
    </div>
</div>

{{-- ALAT --}}
<div class="mb-1 mt-3">
    <p class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">Alat</p>
    <div class="space-y-0.5">
        @php $shareActive = request()->routeIs('filing-system.share-access'); @endphp
        <a href="{{ route('filing-system.share-access') }}"
           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $shareActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-key w-5 text-center text-green-600"></i>
            <span class="flex-1 truncate">Akses Share Code</span>
        </a>
        @php $requestsActive = request()->routeIs('filing-system.requests.*'); @endphp
        <a href="{{ route('filing-system.requests.index') }}"
           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $requestsActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-inbox w-5 text-center text-indigo-500"></i>
            <span class="flex-1 truncate">Permintaan Akses</span>
            @if (($pendingAccessRequestCount ?? 0) > 0)
                <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold {{ $requestsActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">{{ $pendingAccessRequestCount }}</span>
            @endif
        </a>
    </div>
</div>

{{-- ADMIN --}}
@if (($isFilingAdmin ?? false) === true)
    <div class="mb-1 mt-3">
        <p class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">Admin</p>
        <div class="space-y-0.5">
            @php $adminActive = request()->routeIs('filing-system.admin'); @endphp
            <a href="{{ route('filing-system.admin') }}"
               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $adminActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-shield-alt w-5 text-center text-red-600"></i>
                <span class="flex-1 truncate">Admin Filing System</span>
            </a>
            @php $fileTypesActive = request()->routeIs('filing-system.admin.file-types.*'); @endphp
            <a href="{{ route('filing-system.admin.file-types.index') }}"
               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $fileTypesActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-tags w-5 text-center text-amber-500"></i>
                <span class="flex-1 truncate">Kelola Tipe File</span>
            </a>
        </div>
    </div>
@endif

{{-- SAMPAH --}}
<div class="mt-2 border-t border-gray-100 pt-2">
    @php $trashActive = $currentFolder === $trashKey; @endphp
    <a href="{{ route('filing-system.index', $queryFor(['folder' => $trashKey, 'page' => 1])) }}"
       class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $trashActive ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-100' }}">
        <i class="fas {{ $trashDef['icon'] }} w-5 text-center {{ $trashActive ? 'text-red-500' : $trashDef['color'] }}"></i>
        <span class="flex-1 truncate">{{ $trashDef['label'] }}</span>
        @if (($folderCounts[$trashKey] ?? 0) > 0)
            <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold {{ $trashActive ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500' }}">{{ $folderCounts[$trashKey] }}</span>
        @endif
    </a>
</div>

<div class="absolute inset-x-0 bottom-0 border-t border-gray-100 px-4 py-3">
    <div class="flex items-center gap-2">
        <img src="{{ $layoutPhotoUrl ?? asset('assets/personal/nopicture.png') }}" alt="Profile" class="h-8 w-8 rounded-full object-cover">
        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-bold text-gray-700">{{ $layoutUserName ?? 'User' }}</p>
            <p class="truncate text-[10px] text-gray-400">{{ $layoutRoleDivision ?? 'Role belum diatur' }}</p>
        </div>
    </div>
</div>
