<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('run')->table('sys_menus', function (Blueprint $table): void {
            if (! Schema::connection('run')->hasColumn('sys_menus', 'section_key')) {
                $table->string('section_key', 80)->nullable()->after('icon');
            }
            if (! Schema::connection('run')->hasColumn('sys_menus', 'section_label')) {
                $table->string('section_label', 120)->nullable()->after('section_key');
            }
            if (! Schema::connection('run')->hasColumn('sys_menus', 'section_sort')) {
                $table->integer('section_sort')->default(0)->after('section_label');
            }
        });

        DB::connection('run')->table('sys_menus')->where(function ($query): void {
            $query->where('title', 'like', '%Dashboard%')->orWhere('url', 'like', '%dashboard%');
        })->update(['section_key' => 'main', 'section_label' => 'Main', 'section_sort' => 10]);

        DB::connection('run')->table('sys_menus')->where(function ($query): void {
            $query->where('url', 'like', '%/modules/cbt_ops/%')
                ->orWhere('url', 'like', 'modules/cbt_ops/%')
                ->orWhere('title', 'like', '%Test%')
                ->orWhere('title', 'like', '%CBT%')
                ->orWhere('title', 'like', '%Filing%');
        })->update(['section_key' => 'test_operation', 'section_label' => 'Test Operation', 'section_sort' => 20]);

        DB::connection('run')->table('sys_menus')->where(function ($query): void {
            $query->where('url', 'like', '%/modules/profile/%')
                ->orWhere('url', 'like', 'modules/profile/%')
                ->orWhere('url', 'like', '%/modules/notifications/%')
                ->orWhere('url', 'like', 'modules/notifications/%')
                ->orWhere('title', 'like', '%Profile%')
                ->orWhere('title', 'like', '%Notification%');
        })->update(['section_key' => 'account', 'section_label' => 'Account', 'section_sort' => 80]);

        DB::connection('run')->table('sys_menus')->where(function ($query): void {
            $query->where('url', 'like', '%/modules/admin/%')
                ->orWhere('url', 'like', 'modules/admin/%')
                ->orWhere('title', 'like', '%Admin%')
                ->orWhere('title', 'like', '%Role%')
                ->orWhere('title', 'like', '%Menu%')
                ->orWhere('title', 'like', '%System%');
        })->update(['section_key' => 'administration', 'section_label' => 'Administration', 'section_sort' => 90]);

        DB::connection('run')->table('sys_menus')
            ->whereNull('section_key')
            ->update(['section_key' => 'management', 'section_label' => 'Management', 'section_sort' => 50]);
    }

    public function down(): void
    {
        Schema::connection('run')->table('sys_menus', function (Blueprint $table): void {
            if (Schema::connection('run')->hasColumn('sys_menus', 'section_sort')) {
                $table->dropColumn('section_sort');
            }
            if (Schema::connection('run')->hasColumn('sys_menus', 'section_label')) {
                $table->dropColumn('section_label');
            }
            if (Schema::connection('run')->hasColumn('sys_menus', 'section_key')) {
                $table->dropColumn('section_key');
            }
        });
    }
};
