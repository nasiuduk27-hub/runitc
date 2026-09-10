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

        $historical = $db->table('sys_menus')->where('section_key', 'koperasi')->where('mst_id', $parent->rec_id)->where('title', 'Historical Manual')->first();
        if ($historical) {
            $historicalId = $historical->rec_id;
        } else {
            $historicalId = $db->table('sys_menus')->insertGetId([
                'mst_id' => $parent->rec_id,
                'title' => 'Historical Manual',
                'url' => '#',
                'icon' => 'fas fa-history',
                'section_key' => 'koperasi',
                'section_label' => 'Koperasi',
                'section_sort' => 40,
                'is_global' => 0,
                'sort_order' => 6,
                'is_active' => 1,
            ]);
        }

        $db->table('sys_menus')->where('url', '/cooperative/manual-loans/create')->update(['mst_id' => $historicalId, 'sort_order' => 1]);

        $children = [
            ['title' => 'Input Simpanan Manual', 'url' => '/cooperative/manual-savings/create', 'icon' => 'fas fa-hand-holding-usd', 'sort_order' => 2],
            ['title' => 'Input Withdraw Manual', 'url' => '/cooperative/manual-withdraw/create', 'icon' => 'fas fa-money-bill-wave', 'sort_order' => 3],
        ];

        foreach ($children as $child) {
            $exists = $db->table('sys_menus')->where('section_key', 'koperasi')->where('url', $child['url'])->first();
            if ($exists) continue;
            $db->table('sys_menus')->insert([
                'mst_id' => $historicalId,
                'title' => $child['title'],
                'url' => $child['url'],
                'icon' => $child['icon'],
                'section_key' => 'koperasi',
                'section_label' => 'Koperasi',
                'section_sort' => 40,
                'is_global' => 0,
                'sort_order' => $child['sort_order'],
                'is_active' => 1,
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $historical = $db->table('sys_menus')->where('section_key', 'koperasi')->where('title', 'Historical Manual')->first();
        if (! $historical) return;

        $db->table('sys_menus')->where('mst_id', $historical->rec_id)->where('url', 'in', ['/cooperative/manual-savings/create', '/cooperative/manual-withdraw/create'])->delete();
        $parent = $db->table('sys_menus')->where('section_key', 'koperasi')->where('mst_id', 0)->first();
        if ($parent) {
            $db->table('sys_menus')->where('url', '/cooperative/manual-loans/create')->update(['mst_id' => $parent->rec_id, 'sort_order' => 3]);
        }
        $db->table('sys_menus')->where('rec_id', $historical->rec_id)->delete();
    }
};