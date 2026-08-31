<?php

// File: includes/menu_guard.php
// Helper untuk memvalidasi akses user ke halaman tertentu berdasarkan sys_menu_access

/**
 * Cek apakah user yang sedang login memiliki akses ke URL menu tertentu.
 * Jika tidak punya akses, tampilkan halaman 403 dan hentikan eksekusi.
 *
 * @param  PDO  $pdo  Koneksi database
 * @param  string  $menuUrl  URL menu yang akan dicek (relatif dari root, misal: 'modules/admin/system_access/roles.php')
 */
function requireMenuAccess(PDO $pdo, string $menuUrl): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        header('Location: '.rtrim(BASE_URL, '/').'/index.php');
        exit;
    }

    // Cek apakah menu ini ada dan bersifat global
    $stmtMenu = $pdo->prepare('
        SELECT rec_id, is_global
        FROM sys_menus
        WHERE url = ? AND is_active = 1
        LIMIT 1
    ');
    $stmtMenu->execute([$menuUrl]);
    $menu = $stmtMenu->fetch(PDO::FETCH_ASSOC);

    // Jika menu tidak ditemukan di database, izinkan akses (belum terdaftar)
    if (! $menu) {
        return;
    }

    // Jika menu bersifat global, semua user boleh akses
    if ((int) $menu['is_global'] === 1) {
        return;
    }

    // Cek apakah user punya akses via sys_menu_access
    $stmtAccess = $pdo->prepare('
        SELECT COUNT(*) FROM sys_menu_access sma
        JOIN sysitc_grpacc g ON g.rec_id = sma.grpacc_id
        JOIN sysitc_usracc ua ON ua.access_code = g.grpaccess AND ua.access_account = g.grpacc
        WHERE sma.menu_id = ? AND ua.user_rec_id = ?
    ');
    $stmtAccess->execute([(int) $menu['rec_id'], $userId]);

    if ((int) $stmtAccess->fetchColumn() > 0) {
        return; // User punya akses
    }

    // Akses ditolak
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
            <div class="text-6xl text-red-500 mb-4"><i class="fas fa-shield-halved"></i></div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Akses Ditolak!</h2>
            <p class="text-gray-600 mb-6">Maaf, akun Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
            <a href="'.rtrim(BASE_URL, '/').'/dashboard.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </body>
    </html>
    ');
}

// ==========================================
// SUPERADMIN GUARD
// ==========================================
//
// DEVELOPMENT: guard disabled. Flip to true before production.
//
if (! defined('SUPERADMIN_GUARD_ENABLED')) {
    define('SUPERADMIN_GUARD_ENABLED', true); // Menjaga halaman admin dari non-superadmin
}

/**
 * Cek apakah user yang sedang login adalah Superadmin (tanpa guard flag).
 * Fungsi ini selalu aktif — digunakan untuk routing, bukan untuk guard.
 *
 * @param  PDO|null  $pdo  Koneksi database
 */
function isSuperadmin(?PDO $pdo): bool
{
    if (! $pdo) {
        return false;
    }
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sysitc_usracc ua
        JOIN sysitc_grpacc g
            ON g.grpaccess = ua.access_code
           AND g.grpacc = ua.access_account
        WHERE ua.user_rec_id = ?
          AND g.grpaccess = '03'
          AND g.grpacc = '999'
          AND g.grpdesc LIKE '%SUPER%ADMIN%'
    ");
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn() > 0;
}
//
// Superadmin didefinisikan sebagai:
//   sysitc_grpacc.grpaccess = '03'
//   AND sysitc_grpacc.grpacc = '999'
//   AND sysitc_grpacc.grpdesc LIKE '%SUPER%ADMIN%'

/**
 * Cek apakah user yang sedang login adalah Superadmin.
 * Jika tidak, tampilkan 403 dan hentikan eksekusi.
 *
 * @param  PDO|null  $pdo  Koneksi database
 */
function requireSuperadmin(?PDO $pdo): void
{
    if (! SUPERADMIN_GUARD_ENABLED) {
        return;
    }

    if (isSuperadmin($pdo)) {
        return;
    }

    http_response_code(403);
    exit('
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 - Akses Superadmin Ditolak</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-100 h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md border-t-4 border-red-500">
            <div class="text-6xl text-red-500 mb-4"><i class="fas fa-shield-halved"></i></div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Akses Superadmin Ditolak!</h2>
            <p class="text-gray-600 mb-6">Halaman ini hanya dapat diakses oleh pengguna dengan role Superadmin.</p>
            <a href="'.rtrim(BASE_URL, '/').'/dashboard.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </body>
    </html>
    ');
}

/**
 * Hitung jumlah Superadmin aktif.
 * Dipakai untuk melindungi role kritikal (3.3) — bukan guard auth.
 *
 * @param  PDO  $pdo  Koneksi database
 * @return int Jumlah Superadmin aktif
 */
function countActiveSuperadmins(?PDO $pdo): int
{
    if (! $pdo) {
        return 0;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ua.user_rec_id)
        FROM sysitc_usracc ua
        JOIN sysitc_grpacc g
            ON g.grpaccess = ua.access_code
           AND g.grpacc = ua.access_account
        JOIN sysitc_users u
            ON u.rec_id = ua.user_rec_id
        WHERE g.grpaccess = '03'
          AND g.grpacc = '999'
          AND g.grpdesc LIKE '%SUPER%ADMIN%'
          AND u.status = 1
    ");
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}
