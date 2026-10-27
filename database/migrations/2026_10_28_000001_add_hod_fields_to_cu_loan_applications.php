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
            $table->string('hod_email', 150)->nullable()->after('descr');
            $table->timestamp('hod_approved_at')->nullable()->after('reviewed_at');
            $table->unsignedInteger('hod_approved_by')->nullable()->after('hod_approved_at');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('cu_loan_applications', function (Blueprint $table): void {
            $table->dropColumn([
                'hod_email',
                'hod_approved_at',
                'hod_approved_by',
            ]);
        });
    }
};
