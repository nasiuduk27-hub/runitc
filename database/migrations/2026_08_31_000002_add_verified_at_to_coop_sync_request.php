<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('coop_sync_request', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('coop_sync_request', function (Blueprint $table): void {
            $table->dropColumn('verified_at');
        });
    }
};
