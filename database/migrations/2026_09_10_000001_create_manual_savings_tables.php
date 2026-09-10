<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('run')->hasTable('coop_manual_savings')) {
            Schema::connection('run')->create('coop_manual_savings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('member_rec_id')->index();
                $table->string('member_icuno', 7);
                $table->string('member_name', 40);
                $table->char('pprd', 6)->index();
                $table->date('trndt');
                $table->unsignedBigInteger('amount');
                $table->string('method', 20)->default('tunai');
                $table->string('notes', 200)->nullable();
                $table->char('savings_trnno', 12)->nullable();
                $table->unsignedInteger('maker_user_id');
                $table->timestamps();
            });
        }

        if (! Schema::connection('run')->hasTable('coop_manual_withdrawals')) {
            Schema::connection('run')->create('coop_manual_withdrawals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('member_rec_id')->index();
                $table->string('member_icuno', 7);
                $table->string('member_name', 40);
                $table->char('pprd', 6)->index();
                $table->date('trndt');
                $table->unsignedBigInteger('amount');
                $table->string('bank_account', 120)->nullable();
                $table->string('bank_bnkcd', 20)->nullable();
                $table->string('bank_accnm', 150)->nullable();
                $table->string('bank_accno', 80)->nullable();
                $table->string('reason', 200)->nullable();
                $table->char('withdrawal_trnno', 12)->nullable();
                $table->unsignedInteger('maker_user_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('run')->dropIfExists('coop_manual_withdrawals');
        Schema::connection('run')->dropIfExists('coop_manual_savings');
    }
};