<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        if (! Schema::connection(self::CONN)->hasTable('coop_savings')) {
            Schema::connection(self::CONN)->create('coop_savings', function (Blueprint $table): void {
                $table->id();
                // Referensi aplikasi ke icu_member.rec_id (database utama, tanpa FK lintas DB).
                $table->unsignedInteger('member_rec_id')->index();
                $table->string('member_icuno', 7);
                $table->string('member_name', 40);

                // Periode setoran bulanan (YYYYMM).
                $table->char('pprd', 6);

                $table->unsignedBigInteger('amount');
                // potong_gaji|tunai|transfer (batch memakai potong_gaji / autodebit).
                $table->string('method', 20)->default('potong_gaji');
                $table->string('notes', 200)->nullable();

                // posted|cancelled (batch langsung diposting).
                $table->string('status', 30)->default('posted')->index();

                // Nomor transaksi icu_transaction (SAV-...) setelah diposting.
                $table->char('savings_trnno', 12)->nullable();

                $table->unsignedInteger('maker_user_id')->index();

                $table->timestamps();

                $table->unique(['member_rec_id', 'pprd']);
            });
        }

        if (! Schema::connection(self::CONN)->hasTable('coop_savings_actions')) {
            Schema::connection(self::CONN)->create('coop_savings_actions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('savings_id')->constrained('coop_savings')->cascadeOnDelete();
                $table->string('action', 30);
                $table->string('note', 500)->nullable();
                $table->unsignedInteger('actor_user_id')->index();
                $table->string('actor_name', 80);
                $table->timestamps();

                $table->index(['savings_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('coop_savings_actions');
        Schema::connection(self::CONN)->dropIfExists('coop_savings');
    }
};
