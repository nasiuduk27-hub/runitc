<?php

require_once BASE_PATH . '/models/FilingSystemRecord.php';
require_once BASE_PATH . '/classes/FtpStorage.php';
require_once BASE_PATH . '/includes/tad_access.php';

class FilingSystemController
{
    private FilingSystemRecord $model;
    private FtpStorage $ftp;
    private PDO $pdoRun;
    private array $clientMap = [];

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar, array $ftpConfig)
    {
        $this->pdoRun = $pdoRun;
        $this->model = new FilingSystemRecord($pdo, $pdoRun, $pdoWar);
        $this->ftp = new FtpStorage($ftpConfig);
    }

    public function handle(): array
    {
        $this->model->ensureTablesExist();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
            $this->handleFileAction();
        }

        if (isset($_GET['download_file'])) {
            $this->downloadFile((int) $_GET['download_file']);
        }

        if (isset($_GET['download_admin_folder'])) {
            $this->downloadAdminFolder((int) $_GET['download_admin_folder']);
        }

        if (isset($_GET['download_outbound'])) {
            $this->downloadOutboundFile((int) $_GET['download_outbound'], (string) ($_GET['file'] ?? ''));
        }

        if (isset($_GET['download_crc_raw'])) {
            $this->downloadCrcRawFile((int) $_GET['download_crc_raw'], (string) ($_GET['file'] ?? ''));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_input'])) {
            $this->saveInput();
        }

        if (isset($_GET['download_issues'])) {
            $this->downloadIssues((int) $_GET['download_issues']);
        }

        if (isset($_GET['delete_entry'])) {
            $this->deleteEntry((int) $_GET['delete_entry']);
        }

        if (isset($_GET['ajax_admin_info'])) {
            $this->ajaxAdminInfo();
        }

        if (isset($_GET['ajax_get_entry'])) {
            $this->ajaxGetEntry((int) $_GET['ajax_get_entry']);
        }

        if (isset($_GET['ajax_get_submenu'])) {
            $this->ajaxGetSubmenu((int) $_GET['ajax_get_submenu']);
        }

        if (isset($_GET['debug_outbound'])) {
            $this->debugOutbound();
        }

        return $this->getPageData();
    }

    private function getSupervisorData(int $userId): array
    {
        if ($userId <= 0) return ['id' => null, 'name' => '-'];
        $spv = $this->model->getSupervisorByUserId($userId);
        return [
            'id' => $spv ? (int)$spv['rec_id'] : null,
            'name' => $spv['spv_name'] ?? '-'
        ];
    }

    private function getPageData(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);
        
        $this->clientMap = $this->model->getClientMap();
        $adminClientMap = $this->model->getAdminClientMap($this->clientMap);
        $this->model->ensureFilingRecordsFromAssignments();

        $filters = [
            'search' => $_GET['search'] ?? '',
            'client' => $_GET['f_client'] ?? '',
            'spv'    => $_GET['f_spv'] ?? '',
            'date'   => $_GET['f_date'] ?? '',
            'admin'  => $_GET['f_admin'] ?? '',
        ];

        $clientList = array_unique(array_values($this->clientMap));
        sort($clientList);

        // Determine SPV filter: ADMIN/STAFF see all, SPV only sees assigned/uploaded
        $isAdminStaff = userHasTadRole($this->pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
        $isSpvOnly = !$isAdminStaff && userHasTadRole($this->pdoRun, $userId, ['TAD SPV']);

        $spvFilter = null;
        if ($isSpvOnly && $spv['id']) {
            $assignedAdmins = $this->model->getAssignedAdminsBySupervisor($spv['id']);
            $spvFilter = [
                'spv_name' => $spv['name'],
                'assigned_admin_pairs' => array_map(static function (array $row): array {
                    return [
                        'admin_id' => (int) ($row['admin_id'] ?? 0),
                        'sub_admin_id' => (string) ($row['sub_admin_id'] ?? ''),
                    ];
                }, $assignedAdmins),
            ];
        }

        return array_merge([
            'user_id' => $userId,
            'user_name' => $_SESSION['account_nm'] ?? $_SESSION['user_name'] ?? 'Guest',
            'spv_rec_id' => $spv['id'],
            'spv_name_active' => $spv['name'],
            'client_map' => $this->clientMap,
            'admin_client_map' => $adminClientMap,
            'assigned_admins' => $spv['id'] ? $this->model->getAssignedAdminsBySupervisor($spv['id']) : [],
            'client_list' => $clientList,
            'data_list' => $this->model->getFilteredFilingData($filters, $adminClientMap, $spvFilter),
        ], $filters);
    }

    private function saveInput(): void
    {
        if (!$this->canManageBeritaAcara()) {
            http_response_code(403);
            die('TAD SPV tidak memiliki akses untuk mengedit data Berita Acara.');
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);
        
        $nomorAdminRaw = $_POST['nomor_admin'] ?? '';
        $adminId = 0;
        $subAdminId = '';
        $nomorAdmin = $nomorAdminRaw;

        if (str_contains($nomorAdminRaw, '|')) {
            [$adminId, $subAdminId] = explode('|', $nomorAdminRaw);
            $adminId = (int)$adminId;
            $nomorAdmin = $this->model->getAdminNoById($adminId) ?: $nomorAdminRaw;
        }

        $issues = [];
        if (!empty($_POST['participant_checked'])) {
            foreach ($_POST['participant_checked'] as $authId) {
                $issues[] = [
                    'authorize_id' => $authId,
                    'participant_name' => $_POST['participant_name'][$authId] ?? '',
                    'issue_text' => $_POST['issue_text'][$authId] ?? '',
                ];
            }
        }

        $this->model->saveFiling([
            'rec_id' => (int) ($_POST['rec_id'] ?? 0),
            'nomor_admin' => $nomorAdmin,
            'sub_admin_id' => $subAdminId,
            'admin_id' => $adminId,
            'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
            'keterangan' => $_POST['keterangan'] ?? '',
            'input_by' => $_SESSION['account_nm'] ?? $_SESSION['user_name'] ?? 'Guest',
            'spv_name' => $spv['name'],
            'issues' => $issues,
        ]);

        header("Location: main.php?status=success");
        exit;
    }


    private function handleFileAction(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    register_shutdown_function(function () {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'status' => 'error',
                'msg' => 'Fatal error upload: ' . $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line'],
            ]);
        }
    });

    try {
        $action = $_POST['file_action'] ?? '';

        if ($action === 'upload' && !$this->canUploadBeritaAcaraFiles()) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'msg' => 'Anda tidak memiliki akses untuk upload file Berita Acara.'
            ]);
            exit;
        }

        if ($action === 'delete' && !$this->canManageBeritaAcara()) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'msg' => 'TAD SPV tidak memiliki akses untuk mengubah file Berita Acara.'
            ]);
            exit;
        }

        if ($action === 'upload') {
            $this->uploadFile();
            exit;
        }

        if ($action === 'list') {
            $filingId = (int) ($_POST['filing_id'] ?? 0);
            echo json_encode($this->model->getFilesByFilingId($filingId));
            exit;
        }

        if ($action === 'delete') {
            $this->deleteFile();
            exit;
        }

        echo json_encode([
            'status' => 'error',
            'msg' => 'File action tidak dikenal.'
        ]);
        exit;

    } catch (Throwable $e) {
        error_log('[FILING_ACTION_ERROR] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'msg' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);
        exit;
    }
}

    private function uploadFile(): void
    {
        $maxExecutionTime = $this->ftp->getUploadTimeout() + 20;
        @set_time_limit($maxExecutionTime);
        @ini_set('max_execution_time', (string) $maxExecutionTime);

        $filingId = (int) ($_POST['filing_id'] ?? 0);
        $nomorAdmin = trim($_POST['nomor_admin'] ?? '');
        $customName = trim($_POST['custom_file_name'] ?? '');
        $fileCategory = trim($_POST['file_category'] ?? '');

        if ($filingId <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Filing ID tidak valid.']);
            exit;
        }

        if ($nomorAdmin === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Nomor Admin tidak valid.']);
            exit;
        }

        $nomorAdmin = $this->ftp->safeFileName($nomorAdmin);

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            echo json_encode(['status' => 'error', 'msg' => 'File belum dipilih.']);
            exit;
        }

        $tmpName = $_FILES['file']['tmp_name'] ?? '';
        $fileName = $_FILES['file']['name'] ?? '';
        $fileError = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError !== UPLOAD_ERR_OK) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'Upload file ke server lokal gagal: ' . $this->getUploadErrorMessage($fileError)
            ]);
            exit;
        }

        if (!is_uploaded_file($tmpName)) {
            echo json_encode(['status' => 'error', 'msg' => 'File bukan hasil upload valid.']);
            exit;
        }

        if (!is_readable($tmpName)) {
            echo json_encode(['status' => 'error', 'msg' => 'File temporary tidak bisa dibaca.']);
            exit;
        }

        $fileSize = filesize($tmpName);

        if ($fileSize === false || $fileSize <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'File kosong atau gagal terbaca.']);
            exit;
        }

        $originalName = basename($fileName);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === '') {
            echo json_encode(['status' => 'error', 'msg' => 'File harus memiliki ekstensi.']);
            exit;
        }

        // Block dangerous extensions strictly
        $blockedExt = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'exe', 'bat', 'cmd', 'com', 'scr',
            'sh', 'bash', 'cgi', 'pl', 'py',
            'js', 'html', 'htm', 'htaccess'
        ];

        if (in_array($ext, $blockedExt, true)) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'Tipe file ".' . $ext . '" tidak diizinkan.'
            ]);
            exit;
        }

        $dateCode = date('Ymd');
        $timeCode = date('His');

        $displayName = $customName !== ''
            ? $customName
            : pathinfo($originalName, PATHINFO_FILENAME);

        $cleanDisplayName = $this->ftp->safeFileName($displayName);

        // Name format: YYYYMMDD_HHMMSS_NomorAdmin_NamaFile.ext
        $newName = $dateCode . '_' . $timeCode . '_' . $nomorAdmin . '_' . $cleanDisplayName . '.' . $ext;

        $ftpFolder = $nomorAdmin . '/' . $dateCode . '/Documents';
        $ftpFilePath = $this->ftp->normalizePath($ftpFolder . '/' . $newName);
        $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $useHttpUpload = $httpUploadUrl !== '';

        try {
            error_log('[FILING_UPLOAD_START] local=' . $tmpName . ' size=' . $fileSize . ' remote=' . $ftpFilePath);

            if ($useHttpUpload) {
                $this->uploadFileViaHttpReceiver(
                    $tmpName,
                    $nomorAdmin,
                    $newName,
                    $fileSize,
                    $this->getHttpReceiverType($fileCategory, $ext),
                    $httpUploadUrl,
                    $dateCode
                );
            } else {
                $this->ftp->upload($tmpName, $ftpFilePath);
            }

            $this->ftp->close();

            // Insert metadata into database
            $this->model->insertFile([
                'filing_id' => $filingId,
                'file_name' => $newName,
                'file_path' => $ftpFilePath,
                'file_type' => $ext,
                'file_category' => $fileCategory,
            ]);

            echo json_encode([
                'status' => 'success',
                'msg' => 'File berhasil diupload.',
                'file_name' => $newName,
                'file_path' => $ftpFilePath,
                'size' => $fileSize
            ]);
            exit;

        } catch (Throwable $e) {
    $this->ftp->close();

    error_log('[FILING_UPLOAD_ERROR] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'msg' => 'Upload gagal: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
    exit;
}
    }

    private function getHttpReceiverType(string $fileCategory, string $ext): string
    {
        if ($fileCategory === 'berita_acara' && $ext === 'pdf') {
            return 'berita_acara';
        }

        if ($fileCategory === 'crc_gabungan' && $ext === 'zip') {
            return 'crc';
        }

        return 'filing';
    }

    private function uploadFileViaHttpReceiver(string $tmpName, string $nomorAdmin, string $fileName, int $fileSize, string $type, string $uploadUrl, string $uploadDateFolder): void
    {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL tidak tersedia untuk upload via HTTP receiver.');
        }

        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));
        $timeout = max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120));
        $mimeType = $type === 'berita_acara' ? 'application/pdf' : 'application/zip';

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpName);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($uploadUrl);
        if (!$ch) {
            throw new Exception('Gagal initialisasi cURL untuk upload HTTP receiver.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'type' => $type,
                'admin_no' => $nomorAdmin,
                'test_date' => $uploadDateFolder,
                'tanggal' => $uploadDateFolder,
                'file_size' => (string) $fileSize,
                'file' => new CURLFile($tmpName, $mimeType, $fileName),
            ],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
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
            throw new Exception('cURL error upload HTTP receiver: ' . $error);
        }

        $result = null;
        if (is_string($response) && trim($response) !== '') {
            $result = json_decode($response, true);
        }

        if ($status < 200 || $status >= 300 || !is_array($result) || empty($result['success'])) {
            $message = is_array($result) && isset($result['message'])
                ? $result['message']
                : substr(trim(strip_tags((string) $response)), 0, 200);
            throw new Exception('Server HTTP receiver error (HTTP ' . $status . '): ' . $message);
        }
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'File tidak dipilih',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension',
            default => 'Error code: ' . $errorCode
        };
    }

    private function deleteFile(): void
    {
        $fileId = (int) ($_POST['file_id'] ?? 0);

        $file = $this->model->getFileById($fileId);

        if (!$file) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'File tidak ditemukan di database.'
            ]);
            exit;
        }

        try {
            $filePath = $file['file_path'];

            // Check if file actually exists on FTP
            if ($this->ftp->exists($filePath)) {
                // Delete from FTP first
                $deleted = $this->ftp->delete($filePath);
                if (!$deleted) {
                    throw new Exception("Server FTP menolak permintaan penghapusan file.");
                }
            } else {
                // File already missing from FTP (orphan record), log it and allow DB delete
                error_log('[FILING_DELETE_ORPHAN] File is missing on FTP but exists in DB: ' . $filePath);
            }

            $this->ftp->close();

            // Only delete from DB if FTP deletion succeeded or file was already missing
            $this->model->deleteFileById($fileId);

            echo json_encode(['status' => 'success']);
            exit;

        } catch (Throwable $e) {
            $this->ftp->close();

            error_log('[FILING_DELETE_ERROR] ' . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'msg' => 'Gagal menghapus file dari FTP: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    private function downloadFile(int $fileId): void
    {
        $file = $this->model->getFileById($fileId);

        if (!$file) {
            http_response_code(404);
            die('File tidak ditemukan di database.');
        }

        $tempDir = BASE_PATH . '/storage/uploads';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'ftp_dl_');

        if ($tmpFile === false) {
            http_response_code(500);
            die('Gagal membuat file temporary lokal.');
        }

        try {
            // Download FTP file to local temp path
            $this->ftp->download($file['file_path'], $tmpFile);
            $this->ftp->close();

            if (!file_exists($tmpFile)) {
                http_response_code(500);
                die('Gagal mengambil file dari FTP storage: file tidak ada.');
            }

            $fileSize = filesize($tmpFile);
            if ($fileSize === false || $fileSize <= 0) {
                @unlink($tmpFile);
                http_response_code(500);
                die('Gagal mengambil file dari FTP storage: file kosong.');
            }

            // Clear all output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Send headers to browser
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $fileSize);

            $sent = @readfile($tmpFile);

            if ($sent === false) {
                @unlink($tmpFile);
                http_response_code(500);
                die('Gagal mengirim file ke browser.');
            }

            // Strictly unlink the temporary file
            @unlink($tmpFile);
            exit;

        } catch (Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $this->ftp->close();

            /*
             * Fallback khusus deployment Synology -> aaPanel:
             * upload/delete via FTP bisa berhasil, tetapi download via ftp_get kadang gagal
             * karena masalah data connection FTP. Karena file berada di web root aaPanel,
             * browser bisa diarahkan langsung ke URL publik file tersebut.
             */
            $publicUrl = $this->buildPublicFileUrl((string) ($file['file_path'] ?? ''));

            if ($publicUrl !== null) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Location: ' . $publicUrl);
                exit;
            }

            http_response_code(500);
            die('Error download: ' . $e->getMessage());
        }
    }

    private function downloadAdminFolder(int $filingId): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (!$filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            die('Data filing tidak ditemukan.');
        }

        try {
            $this->proxyReceiverBinary('download_zip', (string) $filing['nomor_admin']);
        } catch (Throwable $e) {
            http_response_code(500);
            die('Gagal download folder admin: ' . $e->getMessage());
        }
    }

    private function downloadOutboundFile(int $filingId, string $fileName): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (!$filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            die('Data filing tidak ditemukan.');
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (!$this->isValidOutboundFileName((string) $filing['nomor_admin'], $fileName)) {
            http_response_code(400);
            die('Nama file outbound tidak valid.');
        }

        $remotePath = $this->getOutboundFolder() . '/' . $fileName;
        $tempDir = BASE_PATH . '/storage/uploads';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'outbound_');
        if ($tmpFile === false) {
            http_response_code(500);
            die('Gagal membuat file temporary lokal.');
        }

        try {
            $this->ftp->download($remotePath, $tmpFile);
            $this->ftp->close();

            $fileSize = file_exists($tmpFile) ? filesize($tmpFile) : 0;
            if ($fileSize === false || $fileSize <= 0) {
                throw new Exception('File outbound kosong atau gagal terbaca.');
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($tmpFile);
            @unlink($tmpFile);
            exit;
        } catch (Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $this->ftp->close();

            if ($this->proxyOutboundReceiverDownload((string) $filing['nomor_admin'], $fileName)) {
                exit;
            }

            $publicUrl = $this->buildOutboundPublicUrl($fileName);
            if ($publicUrl !== null) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: ' . $publicUrl);
                exit;
            }

            http_response_code(500);
            die('Gagal download outbound: ' . $e->getMessage());
        }
    }

    private function downloadCrcRawFile(int $filingId, string $fileName): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (!$filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            die('Data filing tidak ditemukan.');
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (!$this->isValidCrcRawFileName($fileName)) {
            http_response_code(400);
            die('Nama file CRC RAW tidak valid.');
        }

        $adminNo = $this->ftp->safeFileName((string) $filing['nomor_admin']);
        $remotePath = $adminNo . '/' . $fileName;
        $publicUrl = $this->buildPublicFileUrl($remotePath);

        if ($publicUrl !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Location: ' . $publicUrl);
            exit;
        }

        http_response_code(500);
        die('Gagal membuat URL download CRC RAW.');
    }

    private function fetchReceiverFileList(string $adminNo): ?array
    {
        $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));

        if ($uploadUrl === '' || !function_exists('curl_init')) {
            return null;
        }

        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($uploadUrl);
        if (!$ch) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => 'list',
                'admin_no' => $this->ftp->safeFileName($adminNo),
            ],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120)),
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300 || !is_string($response)) {
            return null;
        }

        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['success']) || !isset($result['files_by_category'])) {
            return null;
        }

        return $result['files_by_category'];
    }

    private function proxyReceiverBinary(string $action, string $adminNo): void
    {
        $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));

        if ($uploadUrl === '' || !function_exists('curl_init')) {
            throw new Exception('Konfigurasi HTTP receiver belum tersedia.');
        }

        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($uploadUrl);
        if (!$ch) {
            throw new Exception('Gagal initialisasi cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => $action,
                'admin_no' => $this->ftp->safeFileName($adminNo),
            ],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(60, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120)),
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || !is_string($response)) {
            throw new Exception($error ?: 'Receiver tidak merespons.');
        }

        $body = substr($response, $headerSize);

        if ($status < 200 || $status >= 300) {
            $json = json_decode($body, true);
            $message = is_array($json) && isset($json['message']) ? $json['message'] : substr(strip_tags($body), 0, 200);
            throw new Exception($message);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeAdminNo . '.zip"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $body;
        exit;
    }

    private function buildPublicFileUrl(string $filePath): ?string
    {
        $baseUrl = trim((string) env('FTP_PUBLIC_BASE_URL', ''));

        if ($baseUrl === '') {
            // Sesuai document root aaPanel: /www/wwwroot/cbt.toeic.or.id/docs/CBT/CRC
            $baseUrl = 'https://cbt.toeic.or.id/docs/CBT/CRC';
        }

        $filePath = trim($filePath);
        $filePath = str_replace('\\', '/', $filePath);
        $filePath = preg_replace('#/+#', '/', $filePath);
        $filePath = trim($filePath, '/');

        if ($filePath === '') {
            return null;
        }

        if (preg_match('#^[^/]+/.+\.(pdf|zip|crc|xlsx|doc|docx|jpg|jpeg|png|rar)$#i', $filePath)) {
            $httpBaseUrl = trim((string) env('CRC_HTTP_PUBLIC_BASE_URL', ''));
            if ($httpBaseUrl === '') {
                $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
                $httpBaseUrl = $uploadUrl !== '' ? preg_replace('#/[^/]*$#', '', $uploadUrl) : 'https://cbt.toeic.or.id/docs/CBT/CRC';
            }

            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));

            return rtrim($httpBaseUrl, '/') . '/' . $encodedPath;
        }

        // Database bisa menyimpan CRC/filing_system/...,
        // sedangkan base URL sudah sampai folder CRC.
        if (strpos($filePath, 'CRC/') === 0) {
            $filePath = substr($filePath, 4);
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));

        return rtrim($baseUrl, '/') . '/' . $encodedPath;
    }

    private function enrichFilesWithPublicUrls(array $filesByCategory): array
    {
        foreach ($filesByCategory as $category => $files) {
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $index => $file) {
                if (!is_array($file)) {
                    continue;
                }

                $path = (string) ($file['file_path'] ?? '');
                if ($path !== '') {
                    $filesByCategory[$category][$index]['relative_path'] = ltrim(str_replace('\\', '/', $path), '/');
                    $filesByCategory[$category][$index]['download_url'] = $this->buildPublicFileUrl($path);
                }
            }
        }

        return $filesByCategory;
    }

    private function buildOutboundPublicUrl(string $fileName): ?string
    {
        $baseUrl = trim((string) env('OUTBOUND_PUBLIC_BASE_URL', ''));
        if ($baseUrl === '') {
            $baseUrl = 'https://cbt.toeic.or.id/docs/CBT/OUTBOUND';
        }

        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($fileName === '') {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . rawurlencode($fileName);
    }

    private function getOutboundFolder(): string
    {
        $folder = trim((string) env('OUTBOUND_FTP_PATH', 'OUTBOUND'));
        $folder = $folder !== '' ? $folder : 'OUTBOUND';

        return str_starts_with($folder, '/') ? '/' . trim($folder, '/') : trim($folder, '/');
    }

    private function isValidOutboundFileName(string $adminNo, string $fileName): bool
    {
        $adminNo = trim($adminNo);
        $fileName = basename(str_replace('\\', '/', $fileName));

        if ($adminNo === '' || $fileName === '') {
            return false;
        }

        $pattern = '/^' . preg_quote($adminNo, '/') . '-.*-DATA\.ZIP$/i';
        return preg_match($pattern, $fileName) === 1;
    }

    private function getOutboundFilesForFiling(array $filing): array
    {
        $adminNo = trim((string) ($filing['nomor_admin'] ?? ''));
        $filingId = (int) ($filing['rec_id'] ?? 0);

        if ($adminNo === '' || $filingId <= 0) {
            return [];
        }

        try {
            $fileNames = $this->ftp->listFiles($this->getOutboundFolder());
            $this->ftp->close();
        } catch (Throwable $e) {
            $this->ftp->close();
            error_log('[OUTBOUND_LIST_ERROR] ' . $e->getMessage());
            $fileNames = [];
        }

        if (empty($fileNames)) {
            $fileNames = $this->fetchOutboundFileListViaHttp();
        }

        $receiverFiles = [];
        if (empty($fileNames)) {
            $receiverFiles = $this->fetchOutboundFilesViaReceiver($adminNo, $filingId);
        }

        if (!empty($receiverFiles)) {
            return $receiverFiles;
        }

        $files = [];
        foreach ($fileNames as $fileName) {
            if (!$this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'zip',
                'file_category' => 'outbound',
                'uploaded_at' => '',
                'download_url' => 'berita_acara.php?download_outbound=' . $filingId . '&file=' . rawurlencode($fileName),
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return strcasecmp($a['file_name'], $b['file_name']);
        });

        return $files;
    }

    private function isValidCrcRawFileName(string $fileName): bool
    {
        $fileName = basename(str_replace('\\', '/', $fileName));

        if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return false;
        }

        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'crc';
    }

    private function getCrcRawFilesForFiling(array $filing): array
    {
        $adminNo = trim((string) ($filing['nomor_admin'] ?? ''));
        $filingId = (int) ($filing['rec_id'] ?? 0);

        if ($adminNo === '' || $filingId <= 0) {
            return [];
        }

        $safeAdminNo = $this->ftp->safeFileName($adminNo);

        try {
            $fileNames = $this->ftp->listFiles($safeAdminNo);
            $this->ftp->close();
        } catch (Throwable $e) {
            $this->ftp->close();
            error_log('[CRC_RAW_LIST_ERROR] ' . $e->getMessage());
            $fileNames = [];
        }

        $files = [];
        foreach ($fileNames as $fileName) {
            $fileName = basename(str_replace('\\', '/', (string) $fileName));
            if (!$this->isValidCrcRawFileName($fileName)) {
                continue;
            }

            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'crc',
                'file_category' => 'crc_raw',
                'uploaded_at' => '',
                'download_url' => 'berita_acara.php?download_crc_raw=' . $filingId . '&file=' . rawurlencode($fileName),
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return strcasecmp($a['file_name'], $b['file_name']);
        });

        return $files;
    }

    private function normalizeCrcRawCategory(array $filesByCategory): array
    {
        $crcRawFiles = $filesByCategory['crc_raw'] ?? [];

        foreach ($filesByCategory as $category => $files) {
            if (!is_array($files) || $category === 'crc_raw') {
                continue;
            }

            $remainingFiles = [];
            foreach ($files as $file) {
                $fileName = (string) ($file['file_name'] ?? '');
                $relativePath = trim(str_replace('\\', '/', (string) ($file['relative_path'] ?? $file['file_path'] ?? '')), '/');
                $fileType = strtolower((string) ($file['file_type'] ?? pathinfo($fileName, PATHINFO_EXTENSION)));

                if (preg_match('#(^|/)(GrabCRC|[0-9]{8})/CRC/[^/]+\.CRC$#i', $relativePath)) {
                    $file['file_type'] = 'crc';
                    $file['file_category'] = 'crc_individual';
                    if ($category === 'crc_individual') {
                        $remainingFiles[] = $file;
                        continue;
                    }

                    $filesByCategory['crc_individual'][] = $file;
                    continue;
                }

                if (preg_match('#(^|/)(GrabCRC|[0-9]{8})/Rev-[^/]+\.CRC$#i', $relativePath)) {
                    $file['file_type'] = 'crc';
                    $file['file_category'] = 'crc_gabungan';
                    if ($category === 'crc_gabungan') {
                        $remainingFiles[] = $file;
                        continue;
                    }

                    $filesByCategory['crc_gabungan'][] = $file;
                    continue;
                }

                if (($fileType === 'crc' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'crc') && strpos($relativePath, '/') === false) {
                    $file['file_type'] = 'crc';
                    $file['file_category'] = 'crc_raw';
                    $crcRawFiles[] = $file;
                    continue;
                }

                $remainingFiles[] = $file;
            }

            $filesByCategory[$category] = $remainingFiles;
        }

        $unique = [];
        foreach ($crcRawFiles as $file) {
            $key = strtolower((string) ($file['download_url'] ?? $file['file_path'] ?? $file['file_name'] ?? ''));
            if ($key === '') {
                $key = uniqid('crc_raw_', true);
            }
            $unique[$key] = $file;
        }

        $filesByCategory['crc_raw'] = array_values($unique);

        return $filesByCategory;
    }

    private function fetchOutboundFilesViaReceiver(string $adminNo, int $filingId = 0): array
    {
        $result = $this->fetchOutboundReceiverPayload($adminNo, $filingId);

        return $result['files'] ?? [];
    }

    private function fetchOutboundReceiverPayload(string $adminNo, int $filingId = 0): array
    {
        $receiverUrl = trim((string) env('OUTBOUND_RECEIVER_URL', 'https://cbt.toeic.or.id/docs/CBT/CRC/outbound_receiver.php'));
        if ($receiverUrl === '' || !function_exists('curl_init')) {
            return [
                'ok' => false,
                'url' => $receiverUrl,
                'status' => 0,
                'error' => 'Receiver URL kosong atau cURL tidak tersedia.',
                'files' => [],
            ];
        }

        $token = trim((string) env('OUTBOUND_RECEIVER_TOKEN', env('CRC_HTTP_UPLOAD_TOKEN', 'annas123')));
        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($receiverUrl);
        if (!$ch) {
            return [
                'ok' => false,
                'url' => $receiverUrl,
                'status' => 0,
                'error' => 'Gagal initialisasi cURL.',
                'files' => [],
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => 'list',
                'admin_no' => $this->ftp->safeFileName($adminNo),
            ],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 300 || !is_string($response)) {
            error_log('[OUTBOUND_RECEIVER_LIST_ERROR] status=' . $status . ' errno=' . $errno . ' error=' . $error);
            return [
                'ok' => false,
                'url' => $receiverUrl,
                'status' => $status,
                'errno' => $errno,
                'error' => $error,
                'response_sample' => is_string($response) ? substr(strip_tags($response), 0, 500) : '',
                'files' => [],
            ];
        }

        $result = json_decode($response, true);
        if (!is_array($result) || empty($result['success']) || !isset($result['files']) || !is_array($result['files'])) {
            error_log('[OUTBOUND_RECEIVER_LIST_INVALID] ' . substr((string) $response, 0, 300));
            return [
                'ok' => false,
                'url' => $receiverUrl,
                'status' => $status,
                'error' => 'Response receiver tidak valid atau success=false.',
                'response_sample' => substr((string) $response, 0, 500),
                'files' => [],
            ];
        }

        $files = [];
        foreach ($result['files'] as $file) {
            $fileName = (string) ($file['file_name'] ?? '');
            if (!$this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $file['file_id'] = null;
            $file['file_type'] = 'zip';
            $file['file_category'] = 'outbound';
            if ($filingId > 0) {
                $file['download_url'] = 'berita_acara.php?download_outbound=' . $filingId . '&file=' . rawurlencode($fileName);
            }
            $files[] = $file;
        }

        return [
            'ok' => true,
            'url' => $receiverUrl,
            'status' => $status,
            'raw_count' => count($result['files']),
            'files' => $files,
        ];
    }

    private function proxyOutboundReceiverDownload(string $adminNo, string $fileName): bool
    {
        $receiverUrl = trim((string) env('OUTBOUND_RECEIVER_URL', 'https://cbt.toeic.or.id/docs/CBT/CRC/outbound_receiver.php'));
        if ($receiverUrl === '' || !function_exists('curl_init')) {
            return false;
        }

        $token = trim((string) env('OUTBOUND_RECEIVER_TOKEN', env('CRC_HTTP_UPLOAD_TOKEN', 'annas123')));
        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($receiverUrl);
        if (!$ch) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => 'download',
                'admin_no' => $this->ftp->safeFileName($adminNo),
                'file' => $fileName,
            ],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 300 || !is_string($response)) {
            error_log('[OUTBOUND_RECEIVER_DOWNLOAD_ERROR] status=' . $status . ' errno=' . $errno . ' error=' . $error);
            return false;
        }

        $body = substr($response, $headerSize);
        if ($body === '') {
            return false;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $body;

        return true;
    }

    private function fetchOutboundFileListViaHttp(): array
    {
        $baseUrl = trim((string) env('OUTBOUND_PUBLIC_BASE_URL', ''));
        if ($baseUrl === '') {
            $baseUrl = 'https://cbt.toeic.or.id/docs/CBT/OUTBOUND';
        }

        $url = rtrim($baseUrl, '/') . '/';
        $html = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_FAILONERROR => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'RUN-ITC-FilingSystem/1.0',
                ]);
                $html = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($status < 200 || $status >= 300) {
                    $html = false;
                }
            }
        }

        if (!is_string($html) || trim($html) === '') {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'header' => "User-Agent: RUN-ITC-FilingSystem/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $html = @file_get_contents($url, false, $context);
        }

        if (!is_string($html) || trim($html) === '') {
            return [];
        }

        $files = [];
        if (preg_match_all('/href=["\']([^"\']+\.zip)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $path = parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5), PHP_URL_PATH);
                $fileName = basename(rawurldecode($path ?: $href));
                if ($fileName !== '' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'zip') {
                    $files[] = $fileName;
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function debugOutbound(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $adminNo = trim((string) ($_GET['admin'] ?? ''));

        try {
            $diagnostics = $this->ftp->listFilesWithDiagnostics($this->getOutboundFolder());
            $this->ftp->close();
            $httpFiles = empty($diagnostics['files'] ?? []) ? $this->fetchOutboundFileListViaHttp() : [];
            $receiverPayload = empty($diagnostics['files'] ?? []) && empty($httpFiles)
                ? $this->fetchOutboundReceiverPayload($adminNo)
                : ['ok' => false, 'files' => [], 'skipped' => true];
            $receiverFiles = $receiverPayload['files'] ?? [];
            $receiverFileNames = array_map(static function (array $file): string {
                return (string) ($file['file_name'] ?? '');
            }, $receiverFiles);
            $allFiles = array_values(array_unique(array_merge($diagnostics['files'] ?? [], $httpFiles, $receiverFileNames)));

            $matches = [];
            foreach ($allFiles as $fileName) {
                if ($adminNo !== '' && $this->isValidOutboundFileName($adminNo, (string) $fileName)) {
                    $matches[] = $fileName;
                }
            }

            echo json_encode([
                'success' => true,
                'admin' => $adminNo,
                'outbound_folder' => $this->getOutboundFolder(),
                'total_files' => count($allFiles),
                'matches' => $matches,
                'http_fallback_files' => $httpFiles,
                'receiver_files' => $receiverFiles,
                'receiver_debug' => array_diff_key($receiverPayload, ['files' => true]),
                'diagnostics' => $diagnostics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            $this->ftp->close();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    private function downloadIssues(int $filingId): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (!$filing) {
            die('Data filing tidak ditemukan.');
        }

        $issues = $this->model->getIssuesByFilingId($filingId);

        $filename = 'Issue_Report_' . $filing['nomor_admin'] . '_' . date('Ymd_His') . '.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'Nomor Admin',
            'Peserta',
            'Authorize ID',
            'Issue / Berita Acara',
            'Tanggal Input'
        ]);

        foreach ($issues as $issue) {
            fputcsv($output, [
                $filing['nomor_admin'],
                $issue['participant_name'],
                $issue['authorize_id'],
                $issue['issue_text'],
                $issue['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    private function deleteEntry(int $filingId): void
    {
        if (!$this->canManageBeritaAcara()) {
            http_response_code(403);
            die('TAD SPV tidak memiliki akses untuk menghapus data Berita Acara.');
        }

        try {
            $files = $this->model->deleteEntry($filingId);

            foreach ($files as $file) {
                try {
                    $this->ftp->delete($file['file_path']);
                } catch (Exception $e) {
                    // Abaikan kalau file di FTP sudah tidak ada.
                }
            }

            $this->ftp->close();

            header("Location: main.php?status=deleted");
            exit;

        } catch (Exception $e) {
            die('Error deleting: ' . $e->getMessage());
        }
    }

    private function canManageBeritaAcara(): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        if (userHasTadRole($this->pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])) {
            return true;
        }

        return !userHasTadRole($this->pdoRun, $userId, ['TAD SPV']);
    }

    private function canUploadBeritaAcaraFiles(): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        return $this->canManageBeritaAcara()
            || userHasTadRole($this->pdoRun, $userId, ['TAD SPV']);
    }

    private function ajaxAdminInfo(): void
    {
        header('Content-Type: application/json');

        $parts = explode('|', $_GET['admin_val'] ?? '');

        if (count($parts) < 2) {
            echo json_encode(['error' => 'Invalid Selection']);
            exit;
        }

        $adminRecId = (int) $parts[0];
        $subAdminId = (int) $parts[1];
        $filingId = (int) ($_GET['filing_id'] ?? 0);

        try {
            $admin = $this->model->getAdminById($adminRecId);

            $existingIssueAuths = [];

            if ($filingId > 0) {
                $existingIssueAuths = $this->model->getIssueAuthorizeIds($filingId);
            }

            $participants = [];

            if ($admin) {
                $psyskunci = shifting(trim($admin['admin_no']), 3);

                $rows = $this->model->getParticipantsBySubAdmin($adminRecId, $subAdminId);

                foreach ($rows as $row) {
                    $stat = (string) trim($row['statrec']);
                    $isFinished = in_array($stat, ['7', '8', '9', 'c', 'C']);
                    $hasExistingIssue = in_array($row['authorize'], $existingIssueAuths);

                    if ($isFinished || $hasExistingIssue) {
                        $participants[] = [
                            'id' => $row['authorize'],
                            'name' => deccrypt(trim($row['regnm']), $psyskunci),
                            'status' => $stat,
                            'is_finished' => $isFinished
                        ];
                    }
                }
            }

            $clientMap = $this->model->getClientMap();

            echo json_encode([
                'testdate' => $admin ? date('Y-m-d', strtotime($admin['testdt'])) : '',
                'client_name' => $admin ? ($clientMap[$admin['client_id']] ?? '-') : '-',
                'participants' => $participants
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    private function ajaxGetEntry(int $id): void
    {
        header('Content-Type: application/json');

        echo json_encode($this->model->getEntryWithIssues($id));
        exit;
    }

    private function ajaxGetSubmenu(int $id): void
    {
        header('Content-Type: application/json');

        try {
            $filing = $this->model->getFilingById($id);

            $issues = [];
            $liveFinished = [];
            $filesByCategory = [];

            if ($filing) {
                $issues = $this->model->getIssuesByFilingId($id);

                $filesByCategory = $this->enrichFilesWithPublicUrls($this->model->getFilesByCategory($id));

                if (!empty($filing['nomor_admin'])) {
                    $remoteFilesByCategory = $this->fetchReceiverFileList((string) $filing['nomor_admin']);
                    if (is_array($remoteFilesByCategory)) {
                        foreach ($remoteFilesByCategory as $category => $remoteFiles) {
                            if (!isset($filesByCategory[$category]) || !is_array($filesByCategory[$category])) {
                                $filesByCategory[$category] = [];
                            }

                            if (is_array($remoteFiles)) {
                                $filesByCategory[$category] = array_merge($remoteFiles, $filesByCategory[$category]);
                            }
                        }
                    }

                    $filesByCategory['outbound'] = $this->getOutboundFilesForFiling($filing);
                    $filesByCategory['crc_raw'] = array_merge(
                        $filesByCategory['crc_raw'] ?? [],
                        $this->getCrcRawFilesForFiling($filing)
                    );
                    $filesByCategory = $this->normalizeCrcRawCategory($filesByCategory);
                }

                if (!empty($filing['nomor_admin']) && !empty($filing['sub_admin_id'])) {
                    $adminId = $this->model->getAdminIdByAdminNo($filing['nomor_admin']);

                    if ($adminId) {
                        $psyskunci = shifting(trim($filing['nomor_admin']), 3);

                        $rows = $this->model->getFinishedParticipantsBySubAdmin(
                            $adminId,
                            (int) $filing['sub_admin_id']
                        );

                        foreach ($rows as $row) {
                            $liveFinished[] = [
                                'id' => $row['authorize'],
                                'name' => deccrypt(trim($row['regnm']), $psyskunci),
                                'status' => (string) trim($row['statrec']),
                                'sisa_waktu' => 'Selesai'
                            ];
                        }
                    }
                }
            }

            echo json_encode([
                'issues' => $issues,
                'live' => $liveFinished,
                'files_by_category' => $filesByCategory,
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public function getFileIcon(string $ext): string
    {
        $ext = strtolower($ext);

        switch ($ext) {
            case 'pdf':
                return '<i class="fas fa-file-pdf text-red-500"></i>';

            case 'doc':
            case 'docx':
                return '<i class="fas fa-file-word text-blue-500"></i>';

            case 'xls':
            case 'xlsx':
            case 'csv':
                return '<i class="fas fa-file-excel text-green-600"></i>';

            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return '<i class="fas fa-file-image text-emerald-500"></i>';

            case 'zip':
            case 'rar':
            case '7z':
                return '<i class="fas fa-file-archive text-orange-400"></i>';

            default:
                return '<i class="fas fa-file text-gray-400"></i>';
        }
    }
}
