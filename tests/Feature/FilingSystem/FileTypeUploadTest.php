<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use App\Support\FilingSystem\FilingFileType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileTypeUploadTest extends TestCase
{
    private const USER_ID = 971001;
    private const ADMIN_ID = 971002;
    private const GROUP_ID = 55;

    private string $tempStorageDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorageDir = sys_get_temp_dir().'/filing_test_'.uniqid();
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
            $table->string('code', 5)->default('');
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->string('descr', 50)->default('');
            $table->string('notes', 100)->default('');
            $table->string('AddiNotes', 5)->default('');
            $table->integer('statrec')->default(1);
            $table->timestamp('lupdt')->useCurrent();
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
        // 1. Folder storage Filing System (tbl_code '81')
        DB::connection('run')->table('sys_msttable')->insert([
            'tbl_code' => '81',
            'code' => 'F1',
            'descr' => 'Filing Storage',
            'notes' => 'filing_storage/',
            'AddiNotes' => 'F1',
            'urutan' => 1,
            'statrec' => 1,
        ]);

        // 2. Master Tipe File awal (tbl_code '82')
        DB::connection('run')->table('sys_msttable')->insert([
            ['tbl_code' => '82', 'code' => 'INV', 'descr' => 'INVOICE', 'urutan' => 1, 'statrec' => 1, 'notes' => '', 'AddiNotes' => ''],
            ['tbl_code' => '82', 'code' => 'TAX', 'descr' => 'PAJAK',   'urutan' => 2, 'statrec' => 1, 'notes' => '', 'AddiNotes' => ''],
            ['tbl_code' => '82', 'code' => 'OTH', 'descr' => 'LAINNYA', 'urutan' => 3, 'statrec' => 1, 'notes' => '', 'AddiNotes' => ''],
        ]);

        // 3. Serial numbering (key_code '80', owner 'FSY')
        DB::connection('run')->table('sysitc_serialno')->insert([
            'key_code' => '80',
            'owner' => 'FSY',
            'pre_serno' => 'FSY',
            'mid_serno' => '26L',
            'sparerator' => '-',
            'last_number' => 10,
            'length_last_no' => 5,
        ]);

        // 4. Regular User
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => self::USER_ID,
            'account_nm' => 'Regular User',
            'alias_nm' => 'User',
            'cmpcd' => 'ITC',
        ]);

        // 5. Admin User (grpacc 999)
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => self::ADMIN_ID,
            'account_nm' => 'Admin User',
            'alias_nm' => 'Admin',
            'cmpcd' => 'ITC',
        ]);
        DB::connection('run')->table('sysitc_usracc')->insert([
            'user_rec_id' => self::ADMIN_ID,
            'access_code' => '03',
            'access_account' => '999',
        ]);
        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => '03',
            'grpacc' => '999',
            'grpdesc' => 'Super Administrator',
        ]);

        // 6. Group where Regular User is manager
        DB::connection('run')->table('sys_filegrp')->insert([
            'rec_id' => self::GROUP_ID,
            'group_name' => 'Grup Finance',
            'created_by' => self::USER_ID,
            'status' => 'active',
        ]);
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

    public function test_upload_without_type_code_is_rejected_and_writes_zero_rows(): void
    {
        $this->loginUser();

        $file = UploadedFile::fake()->createWithContent('laporan.pdf', 'konten laporan');

        $response = $this->post('/modules/cbt_ops/filing_system/upload.php', [
            'file_type_code' => '',
            'raw_files' => $file,
        ]);

        $response->assertJson([
            'success' => false,
            'message' => 'Tipe file wajib dipilih dan harus valid.',
        ]);

        $this->assertSame(0, DB::connection('run')->table('file_system')->count());
    }

    public function test_upload_with_foreign_or_inactive_type_code_is_rejected(): void
    {
        $this->loginUser();

        $file = UploadedFile::fake()->createWithContent('laporan.pdf', 'konten laporan');

        // Kasus 1: Kode asing
        $respForeign = $this->post('/modules/cbt_ops/filing_system/upload.php', [
            'file_type_code' => 'ASING',
            'raw_files' => $file,
        ]);
        $respForeign->assertJson([
            'success' => false,
            'message' => 'Tipe file wajib dipilih dan harus valid.',
        ]);
        $this->assertSame(0, DB::connection('run')->table('file_system')->count());

        // Kasus 2: Kode nonaktif
        DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '82')
            ->where('code', 'TAX')
            ->update(['statrec' => 0]);

        $respInactive = $this->post('/modules/cbt_ops/filing_system/upload.php', [
            'file_type_code' => 'TAX',
            'raw_files' => $file,
        ]);
        $respInactive->assertJson([
            'success' => false,
            'message' => 'Tipe file wajib dipilih dan harus valid.',
        ]);
        $this->assertSame(0, DB::connection('run')->table('file_system')->count());
    }

    public function test_upload_valid_stores_file_type_code(): void
    {
        $this->loginUser();

        $file = UploadedFile::fake()->createWithContent('invoice-2026.pdf', 'konten invoice 2026');

        $response = $this->post('/modules/cbt_ops/filing_system/upload.php', [
            'file_type_code' => 'INV',
            'raw_files' => $file,
        ]);

        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSame(1, DB::connection('run')->table('file_system')->count());
        $row = DB::connection('run')->table('file_system')->first();
        $this->assertSame('INV', $row->file_type_code);
        $this->assertSame('PDF', $row->file_type);
    }

    public function test_group_upload_with_valid_type_code_is_stored_and_without_type_is_rejected(): void
    {
        $this->loginUser();

        $fileWithoutType = UploadedFile::fake()->createWithContent('berkas-grup-1.pdf', 'konten grup 1');

        // Tanpa tipe -> tertolak
        $respFail = $this->post(route('filing-system.groups.files.upload', self::GROUP_ID), [
            'file_type_code' => '',
            'raw_files' => [$fileWithoutType],
        ]);
        $respFail->assertJson([
            'success' => false,
            'message' => 'Tipe file wajib dipilih dan harus valid.',
        ]);
        $this->assertSame(0, DB::connection('run')->table('file_system')->count());

        // Dengan tipe valid OTH -> tersimpan
        $fileWithType = UploadedFile::fake()->createWithContent('berkas-grup-2.pdf', 'konten grup 2');
        $respOk = $this->post(route('filing-system.groups.files.upload', self::GROUP_ID), [
            'file_type_code' => 'OTH',
            'raw_files' => [$fileWithType],
        ]);
        $respOk->assertJson([
            'success' => true,
        ]);

        $this->assertSame(1, DB::connection('run')->table('file_system')->count());
        $row = DB::connection('run')->table('file_system')->first();
        $this->assertSame('OTH', $row->file_type_code);
    }

    public function test_edit_metadata_updates_file_type_and_logs_audit(): void
    {
        $this->loginUser();

        $fileId = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00001',
            'file_name' => 'Dokumen Lama',
            'upload_flnm' => 'FSY-26L-00001.pdf',
            'file_type' => 'PDF',
            'file_type_code' => null,
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        $response = $this->post('/modules/cbt_ops/filing_system/action.php', [
            'action' => 'update_metadata',
            'filing_id' => $fileId,
            'display_name' => 'Dokumen Lama Updated',
            'file_type_code' => 'TAX',
        ]);

        $response->assertJson([
            'success' => true,
        ]);

        $updated = DB::connection('run')->table('file_system')->where('rec_id', $fileId)->first();
        $this->assertSame('TAX', $updated->file_type_code);
        $this->assertSame('Dokumen Lama Updated', $updated->file_name);

        $audit = DB::connection('run')->table('sys_filing_audit')
            ->where('filing_id', $fileId)
            ->where('action', 'metadata_update')
            ->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('file_type_code: NULL -> TAX', (string) $audit->notes);
    }

    public function test_edit_metadata_with_inactive_type_rejected_when_changed_but_allowed_when_unchanged(): void
    {
        $this->loginUser();

        // Nonaktifkan TAX di master
        DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '82')
            ->where('code', 'TAX')
            ->update(['statrec' => 0]);

        // File 1: sudah memiliki tipe TAX sejak dulu
        $file1Id = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00002',
            'file_name' => 'Invoice Lama',
            'upload_flnm' => 'FSY-26L-00002.pdf',
            'file_type' => 'PDF',
            'file_type_code' => 'TAX',
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        // Skenario A: Pemilik menyimpan metadata baru (ganti nama) tapi membiarkan tipe TAX apa adanya -> WAJIB BERHASIL (no-op)
        $respUnchanged = $this->post('/modules/cbt_ops/filing_system/action.php', [
            'action' => 'update_metadata',
            'filing_id' => $file1Id,
            'display_name' => 'Invoice Lama Ganti Judul',
            'file_type_code' => 'TAX',
        ]);
        $respUnchanged->assertJson(['success' => true]);

        $refreshed1 = DB::connection('run')->table('file_system')->where('rec_id', $file1Id)->first();
        $this->assertSame('Invoice Lama Ganti Judul', $refreshed1->file_name);
        $this->assertSame('TAX', $refreshed1->file_type_code);

        // File 2: bertipe INV
        $file2Id = DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-26L-00003',
            'file_name' => 'File Dua',
            'upload_flnm' => 'FSY-26L-00003.pdf',
            'file_type' => 'PDF',
            'file_type_code' => 'INV',
            'userid' => self::USER_ID,
            'status' => 'active',
        ]);

        // Skenario B: Mengubah tipe dari INV ke TAX (yang nonaktif) -> WAJIB DITOLAK
        $respChangedToInactive = $this->post('/modules/cbt_ops/filing_system/action.php', [
            'action' => 'update_metadata',
            'filing_id' => $file2Id,
            'display_name' => 'File Dua Ganti Tipe',
            'file_type_code' => 'TAX',
        ]);
        $respChangedToInactive->assertJson([
            'success' => false,
            'message' => 'Tipe file wajib dipilih dan harus valid.',
        ]);

        $refreshed2 = DB::connection('run')->table('file_system')->where('rec_id', $file2Id)->first();
        $this->assertSame('INV', $refreshed2->file_type_code);
    }

    public function test_admin_file_types_management_gating_and_crud(): void
    {
        // 1. Non-admin ditolak (403)
        $this->loginUser(self::USER_ID, 'Regular User');
        $this->get(route('filing-system.admin.file-types.index'))->assertStatus(403);
        $this->post(route('filing-system.admin.file-types.store'), ['code' => 'TEST', 'descr' => 'TEST'])->assertStatus(403);

        // 2. Admin masuk
        $this->loginUser(self::ADMIN_ID, 'Admin User');
        $respIndex = $this->get(route('filing-system.admin.file-types.index'));
        $respIndex->assertStatus(200);
        $respIndex->assertSee('INVOICE');
        $respIndex->assertSee('PAJAK');
        $respIndex->assertSee('LAINNYA');

        // 3. Tambah tipe baru
        $respStore = $this->post(route('filing-system.admin.file-types.store'), [
            'code' => 'SURAT',
            'descr' => 'SURAT KEPUTUSAN',
            'urutan' => 4,
        ]);
        $respStore->assertRedirect(route('filing-system.admin.file-types.index'));

        $created = DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '82')
            ->where('code', 'SURAT')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame('SURAT KEPUTUSAN', $created->descr);
        $this->assertSame(4, (int) $created->urutan);
        $this->assertSame(1, (int) $created->statrec);

        // 4. Update nama dan urutan (kode tidak bisa diubah)
        $respUpdate = $this->post(route('filing-system.admin.file-types.update', $created->rec_id), [
            'descr' => 'SURAT RESMI PERUSAHAAN',
            'urutan' => 10,
        ]);
        $respUpdate->assertRedirect(route('filing-system.admin.file-types.index'));

        $updated = DB::connection('run')->table('sys_msttable')->where('rec_id', $created->rec_id)->first();
        $this->assertSame('SURAT RESMI PERUSAHAAN', $updated->descr);
        $this->assertSame(10, (int) $updated->urutan);
        $this->assertSame('SURAT', $updated->code);

        // 5. Toggle nonaktifkan
        $respToggle = $this->post(route('filing-system.admin.file-types.toggle', $created->rec_id));
        $respToggle->assertRedirect(route('filing-system.admin.file-types.index'));

        $toggled = DB::connection('run')->table('sys_msttable')->where('rec_id', $created->rec_id)->first();
        $this->assertSame(0, (int) $toggled->statrec);

        // 6. Verifikasi efek ke helper FilingFileType:
        // SURAT hilang dari active(), tapi namanya tetap ter-resolve di resolveName() untuk file lama
        $activeCodes = array_column(FilingFileType::active(), 'code');
        $this->assertNotContains('SURAT', $activeCodes);
        $this->assertSame('SURAT RESMI PERUSAHAAN', FilingFileType::resolveName('SURAT'));
    }
}
