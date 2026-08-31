<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('coop_loan_applications', function (Blueprint $table): void {
            $table->id();
            // Referensi aplikasi ke icu_member.rec_id (database utama, tanpa FK lintas DB).
            $table->unsignedInteger('member_rec_id')->index();
            // Snapshot data anggota saat pengajuan dibuat agar riwayat tetap terbaca.
            $table->string('member_icuno', 7);
            $table->string('member_name', 40);

            $table->unsignedBigInteger('principal_amount');
            $table->unsignedTinyInteger('tenor_months');
            $table->decimal('annual_rate_percent', 5, 2);
            $table->string('calculation_method', 20)->default('flat');
            $table->string('descr', 100);

            // draft|submitted|approved|rejected|cancelled|posted
            $table->string('status', 30)->default('submitted')->index();

            // Ringkasan simulasi pada saat pengajuan.
            $table->unsignedBigInteger('monthly_installment');
            $table->unsignedBigInteger('total_interest');
            $table->unsignedBigInteger('total_payment');
            $table->longText('schedule_json');

            // Maker-checker: pembuat tidak boleh menyetujui pengajuannya sendiri.
            $table->unsignedInteger('applicant_user_id')->index();
            $table->unsignedInteger('reviewer_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_note', 500)->nullable();

            // Diisi oleh modul posting (icu_mloan.rec_id) setelah pinjaman aktual dibuat.
            $table->unsignedInteger('posted_loan_rec_id')->nullable();

            $table->timestamps();
        });

        Schema::connection(self::CONN)->create('coop_loan_application_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('coop_loan_applications')->cascadeOnDelete();
            // submitted|approved|rejected|cancelled|note
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('actor_user_id');
            $table->string('actor_name', 80);
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_loan_application_actions');
        Schema::connection(self::CONN)->dropIfExists('coop_loan_applications');
    }
};
