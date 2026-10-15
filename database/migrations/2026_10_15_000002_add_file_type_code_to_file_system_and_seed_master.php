<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TF-1: Tipe File Filing System
 * - Kolom `file_system.file_type_code` (VARCHAR 10, NULL) + indeks `idx_fs_file_type_code`
 * - Master data awal `sys_msttable` (tbl_code='82'): INV, TAX, OTH (insert-if-missing)
 *
 * Kedua tabel terbukti berada di koneksi 'run'.
 */
return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE_FILE = 'file_system';

    private const TABLE_MST = 'sys_msttable';

    public function up(): void
    {
        // 1. Tambah kolom file_type_code + indeks pada file_system
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_FILE)) {
            if (! Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'file_type_code')) {
                Schema::connection(self::CONN)->table(self::TABLE_FILE, function (Blueprint $table): void {
                    $table->string('file_type_code', 10)->nullable()->after('file_type');
                    $table->index('file_type_code', 'idx_fs_file_type_code');
                });
            }
        }

        // 2. Seeding master tipe file awal (tbl_code '82')
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_MST)) {
            $seeds = [
                ['code' => 'INV', 'descr' => 'INVOICE', 'urutan' => 1],
                ['code' => 'TAX', 'descr' => 'PAJAK',   'urutan' => 2],
                ['code' => 'OTH', 'descr' => 'LAINNYA', 'urutan' => 3],
            ];

            foreach ($seeds as $seed) {
                $exists = DB::connection(self::CONN)->table(self::TABLE_MST)
                    ->where('tbl_code', '82')
                    ->where('code', $seed['code'])
                    ->exists();

                if (! $exists) {
                    DB::connection(self::CONN)->table(self::TABLE_MST)->insert([
                        'tbl_code' => '82',
                        'code' => $seed['code'],
                        'urutan' => $seed['urutan'],
                        'descr' => $seed['descr'],
                        'notes' => '',
                        'AddiNotes' => '',
                        'statrec' => 1,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // 1. Hapus baris seed bila aman
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_MST)) {
            DB::connection(self::CONN)->table(self::TABLE_MST)
                ->where('tbl_code', '82')
                ->whereIn('code', ['INV', 'TAX', 'OTH'])
                ->delete();
        }

        // 2. Drop kolom dan indeks
        if (Schema::connection(self::CONN)->hasTable(self::TABLE_FILE)) {
            if (Schema::connection(self::CONN)->hasColumn(self::TABLE_FILE, 'file_type_code')) {
                Schema::connection(self::CONN)->table(self::TABLE_FILE, function (Blueprint $table): void {
                    $table->dropIndex('idx_fs_file_type_code');
                    $table->dropColumn('file_type_code');
                });
            }
        }
    }
};
