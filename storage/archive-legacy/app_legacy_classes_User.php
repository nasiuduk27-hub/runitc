<?php

// File: classes/User.php

class User
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Mengambil data profil lengkap untuk tampilan
     */
    public function getProfile($rec_id)
    {
        try {
            $sql = 'SELECT log.account_id, mst.* 
                    FROM sysitc_users mst 
                    JOIN sysitc_login log ON mst.login_rec_id = log.rec_id 
                    WHERE mst.rec_id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$rec_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Get Profile Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Mengambil daftar hak akses user
     */
    public function getAccessList($rec_id)
    {
        try {
            $sql = "SELECT tbl.descr as ketr, acc.access_account as acc_code, 1 as urutan 
                    FROM sysitc_usracc acc JOIN sys_msttable tbl ON tbl.code = acc.access_code 
                    WHERE tbl.tbl_code='52' AND acc.access_code='02' AND acc.user_rec_id=? 
                    UNION 
                    SELECT tbl.descr as ketr, mbr.icuno as acc_code, 2 as urutan 
                    FROM icu_member mbr JOIN sys_msttable tbl ON tbl.code='03' 
                    WHERE tbl.tbl_code='52' AND mbr.itc_user_id=? 
                    UNION 
                    SELECT grp.grpdesc as ketr, acc.access_account as acc_code, 3 as urutan 
                    FROM sysitc_usracc acc JOIN sysitc_grpacc grp ON acc.access_account = grp.grpacc 
                    WHERE acc.user_rec_id=? AND acc.access_code='04' 
                    UNION 
                    SELECT grp.grpdesc as ketr, acc.access_account as acc_code, 4 as urutan 
                    FROM sysitc_usracc acc JOIN sysitc_grpacc grp ON acc.access_account = grp.grpacc 
                    WHERE acc.user_rec_id=? AND acc.access_code='03' 
                    ORDER BY urutan";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$rec_id, $rec_id, $rec_id, $rec_id]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Access List Error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Menambahkan User baru ke sistem RUN-ITC
     */
    // File: classes/User.php - Bagian Register

    public function register($data)
    {
        try {
            $this->db->beginTransaction();

            // 1. Simpan ke tabel sysitc_login
            $sql_login = 'INSERT INTO sysitc_login (account_id, email_id, password_id, token) VALUES (?, ?, MD5(?), ?)';
            $stmt_login = $this->db->prepare($sql_login);

            // BERSIHKAN BAGIAN INI:
            $stmt_login->execute([
                $data['account_id'],
                $data['email_id'],
                $data['password'],
                $data['otp'],
            ]);

            $login_rec_id = $this->db->lastInsertId();

            // 2. Simpan ke tabel sysitc_users
            $sql_user = 'INSERT INTO sysitc_users (login_rec_id, account_nm, alias_nm, status) VALUES (?, ?, ?, 0)';
            $stmt_user = $this->db->prepare($sql_user);

            // BERSIHKAN JUGA BAGIAN INI:
            $stmt_user->execute([
                $login_rec_id,
                $data['account_nm'],
                $data['alias'],
            ]);

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Registration Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Mengganti password user
     */
    public function changePassword($user_recid, $new_password)
    {
        try {
            // Kita perlu mencari login_rec_id dari tabel sysitc_users dulu
            $sql_find = 'SELECT login_rec_id FROM sysitc_users WHERE rec_id = ?';
            $stmt_find = $this->db->prepare($sql_find);
            $stmt_find->execute([$user_recid]);
            $row = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if (! $row) {
                return false;
            }

            $login_rec_id = $row['login_rec_id'];

            // Update di sysitc_login
            $sql_upd = 'UPDATE sysitc_login SET password_id = MD5(?) WHERE rec_id = ?';
            $stmt_upd = $this->db->prepare($sql_upd);

            return $stmt_upd->execute([$new_password, $login_rec_id]);
        } catch (PDOException $e) {
            error_log('Change Password Error: '.$e->getMessage());

            return false;
        }
    }
}
