<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('run')->hasColumn('cu_reconciliations', 'batch_ref')) {
            return;
        }

        Schema::connection('run')->table('cu_reconciliations', function (Blueprint $table): void {
            $table->char('batch_ref', 20)->nullable()->after('ref_no')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('run')->hasColumn('cu_reconciliations', 'batch_ref')) {
            return;
        }

        Schema::connection('run')->table('cu_reconciliations', function (Blueprint $table): void {
            $table->dropIndex(['batch_ref']);
            $table->dropColumn('batch_ref');
        });
    }
};
