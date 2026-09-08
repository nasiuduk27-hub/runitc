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

        $menu = $db->table('sys_menus')->where('url', '/cooperative/audit-log')->first();
        $menuId = $menu?->rec_id;
        if ($menuId) {
            $db->table('sys_menus')->where('rec_id', $menuId)->update(['title' => 'Audit Log', 'icon' => 'fas fa-clock-rotate-left', 'is_active' => 1]);
        } else {
            $menuId = $db->table('sys_menus')->where('mst_id', $parent->rec_id)->max('sort_order') + 1;
            $menuId = $db->table('sys_menus')->insertGetId([
                'mst_id' => $parent->rec_id, 'title' => 'Audit Log', 'url' => '/cooperative/audit-log', 'icon' => 'fas fa-clock-rotate-left',
                'section_key' => 'koperasi', 'section_label' => 'Koperasi', 'section_sort' => 40, 'is_global' => 0, 'sort_order' => $menuId, 'is_active' => 1,
            ], 'rec_id');
        }

        foreach ($db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            $db->table('sys_menu_access')->insertOrIgnore(['grpacc_id' => $roleId, 'menu_id' => $menuId]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $menu = $db->table('sys_menus')->where('url', '/cooperative/audit-log')->first();
        if (! $menu) return;
        $db->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        $db->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }
};
