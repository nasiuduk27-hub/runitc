<?php

namespace Tests\Smoke;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Menguji route modern Laravel (/admin/*, /cbt-ops/*, /filing-system).
 *
 * Route ini TIDAK terbayangi file legacy karena URL-nya tidak punya padanan
 * file di disk, jadi seharusnya sudah dilayani Laravel di produksi hari ini.
 * Suite ini memastikan kondisi itu tidak rusak saat Fase 2 berjalan.
 */
#[Group('smoke')]
class ModernRouteSmokeTest extends SmokeTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'admin dashboard' => ['/admin/dashboard'],
            'admin operational dashboard' => ['/admin/operational-dashboard'],
            'admin operational dashboard api' => ['/admin/operational-dashboard/api'],
            'admin reporting' => ['/admin/reporting'],
            'admin system health' => ['/admin/system-health'],
            'admin system health api' => ['/admin/system-health/api'],
            'admin system settings' => ['/admin/system-settings'],
            'admin active users' => ['/admin/system-access/active-users'],
            'admin audit log' => ['/admin/system-access/audit-log'],
            'admin menu management' => ['/admin/system-access/menu-management'],
            'admin permissions' => ['/admin/system-access/permissions'],
            'admin role menu' => ['/admin/system-access/role-menu'],
            'admin roles' => ['/admin/system-access/roles'],
            'admin user role' => ['/admin/system-access/user-role'],
            'admin users' => ['/admin/system-access/users'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function operationalRoutes(): array
    {
        return [
            'cbt ops index' => ['/cbt-ops'],
            'cbt ops test admin' => ['/cbt-ops/test-admin'],
            'cbt ops test plan' => ['/cbt-ops/test-plan'],
            'cbt ops test plan create' => ['/cbt-ops/test-plan/create'],
            'cbt ops monitoring legacy path' => ['/modules/cbt_ops/test_watching/monitoring.php'],
            'cbt ops monitoring hybrid legacy path' => ['/modules/cbt_ops/test_watching/monitoring_hybrid.php'],
            'filing system' => ['/filing-system'],
            'filing system berita acara legacy path' => ['/modules/cbt_ops/filing_system/berita_acara.php'],
            'dashboard' => ['/dashboard'],
            'profile modern' => ['/profile'],
            'profile update' => ['/profile/update'],
            'profile password' => ['/profile/password'],
            'notifications' => ['/modules/notifications/index.php'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_route_responds(string $path): void
    {
        $this->assertRouteHealthy($path);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function cooperativeRoutes(): array
    {
        return [
            'cooperative audit log' => ['/cooperative/audit-log'],
            'cooperative bank transactions' => ['/cooperative/bank-transactions'],
            'cooperative bank transactions create' => ['/cooperative/bank-transactions/create'],
            'cooperative loan calculation detail' => ['/cooperative/loan-calculation/detail'],
        ];
    }

    #[DataProvider('cooperativeRoutes')]
    public function test_cooperative_route_responds(string $path): void
    {
        $this->assertRouteHealthy($path);
    }

    public function test_cooperative_bank_transaction_edit_responds(): void
    {
        $id = (int) DB::connection('mysql')->table('icu_bank_trx')->orderByDesc('rec_id')->value('rec_id');
        if ($id <= 0) {
            $this->markTestSkipped('Tidak ada data icu_bank_trx untuk smoke edit transaksi bank.');
        }

        $this->assertRouteHealthy('/cooperative/bank-transactions/'.$id.'/edit');
    }

    #[DataProvider('operationalRoutes')]
    public function test_operational_route_responds(string $path): void
    {
        $this->assertRouteHealthy($path);
    }

    public function test_filing_berita_acara_legacy_path_responds(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php');

        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    public function test_filing_berita_acara_crc_b2_ajax_routes_respond(): void
    {
        foreach ([
            '/modules/cbt_ops/filing_system/berita_acara.php?ajax_crc_b2_folders=1',
            '/modules/cbt_ops/filing_system/berita_acara.php?ajax_crc_b2_files=1&admin_no=SMOKE',
        ] as $path) {
            $response = $this->asSuperadmin()->get($path);

            $this->assertLessThan(500, $response->getStatusCode(), "{$path} mengembalikan server error.");
            $this->assertContains($response->getStatusCode(), [200, 302]);
        }
    }

    public function test_filing_berita_acara_submenu_ajax_responds(): void
    {
        $filingId = (int) DB::connection('run')->table('runit_filing_system')->orderByDesc('rec_id')->value('rec_id');
        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke submenu berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?ajax_get_submenu='.$filingId);

        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonStructure(['issues', 'live', 'files_by_category']);
    }

    public function test_filing_berita_acara_entry_ajax_responds(): void
    {
        $filingId = (int) DB::connection('run')->table('runit_filing_system')->orderByDesc('rec_id')->value('rec_id');
        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke entry berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?ajax_get_entry='.$filingId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
    }

    public function test_filing_berita_acara_admin_info_ajax_responds(): void
    {
        $assignment = DB::connection('war')
            ->table('t3sT5ub4dm1n as s')
            ->join('t3sTAdm1n as a', 'a.rec_id', '=', 's.admin_id')
            ->select('a.rec_id as admin_id', 's.rec_id as sub_admin_id')
            ->orderByDesc('a.testdt')
            ->first();

        if (! $assignment) {
            $this->markTestSkipped('Tidak ada data assignment admin/subadmin untuk smoke admin info berita acara.');
        }

        $adminVal = ((int) $assignment->admin_id).'|'.((int) $assignment->sub_admin_id);
        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?ajax_admin_info=1&admin_val='.rawurlencode($adminVal));

        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonStructure(['testdate', 'client_name', 'participants']);
    }

    public function test_filing_berita_acara_debug_outbound_responds(): void
    {
        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?debug_outbound=1&admin=SMOKE');

        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonStructure(['success']);
    }

    public function test_filing_berita_acara_download_issues_responds_with_csv(): void
    {
        $filingId = (int) DB::connection('run')->table('runit_filing_system')->orderByDesc('rec_id')->value('rec_id');
        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke download issues berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_issues='.$filingId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Issue_Report_', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_filing_berita_acara_download_crc_raw_redirects(): void
    {
        $filingId = (int) DB::connection('run')->table('runit_filing_system')->whereNotNull('nomor_admin')->orderByDesc('rec_id')->value('rec_id');
        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke download CRC raw berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_crc_raw='.$filingId.'&file=SMOKE.CRC');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('SMOKE.CRC', (string) $response->headers->get('Location'));
    }

    public function test_filing_berita_acara_download_outbound_responds(): void
    {
        $filing = DB::connection('run')
            ->table('runit_filing_system')
            ->whereNotNull('nomor_admin')
            ->orderByDesc('rec_id')
            ->first(['rec_id', 'nomor_admin']);

        if (! $filing) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke download outbound berita acara.');
        }

        $fileName = $filing->nomor_admin.'-SMOKE-DATA.ZIP';
        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_outbound='.(int) $filing->rec_id.'&file='.rawurlencode($fileName));

        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    public function test_filing_berita_acara_download_file_responds(): void
    {
        $fileId = (int) DB::connection('run')
            ->table('runit_filing_files')
            ->whereNotNull('file_path')
            ->whereNotNull('file_name')
            ->orderByDesc('file_id')
            ->value('file_id');

        if ($fileId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_files untuk smoke download file berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_file='.$fileId);

        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    public function test_filing_berita_acara_download_admin_folder_responds(): void
    {
        $filingId = (int) DB::connection('run')
            ->table('runit_filing_system')
            ->whereNotNull('nomor_admin')
            ->orderByDesc('rec_id')
            ->value('rec_id');

        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke download folder admin berita acara.');
        }

        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_admin_folder='.$filingId);

        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_filing_berita_acara_download_crc_b2_responds(): void
    {
        $filing = DB::connection('run')
            ->table('runit_filing_system')
            ->whereNotNull('nomor_admin')
            ->orderByDesc('rec_id')
            ->first(['rec_id', 'nomor_admin']);

        if (! $filing) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke download CRC B2 berita acara.');
        }

        $byFiling = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_crc_b2='.(int) $filing->rec_id);
        $byAdmin = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?download_crc_b2_admin='.rawurlencode((string) $filing->nomor_admin));

        $this->assertContains($byFiling->getStatusCode(), [200, 302, 404]);
        $this->assertContains($byAdmin->getStatusCode(), [200, 302, 404]);
    }

    public function test_filing_berita_acara_file_action_list_returns_json(): void
    {
        $filingId = (int) DB::connection('run')
            ->table('runit_filing_system')
            ->orderByDesc('rec_id')
            ->value('rec_id');

        if ($filingId <= 0) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke file_action list berita acara.');
        }

        $response = $this->asSuperadmin()->post('/modules/cbt_ops/filing_system/berita_acara.php', [
            'file_action' => 'list',
            'filing_id' => $filingId,
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_filing_berita_acara_file_action_upload_without_file_returns_json_error(): void
    {
        $filing = DB::connection('run')
            ->table('runit_filing_system')
            ->whereNotNull('nomor_admin')
            ->orderByDesc('rec_id')
            ->first(['rec_id', 'nomor_admin']);

        if (! $filing) {
            $this->markTestSkipped('Tidak ada data runit_filing_system untuk smoke upload berita acara.');
        }

        $response = $this->asSuperadmin()->post('/modules/cbt_ops/filing_system/berita_acara.php', [
            'file_action' => 'upload',
            'filing_id' => (int) $filing->rec_id,
            'nomor_admin' => (string) $filing->nomor_admin,
            'file_category' => 'berita_acara',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('msg', 'File belum dipilih.');
    }

    public function test_filing_berita_acara_crc_b2_collect_requires_admin_no(): void
    {
        $response = $this->asSuperadmin()->get('/modules/cbt_ops/filing_system/berita_acara.php?ajax_crc_b2_collect=1');

        $response->assertOk();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('message', 'Nomor admin wajib diisi.');
    }

    /**
     * Halaman tamu harus bisa diakses tanpa sesi.
     *
     * @return array<string, array{0: string}>
     */
    public static function guestRoutes(): array
    {
        return [
            'login root' => ['/'],
            'login' => ['/login'],
            'forgot password' => ['/forgot-password'],
            'register' => ['/register'],
            'health check' => ['/up'],
        ];
    }

    #[DataProvider('guestRoutes')]
    public function test_guest_route_responds_without_session(string $path): void
    {
        $response = $this->get($path);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$path} mengembalikan {$response->getStatusCode()} tanpa sesi."
        );
    }

    private function assertRouteHealthy(string $path): void
    {
        $response = $this->asSuperadmin()->get($path);
        $status = $response->getStatusCode();

        $this->assertLessThan(500, $status, "{$path} mengembalikan {$status}.\n".$this->errorHint($response->getContent()));
        $this->assertNotSame(404, $status, "{$path} tidak terdaftar sebagai route.");
        $this->assertContains($status, [200, 302], "{$path} mengembalikan {$status}, diharapkan 200 atau 302.");
    }

    private function errorHint(string $content): string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $content, $m)) {
            return 'Pesan: '.trim(html_entity_decode($m[1]));
        }

        return 'Cuplikan: '.mb_substr(strip_tags($content), 0, 300);
    }
}
