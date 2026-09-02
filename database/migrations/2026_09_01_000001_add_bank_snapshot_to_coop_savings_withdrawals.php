<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('coop_savings_withdrawals')) {
            return;
        }

        Schema::connection(self::CONN)->table('coop_savings_withdrawals', function (Blueprint $table): void {
            if (! Schema::connection(self::CONN)->hasColumn('coop_savings_withdrawals', 'bank_bnkcd')) {
                $table->string('bank_bnkcd', 20)->nullable()->after('bank_account');
            }

            if (! Schema::connection(self::CONN)->hasColumn('coop_savings_withdrawals', 'bank_accnm')) {
                $table->string('bank_accnm', 150)->nullable()->after('bank_bnkcd');
            }

            if (! Schema::connection(self::CONN)->hasColumn('coop_savings_withdrawals', 'bank_accno')) {
                $table->string('bank_accno', 80)->nullable()->after('bank_accnm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('coop_savings_withdrawals')) {
            return;
        }

        Schema::connection(self::CONN)->table('coop_savings_withdrawals', function (Blueprint $table): void {
            foreach (['bank_accno', 'bank_accnm', 'bank_bnkcd'] as $column) {
                if (Schema::connection(self::CONN)->hasColumn('coop_savings_withdrawals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
