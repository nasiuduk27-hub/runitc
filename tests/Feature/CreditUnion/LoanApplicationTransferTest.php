<?php

namespace Tests\Feature\CreditUnion;

use App\Auth\LegacyUser;
use App\Models\CreditUnion\CreditUnionLoanApplication;
use App\Models\CreditUnion\CreditUnionLoanApplicationAction;
use App\Services\CreditUnion\CreditUnionNotificationService;
use App\Services\CreditUnion\LoanApplicationService;
use App\Services\CreditUnion\LoanPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LoanApplicationTransferTest extends TestCase
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
            $table->string('status', 30)->default('submitted');
            $table->timestamp('transfer_started_at')->nullable();
            $table->unsignedInteger('transfer_started_by')->nullable();
            $table->string('transfer_reference', 100)->nullable();
            $table->timestamp('disbursed_at')->nullable();
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
        ]);
    }

    private function makeApplication(int $makerUserId, string $status = 'approved'): CreditUnionLoanApplication
    {
        return CreditUnionLoanApplication::query()->create([
            'member_rec_id' => 101,
            'member_icuno' => 'CU00101',
            'member_name' => 'Anggota Test',
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
            'fund_release_method' => 'transfer',
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
        Auth::guard('legacy')->setUser($user);
        Auth::setUser($user);

        $this->withSession([
            'user_id' => $userId,
            'user_rec_id' => $userId,
            'account_id' => 'user'.$userId,
            'user_name' => 'User '.$userId,
            'account_nm' => 'User '.$userId,
            'auth_db' => 'run',
        ]);
    }

    public function test_maker_cannot_start_transfer_for_own_application(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_APPROVED);

        $this->loginAs(10);

        $response = $this->post('/credit-union/applications/start-transfer', [
            'id' => $application->id,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationService::STATUS_APPROVED,
            'transfer_started_by' => null,
        ], 'run');
    }

    public function test_non_maker_admin_can_start_transfer(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_APPROVED);

        $this->mock(CreditUnionNotificationService::class, function ($mock) use ($application): void {
            $mock->shouldReceive('notifyMember')
                ->once()
                ->with(
                    101,
                    10,
                    20,
                    'cu.loan_application.transfer_processing',
                    'Pengajuan Pinjaman Sedang Diproses Transfer',
                    Mockery::type('string'),
                    Mockery::type('string'),
                    'cu_loan_application',
                    (int) $application->id
                );
        });

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/start-transfer', [
            'id' => $application->id,
        ]);

        $response->assertRedirect(route('cu.applications.detail', ['id' => $application->id]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationService::STATUS_TRANSFER_PROCESSING,
            'transfer_started_by' => 20,
        ], 'run');

        $this->assertDatabaseHas('cu_loan_application_actions', [
            'application_id' => $application->id,
            'action' => CreditUnionLoanApplicationAction::ACTION_TRANSFER_PROCESSING,
            'actor_user_id' => 20,
        ], 'run');

        $this->assertDatabaseHas('sys_audit_log', [
            'action' => 'cu.loan_application.transfer_processing',
            'target_id' => $application->id,
        ], 'run');
    }

    public function test_cannot_cancel_when_in_transfer_processing(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        // Uji coba maker membatalkan
        $this->loginAs(10);
        $response = $this->post('/credit-union/applications/decide', [
            'id' => $application->id,
            'decision' => 'cancel',
        ]);
        $response->assertSessionHasErrors('decision');

        // Uji coba admin lain membatalkan
        $this->loginAs(20);
        $response = $this->post('/credit-union/applications/decide', [
            'id' => $application->id,
            'decision' => 'cancel',
        ]);
        $response->assertSessionHasErrors('decision');

        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationService::STATUS_TRANSFER_PROCESSING,
        ], 'run');
    }

    public function test_non_maker_admin_can_return_transfer_to_approved(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/return-transfer', [
            'id' => $application->id,
            'note' => 'Rekening tujuan tidak valid',
        ]);

        $response->assertRedirect(route('cu.applications.detail', ['id' => $application->id]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'status' => LoanApplicationService::STATUS_APPROVED,
        ], 'run');

        $this->assertDatabaseHas('cu_loan_application_actions', [
            'application_id' => $application->id,
            'action' => CreditUnionLoanApplicationAction::ACTION_TRANSFER_RETURNED,
            'note' => 'Rekening tujuan tidak valid',
            'actor_user_id' => 20,
        ], 'run');
    }

    public function test_return_transfer_requires_note(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/return-transfer', [
            'id' => $application->id,
            'note' => '',
        ]);

        $response->assertSessionHasErrors('note');
    }

    public function test_posting_confirmation_and_disbursed_notification(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->mock(LoanPostingService::class, function ($mock): void {
            $mock->shouldReceive('post')
                ->once()
                ->with(Mockery::type(CreditUnionLoanApplication::class), 20)
                ->andReturn(505);
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
                    Mockery::on(function (string $message): bool {
                        return str_contains($message, 'Dana Diterima: Rp 950.000')
                            && str_contains($message, '••••7890')
                            && str_contains($message, 'TRX-REF-777');
                    }),
                    Mockery::type('string'),
                    'cu_loan_application',
                    (int) $application->id
                );
        });

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
            'transfer_reference' => 'TRX-REF-777',
            'note' => 'Transfer via mobile banking berhasil',
        ]);

        $response->assertRedirect(route('cu.applications.detail', ['id' => $application->id]));

        $this->assertDatabaseHas('cu_loan_applications', [
            'id' => $application->id,
            'transfer_reference' => 'TRX-REF-777',
        ], 'run');

        $this->assertDatabaseHas('cu_loan_application_actions', [
            'application_id' => $application->id,
            'action' => CreditUnionLoanApplicationAction::ACTION_POSTED,
            'note' => 'Transfer via mobile banking berhasil',
            'actor_user_id' => 20,
        ], 'run');
    }

    public function test_second_start_transfer_is_rejected(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        // Status sudah transfer_processing
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_TRANSFER_PROCESSING);

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/start-transfer', [
            'id' => $application->id,
        ]);

        $response->assertSessionHasErrors('decision');
    }

    public function test_second_post_is_rejected(): void
    {
        $this->seedCuAdmin(10, 'Maker Admin');
        $this->seedCuAdmin(20, 'Checker Admin');
        // Status sudah posted
        $application = $this->makeApplication(makerUserId: 10, status: LoanApplicationService::STATUS_POSTED);

        $this->loginAs(20);

        $response = $this->post('/credit-union/applications/post', [
            'id' => $application->id,
        ]);

        $response->assertSessionHasErrors([
            'decision' => 'Hanya pengajuan yang sedang diproses transfer yang dapat dikonfirmasi pencairannya.',
        ]);
    }
}
