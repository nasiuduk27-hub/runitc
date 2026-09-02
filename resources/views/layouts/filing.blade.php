<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RUN-ITC')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/runitc.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6' } } } }
        };
    </script>
    <style>
        .filing-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .filing-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
        .filing-scroll::-webkit-scrollbar-track { background: transparent; }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-brand-bg font-sans text-gray-600 antialiased">
    @php
        $hasAdvancedFilter = ! empty($filters['file_format'] ?? [])
            || ! empty($filters['owner_id'] ?? 0)
            || ($filters['has_words'] ?? '') !== ''
            || ($filters['location'] ?? '') !== ''
            || ($filters['date_modify'] ?? 'anytime') !== 'anytime'
            || ($filters['share_to'] ?? '') !== '';
    @endphp
    <div id="global-loader" class="fixed inset-0 z-[99999] flex flex-col items-center justify-center bg-brand-bg transition-opacity duration-300">
        <div class="mb-3 flex h-8 items-center justify-center space-x-2">
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary" style="animation-delay: -0.3s;"></div>
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary" style="animation-delay: -0.15s;"></div>
            <div class="h-3 w-3 animate-bounce rounded-full bg-brand-primary"></div>
        </div>
        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Memuat...</span>
    </div>

    <header class="z-[70] flex h-[56px] w-full shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <button id="mobileFilingSidebar" type="button" onclick="toggleMobileFilingSidebar()" class="h-9 w-9 rounded-full border border-gray-200 bg-gray-50 text-gray-600 transition hover:bg-gray-100 md:hidden" aria-label="Buka menu">
                <i class="fas fa-bars"></i>
            </button>
            <a href="{{ route('filing-system.index') }}" class="flex items-center">
                <img src="{{ asset('assets/images/RUNITC_LOGO.png') }}" alt="Logo RUN-ITC" class="h-8 object-contain">
            </a>
        </div>

        <form method="GET" action="{{ route('filing-system.index') }}" class="relative hidden min-w-0 max-w-xl flex-1 md:block">
            <input type="hidden" name="folder" value="{{ $folder ?? 'my_drive' }}">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari..." class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-9 pr-9 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
            <button type="button" onclick="toggleAdvancedFilter()" title="Advance Search / Filter"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-blue-600 {{ $hasAdvancedFilter ? 'text-blue-600' : '' }}">
                <i class="fas fa-sliders-h text-xs"></i>
            </button>
        </form>

        <div class="relative flex items-center gap-4">
            <div class="relative">
                <button id="notificationButton" type="button" onclick="toggleNotificationMenu()" class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
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
                    <img src="{{ $layoutPhotoUrl ?? asset('assets/personal/nopicture.png') }}" alt="Profile" class="h-7 w-7 rounded-full object-cover">
                    <span class="hidden max-w-36 truncate text-sm font-bold text-gray-700 sm:block">{{ $layoutUserName ?? 'User' }}</span>
                    <i id="profileChevron" class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                    <a href="{{ route('profile.index') }}" class="flex items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-user w-4 text-brand-primary"></i> Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-semibold text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt w-4"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="relative flex flex-1 overflow-hidden">
        <div id="filingSidebarOverlay" class="fixed inset-0 z-40 hidden bg-black/40 md:hidden" onclick="closeMobileFilingSidebar()"></div>
        <aside id="filingSidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-gray-200 bg-white md:relative md:translate-x-0 lg:w-64">
            <div class="filing-scroll flex-1 space-y-1 overflow-y-auto overflow-x-hidden p-2.5">
                @yield('filing-sidebar')
            </div>
        </aside>

        <main id="main-content-area" class="filing-scroll relative flex-1 overflow-x-hidden overflow-y-auto bg-white">
            @yield('content')
        </main>
    </div>

    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script>
        window.addEventListener('load', function () {
            const loader = document.getElementById('global-loader');
            if (!loader) return;
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 250);
        });
        function toggleMobileFilingSidebar() {
            const sidebar = document.getElementById('filingSidebar');
            const overlay = document.getElementById('filingSidebarOverlay');
            if (!sidebar || !overlay) return;
            const open = !sidebar.classList.contains('mobile-open');
            sidebar.classList.toggle('mobile-open', open);
            overlay.classList.toggle('hidden', !open);
        }
        function closeMobileFilingSidebar() {
            document.getElementById('filingSidebar')?.classList.remove('mobile-open');
            document.getElementById('filingSidebarOverlay')?.classList.add('hidden');
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
        document.addEventListener('click', function (event) {
            const profileMenu = document.getElementById('profileMenu');
            const profileButton = document.getElementById('profileButton');
            const notificationMenu = document.getElementById('notificationMenu');
            const notificationButton = document.getElementById('notificationButton');
            if (profileMenu && profileButton && !profileButton.contains(event.target) && !profileMenu.contains(event.target)) profileMenu.classList.add('hidden');
            if (notificationMenu && notificationButton && !notificationButton.contains(event.target) && !notificationMenu.contains(event.target)) notificationMenu.classList.add('hidden');
        });
    </script>
</body>
</html>
