<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';

    public function up(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('icu_mtrx2hrd')) {
            return;
        }

        DB::connection(self::CONN)->statement(
            'DELETE old FROM icu_mtrx2hrd old INNER JOIN icu_mtrx2hrd keep '
            .'ON old.pprdk = keep.pprdk AND old.cmpcd = keep.cmpcd AND old.rec_id < keep.rec_id'
        );

        $connection = DB::connection(self::CONN);
        $sqlMode = (string) $connection->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;

        try {
            // Legacy zero dates make MySQL strict ALTER TABLE reject an otherwise harmless index.
            $connection->statement("SET SESSION sql_mode = ''");
            Schema::connection(self::CONN)->table('icu_mtrx2hrd', function (Blueprint $table): void {
                $table->unique(['pprdk', 'cmpcd'], 'icu_mtrx2hrd_pprdk_cmpcd_unique');
            });
        } finally {
            $connection->statement("SET SESSION sql_mode = '".addslashes($sqlMode)."'");
        }
    }

    public function down(): void
    {
        if (Schema::connection(self::CONN)->hasTable('icu_mtrx2hrd')) {
            Schema::connection(self::CONN)->table('icu_mtrx2hrd', function (Blueprint $table): void {
                $table->dropUnique('icu_mtrx2hrd_pprdk_cmpcd_unique');
            });
        }
    }
};
