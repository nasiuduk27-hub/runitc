<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RUN-ITC')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/runitc.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6' } } } }
        };
    </script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        #sidebar { transition: width .3s cubic-bezier(.4,0,.2,1); width: 80px; overflow: hidden; }
        #sidebar:not(.minimized) { width: 260px; }
        #sidebar .sidebar-text, #sidebar .sidebar-chevron { opacity: 0; transition: opacity .2s; white-space: nowrap; }
        #sidebar:not(.minimized) .sidebar-text, #sidebar:not(.minimized) .sidebar-chevron { opacity: 1; }
        #sidebar.minimized .submenu, #sidebar.minimized .sidebar-section-label, #sidebar.minimized .sidebar-footer { display: none !important; }
        #sidebar .menu-container { overflow-x: hidden; scrollbar-width: none; -ms-overflow-style: none; }
        #sidebar .menu-container::-webkit-scrollbar { display: none; }
        .sidebar-tooltip { display: none; position: fixed; z-index: 99999; pointer-events: none; white-space: nowrap; border-radius: 6px; background: #1f2937; padding: 6px 12px; font-size: 12px; font-weight: 500; color: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1); }
        .sidebar-tooltip::before { content: ''; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #1f2937; }
        @media (max-width: 767px) {
            #sidebar { transform: translateX(-100%); width: 280px; max-width: 86vw; }
            #sidebar.mobile-open { transform: translateX(0); }
            #sidebar .sidebar-text, #sidebar .sidebar-chevron { opacity: 1; }
            #sidebar.minimized .submenu, #sidebar.minimized .sidebar-section-label, #sidebar.minimized .sidebar-footer { display: block !important; }
            body.sidebar-mobile-active { overflow: hidden; }
        }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-brand-bg font-sans text-gray-600 antialiased">
    <div id="global-loader" class="fixed inset-0 z-[99999] flex flex-col items-center justify-center bg-brand-bg transition-opacity duration-300">
        <div class="mb-3 flex h-8 items-center justify-center space-x-2">
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary" style="animation-delay: -0.3s;"></div>
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary" style="animation-delay: -0.15s;"></div>
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary"></div>
        </div>
        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Memuat...</span>
    </div>

    <header class="z-[70] flex h-[70px] w-full shrink-0 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6">
        <div class="flex items-center gap-3">
            <button id="mobileSidebarButton" type="button" onclick="toggleMobileSidebar()" class="h-10 w-10 rounded-full border border-gray-200 bg-gray-50 text-gray-600 transition hover:bg-gray-100 md:hidden" aria-label="Buka menu">
                <i class="fas fa-bars"></i>
            </button>
            <img src="{{ asset('assets/images/RUNITC_LOGO.png') }}" alt="Logo" class="h-8 object-contain">
        </div>
        <div class="relative flex items-center gap-4">
                        <div class="relative">
                            <button id="notificationButton" type="button" onclick="toggleNotificationMenu()" class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                                <i class="far fa-bell"></i>
                                @if (($layoutNotificationCount ?? 0) > 0)
                                    <span id="notificationBadge" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white"><span id="notificationBadgeText">{{ $layoutNotificationCount > 99 ? '99+' : $layoutNotificationCount }}</span></span>
                                @else
                                    <span id="notificationBadge" class="absolute -right-1 -top-1 hidden h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white"><span id="notificationBadgeText">0</span></span>
                                @endif
                            </button>
                            <div id="notificationMenu" class="hidden absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                    <p class="text-sm font-extrabold text-gray-900">Notifikasi</p>
                                    <a href="{{ url('/modules/notifications/index.php') }}" class="text-xs font-bold text-brand-primary">Lihat semua</a>
                                </div>
                                <div id="notificationList" class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                                    @forelse (($layoutNotifications ?? []) as $notification)
                                        <a href="{{ url('/modules/notifications/read.php?id='.(int) $notification['rec_id']) }}" class="block px-4 py-3 hover:bg-gray-50 {{ (int) ($notification['is_read'] ?? 1) === 0 ? 'bg-blue-50/60' : '' }}">
                                            <p class="text-xs font-bold text-gray-800">{{ $notification['title'] ?? 'Notifikasi' }}</p>
                                            <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $notification['message'] ?? '' }}</p>
                                        </a>
                                    @empty
                                        <div class="px-4 py-8 text-center text-xs font-medium text-gray-400">Belum ada notifikasi.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <button id="profileButton" type="button" onclick="toggleProfileMenu()" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-2 py-1.5 hover:bg-gray-50">
                                <img src="{{ $layoutPhotoUrl ?? asset('assets/personal/nopicture.png') }}" alt="Profile" class="h-8 w-8 rounded-full object-cover">
                                <span class="hidden max-w-36 truncate text-sm font-bold text-gray-700 sm:block">{{ $layoutUserName ?? 'User' }}</span>
                                <i id="profileChevron" class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                            </button>
                            <div id="profileMenu" class="hidden absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                                <a href="{{ url('/modules/profile/index.php') }}" class="flex items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-user w-4 text-brand-primary"></i> Profile</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-semibold text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt w-4"></i> Logout</button>
                                </form>
                            </div>
                        </div>
        </div>
    </header>

    <div class="relative flex flex-1 overflow-hidden">
        <div id="sidebarOverlay" class="fixed inset-0 z-50 hidden bg-black/40 md:hidden" onclick="closeMobileSidebar()"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-[60] flex flex-col border-r border-gray-200 bg-white shadow-sm transition-all duration-300 md:relative md:translate-x-0">
            <div class="flex h-[70px] shrink-0 items-center border-b border-gray-100 px-4">
                <button type="button" onclick="handleSidebarToggle(event)" class="flex w-full cursor-pointer items-center gap-3 px-2 py-2 text-gray-600 transition-colors hover:text-brand-primary" data-title="Toggle Menu">
                    <i class="fas fa-bars w-5 shrink-0 text-center text-lg"></i>
                    <span class="sidebar-text whitespace-nowrap text-sm font-semibold">Menu</span>
                </button>
            </div>

            <div class="menu-container flex-1 space-y-4 overflow-y-auto overflow-x-hidden px-3 py-4">
                @foreach (($layoutMenuSections ?? []) as $section)
                    <div class="sidebar-section">
                        <div class="sidebar-section-label sidebar-text px-3 pb-2 pt-3 text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ $section['label'] }}</div>
                        <div class="space-y-1">
                            @include('layouts.partials.sidebar-menu', ['items' => $section['items'], 'level' => 1])
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sidebar-footer shrink-0 space-y-3 border-t border-gray-100 p-3">
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-3 shadow-sm" data-title="{{ $layoutRoleDivision ?? 'Role belum diatur' }}">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-primary">
                        <i class="fas fa-shield-alt text-sm"></i>
                    </div>
                    <div class="sidebar-text min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-gray-700">{{ $layoutRoleDivision ?? 'Role belum diatur' }}</p>
                        <p class="truncate text-[10px] text-gray-400">Active Division</p>
                    </div>
                    <i class="sidebar-chevron fas fa-chevron-right text-[10px] text-gray-400"></i>
                </div>
            </div>
        </aside>

        <main id="main-content-area" class="relative flex-1 overflow-x-hidden overflow-y-auto bg-brand-bg p-4 sm:p-6 md:p-8">
                @yield('content')
        </main>
    </div>

    <script src="{{ asset('assets/js/main.js') }}"></script>
    @stack('scripts')
    <script>
        window.addEventListener('load', function () {
            const loader = document.getElementById('global-loader');
            if (!loader) return;
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 250);
        });
        function toggleDropdown(id) {
            document.getElementById(id)?.classList.toggle('hidden');
            document.getElementById('icon-' + id)?.classList.toggle('rotate-90');
        }
        function handleSidebarToggle(event) { toggleSidebar(event); }
        function toggleSidebar(event) {
            if (event) event.stopPropagation();
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            if (window.matchMedia('(max-width: 767px)').matches) return toggleMobileSidebar();
            hideSidebarTooltip();
            sidebar.classList.toggle('minimized');
            const val = sidebar.classList.contains('minimized') ? 'minimized' : 'expanded';
            document.cookie = 'sidebar_state=' + val + '; path=/';
            try { localStorage.setItem('sidebar_state', val); } catch(e) {}
        }
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (!sidebar || !overlay) return;
            const open = !sidebar.classList.contains('mobile-open');
            sidebar.classList.toggle('mobile-open', open);
            overlay.classList.toggle('hidden', !open);
            document.body.classList.toggle('sidebar-mobile-active', open);
        }
        function closeMobileSidebar() {
            document.getElementById('sidebar')?.classList.remove('mobile-open');
            document.getElementById('sidebarOverlay')?.classList.add('hidden');
            document.body.classList.remove('sidebar-mobile-active');
        }
        function toggleProfileMenu() { document.getElementById('profileMenu')?.classList.toggle('hidden'); }
        function toggleNotificationMenu() { document.getElementById('notificationMenu')?.classList.toggle('hidden'); }
        const notificationConfig = {
            fetchUrl: @json(route('notifications.fetch')),
            readBaseUrl: @json(url('/modules/notifications/read.php?id=')),
        };
        function escapeNotificationText(value) {
            return String(value ?? '').replace(/[&<>'"]/g, function (char) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char];
            });
        }
        function renderNotificationList(notifications) {
            const list = document.getElementById('notificationList');
            if (!list) return;
            if (!notifications.length) {
                list.innerHTML = '<div class="px-4 py-8 text-center text-xs font-medium text-gray-400">Belum ada notifikasi.</div>';
                return;
            }
            list.innerHTML = notifications.map(function (notification) {
                const isUnread = Number(notification.is_read) === 0;
                const url = notificationConfig.readBaseUrl + encodeURIComponent(notification.rec_id);
                return '<a href="' + url + '" class="block px-4 py-3 hover:bg-gray-50 ' + (isUnread ? 'bg-blue-50/60' : '') + '">' +
                    '<p class="text-xs font-bold text-gray-800">' + escapeNotificationText(notification.title || 'Notifikasi') + '</p>' +
                    '<p class="mt-1 line-clamp-2 text-xs text-gray-500">' + escapeNotificationText(notification.message || '') + '</p>' +
                    '<p class="mt-1 text-[10px] font-medium text-gray-400">' + escapeNotificationText(notification.created_at || '-') + '</p>' +
                '</a>';
            }).join('');
        }
        function updateNotificationUi(payload) {
            const count = Number(payload.unread_count || 0);
            const badge = document.getElementById('notificationBadge');
            const badgeText = document.getElementById('notificationBadgeText');
            if (badge && badgeText) {
                badgeText.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('hidden', count <= 0);
                badge.classList.toggle('flex', count > 0);
            }
            renderNotificationList(payload.notifications || []);
        }
        function fetchNotifications() {
            if (document.hidden) return;
            fetch(notificationConfig.fetchUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(response => response.ok ? response.json() : null)
                .then(payload => { if (payload && payload.success) updateNotificationUi(payload); })
                .catch(() => {});
        }
        setInterval(fetchNotifications, 20000);
        function hideSidebarTooltip() {
            document.getElementById('sidebar-tooltip')?.style.setProperty('display', 'none');
        }
        document.addEventListener('mouseover', function (event) {
            const target = event.target.closest('#sidebar.minimized [data-title]');
            if (!target || window.matchMedia('(max-width: 767px)').matches) return;
            let tooltip = document.getElementById('sidebar-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'sidebar-tooltip';
                tooltip.className = 'sidebar-tooltip';
                document.body.appendChild(tooltip);
            }
            tooltip.textContent = target.getAttribute('data-title') || '';
            tooltip.style.display = 'block';
            const rect = target.getBoundingClientRect();
            const top = rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2);
            tooltip.style.left = (rect.right + 10) + 'px';
            tooltip.style.top = Math.max(8, top) + 'px';
        });
        document.addEventListener('mouseout', function (event) {
            const target = event.target.closest('#sidebar.minimized [data-title]');
            if (!target) return;
            const next = event.relatedTarget;
            if (next && target.contains(next)) return;
            hideSidebarTooltip();
        });
        document.addEventListener('click', function (event) {
            if (event.target.closest('#sidebar [data-title]')) hideSidebarTooltip();
        });
        document.addEventListener('click', function (event) {
            const profileMenu = document.getElementById('profileMenu');
            const profileButton = document.getElementById('profileButton');
            const notificationMenu = document.getElementById('notificationMenu');
            const notificationButton = document.getElementById('notificationButton');
            if (profileMenu && profileButton && !profileButton.contains(event.target) && !profileMenu.contains(event.target)) profileMenu.classList.add('hidden');
            if (notificationMenu && notificationButton && !notificationButton.contains(event.target) && !notificationMenu.contains(event.target)) notificationMenu.classList.add('hidden');
        });
        (function () {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            let val = null;
            try { val = localStorage.getItem('sidebar_state'); } catch(e) {}
            if (!val) {
                const match = document.cookie.match(/(?:^|;\s*)sidebar_state=(minimized|expanded)/);
                if (match) val = match[1];
            }
            if (val === 'minimized') sidebar.classList.add('minimized');
            if (val === 'expanded') sidebar.classList.remove('minimized');
        })();
    </script>
</body>
</html>
