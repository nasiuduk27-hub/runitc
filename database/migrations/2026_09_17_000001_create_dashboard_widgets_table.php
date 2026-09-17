<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('dashboard_widgets', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('user_id')->index();
            $table->string('widget_key', 120);
            $table->string('widget_type', 30);
            $table->string('title', 150)->nullable();
            $table->longText('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('dashboard_widgets');
    }
};
