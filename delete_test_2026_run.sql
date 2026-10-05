-- =====================================================================
-- delete_test_2026_run.sql  (DB itc_runitc, koneksi run)
-- FASE 2 — hapus anak (run) SEBELUM hapus legacy mysql.
-- FK cascade otomatis: cu_*_actions & cu_loan_payment_allocations.
-- =====================================================================

-- 5. Semua 35 pembayaran 2026 (32 sudah di-reversal di mysql FASE 1)
DELETE FROM cu_loan_payments WHERE YEAR(created_at)=2026;  -- 35 baris

-- 6. 8 pengajuan refinancing 2026
DELETE FROM cu_loan_skips WHERE id IN (1,2,3,4,5,6,7,8);

-- 7. 4 pengajuan pinjaman 2026
DELETE FROM cu_loan_applications WHERE id IN (2,3,4,5);

-- 8. 118 setoran simpanan 2026. REVIEW: 114 milik anggota NYATA.
DELETE FROM cu_savings WHERE YEAR(created_at)=2026;

-- 9. 1 simpanan manual 2026
DELETE FROM cu_manual_savings WHERE id IN (2);

-- 16. Audit log 2026 (4761 baris). REVIEW: tabel ini di DB run, bukan mysql.
DELETE FROM sys_audit_log WHERE YEAR(created_at)=2026;

-- ===== VERIFIKASI AKHIR (run) =====
SELECT 'cu_loan_payments_2026' AS cek, COUNT(*) AS n FROM cu_loan_payments WHERE YEAR(created_at)=2026;   -- 0
SELECT 'cu_loan_skips_2026' AS cek, COUNT(*) AS n FROM cu_loan_skips WHERE YEAR(created_at)=2026;         -- 0
SELECT 'cu_loan_applications_2026' AS cek, COUNT(*) AS n FROM cu_loan_applications WHERE YEAR(created_at)=2026; -- 0
SELECT 'cu_savings_2026' AS cek, COUNT(*) AS n FROM cu_savings WHERE YEAR(created_at)=2026;               -- 0
SELECT 'cu_manual_savings_2026' AS cek, COUNT(*) AS n FROM cu_manual_savings WHERE YEAR(trndt)=2026;      -- 0
SELECT 'sys_audit_log_2026' AS cek, COUNT(*) AS n FROM sys_audit_log WHERE YEAR(created_at)=2026;         -- 0
