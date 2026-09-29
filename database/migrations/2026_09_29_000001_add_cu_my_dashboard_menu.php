<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const URL = '/credit-union/dashboard/my';

    /**
     * Menu "Dashboard Saya" = dashboard pribadi anggota. Dibuat global agar
     * bisa tampil untuk admin yang sekaligus anggota dan anggota biasa;
     * LayoutService menyembunyikannya bila user tidak tertaut ke data anggota.
     */
    public function up(): void
    {
        $db = DB::connection('run');
        $parent = $db->table('sys_menus')->where('mst_id', 0)
            ->whereIn('section_key', ['credit_union', 'koperasi'])->first();

        if (! $parent) {
            return;
        }

        $menu = $db->table('sys_menus')->where('url', self::URL)->first();
        $data = [
            'mst_id' => $parent->rec_id,
            'title' => 'Dashboard Saya',
            'url' => self::URL,
            'icon' => 'fas fa-user',
            'section_key' => $parent->section_key,
            'section_label' => $parent->section_label,
            'section_sort' => $parent->section_sort,
            'is_global' => 1,
            'sort_order' => 2,
            'is_active' => 1,
        ];

        if ($menu) {
            $db->table('sys_menus')->where('rec_id', $menu->rec_id)->update($data);

            return;
        }

        $db->table('sys_menus')->insertGetId($data, 'rec_id');
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $menu = $db->table('sys_menus')->where('url', self::URL)->first();

        if (! $menu) {
            return;
        }

        $db->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        $db->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }
};
