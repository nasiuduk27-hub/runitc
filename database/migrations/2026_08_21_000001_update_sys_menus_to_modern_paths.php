<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Peta URL baru (modern Laravel) per rec_id. Nilai ini sudah diverifikasi
     * mengikuti route yang terdaftar di routes/web.php, routes/admin.php,
     * routes/cbt_ops.php, dan routes/filing_system.php.
     *
     * @var array<int, string>
     */
    private array $modernUrls = [
        1 => '/dashboard',
        5 => '/cbt-ops/test-plan',
        9 => '/cbt-ops/test-admin',
        11 => '/cbt-ops/test-watching/monitoring',
        12 => '/filing-system',
        15 => '/admin/system-access/permissions',
        16 => '/admin/system-access/role-menu',
        17 => '/admin/system-access/roles',
        19 => '/admin/system-access/active-users',
        20 => '/admin/system-access/user-role',
        21 => '/cbt-ops/test-watching/monitoring-hybrid',
        22 => '/cbt-ops/test-watching/monitoring-hybrid',
        24 => '/admin/system-access/users',
        25 => '/admin/system-access/menu-management',
        26 => '/admin/system-access/audit-log',
        27 => '/admin/system-health',
        28 => '/admin/system-settings',
        29 => '/admin/reporting',
        30 => '/admin/operational-dashboard',
        31 => '/filing-system/berita-acara',
    ];

    /**
     * Peta URL legacy per rec_id (untuk rollback). Sama dengan isi
     * database/backups/sys_menus_legacy_paths_backup.sql.
     *
     * @var array<int, string>
     */
    private array $legacyUrls = [
        1 => 'dashboard.php',
        5 => '/modules/cbt_ops/test_plan/index.php',
        9 => '/modules/cbt_ops/test_admin/index.php',
        11 => 'modules/cbt_ops/test_watching/monitoring.php',
        12 => '/modules/cbt_ops/filing_system/main.php',
        15 => 'modules\\admin\\system_access\\permissions.php',
        16 => 'modules\\admin\\system_access\\role_menu.php',
        17 => 'modules\\admin\\system_access\\roles.php',
        19 => 'modules\\admin\\system_access\\user_list_active.php',
        20 => 'modules\\admin\\system_access\\user_role.php',
        21 => '/modules/cbt_ops/test_watching/monitoring_hybrid.php',
        22 => '/modules/cbt_ops/test_watching/monitoring_hybrid.php',
        24 => 'modules/admin/system_access/users.php',
        25 => 'modules/admin/system_access/menu_management.php',
        26 => 'modules/admin/system_access/audit_log.php',
        27 => 'modules/admin/system_health.php',
        28 => 'modules/admin/reporting.php',
        29 => 'modules/admin/reporting.php',
        30 => 'modules/admin/operational_dashboard.php',
        31 => 'modules/cbt_ops/filing_system/berita_acara.php',
    ];

    public function up(): void
    {
        foreach ($this->modernUrls as $recId => $url) {
            DB::connection('run')->table('sys_menus')
                ->where('rec_id', $recId)
                ->update(['url' => $url]);
        }
    }

    public function down(): void
    {
        foreach ($this->legacyUrls as $recId => $url) {
            DB::connection('run')->table('sys_menus')
                ->where('rec_id', $recId)
                ->update(['url' => $url]);
        }
    }
};
