<?php
// File: modules/auth/process_register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// session_start sudah di-handle config.php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($_GET['ajax'] ?? '') !== 'client_search') {
    die('Invalid request method');
}

/*
|--------------------------------------------------------------------------
| AJAX: Search instansi / client
| URL:
| process_register.php?ajax=client_search&q=international
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'client_search') {
    header('Content-Type: application/json; charset=utf-8');

    $q = trim($_GET['q'] ?? '');

    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT rec_id, clientnm
            FROM sys_mstclient
            WHERE clientnm LIKE :keyword
            ORDER BY clientnm ASC
            LIMIT 10
        ");

        $stmt->execute([
            ':keyword' => '%' . $q . '%',
        ]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    } catch (PDOException $e) {
        error_log('Client search error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Helper validasi instansi internal & Employee No
|--------------------------------------------------------------------------
*/
function normalizeInstitutionName(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

function isEmployeeNoRequiredInstitution(string $institution): bool
{
    $requiredInstitutions = [
        'pt international test center',
        'international test center',
        'pt itc',
    ];

    return in_array(normalizeInstitutionName($institution), $requiredInstitutions, true);
}

/**
 * Validasi Employee No memakai logic lama yang kamu kirim:
 * Table  : hrd_employee
 * Kolom  : empno
 * DB     : $pdo / database utama lama
 */
function getValidEmployee(PDO $pdo, string $employeeNo): ?array
{
    $employeeNo = trim($employeeNo);

    if ($employeeNo === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT rec_id, empno
            FROM hrd_employee
            WHERE TRIM(empno) = :emp_no
            LIMIT 1
        ");

        $stmt->execute([
            ':emp_no' => $employeeNo,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        error_log('Employee No validation error: ' . $e->getMessage());
        return null;
    }
}

// Panggil Auth Class (Berdasarkan urutan parameter di Auth.php: $pdoRun, $pdoMain, $pdoBot)
$auth = new Auth($pdo_run, $pdo, $pdo_bot);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $isIndividu  = isset($_POST['is_individu']) && (string) $_POST['is_individu'] === '1';
    $institution = trim($_POST['instansi'] ?? '');
    $employeeNo  = trim($_POST['emp_no'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validasi Employee No sebelum register
    |--------------------------------------------------------------------------
    | Hanya wajib untuk instansi internal.
    | Kalau Employee No tidak ditemukan di hrd_employee.empno, proses register berhenti.
    */
    if (! $isIndividu && isEmployeeNoRequiredInstitution($institution)) {
        $employee = getValidEmployee($pdo, $employeeNo);

        if ($employee === null) {
            $_SESSION['error_message'] = 'Employee No tidak ditemukan di database. Silakan periksa kembali atau hubungi Administrator/HR.';
            header('Location: register.php');
            exit;
        }

        // Simpan sementara jika nanti Auth::register ingin memakainya.
        $_POST['hrd_rec_id'] = $employee['rec_id'];
    }

    // Lempar seluruh data POST ke dalam method register()
    try {
        $result = $auth->register($_POST);
    } catch (Throwable $e) {
        error_log('REGISTER ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem saat memproses pendaftaran. Silakan coba lagi atau hubungi admin.';
        header('Location: register.php');
        exit;
    }

    if ($result['success']) {
        // Jika berhasil tersimpan ke database, kirim email & notifikasi bot
        $msg_subject  = 'Verifikasi Pendaftaran - ITCONE';
        $msg_body     = "Halo " . ucwords(strtolower($result['name'])) . " dari " . ucwords(strtolower($result['institution'])) . ",\n\n";
        $msg_body    .= "Terima kasih telah mendaftar di Portal ITC.\nBerikut adalah 5 digit kode OTP Anda:\n\n";
        $msg_body    .= "OTP CODE = " . $result['token'] . "\n\nSilakan masukkan kode ini di halaman verifikasi.\n\nSalam,\nTim ITCONE";

        try {
            Helper::kirimEmail($result['email'], $result['name'], $msg_subject, $msg_body);
        } catch (Throwable $e) {
            error_log('EMAIL ERROR: ' . $e->getMessage());
        }

        try {
            $auth->insertBotNotification($result['email'], $msg_subject, $msg_body);
        } catch (Throwable $e) {
            error_log('BOT NOTIFICATION ERROR: ' . $e->getMessage());
        }

        // Siapkan sesi untuk halaman verify.php
        $_SESSION['verify_account_id'] = $result['account_id'];
        $_SESSION['verify_purpose']    = 'register';
        $_SESSION['verify_timeout']    = time() + 300;

        header("Location: " . BASE_URL . "modules/auth/verify.php");
        exit;
    }

    // Jika gagal (ID duplikat, dll), tampilkan pesan error via SweetAlert di halaman register
    $_SESSION['error_message'] = $result['message'];
    header('Location: register.php');
    exit;
}

// Kalau file dibuka langsung tanpa POST / AJAX
header('Location: register.php');
exit;
