<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('run');
        $parent = $db->table('sys_menus')->where('mst_id', 0)
            ->whereIn('section_key', ['credit_union', 'koperasi'])->first();

        if (! $parent) {
            return;
        }

        $url = '/credit-union/reconciliation';
        $menu = $db->table('sys_menus')->where('url', $url)->first();
        $data = [
            'mst_id' => $parent->rec_id,
            'title' => 'Rekonsiliasi',
            'url' => $url,
            'icon' => 'fas fa-scale-balanced',
            'section_key' => $parent->section_key,
            'section_label' => $parent->section_label,
            'section_sort' => $parent->section_sort,
            'is_global' => 0,
            'sort_order' => ((int) $db->table('sys_menus')->where('mst_id', $parent->rec_id)->max('sort_order')) + 1,
            'is_active' => 1,
        ];

        $menuId = $menu?->rec_id;
        if ($menuId) {
            $db->table('sys_menus')->where('rec_id', $menuId)->update($data);
        } else {
            $menuId = $db->table('sys_menus')->insertGetId($data, 'rec_id');
        }

        foreach ($db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            $db->table('sys_menu_access')->insertOrIgnore(['grpacc_id' => $roleId, 'menu_id' => $menuId]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $menu = $db->table('sys_menus')->where('url', '/credit-union/reconciliation')->first();
        if (! $menu) {
            return;
        }

        $db->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        $db->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }
};
