<?php

namespace Tests\Feature\CreditUnion;

use App\Auth\LegacyUser;
use App\Models\CreditUnion\CreditUnionLoanApplication;
use App\Models\CreditUnion\CreditUnionLoanApplicationAction;
use App\Services\CreditUnion\CreditUnionNotificationService;
use App\Services\CreditUnion\LoanApplicationService;
use App\Services\CreditUnion\LoanPostingService;
use App\Services\MailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class LoanApplicationTransferProofTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.run' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('run');

        $this->createSchema();
        Storage::fake('local');
    }

    private function createSchema(): void
    {
        Schema::connection('run')->create('cu_loan_applications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('member_rec_id')->default(0);
            $table->string('member_icuno', 7)->default('');
            $table->string('member_name', 40)->default('');
            $table->unsignedBigInteger('principal_amount')->default(1_000_000);
            $table->unsignedTinyInteger('tenor_months')->default(12);
            $table->decimal('annual_rate_percent', 5, 2)->default(6.0);
            $table->string('calculation_method', 20)->default('flat');
            $table->string('descr', 100)->default('Pinjaman test');
            $table->string('status', 30)->default('submitted');
            $table->timestamp('transfer_started_at')->nullable();
            $table->unsignedInteger('transfer_started_by')->nullable();
            $table->string('transfer_reference', 100)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('transfer_proof_path')->nullable();
            $table->string('transfer_proof_original_name')->nullable();
            $table->string('transfer_proof_mime', 100)->nullable();
            $table->unsignedBigInteger('transfer_proof_size')->nullable();
            $table->timestamp('transfer_proof_uploaded_at')->nullable();
            $table->unsignedInteger('transfer_proof_uploaded_by')->nullable();
            $table->unsignedBigInteger('monthly_installment')->default(88_333);
            $table->unsignedBigInteger('total_interest')->default(60_000);
            $table->unsignedBigInteger('total_payment')->default(1_060_000);
            $table->longText('schedule_json')->nullable();
            $table->unsignedInteger('applicant_user_id')->default(0);
            $table->unsignedInteger('reviewer_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->unsignedInteger('posted_loan_rec_id')->nullable();
            $table->string('fund_release_method', 20)->default('transfer');
            $table->unsignedBigInteger('admin_fee')->default(50_000);
            $table->string('admin_fee_type', 20)->default('include');
            $table->string('bank_bnkcd', 20)->nullable()->default('BCA');
            $table->string('bank_accnm', 150)->nullable()->default('Budi Santoso');
            $table->string('bank_accno', 80)->nullable()->default('1234567890');
            $table->timestamps();
        });

        Schema::connection('run')->create('cu_loan_application_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('actor_user_id');
            $table->string('actor_name', 80);
            $table->timestamps();
        });

        Schema::connection('run')->create('sysitc_usracc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('user_rec_id');
            $table->string('access_code');
            $table->string('access_account');
        });

        Schema::connection('run')->create('sysitc_grpacc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('grpaccess');
            $table->string('grpacc');
            $table->string('grpdesc');
        });

        Schema::connection('run')->create('sys_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('recipient_user_id');
            $table->unsignedInteger('sender_user_id')->nullable();
            $table->string('type', 80);
            $table->string('title', 150);
            $table->text('message');
            $table->string('target_url', 255);
            $table->string('ref_table', 80)->nullable();
            $table->unsignedInteger('ref_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('run')->create('sys_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->string('target_type', 80)->nullable();
            $table->unsignedInteger('target_id')->nullable();
            $table->text('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('run')->create('sys_msttable', function (Blueprint $table): void {
            $table->id('rec_id');
            $table->string('tbl_code', 10);
            $table->string('code', 20);
            $table->string('descr', 100);
            $table->unsignedTinyInteger('statrec')->default(1);
        });

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->id('rec_id');
            $table->string('account_nm', 80)->nullable();
            $table->unsignedInteger('login_rec_id')->nullable();
        });

        Schema::connection('run')->create('sysitc_login', function (Blueprint $table): void {
            $table->id('rec_id');
            $table->string('email_id', 120)->nullable();
        });

        Schema::connection('run')->create('sysitc_usermail', function (Blueprint $table): void {
            $table->id('rec_id');
            $table->unsignedInteger('user_recid');
            $table->string('email', 120);
            $table->unsignedTinyInteger('asdefault')->default(1);
        });

        Schema::connection('mysql')->create('icu_member', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('itc_user_id')->nullable();
            $table->string('icuno', 7)->default('');
            $table->string('icunm', 40)->default('');
            $table->unsignedTinyInteger('st_aktif')->default(1);
            $table->unsignedBigInteger('outstanding')->default(0);
        });

        DB::connection('run')->table('sys_msttable')->insert([
            'tbl_code' => '51',
            'code' => 'BCA',
            'descr' => 'Bank Central Asia',
            'statrec' => 1,
        ]);
    }

    private function seedCuAdmin(int $userId, string $name = 'CU Admin'): void
    {
        DB::connection('run')->table('sysitc_usracc')->insert([
            'user_rec_id' => $userId,
            'access_code' => '03',
            'access_account' => '410',
        ]);

        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => '03',
            'grpacc' => '410',
            'grpdesc' => 'CU Admin',
        ]);

        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $userId,
            'account_nm' => $name,
            'login_rec_id' => $userId,
        ]);

        DB::connection('run')->table('sysitc_login')->insert([
            'rec_id' => $userId,
            'email_id' => 'admin'.$userId.'@runitc.test',
        ]);
    }

    private function seedMember(int $memberRecId, int $userId, string $name = 'Anggota Test'): void
    {
        DB::connection('mysql')->table('icu_member')->insert([
            'rec_id' => $memberRecId,
            'itc_user_id' => $userId,
            'icuno' => 'CU'.str_pad((string) $memberRecId, 5, '0', STR_PAD_LEFT),
            'icunm' => $name,
            'st_aktif' => 1,
            'outstanding' => 0,
        ]);

        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $userId,
            'account_nm' => $name,
            'login_rec_id' => $userId,
        ]);

        DB::connection('run')->table('sysitc_login')->insert([
            'rec_id' => $userId,
            'email_id' => 'member'.$userId.'@runitc.test',
        ]);
    }

    private function makeApplication(int $makerUserId, int $memberRecId = 101, string $status = 'approved', string $method = 'transfer'): CreditUnionLoanApplication
    {
        return CreditUnionLoanApplication::query()->create([
            'member_rec_id' => $memberRecId,
            'member_icuno' => 'CU'.str_pad((string) $memberRecId, 5, '0', STR_PAD_LEFT),
            'member_name' => 'Anggota '.$memberRecId,
            'principal_amount' => 1_000_000,
            'tenor_months' => 12,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Pinjaman Test',
            'status' => $status,
            'monthly_installment' => 88_333,
            'total_interest' => 60_000,
            'total_payment' => 1_060_000,
            'schedule_json' => json_encode([['seqno' => 1, 'periode' => '202611', 'amount' => 83333, 'int_amt' => 5000, 'total' => 88333, 'outstand' => 916667]]),
            'applicant_user_id' => $makerUserId,
            'fund_release_method' => $method,
            'admin_fee' => 50_000,
            'admin_fee_type' => 'include',
            'bank_bnkcd' => 'BCA',
            'bank_accnm' => 'Budi Santoso',
            'bank_accno' => '1234567890',
        ]);
    }

    private function loginAs(int $userId): void
    {
        $user = new LegacyUser($userId, 'User '.$userId, 'user'.$userId);
        Auth::setUser($user);
    }

    /**
     * Skenario (a): Konfirmasi transfer tanpa berkas ditolak dan status tetap transfer_processing.
     */
    public function test_post_without_proof_file_is_rejected_for_transfer_method(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $this->seedMember(101, 10, 'Maker Member');
        $application = $this->makeApplication(makerUserId: 10, memberRecId: 101, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_reference' => 'TRX-12345',
        ]);

        $response->assertSessionHasErrors('transfer_proof');
        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationService::STATUS_TRANSFER_PROCESSING,
            'transfer_proof_path' => null,
        ], 'run');
    }

    /**
     * Skenario (b): Tipe berkas tidak diizinkan atau ukuran melebihi 5 MB ditolak.
     */
    public function test_post_with_invalid_file_type_or_excessive_size_is_rejected(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->loginAs(20);

        // Kasus 1: Tipe berkas tidak diizinkan (.txt / .exe)
        $invalidFile = UploadedFile::fake()->create('script.exe', 500, 'application/x-msdownload');
        $response1 = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_proof' => $invalidFile,
        ]);
        $response1->assertSessionHasErrors('transfer_proof');

        // Kasus 2: Ukuran melebihi 5120 KB (5200 KB)
        $oversizedFile = UploadedFile::fake()->create('proof.pdf', 5200, 'application/pdf');
        $response2 = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_proof' => $oversizedFile,
        ]);
        $response2->assertSessionHasErrors('transfer_proof');
    }

    /**
     * Skenario (c): Jalur sukses — berkas tersimpan di disk privat, 6 kolom terisi,
     * status posted, email pencairan membawa lampiran berkas bukti.
     */
    public function test_post_happy_path_stores_proof_and_attaches_to_email(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $this->seedMember(101, 10, 'Member 10');
        $application = $this->makeApplication(makerUserId: 10, memberRecId: 101, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->mock(LoanPostingService::class, function ($mock): void {
            $mock->shouldReceive('post')->once()->andReturn(777);
        });

        $this->mock(CreditUnionNotificationService::class, function ($mock) use ($application): void {
            $mock->shouldReceive('notifyMember')
                ->once()
                ->with(
                    101,
                    10,
                    20,
                    'cu.loan_application.disbursed',
                    'Dana Pinjaman Telah Ditransfer',
                    Mockery::type('string'),
                    Mockery::type('string'),
                    'cu_loan_application',
                    (int) $application->id,
                    Mockery::on(function (array $attachments): bool {
                        return count($attachments) === 1
                            && str_contains($attachments[0]['name'], 'bukti_transfer.pdf')
                            && ! empty($attachments[0]['path']);
                    })
                );
        });

        $this->loginAs(20);

        $file = UploadedFile::fake()->create('bukti_transfer.pdf', 120, 'application/pdf');

        $response = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_reference' => 'TRX-999',
            'transfer_proof' => $file,
        ]);

        $response->assertRedirect(route('cu.applications.detail', ['id' => $application->id]));

        $reloaded = CreditUnionLoanApplication::query()->find($application->id);
        $this->assertNotNull($reloaded->transfer_proof_path);
        $this->assertEquals('bukti_transfer.pdf', $reloaded->transfer_proof_original_name);
        $this->assertEquals('application/pdf', $reloaded->transfer_proof_mime);
        $this->assertEquals(20, $reloaded->transfer_proof_uploaded_by);
        $this->assertNotNull($reloaded->transfer_proof_uploaded_at);
        $this->assertGreaterThan(0, $reloaded->transfer_proof_size);

        Storage::disk('local')->assertExists($reloaded->transfer_proof_path);

        $this->assertDatabaseHas('cu_loan_application_actions', [
            'application_id' => $application->id,
            'action' => CreditUnionLoanApplicationAction::ACTION_POSTED,
            'actor_user_id' => 20,
        ], 'run');
    }

    /**
     * Skenario (d): Bila posting gagal atau exception sesudah berkas tersimpan,
     * berkas fisik DIHAPUS (anti berkas yatim).
     */
    public function test_post_failure_cleans_up_orphaned_proof_file(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->mock(LoanPostingService::class, function ($mock): void {
            $mock->shouldReceive('post')->once()->andThrow(new \RuntimeException('Koneksi database posting terputus'));
        });

        $this->loginAs(20);

        $file = UploadedFile::fake()->image('bukti_tf.jpg', 600, 600);

        $response = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_proof' => $file,
        ]);

        $response->assertSessionHasErrors('decision');

        // Pastikan tidak ada berkas tersisa di folder transfer-proofs/{application->id}
        $filesInFolder = Storage::disk('local')->files('transfer-proofs/'.$application->id);
        $this->assertEmpty($filesInFolder, 'Berkas bukti seharusnya dihapus saat posting gagal (anti berkas yatim).');

        $reloaded = CreditUnionLoanApplication::query()->find($application->id);
        $this->assertNull($reloaded->transfer_proof_path);
    }

    /**
     * Skenario (e): Rute unduh/pratinjau bukti:
     * Anggota lain ditolak (403), anggota pemilik dan admin CU diizinkan (200).
     */
    public function test_transfer_proof_route_authorization(): void
    {
        $this->seedCuAdmin(20, 'Checker Admin');
        $this->seedMember(101, 10, 'Owner Member');
        $this->seedMember(102, 30, 'Other Member');

        $application = $this->makeApplication(makerUserId: 10, memberRecId: 101, status: LoanApplicationService::STATUS_POSTED);
        $storedPath = 'transfer-proofs/'.$application->id.'/sample_proof.pdf';
        Storage::disk('local')->put($storedPath, '%PDF-1.4 test content');

        $application->update([
            'transfer_proof_path' => $storedPath,
            'transfer_proof_original_name' => 'sample_proof.pdf',
            'transfer_proof_mime' => 'application/pdf',
            'transfer_proof_size' => 1024,
            'transfer_proof_uploaded_at' => now(),
            'transfer_proof_uploaded_by' => 20,
        ]);

        // Anggota lain (User 30) -> 403
        $this->loginAs(30);
        $resForbidden = $this->get('/credit-union/applications/transfer-proof?id='.$application->id);
        $resForbidden->assertStatus(403);

        // Anggota pemilik (User 10) -> 200
        $this->loginAs(10);
        $resOwner = $this->get('/credit-union/applications/transfer-proof?id='.$application->id);
        $resOwner->assertStatus(200);

        // Admin CU (User 20) -> 200
        $this->loginAs(20);
        $resAdmin = $this->get('/credit-union/applications/transfer-proof?id='.$application->id);
        $resAdmin->assertStatus(200);
    }

    /**
     * Skenario (f): Ganti bukti oleh admin CU menukar berkas, menghapus berkas lama,
     * dan mencatat action proof_replaced di riwayat aksi.
     */
    public function test_replace_transfer_proof_replaces_file_and_logs_action(): void
    {
        $this->seedCuAdmin(20, 'Admin 20');
        $this->seedMember(101, 10, 'Member 10');

        $application = $this->makeApplication(makerUserId: 10, memberRecId: 101, status: LoanApplicationService::STATUS_POSTED);
        $oldPath = 'transfer-proofs/'.$application->id.'/old_proof.png';
        Storage::disk('local')->put($oldPath, 'old-image-bytes');

        $application->update([
            'posted_loan_rec_id' => 999,
            'transfer_proof_path' => $oldPath,
            'transfer_proof_original_name' => 'old_proof.png',
            'transfer_proof_mime' => 'image/png',
            'transfer_proof_size' => 500,
        ]);

        $this->loginAs(20);

        $newFile = UploadedFile::fake()->create('new_proof.pdf', 300, 'application/pdf');

        $response = $this->post('/credit-union/applications/replace-transfer-proof', [
            'id' => $application->id,
            'transfer_proof' => $newFile,
        ]);

        $response->assertRedirect(route('cu.applications.detail', ['id' => $application->id]));

        $reloaded = CreditUnionLoanApplication::query()->find($application->id);
        $this->assertNotEquals($oldPath, $reloaded->transfer_proof_path);
        $this->assertEquals('new_proof.pdf', $reloaded->transfer_proof_original_name);
        $this->assertEquals('application/pdf', $reloaded->transfer_proof_mime);

        // Berkas lama harus sudah terhapus
        Storage::disk('local')->assertMissing($oldPath);
        // Berkas baru harus ada
        Storage::disk('local')->assertExists($reloaded->transfer_proof_path);

        $this->assertDatabaseHas('cu_loan_application_actions', [
            'application_id' => $application->id,
            'action' => CreditUnionLoanApplicationAction::ACTION_PROOF_REPLACED,
            'actor_user_id' => 20,
        ], 'run');
    }

    /**
     * Skenario (g): Pemanggil MailService lain tanpa lampiran berperilaku identik.
     */
    public function test_mail_service_backward_compatibility_without_attachments(): void
    {
        Mail::fake();

        $mailService = new MailService;
        $sent = $mailService->send('test@example.com', 'Test User', 'Subjek Uji', 'Isi email polos');

        $this->assertTrue($sent);
    }
}
