<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('run')->hasTable('cu_manual_savings')
            || Schema::connection('run')->hasColumn('cu_manual_savings', 'saving_type')) {
            return;
        }

        Schema::connection('run')->table('cu_manual_savings', function (Blueprint $table): void {
            $table->string('saving_type', 20)->default('monthly')->after('amount');
            $table->index('saving_type');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('run')->hasTable('cu_manual_savings')
            || ! Schema::connection('run')->hasColumn('cu_manual_savings', 'saving_type')) {
            return;
        }

        Schema::connection('run')->table('cu_manual_savings', function (Blueprint $table): void {
            $table->dropIndex(['saving_type']);
            $table->dropColumn('saving_type');
        });
    }
};
