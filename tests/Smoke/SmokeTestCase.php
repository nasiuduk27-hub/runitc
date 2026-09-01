<?php

namespace Tests\Smoke;

use App\Auth\LegacyUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\UsesLegacyDatabase;

/**
 * Basis untuk smoke test migrasi legacy -> Laravel (Fase 0).
 *
 * Tujuan suite ini bukan menguji logika bisnis, tapi menjawab satu pertanyaan:
 * "kalau file legacy dihapus, apakah route Laravel penggantinya benar-benar
 * bisa melayani request?"
 *
 * Test ini memanggil kernel Laravel secara langsung, jadi .htaccess tidak
 * ikut campur. Artinya route yang di produksi masih terbayangi oleh file
 * legacy tetap bisa diuji di sini.
 */
abstract class SmokeTestCase extends BaseTestCase
{
    use UsesLegacyDatabase;

    /**
     * Identitas superadmin yang diambil dari database, dipakai lintas test.
     *
     * @var array{user_id: int, account_id: string, account_nm: string}|null
     */
    protected static ?array $superadmin = null;

    /**
     * Identitas user aktif non-superadmin, untuk menguji guard 403.
     *
     * @var array{user_id: int, account_id: string, account_nm: string}|null
     */
    protected static ?array $regularUser = null;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertLegacySchemaPresent();
        $this->beginLegacyTransactions();

        if (static::$superadmin === null) {
            static::$superadmin = $this->resolveSuperadmin();
        }
    }

    protected function tearDown(): void
    {
        $this->rollbackLegacyTransactions();
        $this->clearSuperglobals();

        parent::tearDown();
    }

    /**
     * Isi superglobal PHP sebelum request diteruskan ke kernel.
     *
     * Kode legacy yang masih di-require oleh controller Laravel membaca
     * $_SERVER/$_GET/$_POST langsung (mis. controllers/TestAdminController.php:23
     * membaca $_SERVER['REQUEST_METHOD']). Di produksi Apache selalu mengisi
     * nilai-nilai itu, tapi test client Laravel tidak. Tanpa ini, test gagal
     * karena artefak environment, bukan karena bug aplikasi.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $parsed = parse_url($uri);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $_GET = $query;
        $_POST = strtoupper($method) === 'GET' ? [] : $parameters;
        $_REQUEST = array_merge($query, $_POST);

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function clearSuperglobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    /**
     * Request sebagai superadmin (punya akses ke seluruh menu admin).
     */
    protected function asSuperadmin(): static
    {
        $this->withSession($this->legacySessionFor(static::$superadmin));
        $this->loginLegacyUser(static::$superadmin);

        return $this;
    }

    /**
     * Request sebagai user aktif biasa (bukan superadmin).
     */
    protected function asRegularUser(): static
    {
        if (static::$regularUser === null) {
            static::$regularUser = $this->resolveNonSuperadmin();
        }

        $this->withSession($this->legacySessionFor(static::$regularUser));
        $this->loginLegacyUser(static::$regularUser);

        return $this;
    }

    private function loginLegacyUser(array $user): void
    {
        Auth::guard('legacy')->login(new LegacyUser(
            (int) $user['user_id'],
            (string) $user['account_nm'],
            (string) $user['account_id'],
            (string) $user['user_id'],
            'run',
        ));
    }

    /**
     * Path absolut ke file legacy di dalam modules/.
     */
    protected function legacyFile(string $relativePath): string
    {
        return base_path('modules/'.ltrim($relativePath, '/'));
    }
}
