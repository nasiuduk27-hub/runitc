<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('coop_savings_withdrawals')) {
            Schema::connection(self::CONN)->create('coop_savings_withdrawals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('member_rec_id')->index();
                $table->string('member_icuno', 7);
                $table->string('member_name', 40);
                $table->unsignedBigInteger('amount');
                $table->string('bank_account', 120)->nullable();
                $table->string('reason', 200)->nullable();
                $table->string('status', 30)->default('submitted')->index();
                $table->char('withdrawal_trnno', 12)->nullable();
                $table->unsignedInteger('maker_user_id')->index();
                $table->unsignedInteger('checker_user_id')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->string('decision_note', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection(self::CONN)->hasTable('coop_savings_withdrawal_actions')) {
            Schema::connection(self::CONN)->create('coop_savings_withdrawal_actions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('withdrawal_id')->constrained('coop_savings_withdrawals')->cascadeOnDelete();
                $table->string('action', 30);
                $table->string('note', 500)->nullable();
                $table->unsignedInteger('actor_user_id')->index();
                $table->string('actor_name', 80);
                $table->timestamps();

                $table->index(['withdrawal_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_savings_withdrawal_actions');
        Schema::connection(self::CONN)->dropIfExists('coop_savings_withdrawals');
    }
};
