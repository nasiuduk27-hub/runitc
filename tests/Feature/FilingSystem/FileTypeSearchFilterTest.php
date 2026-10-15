<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use App\Http\Controllers\CbtOps\FilingDriveController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileTypeSearchFilterTest extends TestCase
{
    private const USER_ID = 972001;

    private string $tempStorageDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorageDir = sys_get_temp_dir().'/filing_filter_test_'.uniqid();
        @mkdir($this->tempStorageDir, 0777, true);

        config([
            'database.connections.run' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'runitc.filing_use_new_tables' => true,
        ]);
        putenv("FILING_LOCAL_STORAGE_PATH={$this->tempStorageDir}");
        $_ENV['FILING_LOCAL_STORAGE_PATH'] = $this->tempStorageDir;

        DB::purge('run');

        $this->createSchema();
        $this->seedInitialData();
        $this->loginUser();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempStorageDir)) {
            $files = glob($this->tempStorageDir.'/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempStorageDir);
        }

        parent::tearDown();
    }

    private function createSchema(): void
    {
        Schema::connection('run')->create('file_system', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('trxno', 50)->default('');
            $table->date('trxdt')->nullable();
            $table->tinyInteger('remind_exp')->default(0);
            $table->date('expiredt')->nullable();
            $table->date('warningdt')->nullable();
            $table->string('file_name', 255)->default('');
            $table->string('folder_loc', 10)->default('F1');
            $table->string('upload_flnm', 50)->default('');
            $table->string('file_type', 20)->default('PDF');
            $table->string('file_type_code', 10)->nullable();
            $table->string('client_nm', 150)->default('');
            $table->string('file_notes', 500)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('file_zip_size')->default(0);
            $table->unsignedInteger('userid')->default(0);
            $table->string('depcd', 10)->default('');
            $table->unsignedInteger('userid_upd')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('security_level', 20)->default('normal');
            $table->string('access_mode', 20)->default('private');
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_action', 20)->default('trash');
            $table->timestamp('expired_processed_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('create_dt')->useCurrent();
            $table->timestamp('lupdt')->useCurrent();
        });

        Schema::connection('run')->create('sys_msttable', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('tbl_code', 2);
            $table->string('code', 10)->default('');
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->string('descr', 50)->default('');
            $table->string('notes', 100)->default('');
            $table->string('AddiNotes', 5)->default('');
            $table->integer('statrec')->default(1);
            $table->timestamp('lupdt')->useCurrent();
        });

        Schema::connection('run')->create('file_shareto', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->tinyInteger('share_cat')->default(0);
            $table->string('othercode', 20)->default('');
            $table->unsignedInteger('created_by')->default(0);
            $table->timestamp('create_dt')->useCurrent();
        });

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('account_nm', 100)->default('');
            $table->string('alias_nm', 50)->default('');
            $table->string('cmpcd', 20)->default('ITC');
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

        Schema::connection('run')->create('sys_filegrp', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('group_name', 100);
            $table->string('description', 255)->default('');
            $table->unsignedInteger('created_by')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('run')->create('sys_filegrp_member', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('group_id');
            $table->string('member_type', 20)->default('user');
            $table->string('member_value', 50)->default('');
            $table->string('role', 20)->default('contributor');
            $table->unsignedInteger('added_by')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('mysql')->create('icu_member', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('itc_user_id')->default(0);
        });
    }

    private function seedInitialData(): void
    {
        DB::connection('run')->table('sys_msttable')->insert([
            'tbl_code' => '81',
            'code' => 'F1',
            'descr' => 'Filing Storage',
            'notes' => 'filing_storage/',
            'AddiNotes' => 'F1',
            'urutan' => 1,
            'statrec' => 1,
        ]);

        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => self::USER_ID,
            'account_nm' => 'Test User',
            'alias_nm' => 'Tester',
            'cmpcd' => 'ITC',
        ]);
    }

    private function loginUser(int $id = self::USER_ID, string $name = 'Test User'): void
    {
        $user = new LegacyUser($id, $name, 'user_'.$id);
        Auth::guard('legacy')->setUser($user);
        Auth::setUser($user);

        $this->withSession([
            'user_id' => $id,
            'user_rec_id' => $id,
            'account_id' => 'user_'.$id,
            'user_name' => $name,
            'account_nm' => $name,
            'auth_db' => 'run',
        ]);
    }

    private function makeDriveController(): FilingDriveController
    {
        $ftpConfig = [
            'host' => '127.0.0.1',
            'user' => 'ftp',
            'pass' => 'pass',
            'port' => 21,
            'root' => '',
            'mode' => 'local',
        ];

        return new FilingDriveController(DB::connection('run')->getPdo(), $ftpConfig);
    }

    public function test_filter_by_specific_file_type_code(): void
    {
        DB::connection('run')->table('file_system')->insert([
            ['trxno' => 'F-001', 'file_name' => 'Invoice 1.pdf', 'file_type' => 'PDF', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-002', 'file_name' => 'Tax 1.pdf', 'file_type' => 'PDF', 'file_type_code' => 'TAX', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-003', 'file_name' => 'Untyped 1.pdf', 'file_type' => 'PDF', 'file_type_code' => null, 'userid' => self::USER_ID, 'status' => 'active'],
        ]);

        $controller = $this->makeDriveController();
        $result = $controller->fetchList(['file_type_code' => 'INV']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('F-001', $result['items'][0]['file_code']);
        $this->assertSame('INV', $result['items'][0]['file_type_code']);

        // Verifikasi route index dan info drawer payload
        $httpResp = $this->get(route('filing-system.index', ['file_type_code' => 'INV']));
        $httpResp->assertOk();
        $httpResp->assertSee('Invoice 1.pdf');
        $httpResp->assertDontSee('Tax 1.pdf');

        $infoResp = $this->get('/modules/cbt_ops/filing_system/info.php?id=1');
        $infoResp->assertOk();
        $infoData = $infoResp->json();
        $this->assertSame('INV', $infoData['file']['file_type_code'] ?? null);
    }

    public function test_filter_by_untyped_sentinel(): void
    {
        DB::connection('run')->table('file_system')->insert([
            ['trxno' => 'F-001', 'file_name' => 'Invoice 1.pdf', 'file_type' => 'PDF', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-002', 'file_name' => 'Untyped Null.pdf', 'file_type' => 'PDF', 'file_type_code' => null, 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-003', 'file_name' => 'Untyped Empty.pdf', 'file_type' => 'PDF', 'file_type_code' => '', 'userid' => self::USER_ID, 'status' => 'active'],
        ]);

        $controller = $this->makeDriveController();
        $result = $controller->fetchList(['file_type_code' => '__untyped__']);

        $this->assertCount(2, $result['items']);
        $codes = array_column($result['items'], 'file_code');
        $this->assertContains('F-002', $codes);
        $this->assertContains('F-003', $codes);
        $this->assertNotContains('F-001', $codes);
    }

    public function test_filter_by_file_format_physical_extension(): void
    {
        DB::connection('run')->table('file_system')->insert([
            ['trxno' => 'F-001', 'file_name' => 'Doc 1.pdf', 'file_type' => 'PDF', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-002', 'file_name' => 'Image 1.jpg', 'file_type' => 'JPG', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-003', 'file_name' => 'Doc 2.pdf', 'file_type' => 'PDF', 'file_type_code' => 'TAX', 'userid' => self::USER_ID, 'status' => 'active'],
        ]);

        $controller = $this->makeDriveController();
        $result = $controller->fetchList(['file_format' => ['PDF']]);

        $this->assertCount(2, $result['items']);
        $codes = array_column($result['items'], 'file_code');
        $this->assertContains('F-001', $codes);
        $this->assertContains('F-003', $codes);
        $this->assertNotContains('F-002', $codes);
    }

    public function test_combined_filter_type_format_and_search_words(): void
    {
        DB::connection('run')->table('file_system')->insert([
            ['trxno' => 'F-A', 'file_name' => 'Laporan Keuangan 2026.pdf', 'file_type' => 'PDF', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-B', 'file_name' => 'Laporan Pajak 2026.pdf', 'file_type' => 'PDF', 'file_type_code' => 'TAX', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-C', 'file_name' => 'Laporan Keuangan 2026.xlsx', 'file_type' => 'XLSX', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
            ['trxno' => 'F-D', 'file_name' => 'Surat Perjanjian.pdf', 'file_type' => 'PDF', 'file_type_code' => 'INV', 'userid' => self::USER_ID, 'status' => 'active'],
        ]);

        $controller = $this->makeDriveController();
        $result = $controller->fetchList([
            'file_type_code' => 'INV',
            'file_format' => ['PDF'],
            'search' => 'Keuangan',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('F-A', $result['items'][0]['file_code']);
    }

    public function test_filter_options_includes_active_and_used_inactive_and_excludes_unused_inactive(): void
    {
        DB::connection('run')->table('sys_msttable')->insert([
            ['tbl_code' => '82', 'code' => 'ACTIVE1', 'descr' => 'Tipe Aktif Satu', 'urutan' => 1, 'statrec' => 1, 'notes' => '', 'AddiNotes' => ''],
            ['tbl_code' => '82', 'code' => 'ACTIVE2', 'descr' => 'Tipe Aktif Dua',  'urutan' => 2, 'statrec' => 1, 'notes' => '', 'AddiNotes' => ''],
            ['tbl_code' => '82', 'code' => 'OLD_USED', 'descr' => 'Tipe Lama Terpakai', 'urutan' => 3, 'statrec' => 0, 'notes' => '', 'AddiNotes' => ''],
            ['tbl_code' => '82', 'code' => 'OLD_UNUSED', 'descr' => 'Tipe Lama Tidak Terpakai', 'urutan' => 4, 'statrec' => 0, 'notes' => '', 'AddiNotes' => ''],
        ]);

        // File aktif menggunakan OLD_USED
        DB::connection('run')->table('file_system')->insert([
            ['trxno' => 'F-OLD', 'file_name' => 'Old.pdf', 'file_type' => 'PDF', 'file_type_code' => 'OLD_USED', 'userid' => self::USER_ID, 'status' => 'active', 'deleted_at' => null],
        ]);

        $controller = $this->makeDriveController();
        $options = $controller->getFilterOptionsNew();

        $this->assertArrayHasKey('file_types', $options);
        $values = array_column($options['file_types'], 'value');

        // Master aktif harus ada
        $this->assertContains('ACTIVE1', $values);
        $this->assertContains('ACTIVE2', $values);

        // Master nonaktif yang dipakai file aktif harus ada
        $this->assertContains('OLD_USED', $values);

        // Master nonaktif yang TIDAK dipakai file aktif tidak boleh ada
        $this->assertNotContains('OLD_UNUSED', $values);

        // Opsi sentinel belum bertipe harus ada
        $this->assertContains('__untyped__', $values);

        // Alias getSearchFilterOptions mengembalikan hasil yang sama
        $this->assertSame($options, $controller->getSearchFilterOptions());
    }
}
