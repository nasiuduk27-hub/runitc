<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_applications', function (Blueprint $table): void {
            $table->string('transfer_proof_path')->nullable()->after('disbursed_at');
            $table->string('transfer_proof_original_name')->nullable()->after('transfer_proof_path');
            $table->string('transfer_proof_mime', 100)->nullable()->after('transfer_proof_original_name');
            $table->unsignedBigInteger('transfer_proof_size')->nullable()->after('transfer_proof_mime');
            $table->timestamp('transfer_proof_uploaded_at')->nullable()->after('transfer_proof_size');
            $table->unsignedInteger('transfer_proof_uploaded_by')->nullable()->after('transfer_proof_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_applications', function (Blueprint $table): void {
            $table->dropColumn([
                'transfer_proof_path',
                'transfer_proof_original_name',
                'transfer_proof_mime',
                'transfer_proof_size',
                'transfer_proof_uploaded_at',
                'transfer_proof_uploaded_by',
            ]);
        });
    }
};
