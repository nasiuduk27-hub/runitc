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
        // Kolom legacy (trxdt/expiredt) memakai default '0000-00-00' yang ditolak strict mode.
        // Nonaktifkan sementara agar ALTER TABLE berjalan, lalu kembalikan seperti semula.
        $previousMode = null;
        try {
            $row = DB::connection(self::CONN)->selectOne('SELECT @@SESSION.sql_mode AS mode');
            $previousMode = $row->mode ?? null;
            $sanitized = preg_replace('/NO_ZERO_DATE|NO_ZERO_IN_DATE/', '', (string) $previousMode);
            $sanitized = trim((string) preg_replace('/,\s*,+/', ',', $sanitized), ', ');
            DB::connection(self::CONN)->statement("SET SESSION sql_mode = '{$sanitized}'");
        } catch (Throwable $e) {
            // Abaikan; tetap lanjut.
        }

        try {
            // 1. file_system: lebarkan kolom agar muat nama fisik FSY-25L-00017.pdf
            $this->ensureFileSystemColumns();
        } finally {
            if ($previousMode !== null) {
                try {
                    DB::connection(self::CONN)->statement("SET SESSION sql_mode = '".$previousMode."'");
                } catch (Throwable $e) {
                    // Abaikan.
                }
            }
        }

        // 2. file_shareto: tambah kolom audit + index
        $this->ensureFileSharetoColumns();

        // 3. file_share_link: tabel baru untuk share link publik
        $this->ensureFileShareLinkTable();
    }

    public function down(): void
    {
        // Tidak merubah struktur lama secara destruktif pada down.
        // Kolom/table tambahan dibiarkan; down hanya menghapus tabel baru bila aman.
        if (Schema::connection(self::CONN)->hasTable('file_share_link')) {
            Schema::connection(self::CONN)->dropIfExists('file_share_link');
        }
    }

    private function ensureFileSystemColumns(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('file_system')) {
            return;
        }

        Schema::connection(self::CONN)->table('file_system', function (Blueprint $table): void {
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'trxno')) {
                $table->string('trxno', 50)->nullable();
            } else {
                $table->string('trxno', 50)->nullable()->change();
            }

            if (Schema::connection(self::CONN)->hasColumn('file_system', 'file_name')) {
                $table->string('file_name', 255)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'folder_loc')) {
                $table->string('folder_loc', 10)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'upload_flnm')) {
                $table->string('upload_flnm', 50)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'file_type')) {
                $table->string('file_type', 20)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'client_nm')) {
                $table->string('client_nm', 150)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'file_notes')) {
                $table->string('file_notes', 500)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'depcd')) {
                $table->string('depcd', 10)->nullable()->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'file_size')) {
                $table->unsignedBigInteger('file_size')->default(0)->change();
            }
            if (Schema::connection(self::CONN)->hasColumn('file_system', 'file_zip_size')) {
                $table->unsignedBigInteger('file_zip_size')->default(0)->change();
            }

            // Kolom status/security/expiry untuk kompatibilitas fitur lama
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'status')) {
                $table->string('status', 20)->default('active');
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'security_level')) {
                $table->string('security_level', 20)->default('normal');
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'access_mode')) {
                $table->string('access_mode', 20)->default('private');
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'expired_at')) {
                $table->timestamp('expired_at')->nullable();
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'expired_action')) {
                $table->string('expired_action', 20)->default('trash');
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'expired_processed_at')) {
                $table->timestamp('expired_processed_at')->nullable();
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'trashed_at')) {
                $table->timestamp('trashed_at')->nullable();
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable();
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_system', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            }
        });

        // Index untuk query list/permission (MySQL)
        $indexes = $this->existingIndexes('file_system');
        if (! in_array('idx_fs_folder_loc', $indexes, true)) {
            DB::connection(self::CONN)->statement('ALTER TABLE file_system ADD INDEX idx_fs_folder_loc (folder_loc)');
        }
        if (! in_array('idx_fs_userid', $indexes, true)) {
            DB::connection(self::CONN)->statement('ALTER TABLE file_system ADD INDEX idx_fs_userid (userid)');
        }
        if (! in_array('idx_fs_status', $indexes, true)) {
            DB::connection(self::CONN)->statement('ALTER TABLE file_system ADD INDEX idx_fs_status (status)');
        }
    }

    private function ensureFileSharetoColumns(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('file_shareto')) {
            Schema::connection(self::CONN)->create('file_shareto', function (Blueprint $table): void {
                $table->increments('rec_id');
                $table->unsignedInteger('filesys_id')->default(0);
                $table->tinyInteger('share_cat')->default(1);
                $table->string('othercode', 15)->default('0');
                $table->unsignedInteger('created_by')->default(0);
                $table->timestamp('create_dt')->useCurrent();

                $table->index(['filesys_id', 'share_cat'], 'idx_shareto_filesys_cat');
            });

            return;
        }

        Schema::connection(self::CONN)->table('file_shareto', function (Blueprint $table): void {
            if (! Schema::connection(self::CONN)->hasColumn('file_shareto', 'created_by')) {
                $table->unsignedInteger('created_by')->default(0);
            }
            if (! Schema::connection(self::CONN)->hasColumn('file_shareto', 'create_dt')) {
                $table->timestamp('create_dt')->useCurrent();
            }
        });

        $indexes = $this->existingIndexes('file_shareto');
        if (! in_array('idx_shareto_filesys_cat', $indexes, true)) {
            DB::connection(self::CONN)->statement('ALTER TABLE file_shareto ADD INDEX idx_shareto_filesys_cat (filesys_id, share_cat)');
        }
    }

    private function ensureFileShareLinkTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('file_share_link')) {
            return;
        }

        Schema::connection(self::CONN)->create('file_share_link', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->string('share_code_preview', 20)->nullable();
            $table->string('share_code_hash', 64)->nullable();
            $table->unsignedInteger('created_by')->default(0);
            $table->string('password_hash', 255)->nullable();
            $table->unsignedInteger('max_access')->default(0);
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('filesys_id', 'idx_sharelink_filesys');
            $table->index('share_code_hash', 'idx_sharelink_code_hash');
        });
    }

    private function existingIndexes(string $table): array
    {
        try {
            $rows = DB::connection(self::CONN)->select("SHOW INDEX FROM `{$table}`");

            return array_values(array_unique(array_map(fn ($r) => $r->Key_name, $rows)));
        } catch (Throwable $e) {
            return [];
        }
    }
};
