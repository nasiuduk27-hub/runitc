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
            // include = dipotong dari pencairan; exclude = ditagih ke anggota.
            $table->string('admin_fee_type', 20)->default('include')->after('admin_fee');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_applications', function (Blueprint $table): void {
            $table->dropColumn('admin_fee_type');
        });
    }
};
