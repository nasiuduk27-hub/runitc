<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('run')->create('cu_bank_transaction_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('bank_trx_id')->unique()->comment('icu_bank_trx.rec_id pada database mysql');
            $table->string('category', 30)->index()->comment('admin_fee atau interest');
            $table->string('bank_account_bank', 60)->nullable();
            $table->string('bank_account_no', 40)->nullable()->index();
            $table->string('bank_account_name', 80)->nullable();
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('run')->dropIfExists('cu_bank_transaction_details');
    }
};
