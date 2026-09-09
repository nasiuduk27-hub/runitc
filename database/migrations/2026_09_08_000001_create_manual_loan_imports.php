<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('run')->create('coop_manual_loan_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('filename', 255);
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('actor_user_id');
            $table->timestamps();
        });
        Schema::connection('run')->create('coop_manual_loan_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('loan_rec_id')->index();
            $table->foreignId('import_id')->constrained('coop_manual_loan_imports')->cascadeOnDelete();
            $table->unsignedInteger('member_rec_id')->nullable()->index();
            $table->string('member_name', 100);
            $table->string('source_key', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('run')->dropIfExists('coop_manual_loan_sources');
        Schema::connection('run')->dropIfExists('coop_manual_loan_imports');
    }
};
