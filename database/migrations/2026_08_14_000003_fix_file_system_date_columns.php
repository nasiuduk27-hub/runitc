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
        if (! Schema::connection(self::CONN)->hasTable('file_system')) {
            return;
        }

        // Relaksasi sql_mode sesi agar ALTER/UPDATE pada kolom date zero ('0000-00-00') bisa berjalan.
        $previousMode = null;
        try {
            $row = DB::connection(self::CONN)->selectOne('SELECT @@SESSION.sql_mode AS mode');
            $previousMode = $row->mode ?? null;
            $sanitized = preg_replace('/NO_ZERO_DATE|NO_ZERO_IN_DATE/', '', (string) $previousMode);
            $sanitized = trim((string) preg_replace('/,\s*,+/', ',', $sanitized), ', ');
            DB::connection(self::CONN)->statement("SET SESSION sql_mode = '{$sanitized}'");
        } catch (Throwable $e) {
            // lanjut.
        }

        try {
            // Kolom warningdt (rencana md: tanggal warning expired) bisa ada/tidak ada di DB produksi.
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'warningdt')) {
                Schema::connection(self::CONN)->table('file_system', function (Blueprint $table): void {
                    $table->date('warningdt')->nullable()->after('expiredt');
                });
            }

            // Nilai zero-date lama diganti NULL (setelah kolom dijadikan nullable).
            foreach (['trxdt', 'expiredt', 'warningdt'] as $column) {
                if (Schema::connection(self::CONN)->hasColumn('file_system', $column)) {
                    DB::connection(self::CONN)->statement("ALTER TABLE file_system MODIFY `{$column}` date NULL DEFAULT NULL");
                    DB::connection(self::CONN)->statement("UPDATE file_system SET `{$column}` = NULL WHERE `{$column}` <= '0000-01-01'");
                }
            }
        } finally {
            if ($previousMode !== null) {
                try {
                    DB::connection(self::CONN)->statement("SET SESSION sql_mode = '".$previousMode."'");
                } catch (Throwable $e) {
                    // abaikan.
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('file_system')) {
            return;
        }

        // Kembalikan hanya jika kolom masih ada (non-destruktif).
        if (Schema::connection(self::CONN)->hasColumn('file_system', 'trxdt')) {
            DB::connection(self::CONN)->statement("ALTER TABLE file_system MODIFY `trxdt` date NOT NULL DEFAULT '0000-00-00'");
        }
        if (Schema::connection(self::CONN)->hasColumn('file_system', 'expiredt')) {
            DB::connection(self::CONN)->statement("ALTER TABLE file_system MODIFY `expiredt` date NOT NULL DEFAULT '0000-00-00'");
        }
    }
};
