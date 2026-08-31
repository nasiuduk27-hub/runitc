<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath.'/config.php';
require_once BASE_PATH.'/classes/FtpStorage.php';
require_once BASE_PATH.'/models/FilingSystem.php';

header('Content-Type: application/json; charset=utf-8');

const MONITORING_CRC_MAX_FILES = 500;
const MONITORING_CRC_MAX_FILE_SIZE = 1048576;
const MONITORING_CRC_MAX_TOTAL_SIZE = 10485760;

function monitoringUploadNormalizeFiles(string $field): array
{
    if (! isset($_FILES[$field]) || ! is_array($_FILES[$field])) {
        return [];
    }

    $file = $_FILES[$field];
    if (is_array($file['name'] ?? null)) {
        $items = [];
        foreach ($file['name'] as $idx => $name) {
            $items[] = [
                'name' => $name,
                'type' => $file['type'][$idx] ?? '',
                'tmp_name' => $file['tmp_name'][$idx] ?? '',
                'error' => $file['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$idx] ?? 0,
            ];
        }

        return $items;
    }

    return [$file];
}

function monitoringUploadDateFolder(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp ? date('Ymd', $timestamp) : date('Ymd');
}

function monitoringUploadSafeCrcName(FtpStorage $ftp, string $name): string
{
    $base = $ftp->safeFileName(pathinfo(basename($name), PATHINFO_FILENAME));

    return ($base !== '' ? $base : 'CRC_'.date('His')).'.CRC';
}

function monitoringUploadViaHttpReceiver(array $files, string $adminNo, string $dateFolder, string $uploadUrl): array
{
    if (! function_exists('curl_init')) {
        throw new Exception('cURL tidak tersedia untuk upload HTTP receiver.');
    }

    $postFields = [
        'type' => 'crc_individual',
        'admin_no' => $adminNo,
        'test_date' => $dateFolder,
        'tanggal' => $dateFolder,
    ];

    foreach ($files as $idx => $file) {
        $postFields['files['.$idx.']'] = new CURLFile($file['tmp_name'], 'application/octet-stream', $file['safe_name']);
    }

    $headers = ['Accept: application/json'];
    $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    $ch = curl_init($uploadUrl);
    if (! $ch) {
        throw new Exception('Gagal initialisasi cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120)),
        CURLOPT_FAILONERROR => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new Exception('cURL error upload HTTP receiver: '.$error);
    }

    $result = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300 || ! is_array($result) || empty($result['success'])) {
        $message = is_array($result) ? ($result['message'] ?? 'Upload receiver gagal.') : substr(strip_tags((string) $response), 0, 200);
        throw new Exception('Server HTTP receiver error (HTTP '.$status.'): '.$message);
    }

    return $result;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    $adminCode = trim((string) ($_POST['admin_code'] ?? ''));
    $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
    $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);
    $itcUserId = (int) ($_SESSION['user_id'] ?? 0);

    if ($adminCode === '') {
        throw new Exception('Admin code tidak ditemukan', 400);
    }
    if ($itcUserId <= 0) {
        throw new Exception('Sesi login tidak valid', 403);
    }
    if ($adminRecId <= 0 || $subAdminId <= 0) {
        throw new Exception('Data room monitoring tidak lengkap', 400);
    }

    $stmtSpv = $pdo_run->prepare('SELECT rec_id, spv_name FROM tad_supervisor WHERE itc_usr_id = ? LIMIT 1');
    $stmtSpv->execute([$itcUserId]);
    $spv = $stmtSpv->fetch(PDO::FETCH_ASSOC) ?: [];
    $spvRecId = (int) ($spv['rec_id'] ?? 0);

    if ($spvRecId <= 0) {
        throw new Exception('Akses SPV tidak ditemukan', 403);
    }

    $stmtAccess = $pdo_war->prepare('SELECT COUNT(*) FROM t3sT5ub4dm1n WHERE rec_id = ? AND admin_id = ? AND spv_recid = ?');
    $stmtAccess->execute([$subAdminId, $adminRecId, $spvRecId]);
    if ((int) $stmtAccess->fetchColumn() <= 0) {
        throw new Exception('Anda tidak ditugaskan untuk room monitoring ini', 403);
    }

    $ftp = new FtpStorage($ftp_config);
    $safeAdminNo = $ftp->safeFileName($adminCode);
    $date = trim((string) ($_POST['date'] ?? date('Y-m-d')));
    $dateFolder = monitoringUploadDateFolder($date);
    $files = monitoringUploadNormalizeFiles('crc_files');
    if (empty($files)) {
        $files = monitoringUploadNormalizeFiles('crc_file');
    }
    if (empty($files)) {
        throw new Exception('File CRC tidak dipilih', 400);
    }

    if (count($files) > MONITORING_CRC_MAX_FILES) {
        throw new Exception('Maksimal '.MONITORING_CRC_MAX_FILES.' file .CRC sekali upload.', 400);
    }

    $totalSize = 0;
    foreach ($files as $file) {
        $totalSize += (int) ($file['size'] ?? 0);
    }
    if ($totalSize > MONITORING_CRC_MAX_TOTAL_SIZE) {
        throw new Exception('Total ukuran file CRC maksimal 10MB sekali upload.', 400);
    }

    $validFiles = [];
    $errors = [];
    foreach ($files as $file) {
        $originalName = basename((string) ($file['name'] ?? ''));
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = ['file_name' => $originalName, 'message' => 'Upload error: '.($file['error'] ?? 'unknown')];

            continue;
        }
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'crc') {
            $errors[] = ['file_name' => $originalName, 'message' => 'Format file harus .CRC'];

            continue;
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > MONITORING_CRC_MAX_FILE_SIZE) {
            $errors[] = ['file_name' => $originalName, 'message' => 'File kosong atau lebih dari 1MB'];

            continue;
        }
        if (empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            $errors[] = ['file_name' => $originalName, 'message' => 'File upload tidak valid'];

            continue;
        }

        $file['safe_name'] = monitoringUploadSafeCrcName($ftp, $originalName);
        $validFiles[] = $file;
    }

    $uploaded = [];
    $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
    if (! empty($validFiles) && $httpUploadUrl !== '') {
        $receiverResult = monitoringUploadViaHttpReceiver($validFiles, $safeAdminNo, $dateFolder, $httpUploadUrl);
        $uploaded = $receiverResult['files'] ?? [];
        foreach (($receiverResult['errors'] ?? []) as $error) {
            $errors[] = $error;
        }
    } elseif (! empty($validFiles)) {
        foreach ($validFiles as $file) {
            $remotePath = $ftp->normalizePath($safeAdminNo.'/'.$dateFolder.'/CRC/'.$file['safe_name']);
            try {
                $ftp->upload($file['tmp_name'], $remotePath);
                $uploaded[] = [
                    'file_name' => $file['safe_name'],
                    'file_type' => 'crc',
                    'file_category' => 'crc_individual',
                    'file_path' => $remotePath,
                    'relative_path' => $dateFolder.'/CRC/'.$file['safe_name'],
                ];
            } catch (Throwable $e) {
                $errors[] = ['file_name' => $file['safe_name'], 'message' => $e->getMessage()];
            }
        }
        $ftp->close();
    }

    if (! empty($uploaded)) {
        $filing = new FilingSystem($pdo, $pdo_run, $ftp_config);
        $filing->touchFilingActivity([
            'admin_no' => $safeAdminNo,
            'admin_rec_id' => $adminRecId,
            'sub_admin_id' => $subAdminId,
            'tanggal' => $date,
            'keterangan' => 'Upload CRC Manual',
            'input_by' => $_SESSION['account_nm'] ?? $_SESSION['user_name'] ?? 'System',
            'spv_name' => $spv['spv_name'] ?? ($_SESSION['account_nm'] ?? 'System'),
        ]);
    }

    http_response_code(empty($uploaded) ? 400 : 200);
    echo json_encode([
        'success' => ! empty($uploaded),
        'message' => count($uploaded).' file CRC berhasil diupload'.(! empty($errors) ? ', '.count($errors).' gagal.' : '.'),
        'uploaded_count' => count($uploaded),
        'failed_count' => count($errors),
        'files' => $uploaded,
        'errors' => $errors,
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true,
    ]);
}
