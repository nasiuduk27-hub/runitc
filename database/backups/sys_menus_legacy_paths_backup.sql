-- =====================================================================
-- BACKUP PATH MENU LEGACY (sys_menus)
-- =====================================================================
-- Tanggal backup : 2026-08-12
-- DB target      : itc_runitc (connection 'run', host 103.167.113.20)
-- Alasan         : Path menu di DB diubah ke path modern Laravel. DB ini
--                  dipakai juga oleh program yang berjalan di
--                  runitc.toeic.or.id, sehingga path legacy harus bisa
--                  dikembalikan.
--
-- Cara restore  : jalankan perintah UPDATE di bawah pada DB yang sama.
--                  Hanya mengubah kolom `url` kembali ke nilai legacy.
--
-- Catatan        : Kolom lain (title, icon, section, is_active, dll)
--                  tidak diubah oleh migrasi ini, jadi tidak perlu di-set.
-- =====================================================================

UPDATE sys_menus SET url = 'dashboard.php' WHERE rec_id = 1;

UPDATE sys_menus SET url = '/modules/cbt_ops/test_plan/index.php' WHERE rec_id = 5;
UPDATE sys_menus SET url = '/modules/cbt_ops/test_admin/index.php' WHERE rec_id = 9;
UPDATE sys_menus SET url = 'modules/cbt_ops/test_watching/monitoring.php' WHERE rec_id = 11;
UPDATE sys_menus SET url = '/modules/cbt_ops/filing_system/main.php' WHERE rec_id = 12;
UPDATE sys_menus SET url = 'modules/cbt_ops/filing_system/berita_acara.php' WHERE rec_id = 31;

UPDATE sys_menus SET url = '/modules/cbt_ops/test_watching/monitoring_hybrid.php' WHERE rec_id = 21;
UPDATE sys_menus SET url = '/modules/cbt_ops/test_watching/monitoring_hybrid.php' WHERE rec_id = 22;

-- Admin system access (legacy)
UPDATE sys_menus SET url = 'modules/admin/system_access/users.php' WHERE rec_id = 24;
UPDATE sys_menus SET url = 'modules/admin/system_access/menu_management.php' WHERE rec_id = 25;
UPDATE sys_menus SET url = 'modules/admin/system_access/audit_log.php' WHERE rec_id = 26;
UPDATE sys_menus SET url = 'modules/admin/system_health.php' WHERE rec_id = 27;
-- Catatan: nilai asli rec 28 = 'modules/admin/reporting.php' (quirk data lama,
-- menu "System Settings" memang menunjuk ke reporting.php di DB sumber).
UPDATE sys_menus SET url = 'modules/admin/reporting.php' WHERE rec_id = 28;
UPDATE sys_menus SET url = 'modules/admin/reporting.php' WHERE rec_id = 29;
UPDATE sys_menus SET url = 'modules/admin/operational_dashboard.php' WHERE rec_id = 30;

-- Security > Permissions / Role Menu / Roles
-- Catatan: nilai asli memakai backslash. Di SQL MySQL backslash = escape,
-- jadi ditulis \\ agar tersimpan sebagai satu backslash.
UPDATE sys_menus SET url = 'modules\\admin\\system_access\\permissions.php' WHERE rec_id = 15;
UPDATE sys_menus SET url = 'modules\\admin\\system_access\\role_menu.php' WHERE rec_id = 16;
UPDATE sys_menus SET url = 'modules\\admin\\system_access\\roles.php' WHERE rec_id = 17;

-- Security > Users > User List / User Roles
UPDATE sys_menus SET url = 'modules\\admin\\system_access\\user_list_active.php' WHERE rec_id = 19;
UPDATE sys_menus SET url = 'modules\\admin\\system_access\\user_role.php' WHERE rec_id = 20;

-- =====================================================================
-- END BACKUP
-- =====================================================================
