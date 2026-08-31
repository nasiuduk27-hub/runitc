<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Struktur menu Koperasi: parent + halaman-halamannya.
     *
     * @var list<array{title: string, url: string, icon: string}>
     */
    private const ITEMS = [
        ['title' => 'Dashboard', 'url' => '/cooperative/dashboard', 'icon' => 'fas fa-chart-line'],
        ['title' => 'Anggota', 'url' => '/cooperative/members', 'icon' => 'fas fa-users'],
        ['title' => 'Pinjaman', 'url' => '/cooperative/loans', 'icon' => 'fas fa-hand-holding-dollar'],
        ['title' => 'Pengajuan Pinjaman', 'url' => '/cooperative/applications', 'icon' => 'fas fa-file-signature'],
        ['title' => 'Bayar Angsuran', 'url' => '/cooperative/payments', 'icon' => 'fas fa-money-check-dollar'],
        ['title' => 'Refinancing', 'url' => '/cooperative/skips', 'icon' => 'fas fa-forward'],
        ['title' => 'Laporan', 'url' => '/cooperative/reports', 'icon' => 'fas fa-file-lines'],
        ['title' => 'Simulasi Kredit', 'url' => '/cooperative/loan-simulation', 'icon' => 'fas fa-calculator'],
    ];

    public function up(): void
    {
        $parentId = $this->ensureParent();

        foreach (self::ITEMS as $index => $item) {
            $existing = DB::connection(self::CONN)->table('sys_menus')
                ->where('url', $item['url'])
                ->where('mst_id', $parentId)
                ->first();

            if ($existing) {
                DB::connection(self::CONN)->table('sys_menus')
                    ->where('rec_id', $existing->rec_id)
                    ->update([
                        'title' => $item['title'],
                        'icon' => $item['icon'],
                        'is_active' => 1,
                        'sort_order' => $index + 1,
                    ]);

                continue;
            }

            DB::connection(self::CONN)->table('sys_menus')->insert([
                'mst_id' => $parentId,
                'title' => $item['title'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'section_key' => 'koperasi',
                'section_label' => 'Koperasi',
                'section_sort' => 40,
                'is_global' => 0,
                'sort_order' => $index + 1,
                'is_active' => 1,
            ]);
        }
    }

    public function down(): void
    {
        $parent = DB::connection(self::CONN)->table('sys_menus')
            ->where('section_key', 'koperasi')
            ->where('mst_id', 0)
            ->first();

        if (! $parent) {
            return;
        }

        DB::connection(self::CONN)->table('sys_menus')
            ->where('mst_id', $parent->rec_id)
            ->delete();
        DB::connection(self::CONN)->table('sys_menus')
            ->where('rec_id', $parent->rec_id)
            ->delete();
    }

    private function ensureParent(): int
    {
        $parent = DB::connection(self::CONN)->table('sys_menus')
            ->where('section_key', 'koperasi')
            ->where('mst_id', 0)
            ->first();

        if ($parent) {
            DB::connection(self::CONN)->table('sys_menus')
                ->where('rec_id', $parent->rec_id)
                ->update(['is_active' => 1]);

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
};
