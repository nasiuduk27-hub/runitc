@foreach ($menus as $menu)
    @php
        $menuId = (int) $menu['rec_id'];
        $isChecked = in_array($menuId, $checkedMenuIds, true);
        $hasChildren = ! empty($menu['children']);
        $isGlobal = (int) ($menu['is_global'] ?? 0) === 1;
        $isProtected = in_array($menuId, $adminMenuIds, true);
    @endphp

    @if ($level === 0)
        <div class="overflow-hidden rounded-xl border">
            <label class="flex items-center gap-3 bg-gray-50 px-5 py-3 {{ $isProtected ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                <input type="checkbox" name="menu_ids[]" value="{{ $menuId }}" class="menu-checkbox parent-cb h-4 w-4" @checked($isChecked || $isProtected) @disabled($isProtected) data-group="grp-{{ $menuId }}" onchange="toggleGroup('grp-{{ $menuId }}', this.checked)">
                <span class="font-bold text-gray-900"><i class="fa-solid fa-folder mr-2 text-yellow-500"></i>{{ $menu['title'] }}</span>
                @if ($isGlobal)<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700">Global</span>@endif
                @if ($isProtected)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">Protected</span>@endif
            </label>
            @if ($hasChildren)
                <div class="divide-y">
                    @include('admin.system-access.partials.menu-tree', ['menus' => $menu['children'], 'checkedMenuIds' => $checkedMenuIds, 'adminMenuIds' => $adminMenuIds, 'level' => $level + 1])
                </div>
            @endif
        </div>
    @else
        <label class="flex items-center justify-between px-9 py-4 {{ $isProtected ? 'cursor-not-allowed bg-amber-50' : 'cursor-pointer hover:bg-gray-50' }}">
            <div>
                <div class="font-semibold text-gray-800">{{ $menu['title'] }}</div>
                <div class="text-xs text-gray-500">{{ $menu['url'] ?? '#' }}</div>
            </div>
            <div class="flex items-center gap-2">
                @if ($isGlobal)<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700">Global</span>@endif
                @if ($isProtected)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">Protected</span>@endif
                <input type="checkbox" name="menu_ids[]" value="{{ $menuId }}" class="menu-checkbox child-cb h-4 w-4" @checked($isChecked || $isProtected) @disabled($isProtected) data-parent-group="grp-{{ (int) $menu['mst_id'] }}">
            </div>
        </label>
        @if ($hasChildren)
            <div class="divide-y border-t">
                @include('admin.system-access.partials.menu-tree', ['menus' => $menu['children'], 'checkedMenuIds' => $checkedMenuIds, 'adminMenuIds' => $adminMenuIds, 'level' => $level + 1])
            </div>
        @endif
    @endif
@endforeach
