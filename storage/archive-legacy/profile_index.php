
<?php
    // 1. Panggil Config
    require_once __DIR__.'/../../config.php';

// 2. Ambil Session user_id (yang berisi rec_id)
$user_recid = $_SESSION['user_id'] ?? 0;
$auth_db = $_SESSION['auth_db'] ?? 'run';

if (! $user_recid) {
    header('Location: '.BASE_URL.'index.php');
    exit();
}

$psysuserid = strval($user_recid);

// $active_pdo = $pdo_run;

// Ambil list provinsi dan kota agar sinkron dengan form TAD/SPV.
$sql_all_prov = 'SELECT rec_id, nama AS descr FROM sys_provinsi ORDER BY nama';
$stmt_all_prov = $pdo_run->query($sql_all_prov);
$all_provinces = $stmt_all_prov->fetchAll(PDO::FETCH_ASSOC);

$sql_all_city = 'SELECT rec_id, nama FROM sys_kota ORDER BY nama';
$stmt_all_city = $pdo_run->query($sql_all_city);
$all_cities = $stmt_all_city->fetchAll(PDO::FETCH_ASSOC);

$error_msg = '';
$user_data = [];
$status_text = 'Unknown';
$emails = [];
$banks = [];
$bank_options = [];
$access_list = [];
$photo_url = BASE_URL.'assets/personal/nopicture.png';
$photo_extensions = ['png', 'jpg', 'jpeg', 'gif'];
foreach ($photo_extensions as $ext) {
    if (file_exists(BASE_PATH.'/assets/personal/user_'.$psysuserid.'.'.$ext)) {
        $photo_url = BASE_URL.'assets/personal/user_'.$psysuserid.'.'.$ext.'?v='.time();
        break;
    }
}
$completion_percentage = 0;

try {
    $stmt_bank_options = $pdo_run->query("SELECT code, descr FROM sys_msttable WHERE tbl_code = '51' AND statrec = 1 ORDER BY descr ASC");
    foreach ($stmt_bank_options->fetchAll(PDO::FETCH_ASSOC) as $bank_option) {
        $bank_options[$bank_option['code']] = $bank_option['descr'];
    }

    $sql_user = 'SELECT log.account_id, mst.* FROM sysitc_users mst
                 JOIN sysitc_login log ON mst.login_rec_id = log.rec_id
                 WHERE mst.rec_id = ?';
    $stmt_user = $pdo_run->prepare($sql_user);
    $stmt_user->execute([$psysuserid]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (! $user_data) {
        $error_msg = 'Data profil tidak ditemukan untuk User ID (rec_id): <b>'.htmlspecialchars($psysuserid).'</b>.';
    } else {
        $sql_status = "SELECT descr FROM sys_msttable WHERE tbl_code='53' AND code = ?";
        $stmt_status = $pdo_run->prepare($sql_status);
        $stmt_status->execute([$user_data['status']]);
        $status_data = $stmt_status->fetch(PDO::FETCH_ASSOC);
        $status_text = $status_data ? $status_data['descr'] : 'Unknown';

        $sql_prov = 'SELECT nama AS prov_nama FROM sys_provinsi WHERE rec_id = ?';
        $stmt_prov = $pdo_run->prepare($sql_prov);
        $stmt_prov->execute([$user_data['prov_cd'] ?? '']);
        $prov_data = $stmt_prov->fetch(PDO::FETCH_ASSOC);
        $provinsi_nama = $prov_data ? $prov_data['prov_nama'] : ($user_data['prov_cd'] ?? '');

        $sql_mail = 'SELECT email, asdefault, rec_id FROM sysitc_usermail WHERE user_recid = ? ORDER BY asdefault DESC, email';
        $stmt_mail = $pdo_run->prepare($sql_mail);
        $stmt_mail->execute([$psysuserid]);
        $emails = $stmt_mail->fetchAll(PDO::FETCH_ASSOC);
        $primary_email = ! empty($emails) ? $emails[0]['email'] : '';

        $sql_bank = 'SELECT bnkcd, accno, accnm, asdefault, rec_id FROM sysitc_userbank WHERE user_recid = ? ORDER BY asdefault DESC, bnkcd, accno';
        $stmt_bank = $pdo_run->prepare($sql_bank);
        $stmt_bank->execute([$psysuserid]);
        $banks = $stmt_bank->fetchAll(PDO::FETCH_ASSOC);
        $primary_bank = ! empty($banks) ? $banks[0] : ['bnkcd' => '', 'accnm' => '', 'accno' => ''];

        $sql_acc = "SELECT tbl.descr as ketr, acc.access_account as acc_code, 1 as urutan
                    FROM sysitc_usracc acc JOIN sys_msttable tbl ON tbl.code = acc.access_code
                    WHERE tbl.tbl_code='52' AND acc.access_code='02' AND acc.user_rec_id=?
                    UNION
                    SELECT grp.grpdesc as ketr, acc.access_account as acc_code, 3 as urutan
                    FROM sysitc_usracc acc JOIN sysitc_grpacc grp ON acc.access_account = grp.grpacc
                    WHERE acc.user_rec_id=? AND acc.access_code='04'
                    UNION
                    SELECT grp.grpdesc as ketr, acc.access_account as acc_code, 4 as urutan
                    FROM sysitc_usracc acc JOIN sysitc_grpacc grp ON acc.access_account = grp.grpacc
                    WHERE acc.user_rec_id=? AND acc.access_code='03'
                    ORDER BY urutan";
        $stmt_acc = $pdo_run->prepare($sql_acc);
        $stmt_acc->execute([$psysuserid, $psysuserid, $psysuserid]);
        $access_list = $stmt_acc->fetchAll(PDO::FETCH_ASSOC);

        try {
            $stmt_icu = $pdo->prepare("SELECT tbl.descr as ketr, mbr.icuno as acc_code, 2 as urutan
                                      FROM icu_member mbr JOIN sys_msttable tbl ON tbl.code='03'
                                      WHERE tbl.tbl_code='52' AND mbr.itc_user_id=?");
            $stmt_icu->execute([$psysuserid]);
            $access_list = array_merge($access_list, $stmt_icu->fetchAll(PDO::FETCH_ASSOC));
            usort($access_list, static fn ($a, $b) => ((int) ($a['urutan'] ?? 0)) <=> ((int) ($b['urutan'] ?? 0)));
        } catch (Throwable $e) {
            error_log('Profile ICU access query skipped: '.$e->getMessage());
        }

        $total_fields = 0;
        $filled_fields = 0;

        // 1. Field dari tabel User yang tampil di menu Profile
        $fields_to_check = [
            'account_id', 'account_nm', 'dob', 'sexmf',
            'whatsapp', 'address', 'prov_cd', 'kotakabupaten',
        ];

        foreach ($fields_to_check as $field) {
            $total_fields++;
            // Dihitung terisi jika tidak kosong DAN bukan sekadar karakter strip (-)
            if (! empty($user_data[$field]) && trim($user_data[$field]) !== '-') {
                $filled_fields++;
            }
        }

        // 2. Cek Foto Profil
        $total_fields++;
        // Asumsinya: jika tidak ada kata 'nopicture.png', berarti user sudah upload foto
        if (strpos($photo_url, 'nopicture.png') === false) {
            $filled_fields++;
        }

        // 3. Cek Email Utama
        $total_fields++;
        if (! empty($primary_email)) {
            $filled_fields++;
        }

        // 4. Cek Billing / Bank Information
        $total_fields += 3; // Ada 3 field: Bank, Nama Rekening, No Rekening
        if (! empty($primary_bank['bnkcd'])) {
            $filled_fields++;
        }

        if (! empty($primary_bank['accnm'])) {
            $filled_fields++;
        }

        if (! empty($primary_bank['accno'])) {
            $filled_fields++;
        }

        // Hitung persentase akhir
        $completion_percentage = ($total_fields > 0) ? round(($filled_fields / $total_fields) * 100) : 0;
        // -------------------------------------
    }
} catch (PDOException $e) {
    $error_msg = 'Fatal Database Error: '.$e->getMessage();
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $error_msg ?: ($_SESSION['error_msg'] ?? '');
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

require BASE_PATH.'/includes/layout_header.php';
?>

<div class="flex justify-center w-full h-[calc(100vh-140px)] relative">

    <!-- Toast Notification -->
    <?php if ($success_msg || $error_msg) { ?>
        <div id="statusToast" class="fixed top-24 right-10 z-[100] transition-all duration-500 transform translate-x-0 opacity-100">
            <div class="<?php echo $success_msg ? 'bg-white border-green-500 text-green-800' : 'bg-white border-red-500 text-red-800' ?> border-l-4 rounded-lg shadow-2xl p-4 flex items-center gap-4 min-w-[320px]">
                <div class="<?php echo $success_msg ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> w-10 h-10 rounded-full flex items-center justify-center shrink-0">
                    <i class="fas <?php echo $success_msg ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-xs font-bold uppercase tracking-widest mb-0.5"><?php echo $success_msg ? 'Success' : 'Error' ?></p>
                    <p class="text-sm font-medium text-gray-600"><?php echo $success_msg ?: $error_msg ?></p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('statusToast');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    setTimeout(() => toast.remove(), 500);
                }
            }, 5000);
        </script>
    <?php } ?>

        <div class="w-full max-w-6xl h-full bg-white rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.04)] border border-gray-200 flex flex-col overflow-hidden">

            <div class="px-6 pt-4 bg-gray-50/50 border-b border-gray-200 flex justify-between items-end shrink-0">
                <div class="flex space-x-6">
                    <button type="button" id="btn-tab-personal" onclick="switchTab('tab-personal')" class="tab-btn pb-2.5 border-b-2 border-brand-primary text-brand-primary font-bold text-sm transition-colors outline-none">
                        <i class="far fa-user mr-1.5"></i> Profile Details
                    </button>
                    <button type="button" id="btn-tab-access" onclick="switchTab('tab-access')" class="tab-btn pb-2.5 border-b-2 border-transparent text-gray-400 hover:text-gray-600 font-bold text-sm transition-colors outline-none">
                        <i class="fas fa-shield-alt mr-1.5"></i> Register List
                    </button>
                </div>
                <!-- Tombol Back to Dashboard (Sisi Kanan) -->
    <div class="pb-2.5">
        <a href="<?php echo BASE_URL ?>dashboard.php" class="text-gray-500 hover:text-brand-primary text-xs font-bold transition-colors flex items-center gap-2 outline-none">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>

            <form action="process_profile" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col min-h-0 relative">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
                <input type="hidden" name="rec_id" value="<?php echo htmlspecialchars($user_data['rec_id'] ?? '') ?>">

                <div class="flex-1 relative bg-gray-50/30 overflow-y-auto">

                <div id="tab-personal" class="tab-content absolute inset-0 p-6 flex flex-col gap-6 lg:grid lg:grid-cols-12 overflow-y-auto">

                    <div class="lg:col-span-4 flex flex-col gap-5">

                        <!-- Bagian Foto Profil -->
<div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm flex flex-col items-center text-center relative overflow-hidden">
    <div class="absolute top-0 left-0 right-0 h-20 bg-brand-primary/10"></div>

    <!-- Wadah Foto: Sekarang memanggil fungsi viewFullPhoto -->
    <div onclick="viewFullPhoto('<?php echo $photo_url ?>')" class="relative z-10 w-28 h-28 rounded-full border-4 border-white shadow-md overflow-hidden bg-gray-100 mb-3 mt-4 group cursor-pointer">
        <img id="profilePreview" src="<?php echo $photo_url ?>" alt="Profile" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="fas fa-search-plus text-white text-xl"></i>
        </div>
    </div>

    <!-- Input file tersembunyi -->
    <input type="file" id="photoInput" name="photo" class="hidden" accept="image/*" onchange="previewPhoto(event)">
    <!-- Input hidden untuk flag delete -->
    <input type="hidden" name="delete_photo" id="deletePhotoFlag" value="0">

    <input type="text" name="account_nm" value="<?php echo htmlspecialchars($user_data['account_nm'] ?? '') ?>" class="font-bold text-gray-800 text-lg text-center leading-tight mb-1 bg-transparent border-b border-transparent focus:border-brand-primary/30 outline-none w-full" placeholder="Your Name">
    <?php
        $profile_role_label = trim((string) ($user_role_division ?? ''));
if ($profile_role_label === '' || $profile_role_label === 'Role belum diatur') {
    $profile_role_label = trim((string) ($user_data['jabatan'] ?? '')) ?: trim((string) ($user_data['division'] ?? ''));
}
$profile_role_label = $profile_role_label !== '' ? $profile_role_label : 'Role belum diatur';
?>
    <p class="text-sm text-gray-600 font-medium mb-3"><?php echo htmlspecialchars($profile_role_label) ?></p>

    <span class="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-bold rounded-full border border-green-200 uppercase tracking-widest shadow-sm mb-5">
        <i class="fas fa-circle text-[8px] mr-1"></i> <?php echo htmlspecialchars($status_text) ?>
    </span>

    <div class="w-full text-left mb-4">
        <div class="flex justify-between text-[10px] font-bold text-gray-500 mb-1.5 uppercase">
            <span>Profile Completion</span>
            <span id="profileCompletionText" class="text-brand-primary"><?php echo $completion_percentage ?>%</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
            <div id="profileCompletionBar" class="bg-brand-primary h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $completion_percentage ?>%"></div>
        </div>
    </div>

    <!-- Group Tombol -->
    <div class="flex gap-2 w-full">
        <!-- Button Change: Memicu klik pada input file -->
        <button type="button" onclick="document.getElementById('photoInput').click()" class="flex-1 bg-brand-primary/10 hover:bg-brand-primary text-brand-primary hover:text-white py-2 rounded-lg text-xs font-bold transition-colors shadow-sm">
            <i class="fas fa-edit mr-1"></i> Change Photo
        </button>
        <!-- Button Delete -->
        <button type="button" onclick="confirmDeletePhoto()" class="flex-1 bg-red-50 hover:bg-red-500 text-red-500 hover:text-white py-2 rounded-lg text-xs font-bold transition-colors border border-red-100 shadow-sm">
            <i class="fas fa-trash-alt mr-1"></i> Delete Photo
        </button>
    </div>
</div>

<!-- Modal untuk Full View Photo -->
<div id="photoModal" class="fixed inset-0 z-[999] hidden bg-black/90 flex items-center justify-center p-4">
    <button type="button" onclick="closePhotoModal()" class="absolute top-5 right-5 text-white text-3xl">&times;</button>
    <img id="fullSizePhoto" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl">
</div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-5"><i class="fas fa-id-card text-brand-primary mr-1.5"></i> Personal Details</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Account ID</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-fingerprint text-gray-400 text-xs"></i>
                                        </div>
                                        <input type="text" id="profileAccountId" value="<?php echo htmlspecialchars($user_data['account_id'] ?? '-') ?>" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none font-mono" readonly>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                   <style>
    .date-clickable {
        position: relative;
        cursor: pointer;
    }

    .date-clickable::-webkit-calendar-picker-indicator {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
</style>

<div>
    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Date of Birth</label>

    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
        </div>

        <input
            type="date"
            name="dob"
            value="<?php echo $user_data['dob'] ?? '' ?>"
            class="date-clickable pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700"
        >
    </div>
</div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Gender</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-venus-mars text-gray-400 text-xs"></i>
                                            </div>
                                            <select name="sexmf" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700 bg-white appearance-none">
                                                <option value="" <?php echo empty($user_data['sexmf']) ? 'selected' : '' ?>>- Select -</option>
                                                <option value="M" <?php echo ($user_data['sexmf'] == 'M') ? 'selected' : '' ?>>Male</option>
                                                <option value="F" <?php echo ($user_data['sexmf'] == 'F') ? 'selected' : '' ?>>Female</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400">
                                                <i class="fas fa-chevron-down text-[10px]"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-8 flex flex-col gap-5">

                        <!-- <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-5"><i class="fas fa-id-badge text-brand-primary mr-1.5"></i> Employee Information</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                                <div class="md:col-span-2">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Company</label>
                                    <input type="text" name="company" value="<?php echo htmlspecialchars($user_data['company'] ?? '-') ?>" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none" readonly>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Division</label>
                                    <input type="text" name="division" value="<?php echo htmlspecialchars($user_data['division'] ?? '-') ?>" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none" readonly>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Level</label>
                                    <input type="text" name="level" value="<?php echo htmlspecialchars($user_data['level'] ?? '-') ?>" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none" readonly>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Employee No.</label>
                                    <input type="text" name="employee_no" value="<?php echo htmlspecialchars($user_data['employee_no'] ?? '-') ?>" class="w-full px-3 py-2 text-sm font-mono border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none" readonly>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Credit Union No.</label>
                                    <input type="text" name="cu_no" value="<?php echo htmlspecialchars($user_data['cu_no'] ?? '-') ?>" class="w-full px-3 py-2 text-sm font-mono border border-gray-200 rounded-lg bg-gray-50 text-gray-700 outline-none" readonly>
                                </div>
                            </div>
                        </div> -->

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-5"><i class="fas fa-map-marker-alt text-brand-primary mr-1.5"></i> Contact & Location</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone-alt text-gray-400 text-xs"></i>
                                        </div>
                                        <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($user_data['whatsapp'] ?? '') ?>" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700" placeholder="e.g. 08123456789">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Email</label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-envelope text-gray-400 text-xs"></i>
                                            </div>
                                            <input type="email" value="<?php echo htmlspecialchars($primary_email) ?>" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 outline-none text-gray-700" placeholder="your@email.com" readonly>
                                        </div>
                                        <button type="button" onclick="openEmailModal()" class="bg-brand-primary/10 hover:bg-brand-primary text-brand-primary hover:text-white px-3 py-2 rounded-lg text-[10px] font-bold transition-colors shadow-sm whitespace-nowrap">
                                            Ganti Email
                                        </button>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Full Address</label>
                                    <div class="relative">
                                        <div class="absolute top-2.5 left-0 pl-3 flex items-start pointer-events-none">
                                            <i class="fas fa-home text-gray-400 text-xs mt-0.5"></i>
                                        </div>
                                        <textarea name="address" rows="2" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none resize-none text-gray-700" placeholder="Enter your full address"><?php echo htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Province</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-map text-gray-400 text-xs"></i>
                                        </div>
                                        <select name="prov_cd" id="prov_cd" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700 bg-white appearance-none">
                                            <option value="">- Select Province -</option>
                                            <?php foreach ($all_provinces as $prov) { ?>
                                                <option value="<?php echo $prov['rec_id'] ?>" <?php echo ((string) ($user_data['prov_cd'] ?? '') === (string) $prov['rec_id']) ? 'selected' : '' ?>>
                                                    <?php echo htmlspecialchars($prov['descr']) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400">
                                            <i class="fas fa-chevron-down text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">City / Municipality</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-city text-gray-400 text-xs"></i>
                                        </div>
                                        <select name="kotakabupaten" id="kotakabupaten" class="pl-9 w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700 bg-white appearance-none">
                                            <option value="">- Select City -</option>
                                            <?php foreach ($all_cities as $city) { ?>
                                                <option value="<?php echo $city['rec_id'] ?>" data-prov="<?php echo substr((string) $city['rec_id'], 0, 2) ?>" <?php echo (string) ($user_data['kotakabupaten'] ?? '') === (string) $city['rec_id'] ? 'selected' : '' ?>>
                                                    <?php echo htmlspecialchars($city['nama']) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400">
                                            <i class="fas fa-chevron-down text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <div class="flex justify-between items-center mb-5">
                                <h3 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-0"><i class="fas fa-money-check-alt text-brand-primary mr-1.5"></i> Billing Information</h3>

                                <button type="button" onclick="openBankModal()" class="bg-brand-primary/10 hover:bg-brand-primary text-brand-primary hover:text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-colors shadow-sm flex items-center gap-1">
                                    <i class="fas fa-plus"></i> Add Account
                                </button>
                            </div>

                            <?php if (empty($banks)) { ?>
                                <div id="bankEmptyState" class="border border-dashed border-gray-300 rounded-xl bg-gray-50 px-5 py-6 text-center">
                                    <div class="w-11 h-11 rounded-full bg-brand-primary/10 text-brand-primary flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800 mb-1">Belum ada rekening bank</h4>
                                    <p class="text-xs text-gray-500 mb-4">Tambahkan rekening bank untuk melengkapi informasi billing Anda.</p>
                                    <button type="button" onclick="openBankModal()" class="bg-brand-primary hover:bg-brand-primaryHover text-white px-4 py-2 rounded-lg text-xs font-bold transition-colors shadow-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Bank Account
                                    </button>
                                </div>
                            <?php } ?>

                            <input type="hidden" name="bank_rec_id" id="bank_rec_id" value="<?php echo htmlspecialchars($primary_bank['rec_id'] ?? '') ?>">

                            <?php if (! empty($banks)) { ?>
                                <div id="savedBankSelector" class="mb-4">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Rekening Tersimpan</label>
                                    <div class="relative">
                                        <select id="saved_bank_select" onchange="selectSavedBank()" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary focus:border-brand-primary outline-none text-gray-800 bg-white appearance-none cursor-pointer">
                                            <?php foreach ($banks as $b) { ?>
                                                <?php
                                                $bank_descr = $bank_options[$b['bnkcd']] ?? $b['bnkcd'];
                                                $bank_label = $bank_descr.' | '.$b['accno'].' | A/N '.$b['accnm'];
                                                ?>
                                                <option
                                                    value="<?php echo htmlspecialchars($b['rec_id']) ?>"
                                                    data-bank-code="<?php echo htmlspecialchars($b['bnkcd'], ENT_QUOTES) ?>"
                                                    data-account-no="<?php echo htmlspecialchars($b['accno'], ENT_QUOTES) ?>"
                                                    data-account-name="<?php echo htmlspecialchars($b['accnm'], ENT_QUOTES) ?>"
                                                    <?php echo ($primary_bank['rec_id'] == $b['rec_id']) ? 'selected' : '' ?>
                                                ><?php echo htmlspecialchars($bank_label) ?></option>
                                            <?php } ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500"><i class="fas fa-chevron-down text-[10px]"></i></div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">Pilih rekening lama untuk dijadikan default atau diedit.</p>
                                </div>
                            <?php } ?>

                            <div id="bankFields" class="<?php echo empty($banks) ? 'hidden ' : '' ?>grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Bank Name</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-university text-gray-400 text-xs"></i>
                                        </div>
                                        <select name="bank_code" class="pl-9 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary focus:border-brand-primary outline-none transition-all text-gray-800 bg-white appearance-none cursor-pointer">
                                            <option value=""></option>
                                            <?php foreach ($bank_options as $bank_code => $bank_name) { ?>
                                                <option value="<?php echo htmlspecialchars($bank_code) ?>" <?php echo (($primary_bank['bnkcd'] ?? '') == $bank_code) ? 'selected' : '' ?>><?php echo htmlspecialchars($bank_name) ?></option>
                                            <?php } ?>
                                            <?php if (! empty($primary_bank['bnkcd']) && ! isset($bank_options[$primary_bank['bnkcd']])) { ?>
                                                <option value="<?php echo htmlspecialchars($primary_bank['bnkcd']) ?>" selected><?php echo htmlspecialchars($primary_bank['bnkcd']) ?></option>
                                            <?php } ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500"><i class="fas fa-chevron-down text-[10px]"></i></div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Account Name</label>
                                    <input type="text" name="account_name" value="<?php echo htmlspecialchars($primary_bank['accnm'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none text-gray-700" placeholder="Account owner name">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Account No.</label>
                                    <input type="text" name="account_no" value="<?php echo htmlspecialchars($primary_bank['accno'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none font-mono text-gray-700" placeholder="Bank account number">
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex flex-col md:flex-row justify-between items-start md:items-center shadow-sm gap-3">
                            <div class="flex items-center gap-3">
                                <div class="bg-white p-2.5 rounded-lg shadow-sm border border-blue-100">
                                    <i class="fas fa-shield-alt text-brand-primary"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800">Account Security</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">Disarankan untuk mengganti password secara berkala.</p>
                                </div>
                            </div>
                            <button type="button" onclick="openPasswordModal()" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-[11px] font-bold shadow-sm transition-colors w-full md:w-auto">
                                Ganti Password
                            </button>
                        </div>
                        <button type="submit" class="bg-brand-primary hover:bg-brand-primaryHover text-white text-[10px] font-bold py-1.5 px-4 rounded-md transition-colors shadow-sm flex items-center justify-center gap-2 mx-auto">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </div>

                    <div id="tab-access" class="tab-content absolute inset-0 p-6 hidden flex-col">
                        <div class="flex justify-between items-center mb-4 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider"><i class="fas fa-shield-alt text-brand-primary mr-1.5"></i> Register List</h3>
                                <p class="text-[11px] text-gray-500 mt-1">Daftar hak akses sistem yang terhubung dengan akun Anda saat ini.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-xl flex-1 relative bg-white overflow-hidden shadow-sm">
                            <div class="absolute inset-0 overflow-y-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 sticky top-0 border-b border-gray-200 z-10 shadow-sm">
                                        <tr>
                                            <th class="px-6 py-3 font-bold text-gray-600 uppercase tracking-wide text-xs">Descriptions</th>
                                            <th class="px-6 py-3 font-bold text-gray-600 uppercase tracking-wide text-xs border-l border-gray-200 w-48 text-center">Access Code</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($access_list as $row) { ?>
                                        <tr class="hover:bg-brand-primary/5 transition-colors">
                                            <td class="px-6 py-3.5 text-gray-800 font-medium"><?php echo htmlspecialchars($row['ketr']) ?></td>
                                            <td class="px-6 py-3.5 text-gray-600 border-l border-gray-100 font-mono text-center bg-gray-50/50"><?php echo htmlspecialchars($row['acc_code']) ?></td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (empty($access_list)) { ?>
                                        <tr><td colspan="2" class="px-6 py-10 text-center text-gray-400 italic">No access codes found.</td></tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>


            </form>
        </div>
    <?php // endif;?>

</div>

<div id="addBankModal" class="hidden fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">

        <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">Add New Account</h3>
            <button type="button" onclick="closeBankModal()" class="text-gray-400 hover:text-gray-600 outline-none transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Bank Name</label>
                    <div class="relative">
                        <input type="text" id="new_bank_code" list="bankOptions" class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary focus:border-brand-primary outline-none text-gray-700" placeholder="Ketik nama bank">
                        <datalist id="bankOptions">
                            <?php foreach ($bank_options as $bank_code => $bank_name) { ?>
                                <option value="<?php echo htmlspecialchars($bank_code) ?>"><?php echo htmlspecialchars($bank_name) ?></option>
                            <?php } ?>
                        </datalist>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Account Name</label>
                    <input type="text" id="new_account_name" class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary focus:border-brand-primary outline-none text-gray-700" placeholder="e.g. Igrelle Jenifer Skova">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Account Number</label>
                    <input type="text" id="new_account_no" class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary focus:border-brand-primary outline-none font-mono text-gray-700" placeholder="e.g. 5790465701">
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-100">
            <button type="button" onclick="closeBankModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-5 py-2 rounded-lg text-xs font-bold transition-colors">
                Close
            </button>
            <button type="button" onclick="submitNewBank()" class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded-lg text-xs font-bold transition-colors shadow-sm">
                Save changes
            </button>
        </div>

    </div>
</div>

<div id="passwordModal" class="hidden fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <form action="process_password" method="POST">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800">Ganti Password</h3>
                <button type="button" onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-600 outline-none transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Password Baru</label>
                        <input type="password" name="new_password" id="new_password" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none" placeholder="Masukkan password baru">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none" placeholder="Ulangi password baru">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-100">
                <button type="button" onclick="closePasswordModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-5 py-2 rounded-lg text-xs font-bold transition-colors">
                    Batal
                </button>
                <button type="submit" class="bg-brand-primary hover:bg-brand-primaryHover text-white px-5 py-2 rounded-lg text-xs font-bold transition-colors shadow-sm">
                    Simpan Password
                </button>
            </div>
        </form>
    </div>
</div>

<div id="emailModal" class="hidden fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <form action="process_email_request" method="POST">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800">Ganti Email</h3>
                <button type="button" onclick="closeEmailModal()" class="text-gray-400 hover:text-gray-600 outline-none transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-700">
                        Kode OTP akan dikirim ke email baru. Email akun baru berubah setelah OTP berhasil diverifikasi.
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Email Saat Ini</label>
                        <input type="email" value="<?php echo htmlspecialchars($primary_email) ?>" readonly class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 outline-none text-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Email Baru</label>
                        <input type="email" name="new_email" id="new_email" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-brand-primary outline-none" placeholder="email-baru@example.com">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-100">
                <button type="button" onclick="closeEmailModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-5 py-2 rounded-lg text-xs font-bold transition-colors">
                    Batal
                </button>
                <button type="submit" class="bg-brand-primary hover:bg-brand-primaryHover text-white px-5 py-2 rounded-lg text-xs font-bold transition-colors shadow-sm">
                    Kirim OTP
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('flex', 'lg:grid');
        });

        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('border-brand-primary', 'text-brand-primary');
            el.classList.add('border-transparent', 'text-gray-400');
        });

        const activeTab = document.getElementById(tabId);
        activeTab.classList.remove('hidden');
        if(tabId === 'tab-personal') {
            activeTab.classList.add('flex', 'lg:grid');
        } else {
            activeTab.classList.add('flex');
        }

        const activeBtn = document.getElementById('btn-' + tabId);
        activeBtn.classList.remove('border-transparent', 'text-gray-400');
        activeBtn.classList.add('border-brand-primary', 'text-brand-primary');
    }

// --- Password Modal Functions ---
function openPasswordModal() {
    document.getElementById('passwordModal').classList.remove('hidden');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
}

// --- Email Modal Functions ---
function openEmailModal() {
    document.getElementById('emailModal').classList.remove('hidden');
}

function closeEmailModal() {
    document.getElementById('emailModal').classList.add('hidden');
    document.getElementById('new_email').value = '';
}

// --- Modal Bank Functions ---
function openBankModal() {
    document.getElementById('addBankModal').classList.remove('hidden');
}

function closeBankModal() {
    document.getElementById('addBankModal').classList.add('hidden');
    document.getElementById('new_bank_code').value = '';
    document.getElementById('new_account_name').value = '';
    document.getElementById('new_account_no').value = '';
}

function selectSavedBank() {
    const savedBankSelect = document.getElementById('saved_bank_select');
    const selected = savedBankSelect?.options[savedBankSelect.selectedIndex];
    if (!selected) {
        return;
    }

    document.getElementById('bank_rec_id').value = selected.value || '';
    document.querySelector('select[name="bank_code"]').value = selected.dataset.bankCode || '';
    document.querySelector('input[name="account_name"]').value = selected.dataset.accountName || '';
    document.querySelector('input[name="account_no"]').value = selected.dataset.accountNo || '';
    updateProfileCompletion();
}

function submitNewBank() {
    const bank = document.getElementById('new_bank_code').value;
    const name = document.getElementById('new_account_name').value;
    const no = document.getElementById('new_account_no').value;

    if(!bank || !name || !no) {
        alert("Semua field harus diisi!");
        return;
    }

    // Set ke main form agar tersimpan saat user klik Save Changes.
    document.getElementById('bank_rec_id').value = '';
    const bankSelect = document.querySelector('select[name="bank_code"]');
    if (!Array.from(bankSelect.options).some(option => option.value === bank)) {
        bankSelect.appendChild(new Option(bank, bank, true, true));
    }
    bankSelect.value = bank;
    document.querySelector('input[name="account_name"]').value = name;
    document.querySelector('input[name="account_no"]').value = no;
    document.getElementById('bankEmptyState')?.classList.add('hidden');
    document.getElementById('saved_bank_select')?.closest('#savedBankSelector')?.classList.add('hidden');
    document.getElementById('bankFields')?.classList.remove('hidden');
    updateProfileCompletion();

    closeBankModal();
}


// Fungsi untuk menampilkan foto ukuran penuh
function viewFullPhoto(url) {
    const modal = document.getElementById('photoModal');
    const fullImg = document.getElementById('fullSizePhoto');
    fullImg.src = url;
    modal.classList.remove('hidden');
}

// Fungsi menutup modal
function closePhotoModal() {
    document.getElementById('photoModal').classList.add('hidden');
}

// Preview foto saat user memilih file baru
function previewPhoto(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const output = document.getElementById('profilePreview');
        output.src = reader.result;
        // Update link full view agar mengarah ke foto baru
        output.parentElement.setAttribute('onclick', `viewFullPhoto('${reader.result}')`);
    }
    reader.readAsDataURL(event.target.files[0]);
    document.getElementById('deletePhotoFlag').value = "0"; // Reset flag hapus
    updateProfileCompletion();
}

// Fungsi hapus foto
function confirmDeletePhoto() {
    if (confirm("Are you sure you want to remove your profile photo?")) {
        const preview = document.getElementById('profilePreview');

        // Gunakan path absolut yang konsisten dengan sistem Anda
        const defaultPhoto = '<?php echo BASE_URL ?>/assets/personal/nopicture.png';

        preview.src = defaultPhoto;
        // Update fungsi klik agar preview full size juga berubah ke default
        preview.parentElement.setAttribute('onclick', `viewFullPhoto('${defaultPhoto}')`);

        document.getElementById('photoInput').value = ""; // Reset input file jika ada file yang baru dipilih
        document.getElementById('deletePhotoFlag').value = "1"; // Set flag untuk diproses di backend
        updateProfileCompletion();
    }
}

// Tutup modal jika klik di luar gambar
document.getElementById('photoModal').addEventListener('click', function(e) {
    if (e.target === this) closePhotoModal();
});

const profileProvinceSelect = document.getElementById('prov_cd');
const profileCitySelect = document.getElementById('kotakabupaten');
const profileCityOptions = Array.from(profileCitySelect.options).map(option => option.cloneNode(true));
const profileCompletionText = document.getElementById('profileCompletionText');
const profileCompletionBar = document.getElementById('profileCompletionBar');

function hasFilledValue(selector) {
    const field = document.querySelector(selector);
    return field && field.value.trim() !== '' && field.value.trim() !== '-';
}

function updateProfileCompletion() {
    const checks = [
        () => hasFilledValue('#profileAccountId'),
        () => hasFilledValue('input[name="account_nm"]'),
        () => hasFilledValue('input[name="dob"]'),
        () => hasFilledValue('select[name="sexmf"]'),
        () => hasFilledValue('input[name="whatsapp"]'),
        () => hasFilledValue('textarea[name="address"]'),
        () => hasFilledValue('select[name="prov_cd"]'),
        () => hasFilledValue('select[name="kotakabupaten"]'),
        () => document.getElementById('deletePhotoFlag').value !== '1' && !document.getElementById('profilePreview').src.includes('nopicture.png'),
        () => hasFilledValue('input[type="email"][readonly]'),
        () => hasFilledValue('select[name="bank_code"]'),
        () => hasFilledValue('input[name="account_name"]'),
        () => hasFilledValue('input[name="account_no"]')
    ];

    const filled = checks.filter(check => check()).length;
    const percentage = Math.round((filled / checks.length) * 100);

    profileCompletionText.textContent = `${percentage}%`;
    profileCompletionBar.style.width = `${percentage}%`;
}

function filterProfileCitiesByProvince() {
    const provinceId = profileProvinceSelect.value;
    const selectedCity = profileCitySelect.value;

    profileCitySelect.innerHTML = '';
    profileCityOptions.forEach(option => {
        if (!option.value || !provinceId || option.dataset.prov === provinceId) {
            profileCitySelect.appendChild(option.cloneNode(true));
        }
    });

    if (Array.from(profileCitySelect.options).some(option => option.value === selectedCity)) {
        profileCitySelect.value = selectedCity;
    }
}

profileProvinceSelect.addEventListener('change', filterProfileCitiesByProvince);
document.querySelectorAll('input[name="account_nm"], input[name="dob"], select[name="sexmf"], input[name="whatsapp"], textarea[name="address"], select[name="prov_cd"], select[name="kotakabupaten"], select[name="bank_code"], input[name="account_name"], input[name="account_no"]').forEach(field => {
    field.addEventListener('input', updateProfileCompletion);
    field.addEventListener('change', updateProfileCompletion);
});
filterProfileCitiesByProvince();
updateProfileCompletion();
</script>



<?php
    require BASE_PATH.'/includes/layout_footer.php';
?>

