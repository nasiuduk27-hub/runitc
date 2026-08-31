<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_applications', function (Blueprint $table): void {
            // cash|transfer — bagaimana dana dicairkan ke anggota.
            $table->string('fund_release_method', 20)->default('cash');
            // Biaya admin, dipotong dari pencairan (tidak termasuk pokok + bunga).
            $table->unsignedBigInteger('admin_fee')->default(0);
            // Snapshot bank pencairan (metode transfer) saat pengajuan dibuat.
            $table->string('bank_bnkcd', 20)->nullable();
            $table->string('bank_accnm', 150)->nullable();
            $table->string('bank_accno', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_applications', function (Blueprint $table): void {
            $table->dropColumn(['fund_release_method', 'admin_fee', 'bank_bnkcd', 'bank_accnm', 'bank_accno']);
        });
    }
};
