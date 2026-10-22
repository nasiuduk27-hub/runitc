<?php

namespace Tests\Feature\FilingSystem;

use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\FilingSystem\FilingExpiryReminderService;
use App\Services\MailService;
use App\Support\Legacy\Notification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class FilingExpiryReminderTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('FILING_USE_NEW_TABLES=true');
        $_ENV['FILING_USE_NEW_TABLES'] = 'true';

        $this->createSchema();
        $this->pdo = DB::connection('run')->getPdo();
    }

    protected function tearDown(): void
    {
        putenv('FILING_USE_NEW_TABLES');
        unset($_ENV['FILING_USE_NEW_TABLES']);
        parent::tearDown();
    }

    private function createSchema(): void
    {
        Schema::connection('run')->dropIfExists('file_system');
        Schema::connection('run')->dropIfExists('file_shareto');
        Schema::connection('run')->dropIfExists('sys_filing_audit');
        Schema::connection('run')->dropIfExists('sys_notifications');
        Schema::connection('run')->dropIfExists('sysitc_users');
        Schema::connection('run')->dropIfExists('sysitc_login');
        Schema::connection('run')->dropIfExists('sysitc_usermail');
        Schema::connection('run')->dropIfExists('sysitc_usracc');
        Schema::connection('run')->dropIfExists('sysitc_grpacc');
        Schema::connection('run')->dropIfExists('sys_msttable');

        Schema::connection('run')->create('file_system', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('trxno', 50)->default('');
            $table->date('trxdt')->nullable();
            $table->tinyInteger('remind_exp')->default(0);
            $table->smallInteger('remind_days')->nullable();
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
            $table->integer('userid_upd')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('security_level', 20)->default('normal');
            $table->string('access_mode', 20)->default('private');
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_action', 20)->default('trash');
            $table->timestamp('expired_processed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->dateTime('create_dt')->nullable();
            $table->dateTime('lupdt')->nullable();
        });

        Schema::connection('run')->create('sys_filing_audit', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filing_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        Schema::connection('run')->create('sys_notifications', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->integer('recipient_user_id');
            $table->integer('sender_user_id')->nullable();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('message');
            $table->string('target_url', 255)->nullable();
            $table->string('ref_table', 80)->nullable();
            $table->integer('ref_id')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('read_at')->nullable();
        });

        Schema::connection('run')->create('file_shareto', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->tinyInteger('share_cat')->default(0);
            $table->string('othercode', 50)->default('');
            $table->unsignedInteger('created_by')->default(0);
            $table->dateTime('create_dt')->nullable();
        });

        Schema::connection('run')->create('sysitc_usracc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->integer('user_rec_id');
            $table->string('access_code', 10);
            $table->string('access_account', 10);
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

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->integer('login_rec_id')->default(0);
            $table->integer('cmpcd')->default(0);
            $table->string('account_nm', 30)->default('');
            $table->string('alias_nm', 10)->default('');
        });

        Schema::connection('run')->create('sysitc_login', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('email_id', 50)->default('');
        });

        Schema::connection('run')->create('sysitc_usermail', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->integer('user_recid')->default(0);
            $table->string('email', 50)->default('');
            $table->tinyInteger('asdefault')->default(0);
        });
    }

    private function insertFileRecord(array $attributes): int
    {
        $drive = new FileSystemDrive($this->pdo);

        return $drive->insertFile(array_merge([
            'trxno' => 'FSY-26X-'.rand(10000, 99999),
            'trxdt' => date('Y-m-d'),
            'file_name' => 'Dokumen-'.rand(100, 999).'.pdf',
            'folder_loc' => '2610',
            'upload_flnm' => 'file_'.rand(100, 999).'.pdf',
            'file_type' => 'PDF',
            'userid' => 10,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * 1. Penemu memilih tepat berkas yang warningdt-nya tiba dan belum diingatkan/belum diproses;
     * menolak warningdt besok, reminded_at terisi, sudah diproses, dan status trashed/expired.
     */
    public function test_candidate_finder_filtering(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // Kandidat sah 1 (warningdt = hari ini)
        $id1 = $this->insertFileRecord([
            'file_name' => 'Kandidat Hari Ini.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => $today,
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Kandidat sah 2 (warningdt = kemarin)
        $id2 = $this->insertFileRecord([
            'file_name' => 'Kandidat Kemarin.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'warningdt' => $yesterday,
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Tolak: warningdt besok
        $this->insertFileRecord([
            'file_name' => 'Besok.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'warningdt' => $tomorrow,
            'remind_days' => 4,
            'remind_exp' => 1,
        ]);

        // Tolak: reminded_at terisi
        $this->insertFileRecord([
            'file_name' => 'Sudah Diingatkan.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'warningdt' => $yesterday,
            'reminded_at' => date('Y-m-d H:i:s'),
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Tolak: sudah diproses expired_processed_at
        $this->insertFileRecord([
            'file_name' => 'Sudah Diproses.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'warningdt' => $yesterday,
            'expired_processed_at' => date('Y-m-d H:i:s'),
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Tolak: status trashed
        $this->insertFileRecord([
            'file_name' => 'Status Trashed.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'warningdt' => $yesterday,
            'status' => 'trashed',
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Tolak: status expired
        $this->insertFileRecord([
            'file_name' => 'Status Expired.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'warningdt' => $yesterday,
            'status' => 'expired',
            'remind_days' => 3,
            'remind_exp' => 1,
        ]);

        // Tolak: baris legacy flag-only (remind_exp=1, remind_days NULL, warningdt NULL, expired_at masa depan, status active)
        $this->insertFileRecord([
            'file_name' => 'Legacy Flag Only.pdf',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'warningdt' => null,
            'remind_days' => null,
            'remind_exp' => 1,
            'status' => 'active',
        ]);

        $service = new FilingExpiryReminderService($this->pdo);
        $candidates = $service->findReminderCandidates();

        $ids = array_map(fn ($c) => (int) $c['rec_id'], $candidates);
        $this->assertCount(2, $candidates);
        $this->assertContains($id1, $ids);
        $this->assertContains($id2, $ids);
    }

    /**
     * 2. Agregasi: dua berkas satu pemilik = SATU lonceng dan SATU email berisi keduanya; dua pemilik = dua pengiriman.
     */
    public function test_aggregation_per_owner(): void
    {
        // Setup User 10 & 20
        DB::connection('run')->table('sysitc_users')->insert([
            ['rec_id' => 10, 'login_rec_id' => 101, 'account_nm' => 'Budi'],
            ['rec_id' => 20, 'login_rec_id' => 102, 'account_nm' => 'Siti'],
        ]);
        DB::connection('run')->table('sysitc_login')->insert([
            ['rec_id' => 101, 'email_id' => 'budi@test.com'],
            ['rec_id' => 102, 'email_id' => 'siti@test.com'],
        ]);

        $today = date('Y-m-d');
        // User 10 punya 2 berkas
        $this->insertFileRecord([
            'file_name' => 'Invoice Budi 1.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => $today,
            'remind_days' => 3,
        ]);
        $this->insertFileRecord([
            'file_name' => 'Invoice Budi 2.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'warningdt' => $today,
            'remind_days' => 5,
        ]);

        // User 20 punya 1 berkas
        $this->insertFileRecord([
            'file_name' => 'Kontrak Siti.pdf',
            'userid' => 20,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+4 days')),
            'warningdt' => $today,
            'remind_days' => 4,
        ]);

        $mailMock = $this->createMock(MailService::class);
        $sentEmails = [];
        $mailMock->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function ($toEmail, $toName, $subject, $body) use (&$sentEmails) {
                $sentEmails[] = compact('toEmail', 'toName', 'subject', 'body');

                return true;
            });

        $service = new FilingExpiryReminderService($this->pdo, $mailMock);
        $stats = $service->sendReminders();

        $this->assertSame(3, $stats['total_candidates']);
        $this->assertSame(2, $stats['total_owners']);
        $this->assertSame(2, $stats['bells_created']);
        $this->assertSame(2, $stats['emails_sent']);
        $this->assertSame(0, $stats['emails_skipped']);

        // Verifikasi lonceng di database
        $bells = DB::connection('run')->table('sys_notifications')->get();
        $this->assertCount(2, $bells);

        $bellBudi = $bells->firstWhere('recipient_user_id', 10);
        $this->assertNotNull($bellBudi);
        $this->assertStringContainsString('Invoice Budi 1.pdf', $bellBudi->message);
        $this->assertStringContainsString('Invoice Budi 2.pdf', $bellBudi->message);

        $bellSiti = $bells->firstWhere('recipient_user_id', 20);
        $this->assertNotNull($bellSiti);
        $this->assertStringContainsString('Kontrak Siti.pdf', $bellSiti->message);

        // Verifikasi email isi kedua berkas
        $emailBudi = collect($sentEmails)->firstWhere('toEmail', 'budi@test.com');
        $this->assertNotNull($emailBudi);
        $this->assertStringContainsString('Invoice Budi 1.pdf', $emailBudi['body']);
        $this->assertStringContainsString('Invoice Budi 2.pdf', $emailBudi['body']);
    }

    /**
     * 3. Idempotensi: jalan kedua mengirim nol.
     */
    public function test_idempotency_second_run_zero(): void
    {
        DB::connection('run')->table('sysitc_users')->insert([
            ['rec_id' => 10, 'login_rec_id' => 101, 'account_nm' => 'Budi'],
        ]);
        DB::connection('run')->table('sysitc_login')->insert([
            ['rec_id' => 101, 'email_id' => 'budi@test.com'],
        ]);

        $this->insertFileRecord([
            'file_name' => 'Dokumen 1.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => date('Y-m-d'),
            'remind_days' => 3,
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())->method('send')->willReturn(true);

        $service = new FilingExpiryReminderService($this->pdo, $mailMock);

        $firstRun = $service->sendReminders();
        $this->assertSame(1, $firstRun['total_candidates']);
        $this->assertSame(1, $firstRun['bells_created']);

        $secondRun = $service->sendReminders();
        $this->assertSame(0, $secondRun['total_candidates']);
        $this->assertSame(0, $secondRun['total_owners']);
        $this->assertSame(0, $secondRun['bells_created']);
    }

    /**
     * 4. Isolasi kegagalan: MailService melempar → lonceng tetap terbuat dan berkas tertandai;
     * lonceng gagal → berkas tidak tertandai.
     */
    public function test_failure_isolation(): void
    {
        DB::connection('run')->table('sysitc_users')->insert([
            ['rec_id' => 10, 'login_rec_id' => 101, 'account_nm' => 'Budi'],
        ]);
        DB::connection('run')->table('sysitc_login')->insert([
            ['rec_id' => 101, 'email_id' => 'budi@test.com'],
        ]);

        $fid1 = $this->insertFileRecord([
            'file_name' => 'Dokumen Email Lempar.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => date('Y-m-d'),
            'remind_days' => 3,
        ]);

        // Skenario A: MailService melempar exception
        $mailThrow = $this->createMock(MailService::class);
        $mailThrow->method('send')->willThrowException(new \RuntimeException('SMTP down'));

        $serviceA = new FilingExpiryReminderService($this->pdo, $mailThrow);
        $statsA = $serviceA->sendReminders();

        $this->assertSame(1, $statsA['bells_created']);
        $this->assertSame(1, $statsA['emails_skipped']);
        $rowA = DB::connection('run')->table('file_system')->where('rec_id', $fid1)->first();
        $this->assertNotNull($rowA->reminded_at);

        // Skenario B: Lonceng gagal (Notification mock throwing)
        $fid2 = $this->insertFileRecord([
            'file_name' => 'Dokumen Lonceng Gagal.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => date('Y-m-d'),
            'remind_days' => 3,
        ]);

        $notificationFail = $this->createMock(Notification::class);
        $notificationFail->method('create')->willThrowException(new \RuntimeException('DB locked'));

        $serviceB = new FilingExpiryReminderService($this->pdo, null, $notificationFail);
        $statsB = $serviceB->sendReminders();

        $this->assertSame(0, $statsB['bells_created']);
        $this->assertSame(1, $statsB['failed']);
        $rowB = DB::connection('run')->table('file_system')->where('rec_id', $fid2)->first();
        $this->assertNull($rowB->reminded_at, 'Berkas tidak boleh tertandai bila lonceng gagal');
    }

    /**
     * 5. Pemilik tanpa email: lonceng tetap terkirim, email dilewati dan terhitung di laporan.
     */
    public function test_owner_without_email(): void
    {
        // User 30 tanpa email di sysitc_login maupun sysitc_usermail
        DB::connection('run')->table('sysitc_users')->insert([
            ['rec_id' => 30, 'login_rec_id' => 103, 'account_nm' => 'TanpaEmail'],
        ]);

        $fid = $this->insertFileRecord([
            'file_name' => 'Dokumen Tanpa Email.pdf',
            'userid' => 30,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => date('Y-m-d'),
            'remind_days' => 3,
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->never())->method('send');

        $service = new FilingExpiryReminderService($this->pdo, $mailMock);
        $stats = $service->sendReminders();

        $this->assertSame(1, $stats['bells_created']);
        $this->assertSame(0, $stats['emails_sent']);
        $this->assertSame(1, $stats['emails_skipped']);

        $row = DB::connection('run')->table('file_system')->where('rec_id', $fid)->first();
        $this->assertNotNull($row->reminded_at);
    }

    /**
     * 6. Reset saat edit & jalur data:
     * - Upload menyimpan remind_days + warningdt yang benar
     * - Jalur reservasi (activateReservedFile) melakukan hal yang sama
     * - Edit mengubah hari/tanggal → warningdt terhitung ulang + reminded_at kosong
     * - Edit tanpa menyentuh tanggal/hari → keduanya tidak berubah
     * - remind_days kosong → warningdt NULL dan berkas tidak pernah menjadi kandidat
     */
    public function test_data_pipeline_and_edit_reset(): void
    {
        $drive = new FileSystemDrive($this->pdo);

        // A. InsertFile langsung
        $id1 = $drive->insertFile([
            'trxno' => 'FSY-TEST-1',
            'file_name' => 'File 1.pdf',
            'folder_loc' => '2610',
            'upload_flnm' => 'f1.pdf',
            'file_type' => 'PDF',
            'userid' => 10,
            'expired_at' => '2026-11-10 12:00:00',
            'remind_days' => 5,
        ]);
        $row1 = DB::connection('run')->table('file_system')->where('rec_id', $id1)->first();
        $this->assertEquals(5, $row1->remind_days);
        $this->assertEquals(1, $row1->remind_exp);
        $this->assertEquals('2026-11-05', $row1->warningdt);

        // B. Jalur reservasi (activateReservedFile)
        DB::connection('run')->table('file_system')->insert([
            'rec_id' => 99,
            'userid' => 10,
            'status' => 'reserved',
        ]);
        $drive->activateReservedFile(99, [
            'trxno' => 'FSY-RES-99',
            'file_name' => 'File Reserved.pdf',
            'folder_loc' => '2610',
            'file_type' => 'PDF',
            'userid' => 10,
            'expired_at' => '2026-12-01 10:00:00',
            'remind_days' => 10,
        ]);
        $rowRes = DB::connection('run')->table('file_system')->where('rec_id', 99)->first();
        $this->assertEquals(10, $rowRes->remind_days);
        $this->assertEquals(1, $rowRes->remind_exp);
        $this->assertEquals('2026-11-21', $rowRes->warningdt);

        // C. Edit mengubah hari / tanggal -> warningdt dihitung ulang & reminded_at direset NULL
        DB::connection('run')->table('file_system')->where('rec_id', $id1)->update([
            'reminded_at' => '2026-11-05 07:00:00',
        ]);
        $drive->updateMetadata($id1, [
            'remind_days' => 7,
        ]);
        $row1After = DB::connection('run')->table('file_system')->where('rec_id', $id1)->first();
        $this->assertEquals(7, $row1After->remind_days);
        $this->assertEquals('2026-11-03', $row1After->warningdt);
        $this->assertNull($row1After->reminded_at, 'reminded_at wajib reset NULL saat remind_days berubah');

        // D. Edit tanpa menyentuh tanggal/hari -> warningdt & reminded_at tidak berubah
        DB::connection('run')->table('file_system')->where('rec_id', $id1)->update([
            'reminded_at' => '2026-11-04 07:00:00',
        ]);
        $drive->updateMetadata($id1, [
            'file_name' => 'File 1 Renamed.pdf',
        ]);
        $row1Untouched = DB::connection('run')->table('file_system')->where('rec_id', $id1)->first();
        $this->assertEquals('File 1 Renamed.pdf', $row1Untouched->file_name);
        $this->assertEquals('2026-11-03', $row1Untouched->warningdt);
        $this->assertEquals('2026-11-04 07:00:00', $row1Untouched->reminded_at);

        // E. remind_days kosong -> warningdt NULL dan bukan kandidat
        $idEmpty = $drive->insertFile([
            'trxno' => 'FSY-EMPTY-1',
            'file_name' => 'File Tanpa Pengingat.pdf',
            'folder_loc' => '2610',
            'upload_flnm' => 'fempty.pdf',
            'file_type' => 'PDF',
            'userid' => 10,
            'expired_at' => '2026-11-10 12:00:00',
            'remind_days' => null,
        ]);
        $rowEmpty = DB::connection('run')->table('file_system')->where('rec_id', $idEmpty)->first();
        $this->assertNull($rowEmpty->remind_days);
        $this->assertEquals(0, $rowEmpty->remind_exp);
        $this->assertNull($rowEmpty->warningdt);

        // F. Edit DENGAN key expired_at dan remind_days yang nilainya SAMA persis
        // -> reminded_at dan warningdt harus BERTAHAN
        DB::connection('run')->table('file_system')->where('rec_id', $id1)->update([
            'reminded_at' => '2026-11-04 07:00:00',
            'warningdt' => '2026-11-03',
            'expired_at' => '2026-11-10 12:00:00',
            'remind_days' => 7,
        ]);
        $drive->updateMetadata($id1, [
            'file_name' => 'File 1 Sama Persis.pdf',
            'expired_at' => '2026-11-10 12:00:00',
            'remind_days' => 7,
        ]);
        $row1Same = DB::connection('run')->table('file_system')->where('rec_id', $id1)->first();
        $this->assertEquals('2026-11-04 07:00:00', $row1Same->reminded_at, 'reminded_at harus bertahan jika nilai expired_at & remind_days sama persis');
        $this->assertEquals('2026-11-03', $row1Same->warningdt);

        // G. Edit dengan tanggal berbeda -> reminded_at reset NULL
        $drive->updateMetadata($id1, [
            'expired_at' => '2026-11-12 12:00:00',
            'remind_days' => 7,
        ]);
        $row1Different = DB::connection('run')->table('file_system')->where('rec_id', $id1)->first();
        $this->assertNull($row1Different->reminded_at, 'reminded_at harus reset NULL saat tanggal berbeda');
        $this->assertEquals('2026-11-05', $row1Different->warningdt);

        $service = new FilingExpiryReminderService($this->pdo);
        $candidates = $service->findReminderCandidates();
        $candIds = array_map(fn ($c) => (int) $c['rec_id'], $candidates);
        $this->assertNotContains($idEmpty, $candIds);
    }

    /**
     * 7. Test endpoint action: get_file_info memuat remind_days & update_metadata memperbarui remind_days
     */
    public function test_controller_action_metadata_endpoints(): void
    {
        $drive = new FileSystemDrive($this->pdo);
        $fid = $drive->insertFile([
            'trxno' => 'FSY-ACTION-1',
            'file_name' => 'Original.pdf',
            'folder_loc' => '2610',
            'upload_flnm' => 'orig.pdf',
            'file_type' => 'PDF',
            'userid' => 10,
            'expired_at' => '2026-11-20 10:00:00',
            'remind_days' => 5,
        ]);

        // Simulasikan session login user 10
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => 10,
            'login_rec_id' => 10,
            'account_nm' => 'Owner 10',
            'alias_nm' => 'OWN10',
        ]);
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fid,
            'share_cat' => 0,
            'othercode' => '10',
            'created_by' => 10,
        ]);
        session(['user_id' => 10, 'auth_db' => 'run']);

        // Test get_file_info via FilingSystemController
        $resGet = $this->post('/modules/cbt_ops/filing_system/action.php', [
            'action' => 'get_file_info',
            'filing_id' => $fid,
        ]);
        $resGet->assertOk();
        $dataGet = $resGet->json('data');
        $this->assertSame(5, $dataGet['remind_days']);

        // Test update_metadata dengan perubahan remind_days
        $resUpd = $this->post('/modules/cbt_ops/filing_system/action.php', [
            'action' => 'update_metadata',
            'filing_id' => $fid,
            'display_name' => 'Original Renamed',
            'expired_at' => '2026-11-25 10:00:00',
            'remind_days' => 10,
            'expired_action' => 'archive',
        ]);
        $resUpd->assertOk();
        $this->assertTrue($resUpd->json('success'));

        $rowUpdated = DB::connection('run')->table('file_system')->where('rec_id', $fid)->first();
        $this->assertEquals(10, $rowUpdated->remind_days);
        $this->assertEquals('archive', $rowUpdated->expired_action);
        $this->assertEquals('2026-11-15', $rowUpdated->warningdt);
        $this->assertNull($rowUpdated->reminded_at);
    }

    /**
     * 8. Test artisan command filing:send-expiry-reminders
     */
    public function test_artisan_command_run(): void
    {
        $this->insertFileRecord([
            'file_name' => 'File Artisan.pdf',
            'userid' => 10,
            'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'warningdt' => date('Y-m-d'),
            'remind_days' => 3,
        ]);

        $this->artisan('filing:send-expiry-reminders', ['--limit' => 50])
            ->expectsOutputToContain('RUNITC FILING SYSTEM - EXPIRY REMINDER')
            ->expectsOutputToContain('Total Kandidat : 1')
            ->expectsOutputToContain('Lonceng Terbuat: 1')
            ->assertSuccessful();
    }
}

