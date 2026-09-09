<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Sembunyikan menu "Bayar Angsuran & Simpanan" dari sidebar.
     * Menu tidak dihapus: route /cooperative/payments tetap bisa diakses
     * via URL langsung untuk kasus khusus. Posting rutin bulanan kini
     * dijalankan dari menu Transaksi Bank.
     */
    public function up(): void
    {
        DB::connection(self::CONN)->table('sys_menus')
            ->where('url', '/cooperative/payments')
            ->update(['is_active' => 0]);
    }

    public function down(): void
    {
        DB::connection(self::CONN)->table('sys_menus')
            ->where('url', '/cooperative/payments')
            ->update(['is_active' => 1]);
    }
};
