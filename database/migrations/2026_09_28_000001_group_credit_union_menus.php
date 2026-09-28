<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Sub-grup menu Credit Union. Anak-anak dikelompokkan agar admin tidak
     * melihat terlalu banyak menu menumpuk dalam satu parent.
     *
     * @var list<array{title: string, icon: string, sort: int, children: list<array{url: string, sort: int}>}>
     */
    private const GROUPS = [
        [
            'title' => 'Keanggotaan',
            'icon' => 'fas fa-people-group',
            'sort' => 2,
            'children' => [
                ['url' => '/credit-union/members', 'sort' => 1],
                ['url' => '/credit-union/savings', 'sort' => 2],
            ],
        ],
        [
            'title' => 'Pinjaman',
            'icon' => 'fas fa-hand-holding-dollar',
            'sort' => 3,
            'children' => [
                ['url' => '/credit-union/loan-simulation', 'sort' => 1],
                ['url' => '/credit-union/applications', 'sort' => 2],
                ['url' => '/credit-union/loans', 'sort' => 3],
                ['url' => '/credit-union/skips', 'sort' => 4],
            ],
        ],
        [
            'title' => 'Transaksi',
            'icon' => 'fas fa-right-left',
            'sort' => 4,
            'children' => [
                ['url' => '/credit-union/payments', 'sort' => 1],
                ['url' => '/credit-union/bank-transactions', 'sort' => 2],
            ],
        ],
        [
            'title' => 'Proses & Rekonsiliasi',
            'icon' => 'fas fa-arrows-rotate',
            'sort' => 5,
            'children' => [
                ['url' => '/credit-union/monthly-processing', 'sort' => 1],
                ['url' => '/credit-union/reconciliation', 'sort' => 2],
            ],
        ],
        [
            'title' => 'Laporan & Audit',
            'icon' => 'fas fa-file-shield',
            'sort' => 6,
            'children' => [
                ['url' => '/credit-union/reports', 'sort' => 1],
                ['url' => '/credit-union/audit-log', 'sort' => 2],
            ],
        ],
    ];

    /**
     * Menu yang tetap menjadi anak langsung parent Credit Union.
     *
     * @var list<array{url: string, sort: int}>
     */
    private const ROOT_CHILDREN = [
        ['url' => '/credit-union/dashboard', 'sort' => 1],
        ['url' => '/credit-union/settings', 'sort' => 7],
    ];

    public function up(): void
    {
        $db = DB::connection(self::CONN);
        $parent = $db->table('sys_menus')
            ->where('mst_id', 0)
            ->where('section_key', 'credit_union')
            ->where('title', 'Credit Union')
            ->first();

        if (! $parent) {
            return;
        }

        $parentId = (int) $parent->rec_id;

        foreach (self::ROOT_CHILDREN as $item) {
            $db->table('sys_menus')->where('url', $item['url'])->update([
                'mst_id' => $parentId,
                'sort_order' => $item['sort'],
            ]);
        }

        foreach (self::GROUPS as $group) {
            $groupId = $this->ensureGroup($parentId, $group);
            $this->grantToCuAdmins($groupId);

            foreach ($group['children'] as $child) {
                $db->table('sys_menus')->where('url', $child['url'])->update([
                    'mst_id' => $groupId,
                    'sort_order' => $child['sort'],
                ]);
            }
        }

        // Perbaiki URL basi yang hanya redirect.
        $db->table('sys_menus')
            ->where('url', '/credit-union/manual-savings/create')
            ->update(['url' => '/credit-union/manual-transactions/create']);

        // Samakan posisi section agar urutan sidebar konsisten.
        $db->table('sys_menus')->where('section_key', 'credit_union')->update(['section_sort' => 40]);
    }

    public function down(): void
    {
        $db = DB::connection(self::CONN);
        $parent = $db->table('sys_menus')
            ->where('mst_id', 0)
            ->where('section_key', 'credit_union')
            ->where('title', 'Credit Union')
            ->first();

        if (! $parent) {
            return;
        }

        $parentId = (int) $parent->rec_id;
        $groupIds = $db->table('sys_menus')
            ->where('mst_id', $parentId)
            ->where('url', '#')
            ->whereIn('title', array_column(self::GROUPS, 'title'))
            ->pluck('rec_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($groupIds)) {
            return;
        }

        $db->table('sys_menus')->whereIn('mst_id', $groupIds)->update(['mst_id' => $parentId]);
        $db->table('sys_menu_access')->whereIn('menu_id', $groupIds)->delete();
        $db->table('sys_menus')->whereIn('rec_id', $groupIds)->delete();
    }

    /**
     * @param  array{title: string, icon: string, sort: int}  $group
     */
    private function ensureGroup(int $parentId, array $group): int
    {
        $db = DB::connection(self::CONN);
        // Wajib url "#" agar tidak menabrak menu daun yang judulnya sama
        // (mis. grup "Pinjaman" vs sub-menu "Pinjaman").
        $existing = $db->table('sys_menus')
            ->where('mst_id', $parentId)
            ->where('title', $group['title'])
            ->where('url', '#')
            ->first();

        $data = [
            'title' => $group['title'],
            'url' => '#',
            'icon' => $group['icon'],
            'section_key' => 'credit_union',
            'section_label' => 'Credit Union',
            'section_sort' => 40,
            'is_global' => 0,
            'sort_order' => $group['sort'],
            'is_active' => 1,
        ];

        if ($existing) {
            $db->table('sys_menus')->where('rec_id', $existing->rec_id)->update($data);

            return (int) $existing->rec_id;
        }

        return (int) $db->table('sys_menus')->insertGetId(['mst_id' => $parentId] + $data, 'rec_id');
    }

    private function grantToCuAdmins(int $menuId): void
    {
        $db = DB::connection(self::CONN);

        foreach ($db->table('sysitc_grpacc')->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])->pluck('rec_id') as $roleId) {
            $db->table('sys_menu_access')->insertOrIgnore([
                'grpacc_id' => $roleId,
                'menu_id' => $menuId,
            ]);
        }
    }
};
