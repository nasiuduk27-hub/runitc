<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pencatatan pembukaan link token oleh pengguna login (Link-ke-Shared).
 *
 * Aditif & berpenjaga: tabel baru di koneksi `run` untuk melacak link token
 * aktif yang pernah dibuka oleh pengguna login sehingga file terkait dapat
 * muncul di folder Shared miliknya.
 */
return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE = 'file_link_access';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection(self::CONN)->create(self::TABLE, function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('link_id')->default(0);
            $table->unsignedInteger('file_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->timestamp('first_opened_at')->useCurrent();
            $table->timestamp('last_opened_at')->useCurrent();

            $table->unique(['link_id', 'user_id'], 'uniq_link_user');
            $table->index(['user_id', 'file_id'], 'idx_user_file');
            $table->index('link_id', 'idx_link_access_link');
            $table->index('file_id', 'idx_link_access_file');
            $table->index('user_id', 'idx_link_access_user');
        });
    }

    public function down(): void
    {
        if (! Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection(self::CONN)->dropIfExists(self::TABLE);
    }
};
