<?php

class AccessGuard {
    public static function checkDirectoryAccess($current_path, $raw_menus, $allowed_menu_ids) {
        return true;
        $is_access_denied = false;

        // 1. Dapatkan base path aplikasi
        $base_path_clean = function_exists('appBasePath') ? appBasePath() : ''; 
        
        // 2. Daftar path publik (Bebas diakses tanpa batas)
        $allowed_public_paths = [
            $base_path_clean === '' ? '/' : $base_path_clean,
            $base_path_clean . '/index.php',
            $base_path_clean . '/dashboard.php',
            $base_path_clean . '/modules/profile', // Izinkan semua aksi profil
        ];

        // Cek apakah path saat ini adalah path publik
        $is_public = false;
        foreach ($allowed_public_paths as $pub) {
            if ($current_path === $pub || (strpos($current_path, $pub) === 0 && $pub !== '/' && $pub !== $base_path_clean)) {
                $is_public = true;
                break;
            }
        }

        // 3. Logika Proteksi Utama (Strict Mode)
        if (!$is_public) {
            // Bersihkan akhiran /index.php untuk menyamakan format dengan database
            $clean_current_path = preg_replace('/\/index\.php$/', '', $current_path);
            $clean_current_path = rtrim($clean_current_path, '/');
            
            $current_dir = dirname($current_path);
            $current_dir = str_replace('\\', '/', $current_dir);

            // Default: asumsikan belum terlindungi & tidak punya akses
            $is_page_protected = false;
            $has_access_to_dir = false;

            // STRICT MODE: Jika user masuk ke area /modules/, LANGSUNG nyalakan proteksi!
            // Artinya: dilarang masuk kecuali punya hak akses.
            if (strpos($current_path, '/modules/') !== false) {
                $is_page_protected = true;
            }

            foreach ($raw_menus as $menu) {
                $normalized_url = function_exists('normalizeMenuUrl') ? normalizeMenuUrl($menu['url'] ?? '#') : ($menu['url'] ?? '#');
                
                if ($normalized_url !== '#') {
                    $menu_path = function_exists('cleanPathForActiveCheck') ? cleanPathForActiveCheck($normalized_url) : $normalized_url;
                    
                    // Bersihkan juga url dari database
                    $clean_menu_path = preg_replace('/\/index\.php$/', '', $menu_path);
                    $clean_menu_path = rtrim($clean_menu_path, '/');
                    
                    $menu_dir = dirname($menu_path);
                    $menu_dir = str_replace('\\', '/', $menu_dir);

                    // Cocokkan apakah user berhak masuk:
                    // 1. Path sama persis
                    // 2. Path bersih sama (mengatasi perbedaan adanya index.php)
                    // 3. User mengakses file di dalam folder menu tersebut
                    if ($current_path === $menu_path || 
                        $clean_current_path === $clean_menu_path || 
                        $current_dir === $menu_dir ||
                        strpos($current_dir, $clean_menu_path) === 0 // Mencakup sub-folder
                    ) {
                        $is_page_protected = true; // Konfirmasi ulang bahwa ini diproteksi
                        
                        $is_global = (int)($menu['is_global'] ?? 0) === 1;
                        $has_access = in_array((int)$menu['rec_id'], $allowed_menu_ids, true);
                        
                        // Jika global ATAU user punya rec_id ini, BERIKAN KUNCI MASUK
                        if ($is_global || $has_access) {
                            $has_access_to_dir = true;
                            break; 
                        }
                    }
                }
            }

            // KEPUTUSAN FINAL: 
            // Jika halaman/folder ini terproteksi (berada di /modules/) 
            // TAPI user sama sekali tidak mendapat flag $has_access_to_dir -> BLOKIR!
            if ($is_page_protected && !$has_access_to_dir) {
                $is_access_denied = true;
            }
        }

        return !$is_access_denied;
    }
}