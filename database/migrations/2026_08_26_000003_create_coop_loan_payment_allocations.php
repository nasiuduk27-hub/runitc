<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('coop_loan_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('coop_loan_payments')->cascadeOnDelete();
            // Referensi aplikasi ke icu_dloan.rec_id (database utama, tanpa FK lintas DB).
            $table->unsignedInteger('dloan_rec_id')->index();
            $table->unsignedSmallInteger('seqno');
            $table->unsignedBigInteger('amount_applied');
            // Baris jadwal tertutp penuh hanya jika amount_applied mencapai tagihan baris.
            $table->boolean('covers_full')->default(false);
            $table->timestamps();

            $table->index(['payment_id', 'seqno']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_loan_payment_allocations');
    }
};
