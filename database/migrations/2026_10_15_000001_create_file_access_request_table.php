<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meja permintaan akses Filing System (Fase 3):
 * peminta (terautentikasi) meminta akses ke file (pemilik file) atau grup
 * (pengelola grup); keputusan Setujui/Tolak dicatat di baris yang sama.
 *
 * Tabel baru murni; tidak ada baris lama yang maknanya berubah.
 * Satu pending per (target, peminta) ditegakkan di service + INSERT anti-dup,
 * bukan unique index parsial (MySQL tidak mendukungnya tanpa kolom generated).
 */
return new class extends Migration
{
    private const CONN = 'run';

    private const TABLE = 'file_access_request';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection(self::CONN)->create(self::TABLE, function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->enum('target_type', ['file', 'group']);
            $table->unsignedInteger('target_id');
            $table->unsignedInteger('requester_id');
            $table->string('message', 500)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['target_type', 'target_id', 'status'], 'idx_target_status');
            $table->index(['requester_id', 'status'], 'idx_requester_status');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists(self::TABLE);
    }
};
