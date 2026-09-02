<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_applications', function (Blueprint $table): void {
            $table->string('admin_fee_type', 20)->default('include')->change();
        });

        // Nilai default lama "exclude" berarti potong pencairan pada implementasi sebelumnya.
        DB::connection(self::CONN)->table('coop_loan_applications')
            ->where('admin_fee_type', 'exclude')
            ->update(['admin_fee_type' => 'include']);

        DB::connection(self::CONN)->table('system_settings')
            ->where('setting_key', 'coop_default_admin_fee_type')
            ->where('setting_value', 'exclude')
            ->update(['setting_value' => 'include']);
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_loan_applications', function (Blueprint $table): void {
            $table->string('admin_fee_type', 20)->default('exclude')->change();
        });
    }
};
