<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom `div_cd` VARCHAR(10) NULL pada `sysitc_grpacc` (koneksi run).
 * Kolom ini khusus untuk penentuan folder filing per divisi.
 */
return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE_GRPACC = 'sysitc_grpacc';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_GRPACC)) {
            if (! Schema::connection(self::CONN)->hasColumn(self::TABLE_GRPACC, 'div_cd')) {
                Schema::connection(self::CONN)->table(self::TABLE_GRPACC, function (Blueprint $table): void {
                    $table->string('div_cd', 10)->nullable()->after('grpdesc');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_GRPACC)) {
            if (Schema::connection(self::CONN)->hasColumn(self::TABLE_GRPACC, 'div_cd')) {
                Schema::connection(self::CONN)->table(self::TABLE_GRPACC, function (Blueprint $table): void {
                    $table->dropColumn('div_cd');
                });
            }
        }
    }
};
