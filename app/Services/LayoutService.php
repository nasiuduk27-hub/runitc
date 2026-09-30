<?php

namespace App\Services;

use App\Support\CreditUnionAccess;
use Illuminate\Support\Facades\DB;
use Throwable;

class LayoutService
{
    public function getViewData(): array
    {
        $userId = (int) auth_user_id();

        return [
            'layoutUserName' => session('user_name', session('account_nm', 'User')),
            'layoutRoleDivision' => $this->getRoleDivision($userId),
            'layoutPhotoUrl' => $this->getPhotoUrl($userId),
            'layoutNotificationCount' => $this->getUnreadNotificationCount($userId),
            'layoutNotifications' => $this->getLatestNotifications($userId),
            'layoutMenuSections' => $this->getMenuSections($userId),
            'layoutCuIsAdmin' => CreditUnionAccess::hasAdminRole($userId),
            'layoutCuHasMember' => CreditUnionAccess::memberForUser($userId) !== null,
            'layoutCuMemberMode' => CreditUnionAccess::isActingAsMember($userId),
        ];
    }

    private function getRoleDivision(int $userId): string
    {
        if ($userId <= 0) {
            return 'Role belum diatur';
        }

        try {
            $row = DB::connection('run')->selectOne(
                "SELECT ga.grpdesc AS role_division
                 FROM sysitc_usracc ua
                 INNER JOIN sysitc_grpacc ga
                    ON ga.grpaccess = ua.access_code
                   AND ga.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND ua.access_code IN ('01', '03', '04')
                 ORDER BY
                    CASE
                        WHEN ga.grpaccess = '03' AND ga.grpacc = '999' AND ga.grpdesc LIKE '%SUPER%ADMIN%' THEN 0
                        WHEN ua.access_code = '04' THEN 1
                        WHEN ua.access_code = '03' THEN 2
                        WHEN ua.access_code = '01' THEN 3
                        ELSE 9
                    END,
                    ga.grpdesc
                 LIMIT 1",
                [$userId]
            );

            $role = trim((string) ($row->role_division ?? ''));

            return $role !== '' ? $role : 'Role belum diatur';
        } catch (Throwable) {
            return 'Role belum diatur';
        }
    }

    private function getPhotoUrl(int $userId): string
    {
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            $relative = 'assets/personal/user_'.$userId.'.'.$ext;
            if ($userId > 0 && file_exists(public_path($relative))) {
                return asset($relative).'?v='.filemtime(public_path($relative));
            }
        }

        return asset('assets/personal/nopicture.png');
    }

    private function getUnreadNotificationCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            return (int) DB::connection('run')
                ->table('sys_notifications')
                ->where('recipient_user_id', $userId)
                ->where('is_read', 0)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function getLatestNotifications(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            return DB::connection('run')
                ->table('sys_notifications')
                ->select('rec_id', 'title', 'message', 'target_url', 'is_read', 'created_at')
                ->where('recipient_user_id', $userId)
                ->orderByDesc('created_at')
                ->orderByDesc('rec_id')
                ->limit(8)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function getMenuSections(int $userId): array
    {
        $menus = $this->getDatabaseMenus($userId);

        if (empty($menus)) {
            $menus = $this->getFallbackMenus();
        }

        // Anggota biasa melihat menu CU tetap datar; sub-grup hanya untuk admin.
        $isAdmin = CreditUnionAccess::isAdmin($userId);
        $isActingAsMember = CreditUnionAccess::isActingAsMember($userId);

        $sections = [];
        foreach ($menus as $menu) {
            // Mode anggota: menu CU dari DB seluruhnya admin-oriented, ganti
            // dengan daftar menu anggota (disisipkan setelah loop).
            if ($isActingAsMember && $this->isCreditUnionMenu($menu)) {
                continue;
            }

            if (! $isAdmin && trim((string) ($menu['section_key'] ?? '')) === 'credit_union') {
                $menu['children'] = $this->flattenGroupChildren($menu['children'] ?? []);
            }

            $sectionKey = trim((string) ($menu['section_key'] ?? '')) ?: 'main';
            $sectionLabel = trim((string) ($menu['section_label'] ?? '')) ?: 'Main';
            $sectionSort = (int) ($menu['section_sort'] ?? 10);

            if (! isset($sections[$sectionKey])) {
                $sections[$sectionKey] = ['label' => $sectionLabel, 'sort' => $sectionSort, 'items' => []];
            }

            $sections[$sectionKey]['items'][] = $menu;
        }

        if ($isActingAsMember) {
            $sections['credit_union_member'] = [
                'label' => 'Credit Union',
                'sort' => 40,
                'items' => $this->memberCreditUnionMenus(),
            ];
        }

        usort($sections, fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']));

        return array_values(array_filter($sections, fn ($section) => ! empty($section['items'])));
    }

    /**
     * Angkat anak dari sub-grup menu (url "#") satu level ke atas, sehingga
     * anggota biasa melihat daftar datar. Grup berisi grup lain tidak dibuka
     * rekursif (saat ini struktur CU hanya satu level grup).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    private function flattenGroupChildren(array $children): array
    {
        $flat = [];

        foreach ($children as $child) {
            if (($child['url'] ?? '#') === '#' && ! empty($child['children'])) {
                foreach ($child['children'] as $grandChild) {
                    $flat[] = $grandChild;
                }

                continue;
            }

            $flat[] = $child;
        }

        return $flat;
    }

    /**
     * Menu credit union untuk mode anggota: hanya halaman milik anggota sendiri.
     * Dipakai admin yang sekaligus anggota agar sidebar tampil sebagai anggota,
     * bukan admin. Halaman admin (members, payments, rekonsiliasi, audit,
     * settings, dll.) sengaja tidak disertakan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function memberCreditUnionMenus(): array
    {
        $items = [];

        foreach (self::memberMenuDefinitions() as $index => $item) {
            $active = request()->routeIs($item['active']);

            $items[] = [
                'id' => 'cu-member-'.$index,
                'title' => $item['title'],
                'icon' => $item['icon'],
                'url' => route($item['route'], $item['params']),
                'children' => [],
                'is_exact_active' => $active,
                'is_branch_active' => $active,
            ];
        }

        return $items;
    }

    /**
     * Definisi menu anggota (nama route, bukan URL) supaya dapat diuji murni.
     * Hanya halaman milik anggota sendiri; menu admin tidak termasuk.
     *
     * @return list<array{title: string, icon: string, route: string, params: array<string, mixed>, active: string}>
     */
    public static function memberMenuDefinitions(): array
    {
        return [
            ['title' => 'Dashboard Saya', 'icon' => 'fas fa-user', 'route' => 'cu.dashboard.my', 'params' => [], 'active' => 'cu.dashboard.my'],
            ['title' => 'Simpanan', 'icon' => 'fas fa-piggy-bank', 'route' => 'cu.savings.index', 'params' => ['view' => 'mine'], 'active' => 'cu.savings.*'],
            ['title' => 'Transaksi', 'icon' => 'fas fa-right-left', 'route' => 'cu.transactions.my', 'params' => [], 'active' => 'cu.transactions.my'],
            ['title' => 'Pinjaman', 'icon' => 'fas fa-hand-holding-dollar', 'route' => 'cu.loans.index', 'params' => [], 'active' => 'cu.loans.*'],
            ['title' => 'Pengajuan Pinjaman', 'icon' => 'fas fa-file-signature', 'route' => 'cu.applications.index', 'params' => [], 'active' => 'cu.applications.*'],
            ['title' => 'Refinancing', 'icon' => 'fas fa-forward', 'route' => 'cu.skips.index', 'params' => [], 'active' => 'cu.skips.*'],
            ['title' => 'Simulasi Kredit', 'icon' => 'fas fa-calculator', 'route' => 'cu.loan-simulation.index', 'params' => [], 'active' => 'cu.loan-simulation.*'],
        ];
    }

    private function getDatabaseMenus(int $userId): array
    {
        try {
            $rows = DB::connection('run')->select(
                'SELECT * FROM sys_menus WHERE is_active = 1 ORDER BY mst_id ASC, sort_order ASC, rec_id ASC'
            );

            $rawMenus = array_map(fn ($row) => (array) $row, $rows);
            $isAdmin = CreditUnionAccess::isAdmin($userId);
            $member = CreditUnionAccess::memberForUser($userId);

            $dropMenu = function (array $menus, string $url): array {
                return array_values(array_filter($menus, fn (array $menu): bool => trim((string) ($menu['url'] ?? '')) !== $url));
            };

            // Anggota CU non-aktif tidak melihat menu Credit Union di sidebar.
            if (! $isAdmin && $member !== null && ! $member->isActive()) {
                $rawMenus = array_values(array_filter($rawMenus, fn (array $menu): bool => ! $this->isCreditUnionMenu($menu)));
            }

            // Anggota (bukan admin) tidak melihat dashboard seluruh CU; cukup "Dashboard Saya".
            if (! $isAdmin && $member !== null) {
                $rawMenus = $dropMenu($rawMenus, '/credit-union/dashboard');
            }

            // Menu "Dashboard Saya" hanya untuk user yang tertaut ke data anggota.
            if ($member === null) {
                $rawMenus = $dropMenu($rawMenus, '/credit-union/dashboard/my');
            }

            $allowedMenuIds = $this->isSuperadmin($userId)
                ? array_values(array_unique(array_map(fn ($menu) => (int) $menu['rec_id'], $rawMenus)))
                : $this->getAllowedMenuIds($userId);

            return $this->buildMenuTree($rawMenus, 0, $allowedMenuIds);
        } catch (Throwable) {
            return [];
        }
    }

    private function isSuperadmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (int) DB::connection('run')->selectOne(
                "SELECT COUNT(*) AS aggregate
                 FROM sysitc_usracc ua
                 JOIN sysitc_grpacc g
                   ON g.grpaccess = ua.access_code
                  AND g.grpacc = ua.access_account
                 WHERE ua.user_rec_id = ?
                   AND g.grpaccess = '03'
                   AND g.grpacc = '999'
                   AND g.grpdesc LIKE '%SUPER%ADMIN%'",
                [$userId]
            )->aggregate > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $menu
     */
    private function isCreditUnionMenu(array $menu): bool
    {
        if (trim((string) ($menu['section_key'] ?? '')) === 'credit_union') {
            return true;
        }

        return str_starts_with(trim((string) ($menu['url'] ?? '')), '/credit-union');
    }

    private function getAllowedMenuIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $rows = DB::connection('run')->select(
                'SELECT DISTINCT sma.menu_id
                 FROM sysitc_usracc ua
                 INNER JOIN sysitc_grpacc ga
                   ON ga.grpaccess = ua.access_code
                  AND ga.grpacc = ua.access_account
                 INNER JOIN sys_menu_access sma
                   ON sma.grpacc_id = ga.rec_id
                 WHERE ua.user_rec_id = ?',
                [$userId]
            );

            return array_values(array_unique(array_filter(array_map(fn ($row) => (int) $row->menu_id, $rows))));
        } catch (Throwable) {
            return [];
        }
    }

    private function buildMenuTree(array $rows, int $parentId = 0, array $allowedMenuIds = []): array
    {
        $branch = [];

        foreach ($rows as $row) {
            if ((int) ($row['mst_id'] ?? 0) !== $parentId) {
                continue;
            }

            $children = $this->buildMenuTree($rows, (int) $row['rec_id'], $allowedMenuIds);
            $isGlobal = (int) ($row['is_global'] ?? 0) === 1;
            $isAllowed = in_array((int) $row['rec_id'], $allowedMenuIds, true);

            if (! $isGlobal && ! $isAllowed && empty($children)) {
                continue;
            }

            $url = $this->normalizeMenuUrl((string) ($row['url'] ?? '#'));

            // Parent pemersatu (url "#") tanpa satu pun anak yang boleh diakses
            // akan dirender sebagai <a href="#"> mati; sembunyikan saja.
            if ($url === '#' && empty($children)) {
                continue;
            }

            $isExactActive = $this->isActiveUrl($url);
            $isBranchActive = $isExactActive || collect($children)->contains(fn ($child) => ! empty($child['is_branch_active']));

            $branch[] = [
                'id' => (int) $row['rec_id'],
                'title' => $row['title'] ?? 'Menu',
                'icon' => $row['icon'] ?: 'fas fa-cube',
                'url' => $url,
                'section_key' => $row['section_key'] ?? 'main',
                'section_label' => $row['section_label'] ?? 'Main',
                'section_sort' => (int) ($row['section_sort'] ?? 10),
                'children' => $children,
                'is_exact_active' => $isExactActive,
                'is_branch_active' => $isBranchActive,
            ];
        }

        return $branch;
    }

    private function normalizeMenuUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return '#';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $url = preg_replace('#^(\./|\.\./)+#', '', $url);
        $path = '/'.ltrim($url, '/');

        if ($path === '/dashboard.php' || $path === '/dashboard') {
            if ($this->isSuperadmin((int) auth_user_id())) {
                return route('admin.dashboard');
            }

            return route('dashboard');
        }

        // Path modern Laravel tanpa ekstensi (memiliki route sendiri) tidak boleh ditambah .php
        $modernPrefixes = [
            '/profile',
            '/cbt-ops',
            '/admin',
            '/credit-union',
            '/filing-system',
            '/forgot-password',
            '/verify-otp',
            '/reset-password',
            '/register',
            '/login',
        ];

        $isModern = false;
        foreach ($modernPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $isModern = true;
                break;
            }
        }

        if (! $isModern && ! preg_match('#\.php($|[?])#i', $path) && ! preg_match('#\.[a-z0-9]{2,5}($|[?])#i', $path)) {
            $parts = explode('?', $path, 2);
            $path = $parts[0].'.php'.(isset($parts[1]) ? '?'.$parts[1] : '');
        }

        return url($path);
    }

    private function isActiveUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $current = '/'.trim(request()->path(), '/');

        $clean = fn ($value) => strtolower(rtrim(preg_replace('#\.php$#i', '', preg_replace('#/index\.php$#i', '', $value)), '/') ?: '/');

        return $clean($path) === $clean($current);
    }

    private function getFallbackMenus(): array
    {
        return [
            ['id' => 1, 'title' => 'Dashboard', 'icon' => 'fas fa-chart-pie', 'url' => route('dashboard'), 'children' => [], 'is_exact_active' => request()->routeIs('dashboard'), 'is_branch_active' => request()->routeIs('dashboard')],
            ['id' => 2, 'title' => 'Profile', 'icon' => 'fas fa-user', 'url' => route('profile.index'), 'children' => [], 'is_exact_active' => request()->routeIs('profile.*'), 'is_branch_active' => request()->routeIs('profile.*')],
            ['id' => 3, 'title' => 'Notifications', 'icon' => 'fas fa-bell', 'url' => route('notifications.index'), 'children' => [], 'is_exact_active' => request()->routeIs('notifications.*'), 'is_branch_active' => request()->routeIs('notifications.*')],
            ['id' => 4, 'title' => 'Test Admin', 'icon' => 'fas fa-clipboard-list', 'url' => route('cbt-ops.test-admin.index'), 'children' => [], 'is_exact_active' => request()->routeIs('cbt-ops.test-admin.*'), 'is_branch_active' => request()->routeIs('cbt-ops.test-admin.*')],
            ['id' => 5, 'title' => 'Filing System', 'icon' => 'fas fa-folder-open', 'url' => route('filing-system.index'), 'children' => [], 'is_exact_active' => request()->routeIs('filing-system.*'), 'is_branch_active' => request()->routeIs('filing-system.*')],
            ['id' => 6, 'title' => 'Admin', 'icon' => 'fas fa-shield-halved', 'url' => route('admin.dashboard'), 'children' => [], 'is_exact_active' => request()->routeIs('admin.*'), 'is_branch_active' => request()->routeIs('admin.*')],
        ];
    }
}
