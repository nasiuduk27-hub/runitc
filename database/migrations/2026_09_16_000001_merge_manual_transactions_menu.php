<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** URL anak menu lama yang digabung. */
    private const OLD_URLS = [
        '/cooperative/manual-savings/create',
        '/cooperative/manual-withdraw/create',
        '/credit-union/manual-savings/create',
        '/credit-union/manual-withdraw/create',
    ];

    private const NEW_URL = '/credit-union/manual-transactions/create';

    public function up(): void
    {
        $db = DB::connection('run');
        $historical = $db->table('sys_menus')->where('section_key', 'koperasi')->where('title', 'Historical Manual')->first();
        if (! $historical) return;

        $oldIds = $db->table('sys_menus')
            ->where('mst_id', $historical->rec_id)
            ->whereIn('url', self::OLD_URLS)
            ->pluck('rec_id');

        if ($oldIds->isNotEmpty()) {
            $db->table('sys_menu_access')->whereIn('menu_id', $oldIds)->delete();
            $db->table('sys_menus')->whereIn('rec_id', $oldIds)->delete();
        }

        $existing = $db->table('sys_menus')->where('section_key', 'koperasi')->where('url', self::NEW_URL)->first();
        $menuId = $existing->rec_id ?? $db->table('sys_menus')->insertGetId([
            'mst_id' => $historical->rec_id,
            'title' => 'Input Transaksi Manual',
            'url' => self::NEW_URL,
            'icon' => 'fas fa-exchange-alt',
            'section_key' => 'koperasi',
            'section_label' => 'Koperasi',
            'section_sort' => 40,
            'is_global' => 0,
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        foreach ($db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            $exists = $db->table('sys_menu_access')
                ->where('grpacc_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (! $exists) {
                $db->table('sys_menu_access')->insert(['grpacc_id' => $roleId, 'menu_id' => $menuId]);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('run');
        $menu = $db->table('sys_menus')->where('url', self::NEW_URL)->first();
        if (! $menu) return;

        $db->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        $db->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }
};
