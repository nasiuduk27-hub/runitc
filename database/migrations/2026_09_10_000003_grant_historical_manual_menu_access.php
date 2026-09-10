<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('run');
        $historical = $db->table('sys_menus')->where('section_key', 'koperasi')->where('title', 'Historical Manual')->first();
        if (! $historical) return;

        $menuIds = collect([$historical->rec_id])
            ->merge($db->table('sys_menus')->where('mst_id', $historical->rec_id)->where('is_active', 1)->pluck('rec_id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            foreach ($menuIds as $menuId) {
                $exists = $db->table('sys_menu_access')
                    ->where('grpacc_id', $roleId)
                    ->where('menu_id', $menuId)
                    ->exists();

                if (! $exists) {
                    $db->table('sys_menu_access')->insert(['grpacc_id' => $roleId, 'menu_id' => $menuId]);
                }
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $historical = $db->table('sys_menus')->where('section_key', 'koperasi')->where('title', 'Historical Manual')->first();
        if (! $historical) return;

        $menuIds = collect([$historical->rec_id])
            ->merge($db->table('sys_menus')->where('mst_id', $historical->rec_id)->where('is_active', 1)->pluck('rec_id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $roleIds = $db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id');
        foreach ($roleIds as $roleId) {
            $db->table('sys_menu_access')->where('grpacc_id', $roleId)->whereIn('menu_id', $menuIds)->delete();
        }
    }
};