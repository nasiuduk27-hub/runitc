<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        $parentId = $this->ensureParent();

        $menuId = $this->ensureMenu($parentId);

        $this->grantMenuAccess($parentId);
        $this->grantMenuAccess($menuId);
    }

    public function down(): void
    {
        $menu = DB::connection(self::CONN)->table('sys_menus')
            ->where('url', '/cooperative/savings')
            ->first();

        if (! $menu) {
            return;
        }

        DB::connection(self::CONN)->table('sys_menu_access')->where('menu_id', $menu->rec_id)->delete();
        DB::connection(self::CONN)->table('sys_menus')->where('rec_id', $menu->rec_id)->delete();
    }

    private function ensureParent(): int
    {
        $parent = DB::connection(self::CONN)->table('sys_menus')
            ->where('section_key', 'koperasi')
            ->where('mst_id', 0)
            ->first();

        if ($parent) {
            return (int) $parent->rec_id;
        }

        return (int) DB::connection(self::CONN)->table('sys_menus')->insertGetId([
            'mst_id' => 0,
            'title' => 'Koperasi',
            'url' => '#',
            'icon' => 'fas fa-handshake',
            'section_key' => 'koperasi',
            'section_label' => 'Koperasi',
            'section_sort' => 40,
            'is_global' => 1,
            'sort_order' => 1,
            'is_active' => 1,
        ], 'rec_id');
    }

    private function ensureMenu(int $parentId): int
    {
        $existing = DB::connection(self::CONN)->table('sys_menus')
            ->where('url', '/cooperative/savings')
            ->where('mst_id', $parentId)
            ->first();

        if ($existing) {
            DB::connection(self::CONN)->table('sys_menus')
                ->where('rec_id', $existing->rec_id)
                ->update([
                    'title' => 'Simpanan',
                    'icon' => 'fas fa-piggy-bank',
                    'is_active' => 1,
                    'sort_order' => 2,
                ]);

            return (int) $existing->rec_id;
        }

        DB::connection(self::CONN)->table('sys_menus')
            ->where('mst_id', $parentId)
            ->where('sort_order', '>=', 2)
            ->increment('sort_order');

        return (int) DB::connection(self::CONN)->table('sys_menus')->insertGetId([
            'mst_id' => $parentId,
            'title' => 'Simpanan',
            'url' => '/cooperative/savings',
            'icon' => 'fas fa-piggy-bank',
            'section_key' => 'koperasi',
            'section_label' => 'Koperasi',
            'section_sort' => 40,
            'is_global' => 0,
            'sort_order' => 2,
            'is_active' => 1,
        ], 'rec_id');
    }

    private function grantMenuAccess(int $menuId): void
    {
        $roles = DB::connection(self::CONN)->table('sysitc_grpacc')
            ->where(function ($query): void {
                $query->where(fn ($inner) => $inner->where('grpaccess', '01')->where('grpacc', '01'))
                    ->orWhereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%']);
            })
            ->pluck('rec_id');

        foreach ($roles as $roleId) {
            $exists = DB::connection(self::CONN)->table('sys_menu_access')
                ->where('grpacc_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (! $exists) {
                DB::connection(self::CONN)->table('sys_menu_access')->insert([
                    'grpacc_id' => $roleId,
                    'menu_id' => $menuId,
                ]);
            }
        }
    }
};
