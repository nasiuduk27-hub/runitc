<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Mode refinancing: "skip" (tunda pokok, tenor +N) atau "accelerate"
     * (percepat / perpendek pembayaran, tenor -N).
     */
    public function up(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_skips', function (Blueprint $table): void {
            $table->string('mode', 20)->default('skip')->index()->after('loan_rec_id');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_skips', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
