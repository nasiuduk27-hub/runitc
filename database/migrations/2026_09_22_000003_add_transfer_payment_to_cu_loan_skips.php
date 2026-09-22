<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Mode refinancing "transfer": anggota membayar sendiri via transfer ke
     * rekening koperasi. Kolom ini menyimpan nomor referensi yang dicantumkan
     * pada berita transfer serta data verifikasi dana oleh admin.
     */
    public function up(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_skips', function (Blueprint $table): void {
            $table->string('reference_no', 12)->nullable()->index()->after('mode');
            $table->unsignedBigInteger('paid_amount')->nullable()->after('principal_moved');
            $table->date('paid_at')->nullable()->after('paid_amount');
            $table->string('payment_note', 500)->nullable()->after('paid_at');
            $table->string('bank_trnno', 12)->nullable()->after('payment_note');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_skips', function (Blueprint $table): void {
            $table->dropColumn(['reference_no', 'paid_amount', 'paid_at', 'payment_note', 'bank_trnno']);
        });
    }
};
