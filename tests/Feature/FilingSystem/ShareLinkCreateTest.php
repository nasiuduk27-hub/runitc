<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi Fase 2B: shareNew case create (flag FILING_USE_NEW_TABLES ON)
 * adalah pembuat link token, bukan lagi pembuat kode RFS.
 *
 * Koneksi `run` dialihkan ke sqlite in-memory (pola LoanPaymentDecideTest)
 * supaya test mandiri dan tidak menyentuh database dev/produksi. Skema
 * ditulis tangan karena tabel filing tidak ada di database/migrations default.
 */
class ShareLinkCreateTest extends TestCase
{
    private const OWNER_ID = 971001;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.run' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'runitc.filing_use_new_tables' => true,
        ]);
        DB::purge('run');

        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::connection('run')->create('file_system', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('trxno', 30)->default('');
            $table->date('trxdt')->nullable();
            $table->string('file_name', 255)->default('');
            $table->string('folder_loc', 50)->default('');
            $table->string('upload_flnm', 255)->default('');
            $table->string('file_type', 20)->default('PDF');
            $table->string('client_nm', 100)->default('');
            $table->string('file_notes', 500)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('file_zip_size')->default(0);
            $table->unsignedInteger('userid')->default(0);
            $table->string('depcd', 20)->default('');
            $table->string('status', 20)->default('active');
            $table->string('security_level', 20)->default('normal');
            $table->string('access_mode', 20)->default('private');
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_action', 20)->default('trash');
            $table->timestamp('expired_processed_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('create_dt')->nullable();
            $table->timestamp('lupdt')->nullable();
        });

        Schema::connection('run')->create('file_share_link', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->string('share_code_preview', 20)->nullable();
            $table->string('share_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('link_mode')->default(1);
            $table->unsignedInteger('created_by')->default(0);
            $table->string('password_hash', 255)->nullable();
            $table->unsignedInteger('max_access')->default(0);
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('run')->create('sys_filing_audit', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filing_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->string('action', 50)->default('');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('account_nm', 100)->default('');
            $table->string('alias_nm', 50)->default('');
            $table->string('cmpcd', 20)->default('0');
        });

        Schema::connection('run')->create('sysitc_usracc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('user_rec_id');
            $table->string('access_code', 10);
            $table->string('access_account', 20);
        });

        Schema::connection('run')->create('sysitc_grpacc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('grpaccess', 10);
            $table->string('grpacc', 20);
            $table->string('grpdesc', 100);
        });
    }

    private function seedFile(string $security = 'normal'): int
    {
        return (int) DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-TEST-0001',
            'file_name' => 'dokumen-uji.pdf',
            'folder_loc' => '',
            'upload_flnm' => 'dokumen-uji.pdf',
            'file_type' => 'PDF',
            'file_notes' => 'uji fase 2b',
            'file_size' => 1024,
            'file_zip_size' => 512,
            'userid' => self::OWNER_ID,
            'status' => 'active',
            'security_level' => $security,
            'access_mode' => 'private',
            'expired_action' => 'trash',
        ], 'rec_id');
    }

    private function loginOwner(): void
    {
        $user = new LegacyUser(self::OWNER_ID, 'Owner Uji', 'owneruji');

        Auth::guard('legacy')->setUser($user);
        Auth::setUser($user);

        $this->withSession([
            'user_id' => self::OWNER_ID,
            'user_rec_id' => self::OWNER_ID,
            'account_id' => 'owneruji',
            'user_name' => 'Owner Uji',
            'account_nm' => 'Owner Uji',
            'auth_db' => 'run',
        ]);
    }

    public function test_create_mode_2_membuat_link_dan_tidak_mengembalikan_raw_code(): void
    {
        $fileId = $this->seedFile();
        $this->loginOwner();

        $response = $this->post('/modules/cbt_ops/filing_system/share.php', [
            'action' => 'create',
            'filing_id' => $fileId,
            'link_mode' => '2',
            'allow_download' => '1',
        ]);

        $response->assertOk();
        $res = $response->json();

        $this->assertTrue($res['success']);
        $this->assertSame(2, $res['link_mode']);
        $this->assertArrayNotHasKey('raw_code', $res);

        $prefix = url('/filing-system/s').'/';
        $this->assertStringStartsWith($prefix, $res['url']);

        $token = substr($res['url'], strlen($prefix));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        $this->assertSame('LNK-****-'.substr($token, -4), $res['preview']);

        $row = DB::connection('run')->table('file_share_link')->where('filesys_id', $fileId)->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', $token), $row->share_code_hash);
        $this->assertSame($res['preview'], $row->share_code_preview);
        $this->assertSame(2, (int) $row->link_mode);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(self::OWNER_ID, (int) $row->created_by);

        $audit = DB::connection('run')->table('sys_filing_audit')
            ->where('filing_id', $fileId)->where('action', 'create_share')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString($res['preview'], (string) $audit->notes);
        $this->assertStringNotContainsString($token, (string) $audit->notes);
    }

    public function test_create_link_mode_tak_dikenal_jatuh_ke_mode_hak_akses(): void
    {
        $fileId = $this->seedFile();
        $this->loginOwner();

        $response = $this->post('/modules/cbt_ops/filing_system/share.php', [
            'action' => 'create',
            'filing_id' => $fileId,
            'link_mode' => '9',
            'allow_download' => '1',
        ]);

        $res = $response->json();
        $this->assertTrue($res['success']);
        $this->assertSame(1, $res['link_mode']);

        $row = DB::connection('run')->table('file_share_link')->where('filesys_id', $fileId)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->link_mode);
    }

    public function test_create_restricted_wajib_expired_at(): void
    {
        $fileId = $this->seedFile('restricted');
        $this->loginOwner();

        $denied = $this->post('/modules/cbt_ops/filing_system/share.php', [
            'action' => 'create',
            'filing_id' => $fileId,
            'link_mode' => '1',
        ]);

        $denied->assertOk();
        $this->assertFalse($denied->json('success'));
        $this->assertStringContainsString('Expired Date', (string) $denied->json('message'));
        $this->assertSame(0, DB::connection('run')->table('file_share_link')->where('filesys_id', $fileId)->count());

        $allowed = $this->post('/modules/cbt_ops/filing_system/share.php', [
            'action' => 'create',
            'filing_id' => $fileId,
            'link_mode' => '1',
            'expired_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);

        $allowed->assertOk();
        $this->assertTrue($allowed->json('success'));
        $this->assertSame(1, DB::connection('run')->table('file_share_link')->where('filesys_id', $fileId)->count());
    }

    public function test_list_membawa_link_mode_untuk_badge(): void
    {
        $fileId = $this->seedFile();
        $this->loginOwner();

        DB::connection('run')->table('file_share_link')->insert([
            'filesys_id' => $fileId,
            'share_code_preview' => 'LNK-****-abcd',
            'share_code_hash' => hash('sha256', 'token-uji-list'),
            'link_mode' => 2,
            'created_by' => self::OWNER_ID,
        ]);

        $response = $this->get('/modules/cbt_ops/filing_system/share.php?action=list&filing_id='.$fileId);

        $response->assertOk();
        $res = $response->json();
        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['data']);
        $this->assertSame('LNK-****-abcd', $res['data'][0]['share_code_preview']);
        $this->assertSame(2, (int) $res['data'][0]['link_mode']);
    }
}
