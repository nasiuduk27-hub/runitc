<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';

    /**
     * Tabel milik aplikasi ini (koneksi run / itc_runitc) yang di-rename
     * dari prefix coop_ menjadi cu_.
     *
     * CATATAN: tabel icu_* berada di database lama (itc_itconenew, koneksi mysql)
     * dan TIDAK disentuh oleh migration ini.
     *
     * @var array<string, string> old => new
     */
    private const TABLES = [
        'coop_loan_applications' => 'cu_loan_applications',
        'coop_loan_application_actions' => 'cu_loan_application_actions',
        'coop_loan_payments' => 'cu_loan_payments',
        'coop_loan_payment_actions' => 'cu_loan_payment_actions',
        'coop_loan_payment_allocations' => 'cu_loan_payment_allocations',
        'coop_loan_skips' => 'cu_loan_skips',
        'coop_loan_skip_actions' => 'cu_loan_skip_actions',
        'coop_savings' => 'cu_savings',
        'coop_savings_actions' => 'cu_savings_actions',
        'coop_sync_request' => 'cu_sync_request',
        'coop_savings_withdrawals' => 'cu_savings_withdrawals',
        'coop_savings_withdrawal_actions' => 'cu_savings_withdrawal_actions',
        'coop_manual_loan_imports' => 'cu_manual_loan_imports',
        'coop_manual_loan_sources' => 'cu_manual_loan_sources',
        'coop_manual_savings' => 'cu_manual_savings',
        'coop_manual_withdrawals' => 'cu_manual_withdrawals',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $old => $new) {
            if (Schema::connection(self::CONN)->hasTable($old) && ! Schema::connection(self::CONN)->hasTable($new)) {
                Schema::connection(self::CONN)->rename($old, $new);
            }
        }

        // Sidebar: label section + judul parent menu Koperasi -> Credit Union.
        if (Schema::connection(self::CONN)->hasTable('sys_menus')) {
            DB::connection(self::CONN)->table('sys_menus')
                ->where('url', 'like', '/cooperative/%')
                ->update(['url' => DB::raw("REPLACE(url, '/cooperative/', '/credit-union/')")]);

            DB::connection(self::CONN)->table('sys_menus')
                ->where('section_key', 'koperasi')
                ->update([
                    'title' => DB::raw("REPLACE(title, 'Koperasi', 'Credit Union')"),
                    'section_label' => 'Credit Union',
                    'section_key' => 'credit_union',
                ]);
        }

        // Audit log: hanya action/target milik modul ini (riwayat lain tidak disentuh).
        if (Schema::connection(self::CONN)->hasTable('sys_audit_log')) {
            DB::connection(self::CONN)->table('sys_audit_log')
                ->where('action', 'like', 'cooperative.%')
                ->update(['action' => DB::raw("REPLACE(action, 'cooperative.', 'cu.')")]);

            DB::connection(self::CONN)->table('sys_audit_log')
                ->where('target_type', 'like', 'coop_%')
                ->update(['target_type' => DB::raw("REPLACE(target_type, 'coop_', 'cu_')")]);
        }

        // Kunci pengaturan modul.
        if (Schema::connection(self::CONN)->hasTable('system_settings')) {
            DB::connection(self::CONN)->table('system_settings')
                ->where('setting_key', 'like', 'coop_%')
                ->update(['setting_key' => DB::raw("REPLACE(setting_key, 'coop_', 'cu_')")]);
        }
    }

    public function down(): void
    {
        if (Schema::connection(self::CONN)->hasTable('system_settings')) {
            DB::connection(self::CONN)->table('system_settings')
                ->where('setting_key', 'like', 'cu_%')
                ->update(['setting_key' => DB::raw("REPLACE(setting_key, 'cu_', 'coop_')")]);
        }

        if (Schema::connection(self::CONN)->hasTable('sys_audit_log')) {
            DB::connection(self::CONN)->table('sys_audit_log')
                ->where('target_type', 'like', 'cu_%')
                ->update(['target_type' => DB::raw("REPLACE(target_type, 'cu_', 'coop_')")]);

            DB::connection(self::CONN)->table('sys_audit_log')
                ->where('action', 'like', 'cu.%')
                ->update(['action' => DB::raw("REPLACE(action, 'cu.', 'cooperative.')")]);
        }

        if (Schema::connection(self::CONN)->hasTable('sys_menus')) {
            DB::connection(self::CONN)->table('sys_menus')
                ->where('section_key', 'credit_union')
                ->update([
                    'title' => DB::raw("REPLACE(title, 'Credit Union', 'Koperasi')"),
                    'section_label' => 'Koperasi',
                    'section_key' => 'koperasi',
                ]);

            DB::connection(self::CONN)->table('sys_menus')
                ->where('url', 'like', '/credit-union/%')
                ->update(['url' => DB::raw("REPLACE(url, '/credit-union/', '/cooperative/')")]);
        }

        foreach (self::TABLES as $old => $new) {
            if (Schema::connection(self::CONN)->hasTable($new) && ! Schema::connection(self::CONN)->hasTable($old)) {
                Schema::connection(self::CONN)->rename($new, $old);
            }
        }
    }
};
