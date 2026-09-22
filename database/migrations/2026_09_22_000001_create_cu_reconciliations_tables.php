<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('run')->create('cu_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->char('ref_no', 12)->unique();
            $table->string('scope', 30)->index();
            $table->char('period', 6)->nullable()->index();
            $table->unsignedInteger('member_rec_id')->nullable()->index();
            $table->string('member_icuno', 20)->nullable();
            $table->string('member_name', 80)->nullable();
            $table->string('target_type', 40)->nullable();
            $table->string('target_ref', 80)->nullable();
            $table->unsignedBigInteger('expected_amount')->default(0);
            $table->unsignedBigInteger('actual_amount')->default(0);
            $table->bigInteger('difference_amount')->default(0);
            $table->char('direction', 1)->nullable();
            $table->string('reason', 500);
            $table->json('snapshot_json')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->char('correction_trnno', 20)->nullable();
            $table->unsignedInteger('maker_user_id')->index();
            $table->unsignedInteger('checker_user_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::connection('run')->create('cu_reconciliation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('cu_reconciliations')->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('actor_user_id');
            $table->string('actor_name', 80);
            $table->timestamps();
            $table->index(['reconciliation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('run')->dropIfExists('cu_reconciliation_actions');
        Schema::connection('run')->dropIfExists('cu_reconciliations');
    }
};
