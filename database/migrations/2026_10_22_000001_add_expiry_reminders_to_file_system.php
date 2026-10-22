<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE_FILE = 'file_system';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_FILE)) {
            Schema::connection(self::CONN)->table(self::TABLE_FILE, function (Blueprint $table): void {
                if (! Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'remind_days')) {
                    $table->smallInteger('remind_days')->nullable()->default(null)->after('remind_exp');
                }
                if (! Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'reminded_at')) {
                    $table->timestamp('reminded_at')->nullable()->default(null)->after('expired_processed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_FILE)) {
            Schema::connection(self::CONN)->table(self::TABLE_FILE, function (Blueprint $table): void {
                if (Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'reminded_at')) {
                    $table->dropColumn('reminded_at');
                }
                if (Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'remind_days')) {
                    $table->dropColumn('remind_days');
                }
            });
        }
    }
};
