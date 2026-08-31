<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        // Alokasi kini tersimpan normal di tabel coop_loan_payment_allocations.
        Schema::connection(self::CONN)->table('coop_loan_payments', function (Blueprint $table): void {
            $table->longText('allocation_json')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_payments', function (Blueprint $table): void {
            $table->longText('allocation_json')->nullable(false)->change();
        });
    }
};
