<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('coop_loan_payments', function (Blueprint $table): void {
            $table->id();
            // Referensi aplikasi ke icu_mloan.rec_id (database utama, tanpa FK lintas DB).
            $table->unsignedInteger('loan_rec_id')->index();
            $table->unsignedInteger('member_rec_id')->index();
            $table->string('member_icuno', 7);
            $table->string('member_name', 40);

            $table->date('payment_date');
            $table->unsignedBigInteger('amount');
            // tunai|transfer|potong_gaji
            $table->string('method', 20)->default('tunai');
            $table->string('notes', 200)->nullable();

            // submitted|verified|rejected|cancelled
            $table->string('status', 30)->default('submitted')->index();

            // Nomor transaksi icu_transaction setelah diverifikasi & diposting.
            $table->char('icu_trnno', 12)->nullable();

            // Ringkasan alokasi saat dibuat.
            $table->unsignedBigInteger('principal_portion');
            $table->unsignedBigInteger('interest_portion');
            $table->json('allocation_json');

            // Maker-checker.
            $table->unsignedInteger('maker_user_id')->index();
            $table->unsignedInteger('checker_user_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            $table->timestamps();
        });

        Schema::connection(self::CONN)->create('coop_loan_payment_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('coop_loan_payments')->cascadeOnDelete();
            // submitted|verified|rejected|cancelled
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('actor_user_id');
            $table->string('actor_name', 80);
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_loan_payment_actions');
        Schema::connection(self::CONN)->dropIfExists('coop_loan_payments');
    }
};
