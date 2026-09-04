<?php

namespace Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Gerbang untuk Fase 2 (mengarsipkan file legacy yang membayangi route Laravel).
 *
 * Di produksi, .htaccess:10-12 melayani file legacy lebih dulu kalau file itu
 * ada di disk, sehingga route Laravel di bawah ini tidak pernah dieksekusi.
 * Test ini memanggil kernel Laravel langsung, jadi bisa memverifikasi route
 * pengganti SEBELUM file legacy-nya dihapus.
 *
 * Aturan lulus: response tidak boleh 5xx dan tidak boleh 404.
 * Isi halaman tidak dibandingkan byte-per-byte karena render legacy dan Blade
 * memang berbeda; yang diuji adalah "route ini hidup dan tidak meledak".
 */
#[Group('smoke')]
class LegacyRouteParityTest extends SmokeTestCase
{
    /**
     * Path legacy yang sudah punya route Laravel DAN file legacy-nya masih ada.
     * Dikelompokkan sesuai urutan batch Fase 2 (risiko kecil lebih dulu).
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function legacyGetRoutes(): array
    {
        return [
            // --- Batch 1: notifications (4 file, 135 baris) ---
            'notifications index' => ['notifications', '/modules/notifications/index.php', []],
            'notifications fetch' => ['notifications', '/modules/notifications/fetch.php', []],
            'notifications mark all read' => ['notifications', '/modules/notifications/mark_all_read.php', []],

            // --- Batch 2: profile ---
            'profile index' => ['profile', '/modules/profile/index.php', []],

            // --- Batch 3: admin (14 file) ---
            'admin dashboard' => ['admin', '/modules/admin/dashboard.php', []],
            'admin operational dashboard' => ['admin', '/modules/admin/operational_dashboard.php', []],
            'admin reporting' => ['admin', '/modules/admin/reporting.php', []],
            'admin system health' => ['admin', '/modules/admin/system_health.php', []],
            'admin system settings' => ['admin', '/modules/admin/system_settings.php', []],
            'admin audit log' => ['admin', '/modules/admin/system_access/audit_log.php', []],
            'admin menu management' => ['admin', '/modules/admin/system_access/menu_management.php', []],
            'admin permissions' => ['admin', '/modules/admin/system_access/permissions.php', []],
            'admin role menu' => ['admin', '/modules/admin/system_access/role_menu.php', []],
            'admin roles' => ['admin', '/modules/admin/system_access/roles.php', []],
            'admin active users' => ['admin', '/modules/admin/system_access/user_list_active.php', []],
            'admin user role' => ['admin', '/modules/admin/system_access/user_role.php', []],
            'admin users' => ['admin', '/modules/admin/system_access/users.php', []],

            // --- Batch 4: cbt_ops test_plan & test_admin ---
            'test plan index' => ['cbt_ops', '/modules/cbt_ops/test_plan/index.php', []],
            'test plan create' => ['cbt_ops', '/modules/cbt_ops/test_plan/create.php', []],
            'test admin index' => ['cbt_ops', '/modules/cbt_ops/test_admin/index.php', []],
        ];
    }

    #[DataProvider('legacyGetRoutes')]
    public function test_laravel_route_can_serve_legacy_path(string $batch, string $path, array $query): void
    {
        $response = $this->asSuperadmin()->get($path.($query ? '?'.http_build_query($query) : ''));

        $status = $response->getStatusCode();

        $this->assertLessThan(
            500,
            $status,
            "[{$batch}] {$path} mengembalikan {$status}. Route Laravel error, file legacy belum boleh diarsipkan.\n"
            .$this->extractError($response->getContent())
        );

        $this->assertNotSame(
            404,
            $status,
            "[{$batch}] {$path} mengembalikan 404. Route Laravel tidak terdaftar."
        );

        $this->assertContains(
            $status,
            [200, 301, 302],
            "[{$batch}] {$path} mengembalikan {$status}, diharapkan 200, 301, atau 302."
        );
    }

    /**
     * Route legacy yang butuh parameter. Tanpa parameter valid, 302/404 wajar;
     * yang tidak boleh adalah 500 (berarti kode meledak, bukan menolak).
     *
     * @return array<string, array{0: string}>
     */
    public static function parameterisedLegacyRoutes(): array
    {
        return [
            'test plan edit tanpa id' => ['/modules/cbt_ops/test_plan/edit.php'],
            'ajax user detail tanpa id' => ['/modules/admin/system_access/ajax_user_detail.php'],
            'profile verify email tanpa token' => ['/modules/profile/verify_email_change.php'],
            'notifications read tanpa id' => ['/modules/notifications/read.php'],
        ];
    }

    #[DataProvider('parameterisedLegacyRoutes')]
    public function test_legacy_route_handles_missing_parameters_gracefully(string $path): void
    {
        $response = $this->asSuperadmin()->get($path);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$path} meledak ({$response->getStatusCode()}) saat parameter kosong.\n"
            .$this->extractError($response->getContent())
        );
    }

    /**
     * Ambil pesan error dari halaman exception Laravel supaya kegagalan test
     * langsung menunjukkan penyebabnya, bukan sekadar kode status.
     */
    private function extractError(string $content): string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $content, $m)) {
            return 'Pesan: '.trim(html_entity_decode($m[1]));
        }

        return 'Cuplikan: '.mb_substr(strip_tags($content), 0, 300);
    }
}
