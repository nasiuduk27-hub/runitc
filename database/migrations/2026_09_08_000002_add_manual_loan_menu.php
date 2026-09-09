<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('run');
        $parent = $db->table('sys_menus')->where('section_key', 'koperasi')->where('mst_id', 0)->first();
        if (! $parent) return;
        $exists = $db->table('sys_menus')->where('url', '/cooperative/manual-loans/create')->first();
        if ($exists) return;
        $db->table('sys_menus')->insert(['mst_id' => $parent->rec_id, 'title' => 'Input Loan Manual', 'url' => '/cooperative/manual-loans/create', 'icon' => 'fas fa-file-import', 'section_key' => 'koperasi', 'section_label' => 'Koperasi', 'section_sort' => 40, 'is_global' => 0, 'sort_order' => 3, 'is_active' => 1]);
    }

    public function down(): void
    {
        DB::connection('run')->table('sys_menus')->where('url', '/cooperative/manual-loans/create')->delete();
    }
};
