<?php
    /**
 * File: modules/cbt_ops/test_plan/create.php
 * Deskripsi: Form Tambah Data Supervisor/TAD
 */

    // 1. Inisialisasi Environment
    require_once dirname(__DIR__, 3) . '/includes/layout_header.php';
    require_once BASE_PATH . '/includes/tad_access.php';
    require_once BASE_PATH . '/models/AuditLog.php';
    require_once BASE_PATH . '/includes/audit_helper.php';

    $error               = null;
    $success             = null;
    $canManageSupervisor = canManageTadSupervisor($pdo_run, (int) ($_SESSION['user_id'] ?? 0));

    function getItcUserPhotoPath($userId)
    {
    $extensions = ['png', 'jpg', 'jpeg', 'gif'];
    foreach ($extensions as $ext) {
        $relativePath = 'assets/personal/user_' . $userId . '.' . $ext;
        if (file_exists(BASE_PATH . '/' . $relativePath)) {
            return $relativePath;
        }
    }

    return null;
    }

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

    if (! $canManageSupervisor) {
    http_response_code(403);
    die('Anda tidak memiliki izin untuk menambah data TAD/SPV.');
    }

    // 2. Ambil Master Data untuk Dropdown Form
    try {
    $stmtColumn = $pdo_run->query("SHOW COLUMNS FROM tad_supervisor LIKE 'skills_notes'");
    if (! $stmtColumn->fetch()) {
        $pdo_run->exec("ALTER TABLE tad_supervisor ADD COLUMN skills_notes TEXT NULL AFTER lvl_spv");
    }

    // Ambil Data Kota
    $stmtCity = $pdo_run->query("SELECT rec_id, nama FROM sys_kota ORDER BY nama ASC");
    $cities   = $stmtCity->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Data Provinsi
    $stmtProv  = $pdo_run->query("SELECT rec_id, nama FROM sys_provinsi ORDER BY nama ASC");
    $provinces = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Data Bank (tbl_code = '51' dan statrec = 1)
    $stmtBank = $pdo_run->query("SELECT code, descr FROM sys_msttable WHERE tbl_code = '51' AND statrec = 1 ORDER BY descr ASC");
    $bankList = $stmtBank->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo_run->query("
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
        HAVING SUM(CASE WHEN ua.user_rec_id IS NOT NULL THEN 1 ELSE 0 END) = 0
            OR SUM(CASE WHEN UPPER(g.grpdesc) LIKE '%TAD%ADMIN%'
                OR UPPER(g.grpdesc) LIKE '%TAD%STAFF%'
                OR UPPER(g.grpdesc) LIKE '%TAD%SPV%'
                OR UPPER(g.grpdesc) = 'SUPER ADMIN' THEN 1 ELSE 0 END) > 0
        ORDER BY u.account_nm ASC
    ");
    $itcUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    foreach ($itcUsers as &$itcUser) {
        $itcUser['photo_path'] = getItcUserPhotoPath($itcUser['rec_id']);
    }
    unset($itcUser);
    } catch (PDOException $e) {
    $error    = "Gagal memuat data referensi: " . $e->getMessage();
    $itcUsers = [];
    }

    // 3. Proses Submit Form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo_run->beginTransaction();

        // A. Sanitasi Data Text
        $type         = $_POST['type'] ?? 'SPV';
        $captain      = ($type === 'CAP') ? 1 : 0;
        $status       = $_POST['status'] ?? 1;
        $name         = strtoupper(trim($_POST['name'] ?? ''));
        $alias        = strtoupper(trim($_POST['alias'] ?? ''));
        $gender       = $_POST['gender'] ?? 'L';
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $phone        = trim($_POST['phone'] ?? '');
        $city_id      = ! empty($_POST['city_id']) ? $_POST['city_id'] : null;
        $address      = trim($_POST['address'] ?? '');
        $spv_category = $_POST['spv_category'] ?? null;
        $prov_cd      = $_POST['prov_cd'] ?? null;
        $lvl_spv      = $_POST['lvl_spv'] ?? 0;
        $skills_notes = trim($_POST['skills_notes'] ?? '');
        $itc_user_id  = ! empty($_POST['itc_user_id']) ? (int) $_POST['itc_user_id'] : null;

        if ($itc_user_id) {
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

            if (! $selectedUser) {
                throw new Exception('Akun ITC yang dipilih tidak ditemukan atau tidak aktif.');
            }

            $name          = strtoupper(trim((string) ($selectedUser['account_nm'] ?? $name)));
            $alias         = strtoupper(trim((string) (($selectedUser['alias_nm'] ?? '') ?: $alias)));
            $accountGender = strtoupper((string) ($selectedUser['sexmf'] ?? ''));
            $gender        = match ($accountGender) {
                'F', 'P' => 'P',
                'M', 'L' => 'L',
                default => $gender,
            };
            $email   = strtolower(trim((string) (($selectedUser['email_id'] ?? '') ?: $email)));
            $phone   = trim((string) (($selectedUser['whatsapp'] ?? '') ?: $phone));
            $address = trim((string) (($selectedUser['address'] ?? '') ?: $address));
            $city_id = ! empty($selectedUser['kotakabupaten']) ? $selectedUser['kotakabupaten'] : $city_id;
            $prov_cd = ! empty($selectedUser['prov_cd']) ? $selectedUser['prov_cd'] : $prov_cd;

            if (empty(array_filter($_POST['bank_code'] ?? [])) && ! empty($selectedUser['bank_code'])) {
                $_POST['bank_code'] = [$selectedUser['bank_code']];
                $_POST['bank_acc_no'] = [$selectedUser['bank_acc_no'] ?? ''];
                $_POST['bank_acc_name'] = [$selectedUser['bank_acc_name'] ?? $name];
                $_POST['is_default_bank'] = 0;
            }
        }

        // Validasi Input Wajib
        if (empty($itc_user_id)) {
            throw new Exception("Akun ITC / Rec ID User wajib dipilih.");
        }

        if (empty($name) || empty($phone)) {
            throw new Exception("Nama dan Nomor Telepon wajib tersedia di data akun ITC.");
        }

        // B. Proses Upload Foto
        $photoPath = $itc_user_id ? getItcUserPhotoPath($itc_user_id) : null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['photo']['tmp_name'];
            $fileName    = $_FILES['photo']['name'];
            $fileSize    = $_FILES['photo']['size'];
            $fileType    = $_FILES['photo']['type'];

            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            $fileExtension     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (! in_array($fileExtension, $allowedExtensions)) {
                throw new Exception("Format foto tidak valid. Gunakan JPG atau PNG.");
            }
            if ($fileSize > 2 * 1024 * 1024) { // 2MB
                throw new Exception("Ukuran foto maksimal 2MB.");
            }

            // Path penyimpanan: root/storage/photos/tad/
            $uploadDir = dirname(__DIR__, 3) . '/storage/photos/tad/';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = bin2hex(random_bytes(10)) . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $photoPath = 'storage/photos/tad/' . $newFileName;
            } else {
                throw new Exception("Gagal menyimpan file ke server.");
            }
        }

        // C. Insert ke Tabel `tad_supervisor`
        $sqlTad = "INSERT INTO tad_supervisor
                   (itc_usr_id, spv_name, spv_alias, gender, email, phone, address, city_id, spv_category, prov_cd, lvl_spv, skills_notes, captain, status, photo_path, entdt, lupd)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmtTad = $pdo_run->prepare($sqlTad);
        $stmtTad->execute([
            $itc_user_id, $name, $alias, $gender, $email, $phone, $address,
            $city_id, $spv_category, $prov_cd, $lvl_spv,
            $skills_notes, $captain, $status, $photoPath,
        ]);

        $tadId = $pdo_run->lastInsertId();

        // D. Insert ke Tabel `tad_rekening`
        $defaultBankToSync = null;
        if (isset($_POST['bank_code']) && is_array($_POST['bank_code'])) {
            $defaultBankIndex = $_POST['is_default_bank'] ?? 0;

            $sqlBank     = "INSERT INTO tad_rekening (tad_id, bank_code, bank_acc_no, bank_acc_name, is_default) VALUES (?, ?, ?, ?, ?)";
            $stmtBankIns = $pdo_run->prepare($sqlBank);

            foreach ($_POST['bank_code'] as $index => $code) {
                $accNo   = trim($_POST['bank_acc_no'][$index] ?? '');
                $accName = strtoupper(trim($_POST['bank_acc_name'][$index] ?? ''));

                if (! empty($code) && ! empty($accNo)) {
                    $isDefault = ($index == $defaultBankIndex) ? 1 : 0;
                    $stmtBankIns->execute([$tadId, $code, $accNo, $accName, $isDefault]);
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

        logAudit($pdo_run, 'TEST_PLAN_CREATE', 'tad_supervisor', (int) $tadId, [
            'input_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'input_by_name' => $_SESSION['account_nm'] ?? $_SESSION['username'] ?? null,
            'tad_id' => (int) $tadId,
            'itc_user_id' => $itc_user_id,
            'tad_name' => $name,
            'tad_alias' => $alias,
            'type' => $captain ? 'CAP' : 'SPV',
            'status' => (int) $status,
        ]);

        // Kirim notifikasi ke TAD SPV baru
        try {
            (new Notification($pdo_run))->create(
                $itc_user_id,
                (int) ($_SESSION['user_id'] ?? 0) ?: null,
                'tad_spv_created',
                'Penugasan SPV Baru',
                "Anda ditugaskan sebagai Supervisor TAD. Data Anda telah terdaftar.",
                rtrim(BASE_URL, '/') . '/modules/cbt_ops/test_plan/index.php',
                'tad_supervisor',
                (int) $tadId
            );
        } catch (Throwable $e) {
            error_log('Notification create failed: ' . $e->getMessage());
        }

        // Simpan pesan sukses di session dan redirect
        $_SESSION['success'] = "Data TAD [$name] berhasil disimpan.";
        echo "<script>window.location.href='index.php';</script>";
        exit;

    } catch (Throwable $e) {
        if ($pdo_run->inTransaction()) {
            $pdo_run->rollBack();
        }
        $error = $e->getMessage();
    }
    }
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">ADD SPV TEST</h1>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm" role="alert">
            <p><?php echo htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <form action="create.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Informasi Utama</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Akun ITC / Rec ID User</label>
                        <select name="itc_user_id" id="itc_user_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="">-- Pilih akun TAD --</option>
                            <?php foreach ($itcUsers as $user): ?>
                                <option value="<?php echo $user['rec_id'] ?>"
                                    data-name="<?php echo htmlspecialchars($user['account_nm'] ?? '', ENT_QUOTES) ?>"
                                    data-alias="<?php echo htmlspecialchars($user['alias_nm'] ?? '', ENT_QUOTES) ?>"
                                    data-gender="<?php echo htmlspecialchars($user['sexmf'] ?? '', ENT_QUOTES) ?>"
                                    data-email="<?php echo htmlspecialchars($user['email_id'] ?? '', ENT_QUOTES) ?>"
                                    data-phone="<?php echo htmlspecialchars($user['whatsapp'] ?? '', ENT_QUOTES) ?>"
                                    data-address="<?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES) ?>"
                                    data-city="<?php echo htmlspecialchars($user['kotakabupaten'] ?? '', ENT_QUOTES) ?>"
                                    data-prov="<?php echo htmlspecialchars($user['prov_cd'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-code="<?php echo htmlspecialchars($user['bank_code'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-no="<?php echo htmlspecialchars($user['bank_acc_no'] ?? '', ENT_QUOTES) ?>"
                                    data-bank-name="<?php echo htmlspecialchars($user['bank_acc_name'] ?? '', ENT_QUOTES) ?>"
                                    data-photo="<?php echo ! empty($user['photo_path']) ? htmlspecialchars(BASE_URL . $user['photo_path'], ENT_QUOTES) : '' ?>">
                                    <?php echo htmlspecialchars($user['account_nm']) ?> - <?php echo htmlspecialchars($user['account_id']) ?> (Rec ID: <?php echo htmlspecialchars($user['rec_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Pilih akun terlebih dahulu. Nama, alias, kontak, dan alamat akan mengikuti data registrasi akun.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="name" id="name" required readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100 text-gray-600 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Alias/Panggilan</label>
                        <input type="text" name="alias" id="alias" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100 text-gray-600 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipe Anggota</label>
                        <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="SPV">Supervisor (SPV)</option>
                            <option value="CAP">Captain (CAP)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
                        <div class="mt-2 flex space-x-4">
                            <label class="flex items-center"><input type="radio" name="gender" value="L" checked class="mr-2" onclick="return false;"> Laki-laki</label>
                            <label class="flex items-center"><input type="radio" name="gender" value="P" class="mr-2" onclick="return false;"> Perempuan</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Kontak & Alamat</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100 text-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nomor Telepon/WA</label>
                        <input type="text" name="phone" id="phone" required readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100 text-gray-600">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Alamat Domisili</label>
                        <textarea name="address" id="address" rows="3" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100 text-gray-600"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provinsi</label>
                        <select name="prov_cd" id="prov_cd" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinces as $p): ?>
                                <option value="<?php echo $p['rec_id'] ?>"><?php echo $p['nama'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kota/Kabupaten</label>
                        <select name="city_id" id="city_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="">-- Pilih Kota --</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?php echo $c['rec_id'] ?>" data-prov="<?php echo substr((string) $c['rec_id'], 0, 2) ?>"><?php echo $c['nama'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h2 class="text-lg font-semibold text-gray-700">Informasi Rekening Bank</h2>
                    <button type="button" onclick="addBankRow()" class="text-blue-600 hover:text-blue-800 text-sm font-bold">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Bank
                    </button>
                </div>
                <div id="bank-container" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 bg-gray-50 rounded-lg relative border">
                        <div class="md:col-span-4">
                            <label class="text-xs text-gray-500">Bank</label>
                            <select name="bank_code[]" class="w-full border-gray-300 rounded p-2 border text-sm">
                                <?php foreach ($bankList as $b): ?>
                                    <option value="<?php echo $b['code'] ?>"><?php echo $b['descr'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="text-xs text-gray-500">No. Rekening</label>
                            <input type="text" name="bank_acc_no[]" class="w-full border-gray-300 rounded p-2 border text-sm">
                        </div>
                        <div class="md:col-span-3">
                            <label class="text-xs text-gray-500">Atas Nama</label>
                            <input type="text" name="bank_acc_name[]" class="w-full border-gray-300 rounded p-2 border text-sm uppercase">
                        </div>
                        <div class="md:col-span-2 flex flex-col items-center">
                            <label class="text-[10px] font-bold text-gray-400">Utama</label>
                            <input type="radio" name="is_default_bank" value="0" checked class="mt-2 w-5 h-5">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 text-center">
                <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4">Foto Profil</h2>
                <div class="relative inline-block group">
                    <div class="w-48 h-48 rounded-2xl border-4 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
                        <img id="preview-photo" src="" class="hidden w-full h-full object-cover">
                        <div id="placeholder-photo" class="text-gray-400">
                            <i class="fas fa-user-circle text-6xl"></i>
                            <p class="text-xs mt-2">Pilih File Foto</p>
                        </div>
                    </div>
                    <input type="file" name="photo" accept="image/*" onchange="previewImage(this)" class="mt-4 block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4">Pengaturan Akun</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status Aktif</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Level (0-9)</label>
                        <input type="number" name="lvl_spv" value="1" min="1" max="9" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Pengalaman/Kemampuan</label>
                        <textarea name="skills_notes" rows="4" placeholder="Contoh: PBT, CBT, IBT, onsite handling, troubleshooting audio..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border"></textarea>
                    </div>
                </div>
                <hr class="my-6">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition duration-200 transform hover:-translate-y-1">
                    <i class="fas fa-save mr-2"></i> Simpan Data Baru
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    let bankIndex = 1;
    const itcUserSelect = document.getElementById('itc_user_id');
    const provinceSelect = document.getElementById('prov_cd');
    const citySelect = document.getElementById('city_id');
    const cityOptions = Array.from(citySelect.options).map(option => option.cloneNode(true));

    function fillProfileFromSelectedUser() {
        const selected = itcUserSelect.options[itcUserSelect.selectedIndex];

        document.getElementById('name').value = (selected.dataset.name || '').toUpperCase();
        document.getElementById('alias').value = (selected.dataset.alias || '').toUpperCase();
        document.getElementById('email').value = selected.dataset.email || '';
        document.getElementById('phone').value = selected.dataset.phone || '';
        document.getElementById('address').value = selected.dataset.address || '';

        provinceSelect.value = selected.dataset.prov || '';
        filterCitiesByProvince();
        citySelect.value = selected.dataset.city || '';

        const rawGender = (selected.dataset.gender || 'L').toUpperCase();
        const gender = ['F', 'P'].includes(rawGender) ? 'P' : 'L';
        const genderInput = document.querySelector(`input[name="gender"][value="${gender}"]`);
        if (genderInput) {
            genderInput.checked = true;
        }

        const firstBankAccountName = document.querySelector('input[name="bank_acc_name[]"]');
        const firstBankCode = document.querySelector('select[name="bank_code[]"]');
        const firstBankAccountNo = document.querySelector('input[name="bank_acc_no[]"]');

        if (firstBankCode && selected.dataset.bankCode) {
            firstBankCode.value = selected.dataset.bankCode;
        }
        if (firstBankAccountNo) {
            firstBankAccountNo.value = selected.dataset.bankNo || '';
        }
        if (firstBankAccountName) {
            firstBankAccountName.value = (selected.dataset.bankName || selected.dataset.name || '').toUpperCase();
        }

        const preview = document.getElementById('preview-photo');
        const placeholder = document.getElementById('placeholder-photo');
        if (selected.dataset.photo) {
            preview.src = selected.dataset.photo;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
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

    provinceSelect.addEventListener('change', filterCitiesByProvince);
    filterCitiesByProvince();

    // Tambah Baris Bank Dinamis
    function addBankRow() {
        const container = document.getElementById('bank-container');
        const bankOptions = `<?php foreach ($bankList as $b): ?><option value="<?php echo $b['code'] ?>"><?php echo $b['descr'] ?></option><?php endforeach; ?>`;

        const html = `
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 bg-white rounded-lg relative border">
                <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-xs flex items-center justify-center hover:bg-red-700 shadow-sm">Ã—</button>
                <div class="md:col-span-4">
                    <label class="text-xs text-gray-500">Bank</label>
                    <select name="bank_code[]" class="w-full border-gray-300 rounded p-2 border text-sm">${bankOptions}</select>
                </div>
                <div class="md:col-span-3">
                    <label class="text-xs text-gray-500">No. Rekening</label>
                    <input type="text" name="bank_acc_no[]" class="w-full border-gray-300 rounded p-2 border text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="text-xs text-gray-500">Atas Nama</label>
                    <input type="text" name="bank_acc_name[]" class="w-full border-gray-300 rounded p-2 border text-sm uppercase">
                </div>
                <div class="md:col-span-2 flex flex-col items-center">
                    <label class="text-[10px] font-bold text-gray-400">Utama</label>
                    <input type="radio" name="is_default_bank" value="${bankIndex}" class="mt-2 w-5 h-5">
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        bankIndex++;
    }

    // Preview Foto Sebelum Upload
    function previewImage(input) {
        const preview = document.getElementById('preview-photo');
        const placeholder = document.getElementById('placeholder-photo');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php require_once dirname(__DIR__, 3) . '/includes/layout_footer.php'; ?>
