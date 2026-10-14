<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode link Filing System (Fase 2):
 * 1 = hanya yang diberi hak (bawaan, aman untuk baris RFS lama)
 * 2 = siapa pun yang memegang token.
 *
 * Aditif & berpenjaga: baris RFS lama tidak pernah membaca kolom ini,
 * jadi maknanya tidak berubah.
 */
return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE = 'file_share_link';

    public function up(): void
    {
        if (! Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        if (Schema::connection(self::CONN)->hasColumn(self::TABLE, 'link_mode')) {
            return;
        }

        Schema::connection(self::CONN)->table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedTinyInteger('link_mode')->default(1)->after('share_code_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::connection(self::CONN)->hasColumn(self::TABLE, 'link_mode')) {
            return;
        }

        Schema::connection(self::CONN)->table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('link_mode');
        });
    }
};
