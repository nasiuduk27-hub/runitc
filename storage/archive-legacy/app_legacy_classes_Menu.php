<?php

// File: classes/Menu.php

class Menu
{
    private PDO $db;

    private string $baseFolder;

    public function __construct(PDO $pdo, string $baseFolder = '')
    {
        $this->db = $pdo;
        $this->baseFolder = rtrim($baseFolder, '/');
    }

    public function getAccessibleMenus(array $userGroups, $statrec = null): array
    {
        try {
            if (empty($userGroups)) {
                $sql = 'SELECT DISTINCT m.*
                        FROM sys_menus m
                        WHERE m.is_active = 1
                          AND m.is_global = 1
                          AND (m.min_statrec IS NULL OR (? IS NOT NULL AND m.min_statrec <= ? AND m.max_statrec >= ?))
                        ORDER BY m.mst_id ASC, m.sort_order ASC';

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$statrec, $statrec, $statrec]);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $placeholders = implode(',', array_fill(0, count($userGroups), '?'));

            $sql = "SELECT DISTINCT m.*
                    FROM sys_menus m
                    LEFT JOIN sys_menu_access acc ON m.rec_id = acc.menu_id
                    WHERE m.is_active = 1
                      AND (
                            m.is_global = 1 
                            OR acc.grpacc_id IN ($placeholders)
                          )
                      AND (m.min_statrec IS NULL OR (? IS NOT NULL AND m.min_statrec <= ? AND m.max_statrec >= ?))
                    ORDER BY m.mst_id ASC, m.sort_order ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($userGroups, [$statrec, $statrec, $statrec]));

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Menu query failed: '.$e->getMessage());

            return [];
        }
    }

    public function getMenuTree(array $userGroups, string $currentPath = '/', $statrec = null): array
    {
        $rawMenus = $this->getAccessibleMenus($userGroups, $statrec);

        return $this->buildMenuTree($rawMenus, 0, $currentPath);
    }

    public function normalizeMenuPath(string $url): string
    {
        $url = trim($url);

        if ($url === '' || $url === '#') {
            return '#';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $url = preg_replace('#^(\./|\../)+#', '', $url);

        if ($this->baseFolder !== '' && strpos($url, $this->baseFolder.'/') === 0) {
            return $this->ensurePhpExtension(preg_replace('#/+#', '/', $url));
        }

        if (strpos($url, '/') === 0) {
            return $this->ensurePhpExtension(preg_replace('#/+#', '/', $this->baseFolder.'/'.ltrim($url, '/')));
        }

        return $this->ensurePhpExtension(preg_replace('#/+#', '/', $this->baseFolder.'/'.ltrim($url, '/')));
    }

    private function ensurePhpExtension(string $url): string
    {
        $parts = explode('?', $url, 2);
        $path = $parts[0];
        $query = isset($parts[1]) ? '?'.$parts[1] : '';

        if (preg_match('#\.php$#i', $path) || preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
            return $url;
        }

        $relativePath = $path;
        if ($this->baseFolder !== '' && (strpos($relativePath, $this->baseFolder.'/') === 0 || $relativePath === $this->baseFolder)) {
            $relativePath = substr($relativePath, strlen($this->baseFolder));
        }

        $relativePath = '/'.trim($relativePath, '/');
        if ($relativePath !== '/' && defined('BASE_PATH') && file_exists(BASE_PATH.$relativePath.'.php')) {
            return $path.'.php'.$query;
        }

        if ($relativePath !== '/' && defined('BASE_PATH') && file_exists(BASE_PATH.$relativePath.'/index.php')) {
            return rtrim($path, '/').'/index.php'.$query;
        }

        return $url;
    }

    public function getCurrentPath(): string
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $currentPath = preg_replace('#/+#', '/', $currentPath);
        $currentPath = rtrim(str_ireplace(['/index.php', 'index.php', '.php'], '', $currentPath), '/');

        return $currentPath === '' ? '/' : $currentPath;
    }

    private function buildMenuTree(array $elements, int $parentId = 0, string $currentPath = '/'): array
    {
        $branch = [];

        foreach ($elements as $element) {
            if ((int) $element['mst_id'] !== $parentId) {
                continue;
            }

            $children = $this->buildMenuTree($elements, (int) $element['rec_id'], $currentPath);

            $dbUrl = trim($element['url'] ?? '');
            $normalized = $this->normalizeMenuPath($dbUrl);

            $element['normalized_url'] = $normalized;
            $element['children'] = $children ?: [];
            $element['is_exact_active'] = $this->isExactActive($normalized, $currentPath);
            $element['is_branch_active'] = $element['is_exact_active'];

            foreach ($element['children'] as $child) {
                if (! empty($child['is_branch_active'])) {
                    $element['is_branch_active'] = true;
                    break;
                }
            }

            $branch[$element['rec_id']] = $element;
        }

        return $branch;
    }

    private function isExactActive(string $normalizedUrl, string $currentPath): bool
    {
        if ($normalizedUrl === '#') {
            return false;
        }

        $menuPath = parse_url($normalizedUrl, PHP_URL_PATH) ?: '/';
        $menuPath = preg_replace('#/+#', '/', $menuPath);
        $menuPath = rtrim(str_ireplace(['/index.php', 'index.php', '.php'], '', $menuPath), '/');

        if ($menuPath === '') {
            $menuPath = '/';
        }

        if ($menuPath === '/' && $currentPath === '/') {
            return true;
        }

        return $menuPath === $currentPath;
    }

    public function render(array $menuArray, int $level = 1): void
    {
        foreach ($menuArray as $menu) {
            $hasChildren = ! empty($menu['children']);
            $isExactActive = ! empty($menu['is_exact_active']);
            $isBranchActive = ! empty($menu['is_branch_active']);

            $activeClass = $isExactActive ? 'bg-brand-primary text-white shadow-md shadow-brand-primary/20' : 'text-gray-600 hover:bg-brand-primary/5 hover:text-brand-primary';
            $iconClass = ($menu['icon'] ?: 'fas fa-cube').' w-5 text-center text-lg '.($isExactActive ? 'text-white' : 'text-brand-primary');
            $submenuClass = $isBranchActive ? '' : 'hidden';
            $chevronRotation = $isBranchActive ? 'rotate-90' : '';

            if ($level === 1) {
                $containerClass = 'mb-1';
                $padClass = 'px-3 py-2.5';
            } else {
                $containerClass = 'mb-0.5';
                $padClass = 'pl-11 pr-3 py-2';
            }

            echo '<div class="'.$containerClass.'">';

            if ($hasChildren) {
                echo '<button onclick="toggleDropdown(this)" class="menu-btn w-full flex items-center justify-between '.$padClass.' rounded-lg transition-all '.$activeClass.' group">';
                echo '    <div class="flex items-center gap-3">';
                echo '        <i class="'.$iconClass.'"></i>';
                echo '        <span class="sidebar-text text-sm font-medium">'.htmlspecialchars($menu['title'] ?? '').'</span>';
                echo '    </div>';
                echo '    <i class="fas fa-chevron-right sidebar-chevron text-[10px] transition-transform duration-200 '.$chevronRotation.'"></i>';
                echo '</button>';

                echo '<div class="submenu mt-1 space-y-1 '.$submenuClass.'">';
                $this->render($menu['children'], $level + 1);
                echo '</div>';
            } else {
                echo '<a href="'.htmlspecialchars($menu['normalized_url'] ?? '#').'" class="menu-btn flex items-center gap-3 '.$padClass.' rounded-lg transition-all '.$activeClass.' group">';
                echo '    <i class="'.$iconClass.'"></i>';
                echo '    <span class="sidebar-text text-sm font-medium">'.htmlspecialchars($menu['title'] ?? '').'</span>';
                echo '</a>';
            }

            echo '</div>';
        }
    }
}
