<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Urutan menu Koperasi mengikuti alur proses admin:
     * master data -> pengajuan/pinjaman -> simpanan -> tagihan HRD -> bank -> posting -> laporan/audit.
     *
     * @var list<array{title: string, url: string, icon: string}>
     */
    private const ITEMS = [
        ['title' => 'Dashboard', 'url' => '/cooperative/dashboard', 'icon' => 'fas fa-chart-line'],
        ['title' => 'Anggota', 'url' => '/cooperative/members', 'icon' => 'fas fa-users'],
        ['title' => 'Simulasi Kredit', 'url' => '/cooperative/loan-simulation', 'icon' => 'fas fa-calculator'],
        ['title' => 'Pengajuan Pinjaman', 'url' => '/cooperative/applications', 'icon' => 'fas fa-file-signature'],
        ['title' => 'Pinjaman', 'url' => '/cooperative/loans', 'icon' => 'fas fa-hand-holding-dollar'],
        ['title' => 'Refinancing', 'url' => '/cooperative/skips', 'icon' => 'fas fa-forward'],
        ['title' => 'Simpanan', 'url' => '/cooperative/savings', 'icon' => 'fas fa-piggy-bank'],
        ['title' => 'Monthly Processing', 'url' => '/cooperative/monthly-processing', 'icon' => 'fas fa-calendar-check'],
        ['title' => 'Transaksi Bank', 'url' => '/cooperative/bank-transactions', 'icon' => 'fas fa-building-columns'],
        ['title' => 'Bayar Angsuran & Simpanan', 'url' => '/cooperative/payments', 'icon' => 'fas fa-money-check-dollar'],
        ['title' => 'Laporan', 'url' => '/cooperative/reports', 'icon' => 'fas fa-file-lines'],
        ['title' => 'Audit Log', 'url' => '/cooperative/audit-log', 'icon' => 'fas fa-clock-rotate-left'],
        ['title' => 'Pengaturan', 'url' => '/cooperative/settings', 'icon' => 'fas fa-gear'],
    ];

    public function up(): void
    {
        $parent = DB::connection(self::CONN)->table('sys_menus')
            ->where('section_key', 'koperasi')
            ->where('mst_id', 0)
            ->first();

        if (! $parent) {
            return;
        }

        foreach (self::ITEMS as $index => $item) {
            $menuId = $this->ensureMenu($parent->rec_id, $item, $index + 1);
            $this->grantAccess($menuId);
        }
    }

    public function down(): void
    {
        // Migration data urutan menu; tidak ada kebalikan yang aman untuk dijalankan.
    }

    private function ensureMenu(int $parentId, array $item, int $sortOrder): int
    {
        $existing = DB::connection(self::CONN)->table('sys_menus')
            ->where('url', $item['url'])
            ->first();

        if ($existing) {
            DB::connection(self::CONN)->table('sys_menus')->where('rec_id', $existing->rec_id)->update([
                'title' => $item['title'],
                'icon' => $item['icon'],
                'sort_order' => $sortOrder,
                'is_active' => 1,
            ]);

            return (int) $existing->rec_id;
        }

        return (int) DB::connection(self::CONN)->table('sys_menus')->insertGetId([
            'mst_id' => $parentId,
            'title' => $item['title'],
            'url' => $item['url'],
            'icon' => $item['icon'],
            'section_key' => 'koperasi',
            'section_label' => 'Koperasi',
            'section_sort' => 40,
            'is_global' => 0,
            'sort_order' => $sortOrder,
            'is_active' => 1,
        ], 'rec_id');
    }

    private function grantAccess(int $menuId): void
    {
        $roles = DB::connection(self::CONN)->table('sysitc_grpacc')
            ->whereRaw('UPPER(grpdesc) LIKE ?', ['%CU%ADMIN%'])
            ->pluck('rec_id');

        foreach ($roles as $roleId) {
            DB::connection(self::CONN)->table('sys_menu_access')->insertOrIgnore([
                'grpacc_id' => $roleId,
                'menu_id' => $menuId,
            ]);
        }
    }
};
