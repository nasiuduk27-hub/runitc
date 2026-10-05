<?php

namespace Tests\Feature\CreditUnion;

use App\Auth\LegacyUser;
use App\Models\CreditUnion\CreditUnionLoanPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi batch 1: guard maker-checker di LoanPaymentController::decide().
 *
 * Test unit canVerify() tidak menangkap bug ini karena masalahnya adalah
 * controller tidak pernah memanggilnya. Test ini menembak endpoint sungguhan
 * sehingga penghapusan guard di controller akan membuatnya merah.
 *
 * Koneksi `run` dialihkan ke sqlite in-memory supaya test mandiri dan tidak
 * menyentuh database dev/produksi. Jalur penolakan maker-checker return
 * SEBELUM post(), jadi tidak ada tulis ke tabel legacy `mysql`.
 */
class LoanPaymentDecideTest extends TestCase
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
        Schema::connection('run')->create('cu_loan_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('loan_rec_id')->default(0);
            $table->unsignedInteger('member_rec_id')->default(0);
            $table->string('member_icuno', 7)->default('');
            $table->string('member_name', 40)->default('');
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('method', 20)->default('tunai');
            $table->string('notes', 200)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->char('icu_trnno', 12)->nullable();
            $table->unsignedBigInteger('principal_portion')->default(0);
            $table->unsignedBigInteger('interest_portion')->default(0);
            $table->json('allocation_json')->nullable();
            $table->unsignedInteger('maker_user_id')->default(0);
            $table->unsignedInteger('checker_user_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::connection('run')->create('cu_loan_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedInteger('dloan_rec_id')->default(0);
            $table->unsignedInteger('seqno')->default(0);
            $table->unsignedBigInteger('amount_applied')->default(0);
            $table->boolean('covers_full')->default(false);
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
    }

    private function seedCuAdmin(int $userId): void
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
    }

    private function makePayment(int $makerUserId): CreditUnionLoanPayment
    {
        return CreditUnionLoanPayment::query()->create([
            'loan_rec_id' => 1,
            'member_rec_id' => 1,
            'member_icuno' => 'M000001',
            'member_name' => 'Anggota Uji',
            'payment_date' => now()->toDateString(),
            'amount' => 100_000,
            'method' => 'tunai',
            'status' => 'submitted',
            'principal_portion' => 90_000,
            'interest_portion' => 10_000,
            'maker_user_id' => $makerUserId,
        ]);
    }

    private function loginAsCuUser(int $userId): void
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

    public function test_maker_cannot_verify_own_payment(): void
    {
        $this->seedCuAdmin(10);
        $payment =         $this->makePayment(makerUserId: 10);
        $this->loginAsCuUser(10);

        $response = $this->post('/credit-union/payments/decide', [
            'id' => $payment->id,
            'decision' => 'verify',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'decision' => 'Pembuat pembayaran tidak dapat memverifikasi pembayarannya sendiri. Verifikasi harus dilakukan oleh admin credit union lain.',
        ]);

        $this->assertDatabaseHas('cu_loan_payments', [
            'id' => $payment->id,
            'status' => 'submitted',
            'checker_user_id' => null,
        ], 'run');
    }

    /**
     * Opsional: guard TIDAK memblokir admin lain (bukan pembuat).
     *
     * Jalur sukses penuh (status -> verified) sengaja tidak diuji di sini:
     * post() menulis tabel legacy `mysql` dan memakai SUBSTRING_INDEX (khusus
     * MySQL) sehingga tidak jalan di sqlite. Cukup dibuktikan bahwa admin lain
     * lewat guard dan gagal karena alokasi kosong, bukan karena maker-checker.
     */
    public function test_non_maker_admin_passes_maker_checker_guard(): void
    {
        $this->seedCuAdmin(20);
        $payment =         $this->makePayment(makerUserId: 10);
        $this->loginAsCuUser(20);

        $response = $this->post('/credit-union/payments/decide', [
            'id' => $payment->id,
            'decision' => 'verify',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['decision' => 'Alokasi pembayaran kosong.']);
    }
}
