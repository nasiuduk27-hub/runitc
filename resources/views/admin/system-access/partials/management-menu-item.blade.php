@php
    $id = (int) $menu['rec_id'];
    $isActive = (int) ($menu['is_active'] ?? 1) === 1;
    $isGlobal = (int) ($menu['is_global'] ?? 0) === 1;
    $isProtected = in_array($id, $adminMenuIds, true);
    $hasChildren = ! empty($menu['children']);
    $indent = $level * 24;
@endphp

<li class="menu-item rounded-xl border border-transparent px-4 py-3 transition hover:border-gray-200 hover:bg-gray-50" data-menu-id="{{ $id }}" data-name="{{ strtolower($menu['title'] ?? '') }}">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3" style="margin-left: {{ $indent }}px">
            <button type="button" class="drag-handle cursor-grab rounded-md px-1.5 py-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-500 active:cursor-grabbing" title="Geser posisi menu">
                <i class="fa-solid fa-grip-vertical"></i>
            </button>
            @if ($level === 0)
                <i class="fa-solid fa-folder text-yellow-500"></i>
            @elseif ($level === 1)
                <i class="fa-regular fa-file-lines text-blue-500"></i>
            @else
                <i class="fa-solid fa-ellipsis-h text-gray-400"></i>
            @endif
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-900">{{ $menu['title'] }}</span>
                    @if (! $isActive)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-500">Inactive</span>@endif
                    @if ($isGlobal)<span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">Global</span>@endif
                    @if ($isProtected)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Protected</span>@endif
                </div>
                <div class="font-mono text-xs text-gray-500">{{ $menu['url'] ?? '#' }}</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick='openEditModal(@json($menu))' class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200">
                <i class="fa-solid fa-pen mr-1"></i>Edit
            </button>
            @if (! $isProtected)
                <form method="POST" action="{{ route('admin.menu-management.destroy') }}" onsubmit="return confirm('Yakin ingin menghapus menu {{ addslashes($menu['title'] ?? '') }}?')">
                    @csrf
                    <input type="hidden" name="rec_id" value="{{ $id }}">
                    <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                        <i class="fa-solid fa-trash mr-1"></i>Hapus
                    </button>
                </form>
            @else
                <span class="cursor-not-allowed rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-400">Hapus</span>
            @endif
        </div>
    </div>
    <ol class="menu-sortable mt-2 min-h-[10px] space-y-2" data-parent-id="{{ $id }}">
        @foreach ($menu['children'] ?? [] as $child)
            @include('admin.system-access.partials.management-menu-item', ['menu' => $child, 'adminMenuIds' => $adminMenuIds, 'level' => $level + 1])
        @endforeach
    </ol>
</li>
