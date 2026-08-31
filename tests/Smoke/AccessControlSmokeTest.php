<?php

namespace Tests\Smoke;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Menguji lapisan proteksi, bukan tampilan.
 *
 * Ini bagian terpenting dari Fase 0: saat file legacy diarsipkan di Fase 2,
 * guard yang tadinya dikerjakan includes/menu_guard.php (requireSuperadmin)
 * berpindah ke abort_unless() di controller Laravel. Test ini memastikan
 * perpindahan itu tidak menghilangkan proteksi.
 */
#[Group('smoke')]
class AccessControlSmokeTest extends SmokeTestCase
{
    /**
     * Halaman admin yang hanya boleh diakses superadmin.
     * Padanan legacy-nya memanggil requireSuperadmin() (includes/menu_guard.php:134).
     *
     * @return array<string, array{0: string}>
     */
    public static function superadminOnlyRoutes(): array
    {
        return [
            'admin dashboard' => ['/admin/dashboard'],
            'admin audit log' => ['/admin/system-access/audit-log'],
            'admin users' => ['/admin/system-access/users'],
            'admin user role' => ['/admin/system-access/user-role'],
            'admin menu management' => ['/admin/system-access/menu-management'],
            'admin system health' => ['/admin/system-health'],
            'admin operational dashboard' => ['/admin/operational-dashboard'],
            'admin active users' => ['/admin/system-access/active-users'],
        ];
    }

    #[DataProvider('superadminOnlyRoutes')]
    public function test_regular_user_is_denied(string $path): void
    {
        $response = $this->asRegularUser()->get($path);

        $this->assertSame(
            403,
            $response->getStatusCode(),
            "{$path} mengembalikan {$response->getStatusCode()} untuk user non-superadmin. "
            .'Diharapkan 403 — guard admin bocor.'
        );
    }

    #[DataProvider('superadminOnlyRoutes')]
    public function test_superadmin_is_allowed(string $path): void
    {
        $response = $this->asSuperadmin()->get($path);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            "{$path} menolak superadmin. Guard terlalu ketat."
        );
    }

    /**
     * Semua halaman terproteksi harus mengarahkan ke login saat tanpa sesi.
     * Ini yang dikerjakan middleware legacy.auth (app/Http/Middleware/LegacyAuthenticate.php:29).
     *
     * @return array<string, array{0: string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'admin dashboard' => ['/admin/dashboard'],
            'admin users' => ['/admin/system-access/users'],
            'cbt ops' => ['/cbt-ops'],
            'cbt ops test admin' => ['/cbt-ops/test-admin'],
            'filing system' => ['/filing-system'],
            'profile legacy path' => ['/modules/profile/index.php'],
            'notifications legacy path' => ['/modules/notifications/index.php'],
            'admin roles legacy path' => ['/modules/admin/system_access/roles.php'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guest_is_redirected_to_login(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            "{$path} mengembalikan {$response->getStatusCode()} tanpa sesi. Diharapkan redirect 302 ke login."
        );

        $response->assertRedirectToRoute('login');
    }

    /**
     * CSRF tidak bisa diuji lewat request HTTP di dalam test suite:
     * VerifyCsrfToken::handle() (vendor/.../VerifyCsrfToken.php:83) memanggil
     * runningUnitTests() dan langsung melewati validasi. Jadi yang diuji di
     * sini adalah konfigurasinya, bukan perilaku runtime-nya.
     *
     * Endpoint destruktif berikut TIDAK boleh masuk daftar pengecualian CSRF.
     *
     * @return array<string, array{0: string}>
     */
    public static function destructivePostRoutes(): array
    {
        return [
            'reset password user' => ['admin/system-access/users/reset-password'],
            'hapus user' => ['admin/system-access/users/delete'],
            'ubah status user' => ['admin/system-access/users/status'],
            'assign role' => ['admin/system-access/user-role/assign'],
            'hapus role' => ['admin/system-access/roles/delete'],
            'hapus menu' => ['admin/system-access/menu-management/delete'],
        ];
    }

    #[DataProvider('destructivePostRoutes')]
    public function test_destructive_route_is_not_excluded_from_csrf(string $uri): void
    {
        $this->assertFalse(
            $this->isExcludedFromCsrf($uri),
            "{$uri} termasuk dalam pengecualian CSRF. Endpoint destruktif ini tidak terlindungi."
        );
    }

    public function test_legacy_module_admin_paths_are_not_excluded_from_csrf(): void
    {
        $this->assertFalse(
            $this->isExcludedFromCsrf('modules/admin/system_access/roles.php'),
            'Path legacy admin di bawah modules/* tidak boleh kebal CSRF.'
        );
    }

    public function test_external_receiver_paths_remain_excluded_from_csrf(): void
    {
        foreach ([
            'modules/cbt_ops/filing_system/crc_b2_receiver',
            'modules/cbt_ops/filing_system/crc_b2_receiver.php',
            'modules/cbt_ops/filing_system/http_upload_receiver',
            'modules/cbt_ops/filing_system/http_upload_receiver.php',
            'modules/cbt_ops/test_watching/crc_receiver',
            'modules/cbt_ops/test_watching/crc_receiver.php',
            'modules/cbt_ops/test_watching/outbound_receiver',
            'modules/cbt_ops/test_watching/outbound_receiver.php',
        ] as $uri) {
            $this->assertTrue($this->isExcludedFromCsrf($uri), "Receiver eksternal {$uri} masih perlu kompatibel tanpa CSRF browser.");
        }
    }

    /**
     * Hitung berapa banyak route tulis di bawah modules/* yang kebal CSRF.
     * Angka ini harus turun seiring Fase 2 berjalan, dan menjadi nol
     * setelah Fase 3 menghapus pengecualian 'modules/*'.
     */
    public function test_reports_how_many_write_routes_bypass_csrf(): void
    {
        $bypassed = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->filter(fn ($route) => $this->isExcludedFromCsrf($route->uri()))
            ->map(fn ($route) => implode('|', array_diff($route->methods(), ['HEAD'])).' /'.$route->uri())
            ->values();

        // Hanya receiver machine-to-machine yang masih dikecualikan dari CSRF.
        $baseline = 8;

        $this->assertLessThanOrEqual(
            $baseline,
            $bypassed->count(),
            "Jumlah route tulis yang kebal CSRF naik menjadi {$bypassed->count()} (baseline {$baseline}). "
            ."Ada route legacy baru yang ditambahkan:\n".$bypassed->implode("\n")
        );
    }

    /**
     * Cek apakah sebuah URI dikecualikan dari validasi CSRF, mengikuti
     * logika ExcludesPaths yang dipakai middleware.
     */
    private function isExcludedFromCsrf(string $uri): bool
    {
        $middleware = app(VerifyCsrfToken::class);
        $request = Request::create('/'.ltrim($uri, '/'), 'POST');

        foreach ($middleware->getExcludedPaths() as $pattern) {
            if ($request->fullUrlIs($pattern) || $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
