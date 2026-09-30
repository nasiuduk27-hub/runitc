<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    /** Section menu anggota (tampilan anggota). */
    private const MEMBER_SECTION = 'credit_union';

    /** Section menu admin (tampilan admin). */
    private const ADMIN_SECTION = 'credit_union_admin';

    private const MEMBER_PARENT_TITLE = 'Credit Union';

    private const ADMIN_PARENT_TITLE = 'Credit Union Admin';

    private const MEMBER_DASHBOARD_URL = '/credit-union/dashboard/my';

    /**
     * Menu anggota. URL boleh sama dengan menu admin karena dipisah per baris
     * (parent berbeda); scoping data ditentukan mode tampilan.
     *
     * @var list<array{title: string, url: string, icon: string, sort: int}>
     */
    private const MEMBER_CHILDREN = [
        ['title' => 'Simpanan', 'url' => '/credit-union/savings', 'icon' => 'fas fa-piggy-bank', 'sort' => 3],
        ['title' => 'Transaksi', 'url' => '/credit-union/transactions/my', 'icon' => 'fas fa-right-left', 'sort' => 4],
        ['title' => 'Pinjaman', 'url' => '/credit-union/loans', 'icon' => 'fas fa-hand-holding-dollar', 'sort' => 5],
        ['title' => 'Pengajuan Pinjaman', 'url' => '/credit-union/applications', 'icon' => 'fas fa-file-signature', 'sort' => 6],
        ['title' => 'Refinancing', 'url' => '/credit-union/skips', 'icon' => 'fas fa-forward', 'sort' => 7],
        ['title' => 'Simulasi Kredit', 'url' => '/credit-union/loan-simulation', 'icon' => 'fas fa-calculator', 'sort' => 8],
    ];

    public function up(): void
    {
        $db = DB::connection(self::CONN);

        $adminParent = $this->resolveAdminParent($db);

        if ($adminParent === null) {
            return;
        }

        $adminParentId = (int) $adminParent->rec_id;

        // 1. Parent lama "Credit Union" menjadi "Credit Union Admin".
        $db->table('sys_menus')->where('rec_id', $adminParentId)->update([
            'title' => self::ADMIN_PARENT_TITLE,
            'icon' => 'fas fa-user-shield',
            'section_key' => self::ADMIN_SECTION,
            'section_label' => self::ADMIN_PARENT_TITLE,
            'section_sort' => 41,
            'is_global' => 0,
            'is_active' => 1,
        ]);

        // 2. Node top-level CU lain (mis. "Historical Manual") adalah menu
        //    admin; pindahkan ke section admin agar tidak ikut tersembunyi.
        $db->table('sys_menus')->where('mst_id', 0)->where('url', '#')
            ->where('section_key', self::MEMBER_SECTION)
            ->where('title', '!=', self::MEMBER_PARENT_TITLE)
            ->update([
                'section_key' => self::ADMIN_SECTION,
                'section_label' => self::ADMIN_PARENT_TITLE,
                'section_sort' => 41,
            ]);

        // 3. Parent baru "Credit Union" untuk tampilan anggota.
        $memberParentId = $this->ensureMemberParent($db);

        // 4. "Dashboard Saya" dipindah ke parent anggota.
        $db->table('sys_menus')->where('url', self::MEMBER_DASHBOARD_URL)->update([
            'mst_id' => $memberParentId,
            'title' => 'Dashboard Saya',
            'icon' => 'fas fa-user',
            'section_key' => self::MEMBER_SECTION,
            'section_label' => self::MEMBER_PARENT_TITLE,
            'sort_order' => 1,
            'is_global' => 1,
            'is_active' => 1,
        ]);

        // 5. Bersihkan menu anggota yang mungkin salah parent (sisa percobaan
        //    sebelumnya), lalu pastikan hanya ada di bawah parent anggota.
        $this->cleanupStrayMemberChildren($db, $adminParentId, $memberParentId);

        // 6. Menu anggota (baris duplikat baru, parent anggota).
        $memberRoles = $this->cuAdminRoleIds()->merge($this->memberRoleIds())->unique()->values()->all();

        foreach (self::MEMBER_CHILDREN as $child) {
            $menuId = $this->ensureChild($db, $memberParentId, $child);
            $this->grantToRoles($menuId, $memberRoles);
        }
    }

    public function down(): void
    {
        $db = DB::connection(self::CONN);

        $memberParent = $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::MEMBER_SECTION)
            ->where('title', self::MEMBER_PARENT_TITLE)
            ->where('url', '#')
            ->first();

        $adminParent = $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::ADMIN_SECTION)
            ->where('title', self::ADMIN_PARENT_TITLE)
            ->first();

        if ($memberParent !== null) {
            $memberParentId = (int) $memberParent->rec_id;

            // Hapus duplikat menu anggota (baris asli admin tetap ada).
            $childIds = $db->table('sys_menus')
                ->where('mst_id', $memberParentId)
                ->whereIn('url', array_column(self::MEMBER_CHILDREN, 'url'))
                ->pluck('rec_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($childIds !== []) {
                $db->table('sys_menu_access')->whereIn('menu_id', $childIds)->delete();
                $db->table('sys_menus')->whereIn('rec_id', $childIds)->delete();
            }

            if ($adminParent !== null) {
                $db->table('sys_menus')->where('url', self::MEMBER_DASHBOARD_URL)
                    ->update(['mst_id' => (int) $adminParent->rec_id]);
            }

            $db->table('sys_menu_access')->where('menu_id', $memberParentId)->delete();
            $db->table('sys_menus')->where('rec_id', $memberParentId)->delete();
        }

        if ($adminParent !== null) {
            $db->table('sys_menus')->where('rec_id', (int) $adminParent->rec_id)->update([
                'title' => self::MEMBER_PARENT_TITLE,
                'icon' => 'fas fa-handshake',
                'section_key' => self::MEMBER_SECTION,
                'section_label' => self::MEMBER_PARENT_TITLE,
                'is_global' => 1,
            ]);
        }
    }

    private function resolveAdminParent($db): ?object
    {
        $admin = $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::ADMIN_SECTION)
            ->first();

        if ($admin !== null) {
            return $admin;
        }

        // Sebelum split: parent lama masih memakai section anggota. Cocokkan
        // judul persis agar tidak mengambil node lain (mis. "Historical Manual").
        return $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::MEMBER_SECTION)
            ->where('title', self::MEMBER_PARENT_TITLE)
            ->where('url', '#')
            ->first();
    }

    private function ensureMemberParent($db): int
    {
        $existing = $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::MEMBER_SECTION)
            ->where('title', self::MEMBER_PARENT_TITLE)
            ->where('url', '#')
            ->first();

        if ($existing !== null) {
            $db->table('sys_menus')->where('rec_id', $existing->rec_id)->update([
                'icon' => 'fas fa-handshake',
                'section_label' => self::MEMBER_PARENT_TITLE,
                'section_sort' => 40,
                'is_global' => 1,
                'is_active' => 1,
            ]);

            return (int) $existing->rec_id;
        }

        return (int) $db->table('sys_menus')->insertGetId([
            'mst_id' => 0,
            'title' => self::MEMBER_PARENT_TITLE,
            'url' => '#',
            'icon' => 'fas fa-handshake',
            'section_key' => self::MEMBER_SECTION,
            'section_label' => self::MEMBER_PARENT_TITLE,
            'section_sort' => 40,
            'is_global' => 1,
            'sort_order' => 1,
            'is_active' => 1,
        ], 'rec_id');
    }

    /**
     * Hapus baris menu anggota yang berada di parent selain parent anggota
     * (mis. sisa salah parent), agar tidak muncul ganda/nyasar.
     */
    private function cleanupStrayMemberChildren($db, int $adminParentId, int $memberParentId): void
    {
        $otherTopIds = $db->table('sys_menus')->where('mst_id', 0)->where('url', '#')
            ->whereNotIn('rec_id', [$adminParentId, $memberParentId])
            ->pluck('rec_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($otherTopIds === []) {
            return;
        }

        $strayIds = $db->table('sys_menus')
            ->whereIn('mst_id', $otherTopIds)
            ->whereIn('url', array_column(self::MEMBER_CHILDREN, 'url'))
            ->pluck('rec_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($strayIds === []) {
            return;
        }

        $db->table('sys_menu_access')->whereIn('menu_id', $strayIds)->delete();
        $db->table('sys_menus')->whereIn('rec_id', $strayIds)->delete();
    }

    /**
     * @param  array{title: string, url: string, icon: string, sort: int}  $child
     */
    private function ensureChild($db, int $parentId, array $child): int
    {
        $existing = $db->table('sys_menus')
            ->where('mst_id', $parentId)
            ->where('url', $child['url'])
            ->first();

        $data = [
            'title' => $child['title'],
            'icon' => $child['icon'],
            'section_key' => self::MEMBER_SECTION,
            'section_label' => self::MEMBER_PARENT_TITLE,
            'section_sort' => 40,
            'is_global' => 0,
            'sort_order' => $child['sort'],
            'is_active' => 1,
        ];

        if ($existing !== null) {
            $db->table('sys_menus')->where('rec_id', $existing->rec_id)->update($data);

            return (int) $existing->rec_id;
        }

        return (int) $db->table('sys_menus')->insertGetId($data + [
            'mst_id' => $parentId,
            'url' => $child['url'],
        ], 'rec_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function cuAdminRoleIds()
    {
        return DB::connection(self::CONN)->table('sysitc_grpacc')
            ->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])
            ->pluck('rec_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function memberRoleIds()
    {
        return DB::connection(self::CONN)->table('sysitc_grpacc')
            ->where('grpaccess', '01')
            ->where('grpacc', '01')
            ->pluck('rec_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * @param  iterable<int>  $roleIds
     */
    private function grantToRoles(int $menuId, iterable $roleIds): void
    {
        $db = DB::connection(self::CONN);

        foreach ($roleIds as $roleId) {
            $db->table('sys_menu_access')->insertOrIgnore([
                'grpacc_id' => $roleId,
                'menu_id' => $menuId,
            ]);
        }
    }
};
