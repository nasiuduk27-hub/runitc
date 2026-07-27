<?php
// require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../../config.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);


/**
 * PROCESS PROFILE UPDATE
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$user_recid = $_SESSION['user_id'] ?? 0;
if (!$user_recid) {
    die("Unauthorized access. Please login.");
}
// VALIDASI TAMBAHAN: Cegah Session Overlap
$post_recid = $_POST['rec_id'] ?? '';
if ($post_recid != $user_recid) {
    // Jika ID di form tidak sama dengan ID di session yang aktif
    $_SESSION['error_msg'] = "Sesi telah berubah. Silakan refresh halaman dan coba lagi.";
    header("Location: index.php");
    exit();
}

try {
    $pdo_run->beginTransaction();

    // 1. Ambil Data Form
    $account_nm    = trim($_POST['account_nm'] ?? '');
    $dob           = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $sexmf         = $_POST['sexmf'] ?? '';
    $whatsapp      = trim($_POST['whatsapp'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $kotakabupaten = trim($_POST['kotakabupaten'] ?? '');
    $prov_cd       = $_POST['prov_cd'] ?? '';

    // 2. Update Tabel sysitc_users
    $sql_user = "UPDATE sysitc_users SET 
                    account_nm = ?, 
                    dob = ?, 
                    sexmf = ?, 
                    whatsapp = ?, 
                    address = ?, 
                    kotakabupaten = ?, 
                    prov_cd = ? 
                 WHERE rec_id = ?";
    $stmt_user = $pdo_run->prepare($sql_user);
    $stmt_user->execute([$account_nm, $dob, $sexmf, $whatsapp, $address, $kotakabupaten, $prov_cd, $user_recid]);

    // 3. Handle Bank (sysitc_userbank)
    $bank_rec_id  = !empty($_POST['bank_rec_id']) ? (int) $_POST['bank_rec_id'] : 0;
    $bank_code    = $_POST['bank_code'] ?? '';
    $account_name = trim($_POST['account_name'] ?? '');
    $account_no   = trim($_POST['account_no'] ?? '');

    if (!empty($bank_code) || !empty($account_name) || !empty($account_no)) {
        if (empty($bank_code) || empty($account_name) || empty($account_no)) {
            throw new Exception('Bank, nama rekening, dan nomor rekening wajib diisi lengkap.');
        }

        if ($bank_rec_id > 0) {
            $sql_check_bank = "SELECT rec_id FROM sysitc_userbank WHERE rec_id = ? AND user_recid = ? LIMIT 1";
            $stmt_check_bank = $pdo_run->prepare($sql_check_bank);
            $stmt_check_bank->execute([$bank_rec_id, $user_recid]);
            $bank_row = $stmt_check_bank->fetch(PDO::FETCH_ASSOC);

            if (!$bank_row) {
                throw new Exception('Rekening bank yang dipilih tidak ditemukan.');
            }

            $sql_upd_bank = "UPDATE sysitc_userbank SET bnkcd = ?, accnm = ?, accno = ? WHERE rec_id = ? AND user_recid = ?";
            $pdo_run->prepare($sql_upd_bank)->execute([$bank_code, $account_name, $account_no, $bank_rec_id, $user_recid]);
        } else {
            $sql_ins_bank = "INSERT INTO sysitc_userbank (user_recid, bnkcd, accnm, accno, asdefault) VALUES (?, ?, ?, ?, 1)";
            $pdo_run->prepare($sql_ins_bank)->execute([$user_recid, $bank_code, $account_name, $account_no]);
            $bank_rec_id = (int) $pdo_run->lastInsertId();
        }

        $pdo_run->prepare("UPDATE sysitc_userbank SET asdefault = 0 WHERE user_recid = ?")->execute([$user_recid]);
        $pdo_run->prepare("UPDATE sysitc_userbank SET asdefault = 1 WHERE rec_id = ? AND user_recid = ?")->execute([$bank_rec_id, $user_recid]);
    }

    // / --- LOGIKA DELETE PHOTO ---
$delete_photo = $_POST['delete_photo'] ?? '0';

    if ($delete_photo === '1') {
        $target_dir = BASE_PATH . "/assets/personal/";
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        foreach ($allowed_extensions as $ext) {
            $file_to_delete = $target_dir . "user_" . $user_recid . "." . $ext;
            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }
        }
    }

    // 5. Handle Photo Upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = BASE_PATH . "/assets/personal/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_extensions)) {
            // Hapus foto lama jika ada (agar tidak menumpuk)
            foreach ($allowed_extensions as $ext) {
                $old_file = $target_dir . "user_" . $user_recid . "." . $ext;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            $new_filename = "user_" . $user_recid . "." . $file_ext;
            $target_file = $target_dir . $new_filename;

            move_uploaded_file($_FILES['photo']['tmp_name'], $target_file);
        }
    }

    $pdo_run->commit();

    // Update session name
    $_SESSION['user_name'] = $account_nm;
    $_SESSION['success_msg'] = "Profile updated successfully!";

} catch (Exception $e) {
    if ($pdo_run->inTransaction()) {
        $pdo_run->rollBack();
    }
    error_log("Profile Update Error: " . $e->getMessage());
    $_SESSION['error_msg'] = "Error updating profile: " . $e->getMessage();
}

header("Location: index.php");
exit();

?>
