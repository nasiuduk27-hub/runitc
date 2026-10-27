<?php

namespace Tests\Feature\CreditUnion;

use App\Auth\LegacyUser;
use App\Models\CreditUnion\CreditUnionLoanApplication;
use App\Models\CreditUnion\CreditUnionLoanApplicationAction;
use App\Services\CreditUnion\CreditUnionNotificationService;
use App\Services\CreditUnion\LoanApplicationService;
use App\Services\CreditUnion\LoanSimulationService;
use App\Services\MailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LoanApplicationHodApprovalTest extends TestCase
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
            $table->string('hod_email', 150)->nullable();
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
            $table->timestamp('hod_approved_at')->nullable();
            $table->unsignedInteger('hod_approved_by')->nullable();
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

        Schema::connection('run')->create('sysitc_userbank', function (Blueprint $table): void {
            $table->id('rec_id');
            $table->unsignedInteger('user_recid');
            $table->string('bnkcd', 20);
            $table->string('accnm', 150);
            $table->string('accno', 80);
            $table->unsignedTinyInteger('asdefault')->default(1);
        });

        Schema::connection('run')->create('cu_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('description', 255)->nullable();
            $table->timestamps();
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

    private function seedCuAdmin(int $userId, string $name = 'CU Admin', string $email = 'admin.cu@example.com'): void
    {
        DB::connection('run')->table('sysitc_usracc')->insert([
            'user_rec_id' => $userId,
            'access_code' => '03',
            'access_account' => '410',
        ]);
        DB::connection('run')->table('sysitc_grpacc')->insert([
            'grpaccess' => '03',
            'grpacc' => '410',
            'grpdesc' => 'CU ADMIN GROUP',
        ]);
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $userId,
            'account_nm' => $name,
            'login_rec_id' => $userId,
        ]);
        DB::connection('run')->table('sysitc_login')->insert([
            'rec_id' => $userId,
            'email_id' => $email,
        ]);
    }

    private function seedMemberUser(int $userId, int $memberRecId, string $icuno = '0001234', string $name = 'Anggota Test'): void
    {
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $userId,
            'account_nm' => $name,
            'login_rec_id' => $userId,
        ]);
        DB::connection('run')->table('sysitc_login')->insert([
            'rec_id' => $userId,
            'email_id' => 'member@example.com',
        ]);
        DB::connection('mysql')->table('icu_member')->insert([
            'rec_id' => $memberRecId,
            'itc_user_id' => $userId,
            'icuno' => $icuno,
            'icunm' => $name,
            'st_aktif' => 1,
            'outstanding' => 0,
        ]);
    }

    private function login(int $userId): void
    {
        $user = new LegacyUser([
            'rec_id' => $userId,
            'user_id' => 'USR-'.$userId,
            'account_nm' => 'User '.$userId,
        ]);
        Auth::login($user);
    }

    /**
     * (a) Pengajuan tanpa email HOD ditolak validasi; format email tak valid ditolak.
     */
    public function test_pengajuan_tanpa_email_hod_atau_format_tidak_valid_ditolak(): void
    {
        $this->seedMemberUser(10, 100);
        $this->login(10);

        // Tanpa hod_email
        $response = $this->post(route('cu.applications.store'), [
            'principal_amount' => '1.000.000',
            'tenor_months' => 12,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Keperluan renovasi',
            'fund_release_method' => 'transfer',
            'admin_fee' => 50000,
            'admin_fee_type' => 'include',
            'bank_code' => 'BCA',
            'account_name' => 'Anggota Test',
            'account_no' => '1234567890',
        ]);
        $response->assertSessionHasErrors('hod_email');

        // hod_email bukan format email valid
        $response2 = $this->post(route('cu.applications.store'), [
            'principal_amount' => '1.000.000',
            'tenor_months' => 12,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Keperluan renovasi',
            'hod_email' => 'bukan-email-valid',
            'fund_release_method' => 'transfer',
            'admin_fee' => 50000,
            'admin_fee_type' => 'include',
            'bank_code' => 'BCA',
            'account_name' => 'Anggota Test',
            'account_no' => '1234567890',
        ]);
        $response2->assertSessionHasErrors('hod_email');
    }

    /**
     * (b) Pengajuan valid tersimpan dengan hod_email dan email terkirim dengan:
     * To = alamat admin terdaftar, CC = hod_email, Reply-To = alamat admin.
     */
    public function test_pengajuan_valid_tersimpan_dengan_hod_email_dan_email_kolektif_terkirim(): void
    {
        $this->seedCuAdmin(1, 'Admin 1', 'admin1@creditunion.com');
        $this->seedCuAdmin(2, 'Admin 2', 'admin2@creditunion.com');
        $this->seedMemberUser(10, 100, '0001001', 'Budi Pemohon');
        $this->login(10);

        $mockMail = Mockery::mock(MailService::class);
        $mockMail->shouldReceive('send')
            ->once()
            ->withArgs(function ($toEmail, $toName, $subject, $body, $isHtml, $attachments, $cc, $replyTo): bool {
                $toMatches = is_array($toEmail) && in_array('admin1@creditunion.com', $toEmail, true) && in_array('admin2@creditunion.com', $toEmail, true);
                $ccMatches = is_array($cc) && in_array('hod.divisi@perusahaan.com', $cc, true);
                $replyMatches = is_array($replyTo) && in_array('admin1@creditunion.com', $replyTo, true);

                return $toMatches && $ccMatches && $replyMatches;
            })
            ->andReturn(true);

        $this->app->instance(MailService::class, $mockMail);

        $response = $this->post(route('cu.applications.store'), [
            'principal_amount' => '2.000.000',
            'tenor_months' => 12,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Beli laptop dinas',
            'hod_email' => 'hod.divisi@perusahaan.com',
            'fund_release_method' => 'transfer',
            'admin_fee' => 50000,
            'admin_fee_type' => 'include',
            'bank_code' => 'BCA',
            'account_name' => 'Budi Pemohon',
            'account_no' => '5432109876',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appRow = CreditUnionLoanApplication::query()->first();
        $this->assertNotNull($appRow);
        $this->assertSame('hod.divisi@perusahaan.com', $appRow->hod_email);
        $this->assertSame('submitted', $appRow->status);
    }

    /**
     * (c) Kegagalan kirim email tidak menggagalkan penyimpanan pengajuan.
     */
    public function test_kegagalan_kirim_email_tidak_menggagalkan_penyimpanan_pengajuan(): void
    {
        $this->seedCuAdmin(1, 'Admin 1', 'admin1@creditunion.com');
        $this->seedMemberUser(10, 100, '0001001', 'Budi Pemohon');
        $this->login(10);

        $mockMail = Mockery::mock(MailService::class);
        $mockMail->shouldReceive('send')->andThrow(new \RuntimeException('Koneksi SMTP down'));
        $this->app->instance(MailService::class, $mockMail);

        $response = $this->post(route('cu.applications.store'), [
            'principal_amount' => '1.500.000',
            'tenor_months' => 10,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Biaya pendidikan',
            'hod_email' => 'hod.fail@perusahaan.com',
            'fund_release_method' => 'transfer',
            'admin_fee' => 50000,
            'admin_fee_type' => 'include',
            'bank_code' => 'BCA',
            'account_name' => 'Budi Pemohon',
            'account_no' => '1122334455',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appRow = CreditUnionLoanApplication::query()->where('hod_email', 'hod.fail@perusahaan.com')->first();
        $this->assertNotNull($appRow);
        $this->assertSame('submitted', $appRow->status);
    }

    /**
     * (d) Penanda persetujuan HOD oleh admin tercatat (kolom + aksi + audit),
     * non-admin dilarang menandai, dan aksi idempoten.
     */
    public function test_penanda_persetujuan_hod_oleh_admin_tercatat_dan_idempoten(): void
    {
        $this->seedCuAdmin(1, 'Admin CU', 'admin@cu.com');
        $this->seedMemberUser(10, 100, '0001001', 'Anggota Biasa');

        $application = CreditUnionLoanApplication::query()->create([
            'member_rec_id' => 100,
            'member_icuno' => '0001001',
            'member_name' => 'Anggota Biasa',
            'principal_amount' => 1000000,
            'tenor_months' => 12,
            'annual_rate_percent' => 6.0,
            'calculation_method' => 'flat',
            'descr' => 'Test HOD mark',
            'hod_email' => 'hod@divisi.com',
            'status' => 'submitted',
            'applicant_user_id' => 10,
        ]);

        // 1. Non-admin mencoba menandai -> 403 Forbidden
        $this->login(10);
        $resForbidden = $this->post(route('cu.applications.markHodApproval'), ['id' => $application->id]);
        $resForbidden->assertStatus(403);

        // 2. Admin menandai -> berhasil
        $this->login(1);
        $resAdmin = $this->post(route('cu.applications.markHodApproval'), ['id' => $application->id]);
        $resAdmin->assertRedirect();

        $application->refresh();
        $this->assertNotNull($application->hod_approved_at);
        $this->assertSame(1, (int) $application->hod_approved_by);

        // Periksa cu_loan_application_actions
        $action = CreditUnionLoanApplicationAction::query()
            ->where('application_id', $application->id)
            ->where('action', CreditUnionLoanApplicationAction::ACTION_HOD_APPROVED)
            ->first();
        $this->assertNotNull($action);
        $this->assertSame('Persetujuan HOD Diterima', $action->actionLabel());

        // Periksa sys_audit_log
        $audit = DB::connection('run')->table('sys_audit_log')
            ->where('target_id', $application->id)
            ->where('action', 'cu.loan_application.hod_approved')
            ->first();
        $this->assertNotNull($audit);
        $meta = json_decode((string) $audit->metadata_json, true);
        $this->assertSame('hod@divisi.com', $meta['hod_email']);
        $this->assertSame(1, $meta['marked_by']);

        // 3. Admin kedua mencoba menandai ulang -> idempoten, tidak menimpa
        $firstApprovedAt = $application->hod_approved_at;
        $this->seedCuAdmin(2, 'Admin 2', 'admin2@cu.com');
        $this->login(2);

        $resAdmin2 = $this->post(route('cu.applications.markHodApproval'), ['id' => $application->id]);
        $resAdmin2->assertRedirect();

        $application->refresh();
        $this->assertSame(1, (int) $application->hod_approved_by);
        $this->assertEquals($firstApprovedAt, $application->hod_approved_at);

        // Baris aksi dan audit tidak bertambah
        $actionCount = CreditUnionLoanApplicationAction::query()
            ->where('application_id', $application->id)
            ->where('action', CreditUnionLoanApplicationAction::ACTION_HOD_APPROVED)
            ->count();
        $this->assertSame(1, $actionCount);
    }

    /**
     * (e) MailService tanpa CC/Reply-To berperilaku identik sebelumnya (kompatibilitas mundur).
     */
    public function test_mail_service_backward_compatibility_tanpa_cc_reply_to(): void
    {
        Mail::fake();

        $service = new MailService;
        $sent = $service->send('user@example.com', 'User Contoh', 'Subjek Lama', 'Isi Body Lama');

        $this->assertTrue($sent);
    }

    /**
     * (f) Email ke HOD tidak memuat nomor rekening penuh (masking terbukti di assert).
     */
    public function test_email_ke_hod_tidak_memuat_nomor_rekening_penuh_dan_termasking(): void
    {
        $this->seedCuAdmin(1, 'Admin 1', 'admin@cu.com');
        $this->seedMemberUser(10, 100, '0001001', 'Nasabah Test');

        $app = CreditUnionLoanApplication::query()->create([
            'member_rec_id' => 100,
            'member_icuno' => '0001001',
            'member_name' => 'Nasabah Test',
            'principal_amount' => 5000000,
            'tenor_months' => 24,
            'annual_rate_percent' => 7.5,
            'calculation_method' => 'flat',
            'descr' => 'Pinjaman Darurat',
            'hod_email' => 'hod.boss@perusahaan.com',
            'fund_release_method' => 'transfer',
            'admin_fee' => 50000,
            'admin_fee_type' => 'include',
            'bank_bnkcd' => 'BCA',
            'bank_accnm' => 'Nasabah Test',
            'bank_accno' => '987654321098',
            'status' => 'submitted',
            'applicant_user_id' => 10,
        ]);

        $capturedBody = '';
        $mockMail = Mockery::mock(MailService::class);
        $mockMail->shouldReceive('send')
            ->once()
            ->withArgs(function ($toEmail, $toName, $subject, $body) use (&$capturedBody): bool {
                $capturedBody = $body;

                return true;
            })
            ->andReturn(true);

        $notifService = new CreditUnionNotificationService($mockMail);
        $notifService->notifyLoanApplicationSubmitted($app, 10, 'http://test.url/applications/detail?id=1', 'Bank Central Asia');

        $this->assertNotEmpty($capturedBody);
        $this->assertStringContainsString('••••2109', $capturedBody);
        $this->assertStringNotContainsString('987654321098', $capturedBody);
        $this->assertStringContainsString('PETUNJUK KEPALA DIVISI (HOD)', $capturedBody);
    }
}
