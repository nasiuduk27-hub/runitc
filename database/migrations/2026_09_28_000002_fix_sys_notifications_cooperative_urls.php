<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable('sys_notifications')) {
            DB::connection(self::CONN)->table('sys_notifications')
                ->where('target_url', 'like', '%/cooperative/%')
                ->update([
                    'target_url' => DB::raw("REPLACE(target_url, '/cooperative/', '/credit-union/')"),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::connection(self::CONN)->hasTable('sys_notifications')) {
            DB::connection(self::CONN)->table('sys_notifications')
                ->where('target_url', 'like', '%/credit-union/%')
                ->update([
                    'target_url' => DB::raw("REPLACE(target_url, '/credit-union/', '/cooperative/')"),
                ]);
        }
    }
};
