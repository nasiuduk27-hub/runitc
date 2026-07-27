<?php
/**
 * File: modules/cbt_ops/test_plan/edit.php
 * Deskripsi: Form Edit Data Supervisor/TAD
 */

// 1. Inisialisasi Environment & Header
require_once dirname(__DIR__, 3) . '/includes/layout_header.php';
require_once BASE_PATH . '/includes/tad_access.php';
require_once BASE_PATH . '/models/AuditLog.php';
require_once BASE_PATH . '/includes/audit_helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$success = null;
$canManageSupervisor = canManageTadSupervisor($pdo_run, (int) ($_SESSION['user_id'] ?? 0));

function syncItcUserDefaultBank(PDO $pdoRun, int $userId, string $bankCode, string $accountNo, string $accountName): void
{
    if ($userId <= 0 || $bankCode === '' || $accountNo === '') {
        return;
    }

    $stmtCheck = $pdoRun->prepare("SELECT rec_id FROM sysitc_userbank WHERE user_recid = ? AND asdefault = 1 LIMIT 1");
    $stmtCheck->execute([$userId]);
    $bankRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($bankRow) {
        $stmtUpdate = $pdoRun->prepare("UPDATE sysitc_userbank SET bnkcd = ?, accnm = ?, accno = ? WHERE rec_id = ?");
        $stmtUpdate->execute([$bankCode, $accountName, $accountNo, $bankRow['rec_id']]);
        return;
    }

    $stmtInsert = $pdoRun->prepare("INSERT INTO sysitc_userbank (user_recid, bnkcd, accnm, accno, asdefault) VALUES (?, ?, ?, ?, 1)");
    $stmtInsert->execute([$userId, $bankCode, $accountName, $accountNo]);
}

if (!$canManageSupervisor) {
    http_response_code(403);
    die('Anda tidak memiliki izin untuk mengubah data TAD/SPV.');
}

if (!$id) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// 2. Ambil Data Existing & Master Data
try {
    $stmtColumn = $pdo_run->query("SHOW COLUMNS FROM tad_supervisor LIKE 'skills_notes'");
    if (!$stmtColumn->fetch()) {
        $pdo_run->exec("ALTER TABLE tad_supervisor ADD COLUMN skills_notes TEXT NULL AFTER lvl_spv");
    }

    // Data Supervisor
    $stmtTad = $pdo_run->prepare("SELECT * FROM tad_supervisor WHERE rec_id = ?");
    $stmtTad->execute([$id]);
    $tad = $stmtTad->fetch(PDO::FETCH_ASSOC);

    if (!$tad) {
        $_SESSION['error'] = "Data tidak ditemukan.";
        echo "<script>window.location.href='index.php';</script>";
        exit;
    }

    // Data Rekening
    $stmtBanks = $pdo_run->prepare("SELECT * FROM tad_rekening WHERE tad_id = ?");
    $stmtBanks->execute([$id]);
    $banks = $stmtBanks->fetchAll(PDO::FETCH_ASSOC);

    if (empty($banks) && !empty($tad['itc_usr_id'])) {
        $stmtProfileBank = $pdo_run->prepare("
            SELECT bnkcd AS bank_code, accno AS bank_acc_no, accnm AS bank_acc_name, 1 AS is_default
            FROM sysitc_userbank
            WHERE user_recid = ? AND asdefault = 1
            LIMIT 1
        ");
        $stmtProfileBank->execute([(int) $tad['itc_usr_id']]);
        $profileBank = $stmtProfileBank->fetch(PDO::FETCH_ASSOC);
        if ($profileBank) {
            $banks = [$profileBank];
        }
    }

    // Master Data Dropdown
    $cities = $pdo_run->query("SELECT rec_id, nama FROM sys_kota ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
    $provinces = $pdo_run->query("SELECT rec_id, nama FROM sys_provinsi ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
    $bankList = $pdo_run->query("SELECT code, descr FROM sys_msttable WHERE tbl_code = '51' AND statrec = 1 ORDER BY descr ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo_run->prepare("
        SELECT
            u.rec_id,
            u.account_nm,
            u.alias_nm,
            u.sexmf,
            u.whatsapp,
            u.address,
            u.kotakabupaten,
            u.prov_cd,
            l.account_id,
            COALESCE(m.email, l.email_id) AS email_id,
            b.bnkcd AS bank_code,
            b.accno AS bank_acc_no,
            b.accnm AS bank_acc_name
        FROM sysitc_users u
        JOIN sysitc_login l ON l.rec_id = u.login_rec_id
        LEFT JOIN sysitc_usermail m ON m.user_recid = u.rec_id AND m.asdefault = 1
        LEFT JOIN sysitc_userbank b ON b.user_recid = u.rec_id AND b.asdefault = 1
        LEFT JOIN sysitc_usracc ua ON ua.user_rec_id = u.rec_id
        LEFT JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
        WHERE u.status = 1
        GROUP BY u.rec_id, u.account_nm, u.alias_nm, u.sexmf, u.whatsapp, u.address, u.kotakabupaten, u.prov_cd, l.account_id, m.email, l.email_id, b.bnkcd, b.accno, b.accnm
        HAVING u.rec_id = ?
            OR SUM(CASE WHEN ua.user_rec_id IS NOT NULL THEN 1 ELSE 0 END) = 0
            OR SUM(CASE WHEN UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                OR UPPER(g.grpdesc) LIKE '%TAD%SPV%'
                OR UPPER(g.grpdesc) = 'SUPER ADMIN' THEN 1 ELSE 0 END) > 0
        ORDER BY u.account_nm ASC
    ");
    $stmtUsers->execute([(int) ($tad['itc_usr_id'] ?? 0)]);
    $itcUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error Database: " . $e->getMessage();
    $itcUsers = [];
}

// 3. PROSES UPDATE DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo_run->beginTransaction();

        $type         = $_POST['type'] ?? 'SPV';
        $captain      = ($type === 'CAP') ? 1 : 0;
        $status       = $_POST['status'] ?? 1;
        $name         = strtoupper(trim($_POST['name'] ?? ''));
        $alias        = strtoupper(trim($_POST['alias'] ?? ''));
        $gender       = $_POST['gender'] ?? 'L';
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $phone        = trim($_POST['phone'] ?? '');
        $city_id      = !empty($_POST['city_id']) ? $_POST['city_id'] : null;
        $address      = trim($_POST['address'] ?? '');
        $spv_category = $_POST['spv_category'] ?? null;
        $prov_cd      = $_POST['prov_cd'] ?? null;
        $lvl_spv      = $_POST['lvl_spv'] ?? 0;
        $skills_notes = trim($_POST['skills_notes'] ?? '');
        $itc_user_id  = !empty($_POST['itc_user_id']) ? (int) $_POST['itc_user_id'] : null;

        if (empty($itc_user_id)) {
            throw new Exception('Akun ITC / Rec ID User wajib dipilih.');
        }

        ensureUserHasTadSpvRole($pdo_run, $itc_user_id);

        $stmtSelectedUser = $pdo_run->prepare("
            SELECT
                u.account_nm,
                u.alias_nm,
                u.sexmf,
                u.whatsapp,
                u.address,
                u.kotakabupaten,
                u.prov_cd,
                COALESCE(m.email, l.email_id) AS email_id,
                b.bnkcd AS bank_code,
                b.accno AS bank_acc_no,
                b.accnm AS bank_acc_name
            FROM sysitc_users u
            JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            LEFT JOIN sysitc_usermail m ON m.user_recid = u.rec_id AND m.asdefault = 1
            LEFT JOIN sysitc_userbank b ON b.user_recid = u.rec_id AND b.asdefault = 1
            WHERE u.rec_id = ? AND u.status = 1
            LIMIT 1
        ");
        $stmtSelectedUser->execute([$itc_user_id]);
        $selectedUser = $stmtSelectedUser->fetch(PDO::FETCH_ASSOC);

        if (!$selectedUser) {
            throw new Exception('Akun ITC yang dipilih tidak ditemukan atau tidak aktif.');
        }

        $name = strtoupper(trim((string) ($selectedUser['account_nm'] ?? $name)));
        $alias = strtoupper(trim((string) (($selectedUser['alias_nm'] ?? '') ?: $alias)));
        $accountGender = strtoupper((string) ($selectedUser['sexmf'] ?? ''));
        $gender = match ($accountGender) {
            'F', 'P' => 'P',
            'M', 'L' => 'L',
            default => $gender,
        };
        $email = strtolower(trim((string) (($selectedUser['email_id'] ?? '') ?: $email)));
        $phone = trim((string) (($selectedUser['whatsapp'] ?? '') ?: $phone));
        $address = trim((string) (($selectedUser['address'] ?? '') ?: $address));
        $city_id = !empty($selectedUser['kotakabupaten']) ? $selectedUser['kotakabupaten'] : $city_id;
        $prov_cd = !empty($selectedUser['prov_cd']) ? $selectedUser['prov_cd'] : $prov_cd;

        if (empty(array_filter($_POST['bank_code'] ?? [])) && !empty($selectedUser['bank_code'])) {
            $_POST['bank_code'] = [$selectedUser['bank_code']];
            $_POST['bank_acc_no'] = [$selectedUser['bank_acc_no'] ?? ''];
            $_POST['bank_acc_name'] = [$selectedUser['bank_acc_name'] ?? $name];
            $_POST['is_default_bank'] = 0;
        }

        if ($name === '' || $phone === '') {
            throw new Exception('Nama dan Nomor Telepon wajib tersedia di data akun ITC.');
        }

        if ($itc_user_id) {
            ensureUserHasTadSpvRole($pdo_run, $itc_user_id);
        }

        // A. Proses Update Foto
        $photoPath = $tad['photo_path'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 3) . '/storage/photos/tad/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Hapus foto lama dari storage jika ada
            if ($tad['photo_path'] && file_exists(dirname(__DIR__, 3) . '/' . $tad['photo_path'])) {
                unlink(dirname(__DIR__, 3) . '/' . $tad['photo_path']);
            }

            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $newFileName = bin2hex(random_bytes(10)) . '.' . $ext;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newFileName)) {
                $photoPath = 'storage/photos/tad/' . $newFileName;
            }
        }

        // B. Update Tabel `tad_supervisor`
        $sqlUpd = "UPDATE tad_supervisor SET 
                   itc_usr_id = ?, spv_name = ?, spv_alias = ?, gender = ?, email = ?, phone = ?, 
                   address = ?, city_id = ?, spv_category = ?, prov_cd = ?, 
                   lvl_spv = ?, skills_notes = ?, captain = ?, status = ?, photo_path = ?, lupd = NOW() 
                   WHERE rec_id = ?";
        
        $pdo_run->prepare($sqlUpd)->execute([
            $itc_user_id, $name, $alias, $gender, $email, $phone, 
            $address, $city_id, $spv_category, $prov_cd, 
            $lvl_spv, $skills_notes, $captain, $status, $photoPath, $id
        ]);

        // C. Update Tabel `tad_rekening` (Sync Logic: Delete then Re-insert)
        $pdo_run->prepare("DELETE FROM tad_rekening WHERE tad_id = ?")->execute([$id]);
        $defaultBankToSync = null;
        
        if (isset($_POST['bank_code']) && is_array($_POST['bank_code'])) {
            $defaultBankIndex = $_POST['is_default_bank'] ?? 0;
            $stmtBankIns = $pdo_run->prepare("INSERT INTO tad_rekening (tad_id, bank_code, bank_acc_no, bank_acc_name, is_default) VALUES (?, ?, ?, ?, ?)");

            foreach ($_POST['bank_code'] as $index => $code) {
                $accNo   = trim($_POST['bank_acc_no'][$index] ?? '');
                $accName = strtoupper(trim($_POST['bank_acc_name'][$index] ?? ''));
                
                if (!empty($code) && !empty($accNo)) {
                    $isDefault = ($index == $defaultBankIndex) ? 1 : 0;
                    $stmtBankIns->execute([$id, $code, $accNo, $accName, $isDefault]);
                    if ($isDefault) {
                        $defaultBankToSync = [$code, $accNo, $accName];
                    }
                }
            }
        }

        if ($defaultBankToSync) {
            syncItcUserDefaultBank($pdo_run, $itc_user_id, $defaultBankToSync[0], $defaultBankToSync[1], $defaultBankToSync[2]);
        }

        $pdo_run->commit();

        logAudit($pdo_run, 'TEST_PLAN_UPDATE', 'tad_supervisor', (int) $id, [
            'updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'updated_by_name' => $_SESSION['account_nm'] ?? $_SESSION['username'] ?? null,
            'tad_id' => (int) $id,
            'itc_user_id' => $itc_user_id,
            'old_itc_user_id' => isset($tad['itc_usr_id']) ? (int) $tad['itc_usr_id'] : null,
            'tad_name' => $name,
            'old_tad_name' => $tad['spv_name'] ?? null,
            'type' => $captain ? 'CAP' : 'SPV',
            'status' => (int) $status,
        ]);

        // Kirim notifikasi ke TAD SPV bahwa datanya diperbarui
        try {
            (new Notification($pdo_run))->create(
                $itc_user_id,
                (int) ($_SESSION['user_id'] ?? 0) ?: null,
                'tad_spv_updated',
                'Data TAD Diperbarui',
                "Data Supervisor TAD Anda telah diperbarui.",
                rtrim(BASE_URL, '/') . '/modules/cbt_ops/test_plan/index.php',
                'tad_supervisor',
                $id
            );
        } catch (Throwable $e) {
            error_log('Notification create failed: ' . $e->getMessage());
        }

        $_SESSION['success'] = "Data [$name] berhasil diperbarui.";
        echo "<script>window.location.href='index.php';</script>";
        exit;

    } catch (Throwable $e) {
        if ($pdo_run->inTransaction()) {
            $pdo_run->rollBack();
        }
        $error = "Gagal memperbarui data: " . $e->getMessage();
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 42px !important; border-color: #d1d5db !important; border-radius: 0.5rem !important; padding-top: 6px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { top: 8px !important; }
</style>

<div class="container mx-auto px-4 py-8 max-w-5xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Test Plan / TAD</h1>
        <a href="index.php" class="text-sm font-medium text-blue-600 hover:underline"> Kembali ke Daftar</a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm"><?= $error ?></div>
    <?php endif; ?>

    <form action="edit.php?id=<?= (int) $tad['rec_id'] ?>" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2 italic">Profil & Status</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tipe Jabatan</label>
                        <div class="flex gap-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="type" value="CAP" class="peer hidden" <?= $tad['captain'] == 1 ? 'checked' : '' ?>>
                                <div class="p-2 text-center border rounded-lg peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-700 transition">Captain</div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="type" value="SPV" class="peer hidden" <?= $tad['captain'] == 0 ? 'checked' : '' ?>>
                                <div class="p-2 text-center border rounded-lg peer-checked:bg-blue-50 peer-checked:border-blue-500 peer-checked:text-blue-700 transition">Supervisor</div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Status Akun</label>
                        <select name="status" class="w-full border-gray-300 rounded-lg p-2 border">
                            <option value="1" <?= $tad['status'] == 1 ? 'selected' : '' ?>>Aktif / Active</option>
                            <option value="0" <?= $tad['status'] == 0 ? 'selected' : '' ?>>Suspend / Non-Aktif</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Akun ITC / Rec ID User</label>
                        <select name="itc_user_id" id="itc_user_id" required class="mt-1 block w-full rounded-md border-gray-300 p-2 border shadow-sm">
                            <option value="">-- Pilih akun TAD --</option>
                            <?php foreach ($itcUsers as $user): ?>
                                <option value="<?= $user['rec_id'] ?>"
                                    data-name="<?= htmlspecialchars($user['account_nm'] ?? '', ENT_QUOTES) ?>"
                                    data-alias="<?= htmlspecialchars($user['alias_nm'] ?? '', ENT_QUOTES) ?>"
                                    data-gender="<?= htmlspecialchars($user['sexmf'] ?? '', ENT_QUOTES) ?>"
                                    data-email="<?= htmlspecialchars($user['email_id'] ?? '', ENT_QUOTES) ?>"
                                    data-phone="<?= htmlspecialchars($user['whatsapp'] ?? '', ENT_QUOTES) ?>"
                                    data-address="<?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES) ?>"
                                    data-city="<?= htmlspecialchars($user['kotakabupaten'] ?? '', ENT_QUOTES) ?>"
                                    data-prov="<?= htmlspecialchars($user['prov_cd'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-code="<?= htmlspecialchars($user['bank_code'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-no="<?= htmlspecialchars($user['bank_acc_no'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-name="<?= htmlspecialchars($user['bank_acc_name'] ?? '', ENT_QUOTES) ?>"
                                    <?= (string)($tad['itc_usr_id'] ?? '') === (string)$user['rec_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['account_nm']) ?> - <?= htmlspecialchars($user['account_id']) ?> (Rec ID: <?= htmlspecialchars($user['rec_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Nama, alias, kontak, dan alamat mengikuti data registrasi akun.</p>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="name" id="name" value="<?= htmlspecialchars($tad['spv_name']) ?>" required readonly class="mt-1 block w-full rounded-md border-gray-300 p-2 border uppercase shadow-sm bg-gray-100 text-gray-600">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Alias</label>
                        <input type="text" name="alias" id="alias" value="<?= htmlspecialchars($tad['spv_alias']) ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 p-2 border uppercase shadow-sm bg-gray-100 text-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($tad['email']) ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 p-2 border shadow-sm bg-gray-100 text-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nomor HP</label>
                        <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($tad['phone']) ?>" required readonly class="mt-1 block w-full rounded-md border-gray-300 p-2 border shadow-sm bg-gray-100 text-gray-600">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Alamat Domisili</label>
                        <textarea name="address" id="address" rows="3" readonly class="mt-1 block w-full rounded-md border-gray-300 p-2 border shadow-sm bg-gray-100 text-gray-600"><?= htmlspecialchars($tad['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 p-6 rounded-xl border border-dashed border-gray-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700 uppercase text-sm"><i class="fas fa-university mr-2"></i> Rekening Bank</h3>
                    <button type="button" onclick="addBankRow()" class="bg-indigo-600 text-white px-3 py-1 rounded-md text-xs hover:bg-indigo-700">+ Tambah Baris</button>
                </div>
                <div id="bank-container" class="space-y-3">
                    <?php foreach ($banks as $idx => $bank): ?>
                    <div class="bank-row grid grid-cols-1 md:grid-cols-12 gap-3 bg-white p-3 rounded-lg border border-gray-200 relative">
                        <button type="button" onclick="this.closest('.bank-row').remove()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-[10px] flex items-center justify-center">Ã—</button>
                        <div class="md:col-span-4">
                            <label class="text-[10px] text-gray-400 font-bold uppercase">Bank</label>
                            <select name="bank_code[]" class="w-full border-gray-300 rounded p-1.5 border text-sm" required>
                                <?php foreach ($bankList as $bl): ?>
                                    <option value="<?= $bl['code'] ?>" <?= $bank['bank_code'] == $bl['code'] ? 'selected' : '' ?>><?= $bl['descr'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="text-[10px] text-gray-400 font-bold uppercase">No. Rekening</label>
                            <input name="bank_acc_no[]" type="text" value="<?= htmlspecialchars($bank['bank_acc_no']) ?>" class="w-full border-gray-300 rounded p-1.5 border text-sm font-mono" required>
                        </div>
                        <div class="md:col-span-3">
                            <label class="text-[10px] text-gray-400 font-bold uppercase">Atas Nama</label>
                            <input name="bank_acc_name[]" type="text" value="<?= htmlspecialchars($bank['bank_acc_name']) ?>" class="w-full border-gray-300 rounded p-1.5 border text-sm uppercase" required>
                        </div>
                        <div class="md:col-span-2 flex flex-col items-center justify-center">
                            <label class="text-[9px] text-gray-400 font-bold">UTAMA</label>
                            <input type="radio" name="is_default_bank" value="<?= $idx ?>" class="w-4 h-4 text-indigo-600" <?= $bank['is_default'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 text-center">
                <label class="block text-sm font-bold text-gray-700 mb-4">FOTO PROFIL</label>
                <div class="relative group mx-auto w-40 h-40">
                    <div class="w-full h-full rounded-2xl border-4 border-double border-gray-100 overflow-hidden bg-gray-50 flex items-center justify-center">
                        <?php 
                        $currentPhoto = ($tad['photo_path']) ? BASE_URL . '/' . $tad['photo_path'] : '#';
                        $isHidden = ($tad['photo_path']) ? '' : 'hidden';
                        ?>
                        <img id="preview-photo" src="<?= $currentPhoto ?>" class="w-full h-full object-cover <?= $isHidden ?>">
                        <div id="placeholder-photo" class="<?= ($tad['photo_path']) ? 'hidden' : '' ?> text-gray-300">
                            <i class="fas fa-user-circle text-6xl"></i>
                        </div>
                    </div>
                    <input type="file" name="photo" accept="image/*" onchange="previewImage(this)" class="mt-4 block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-gray-100 hover:file:bg-gray-200">
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Kategori & Level</label>
                    <select name="spv_category" class="w-full border-gray-300 rounded-lg p-2 border text-sm mb-3">
                        <option value="REMOTE" <?= $tad['spv_category'] == 'REMOTE' ? 'selected' : '' ?>>REMOTE</option>
                        <option value="ONSITE" <?= $tad['spv_category'] == 'ONSITE' ? 'selected' : '' ?>>ONSITE</option>
                        <option value="TCA_SSW" <?= $tad['spv_category'] == 'TCA_SSW' ? 'selected' : '' ?>>TCA / SSW</option>
                    </select>
                    
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Provinsi</label>
                    <select name="prov_cd" id="prov_cd" class="select2-prov w-full mb-3">
                        <?php foreach($provinces as $p): ?>
                            <option value="<?= $p['rec_id'] ?>" <?= $tad['prov_cd'] == $p['rec_id'] ? 'selected' : '' ?>><?= $p['nama'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2 mt-3">Kota/Kabupaten</label>
                    <select name="city_id" id="city_id" class="w-full border-gray-300 rounded-lg p-2 border text-sm mb-3">
                        <option value="">-- Pilih Kota --</option>
                        <?php foreach($cities as $c): ?>
                            <option value="<?= $c['rec_id'] ?>" data-prov="<?= substr((string) $c['rec_id'], 0, 2) ?>" <?= (string) ($tad['city_id'] ?? '') === (string) $c['rec_id'] ? 'selected' : '' ?>><?= $c['nama'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2 mt-3">Pengalaman/Kemampuan</label>
                    <textarea name="skills_notes" rows="4" placeholder="Contoh: PBT, CBT, IBT, onsite handling, troubleshooting audio..." class="w-full border-gray-300 rounded-lg p-2 border text-sm mb-3"><?= htmlspecialchars($tad['skills_notes'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-yellow-100 transition duration-200">
                    Update Data TAD
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2-prov').select2({ width: '100%' });
        $('.select2-prov').on('change', filterCitiesByProvince);
        filterCitiesByProvince();
    });

    const provinceSelect = document.getElementById('prov_cd');
    const citySelect = document.getElementById('city_id');
    const itcUserSelect = document.getElementById('itc_user_id');
    const cityOptions = Array.from(citySelect.options).map(option => option.cloneNode(true));

    function fillProfileFromSelectedUser() {
        const selected = itcUserSelect.options[itcUserSelect.selectedIndex];

        document.getElementById('name').value = (selected.dataset.name || '').toUpperCase();
        document.getElementById('alias').value = (selected.dataset.alias || '').toUpperCase();
        document.getElementById('email').value = selected.dataset.email || '';
        document.getElementById('phone').value = selected.dataset.phone || '';
        document.getElementById('address').value = selected.dataset.address || '';

        provinceSelect.value = selected.dataset.prov || provinceSelect.value;
        filterCitiesByProvince();
        citySelect.value = selected.dataset.city || citySelect.value;

        const rawGender = (selected.dataset.gender || 'L').toUpperCase();
        const gender = ['F', 'P'].includes(rawGender) ? 'P' : 'L';
        const genderInput = document.querySelector(`input[name="gender"][value="${gender}"]`);
        if (genderInput) {
            genderInput.checked = true;
        }

        const firstBankAccountName = document.querySelector('input[name="bank_acc_name[]"]');
        const firstBankCode = document.querySelector('select[name="bank_code[]"]');
        const firstBankAccountNo = document.querySelector('input[name="bank_acc_no[]"]');
        if (firstBankCode && selected.dataset.bankCode && firstBankCode.value.trim() === '') {
            firstBankCode.value = selected.dataset.bankCode;
        }
        if (firstBankAccountNo && firstBankAccountNo.value.trim() === '') {
            firstBankAccountNo.value = selected.dataset.bankNo || '';
        }
        if (firstBankAccountName && firstBankAccountName.value.trim() === '') {
            firstBankAccountName.value = (selected.dataset.bankName || selected.dataset.name || '').toUpperCase();
        }
    }

    itcUserSelect.addEventListener('change', fillProfileFromSelectedUser);

    function filterCitiesByProvince() {
        const provinceId = provinceSelect.value;
        const selectedCity = citySelect.value;

        citySelect.innerHTML = '';
        cityOptions.forEach(option => {
            if (!option.value || !provinceId || option.dataset.prov === provinceId) {
                citySelect.appendChild(option.cloneNode(true));
            }
        });

        if (Array.from(citySelect.options).some(option => option.value === selectedCity)) {
            citySelect.value = selectedCity;
        }
    }

    let bankIndex = <?= count($banks) ?>;
    const bankOptions = `<?php foreach($bankList as $bl) echo "<option value='{$bl['code']}'>{$bl['descr']}</option>"; ?>`;

    function addBankRow() {
        const html = `
            <div class="bank-row grid grid-cols-1 md:grid-cols-12 gap-3 bg-white p-3 rounded-lg border border-gray-200 relative">
                <button type="button" onclick="this.closest('.bank-row').remove()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-[10px] flex items-center justify-center">Ã—</button>
                <div class="md:col-span-4">
                    <select name="bank_code[]" class="w-full border-gray-300 rounded p-1.5 border text-sm" required>${bankOptions}</select>
                </div>
                <div class="md:col-span-3">
                    <input name="bank_acc_no[]" type="text" placeholder="No. Rekening" class="w-full border-gray-300 rounded p-1.5 border text-sm font-mono" required>
                </div>
                <div class="md:col-span-3">
                    <input name="bank_acc_name[]" type="text" placeholder="A/N" class="w-full border-gray-300 rounded p-1.5 border text-sm uppercase" required>
                </div>
                <div class="md:col-span-2 flex flex-col items-center justify-center">
                    <input type="radio" name="is_default_bank" value="${bankIndex}" class="w-4 h-4 text-indigo-600">
                </div>
            </div>`;
        $('#bank-container').append(html);
        bankIndex++;
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                $('#preview-photo').attr('src', e.target.result).removeClass('hidden');
                $('#placeholder-photo').addClass('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php require_once dirname(__DIR__, 3) . '/includes/layout_footer.php'; ?>
