<?php
session_start();
// Paksa tampilkan error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'bootstrap.php';
require_once 'functions.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // TANGKAP DATA
    $account_id = trim($_POST['account_id']);
    $email_id   = trim($_POST['email_id']);
    $password   = $_POST['password_id'];
    $retype     = $_POST['retype_password'];
    
    $instansi   = isset($_POST['instansi']) ? trim($_POST['instansi']) : ''; 
    $is_individu= isset($_POST['is_individu']) ? 1 : 0; 
    $account_nm = trim($_POST['account_nm']);
    $alias      = trim($_POST['alias']);
    $dob_post   = $_POST['dob']; 
    
    $dob = null;
    if (!empty($dob_post)) {
        $dob_obj = DateTime::createFromFormat('d/m/Y', $dob_post);
        if ($dob_obj) {
            $dob = $dob_obj->format('Y-m-d');
        }
    }

    $sexmf      = isset($_POST['sexmf']) ? $_POST['sexmf'] : ''; 

    $wa_code    = isset($_POST['wa_code']) ? trim($_POST['wa_code']) : '';
    $wa_number  = isset($_POST['wa_number']) ? ltrim(trim($_POST['wa_number']), '0') : ''; 
    $whatsapp   = (!empty($wa_number)) ? $wa_code . $wa_number : '';
    $emp_no     = isset($_POST['emp_no']) ? trim($_POST['emp_no']) : '';

    // 1. VALIDASI
    if (strlen($account_id) < 6) { pesan_layar('Account ID minimal harus 6 karakter!', 'error'); exit; }
    if (strlen($password) < 6) { pesan_layar('Password minimal harus 6 karakter!', 'error'); exit; }
    if ($password !== $retype) { pesan_layar('Password dan konfirmasi password tidak cocok.', 'error'); exit; }
    if ($is_individu == 0 && empty($instansi)) { pesan_layar('Instansi / Sekolah wajib diisi (atau centang Individu).', 'error'); exit; }

    if (!empty($dob)) {
        $dob_obj = new DateTime($dob);
        $now_obj = new DateTime();
        $age = $now_obj->diff($dob_obj)->y;
        if ($age < 17 || $age > 100) { pesan_layar('Pendaftaran ditolak. Usia Anda harus antara 17 hingga 100 tahun.', 'error'); exit; }
    }

    $sister_companies = ['pt. international test center', 'international test center', 'pt. itc'];
    $is_sister_company = false;
    
    if ($is_individu == 0 && in_array(strtolower($instansi), $sister_companies)) {
        $is_sister_company = true;
    }

    if ($is_sister_company && empty($emp_no)) {
        pesan_layar('Employee No. wajib diisi untuk pendaftaran internal!', 'error');
        exit;
    }

    $token = rand(10000, 99999);

    try {
        // Cek ID Ganda di RUNITC
        $cek_sql = "SELECT account_nm FROM sysitc_login WHERE account_id = ?";
        $cek_stmt = $pdo_run->prepare($cek_sql);
        $cek_stmt->execute([$account_id]);
        if ($cek_stmt->rowCount() > 0) {
            pesan_layar("Maaf, Account ID <b>$account_id</b> sudah digunakan. Silakan cari ID lain.", 'warning');
            exit;
        }

        $hrd_rec_id = null;
        if ($is_sister_company) {
            $stmt_cek_emp = $pdo->prepare("SELECT rec_id FROM hrd_employee WHERE empno = ?");
            $stmt_cek_emp->execute([$emp_no]);
            
            if ($stmt_cek_emp->rowCount() == 1) {
                $row_emp = $stmt_cek_emp->fetch(PDO::FETCH_ASSOC);
                $hrd_rec_id = $row_emp['rec_id']; 
            } else {
                pesan_layar('Employee No. Tidak Sesuai. Silahkan input kembali atau hubungi Administrator/HR', 'error');
                exit; 
            }
        }

        $pdo_run->beginTransaction();
        if ($is_sister_company) {
            $pdo->beginTransaction();
        }

        // Kembalikan pengecekan Client/Instansi ke $pdo (ITCONENEW)
        $cmpcd = 0; 
        if ($is_individu == 0 && !$is_sister_company) {
            $stmt_cek_client = $pdo->prepare("SELECT rec_id FROM sys_mstclient WHERE clientnm = ?");
            $stmt_cek_client->execute([$instansi]);
            
            if ($stmt_cek_client->rowCount() > 0) {
                $client_data = $stmt_cek_client->fetch(PDO::FETCH_ASSOC);
                $cmpcd = $client_data['rec_id'];
            } else {
                $stmt_ins_client = $pdo->prepare("INSERT INTO sys_mstclient (clientnm) VALUES (?)");
                $stmt_ins_client->execute([$instansi]);
                $cmpcd = $pdo->lastInsertId();
            }
        }

        // INSERT SYSITC_LOGIN (RUNITC)
        $sql_login = "INSERT INTO sysitc_login (account_id, password_id, token, email_id, entdt, whatsapp, account_nm) VALUES (?, MD5(?), ?, ?, CURDATE(), ?, ?)";
        $stmt_login = $pdo_run->prepare($sql_login);
        $stmt_login->execute([$account_id, $password, $token, $email_id, $whatsapp, $account_nm]);
        $login_rec_id = $pdo_run->lastInsertId();

        // INSERT SYSITC_USERS (RUNITC)
        $sql_user = "INSERT INTO sysitc_users (login_rec_id, account_nm, alias_nm, dob, whatsapp, sexmf, status, token_smart, cmpcd) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)";
        $stmt_user = $pdo_run->prepare($sql_user);
        $stmt_user->execute([$login_rec_id, $account_nm, $alias, $dob, $whatsapp, $sexmf, $email_id, $cmpcd]);
        $user_recid = $pdo_run->lastInsertId(); 

        // INSERT SYSITC_USERMAIL (RUNITC)
        $sql_mail = "INSERT INTO sysitc_usermail (user_recid, email, asdefault) VALUES (?, ?, 1)";
        $stmt_mail = $pdo_run->prepare($sql_mail);
        $stmt_mail->execute([$user_recid, $email_id]);


        // INSERT KHUSUS INTERNAL (ITCONENEW)
        if ($is_sister_company) {
            $stmt_login_pdo = $pdo->prepare($sql_login);
            $stmt_login_pdo->execute([$account_id, $password, $token, $email_id, $whatsapp, $account_nm]);
            $login_rec_id_pdo = $pdo->lastInsertId();

            $stmt_user_pdo = $pdo->prepare($sql_user);
            $stmt_user_pdo->execute([$login_rec_id_pdo, $account_nm, $alias, $dob, $whatsapp, $sexmf, $email_id, $cmpcd]);
            $user_recid_pdo = $pdo->lastInsertId();

            $stmt_mail_pdo = $pdo->prepare($sql_mail);
            $stmt_mail_pdo->execute([$user_recid_pdo, $email_id]);

            if ($hrd_rec_id !== null) {
                $stmt_upd_emp = $pdo->prepare("UPDATE hrd_employee SET itc_user_id = ? WHERE rec_id = ?");
                $stmt_upd_emp->execute([$user_recid_pdo, $hrd_rec_id]);
            }
        }

        $pdo_run->commit();
        if ($is_sister_company) {
            $pdo->commit();
        }

        // EMAIL & SMARTCART BOT
        $msg_subject_reg = 'Verifikasi Pendaftaran - ITCONE';
        $nama_institusi_email = ($is_individu == 1) ? 'Individu' : $instansi;
        $msg_body_reg  = "Halo " . ucwords(strtolower($account_nm)) . " dari " . ucwords(strtolower($nama_institusi_email)) . ",\n\n";
        $msg_body_reg .= "Terima kasih telah mendaftar di Portal ITC.\nBerikut adalah 5 digit kode OTP Anda:\n\n";
        $msg_body_reg .= "OTP CODE = " . $token . "\n\nSilakan masukkan kode ini di halaman verifikasi.\n\nSalam,\nTim ITCONE";
        
        // Bungkus try-catch agar jika email gagal, pendaftaran tidak rollback/putih
        try {
            kirim_email($email_id, $account_nm, $msg_subject_reg, $msg_body_reg);
        } catch (Throwable $e) {}
        
        $smart_token = trim($email_id); 
        if (!empty($smart_token)) {
            try {
                $sql_bot = "INSERT INTO bot_nsmartcart (token, notif_subject, notif_msg) VALUES (?, ?, ?)";
                $stmt_bot = $pdo_bot->prepare($sql_bot); 
                $stmt_bot->execute([$smart_token, $msg_subject_reg, $msg_body_reg]);
            } catch (Throwable $e) {}
        }

        // REDIRECT KE VERIFY
        $_SESSION['verify_account_id'] = $account_id;
        $_SESSION['verify_purpose']    = 'register';       
        $_SESSION['verify_timeout']    = time() + 300; 

        $pesan_sukses = "Pendaftaran berhasil!<br><br>Kami telah mengirimkan <b>5 digit kode OTP</b>.<br>Silakan periksa Email atau aplikasi Smartcart Anda.";
        pesan_layar($pesan_sukses, 'success', 'verify.php');
        exit;

    } catch (Throwable $e) { // Menggunakan Throwable untuk menangkap semua level error di PHP
        if (isset($pdo_run) && $pdo_run->inTransaction()) {
            $pdo_run->rollBack();
        }
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Output Error Ekstrem agar tidak layar putih
        echo "<div style='font-family:sans-serif; padding:20px; margin:20px; border:1px solid #ff0000; background-color:#ffebeb; color:#cc0000; border-radius:5px;'>";
        echo "<h3>Oops! Terjadi Kesalahan Sistem</h3>";
        echo "<strong>Pesan Error:</strong> " . $e->getMessage() . "<br><br>";
        echo "<strong>File:</strong> " . $e->getFile() . " (Baris " . $e->getLine() . ")";
        echo "</div>";
        exit;
    }
}
?>
