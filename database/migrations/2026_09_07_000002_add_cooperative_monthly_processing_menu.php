<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        $parent = DB::connection(self::CONN)->table('sys_menus')
            ->where('section_key', 'koperasi')->where('mst_id', 0)->first();

        if (! $parent) {
            return;
        }

        $existing = DB::connection(self::CONN)->table('sys_menus')
            ->where('url', '/cooperative/monthly-processing')->first();

        if ($existing) {
            $menuId = (int) $existing->rec_id;
            DB::connection(self::CONN)->table('sys_menus')->where('rec_id', $menuId)->update([
                'title' => 'Monthly Processing', 'icon' => 'fas fa-calendar-check', 'is_active' => 1,
            ]);
        } else {
            DB::connection(self::CONN)->table('sys_menus')->where('mst_id', $parent->rec_id)->where('sort_order', '>=', 3)->increment('sort_order');
            $menuId = (int) DB::connection(self::CONN)->table('sys_menus')->insertGetId([
                'mst_id' => $parent->rec_id, 'title' => 'Monthly Processing', 'url' => '/cooperative/monthly-processing',
                'icon' => 'fas fa-calendar-check', 'section_key' => 'koperasi', 'section_label' => 'Koperasi',
                'section_sort' => 40, 'is_global' => 0, 'sort_order' => 3, 'is_active' => 1,
            ], 'rec_id');
        }

        foreach (DB::connection(self::CONN)->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            DB::connection(self::CONN)->table('sys_menu_access')->insertOrIgnore([
                'grpacc_id' => $roleId, 'menu_id' => $menuId,
            ]);
        }
    }

    public function down(): void
    {
        $menu = DB::connection(self::CONN)->table('sys_menus')->where('url', '/cooperative/monthly-processing')->first();
        if (! $menu) {
            return;
        }

        DB::connection(self::CONN)->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        DB::connection(self::CONN)->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }
};
