<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('run')->table('coop_manual_loan_sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('remaining_principal')->default(0)->after('member_name');
        });
    }

    public function down(): void
    {
        Schema::connection('run')->table('coop_manual_loan_sources', function (Blueprint $table): void {
            $table->dropColumn('remaining_principal');
        });
    }
};
