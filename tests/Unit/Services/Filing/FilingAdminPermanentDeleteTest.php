<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingAdminService;
use App\Services\FilingSystem\FilingPermissionService;
use App\Services\FilingSystem\FilingStorageService;
use PDO;
use Tests\TestCase;

class FilingAdminPermanentDeleteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['runitc.filing_use_new_tables' => true]);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));

        $this->pdo->exec('CREATE TABLE file_system (
            rec_id INTEGER PRIMARY KEY,
            status TEXT,
            folder_loc TEXT,
            upload_flnm TEXT,
            deleted_at TEXT,
            userid_upd INTEGER,
            lupdt TEXT
        )');
        $this->pdo->exec('CREATE TABLE sys_msttable (
            rec_id INTEGER PRIMARY KEY,
            code TEXT,
            notes TEXT,
            AddiNotes TEXT,
            statrec INTEGER,
            tbl_code TEXT,
            urutan INTEGER
        )');
        $this->pdo->exec('CREATE TABLE sys_filing_audit (
            rec_id INTEGER PRIMARY KEY AUTOINCREMENT,
            filing_id INTEGER,
            user_id INTEGER,
            action TEXT,
            ip_address TEXT,
            user_agent TEXT,
            notes TEXT
        )');
    }

    public function test_wrong_confirmation_is_rejected_before_the_file_is_even_read(): void
    {
        $this->insertFile('trashed');
        $storage = $this->storage(42);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'delete');

        $this->assertFalse($res['success']);
        $this->assertSame('Confirmation text must be exactly DELETE.', $res['message']);
        $this->assertSame(0, $storage->sizeCalls);
        $this->assertSame(0, $storage->deleteCalls);
        $this->assertSame('trashed', $this->pdo->query('SELECT status FROM file_system WHERE rec_id = 1')->fetchColumn());
        $this->assertSame([], $this->auditRows());
    }

    public function test_missing_file_is_reported(): void
    {
        $res = $this->service($this->storage(0))->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertFalse($res['success']);
        $this->assertSame('File not found.', $res['message']);
        $this->assertSame([], $this->auditRows());
    }

    public function test_active_file_is_rejected_with_trash_gate_message(): void
    {
        $this->insertFile('active');
        $storage = $this->storage(42);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertFalse($res['success']);
        $this->assertSame('Hanya file di Sampah yang bisa dihapus permanen.', $res['message']);
        $this->assertSame(0, $storage->deleteCalls);
        $this->assertSame([], $this->auditRows());
    }

    public function test_trashed_file_is_deleted_and_audited_even_when_physical_delete_fails(): void
    {
        $this->insertFolder();
        $this->insertFile('trashed');
        $storage = $this->storage(42, deleteResult: false);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertTrue($res['success']);
        $this->assertSame('File berhasil dihapus permanen.', $res['message']);
        $this->assertSame(1, $storage->sizeCalls);
        $this->assertSame(1, $storage->deleteCalls);

        $row = $this->pdo->query('SELECT status, deleted_at, lupdt FROM file_system WHERE rec_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('deleted', $row['status']);
        $this->assertNotEmpty($row['deleted_at']);
        $this->assertNotEmpty($row['lupdt']);

        $audits = $this->auditRows();
        $this->assertCount(1, $audits);
        $this->assertSame('admin_permanent_delete', $audits[0]['action']);
        $this->assertSame('Physical file deleted and marked as deleted by admin.', $audits[0]['notes']);
    }

    public function test_missing_physical_file_gets_missing_note(): void
    {
        $this->insertFolder();
        $this->insertFile('trashed');
        $storage = $this->storage(0);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertTrue($res['success']);
        $this->assertSame('Physical file was missing, marked as deleted by admin.', $this->auditRows()[0]['notes']);
    }

    public function test_already_deleted_row_can_be_deleted_again_and_note_comes_from_size(): void
    {
        $this->insertFolder();
        $this->insertFile('deleted', deletedAt: '2020-01-01 00:00:00');
        $storage = $this->storage(7, deleteResult: false);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertTrue($res['success']);
        $deletedAt = $this->pdo->query('SELECT deleted_at FROM file_system WHERE rec_id = 1')->fetchColumn();
        $this->assertNotSame('2020-01-01 00:00:00', $deletedAt);
        $this->assertSame('Physical file deleted and marked as deleted by admin.', $this->auditRows()[0]['notes']);
    }

    public function test_empty_storage_path_skips_storage_calls_and_gets_missing_note(): void
    {
        $this->insertFile('trashed', folderLoc: '', uploadFlnm: '');
        $storage = $this->storage(99);

        $res = $this->service($storage)->permanentDeleteFile(1, 9, 'DELETE');

        $this->assertTrue($res['success']);
        $this->assertSame(0, $storage->sizeCalls);
        $this->assertSame(0, $storage->deleteCalls);
        $this->assertSame('Physical file was missing, marked as deleted by admin.', $this->auditRows()[0]['notes']);
    }

    private function service(FilingStorageService $storage): FilingAdminService
    {
        return new FilingAdminService($this->pdo, $storage, new FilingPermissionService($this->pdo));
    }

    private function storage(int $size, bool $deleteResult = true): FilingStorageService
    {
        return new class($size, $deleteResult) extends FilingStorageService
        {
            public int $deleteCalls = 0;

            public int $sizeCalls = 0;

            private int $fakeSize;

            private bool $deleteResult;

            public function __construct(int $fakeSize, bool $deleteResult)
            {
                parent::__construct([]);

                $this->fakeSize = $fakeSize;
                $this->deleteResult = $deleteResult;
            }

            public function deletePhysicalFile(string $storagePath): bool
            {
                $this->deleteCalls++;

                return $this->deleteResult;
            }

            public function getFileSize(string $storagePath): int
            {
                $this->sizeCalls++;

                return $this->fakeSize;
            }
        };
    }

    private function insertFile(string $status, string $folderLoc = 'F1', string $uploadFlnm = 'FS-1.zip', ?string $deletedAt = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO file_system (rec_id, status, folder_loc, upload_flnm, deleted_at) VALUES (1, ?, ?, ?, ?)');
        $stmt->execute([$status, $folderLoc, $uploadFlnm, $deletedAt]);
    }

    private function insertFolder(): void
    {
        $this->pdo->exec("INSERT INTO sys_msttable (rec_id, code, notes, AddiNotes, statrec, tbl_code, urutan) VALUES (1, 'F1', 'filing_storage/', 'F1', 1, '81', 1)");
    }

    private function auditRows(): array
    {
        return $this->pdo->query('SELECT action, notes FROM sys_filing_audit ORDER BY rec_id')->fetchAll(PDO::FETCH_ASSOC);
    }
}
