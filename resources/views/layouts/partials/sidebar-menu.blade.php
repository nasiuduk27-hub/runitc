@foreach ($items as $menu)
    @php
        $hasChildren = !empty($menu['children']);
        $menuId = 'menu_'.($menu['id'] ?? md5($menu['title'] ?? 'menu'));
        $isBranchActive = !empty($menu['is_branch_active']);
        $isExactActive = !empty($menu['is_exact_active']);
        $btnClass = $level === 1
            ? ($isBranchActive ? 'bg-brand-primary text-white font-semibold shadow-md shadow-brand-primary/30' : 'text-gray-600 hover:bg-gray-50 transition-colors')
            : ($isExactActive ? 'bg-brand-primary text-white font-semibold shadow-sm rounded-lg' : ($isBranchActive ? 'text-brand-primary font-medium bg-brand-primary/5 rounded-lg' : 'text-gray-500 hover:text-brand-primary hover:bg-gray-50 rounded-lg transition-colors'));
        $padClass = $level === 1 ? 'px-3 py-2.5 mb-1' : 'py-2.5 pr-3 pl-'.(5 + (($level - 1) * 2)).' w-full text-left mb-0.5';
        $iconClass = ($menu['icon'] ?? 'fas fa-cube').' w-5 text-center '.($level === 1 ? 'text-[15px]' : 'text-[14px]');
        $menuUrl = (string) ($menu['url'] ?? '#');
        $openInNewTab = str_contains($menuUrl, 'filing_system/main.php') || str_contains($menuUrl, '/filing-system');
        $targetAttr = $openInNewTab ? ' target="_blank" rel="noopener"' : '';
    @endphp

    <div>
        @if ($hasChildren)
            <button type="button" onclick="toggleDropdown('{{ $menuId }}')" class="menu-btn flex w-full items-center justify-between rounded-lg {{ $padClass }} {{ $btnClass }} transition-all duration-300" data-title="{{ $menu['title'] ?? 'Menu' }}">
                <div class="flex items-center gap-3 overflow-hidden">
                    <i class="{{ $iconClass }} shrink-0"></i>
                    <span class="sidebar-text whitespace-nowrap text-sm transition-opacity duration-300">{{ $menu['title'] ?? 'Menu' }}</span>
                </div>
                <i id="icon-{{ $menuId }}" class="sidebar-chevron fas fa-chevron-right ml-2 shrink-0 text-[10px] transition-transform duration-300 {{ $isBranchActive ? 'rotate-90' : '' }}"></i>
            </button>
            <div id="{{ $menuId }}" class="submenu {{ $isBranchActive ? '' : 'hidden' }} mb-1 mt-0.5 space-y-0.5 overflow-hidden transition-all duration-300">
                @include('layouts.partials.sidebar-menu', ['items' => $menu['children'], 'level' => $level + 1])
            </div>
        @else
            <a href="{{ $menu['url'] ?? '#' }}"{{ $targetAttr }} class="menu-btn group flex w-full items-center justify-between rounded-lg {{ $padClass }} {{ $btnClass }} transition-all duration-300" data-title="{{ $menu['title'] ?? 'Menu' }}">
                <div class="flex items-center gap-3 overflow-hidden">
                    <i class="{{ $iconClass }} shrink-0"></i>
                    <span class="sidebar-text whitespace-nowrap text-sm transition-opacity duration-300">{{ $menu['title'] ?? 'Menu' }}</span>
                </div>
            </a>
        @endif
    </div>
@endforeach
