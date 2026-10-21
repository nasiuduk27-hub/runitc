<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use App\Http\Controllers\CbtOps\FilingDriveController;
use App\Services\FilingSystem\FilingAdminService;
use App\Services\FilingSystem\FilingStorageService;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class MonthlyFolderAndReservationTest extends TestCase
{
    private const USER_ID = 1001;

    private string $tempStorageDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorageDir = sys_get_temp_dir().'/filing_monthly_test_'.uniqid();
        @mkdir($this->tempStorageDir, 0777, true);

        putenv("FILING_LOCAL_STORAGE_PATH={$this->tempStorageDir}");
        $_ENV['FILING_LOCAL_STORAGE_PATH'] = $this->tempStorageDir;

        config([
            'database.connections.run' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'runitc.filing_use_new_tables' => true,
            'runitc.filing_local_storage_path' => $this->tempStorageDir,
        ]);

        DB::purge('run');

        $this->createSchema();
        $this->seedInitialData();
        $this->loginUser();
    }

    protected function tearDown(): void
    {
        putenv('FILING_LOCAL_STORAGE_PATH');
        unset($_ENV['FILING_LOCAL_STORAGE_PATH']);

        if (is_dir($this->tempStorageDir)) {
            $this->deleteDirectory($this->tempStorageDir);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_role_store_validates_div_cd_from_master_55(): void
    {
        $this->loginSuperadmin();

        // 1. Kode divisi valid dari master 55 (HRD) -> berhasil
        $respSuccess = $this->post(route('admin.roles.store'), [
            'grpaccess' => '03',
            'grpacc' => '701',
            'grpdesc' => 'Test HR Role',
            'div_cd' => 'HRD',
        ]);
        $respSuccess->assertSessionHas('success_msg');
        $this->assertDatabaseHas('sysitc_grpacc', [
            'grpaccess' => '03',
            'grpacc' => '701',
            'div_cd' => 'HRD',
        ], 'run');

        // 2. Kode divisi NULL / kosong -> berhasil dan tersimpan NULL
        $respNull = $this->post(route('admin.roles.store'), [
            'grpaccess' => '03',
            'grpacc' => '702',
            'grpdesc' => 'Test Null Role',
            'div_cd' => '',
        ]);
        $respNull->assertSessionHas('success_msg');
        $rowNull = DB::connection('run')->table('sysitc_grpacc')->where('grpacc', '702')->first();
        $this->assertNull($rowNull->div_cd);

        // 3. Kode divisi di luar master 55 (INVALID) -> ditolak validasi
        $respFail = $this->post(route('admin.roles.store'), [
            'grpaccess' => '03',
            'grpacc' => '703',
            'grpdesc' => 'Test Invalid Role',
            'div_cd' => 'NONEXISTENT',
        ]);
        $respFail->assertSessionHasErrors(['div_cd']);
        $this->assertDatabaseMissing('sysitc_grpacc', ['grpacc' => '703'], 'run');
    }

    public function test_role_update_updates_div_cd(): void
    {
        $this->loginSuperadmin();

        $roleId = DB::connection('run')->table('sysitc_grpacc')->insertGetId([
            'grpaccess' => '03',
            'grpacc' => '750',
            'grpdesc' => 'Old Role',
            'div_cd' => 'HRD',
        ]);

        $response = $this->post(route('admin.roles.update'), [
            'role_id' => $roleId,
            'grpdesc' => 'Updated Role',
            'div_cd' => 'EDD',
        ]);

        $response->assertSessionHas('success_msg');
        $this->assertDatabaseHas('sysitc_grpacc', [
            'rec_id' => $roleId,
            'grpdesc' => 'Updated Role',
            'div_cd' => 'EDD',
        ], 'run');
    }

    public function test_upload_flow_creates_monthly_folder_and_named_f_id_file(): void
    {
        // Beri user role HRD (03/110)
        DB::connection('run')->table('sysitc_usracc')->insert([
            'user_rec_id' => self::USER_ID,
            'access_code' => '03',
            'access_account' => '110',
        ]);

        $file = UploadedFile::fake()->createWithContent('slip-gaji.pdf', 'konten slip');
        $_FILES = [
            'raw_files' => [
                'name' => [$file->getClientOriginalName()],
                'type' => [$file->getClientMimeType()],
                'tmp_name' => [$file->getPathname()],
                'error' => [UPLOAD_ERR_OK],
                'size' => [$file->getSize()],
            ],
        ];
        $_POST = [
            'file_type_code' => 'INV',
            'notes' => 'Catatan gaji',
        ];

        $pdo = DB::connection('run')->getPdo();
        $controller = new FilingDriveController($pdo, []);
        $result = $controller->handleUploadNew();

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'Upload failed');

        // Cek baris file_system: status active, depcd HRD, nama fisik F<rec_id>.pdf
        $row = DB::connection('run')->table('file_system')->first();
        $this->assertNotNull($row);
        $this->assertSame('active', $row->status);
        $this->assertSame('HRD', $row->depcd);
        $this->assertSame('F'.$row->rec_id.'.pdf', $row->upload_flnm);

        // Folder di sys_msttable tbl_code 81 dibuat untuk HRD
        $folder = DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '81')
            ->where('AddiNotes', $row->folder_loc)
            ->first();
        $this->assertNotNull($folder);
        $this->assertSame('HRD', $folder->code);
        $this->assertStringContainsString('hrd26', $folder->notes);
    }

    public function test_anti_overwrite_fails_when_physical_file_already_exists(): void
    {
        $pdo = DB::connection('run')->getPdo();

        // Siapkan fake storage yang melaporkan file fisik sudah ada
        $fakeStorage = new class extends FilingStorageService {
            public function __construct() { parent::__construct([]); }
            public function fileExists(string $storagePath): bool { return true; }
        };

        $file = UploadedFile::fake()->createWithContent('dokumen.pdf', 'konten');
        $_FILES = [
            'raw_files' => [
                'name' => [$file->getClientOriginalName()],
                'type' => [$file->getClientMimeType()],
                'tmp_name' => [$file->getPathname()],
                'error' => [UPLOAD_ERR_OK],
                'size' => [$file->getSize()],
            ],
        ];
        $_POST = ['file_type_code' => 'INV'];

        $controller = new FilingDriveController($pdo, [], $fakeStorage);
        $result = $controller->handleUploadNew();

        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('sudah ada di storage', $result['message'] ?? '');

        // Baris reservasi ditandai failed, bukan active
        $this->assertSame(0, DB::connection('run')->table('file_system')->where('status', 'active')->count());
        $this->assertSame(1, DB::connection('run')->table('file_system')->where('status', 'failed')->count());
    }

    public function test_drawer_info_shows_depcd_when_present_and_null_when_empty(): void
    {
        // 1. File dengan depcd 'HRD'
        $id1 = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00001',
            'file_name' => 'Berkas HRD',
            'upload_flnm' => 'F1.pdf',
            'file_type' => 'PDF',
            'file_type_code' => 'INV',
            'folder_loc' => 'AA',
            'depcd' => 'HRD',
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        // 2. File lama dengan depcd NULL
        $id2 = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00002',
            'file_name' => 'Berkas Lama',
            'upload_flnm' => 'F2.pdf',
            'file_type' => 'PDF',
            'file_type_code' => 'INV',
            'folder_loc' => 'AA',
            'depcd' => null,
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        $resp1 = $this->get('/modules/cbt_ops/filing_system/info.php?id='.$id1);
        $resp1->assertJson(['success' => true]);
        $data1 = $resp1->json('file');
        $this->assertSame('HRD', $data1['depcd']);

        $resp2 = $this->get('/modules/cbt_ops/filing_system/info.php?id='.$id2);
        $resp2->assertJson(['success' => true]);
        $data2 = $resp2->json('file');
        $this->assertNull($data2['depcd']);
    }

    public function test_consumers_exclude_reserved_and_failed_files(): void
    {
        // Sisipkan 3 file: 1 active, 1 reserved, 1 failed
        $activeId = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00010',
            'file_name' => 'File Aktif',
            'upload_flnm' => 'F10.pdf',
            'file_type' => 'PDF',
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        $reservedId = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00011',
            'file_name' => 'File Draf',
            'upload_flnm' => 'F11.pdf',
            'file_type' => 'PDF',
            'userid' => self::USER_ID,
            'status' => 'reserved',
        ]);

        $failedId = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00012',
            'file_name' => 'File Gagal',
            'upload_flnm' => 'F12.pdf',
            'file_type' => 'PDF',
            'userid' => self::USER_ID,
            'status' => 'failed',
        ]);

        $pdo = DB::connection('run')->getPdo();
        $storage = new FilingStorageService([]);
        $permService = new \App\Services\FilingSystem\FilingPermissionService($pdo);

        // 1. Admin Service list & count
        $adminService = new FilingAdminService($pdo, $storage, $permService);
        $adminCount = $adminService->countAdminFiles([]);
        $this->assertSame(1, $adminCount);
        $adminFiles = $adminService->getAdminFiles([]);
        $adminFileIds = array_column($adminFiles, 'rec_id');
        $this->assertContains($activeId, $adminFileIds);
        $this->assertNotContains($reservedId, $adminFileIds);
        $this->assertNotContains($failedId, $adminFileIds);

        // 2. Drive fetchListNew
        $driveController = new FilingDriveController($pdo, []);
        $driveList = $driveController->fetchListNew(['folder' => 'my_drive']);
        $driveIds = array_column($driveList['items'], 'rec_id');
        $this->assertContains($activeId, $driveIds);
        $this->assertNotContains($reservedId, $driveIds);
        $this->assertNotContains($failedId, $driveIds);

        // 3. Drive fetchListLegacy
        config(['runitc.filing_use_new_tables' => false]);
        $legacyList = $driveController->fetchList([]);
        $legacyIds = array_column($legacyList['items'], 'rec_id');
        $this->assertNotContains($reservedId, $legacyIds);
        $this->assertNotContains($failedId, $legacyIds);
        config(['runitc.filing_use_new_tables' => true]);

        // 4. Preview & Download reject reserved/failed
        $previewReserved = $this->get('/modules/cbt_ops/filing_system/preview.php?id='.$reservedId);
        $this->assertSame(404, $previewReserved->getStatusCode());

        $previewFailed = $this->get('/modules/cbt_ops/filing_system/preview.php?id='.$failedId);
        $this->assertSame(404, $previewFailed->getStatusCode());
    }

    public function test_zip_bundle_display_name_formats_correctly(): void
    {
        $bundleName = 'laporan.pdf +2 berkas lainnya.zip';
        $file = UploadedFile::fake()->createWithContent($bundleName, 'fake zip content');

        $_FILES = [
            'raw_files' => [
                'name' => [$file->getClientOriginalName()],
                'type' => ['application/zip'],
                'tmp_name' => [$file->getPathname()],
                'error' => [UPLOAD_ERR_OK],
                'size' => [$file->getSize()],
            ],
        ];
        $_POST = [
            'file_type_code' => 'INV',
        ];

        $pdo = DB::connection('run')->getPdo();
        $controller = new FilingDriveController($pdo, []);
        $result = $controller->handleUploadNew();

        $this->assertTrue($result['success'] ?? false);
        $row = DB::connection('run')->table('file_system')->where('rec_id', $result['file_id'])->first();
        $this->assertNotNull($row);
        $this->assertSame('laporan.pdf +2 berkas lainnya', $row->file_name);
        $this->assertSame('ZIP', $row->file_type);
        $this->assertSame('F'.$row->rec_id.'.zip', $row->upload_flnm);
    }

    private function loginUser(int $id = self::USER_ID, string $name = 'Regular User'): void
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

    private function loginSuperadmin(): void
    {
        $this->loginUser(9999, 'Superadmin');
        DB::connection('run')->table('sysitc_usracc')->insert([
            'user_rec_id' => 9999,
            'access_code' => '03',
            'access_account' => '999',
        ]);
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
            $table->string('folder_loc', 10)->default('');
            $table->string('upload_flnm', 50)->default('');
            $table->string('file_type', 20)->default('PDF');
            $table->string('file_type_code', 10)->nullable();
            $table->string('client_nm', 150)->default('');
            $table->string('file_notes', 500)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('file_zip_size')->default(0);
            $table->integer('userid')->default(0);
            $table->string('depcd', 10)->nullable();
            $table->dateTime('create_dt')->nullable();
            $table->integer('userid_upd')->default(0);
            $table->dateTime('lupdt')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('security_level', 20)->default('normal');
            $table->string('access_mode', 20)->default('private');
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_action', 20)->default('trash');
            $table->timestamp('expired_processed_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('run')->create('file_shareto', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->tinyInteger('share_cat')->default(0);
            $table->string('othercode', 50)->default('');
            $table->unsignedInteger('created_by')->default(0);
            $table->timestamps();
        });

        Schema::connection('run')->create('sysitc_serialno', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('key_code', 10);
            $table->string('owner', 10);
            $table->string('pre_serno', 10)->default('');
            $table->string('mid_serno', 10)->default('');
            $table->string('sparerator', 5)->default('-');
            $table->integer('last_number')->default(0);
            $table->integer('length_last_no')->default(5);
            $table->unsignedInteger('reclock_byID')->default(0);
            $table->timestamp('lupdt')->useCurrent();
        });

        Schema::connection('run')->create('sys_filegrp', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('group_name', 100)->default('');
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('run')->create('sys_msttable', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('tbl_code', 10);
            $table->string('code', 50)->default('');
            $table->integer('urutan')->default(0);
            $table->string('descr', 255)->default('');
            $table->string('notes', 255)->default('');
            $table->string('AddiNotes', 50)->default('');
            $table->integer('statrec')->default(1);
            $table->dateTime('lupdt')->nullable();
        });

        Schema::connection('run')->create('sysitc_grpacc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('grpaccess', 10);
            $table->string('grpacc', 10);
            $table->string('grpdesc', 100)->default('');
            $table->string('div_cd', 10)->nullable();
            $table->integer('urutan')->default(1);
            $table->dateTime('lupdt')->nullable();
        });

        Schema::connection('run')->create('sysitc_usracc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->integer('user_rec_id');
            $table->string('access_code', 10);
            $table->string('access_account', 10);
        });

        Schema::connection('run')->create('sys_filing_audit', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filing_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('account_nm', 100)->default('');
            $table->string('alias_nm', 100)->default('');
            $table->string('cmpcd', 50)->nullable();
            $table->string('depcd', 50)->nullable();
            $table->integer('status')->default(1);
        });
    }

    private function seedInitialData(): void
    {
        DB::connection('run')->table('sysitc_serialno')->insert([
            'key_code' => '80',
            'owner' => 'FSY',
            'pre_serno' => 'FSY',
            'mid_serno' => '26L',
            'sparerator' => '-',
            'last_number' => 0,
            'length_last_no' => 5,
        ]);

        // Seed master 55 divisi
        DB::connection('run')->table('sys_msttable')->insert([
            ['tbl_code' => '55', 'code' => 'HRD', 'descr' => 'HUMAN RESOURCE DIVISION', 'notes' => 'ITC', 'AddiNotes' => '', 'statrec' => 1],
            ['tbl_code' => '55', 'code' => 'EDD', 'descr' => 'EDUCATION DEPARTMENT DIVISION', 'notes' => 'ITC', 'AddiNotes' => '', 'statrec' => 1],
            ['tbl_code' => '55', 'code' => 'CID', 'descr' => 'COMMERCIAL & INDUST. SWASTA', 'notes' => 'ITC', 'AddiNotes' => '', 'statrec' => 1],
        ]);

        // Seed master 82 tipe file
        DB::connection('run')->table('sys_msttable')->insert([
            ['tbl_code' => '82', 'code' => 'INV', 'descr' => 'INVOICE', 'notes' => '', 'AddiNotes' => '', 'statrec' => 1],
        ]);

        // Seed superadmin group
        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => '03',
            'grpacc' => '999',
            'grpdesc' => 'SUPER ADMIN',
            'div_cd' => 'MAN',
        ]);

        // Seed HRD group
        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => '03',
            'grpacc' => '110',
            'grpdesc' => 'HR Admin',
            'div_cd' => 'HRD',
        ]);
    }
}
