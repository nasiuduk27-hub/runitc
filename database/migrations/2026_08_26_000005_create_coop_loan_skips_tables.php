<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('coop_loan_skips', function (Blueprint $table): void {
            $table->id();
            // Referensi aplikasi ke icu_mloan.rec_id (database utama, tanpa FK lintas DB).
            $table->unsignedInteger('loan_rec_id')->index();
            $table->unsignedInteger('member_rec_id');
            $table->string('member_icuno', 7);
            $table->string('member_name', 40);

            // Rentang skip: N bulan mulai periode YYYYMM.
            $table->char('start_period', 6);
            $table->unsignedTinyInteger('months_count');

            // Ringkasan hasil perhitungan saat pengajuan dibuat.
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedBigInteger('principal_moved')->default(0);
            $table->unsignedBigInteger('extra_interest')->default(0);
            $table->unsignedSmallInteger('new_term')->default(0);
            $table->json('plan_json')->nullable();

            // submitted|applied|rejected|cancelled
            $table->string('status', 30)->default('submitted')->index();
            $table->string('reason', 200)->nullable();

            // Maker-checker: pengaju tidak dapat menyetujui sendiri (bagian 34 poin 8).
            $table->unsignedInteger('maker_user_id')->index();
            $table->unsignedInteger('checker_user_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            $table->timestamps();
        });

        Schema::connection(self::CONN)->create('coop_loan_skip_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skip_id')->constrained('coop_loan_skips')->cascadeOnDelete();
            // submitted|applied|rejected|cancelled
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('actor_user_id');
            $table->string('actor_name', 80);
            $table->timestamps();

            $table->index(['skip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_loan_skip_actions');
        Schema::connection(self::CONN)->dropIfExists('coop_loan_skips');
    }
};
