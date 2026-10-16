<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use App\Http\Controllers\CbtOps\FilingDriveController;
use App\Services\FilingSystem\FilingStorageService;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UploadTransactionOrderTest extends TestCase
{
    private const USER_ID = 971001;

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
        $this->seedInitialData();
    }

    public function test_transaction_is_not_active_during_storage_upload_on_success(): void
    {
        $this->loginUser();

        $pdo = DB::connection('run')->getPdo();
        $inTransactionDuringStorage = null;

        $fakeStorage = new class($pdo, $inTransactionDuringStorage) extends FilingStorageService {
            private \PDO $pdo;
            public ?bool $capturedTx;

            public function __construct(\PDO $pdo, &$capturedTx)
            {
                parent::__construct([]);
                $this->pdo = $pdo;
                $this->capturedTx = &$capturedTx;
            }

            public function moveUploadedFile(string $tmpPath, string $storagePath): bool
            {
                $this->capturedTx = $this->pdo->inTransaction();

                return true;
            }
        };

        $file = UploadedFile::fake()->createWithContent('laporan.pdf', 'konten laporan');
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
            'notes' => 'Test upload transaction order',
        ];

        $controller = new FilingDriveController($pdo, [], $fakeStorage);
        $result = $controller->handleUploadNew();

        $this->assertTrue($result['success'] ?? false);
        $this->assertFalse($inTransactionDuringStorage, 'Transaksi DB TIDAK boleh aktif saat pengiriman ke storage.');
        $this->assertFalse($pdo->inTransaction(), 'Transaksi DB harus sudah di-commit setelah proses selesai.');

        // Memastikan metadata berhasil tersimpan dan serial nomor naik
        $this->assertSame(1, DB::connection('run')->table('file_system')->count());
        $serial = DB::connection('run')->table('sysitc_serialno')->where('key_code', '80')->first();
        $this->assertSame(1, (int) $serial->last_number);
    }

    public function test_storage_failure_does_not_open_db_transaction_and_preserves_serial(): void
    {
        $this->loginUser();

        $pdo = DB::connection('run')->getPdo();
        $inTransactionDuringStorage = null;

        $fakeStorage = new class($pdo, $inTransactionDuringStorage) extends FilingStorageService {
            private \PDO $pdo;
            public ?bool $capturedTx;

            public function __construct(\PDO $pdo, &$capturedTx)
            {
                parent::__construct([]);
                $this->pdo = $pdo;
                $this->capturedTx = &$capturedTx;
            }

            public function moveUploadedFile(string $tmpPath, string $storagePath): bool
            {
                $this->capturedTx = $this->pdo->inTransaction();

                throw new Exception('Jaringan storage gagal simulasi');
            }
        };

        $file = UploadedFile::fake()->createWithContent('laporan.pdf', 'konten laporan');
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
        ];

        $controller = new FilingDriveController($pdo, [], $fakeStorage);
        $result = $controller->handleUploadNew();

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame('Jaringan storage gagal simulasi', $result['message'] ?? '');
        $this->assertFalse($inTransactionDuringStorage, 'Transaksi DB tidak boleh terbuka sebelum storage.');
        $this->assertFalse($pdo->inTransaction(), 'Tidak ada transaksi DB yang tertinggal.');

        // Baris DB tidak boleh bertambah dan serial tidak boleh terkonsumsi
        $this->assertSame(0, DB::connection('run')->table('file_system')->count());
        $serial = DB::connection('run')->table('sysitc_serialno')->where('key_code', '80')->first();
        $this->assertSame(0, (int) $serial->last_number);
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
    }

    private function seedInitialData(): void
    {
        // Folder aktif
        DB::connection('run')->table('sys_msttable')->insert([
            'tbl_code' => '81',
            'code' => 'ACTIVE',
            'descr' => 'Folder Aktif',
            'notes' => 'filesys/active/',
            'AddiNotes' => 'ACT',
            'statrec' => 1,
        ]);

        // Tipe file INV
        DB::connection('run')->table('sys_msttable')->insert([
            'tbl_code' => '82',
            'code' => 'INV',
            'descr' => 'Invoice',
            'notes' => 'INV',
            'AddiNotes' => '',
            'statrec' => 1,
        ]);

        // Serial counter
        DB::connection('run')->table('sysitc_serialno')->insert([
            'key_code' => '80',
            'owner' => 'FSY',
            'pre_serno' => 'FSY',
            'mid_serno' => '26L',
            'sparerator' => '-',
            'last_number' => 0,
            'length_last_no' => 5,
        ]);
    }
}
