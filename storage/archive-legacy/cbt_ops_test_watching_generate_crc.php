<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);

require_once $basePath.'/config.php';
require_once $basePath.'/nisnlib.php';

function jsonError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => true,
    ]);

    exit;
}

function fw($str, $len)
{
    return str_pad(substr((string) ($str ?? ''), 0, $len), $len, ' ', STR_PAD_RIGHT);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Akses tidak valid.', 405);
}

if (! $pdo_war) {
    jsonError('Koneksi database WAR tidak tersedia.', 500);
}

if (! class_exists('ZipArchive')) {
    jsonError('Extension ZipArchive belum aktif di server.', 500);
}

$admin_code = trim($_POST['admin_code'] ?? '');
$admin_rec_id = (int) ($_POST['admin_rec_id'] ?? 0);
$sub_admin_id = (int) ($_POST['sub_admin_id'] ?? 0);
$test_type = trim($_POST['test_type'] ?? '');
$test_date = trim($_POST['test_date'] ?? '');
$selected_takers = $_POST['selected_takers'] ?? [];
$itc_user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($admin_code === '' || $test_type === '') {
    jsonError('Data ujian tidak lengkap.', 400);
}

if ($itc_user_id <= 0) {
    jsonError('Sesi login tidak valid.', 403);
}

if ($admin_rec_id <= 0 || $sub_admin_id <= 0) {
    jsonError('Data room monitoring tidak lengkap.', 400);
}

if (! is_array($selected_takers) || count($selected_takers) === 0) {
    jsonError('Anda belum memilih peserta untuk di-generate.', 400);
}

/**
 * Amankan selected_takers supaya hanya integer.
 */
$selected_takers = array_values(array_filter($selected_takers, function ($value) {
    return is_numeric($value);
}));

$selected_takers = array_map('intval', $selected_takers);

if (count($selected_takers) === 0) {
    jsonError('Data peserta yang dipilih tidak valid.', 400);
}

$stmtSpv = $pdo_run->prepare('
    SELECT rec_id
    FROM tad_supervisor
    WHERE itc_usr_id = ?
    LIMIT 1
');
$stmtSpv->execute([$itc_user_id]);
$spv_rec_id = (int) $stmtSpv->fetchColumn();

if ($spv_rec_id <= 0) {
    jsonError('Akses SPV tidak ditemukan.', 403);
}

$stmtAccess = $pdo_war->prepare('
    SELECT COUNT(*)
    FROM t3sT5ub4dm1n
    WHERE rec_id = ?
      AND admin_id = ?
      AND spv_recid = ?
');
$stmtAccess->execute([$sub_admin_id, $admin_rec_id, $spv_rec_id]);

if ((int) $stmtAccess->fetchColumn() <= 0) {
    jsonError('Anda tidak ditugaskan untuk room monitoring ini.', 403);
}

/**
 * Parsing tanggal.
 */
$testdt_str = date('Ymd');
$formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];

foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $test_date);

    if ($dt instanceof DateTime) {
        $testdt_str = $dt->format('Ymd');
        break;
    }
}

$lkeylock = shifting(trim($admin_code), 3);

/**
 * Ambil konfigurasi timer berdasarkan test type.
 */
$stmtTmr = $pdo_war->prepare('
    SELECT lrtype, nofansw, resetno
    FROM cbt_tbltimer
    WHERE testcd = ?
    ORDER BY lrtype
');
$stmtTmr->execute([$test_type]);

$tmrConfig = [];

while ($row = $stmtTmr->fetch(PDO::FETCH_ASSOC)) {
    $tmrConfig[$row['lrtype']] = $row;
}

/**
 * Ambil peserta.
 */
$placeholders = implode(',', array_fill(0, count($selected_takers), '?'));

$stmt = $pdo_war->prepare("
    SELECT *
    FROM t3sTt4keR5
    WHERE rec_id IN ($placeholders)
      AND admin_id = ?
      AND sub_adm_id = ?
");
$stmt->execute(array_merge($selected_takers, [$admin_rec_id, $sub_admin_id]));

$completed_takers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (! $completed_takers) {
    jsonError('Tidak ada data peserta yang ditemukan.', 404);
}

if (count($completed_takers) !== count(array_unique($selected_takers))) {
    jsonError('Sebagian peserta yang dipilih tidak berada dalam room monitoring ini.', 403);
}

/**
 * Nama admin aman untuk nama file.
 */
$safe_admin_code = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($admin_code));

if ($safe_admin_code === '') {
    jsonError('Kode admin tidak valid untuk nama file.', 400);
}

/**
 * Nama ZIP dan nama CRC gabungan.
 *
 * Output:
 * CRC_B00483.zip
 * ├── Rev-B00483.CRC
 * └── CRC/
 *     ├── CBT483xxxxx.CRC
 *     └── CBT483yyyyy.CRC
 */
$nama_zip = 'CRC_'.$safe_admin_code.'.zip';
$crcGabunganName = 'Rev-'.$safe_admin_code.'.CRC';

/**
 * Buat temporary ZIP.
 */
$zipFile = tempnam(sys_get_temp_dir(), 'CRC_').'.zip';
$zip = new ZipArchive;

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    jsonError('Gagal membuat file ZIP pada sistem.', 500);
}

/**
 * Buat folder CRC untuk file individual.
 */
$zip->addEmptyDir('CRC');

/**
 * Variable untuk menampung semua record CRC.
 * Nanti menjadi file Rev-[admin].CRC di luar folder CRC.
 */
$crcKumpulan = '';

foreach ($completed_takers as $acc) {
    $ttaker_id = $acc['rec_id'];
    $authorize = trim($acc['authorize'] ?? '');

    if ($authorize === '') {
        continue;
    }

    /**
     * Ambil jawaban peserta.
     */
    $stmtAns = $pdo_war->prepare('
        SELECT lrtype, partno, nosoalori, shfansw
        FROM t3sT4n5wers
        WHERE ttaker_id = ?
        ORDER BY lrtype, partno, CAST(nosoalori AS UNSIGNED)
    ');
    $stmtAns->execute([$ttaker_id]);

    $answers = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

    $ans_by_type = [];

    foreach ($answers as $a) {
        $ans_by_type[$a['lrtype']][] = $a;
    }

    $lseljawaban = '';
    $lselparam = '';
    $ltypeLFM = 0;
    $li = 0;

    foreach ($ans_by_type as $m_lrtype => $ans_list) {
        $ltypeLFM++;

        $ltjawab = isset($tmrConfig[$m_lrtype])
            ? (int) $tmrConfig[$m_lrtype]['nofansw']
            : 0;

        $resetno = isset($tmrConfig[$m_lrtype])
            ? (int) $tmrConfig[$m_lrtype]['resetno']
            : 0;

        $lselparam .= $m_lrtype.substr((string) (1000 + $ltjawab), -3);

        if ($resetno == 1) {
            $li = 0;
        }

        $ljawaban = '';

        foreach ($ans_list as $ans) {
            $li++;

            $nosoalori = (int) $ans['nosoalori'];
            $shfansw = substr((string) $ans['shfansw'], 0, 1);

            if ($nosoalori == $li) {
                $ljawaban .= $shfansw;
            } elseif ($li < $nosoalori) {
                $lx = $nosoalori - $li - 1;
                $ljawaban .= str_repeat(' ', $lx).$shfansw;
                $li = $nosoalori;
            }
        }

        if (strlen($ljawaban) < $ltjawab) {
            $ljawaban .= str_repeat(' ', $ltjawab - strlen($ljawaban));
        } else {
            $ljawaban = substr($ljawaban, 0, $ltjawab);
        }

        $lseljawaban .= nisn_encrypt($ljawaban, $lkeylock);
    }

    $lseljawaban = str_pad(substr($lseljawaban, 0, 200), 200, ' ');

    $lselparam = $ltypeLFM.$lselparam;
    $lselparam = str_pad(substr($lselparam, 0, 13), 13, ' ');

    /**
     * DOB.
     */
    // $dob  = trim($acc['dob'] ?? '');
    $mdob = fw((string) ($acc['dob'] ?? ''), 8);

    /**
     * Data peserta fixed width.
     */
    $authorcryp = fw(nisn_encrypt($authorize, $lkeylock), 10);
    $idno = fw($acc['idno'] ?? '', 20);
    $regnm = fw($acc['regnm'] ?? '', 40);
    $mdob = fw($mdob, 8);
    $countcd = fw($acc['countcd'] ?? '', 3);
    $langcode = fw($acc['langcode'] ?? '', 3);
    $sexmf = fw($acc['sexmf'] ?? '', 1);
    $custom1 = fw($acc['custom1'] ?? '', 3);
    $custom2 = fw($acc['custom2'] ?? '', 3);
    $custom3 = fw($acc['custom3'] ?? '', 3);
    $groupcd = fw($acc['groupcd'] ?? '', 5);
    $email = fw($acc['email'] ?? '', 50);

    $raw_email = trim($acc['email'] ?? '');

    $macno_val = ! empty(trim($acc['macno'] ?? ''))
        ? $acc['macno']
        : $raw_email;

    $cmpnm_val = ! empty(trim($acc['cmpnm'] ?? ''))
        ? $acc['cmpnm']
        : $raw_email;

    $macno = fw($macno_val, 17);
    $cmpnm = fw($cmpnm_val, 20);
    $questioner = fw(nisn_encrypt($acc['questioner'] ?? '', $lkeylock), 36);

    /**
     * Susun isi CRC untuk 1 peserta.
     */
    $crc = '';
    $crc .= fw($admin_code, 6);
    $crc .= $authorcryp;
    $crc .= fw(nisn_encrypt($testdt_str, $lkeylock), 8);
    $crc .= $idno;
    $crc .= $regnm;
    $crc .= $mdob;
    $crc .= $countcd;
    $crc .= $langcode;
    $crc .= $sexmf;
    $crc .= $questioner;
    $crc .= $custom1;
    $crc .= $custom2;
    $crc .= $custom3;
    $crc .= $lseljawaban;
    $crc .= $groupcd;
    $crc .= $email;
    $crc .= $macno;
    $crc .= $cmpnm;
    $crc .= fw(nisn_encrypt($lselparam, $lkeylock), 13);
    $crc .= substr($test_type, 0, 1)."\r\n";

    /**
     * 1. Masukkan ke CRC gabungan.
     * File ini nanti berada di root ZIP:
     * Rev-B00483.CRC
     */
    $crcKumpulan .= $crc;

    /**
     * 2. Buat CRC individual.
     * File ini nanti berada di folder:
     * CRC/CBT483xxxxx.CRC
     */
    $file_admin = substr(trim($admin_code), -3);
    $filename = 'CBT'.$file_admin.$authorize.'.CRC';

    /**
     * Sanitasi nama file individual supaya aman.
     */
    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $filename);

    $zip->addFromString('CRC/'.$filename, $crc);
}

/**
 * Kalau tidak ada CRC yang berhasil dibuat.
 */
if ($crcKumpulan === '') {
    $zip->close();

    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    jsonError('CRC gagal dibuat. Tidak ada peserta valid atau authorize kosong.', 400);
}

/**
 * Masukkan CRC gabungan ke root ZIP.
 * BUKAN ke folder CRC.
 */
$zip->addFromString($crcGabunganName, $crcKumpulan);

$zip->close();

/**
 * Bersihkan output buffer agar ZIP tidak corrupt.
 */
while (ob_get_level()) {
    ob_end_clean();
}

if (! file_exists($zipFile) || filesize($zipFile) <= 0) {
    jsonError('File ZIP gagal dibuat atau kosong.', 500);
}

/**
 * Download ZIP.
 */
header('Content-Type: application/zip');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="'.$nama_zip.'"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: '.filesize($zipFile));

readfile($zipFile);
unlink($zipFile);
exit;
