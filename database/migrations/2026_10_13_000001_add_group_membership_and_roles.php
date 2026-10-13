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
        $schema = Schema::connection(self::CONN);

        if ($schema->hasTable('sys_filegrp') && ! $schema->hasColumn('sys_filegrp', 'archived_at')) {
            $schema->table('sys_filegrp', function (Blueprint $table): void {
                $table->dateTime('archived_at')->nullable()->after('updated_at');
            });
        }

        if ($schema->hasTable('sys_filegrp_member')) {
            $schema->table('sys_filegrp_member', function (Blueprint $table) use ($schema): void {
                if (! $schema->hasColumn('sys_filegrp_member', 'member_type')) {
                    $table->enum('member_type', ['user', 'division'])->default('user')->after('user_id');
                }
                if (! $schema->hasColumn('sys_filegrp_member', 'member_value')) {
                    $table->string('member_value', 100)->default('')->after('member_type');
                }
                if (! $schema->hasColumn('sys_filegrp_member', 'role')) {
                    $table->enum('role', ['manager', 'contributor', 'viewer'])->default('contributor')->after('member_value');
                }
                if (! $schema->hasColumn('sys_filegrp_member', 'added_by')) {
                    $table->integer('added_by')->nullable()->after('role');
                }
            });

            if (! $this->hasIndex('sys_filegrp_member', 'uq_filegrp_member')) {
                $schema->table('sys_filegrp_member', function (Blueprint $table): void {
                    $table->unique(['group_id', 'member_type', 'member_value'], 'uq_filegrp_member');
                });
            }

            // Backfill idempoten: baris legacy (hanya user_id) => member_type user + member_value.
            DB::connection(self::CONN)->statement("
                UPDATE sys_filegrp_member
                   SET member_type = 'user',
                       member_value = CAST(user_id AS CHAR)
                 WHERE member_type = 'user'
                   AND (member_value = '' OR member_value IS NULL)
            ");
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONN);

        if ($schema->hasTable('sys_filegrp_member')) {
            if ($this->hasIndex('sys_filegrp_member', 'uq_filegrp_member')) {
                $schema->table('sys_filegrp_member', function (Blueprint $table): void {
                    $table->dropUnique('uq_filegrp_member');
                });
            }
            $schema->table('sys_filegrp_member', function (Blueprint $table) use ($schema): void {
                foreach (['added_by', 'role', 'member_value', 'member_type'] as $column) {
                    if ($schema->hasColumn('sys_filegrp_member', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if ($schema->hasTable('sys_filegrp') && $schema->hasColumn('sys_filegrp', 'archived_at')) {
            $schema->table('sys_filegrp', function (Blueprint $table): void {
                $table->dropColumn('archived_at');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $rows = DB::connection(self::CONN)->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return ! empty($rows);
    }
};
