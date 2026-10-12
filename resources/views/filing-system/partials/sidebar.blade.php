@php
    $currentFolder = $folder ?? 'my_drive';
    $folderDefs = [
        'my_drive' => ['label' => 'My Drive', 'title' => 'My Drive', 'icon' => 'fa-folder-open', 'color' => 'text-blue-600'],
        'shared' => ['label' => 'Shared', 'title' => 'Share With Me', 'icon' => 'fa-share-alt', 'color' => 'text-green-600'],
        'department' => ['label' => 'Divisi', 'title' => 'Divisi / Departemen', 'icon' => 'fa-building', 'color' => 'text-indigo-600'],
        'company' => ['label' => 'Company', 'title' => 'Company / Staff', 'icon' => 'fa-users', 'color' => 'text-purple-600'],
    ];
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

{{-- DOKUMEN TES --}}
<div class="mb-1 mt-3">
    <p class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">Dokumen Tes</p>
    <div class="space-y-0.5">
        @php $beritaActive = request()->routeIs('filing-system.berita-acara'); @endphp
        <a href="{{ route('filing-system.berita-acara') }}"
           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $beritaActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-file-alt w-5 text-center text-indigo-600"></i>
            <span class="flex-1 truncate">Berita Acara</span>
        </a>
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
        @php $auditActive = request()->routeIs('filing-system.audit'); @endphp
        <a href="{{ route('filing-system.audit') }}"
           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $auditActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-history w-5 text-center text-indigo-600"></i>
            <span class="flex-1 truncate">Audit Log</span>
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
        </div>
    </div>
@endif

{{-- SAMPAH --}}
<div class="mt-2 border-t border-gray-100 pt-2">
    @php $trashActive = $currentFolder === 'trash'; @endphp
    <a href="{{ route('filing-system.index', $queryFor(['folder' => 'trash', 'page' => 1])) }}"
       class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $trashActive ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-100' }}">
        <i class="fas fa-trash w-5 text-center {{ $trashActive ? 'text-red-500' : 'text-gray-400' }}"></i>
        <span class="flex-1 truncate">Sampah</span>
        @if (($folderCounts['trash'] ?? 0) > 0)
            <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold {{ $trashActive ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500' }}">{{ $folderCounts['trash'] }}</span>
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
