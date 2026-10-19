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
            $table->timestamp('transfer_started_at')->nullable()->after('status');
            $table->unsignedInteger('transfer_started_by')->nullable()->after('transfer_started_at');
            $table->string('transfer_reference', 100)->nullable()->after('transfer_started_by');
            $table->timestamp('disbursed_at')->nullable()->after('transfer_reference');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_applications', function (Blueprint $table): void {
            $table->dropColumn(['transfer_started_at', 'transfer_started_by', 'transfer_reference', 'disbursed_at']);
        });
    }
};
