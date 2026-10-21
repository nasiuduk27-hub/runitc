<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingFolderAllocationService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class FilingFolderAllocationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE sysitc_usracc (
                rec_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_rec_id INTEGER NOT NULL,
                access_code TEXT NOT NULL,
                access_account TEXT NOT NULL
            );
            CREATE TABLE sysitc_grpacc (
                rec_id INTEGER PRIMARY KEY AUTOINCREMENT,
                grpaccess TEXT NOT NULL,
                grpacc TEXT NOT NULL,
                grpdesc TEXT NOT NULL,
                div_cd TEXT NULL
            );
            CREATE TABLE sys_msttable (
                rec_id INTEGER PRIMARY KEY AUTOINCREMENT,
                tbl_code TEXT NOT NULL,
                code TEXT NOT NULL,
                urutan INTEGER NOT NULL DEFAULT 0,
                descr TEXT NOT NULL,
                notes TEXT NOT NULL,
                AddiNotes TEXT NOT NULL DEFAULT "",
                statrec INTEGER NOT NULL DEFAULT 1,
                lupdt TEXT NOT NULL
            );
            CREATE TABLE file_system (
                rec_id INTEGER PRIMARY KEY AUTOINCREMENT,
                folder_loc TEXT NULL
            );
        ');
    }

    public function test_resolve_user_division_returns_first_assigned_role_with_div_cd(): void
    {
        // Role 110 => HRD, Role 210 => EDD
        $this->pdo->exec("INSERT INTO sysitc_grpacc (rec_id, grpaccess, grpacc, grpdesc, div_cd) VALUES (1, '03', '110', 'HR Admin', 'HRD')");
        $this->pdo->exec("INSERT INTO sysitc_grpacc (rec_id, grpaccess, grpacc, grpdesc, div_cd) VALUES (2, '03', '210', 'EDD Admin', 'EDD')");

        // User 5 di-assign ke HRD (rec_id 10) lalu ke EDD (rec_id 20)
        $this->pdo->exec("INSERT INTO sysitc_usracc (rec_id, user_rec_id, access_code, access_account) VALUES (10, 5, '03', '110')");
        $this->pdo->exec("INSERT INTO sysitc_usracc (rec_id, user_rec_id, access_code, access_account) VALUES (20, 5, '03', '210')");

        $service = new FilingFolderAllocationService($this->pdo);
        $this->assertSame('HRD', $service->resolveUserDivision(5));
    }

    public function test_resolve_user_division_skips_null_div_cd_without_blocking(): void
    {
        // Role 410 (CU Admin) punya div_cd NULL, Role 110 punya HRD
        $this->pdo->exec("INSERT INTO sysitc_grpacc (rec_id, grpaccess, grpacc, grpdesc, div_cd) VALUES (1, '03', '410', 'CU Admin', NULL)");
        $this->pdo->exec("INSERT INTO sysitc_grpacc (rec_id, grpaccess, grpacc, grpdesc, div_cd) VALUES (2, '03', '110', 'HR Admin', 'HRD')");

        // User 6 punya assignment CU Admin lebih dulu (rec_id 1), lalu HR Admin (rec_id 2)
        $this->pdo->exec("INSERT INTO sysitc_usracc (rec_id, user_rec_id, access_code, access_account) VALUES (1, 6, '03', '410')");
        $this->pdo->exec("INSERT INTO sysitc_usracc (rec_id, user_rec_id, access_code, access_account) VALUES (2, 6, '03', '110')");

        $service = new FilingFolderAllocationService($this->pdo);
        // CU Admin NULL dilewati, HRD menang
        $this->assertSame('HRD', $service->resolveUserDivision(6));
    }

    public function test_resolve_user_division_fallbacks_to_umum_when_no_role_or_all_null(): void
    {
        $this->pdo->exec("INSERT INTO sysitc_grpacc (rec_id, grpaccess, grpacc, grpdesc, div_cd) VALUES (1, '03', '410', 'CU Admin', NULL)");
        $this->pdo->exec("INSERT INTO sysitc_usracc (rec_id, user_rec_id, access_code, access_account) VALUES (1, 7, '03', '410')");

        $service = new FilingFolderAllocationService($this->pdo);
        $this->assertSame('UMUM', $service->resolveUserDivision(7));
        $this->assertSame('UMUM', $service->resolveUserDivision(999));
    }

    public function test_compute_folder_name_formats_flat_monthly_string(): void
    {
        $service = new FilingFolderAllocationService($this->pdo);

        // Bulan 1 (Januari 2026) => hrd26a
        $dateJan = new DateTimeImmutable('2026-01-15');
        $this->assertSame('hrd26a', $service->computeFolderName('HRD', $dateJan));

        // Bulan 10 (Oktober 2026) => hrd26j
        $dateOct = new DateTimeImmutable('2026-10-21');
        $this->assertSame('hrd26j', $service->computeFolderName('HRD', $dateOct));
        $this->assertSame('umum26j', $service->computeFolderName('UMUM', $dateOct));

        // Bulan 12 (Desember 2026) => cid26l
        $dateDec = new DateTimeImmutable('2026-12-31');
        $this->assertSame('cid26l', $service->computeFolderName('CID', $dateDec));
    }

    public function test_calculate_next_addi_notes_progresses_beyond_highest_used_code(): void
    {
        // Isi baseline produksi: sys_msttable tbl_code 81 punya AA dan AC; file_system punya AA, AB, AC, AT
        $this->pdo->exec("INSERT INTO sys_msttable (tbl_code, code, descr, notes, AddiNotes, lupdt) VALUES ('81', 'CID', 'Desc', '/filesys/cid26a/', 'AA', '2026-01-01')");
        $this->pdo->exec("INSERT INTO sys_msttable (tbl_code, code, descr, notes, AddiNotes, lupdt) VALUES ('81', 'CID', 'Desc', '/filesys/cid26b/', 'AC', '2026-02-01')");

        $this->pdo->exec("INSERT INTO file_system (folder_loc) VALUES ('AA')");
        $this->pdo->exec("INSERT INTO file_system (folder_loc) VALUES ('AB')");
        $this->pdo->exec("INSERT INTO file_system (folder_loc) VALUES ('AC')");
        $this->pdo->exec("INSERT INTO file_system (folder_loc) VALUES ('AT')");

        $service = new FilingFolderAllocationService($this->pdo);

        // Maksimum saat ini AT => nilai baru pertama wajib AU
        $next = $service->calculateNextAddiNotes();
        $this->assertSame('AU', $next);
    }

    public function test_get_or_create_monthly_folder_is_idempotent(): void
    {
        $service = new FilingFolderAllocationService($this->pdo);
        $date = new DateTimeImmutable('2026-10-21');

        $folder1 = $service->getOrCreateMonthlyFolder('HRD', $date);
        $this->assertSame('/filesys/hrd26j/', $folder1['notes']);
        $this->assertSame('AA', $folder1['AddiNotes']);

        // Pemanggilan kedua untuk divisi & bulan yang sama harus mengembalikan folder yang sama
        $folder2 = $service->getOrCreateMonthlyFolder('HRD', $date);
        $this->assertSame($folder1['rec_id'], $folder2['rec_id']);
        $this->assertSame($folder1['AddiNotes'], $folder2['AddiNotes']);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM sys_msttable WHERE tbl_code = '81'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
