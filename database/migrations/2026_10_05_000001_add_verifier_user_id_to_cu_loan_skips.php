<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Mode refinancing "transfer" punya dua langkah admin yang harus dilakukan
     * orang berbeda: verifikasi dana masuk, lalu penerapan ke jadwal.
     * Kolom ini mencatat siapa verifikatornya agar penerap bisa ditolak
     * bila ternyata orang yang sama (pemisahan tugas).
     */
    public function up(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_skips', function (Blueprint $table): void {
            $table->unsignedBigInteger('verifier_user_id')->nullable()->after('checker_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_skips', function (Blueprint $table): void {
            $table->dropColumn(['verifier_user_id']);
        });
    }
};
