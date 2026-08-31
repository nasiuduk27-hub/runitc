<?php
// File: includes/layout_header.php

require_once dirname(__DIR__, 3).'/config.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: '.rtrim(BASE_URL, '/').'/index.php');
    exit;
}

// Maintenance mode check
try {
    require_once dirname(__DIR__).'/Models/SystemSettings.php';
    $settings = new SystemSettings($pdo_run);
    if ($settings->get('maintenance_mode', false)) {
        require_once __DIR__.'/menu_guard.php';
        if (! isSuperadmin($pdo_run)) {
            $msg = $settings->get('maintenance_message', 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.');
            http_response_code(503);
            exit('
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md border-t-4 border-amber-500">
        <div class="text-6xl text-amber-500 mb-4"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Mode Maintenance</h2>
        <p class="text-gray-600 mb-6">'.htmlspecialchars($msg).'</p>
        <div class="text-xs text-gray-400">Silakan kembali lagi nanti.</div>
    </div>
</body>
</html>
            ');
        }
    }
} catch (Throwable $e) {
    error_log('Maintenance mode check failed: '.$e->getMessage());
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_role_division = 'Role belum diatur';
$sidebar_preview_role_id = (int) ($_GET['role_preview_id'] ?? 0);

try {
    $stmtUserRole = $pdo_run->prepare("
        SELECT ga.grpdesc AS role_division
        FROM sysitc_usracc ua
        INNER JOIN sysitc_grpacc ga
            ON ga.grpaccess = ua.access_code
            AND ga.grpacc = ua.access_account
        WHERE ua.user_rec_id = ?
          AND ua.access_code IN ('03', '04')
        ORDER BY
            CASE
                WHEN ga.grpaccess = '03'
                    AND ga.grpacc = '999'
                    AND ga.grpdesc LIKE '%SUPER%ADMIN%' THEN 0
                WHEN ua.access_code = '04' THEN 1
                WHEN ua.access_code = '03' THEN 2
                ELSE 9
            END,
            ga.grpdesc
        LIMIT 1
    ");
    $stmtUserRole->execute([$user_id]);
    $roleDivision = trim((string) $stmtUserRole->fetchColumn());

    if ($roleDivision !== '') {
        $user_role_division = $roleDivision;
    }
} catch (Throwable $e) {
    error_log('User role division query failed: '.$e->getMessage());
}

// --- LOGIKA FOTO PROFIL ---
$photo_url = BASE_URL.'/assets/personal/nopicture.png'; // Default jika tidak ada foto
$photo_extensions = ['png', 'jpg', 'jpeg', 'gif'];
$psysuserid = strval($user_id);

foreach ($photo_extensions as $ext) {
    // Cek apakah file fisik ada di server
    if (file_exists(BASE_PATH.'/assets/personal/user_'.$psysuserid.'.'.$ext)) {
        // Jika ada, buat URL-nya (tambah ?v=time untuk menghindari cache browser)
        $photo_url = BASE_URL.'/assets/personal/user_'.$psysuserid.'.'.$ext.'?v='.time();
        break;
    }
}

$notification_count = 0;
$notifications = [];
$allNotificationsUrl = rtrim(BASE_URL, '/').'/modules/notifications/index.php';
$markAllReadUrl = rtrim(BASE_URL, '/').'/modules/notifications/mark_all_read.php?redirect='.urlencode($allNotificationsUrl);
$notificationFetchUrl = rtrim(BASE_URL, '/').'/modules/notifications/fetch.php';
$latest_notification_id = 0;

try {
    $notificationService = new Notification($pdo_run);
    $notification_count = $notificationService->getUnreadCount($user_id);
    $notifications = $notificationService->getLatest($user_id, 8);
    $latest_notification_id = ! empty($notifications) ? (int) $notifications[0]['rec_id'] : 0;
} catch (Throwable $e) {
    error_log('Notification header failed: '.$e->getMessage());
}

// Akan diisi dari sysitc_usracc -> sysitc_grpacc
$user_grps = [];

/*
|--------------------------------------------------------------------------
| Helper Base Path
|--------------------------------------------------------------------------
| Support project di root domain maupun sub-folder.
*/
if (! function_exists('appBasePath')) {
    function appBasePath(): string
    {
        $fromBaseUrl = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        $base = defined('APP_BASE_FOLDER') && APP_BASE_FOLDER !== ''
            ? APP_BASE_FOLDER
            : $fromBaseUrl;

        $base = '/'.trim($base, '/');

        return $base === '/' ? '' : $base;
    }
}

if (! function_exists('ensurePhpExtensionForUrl')) {
    function ensurePhpExtensionForUrl(string $url): string
    {
        $parts = explode('?', $url, 2);
        $path = $parts[0];
        $query = isset($parts[1]) ? '?'.$parts[1] : '';

        if (preg_match('#\.php$#i', $path) || preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
            return $url;
        }

        $basePath = appBasePath();
        $relativePath = $path;
        if ($basePath !== '' && (strpos($relativePath, $basePath.'/') === 0 || $relativePath === $basePath)) {
            $relativePath = substr($relativePath, strlen($basePath));
        }

        $relativePath = '/'.trim($relativePath, '/');
        if ($relativePath !== '/' && file_exists(BASE_PATH.$relativePath.'.php')) {
            return $path.'.php'.$query;
        }

        if ($relativePath !== '/' && file_exists(BASE_PATH.$relativePath.'/index.php')) {
            return rtrim($path, '/').'/index.php'.$query;
        }

        return $url;
    }
}

if (! function_exists('normalizeMenuUrl')) {
    function normalizeMenuUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || $url === '#') {
            return '#';
        }

        // External link jangan diubah, kecuali masih domain aplikasi sendiri.
        if (preg_match('/^https?:\/\//i', $url)) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            $baseHost = parse_url(BASE_URL, PHP_URL_HOST);

            if ($urlHost && $baseHost && strcasecmp($urlHost, $baseHost) === 0) {
                $scheme = parse_url($url, PHP_URL_SCHEME) ?: parse_url(BASE_URL, PHP_URL_SCHEME) ?: 'https';
                $port = parse_url($url, PHP_URL_PORT);
                $path = parse_url($url, PHP_URL_PATH) ?: '/';
                $query = parse_url($url, PHP_URL_QUERY);
                $normalizedPath = ensurePhpExtensionForUrl($path.($query !== null ? '?'.$query : ''));

                return $scheme.'://'.$urlHost.($port ? ':'.$port : '').$normalizedPath;
            }

            return $url;
        }

        // Buang awalan ./ atau ../ supaya path konsisten.
        $url = preg_replace('#^(\./|\.\./)+#', '', $url);

        $basePath = appBasePath();

        // Jika sudah root-relative.
        if (strpos($url, '/') === 0) {
            // Kalau sudah mengandung base path, biarkan.
            if ($basePath !== '' && (strpos($url, $basePath.'/') === 0 || $url === $basePath)) {
                return ensurePhpExtensionForUrl(preg_replace('#/+#', '/', $url));
            }

            return ensurePhpExtensionForUrl(preg_replace('#/+#', '/', $basePath.'/'.ltrim($url, '/')));
        }

        // Default: path relatif dari root app.
        return ensurePhpExtensionForUrl(preg_replace('#/+#', '/', $basePath.'/'.ltrim($url, '/')));
    }
}

if (! function_exists('cleanPathForActiveCheck')) {
    function cleanPathForActiveCheck(string $path): string
    {
        $path = html_entity_decode(trim((string) $path));
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#/+#', '/', $path);

        // Buang base folder aplikasi jika ada, supaya aman untuk IP/domain/sub-folder.
        $basePath = appBasePath();
        if ($basePath !== '' && (stripos($path, $basePath.'/') === 0 || strcasecmp($path, $basePath) === 0)) {
            $path = substr($path, strlen($basePath));
            $path = $path === false || $path === '' ? '/' : $path;
        }

        $path = preg_replace('#/index\.php$#i', '', $path);
        $path = preg_replace('#\.php$#i', '', $path);
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : strtolower($path);
    }
}

if (! function_exists('isMenuActivePath')) {
    function isMenuActivePath(?string $menuUrl, string $currentPath): bool
    {
        $menuUrl = trim((string) $menuUrl);

        if ($menuUrl === '' || $menuUrl === '#') {
            return false;
        }

        $menuPath = cleanPathForActiveCheck($menuUrl);
        $currentPath = cleanPathForActiveCheck($currentPath);

        if ($menuPath === '/' || $currentPath === '/') {
            return false;
        }

        // 1) Cocok penuh.
        if ($menuPath === $currentPath) {
            return true;
        }

        // 2) Cocok ketika menu URL disimpan sebagai potongan path.
        if (str_ends_with($currentPath, $menuPath)) {
            return true;
        }

        // 3) Cocok ketika menu adalah parent folder/module.
        if (str_starts_with($currentPath.'/', $menuPath.'/')) {
            return true;
        }

        // 4) Fallback untuk data menu yang hanya menyimpan filename, contoh: permissions.php.
        $menuBase = basename($menuPath);
        $currentBase = basename($currentPath);

        if ($menuBase !== '' && $menuBase !== 'index' && $menuBase === $currentBase) {
            return true;
        }

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Current Path
|--------------------------------------------------------------------------
*/
$current_path = cleanPathForActiveCheck($_SERVER['REQUEST_URI'] ?? '/');

/*
|--------------------------------------------------------------------------
| Ambil Menu dari Database
|--------------------------------------------------------------------------
| Penting:
| - Semua parent aktif diambil dulu.
| - Permission dicek terpisah.
| - Parent tetap muncul jika punya child yang boleh diakses user.
*/
$raw_menus = [];
$allowed_menu_ids = [];

try {
    // Ambil semua menu aktif.
    // Global dan non-global tetap diambil dulu.
    // Nanti filter akses dilakukan di buildMenuTreeFromDatabase().
    $stmt = $pdo_run->prepare('
        SELECT *
        FROM sys_menus
        WHERE is_active = 1
        ORDER BY mst_id ASC, sort_order ASC, rec_id ASC
    ');
    $stmt->execute();
    $raw_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Ambil akses menu user
    |--------------------------------------------------------------------------
    | Alur:
    | 1. sysitc_usracc.user_rec_id = user login
    | 2. superadmin → semua menu boleh tampil
    | 3. non-superadmin → hanya access_code 03 dan 04
    | 4. cocokkan ke sysitc_grpacc:
    |    - sysitc_grpacc.grpaccess = sysitc_usracc.access_code
    |    - sysitc_grpacc.grpacc    = sysitc_usracc.access_account
    | 5. ambil sysitc_grpacc.rec_id
    | 6. cocokkan ke sys_menu_access.grpacc_id
    | 7. hasil menu_id itulah menu non-global yang boleh tampil
    */
    if ($user_id > 0) {
        require_once __DIR__.'/menu_guard.php';

        if (isSuperadmin($pdo_run)) {
            // Superadmin: semua menu boleh tampil
            $allowed_menu_ids = array_values(array_unique(array_map(
                'intval',
                array_column($raw_menus, 'rec_id')
            )));
            $user_grps = [];

            if ($sidebar_preview_role_id > 0) {
                $stmtPreviewAccess = $pdo_run->prepare('
                    SELECT DISTINCT
                        sma.menu_id,
                        ga.rec_id AS grpacc_id,
                        ga.grpdesc
                    FROM sysitc_grpacc ga
                    LEFT JOIN sys_menu_access sma
                        ON sma.grpacc_id = ga.rec_id
                    WHERE ga.rec_id = ?
                ');
                $stmtPreviewAccess->execute([$sidebar_preview_role_id]);
                $previewRows = $stmtPreviewAccess->fetchAll(PDO::FETCH_ASSOC);

                if (! empty($previewRows)) {
                    $allowed_menu_ids = array_values(array_unique(array_map(
                        'intval',
                        array_filter(array_column($previewRows, 'menu_id'))
                    )));
                    $user_grps = [$sidebar_preview_role_id];
                    $user_role_division = (string) ($previewRows[0]['grpdesc'] ?? $user_role_division);
                }
            }
        } else {
            $stmtAccess = $pdo_run->prepare("
                SELECT DISTINCT
                    sma.menu_id,
                    ga.rec_id AS grpacc_id
                FROM sysitc_usracc ua
                INNER JOIN sysitc_grpacc ga
                    ON ga.grpaccess = ua.access_code
                    AND ga.grpacc = ua.access_account
                INNER JOIN sys_menu_access sma
                    ON sma.grpacc_id = ga.rec_id
                WHERE ua.user_rec_id = ?
                  AND ua.access_code IN ('03', '04')
            ");

            $stmtAccess->execute([$user_id]);
            $accessRows = $stmtAccess->fetchAll(PDO::FETCH_ASSOC);

            $allowed_menu_ids = array_values(array_unique(array_map(
                'intval',
                array_column($accessRows, 'menu_id')
            )));

            $user_grps = array_values(array_unique(array_map(
                'intval',
                array_column($accessRows, 'grpacc_id')
            )));
        }
    }
} catch (Throwable $e) {
    error_log('Menu query failed: '.$e->getMessage());
    $raw_menus = [];
    $allowed_menu_ids = [];
    $user_grps = [];
}

if (! function_exists('userCanSeeMenuDirectly')) {
    function userCanSeeMenuDirectly(array $menu, array $allowedMenuIds): bool
    {
        if ((int) ($menu['is_global'] ?? 0) === 1) {
            return true;
        }

        return in_array((int) $menu['rec_id'], $allowedMenuIds, true);
    }
}

if (! function_exists('buildMenuTreeFromDatabase')) {
    function buildMenuTreeFromDatabase(array $menus, int $parentId, string $currentPath, array $allowedMenuIds, array $userGroups): array
    {
        $branch = [];

        foreach ($menus as $menu) {
            if ((int) ($menu['mst_id'] ?? 0) !== $parentId) {
                continue;
            }

            $children = buildMenuTreeFromDatabase(
                $menus,
                (int) $menu['rec_id'],
                $currentPath,
                $allowedMenuIds,
                $userGroups
            );

            $isDirectAllowed = userCanSeeMenuDirectly($menu, $allowedMenuIds, $userGroups);

            // Tampilkan jika: menu itu global, ATAU user punya akses langsung, ATAU menu itu punya anak (folder)
            if ((int) ($menu['is_global'] ?? 0) !== 1 && ! in_array((int) $menu['rec_id'], $allowedMenuIds) && empty($children)) {
                continue;
            }

            $normalizedUrl = normalizeMenuUrl($menu['url'] ?? '#');
            $menuPath = $normalizedUrl !== '#'
                ? cleanPathForActiveCheck($normalizedUrl)
                : '#';

            $menu['normalized_url'] = $normalizedUrl;
            $menu['children'] = $children;
            $menu['is_exact_active'] = isMenuActivePath($normalizedUrl, $currentPath);
            $menu['is_branch_active'] = $menu['is_exact_active'];

            foreach ($children as $child) {
                if (! empty($child['is_branch_active'])) {
                    $menu['is_branch_active'] = true;
                    break;
                }
            }

            $branch[] = $menu;
        }

        return $branch;
    }
}

$menu_tree = buildMenuTreeFromDatabase($raw_menus, 0, $current_path, $allowed_menu_ids, $user_grps);

/*
|--------------------------------------------------------------------------
| URL Bypass Protection
|--------------------------------------------------------------------------
*/
require_once dirname(__DIR__).'/Classes/AccessGuard.php';

// Panggil fungsi penjaga
$is_allowed = AccessGuard::checkDirectoryAccess($current_path, $raw_menus, $allowed_menu_ids);

if (! $is_allowed) {
    http_response_code(403);
    exit('
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 - Akses Ditolak</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-100 h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md border-t-4 border-red-500">
            <div class="text-6xl text-red-500 mb-4 animate-pulse"><i class="fas fa-shield-halved"></i></div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Akses Ditolak!</h2>
            <p class="text-gray-600 mb-6">Maaf, akun Anda tidak memiliki hak akses untuk membuka halaman atau modul ini.</p>
            <a href="'.rtrim(BASE_URL, '/').'/dashboard.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </body>
    </html>
    ');
}

/*
|--------------------------------------------------------------------------
| Render Sidebar Menu
|--------------------------------------------------------------------------
*/
if (! function_exists('sidebarMenuHasUrlPrefix')) {
    function sidebarMenuHasUrlPrefix(array $menu, string $prefix): bool
    {
        $url = strtolower((string) ($menu['normalized_url'] ?? $menu['url'] ?? ''));
        $prefix = strtolower($prefix);

        if (str_contains($url, $prefix)) {
            return true;
        }

        foreach (($menu['children'] ?? []) as $child) {
            if (sidebarMenuHasUrlPrefix($child, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('sidebarMenuSection')) {
    function sidebarMenuSection(array $menu): string
    {
        $title = strtolower((string) ($menu['title'] ?? ''));

        if (str_contains($title, 'dashboard') || sidebarMenuHasUrlPrefix($menu, '/dashboard')) {
            return 'main';
        }

        if (str_contains($title, 'berita acara') || sidebarMenuHasUrlPrefix($menu, 'berita_acara')) {
            return 'test_operations';
        }

        if (
            sidebarMenuHasUrlPrefix($menu, '/modules/cbt_ops/filing_system/')
            || str_contains($title, 'filing')
            || str_contains($title, 'report')
        ) {
            return 'management';
        }

        if (
            sidebarMenuHasUrlPrefix($menu, '/modules/cbt_ops/')
            || sidebarMenuHasUrlPrefix($menu, '/cbt-ops/')
            || str_contains($title, 'supervisor')
            || str_contains($title, 'test ')
            || str_contains($title, 'monitoring')
        ) {
            return 'test_operations';
        }

        if (
            str_contains($title, 'security')
            || str_contains($title, 'setting')
            || sidebarMenuHasUrlPrefix($menu, '/modules/admin/system_settings')
            || sidebarMenuHasUrlPrefix($menu, '/modules/admin/system_access/audit')
            || sidebarMenuHasUrlPrefix($menu, '/admin/')
            || str_contains($title, 'user')
            || str_contains($title, 'role')
        ) {
            return 'system';
        }

        return 'management';
    }
}

if (! function_exists('renderSidebarSections')) {
    function renderSidebarSections(array $menu_array): void
    {
        $sections = [
            'main' => ['label' => 'MAIN', 'items' => []],
            'test_operations' => ['label' => 'TEST OPERATIONS', 'items' => []],
            'management' => ['label' => 'MANAGEMENT', 'items' => []],
            'system' => ['label' => 'SYSTEM', 'items' => []],
        ];

        foreach ($menu_array as $menu) {
            $section = sidebarMenuSection($menu);
            $sections[$section]['items'][] = $menu;
        }

        foreach ($sections as $section) {
            if (empty($section['items'])) {
                continue;
            }

            echo '<div class="sidebar-section">';
            echo '<div class="sidebar-section-label sidebar-text px-3 pt-3 pb-2 text-[10px] font-bold tracking-widest text-gray-400 uppercase">'.htmlspecialchars($section['label']).'</div>';
            echo '<div class="space-y-1">';
            renderSidebarMenu($section['items']);
            echo '</div>';
            echo '</div>';
        }
    }
}

if (! function_exists('renderSidebarMenu')) {
    function renderSidebarMenu(array $menu_array, int $level = 1): void
    {
        foreach ($menu_array as $menu) {
            $hasChildren = ! empty($menu['children']);
            $menuId = 'menu_'.(int) $menu['rec_id'];
            $isBranchActive = ! empty($menu['is_branch_active']);
            $isExactActive = ! empty($menu['is_exact_active']);
            $dbIcon = ! empty($menu['icon']) ? htmlspecialchars($menu['icon']) : '';
            $url = $menu['normalized_url'] ?? '#';
            $title = htmlspecialchars($menu['title'] ?? 'Menu');

            if ($level === 1) {
                $btnClass = $isBranchActive
                    ? 'bg-brand-primary text-white font-semibold shadow-md shadow-brand-primary/30'
                    : 'text-gray-600 hover:bg-gray-50 transition-colors';

                $iconClass = ($dbIcon ?: 'fas fa-cube').' w-5 text-center text-[15px]';
                $padClass = 'px-3 py-2.5 mb-1';
            } else {
                $btnClass = $isExactActive
                    ? 'bg-brand-primary text-white font-semibold shadow-sm rounded-lg'
                    : ($isBranchActive
                        ? 'text-brand-primary font-medium bg-brand-primary/5 rounded-lg'
                        : 'text-gray-500 hover:text-brand-primary hover:bg-gray-50 rounded-lg transition-colors');

                $iconClass = $dbIcon
                    ? ($dbIcon.' w-5 text-center text-[14px] '.($isExactActive ? 'text-white' : ($isBranchActive ? 'text-brand-primary' : 'text-gray-400')))
                    : ($isBranchActive
                        ? ($isExactActive
                            ? 'far fa-dot-circle text-[13px] w-5 text-center text-white'
                            : 'far fa-dot-circle text-[13px] w-5 text-center text-brand-primary')
                        : 'far fa-circle text-[12px] w-5 text-center text-gray-400');

                $pl = 5 + (($level - 1) * 2);
                $padClass = "py-2.5 pr-3 pl-{$pl} w-full text-left mb-0.5";
            }

            if ($hasChildren) {
                $rotation = $isBranchActive ? 'rotate-90' : '';
                $submenuClass = $isBranchActive ? '' : 'hidden';

                echo '<div>';
                // Gunakan justify-between agar panah terdorong ke kanan secara otomatis
                echo '<button type="button" onclick="toggleDropdown(\''.$menuId.'\')" class="menu-btn w-full flex items-center justify-between rounded-lg '.$padClass.' '.$btnClass.' transition-all duration-300" data-title="'.$title.'">';

                // Kontainer Kiri: Ikon + Teks
                echo '<div class="flex items-center gap-3 overflow-hidden">';
                echo '<i class="'.$iconClass.' shrink-0"></i>';
                // whitespace-nowrap penting agar teks tidak turun ke bawah saat sidebar mengecil/melebar
                echo '<span class="sidebar-text text-sm whitespace-nowrap transition-opacity duration-300">'.$title.'</span>';
                echo '</div>';

                // Kontainer Kanan: Ikon Panah (tambahkan ml-2 sebagai pengaman jarak)
                echo '<i id="icon-'.$menuId.'" class="sidebar-chevron fas fa-chevron-right text-[10px] ml-2 shrink-0 transition-transform duration-300 '.$rotation.'"></i>';
                echo '</button>';

                echo '<div id="'.$menuId.'" class="submenu '.$submenuClass.' mt-0.5 mb-1 space-y-0.5 overflow-hidden transition-all duration-300">';
                renderSidebarMenu($menu['children'], $level + 1);
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div>';
                echo '<a href="'.htmlspecialchars($url).'" class="menu-btn w-full flex items-center justify-between rounded-lg '.$padClass.' '.$btnClass.' group transition-all duration-300" data-title="'.$title.'">';

                // Kontainer Kiri: Ikon + Teks
                echo '<div class="flex items-center gap-3 overflow-hidden">';
                echo '<i class="'.$iconClass.' shrink-0"></i>';
                echo '<span class="sidebar-text text-sm whitespace-nowrap transition-opacity duration-300">'.$title.'</span>';
                echo '</div>';

                echo '</a>';
                echo '</div>';
            }
        }
    }
}

$sidebar_minimized = '';
if (isset($_COOKIE['sidebar_state']) && $_COOKIE['sidebar_state'] === 'minimized') {
    $sidebar_minimized = 'minimized';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RUN ITC Portal</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/runitc.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>

function toggleSidebar(event) {
    if (event) {
        event.stopPropagation();
    }
    const sidebar = document.getElementById('sidebar');
    if (window.matchMedia('(max-width: 767px)').matches) {
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
        return;
    }
    if (sidebar) {
        sidebar.classList.toggle('minimized');
        var isMinimized = sidebar.classList.contains('minimized');
        var val = isMinimized ? 'minimized' : 'expanded';
        document.cookie = 'sidebar_state=' + val + '; path=/';
        try { localStorage.setItem('sidebar_state', val); } catch(e) {}
    }
}

function handleSidebarToggle(event) {
    if (window.matchMedia('(max-width: 767px)').matches) {
        if (event) event.stopPropagation();
        closeMobileSidebar();
        return;
    }

    toggleSidebar(event);
}

function openMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const button = document.getElementById('mobileSidebarButton');
    if (!sidebar) return;

    sidebar.classList.add('mobile-open');
    sidebar.classList.remove('minimized');
    if (overlay) overlay.classList.remove('hidden');
    if (button) button.classList.add('mobile-sidebar-button-active');
    document.body.classList.add('sidebar-mobile-active');
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const button = document.getElementById('mobileSidebarButton');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.add('hidden');
    if (button) button.classList.remove('mobile-sidebar-button-active');
    document.body.classList.remove('sidebar-mobile-active');
}

function toggleMobileSidebar() {
    if (document.body.classList.contains('sidebar-mobile-active')) {
        closeMobileSidebar();
    } else {
        openMobileSidebar();
    }
}

        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            primary: '#1D4ED8',
                            primaryHover: '#1E40AF',
                            bg: '#f3f4f6',
                            card: '#ffffff',
                        }
                    }
                }
            }
        }

        function toggleDropdown(id) {
            const submenu = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);

            if (!submenu) return;

            submenu.classList.toggle('hidden');
            if (icon) icon.classList.toggle('rotate-90');
        }

        function toggleProfileMenu() {
            const profileMenu = document.getElementById('profileMenu');
            const profileChevron = document.getElementById('profileChevron');

            if (profileMenu) {
                profileMenu.classList.toggle('hidden');
                if (profileChevron) {
                    profileChevron.classList.toggle('is-open', !profileMenu.classList.contains('hidden'));
                }
            }
        }

        function toggleNotificationMenu() {
            const notificationMenu = document.getElementById('notificationMenu');
            if (notificationMenu) notificationMenu.classList.toggle('hidden');
        }

        const notificationConfig = {
            fetchUrl: <?php echo json_encode($notificationFetchUrl) ?>,
            readBaseUrl: <?php echo json_encode(rtrim(BASE_URL, '/').'/modules/notifications/read.php?id=') ?>,
            initialLatestId: <?php echo (int) $latest_notification_id ?>
        };

        let latestNotificationId = notificationConfig.initialLatestId;
        let notificationAudioAllowed = false;

        function escapeNotificationText(value) {
            return String(value ?? '').replace(/[&<>'"]/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#039;',
                    '"': '&quot;'
                }[char];
            });
        }

        function playNotificationTone() {
            if (!notificationAudioAllowed) return;

            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, context.currentTime);
                oscillator.frequency.setValueAtTime(1175, context.currentTime + 0.08);
                gain.gain.setValueAtTime(0.0001, context.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.24);
            } catch (e) {}
        }

        function renderNotificationList(notifications) {
            const list = document.getElementById('notificationList');
            if (!list) return;

            if (!notifications.length) {
                list.innerHTML = '<div class="px-4 py-8 text-center text-gray-400"><i class="far fa-bell-slash text-2xl mb-2"></i><p class="text-xs font-medium">Belum ada notifikasi.</p></div>';
                return;
            }

            list.innerHTML = notifications.map(function (notification) {
                const isUnread = Number(notification.is_read) === 0;
                const url = notificationConfig.readBaseUrl + encodeURIComponent(notification.rec_id);
                return '<a href="' + url + '" class="block px-4 py-3 hover:bg-gray-50 transition ' + (isUnread ? 'bg-blue-50/60' : 'bg-white') + '">' +
                    '<div class="flex gap-3">' +
                        '<div class="w-9 h-9 rounded-full ' + (isUnread ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500') + ' flex items-center justify-center shrink-0"><i class="fas fa-user-check text-xs"></i></div>' +
                        '<div class="min-w-0 flex-1">' +
                            '<p class="text-xs font-bold text-gray-800 leading-snug">' + escapeNotificationText(notification.title || 'Notifikasi') + '</p>' +
                            '<p class="text-xs text-gray-500 mt-1 leading-relaxed line-clamp-2">' + escapeNotificationText(notification.message || '') + '</p>' +
                            '<p class="text-[10px] text-gray-400 mt-1.5 font-medium">' + escapeNotificationText(notification.created_at || '') + '</p>' +
                        '</div>' +
                        (isUnread ? '<span class="w-2 h-2 rounded-full bg-blue-600 mt-1.5 shrink-0"></span>' : '') +
                    '</div>' +
                '</a>';
            }).join('');
        }

        function updateNotificationUi(payload) {
            const count = Number(payload.unread_count || 0);
            const badge = document.getElementById('notificationBadge');
            const badgeText = document.getElementById('notificationBadgeText');
            const markAllTop = document.getElementById('notificationMarkAllTop');
            const footerStatus = document.getElementById('notificationFooterStatus');

            if (badge && badgeText) {
                badgeText.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('hidden', count <= 0);
                badge.classList.toggle('flex', count > 0);
            }

            if (markAllTop) markAllTop.classList.toggle('hidden', count <= 0);

            if (footerStatus) {
                footerStatus.textContent = count > 0 ? count + ' baru' : 'Tidak ada notifikasi baru';
                footerStatus.className = count > 0
                    ? 'text-[10px] font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full'
                    : 'text-[10px] font-medium text-gray-400';
            }

            renderNotificationList(payload.notifications || []);
        }

        function fetchNotifications() {
            if (document.hidden) return;

            fetch(notificationConfig.fetchUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.success) return;

                    const newLatestId = Number(payload.latest_id || 0);
                    if (latestNotificationId > 0 && newLatestId > latestNotificationId) {
                        playNotificationTone();
                    }
                    latestNotificationId = Math.max(latestNotificationId, newLatestId);
                    updateNotificationUi(payload);
                })
                .catch(function () {});
        }

        document.addEventListener('click', function () {
            notificationAudioAllowed = true;
        }, { once: true });

        setInterval(fetchNotifications, 20000);



        document.addEventListener('click', function (event) {
            const profileMenu = document.getElementById('profileMenu');
            const profileButton = document.getElementById('profileButton');
            const notificationMenu = document.getElementById('notificationMenu');
            const notificationButton = document.getElementById('notificationButton');

            if (notificationMenu && notificationButton && !notificationButton.contains(event.target) && !notificationMenu.contains(event.target)) {
                notificationMenu.classList.add('hidden');
            }

            if (!profileMenu || !profileButton) return;

            if (!profileButton.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.add('hidden');
                const profileChevron = document.getElementById('profileChevron');
                if (profileChevron) profileChevron.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMobileSidebar();
        });
    </script>

    <style>
/* Sidebar transition — click toggle, not hover */
#sidebar {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 80px;
    overflow: hidden;
}
#sidebar:not(.minimized) {
    width: 260px;
}
#sidebar .sidebar-text,
#sidebar .sidebar-chevron {
    opacity: 0;
    transition: opacity 0.2s;
    white-space: nowrap;
}
#sidebar:not(.minimized) .sidebar-text,
#sidebar:not(.minimized) .sidebar-chevron {
    opacity: 1;
}
#sidebar .menu-btn {
    justify-content: flex-start;
    width: 100%;
    min-width: 0;
}
#sidebar.minimized .submenu {
    display: none !important;
}

#sidebar.minimized .sidebar-section-label,
#sidebar.minimized .sidebar-footer {
    display: none;
}

#sidebar .menu-container {
    overflow-x: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

#sidebar .menu-container::-webkit-scrollbar {
    display: none;
}

.sidebar-tooltip {
    display: none;
    position: fixed;
    padding: 6px 12px;
    background: #1f2937;
    color: #fff;
    font-size: 12px;
    font-weight: 500;
    border-radius: 6px;
    white-space: nowrap;
    z-index: 99999;
    pointer-events: none;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
}
.sidebar-tooltip::before {
    content: '';
    position: absolute;
    right: 100%;
    top: 50%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-right-color: #1f2937;
}

#profileChevron {
    display: inline-block;
    transform: rotate(0deg);
    transition: transform 0.2s ease-in-out;
}

#profileChevron.is-open {
    transform: rotate(180deg);
}

@media (max-width: 767px) {
    body.sidebar-mobile-active {
        overflow: hidden;
    }

    #mobileSidebarButton.mobile-sidebar-button-active {
        position: relative;
        z-index: 70;
    }

    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 60;
        display: flex;
        background: #ffffff;
        width: 280px;
        max-width: 86vw;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #sidebar.mobile-open {
        transform: translateX(0);
    }

    #sidebar .sidebar-text,
    #sidebar .sidebar-chevron {
        opacity: 1;
    }

    #sidebar.minimized .submenu,
    #sidebar.minimized .sidebar-section-label,
    #sidebar.minimized .sidebar-footer {
        display: block;
    }
}


    </style>
</head>

<body class="bg-brand-bg text-gray-600 font-sans h-screen flex flex-col overflow-hidden">

<div id="global-loader" class="fixed inset-0 z-[99999] bg-brand-bg flex flex-col items-center justify-center transition-opacity duration-300">
    <div class="flex space-x-2 justify-center items-center mb-3 h-8">
        <div class="h-3 w-3 bg-brand-primary rounded-full animate-bounce" style="animation-delay: -0.3s;"></div>
        <div class="h-3 w-3 bg-brand-primary rounded-full animate-bounce" style="animation-delay: -0.15s;"></div>
        <div class="h-3 w-3 bg-brand-primary rounded-full animate-bounce"></div>
    </div>
    <span class="text-xs font-bold text-gray-500 tracking-wider uppercase">Memuat...</span>
</div>
<script>
    (function() {
        function hideGlobalLoader() {
            const loader = document.getElementById('global-loader');
            if (!loader) return;
            loader.style.opacity = '0';
            setTimeout(function() { loader.style.display = 'none'; }, 300);
        }

        document.addEventListener('DOMContentLoaded', hideGlobalLoader);
        setTimeout(hideGlobalLoader, 2000);
    })();
</script>

<header class="w-full h-[70px] bg-brand-card shadow-sm px-4 sm:px-6 flex justify-between items-center z-[70] shrink-0 border-b border-gray-200">
    <div class="flex items-center gap-3">
        <button id="mobileSidebarButton" type="button" onclick="toggleMobileSidebar()" class="md:hidden w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 transition" aria-label="Buka menu">
            <i class="fas fa-bars"></i>
        </button>
        <img src="<?php echo rtrim(BASE_URL, '/') ?>/assets/images/RUNITC_LOGO.png" alt="Logo" class="h-8 object-contain">
    </div>

    <div class="flex items-center gap-4 relative">
        <button id="notificationButton" onclick="toggleNotificationMenu()" class="relative w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 transition">
            <i class="far fa-bell"></i>
            <span id="notificationBadge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold <?php echo $notification_count > 0 ? 'flex' : 'hidden' ?> items-center justify-center border-2 border-white">
                <span id="notificationBadgeText">
                    <?php echo $notification_count > 99 ? '99+' : (int) $notification_count ?>
                </span>
            </span>
        </button>

        <div id="notificationMenu" class="hidden absolute right-14 top-12 bg-white shadow-xl rounded-xl w-80 z-50 border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <p class="text-sm font-bold text-gray-800">Notifikasi</p>
                <a id="notificationMarkAllTop" href="<?php echo htmlspecialchars($markAllReadUrl) ?>" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 bg-blue-50 px-2 py-1 rounded-full transition <?php echo $notification_count > 0 ? '' : 'hidden' ?>">
                        Tandai semua dibaca
                </a>
            </div>
            <div id="notificationList" class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                <?php if (empty($notifications)) { ?>
                    <div class="px-4 py-8 text-center text-gray-400">
                        <i class="far fa-bell-slash text-2xl mb-2"></i>
                        <p class="text-xs font-medium">Belum ada notifikasi.</p>
                    </div>
                <?php } else { ?>
                    <?php foreach ($notifications as $notification) { ?>
                        <?php
                            $notificationUrl = rtrim(BASE_URL, '/').'/modules/notifications/read.php?id='.(int) $notification['rec_id'];
                        $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
                        ?>
                        <a href="<?php echo htmlspecialchars($notificationUrl) ?>" class="block px-4 py-3 hover:bg-gray-50 transition <?php echo $isUnread ? 'bg-blue-50/60' : 'bg-white' ?>">
                            <div class="flex gap-3">
                                <div class="w-9 h-9 rounded-full <?php echo $isUnread ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500' ?> flex items-center justify-center shrink-0">
                                    <i class="fas fa-user-check text-xs"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-gray-800 leading-snug"><?php echo htmlspecialchars($notification['title'] ?? 'Notifikasi') ?></p>
                                    <p class="text-xs text-gray-500 mt-1 leading-relaxed line-clamp-2"><?php echo htmlspecialchars($notification['message'] ?? '') ?></p>
                                    <p class="text-[10px] text-gray-400 mt-1.5 font-medium"><?php echo htmlspecialchars(date('d M Y H:i', strtotime((string) $notification['created_at']))) ?></p>
                                </div>
                                <?php if ($isUnread) { ?>
                                    <span class="w-2 h-2 rounded-full bg-blue-600 mt-1.5 shrink-0"></span>
                                <?php } ?>
                            </div>
                        </a>
                    <?php } ?>
                <?php } ?>
            </div>
            <div class="border-t border-gray-100 bg-gray-50 px-4 py-3 flex items-center justify-between">
                <span id="notificationFooterStatus" class="text-[10px] <?php echo $notification_count > 0 ? 'font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full' : 'font-medium text-gray-400' ?>">
                    <?php echo $notification_count > 0 ? (int) $notification_count.' baru' : 'Tidak ada notifikasi baru' ?>
                </span>
                <a href="<?php echo htmlspecialchars($allNotificationsUrl) ?>" class="text-xs font-bold text-brand-primary hover:text-brand-primaryHover transition">
                    Lihat semua
                </a>
            </div>
        </div>

        <div class="h-8 w-px bg-gray-200"></div>

        <button id="profileButton" onclick="toggleProfileMenu()" class="flex items-center gap-3 text-gray-600 hover:text-gray-800 transition">
            <div class="w-10 h-10 rounded-full border-2 border-white shadow-sm overflow-hidden bg-gray-100 shrink-0">
                <img src="<?php echo $photo_url ?>" alt="Profile" class="w-full h-full object-cover">
            </div>
            <div class="hidden sm:block text-left leading-tight max-w-44">
                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($user_name) ?></p>
                <p class="text-[11px] text-gray-500 truncate"><?php echo htmlspecialchars($user_role_division) ?></p>
            </div>
            <i id="profileChevron" class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200"></i>
        </button>

        <div id="profileMenu" class="hidden absolute right-0 top-12 bg-white shadow-lg rounded-lg w-64 z-50">
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user_name) ?></p>
                <p class="text-xs text-gray-500 mt-1 leading-snug"><?php echo htmlspecialchars($user_role_division) ?></p>
            </div>
            <div class="px-4 py-2 pb-1 border-t border-gray-100">
                <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/profile/index.php" class="flex items-center justify-center gap-2 w-full bg-brand-primary hover:bg-brand-primaryHover text-white text-sm font-medium py-2 rounded-lg mb-2">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="<?php echo rtrim(BASE_URL, '/') ?>/modules/auth/logout.php" class="flex items-center justify-center gap-2 w-full bg-[#ff4c51] hover:bg-[#e64449] text-white text-sm font-medium py-2 rounded-lg">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<div class="flex-1 flex overflow-hidden relative">
<div id="sidebarOverlay" onclick="closeMobileSidebar()" class="hidden fixed inset-0 z-50 bg-black/40 md:hidden"></div>
<aside id="sidebar" class="<?php echo $sidebar_minimized; ?> w-[80px] bg-brand-card shadow-sm flex-col md:flex z-20 border-r border-gray-200 transition-all duration-300">

    <div class="h-[70px] flex items-center px-4 border-b border-gray-100 shrink-0">
        <button type="button" onclick="handleSidebarToggle(event)" class="w-full flex items-center gap-3 px-2 py-2 text-gray-600 hover:text-brand-primary transition-colors cursor-pointer" data-title="Toggle Menu">
            <i class="fas fa-bars text-lg w-5 text-center shrink-0"></i>
            <span class="sidebar-text text-sm font-semibold whitespace-nowrap">Menu</span>
        </button>
    </div>

    <div class="menu-container flex-1 overflow-y-auto overflow-x-hidden py-4 px-3 space-y-4">
        <?php renderSidebarSections($menu_tree); ?>
    </div>

    <div class="sidebar-footer p-3 border-t border-gray-100 space-y-3 shrink-0">
        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-3 shadow-sm" data-title="<?php echo htmlspecialchars($user_role_division) ?>">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-brand-primary flex items-center justify-center shrink-0">
                <i class="fas fa-shield-alt text-sm"></i>
            </div>
            <div class="sidebar-text min-w-0 flex-1">
                <p class="text-xs font-bold text-gray-700 truncate"><?php echo htmlspecialchars($user_role_division) ?></p>
                <p class="text-[10px] text-gray-400 truncate">Active Division</p>
            </div>
            <i class="sidebar-chevron fas fa-chevron-right text-[10px] text-gray-400"></i>
        </div>

        <a href="#" class="flex items-center gap-3 px-3 py-2 text-gray-500 hover:text-brand-primary rounded-lg hover:bg-gray-50 transition" data-title="Help &amp; Support">
            <i class="far fa-circle-question w-5 text-center text-sm shrink-0"></i>
            <span class="sidebar-text text-xs font-medium">Help &amp; Support</span>
            <i class="sidebar-chevron fas fa-chevron-right text-[10px] ml-auto"></i>
        </a>
    </div>

</aside>

<script>
(function() {
    var el = document.getElementById('sidebar');
    if (!el) return;
    var val = null;
    try { val = localStorage.getItem('sidebar_state'); } catch(e) {}
    if (!val) {
        var m = document.cookie.match(/(?:^|;\s*)sidebar_state=(minimized|expanded)/);
        if (m) val = m[1];
    }
    if (val === 'minimized') {
        el.classList.add('minimized');
    } else if (val === 'expanded') {
        el.classList.remove('minimized');
    }
})();
$(document).on('click', '#sidebar a[href]', function(event) {
    var href = this.getAttribute('href') || '';
    if (href && href !== '#' && !/\.php(?:$|[?#])|\.[a-z0-9]{2,5}(?:$|[?#])/i.test(href)) {
        try {
            var url = new URL(href, window.location.origin);
            if (url.origin === window.location.origin) {
                // Path modern Laravel (memiliki route sendiri) jangan ditambah .php
                var modernPrefixes = ['/cbt-ops', '/admin', '/filing-system', '/forgot-password', '/verify-otp', '/reset-password', '/register', '/login', '/dashboard'];
                var isModern = false;
                for (var i = 0; i < modernPrefixes.length; i++) {
                    if (url.pathname === modernPrefixes[i] || url.pathname.indexOf(modernPrefixes[i] + '/') === 0) {
                        isModern = true;
                        break;
                    }
                }
                if (isModern) {
                    window.location.href = url.toString();
                    return;
                }
                event.preventDefault();
                var path = url.pathname.replace(/\/+$/, '');
                url.pathname = /\/index$/i.test(path) ? path + '.php' : path + '.php';
                window.location.href = url.toString();
                return;
            }
        } catch (e) {}
    }

    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    if (window.matchMedia('(max-width: 767px)').matches) {
        closeMobileSidebar();
        return;
    }
    var isMin = sidebar.classList.contains('minimized');
    var val = isMin ? 'minimized' : 'expanded';
    document.cookie = 'sidebar_state=' + val + '; path=/';
    try { localStorage.setItem('sidebar_state', val); } catch(e) {}
});
$(document).on('mouseenter', '#sidebar.minimized [data-title]', function() {
    var $tip = $('#sidebar-tooltip');
    if (!$tip.length) {
        $tip = $('<div id="sidebar-tooltip" class="sidebar-tooltip"></div>').appendTo('body');
    }
    $tip.text($(this).attr('data-title'));
    var $el = $(this);
    var pos = $el.offset();
    var tipH = $tip.outerHeight();
    var elH = $el.outerHeight();
    $tip.css({
        left: pos.left + $el.outerWidth() + 10,
        top: pos.top + (elH / 2) - (tipH / 2)
    }).show();
}).on('mouseleave', '#sidebar.minimized [data-title]', function() {
    $('#sidebar-tooltip').hide();
});
</script>

    <main id="main-content-area" class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 md:p-8 relative bg-brand-bg">
