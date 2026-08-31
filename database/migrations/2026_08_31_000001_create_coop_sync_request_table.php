<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('coop_sync_request', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('member_rec_id')->index();
            $table->string('member_icuno', 7);
            $table->string('member_name', 40);
            $table->unsignedInteger('target_user_id')->index();
            $table->string('target_email', 191);
            $table->string('otp_code', 6);
            $table->string('ref_token', 64)->unique();
            $table->enum('status', ['pending', 'verified', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_sync_request');
    }
};