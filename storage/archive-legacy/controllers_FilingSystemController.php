<?php

require_once BASE_PATH.'/models/FilingSystemRecord.php';
require_once BASE_PATH.'/classes/FtpStorage.php';
require_once BASE_PATH.'/includes/tad_access.php';

class FilingSystemController
{
    private const CRC_INDIVIDUAL_MAX_FILES = 500;

    private const CRC_INDIVIDUAL_MAX_FILE_SIZE = 1048576;

    private const CRC_INDIVIDUAL_MAX_TOTAL_SIZE = 10485760;

    private FilingSystemRecord $model;

    private FtpStorage $ftp;

    private PDO $pdoRun;

    private ?PDO $pdoCollector;

    private array $clientMap = [];

    public function __construct(PDO $pdo, PDO $pdoRun, PDO $pdoWar, array $ftpConfig, ?PDO $pdoCollector = null)
    {
        $this->pdoRun = $pdoRun;
        $this->pdoCollector = $pdoCollector;
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

        if (isset($_GET['download_crc_b2'])) {
            $this->downloadCrcB2Zip((int) $_GET['download_crc_b2']);
        }

        if (isset($_GET['download_crc_b2_admin'])) {
            $this->downloadCrcB2ZipByAdmin((string) $_GET['download_crc_b2_admin']);
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

        if (isset($_GET['ajax_crc_b2_collect'])) {
            $this->ajaxCrcB2Collect((string) ($_GET['admin_no'] ?? ''));
        }

        if (isset($_GET['ajax_crc_b2_folders'])) {
            $this->ajaxCrcB2Folders();
        }

        if (isset($_GET['ajax_crc_b2_files'])) {
            $this->ajaxCrcB2Files((string) ($_GET['admin_no'] ?? ''));
        }

        if (isset($_GET['debug_outbound'])) {
            $this->debugOutbound();
        }

        return $this->getPageData();
    }

    private function getSupervisorData(int $userId): array
    {
        if ($userId <= 0) {
            return ['id' => null, 'name' => '-'];
        }
        $spv = $this->model->getSupervisorByUserId($userId);

        return [
            'id' => $spv ? (int) $spv['rec_id'] : null,
            'name' => $spv['spv_name'] ?? '-',
        ];
    }

    private function getPageData(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);

        $this->clientMap = $this->model->getClientMap();
        $adminClientMap = $this->model->getAdminClientMap($this->clientMap);
        if (isset($_GET['sync_assignments']) && $_GET['sync_assignments'] === '1') {
            $this->model->ensureFilingRecordsFromAssignments();
            header('Location: berita_acara');
            exit;
        }

        $filters = [
            'search' => $_GET['search'] ?? '',
            'client' => $_GET['f_client'] ?? '',
            'spv' => $_GET['f_spv'] ?? '',
            'date' => $_GET['f_date'] ?? '',
            'admin' => $_GET['f_admin'] ?? '',
        ];

        $clientList = array_unique(array_values($this->clientMap));
        sort($clientList);

        // Determine SPV filter: ADMIN/STAFF see all, SPV only sees assigned/uploaded
        $isAdminStaff = userHasTadRole($this->pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
        $isSpvOnly = ! $isAdminStaff && userHasTadRole($this->pdoRun, $userId, ['TAD SPV']);
        $assignedAdmins = [];

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
        } elseif ($isAdminStaff) {
            $assignedAdmins = $this->model->getAllAssignedAdmins();
        } elseif ($spv['id']) {
            $assignedAdmins = $this->model->getAssignedAdminsBySupervisor($spv['id']);
        }

        $queryFilters = $filters;
        $queryFilters['search'] = '';
        $dataList = $this->model->getFilteredFilingData($queryFilters, $adminClientMap, $spvFilter);
        if (empty($dataList)) {
            $dataList = $this->model->getFilingDataFromAssignments($queryFilters, $adminClientMap, $spvFilter);
        }
        $dataList = $this->filterRowsBySearch($dataList, (string) $filters['search']);

        return array_merge([
            'user_id' => $userId,
            'user_name' => $_SESSION['account_nm'] ?? $_SESSION['user_name'] ?? 'Guest',
            'spv_rec_id' => $spv['id'],
            'spv_name_active' => $spv['name'],
            'client_map' => $this->clientMap,
            'admin_client_map' => $adminClientMap,
            'assigned_admins' => $assignedAdmins,
            'client_list' => $clientList,
            'data_list' => $dataList,
        ], $filters);
    }

    private function filterRowsBySearch(array $rows, string $search): array
    {
        $search = trim(strtolower($search));
        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($search): bool {
            $haystack = strtolower(implode(' ', [
                $row['nomor_admin'] ?? '',
                $row['client_name'] ?? '',
                $row['keterangan'] ?? '',
                $row['spv_name'] ?? '',
            ]));

            return str_contains($haystack, $search);
        }));
    }

    private function saveInput(): void
    {
        if (! $this->canManageBeritaAcara()) {
            http_response_code(403);
            exit('TAD SPV tidak memiliki akses untuk mengedit data Berita Acara.');
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);

        $nomorAdminRaw = $_POST['nomor_admin'] ?? '';
        $adminId = 0;
        $subAdminId = '';
        $nomorAdmin = $nomorAdminRaw;

        if (str_contains($nomorAdminRaw, '|')) {
            [$adminId, $subAdminId] = explode('|', $nomorAdminRaw);
            $adminId = (int) $adminId;
            $nomorAdmin = $this->model->getAdminNoById($adminId) ?: $nomorAdminRaw;
        }

        $issues = [];
        if (! empty($_POST['participant_checked'])) {
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

        header('Location: main.php?status=success');
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
                    'msg' => 'Fatal error upload: '.$error['message'],
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                ]);
            }
        });

        try {
            $action = $_POST['file_action'] ?? '';

            if ($action === 'upload' && ! $this->canUploadBeritaAcaraFiles()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'msg' => 'Anda tidak memiliki akses untuk upload file Berita Acara.',
                ]);
                exit;
            }

            if ($action === 'delete' && ! $this->canManageBeritaAcara()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'msg' => 'TAD SPV tidak memiliki akses untuk mengubah file Berita Acara.',
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
                'msg' => 'File action tidak dikenal.',
            ]);
            exit;

        } catch (Throwable $e) {
            error_log('[FILING_ACTION_ERROR] '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine());

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

        if (isset($_FILES['files']) && is_array($_FILES['files']['name'] ?? null)) {
            $this->uploadFilesBulk();

            return;
        }

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

        if (! isset($_FILES['file']) || ! is_array($_FILES['file'])) {
            echo json_encode(['status' => 'error', 'msg' => 'File belum dipilih.']);
            exit;
        }

        $tmpName = $_FILES['file']['tmp_name'] ?? '';
        $fileName = $_FILES['file']['name'] ?? '';
        $fileError = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError !== UPLOAD_ERR_OK) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'Upload file ke server lokal gagal: '.$this->getUploadErrorMessage($fileError),
            ]);
            exit;
        }

        if (! is_uploaded_file($tmpName)) {
            echo json_encode(['status' => 'error', 'msg' => 'File bukan hasil upload valid.']);
            exit;
        }

        if (! is_readable($tmpName)) {
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
            'js', 'html', 'htm', 'htaccess',
        ];

        if ($fileCategory === 'crc_individual' && $ext !== 'crc') {
            echo json_encode([
                'status' => 'error',
                'msg' => 'Kategori CRC Individu hanya menerima file .CRC.',
            ]);
            exit;
        }

        if (in_array($ext, $blockedExt, true)) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'Tipe file ".'.$ext.'" tidak diizinkan.',
            ]);
            exit;
        }

        $dateCode = $fileCategory === 'crc_individual' ? $this->getFilingDateCode($filingId) : date('Ymd');
        $timeCode = date('His');

        $displayName = $customName !== ''
            ? $customName
            : pathinfo($originalName, PATHINFO_FILENAME);

        $cleanDisplayName = $this->ftp->safeFileName($displayName);

        // Name format: YYYYMMDD_HHMMSS_NomorAdmin_NamaFile.ext
        $newName = $fileCategory === 'crc_individual'
            ? ($cleanDisplayName !== '' ? $cleanDisplayName : 'CRC_'.$timeCode).'.CRC'
            : $dateCode.'_'.$timeCode.'_'.$nomorAdmin.'_'.$cleanDisplayName.'.'.$ext;

        $ftpFolder = $fileCategory === 'crc_individual'
            ? $nomorAdmin.'/'.$dateCode.'/CRC'
            : $nomorAdmin.'/'.$dateCode.'/Documents';
        $ftpFilePath = $this->ftp->normalizePath($ftpFolder.'/'.$newName);
        $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $useHttpUpload = $httpUploadUrl !== '';

        try {
            error_log('[FILING_UPLOAD_START] local='.$tmpName.' size='.$fileSize.' remote='.$ftpFilePath);

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

            if (! $this->expectsJson()) {
                $_SESSION['success_msg'] = 'File berhasil diupload: '.$newName;
                header('Location: berita_acara.php');
                exit;
            }

            echo json_encode([
                'status' => 'success',
                'msg' => 'File berhasil diupload.',
                'file_name' => $newName,
                'file_path' => $ftpFilePath,
                'size' => $fileSize,
            ]);
            exit;

        } catch (Throwable $e) {
            $this->ftp->close();

            error_log('[FILING_UPLOAD_ERROR] '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine());

            http_response_code(500);
            if (! $this->expectsJson()) {
                $_SESSION['error_msg'] = 'Upload gagal: '.$e->getMessage();
                header('Location: berita_acara.php');
                exit;
            }

            echo json_encode([
                'status' => 'error',
                'msg' => 'Upload gagal: '.$e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ]);
            exit;
        }
    }

    private function expectsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function getHttpReceiverType(string $fileCategory, string $ext): string
    {
        if ($fileCategory === 'crc_individual' && $ext === 'crc') {
            return 'crc_individual';
        }

        if ($fileCategory === 'berita_acara' && $ext === 'pdf') {
            return 'berita_acara';
        }

        if ($fileCategory === 'crc_gabungan' && $ext === 'zip') {
            return 'crc';
        }

        return 'filing';
    }

    private function uploadFilesBulk(): void
    {
        $filingId = (int) ($_POST['filing_id'] ?? 0);
        $nomorAdmin = trim($_POST['nomor_admin'] ?? '');
        $customName = trim($_POST['custom_file_name'] ?? '');
        $fileCategory = trim($_POST['file_category'] ?? '');

        if ($filingId <= 0 || $nomorAdmin === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Filing ID atau nomor admin tidak valid.']);
            exit;
        }

        $nomorAdmin = $this->ftp->safeFileName($nomorAdmin);
        $dateCode = $this->getFilingDateCode($filingId);
        $uploads = $this->normalizeBulkFiles($_FILES['files']);
        if (empty($uploads)) {
            echo json_encode(['status' => 'error', 'msg' => 'File belum dipilih.']);
            exit;
        }

        if ($fileCategory === 'crc_individual') {
            if (count($uploads) > self::CRC_INDIVIDUAL_MAX_FILES) {
                echo json_encode(['status' => 'error', 'msg' => 'Maksimal '.self::CRC_INDIVIDUAL_MAX_FILES.' file .CRC sekali upload.']);
                exit;
            }

            $totalSize = 0;
            foreach ($uploads as $upload) {
                $totalSize += (int) ($upload['size'] ?? 0);
            }
            if ($totalSize > self::CRC_INDIVIDUAL_MAX_TOTAL_SIZE) {
                echo json_encode(['status' => 'error', 'msg' => 'Total ukuran file CRC maksimal 10MB sekali upload.']);
                exit;
            }
        }

        $uploaded = [];
        $errors = [];
        $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $crcReceiverFiles = [];

        foreach ($uploads as $index => $file) {
            $originalName = basename((string) ($file['name'] ?? ''));
            $tmpName = (string) ($file['tmp_name'] ?? '');
            $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = ['file_name' => $originalName, 'message' => $this->getUploadErrorMessage($fileError)];

                continue;
            }

            if (! is_uploaded_file($tmpName) || ! is_readable($tmpName)) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File upload tidak valid.'];

                continue;
            }

            $fileSize = filesize($tmpName);
            if ($fileSize === false || $fileSize <= 0) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File kosong atau gagal terbaca.'];

                continue;
            }

            if ($fileCategory === 'crc_individual' && $fileSize > self::CRC_INDIVIDUAL_MAX_FILE_SIZE) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File CRC lebih dari 1MB.'];

                continue;
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($fileCategory === 'crc_individual' && $ext !== 'crc') {
                $errors[] = ['file_name' => $originalName, 'message' => 'Kategori CRC Individu hanya menerima file .CRC.'];

                continue;
            }

            if ($fileCategory !== 'crc_individual' && $this->isBlockedUploadExtension($ext)) {
                $errors[] = ['file_name' => $originalName, 'message' => 'Tipe file ".'.$ext.'" tidak diizinkan.'];

                continue;
            }

            $displayName = $customName !== '' && count($uploads) === 1
                ? $customName
                : pathinfo($originalName, PATHINFO_FILENAME);
            $cleanDisplayName = $this->ftp->safeFileName($displayName);
            $newName = $fileCategory === 'crc_individual'
                ? ($cleanDisplayName !== '' ? $cleanDisplayName : 'CRC_'.date('His').'_'.$index).'.CRC'
                : date('Ymd_His').'_'.$nomorAdmin.'_'.$cleanDisplayName.'.'.$ext;
            $ftpFolder = $fileCategory === 'crc_individual'
                ? $nomorAdmin.'/'.$dateCode.'/CRC'
                : $nomorAdmin.'/'.date('Ymd').'/Documents';
            $ftpFilePath = $this->ftp->normalizePath($ftpFolder.'/'.$newName);

            try {
                if ($httpUploadUrl !== '' && $fileCategory === 'crc_individual') {
                    $crcReceiverFiles[] = [
                        'tmp_name' => $tmpName,
                        'file_name' => $newName,
                        'file_size' => (int) $fileSize,
                        'file_path' => $ftpFilePath,
                        'file_type' => 'crc',
                    ];

                    continue;
                }

                if ($httpUploadUrl !== '') {
                    $this->uploadFileViaHttpReceiver($tmpName, $nomorAdmin, $newName, (int) $fileSize, $this->getHttpReceiverType($fileCategory, $ext), $httpUploadUrl, date('Ymd'));
                } else {
                    $this->ftp->upload($tmpName, $ftpFilePath);
                }

                $this->model->insertFile([
                    'filing_id' => $filingId,
                    'file_name' => $newName,
                    'file_path' => $ftpFilePath,
                    'file_type' => $fileCategory === 'crc_individual' ? 'crc' : $ext,
                    'file_category' => $fileCategory,
                ]);
                $uploaded[] = ['file_name' => $newName, 'file_path' => $ftpFilePath];
            } catch (Throwable $e) {
                $errors[] = ['file_name' => $originalName, 'message' => $e->getMessage()];
            }
        }

        if (! empty($crcReceiverFiles)) {
            try {
                $receiverResult = $this->uploadCrcIndividualBulkViaHttpReceiver($crcReceiverFiles, $nomorAdmin, $dateCode, $httpUploadUrl);
                $receiverFiles = $receiverResult['files'] ?? [];
                $uploadedByName = [];
                foreach ($receiverFiles as $receiverFile) {
                    $receiverName = (string) ($receiverFile['file_name'] ?? '');
                    if ($receiverName !== '') {
                        $uploadedByName[strtolower($receiverName)] = true;
                    }
                }

                foreach ($crcReceiverFiles as $file) {
                    if (! isset($uploadedByName[strtolower($file['file_name'])])) {
                        continue;
                    }
                    $this->model->insertFile([
                        'filing_id' => $filingId,
                        'file_name' => $file['file_name'],
                        'file_path' => $file['file_path'],
                        'file_type' => 'crc',
                        'file_category' => 'crc_individual',
                    ]);
                    $uploaded[] = ['file_name' => $file['file_name'], 'file_path' => $file['file_path']];
                }
                foreach (($receiverResult['errors'] ?? []) as $error) {
                    $errors[] = $error;
                }
            } catch (Throwable $e) {
                foreach ($crcReceiverFiles as $file) {
                    $errors[] = ['file_name' => $file['file_name'], 'message' => $e->getMessage()];
                }
            }
        }

        $this->ftp->close();
        echo json_encode([
            'status' => ! empty($uploaded) ? 'success' : 'error',
            'msg' => count($uploaded).' file berhasil diupload'.(! empty($errors) ? ', '.count($errors).' gagal.' : '.'),
            'uploaded_count' => count($uploaded),
            'failed_count' => count($errors),
            'files' => $uploaded,
            'errors' => $errors,
        ]);
        exit;
    }

    private function normalizeBulkFiles(array $files): array
    {
        $items = [];
        foreach (($files['name'] ?? []) as $idx => $name) {
            $items[] = [
                'name' => $name,
                'type' => $files['type'][$idx] ?? '',
                'tmp_name' => $files['tmp_name'][$idx] ?? '',
                'error' => $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$idx] ?? 0,
            ];
        }

        return $items;
    }

    private function getFilingDateCode(int $filingId): string
    {
        $stmt = $this->pdoRun->prepare('SELECT tanggal FROM runit_filing_system WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$filingId]);
        $date = (string) $stmt->fetchColumn();
        $timestamp = strtotime($date);

        return $timestamp ? date('Ymd', $timestamp) : date('Ymd');
    }

    private function isBlockedUploadExtension(string $ext): bool
    {
        return $ext === '' || in_array($ext, [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'exe', 'bat', 'cmd', 'com', 'scr',
            'sh', 'bash', 'cgi', 'pl', 'py',
            'js', 'html', 'htm', 'htaccess',
        ], true);
    }

    private function uploadCrcIndividualBulkViaHttpReceiver(array $files, string $nomorAdmin, string $dateCode, string $uploadUrl): array
    {
        if (! function_exists('curl_init')) {
            throw new Exception('cURL tidak tersedia untuk upload HTTP receiver.');
        }

        $postFields = [
            'type' => 'crc_individual',
            'admin_no' => $nomorAdmin,
            'test_date' => $dateCode,
            'tanggal' => $dateCode,
        ];
        foreach ($files as $idx => $file) {
            $postFields['files['.$idx.']'] = new CURLFile($file['tmp_name'], 'application/octet-stream', $file['file_name']);
        }

        $headers = ['Accept: application/json'];
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
            throw new Exception('Gagal initialisasi cURL untuk upload HTTP receiver.');
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

    private function uploadFileViaHttpReceiver(string $tmpName, string $nomorAdmin, string $fileName, int $fileSize, string $type, string $uploadUrl, string $uploadDateFolder): void
    {
        if (! function_exists('curl_init')) {
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
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
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
            throw new Exception('cURL error upload HTTP receiver: '.$error);
        }

        $result = null;
        if (is_string($response) && trim($response) !== '') {
            $result = json_decode($response, true);
        }

        if ($status < 200 || $status >= 300 || ! is_array($result) || empty($result['success'])) {
            $message = is_array($result) && isset($result['message'])
                ? $result['message']
                : substr(trim(strip_tags((string) $response)), 0, 200);
            throw new Exception('Server HTTP receiver error (HTTP '.$status.'): '.$message);
        }
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'File tidak dipilih',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension',
            default => 'Error code: '.$errorCode
        };
    }

    private function deleteFile(): void
    {
        $fileId = (int) ($_POST['file_id'] ?? 0);

        $file = $this->model->getFileById($fileId);

        if (! $file) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'File tidak ditemukan di database.',
            ]);
            exit;
        }

        try {
            $filePath = $file['file_path'];

            // Check if file actually exists on FTP
            if ($this->ftp->exists($filePath)) {
                // Delete from FTP first
                $deleted = $this->ftp->delete($filePath);
                if (! $deleted) {
                    throw new Exception('Server FTP menolak permintaan penghapusan file.');
                }
            } else {
                // File already missing from FTP (orphan record), log it and allow DB delete
                error_log('[FILING_DELETE_ORPHAN] File is missing on FTP but exists in DB: '.$filePath);
            }

            $this->ftp->close();

            // Only delete from DB if FTP deletion succeeded or file was already missing
            $this->model->deleteFileById($fileId);

            echo json_encode(['status' => 'success']);
            exit;

        } catch (Throwable $e) {
            $this->ftp->close();

            error_log('[FILING_DELETE_ERROR] '.$e->getMessage());

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'msg' => 'Gagal menghapus file dari FTP: '.$e->getMessage(),
            ]);
            exit;
        }
    }

    private function downloadFile(int $fileId): void
    {
        $file = $this->model->getFileById($fileId);

        if (! $file) {
            http_response_code(404);
            exit('File tidak ditemukan di database.');
        }

        $tempDir = BASE_PATH.'/storage/uploads';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'ftp_dl_');

        if ($tmpFile === false) {
            http_response_code(500);
            exit('Gagal membuat file temporary lokal.');
        }

        try {
            // Download FTP file to local temp path
            $this->ftp->download($file['file_path'], $tmpFile);
            $this->ftp->close();

            if (! file_exists($tmpFile)) {
                http_response_code(500);
                exit('Gagal mengambil file dari FTP storage: file tidak ada.');
            }

            $fileSize = filesize($tmpFile);
            if ($fileSize === false || $fileSize <= 0) {
                @unlink($tmpFile);
                http_response_code(500);
                exit('Gagal mengambil file dari FTP storage: file kosong.');
            }

            // Clear all output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Send headers to browser
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file['file_name']).'"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: '.$fileSize);

            $sent = @readfile($tmpFile);

            if ($sent === false) {
                @unlink($tmpFile);
                http_response_code(500);
                exit('Gagal mengirim file ke browser.');
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

                header('Location: '.$publicUrl);
                exit;
            }

            http_response_code(500);
            exit('Error download: '.$e->getMessage());
        }
    }

    private function downloadAdminFolder(int $filingId): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (! $filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            exit('Data filing tidak ditemukan.');
        }

        try {
            $this->proxyReceiverBinary('download_zip', (string) $filing['nomor_admin']);
        } catch (Throwable $e) {
            http_response_code(500);
            exit('Gagal download folder admin: '.$e->getMessage());
        }
    }

    private function downloadOutboundFile(int $filingId, string $fileName): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (! $filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            exit('Data filing tidak ditemukan.');
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (! $this->isValidOutboundFileName((string) $filing['nomor_admin'], $fileName)) {
            http_response_code(400);
            exit('Nama file outbound tidak valid.');
        }

        $remotePath = $this->getOutboundFolder().'/'.$fileName;
        $tempDir = BASE_PATH.'/storage/uploads';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'outbound_');
        if ($tmpFile === false) {
            http_response_code(500);
            exit('Gagal membuat file temporary lokal.');
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
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: '.$fileSize);
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
                header('Location: '.$publicUrl);
                exit;
            }

            http_response_code(500);
            exit('Gagal download outbound: '.$e->getMessage());
        }
    }

    private function downloadCrcRawFile(int $filingId, string $fileName): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (! $filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            exit('Data filing tidak ditemukan.');
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (! $this->isValidCrcRawFileName($fileName)) {
            http_response_code(400);
            exit('Nama file CRC RAW tidak valid.');
        }

        $adminNo = $this->ftp->safeFileName((string) $filing['nomor_admin']);
        $remotePath = $adminNo.'/'.$fileName;
        $publicUrl = $this->buildPublicFileUrl($remotePath);

        if ($publicUrl !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Location: '.$publicUrl);
            exit;
        }

        http_response_code(500);
        exit('Gagal membuat URL download CRC RAW.');
    }

    private function fetchReceiverFileList(string $adminNo): ?array
    {
        $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));

        if ($uploadUrl === '' || ! function_exists('curl_init')) {
            return null;
        }

        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
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

        if ($status < 200 || $status >= 300 || ! is_string($response)) {
            return null;
        }

        $result = json_decode($response, true);

        if (! is_array($result) || empty($result['success']) || ! isset($result['files_by_category'])) {
            return null;
        }

        return $result['files_by_category'];
    }

    private function proxyReceiverBinary(string $action, string $adminNo): void
    {
        $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));

        if ($uploadUrl === '' || ! function_exists('curl_init')) {
            throw new Exception('Konfigurasi HTTP receiver belum tersedia.');
        }

        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
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

        if ($errno !== 0 || ! is_string($response)) {
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
        header('Content-Disposition: attachment; filename="'.$safeAdminNo.'.zip"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.strlen($body));
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

            return rtrim($httpBaseUrl, '/').'/'.$encodedPath;
        }

        // Database bisa menyimpan CRC/filing_system/...,
        // sedangkan base URL sudah sampai folder CRC.
        if (strpos($filePath, 'CRC/') === 0) {
            $filePath = substr($filePath, 4);
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));

        return rtrim($baseUrl, '/').'/'.$encodedPath;
    }

    private function enrichFilesWithPublicUrls(array $filesByCategory): array
    {
        foreach ($filesByCategory as $category => $files) {
            if (! is_array($files)) {
                continue;
            }

            foreach ($files as $index => $file) {
                if (! is_array($file)) {
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

        return rtrim($baseUrl, '/').'/'.rawurlencode($fileName);
    }

    private function getOutboundFolder(): string
    {
        $folder = trim((string) env('OUTBOUND_FTP_PATH', 'OUTBOUND'));
        $folder = $folder !== '' ? $folder : 'OUTBOUND';

        return str_starts_with($folder, '/') ? '/'.trim($folder, '/') : trim($folder, '/');
    }

    private function isValidOutboundFileName(string $adminNo, string $fileName): bool
    {
        $adminNo = trim($adminNo);
        $fileName = basename(str_replace('\\', '/', $fileName));

        if ($adminNo === '' || $fileName === '') {
            return false;
        }

        $pattern = '/^'.preg_quote($adminNo, '/').'-.*-DATA\.ZIP$/i';

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
            error_log('[OUTBOUND_LIST_ERROR] '.$e->getMessage());
            $fileNames = [];
        }

        if (empty($fileNames)) {
            $fileNames = $this->fetchOutboundFileListViaHttp();
        }

        $receiverFiles = [];
        if (empty($fileNames)) {
            $receiverFiles = $this->fetchOutboundFilesViaReceiver($adminNo, $filingId);
        }

        if (! empty($receiverFiles)) {
            return $receiverFiles;
        }

        $files = [];
        foreach ($fileNames as $fileName) {
            if (! $this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'zip',
                'file_category' => 'outbound',
                'uploaded_at' => '',
                'download_url' => 'berita_acara.php?download_outbound='.$filingId.'&file='.rawurlencode($fileName),
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
            error_log('[CRC_RAW_LIST_ERROR] '.$e->getMessage());
            $fileNames = [];
        }

        $files = [];
        foreach ($fileNames as $fileName) {
            $fileName = basename(str_replace('\\', '/', (string) $fileName));
            if (! $this->isValidCrcRawFileName($fileName)) {
                continue;
            }

            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'crc',
                'file_category' => 'crc_raw',
                'uploaded_at' => '',
                'download_url' => 'berita_acara.php?download_crc_raw='.$filingId.'&file='.rawurlencode($fileName),
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return strcasecmp($a['file_name'], $b['file_name']);
        });

        return $files;
    }

    private function getCrcB2Folder(string $adminNo): string
    {
        return $this->getCrcB2RemoteRoot().'/'.$this->ftp->safeFileName($adminNo);
    }

    private function getCrcB2RemoteRoot(): string
    {
        $root = trim((string) env('CRC_B2_FTP_PATH', 'collector/final'));

        return trim(str_replace('\\', '/', $root), '/');
    }

    private function getCrcB2LocalRoot(): string
    {
        $root = trim((string) env('CRC_B2_STORAGE_ROOT', '/www/wwwroot/cbt.toeic.or.id/docs/collector/final'));

        return rtrim(str_replace('\\', '/', $root), '/');
    }

    private function getCrcB2ReceiverUrl(): string
    {
        return trim((string) env('CRC_B2_RECEIVER_URL', 'https://cbt.toeic.or.id/docs/CBT/CRC_B2/crc_b2_receiver.php'));
    }

    private function fetchCrcB2Receiver(string $action, array $params = []): ?array
    {
        $url = $this->getCrcB2ReceiverUrl();
        if ($url === '') {
            return null;
        }

        $requestUrl = $url.(str_contains($url, '?') ? '&' : '?').http_build_query(array_merge(['action' => $action], $params));
        $headers = ['Accept: application/json'];
        $token = trim((string) env('CRC_B2_RECEIVER_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        if (! function_exists('curl_init')) {
            return $this->fetchCrcB2ReceiverWithStream($requestUrl, $headers);
        }

        $ch = curl_init($requestUrl);
        if (! $ch) {
            return $this->fetchCrcB2ReceiverWithStream($requestUrl, $headers);
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 300 || ! is_string($response)) {
            error_log('[CRC_B2_RECEIVER_ERROR] status='.$status.' error='.$error);

            return $this->fetchCrcB2ReceiverWithStream($requestUrl, $headers);
        }

        $payload = json_decode($response, true);
        if (! is_array($payload) || empty($payload['success'])) {
            error_log('[CRC_B2_RECEIVER_INVALID] '.substr((string) $response, 0, 300));

            return null;
        }

        return $payload;
    }

    private function fetchCrcB2ReceiverWithStream(string $requestUrl, array $headers): ?array
    {
        $headerText = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerText,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents($requestUrl, false, $context);
        if (! is_string($response) || $response === '') {
            error_log('[CRC_B2_RECEIVER_STREAM_ERROR] empty response');

            return null;
        }

        $payload = json_decode($response, true);
        if (! is_array($payload) || empty($payload['success'])) {
            error_log('[CRC_B2_RECEIVER_STREAM_INVALID] '.substr($response, 0, 300));

            return null;
        }

        return $payload;
    }

    private function getCrcB2LocalAdminDir(string $adminNo): ?string
    {
        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        if ($safeAdminNo === '') {
            return null;
        }

        $root = $this->getCrcB2LocalRoot();
        $dir = $root.'/'.$safeAdminNo;
        $realRoot = realpath($root);
        $realDir = realpath($dir);

        if (! $realRoot || ! $realDir || strpos(str_replace('\\', '/', $realDir), str_replace('\\', '/', $realRoot).'/') !== 0) {
            return null;
        }

        return is_dir($realDir) ? str_replace('\\', '/', $realDir) : null;
    }

    private function getCrcB2LocalFilesForAdmin(string $adminNo): array
    {
        $dir = $this->getCrcB2LocalAdminDir($adminNo);
        if ($dir === null) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $fileName) {
            if (! $this->isValidCrcRawFileName($fileName)) {
                continue;
            }

            $path = $dir.'/'.$fileName;
            if (! is_file($path)) {
                continue;
            }

            $modifiedAt = filemtime($path) ?: null;
            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'crc',
                'file_category' => 'crc_b2',
                'relative_path' => $this->getCrcB2RemoteRoot().'/'.$this->ftp->safeFileName($adminNo).'/'.$fileName,
                'matched_admin_no' => $this->ftp->safeFileName($adminNo),
                'modified_at_ts' => $modifiedAt,
                'uploaded_at' => $modifiedAt ? date('d M Y H:i', $modifiedAt) : '',
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return strcasecmp($a['file_name'], $b['file_name']);
        });

        return $files;
    }

    private function getCrcB2LocalFolders(): array
    {
        $root = $this->getCrcB2LocalRoot();
        if (! is_dir($root) || ! is_readable($root)) {
            return [];
        }

        $folders = [];
        foreach (scandir($root) ?: [] as $folderName) {
            if ($folderName === '.' || $folderName === '..') {
                continue;
            }

            $dir = $root.'/'.$folderName;
            if (! is_dir($dir)) {
                continue;
            }

            $files = $this->getCrcB2LocalFilesForAdmin($folderName);
            $summary = $this->getCrcB2Summary(['nomor_admin' => $folderName, 'rec_id' => 0], $files);
            $summary['download_url'] = 'berita_acara.php?download_crc_b2_admin='.rawurlencode($folderName);
            $folders[] = $summary;
        }

        usort($folders, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['admin_no'] ?? ''), (string) ($b['admin_no'] ?? ''));
        });

        return $folders;
    }

    private function getCrcB2CollectorFolders(array $filters = [], ?array &$pagination = null): array
    {
        if (! $this->pdoCollector) {
            return [];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 21;
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = 'admin_no LIKE ?';
            $params[] = '%'.$search.'%';
        }

        $dateStart = trim((string) ($filters['date_start'] ?? ''));
        $dateEnd = trim((string) ($filters['date_end'] ?? ''));
        if ($dateStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
            $where[] = 'updated_at >= ?';
            $params[] = $dateStart.' 00:00:00';
        }
        if ($dateEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            $where[] = 'updated_at <= ?';
            $params[] = $dateEnd.' 23:59:59';
        }

        $whereSql = ! empty($where) ? 'WHERE '.implode(' AND ', $where) : '';

        try {
            $countStmt = $this->pdoCollector->prepare("SELECT COUNT(*) AS total FROM (SELECT admin_no FROM crc_upload_queue {$whereSql} GROUP BY admin_no) x");
            $countStmt->execute($params);
            $total = (int) ($countStmt->fetchColumn() ?: 0);

            $sql = "\n                SELECT\n                    admin_no,\n                    COUNT(*) AS total_files,\n                    SUM(status = 'done') AS done_files,\n                    SUM(status = 'failed') AS failed_files,\n                    SUM(status = 'processing') AS processing_files,\n                    SUM(status = 'queued') AS queued_files,\n                    MAX(updated_at) AS last_updated\n                FROM crc_upload_queue\n                {$whereSql}\n                GROUP BY admin_no\n                ORDER BY MAX(updated_at) DESC, admin_no ASC\n                LIMIT {$perPage} OFFSET {$offset}\n            ";
            $stmt = $this->pdoCollector->prepare($sql);
            $stmt->execute($params);

            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ];
        } catch (Throwable $e) {
            error_log('[CRC_COLLECTOR_FOLDER_DB_ERROR] '.$e->getMessage());

            return [];
        }

        $folders = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $adminNo = (string) ($row['admin_no'] ?? '');
            if ($adminNo === '') {
                continue;
            }

            $lastUpdated = (string) ($row['last_updated'] ?? '');
            $folders[] = [
                'admin_no' => $adminNo,
                'count' => (int) ($row['total_files'] ?? 0),
                'total_files' => (int) ($row['total_files'] ?? 0),
                'done_files' => (int) ($row['done_files'] ?? 0),
                'failed_files' => (int) ($row['failed_files'] ?? 0),
                'processing_files' => (int) ($row['processing_files'] ?? 0),
                'queued_files' => (int) ($row['queued_files'] ?? 0),
                'processed_at' => $lastUpdated !== '' ? date('d M Y H:i', strtotime($lastUpdated)) : '',
                'last_updated' => $lastUpdated,
                'source' => 'db',
                'download_url' => 'berita_acara.php?download_crc_b2_admin='.rawurlencode($adminNo),
            ];
        }

        return $folders;
    }

    private function getCrcB2CollectorFilesForAdmin(string $adminNo): array
    {
        if (! $this->pdoCollector || trim($adminNo) === '') {
            return [];
        }

        try {
            $stmt = $this->pdoCollector->prepare("\n                SELECT\n                    id, request_id, admin_no, authorize_no, original_name, stored_name, final_path,\n                    file_size, status, attempts, error_message, created_at, updated_at\n                FROM crc_upload_queue\n                WHERE admin_no = ?\n                ORDER BY updated_at DESC, id DESC\n            ");
            $stmt->execute([$adminNo]);
        } catch (Throwable $e) {
            error_log('[CRC_COLLECTOR_FILE_DB_ERROR] '.$e->getMessage());

            return [];
        }

        $files = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fileName = (string) ($row['stored_name'] ?: $row['original_name'] ?: basename((string) ($row['final_path'] ?? '')));
            $updatedAt = (string) ($row['updated_at'] ?? '');
            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'crc',
                'file_category' => 'crc_b2',
                'relative_path' => (string) ($row['final_path'] ?? ''),
                'authorize_no' => (string) ($row['authorize_no'] ?? ''),
                'original_name' => (string) ($row['original_name'] ?? ''),
                'stored_name' => (string) ($row['stored_name'] ?? ''),
                'file_size' => (int) ($row['file_size'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'attempts' => (int) ($row['attempts'] ?? 0),
                'error_message' => (string) ($row['error_message'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => $updatedAt,
                'uploaded_at' => $updatedAt !== '' ? date('d M Y H:i', strtotime($updatedAt)) : '',
                'source' => 'db',
            ];
        }

        return $files;
    }

    private function getCrcB2Folders(array $filters = [], ?array &$pagination = null): array
    {
        $collectorFolders = $this->getCrcB2CollectorFolders($filters, $pagination);
        if ($this->pdoCollector && $pagination !== null) {
            return $collectorFolders;
        }

        if (! empty($collectorFolders)) {
            return $collectorFolders;
        }

        $receiver = $this->fetchCrcB2Receiver('list_folders');
        if (is_array($receiver) && isset($receiver['folders']) && is_array($receiver['folders'])) {
            $folders = [];
            foreach ($receiver['folders'] as $folder) {
                if (! is_array($folder) || empty($folder['admin_no'])) {
                    continue;
                }

                $folder['download_url'] = 'berita_acara.php?download_crc_b2_admin='.rawurlencode((string) $folder['admin_no']);
                $folders[] = $folder;
            }

            if (! empty($folders)) {
                return $folders;
            }
        }

        $folders = $this->getCrcB2LocalFolders();
        if (! empty($folders)) {
            return $folders;
        }

        try {
            $diagnostics = $this->ftp->listFilesWithDiagnostics($this->getCrcB2RemoteRoot());
            $folderNames = $diagnostics['files'] ?? [];
        } catch (Throwable $e) {
            $this->ftp->close();
            error_log('[CRC_B2_FOLDER_LIST_ERROR] '.$e->getMessage());

            return [];
        }

        $folders = [];
        foreach ($folderNames as $folderName) {
            $folderName = basename(str_replace('\\', '/', (string) $folderName));
            if ($folderName === '' || $folderName === '.' || $folderName === '..') {
                continue;
            }

            if (strtolower(pathinfo($folderName, PATHINFO_EXTENSION)) === 'crc') {
                continue;
            }

            $summary = $this->getCrcB2Summary(['nomor_admin' => $folderName, 'rec_id' => 0], []);
            $summary['count'] = null;
            $summary['processed_at'] = '';
            $summary['download_url'] = 'berita_acara.php?download_crc_b2_admin='.rawurlencode($folderName);
            $folders[] = $summary;
        }

        $this->ftp->close();

        usort($folders, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['admin_no'] ?? ''), (string) ($b['admin_no'] ?? ''));
        });

        return $folders;
    }

    private function getCrcB2FolderDiagnostics(): array
    {
        $root = $this->getCrcB2LocalRoot();
        $diagnostics = [
            'local_root' => $root,
            'local_root_exists' => is_dir($root),
            'local_root_readable' => is_readable($root),
            'receiver_url' => $this->getCrcB2ReceiverUrl(),
            'receiver_available' => is_array($this->fetchCrcB2Receiver('list_folders')),
            'ftp' => null,
        ];

        try {
            $diagnostics['ftp'] = $this->ftp->listFilesWithDiagnostics($this->getCrcB2RemoteRoot());
            $this->ftp->close();
        } catch (Throwable $e) {
            $this->ftp->close();
            $diagnostics['ftp_error'] = $e->getMessage();
        }

        return $diagnostics;
    }

    private function findCrcB2AdminFolder(string $adminNo): array
    {
        $adminNo = trim($adminNo);
        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        if ($safeAdminNo === '') {
            return ['', ''];
        }

        try {
            $folders = $this->ftp->listFiles($this->getCrcB2RemoteRoot());
        } catch (Throwable $e) {
            error_log('[CRC_B2_ROOT_LIST_ERROR] '.$e->getMessage());

            return [$this->getCrcB2Folder($adminNo), $safeAdminNo];
        }

        $targetNumeric = ltrim(preg_replace('/\D+/', '', $adminNo), '0');
        foreach ($folders as $folder) {
            $folderName = basename(str_replace('\\', '/', (string) $folder));
            if ($folderName === '' || $folderName === '.' || $folderName === '..') {
                continue;
            }

            if (strcasecmp($folderName, $safeAdminNo) === 0) {
                return [$this->getCrcB2RemoteRoot().'/'.$folderName, $folderName];
            }

            if ($targetNumeric !== '') {
                $folderNumeric = ltrim(preg_replace('/\D+/', '', $folderName), '0');
                if ($folderNumeric !== '' && $folderNumeric === $targetNumeric) {
                    return [$this->getCrcB2RemoteRoot().'/'.$folderName, $folderName];
                }
            }
        }

        return [$this->getCrcB2Folder($adminNo), $safeAdminNo];
    }

    private function getCrcB2AdminFolderCandidates(string $adminNo, bool $includeRootScan = true): array
    {
        $candidates = [];

        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        if ($safeAdminNo !== '') {
            $candidates[] = [$this->getCrcB2Folder($safeAdminNo), $safeAdminNo];
            $upperAdminNo = strtoupper($safeAdminNo);
            if ($upperAdminNo !== $safeAdminNo) {
                $candidates[] = [$this->getCrcB2Folder($upperAdminNo), $upperAdminNo];
            }
        }

        $numeric = preg_replace('/\D+/', '', $adminNo);
        if ($numeric !== '') {
            $numericNoZero = ltrim($numeric, '0');
            $numericNoZero = $numericNoZero !== '' ? $numericNoZero : '0';
            foreach ([4, 5, 6] as $length) {
                $candidateAdmin = 'B'.str_pad($numericNoZero, $length, '0', STR_PAD_LEFT);
                $candidates[] = [$this->getCrcB2Folder($candidateAdmin), $candidateAdmin];
            }
            $candidates[] = [$this->getCrcB2Folder('B'.$numericNoZero), 'B'.$numericNoZero];
            $candidates[] = [$this->getCrcB2Folder('B0'.$numericNoZero), 'B0'.$numericNoZero];
        }

        if ($includeRootScan) {
            [$matchedDir, $matchedAdminNo] = $this->findCrcB2AdminFolder($adminNo);
            $candidates[] = [$matchedDir, $matchedAdminNo];
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate[0]);
            $unique[$key] = $candidate;
        }

        return array_values($unique);
    }

    private function getCrcB2FilesForFiling(array $filing): array
    {
        $adminNo = trim((string) ($filing['nomor_admin'] ?? ''));
        $filingId = (int) ($filing['rec_id'] ?? 0);

        if ($adminNo === '' || $filingId <= 0) {
            return [];
        }

        return $this->getCrcB2FilesForAdmin($adminNo);
    }

    private function getCrcB2FilesForAdmin(string $adminNo, bool $includeRootScan = true): array
    {
        $adminNo = trim($adminNo);
        if ($adminNo === '') {
            return [];
        }

        $collectorFiles = $this->getCrcB2CollectorFilesForAdmin($adminNo);
        if (! empty($collectorFiles)) {
            return $collectorFiles;
        }

        $receiver = $this->fetchCrcB2Receiver('list_files', ['admin_no' => $this->ftp->safeFileName($adminNo)]);
        if (is_array($receiver) && isset($receiver['files']) && is_array($receiver['files'])) {
            return $receiver['files'];
        }

        $localFiles = $this->getCrcB2LocalFilesForAdmin($adminNo);
        if (! empty($localFiles)) {
            return $localFiles;
        }

        $candidateDirs = $this->getCrcB2AdminFolderCandidates($adminNo, $includeRootScan);
        $lastError = null;

        foreach ($candidateDirs as [$remoteDir, $matchedAdminNo]) {
            try {
                $fileNames = $this->ftp->listFiles($remoteDir);
            } catch (Throwable $e) {
                $lastError = $e;

                continue;
            }

            $files = [];
            foreach ($fileNames as $fileName) {
                $fileName = basename(str_replace('\\', '/', (string) $fileName));
                if (! $this->isValidCrcRawFileName($fileName)) {
                    continue;
                }

                $remotePath = $remoteDir.'/'.$fileName;
                $modifiedAt = -1;
                try {
                    $modifiedAt = $this->ftp->modifiedTime($remotePath);
                } catch (Throwable $e) {
                    $modifiedAt = -1;
                }

                $files[] = [
                    'file_id' => null,
                    'file_name' => $fileName,
                    'file_type' => 'crc',
                    'file_category' => 'crc_b2',
                    'file_path' => $remotePath,
                    'relative_path' => $remotePath,
                    'matched_admin_no' => $matchedAdminNo,
                    'modified_at_ts' => $modifiedAt > 0 ? $modifiedAt : null,
                    'uploaded_at' => $modifiedAt > 0 ? date('d M Y H:i', $modifiedAt) : '',
                ];
            }

            if (empty($files)) {
                continue;
            }

            $this->ftp->close();

            usort($files, static function (array $a, array $b): int {
                return strcasecmp($a['file_name'], $b['file_name']);
            });

            return $files;
        }

        $this->ftp->close();
        if ($lastError) {
            error_log('[CRC_B2_LIST_ERROR] '.$lastError->getMessage());
        }

        return [];
    }

    private function getCrcB2Summary(array $filing, array $files): array
    {
        $latest = null;
        foreach ($files as $file) {
            $modifiedAt = $file['modified_at_ts'] ?? null;
            if (is_int($modifiedAt) && ($latest === null || $modifiedAt > $latest)) {
                $latest = $modifiedAt;
            }
        }

        return [
            'admin_no' => (string) ($filing['nomor_admin'] ?? ''),
            'count' => count($files),
            'processed_at' => $latest ? date('d M Y H:i', $latest) : '',
            'download_url' => ! empty($filing['rec_id']) ? 'berita_acara.php?download_crc_b2='.(int) $filing['rec_id'] : '',
        ];
    }

    private function downloadCrcB2Zip(int $filingId): void
    {
        $filing = $this->model->getFilingById($filingId);

        if (! $filing || empty($filing['nomor_admin'])) {
            http_response_code(404);
            exit('Data filing tidak ditemukan.');
        }

        if (! class_exists('ZipArchive')) {
            http_response_code(500);
            exit('Extension ZipArchive PHP belum aktif di server ini.');
        }

        $adminNo = (string) $filing['nomor_admin'];
        $this->downloadCrcB2ZipByAdmin($adminNo);
    }

    private function downloadCrcB2ZipByAdmin(string $adminNo): void
    {
        $adminNo = trim($adminNo);
        if ($adminNo === '') {
            http_response_code(400);
            exit('Nomor admin tidak valid.');
        }

        if (! class_exists('ZipArchive')) {
            http_response_code(500);
            exit('Extension ZipArchive PHP belum aktif di server ini.');
        }

        if ($this->proxyCrcB2ReceiverZip($adminNo)) {
            exit;
        }

        $localDir = $this->getCrcB2LocalAdminDir($adminNo);
        if ($localDir !== null) {
            $this->downloadCrcB2LocalZip($adminNo, $localDir);
        }

        $candidateDirs = $this->getCrcB2AdminFolderCandidates($adminNo);
        [$remoteDir, $safeAdminNo] = $candidateDirs[0];
        $tempDir = BASE_PATH.'/storage/uploads';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $zipPath = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'crc_b2_');
        if ($zipPath === false) {
            http_response_code(500);
            exit('Gagal membuat file temporary ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            http_response_code(500);
            exit('Gagal membuat ZIP.');
        }

        $downloadedFiles = [];

        try {
            $fileNames = [];
            foreach ($candidateDirs as [$candidateDir, $candidateAdminNo]) {
                $candidateFiles = $this->ftp->listFiles($candidateDir);
                $crcFiles = array_filter($candidateFiles, function ($fileName): bool {
                    return $this->isValidCrcRawFileName(basename(str_replace('\\', '/', (string) $fileName)));
                });
                if (! empty($crcFiles)) {
                    $remoteDir = $candidateDir;
                    $safeAdminNo = $candidateAdminNo;
                    $fileNames = $candidateFiles;
                    break;
                }
            }
            foreach ($fileNames as $fileName) {
                $fileName = basename(str_replace('\\', '/', (string) $fileName));
                if (! $this->isValidCrcRawFileName($fileName)) {
                    continue;
                }

                $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'crc_b2_file_');
                if ($tmpFile === false) {
                    continue;
                }

                $this->ftp->download($remoteDir.'/'.$fileName, $tmpFile);
                if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
                    $zip->addFile($tmpFile, $fileName);
                    $downloadedFiles[] = $tmpFile;
                } else {
                    @unlink($tmpFile);
                }
            }

            $this->ftp->close();
            $zip->close();

            foreach ($downloadedFiles as $tmpFile) {
                @unlink($tmpFile);
            }

            $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
            if ($zipSize === false || $zipSize <= 0) {
                @unlink($zipPath);
                http_response_code(404);
                exit('File CRC B2 tidak ditemukan untuk nomor admin ini.');
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="CRC_B2_'.$safeAdminNo.'.zip"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: '.$zipSize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        } catch (Throwable $e) {
            $this->ftp->close();
            $zip->close();
            foreach ($downloadedFiles as $tmpFile) {
                @unlink($tmpFile);
            }
            @unlink($zipPath);
            http_response_code(500);
            exit('Gagal download CRC B2: '.$e->getMessage());
        }
    }

    private function proxyCrcB2ReceiverZip(string $adminNo): bool
    {
        $url = $this->getCrcB2ReceiverUrl();
        if ($url === '' || ! function_exists('curl_init')) {
            if ($url !== '') {
                header('Location: '.$url.(str_contains($url, '?') ? '&' : '?').http_build_query([
                    'action' => 'download_zip',
                    'admin_no' => $this->ftp->safeFileName($adminNo),
                ]));

                return true;
            }

            return false;
        }

        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        $requestUrl = $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'action' => 'download_zip',
            'admin_no' => $safeAdminNo,
        ]);
        $headers = ['Accept: application/zip'];
        $token = trim((string) env('CRC_B2_RECEIVER_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($requestUrl);
        if (! $ch) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
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

        if ($errno !== 0 || $status < 200 || $status >= 300 || ! is_string($response)) {
            error_log('[CRC_B2_RECEIVER_ZIP_ERROR] status='.$status.' error='.$error);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: '.$requestUrl);

            return true;
        }

        $body = substr($response, $headerSize);
        if ($body === '') {
            return false;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="CRC_B2_'.$safeAdminNo.'.zip"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.strlen($body));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $body;

        return true;
    }

    private function downloadCrcB2LocalZip(string $adminNo, string $localDir): void
    {
        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        $tempDir = BASE_PATH.'/storage/uploads';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $zipPath = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'crc_b2_');
        if ($zipPath === false) {
            http_response_code(500);
            exit('Gagal membuat file temporary ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            http_response_code(500);
            exit('Gagal membuat ZIP.');
        }

        foreach (scandir($localDir) ?: [] as $fileName) {
            if (! $this->isValidCrcRawFileName($fileName)) {
                continue;
            }

            $path = $localDir.'/'.$fileName;
            if (is_file($path) && filesize($path) > 0) {
                $zip->addFile($path, $fileName);
            }
        }

        $zip->close();
        $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
        if ($zipSize === false || $zipSize <= 0) {
            @unlink($zipPath);
            http_response_code(404);
            exit('File CRC B2 tidak ditemukan untuk nomor admin ini.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="CRC_B2_'.$safeAdminNo.'.zip"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.$zipSize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    private function ajaxCrcB2Folders(): void
    {
        header('Content-Type: application/json');

        try {
            $pagination = null;
            $filters = [
                'search' => $_GET['search'] ?? '',
                'date_start' => $_GET['date_start'] ?? '',
                'date_end' => $_GET['date_end'] ?? '',
                'page' => $_GET['page'] ?? 1,
            ];
            $folders = $this->getCrcB2Folders($filters, $pagination);
            echo json_encode([
                'status' => 'success',
                'folders' => $folders,
                'pagination' => $pagination,
                'diagnostics' => empty($folders) ? $this->getCrcB2FolderDiagnostics() : null,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function ajaxCrcB2Files(string $adminNo): void
    {
        header('Content-Type: application/json');

        try {
            $adminNo = $this->ftp->safeFileName($adminNo);
            echo json_encode([
                'status' => 'success',
                'files' => $this->getCrcB2FilesForAdmin($adminNo, false),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function ajaxCrcB2Collect(string $adminNo): void
    {
        header('Content-Type: application/json');

        $responded = false;
        register_shutdown_function(static function () use (&$responded): void {
            if ($responded) {
                return;
            }

            $error = error_get_last();
            if (! $error) {
                return;
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Fatal error: '.($error['message'] ?? 'Unknown error'),
            ]);
        });

        try {
            $adminNo = trim($adminNo);
            if ($adminNo === '') {
                echo json_encode(['status' => 'error', 'message' => 'Nomor admin wajib diisi.']);
                exit;
            }

            $files = $this->getCrcB2FilesForAdmin($adminNo, false);
            $matchedAdminNo = $adminNo;
            if (! empty($files[0]['relative_path'])) {
                $parts = explode('/', str_replace('\\', '/', (string) $files[0]['relative_path']));
                $matchedAdminNo = $parts[1] ?? $adminNo;
            }

            $summary = $this->getCrcB2Summary(['nomor_admin' => $matchedAdminNo, 'rec_id' => 0], $files);
            $summary['searched_admin_no'] = $adminNo;
            $summary['download_url'] = 'berita_acara.php?download_crc_b2_admin='.rawurlencode($matchedAdminNo);
            $summary['attempted_folders'] = array_map(static function (array $candidate): string {
                return $candidate[0];
            }, $this->getCrcB2AdminFolderCandidates($adminNo, false));

            $responded = true;
            echo json_encode([
                'status' => 'success',
                'files' => $files,
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            error_log('[CRC_B2_AJAX_ERROR] '.$e->getMessage());
            $responded = true;
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }

    private function normalizeCrcRawCategory(array $filesByCategory): array
    {
        $crcRawFiles = $filesByCategory['crc_raw'] ?? [];

        foreach ($filesByCategory as $category => $files) {
            if (! is_array($files) || $category === 'crc_raw') {
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
        if ($receiverUrl === '' || ! function_exists('curl_init')) {
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
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($receiverUrl);
        if (! $ch) {
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

        if ($errno !== 0 || $status < 200 || $status >= 300 || ! is_string($response)) {
            error_log('[OUTBOUND_RECEIVER_LIST_ERROR] status='.$status.' errno='.$errno.' error='.$error);

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
        if (! is_array($result) || empty($result['success']) || ! isset($result['files']) || ! is_array($result['files'])) {
            error_log('[OUTBOUND_RECEIVER_LIST_INVALID] '.substr((string) $response, 0, 300));

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
            if (! $this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $file['file_id'] = null;
            $file['file_type'] = 'zip';
            $file['file_category'] = 'outbound';
            if ($filingId > 0) {
                $file['download_url'] = 'berita_acara.php?download_outbound='.$filingId.'&file='.rawurlencode($fileName);
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
        if ($receiverUrl === '' || ! function_exists('curl_init')) {
            return false;
        }

        $token = trim((string) env('OUTBOUND_RECEIVER_TOKEN', env('CRC_HTTP_UPLOAD_TOKEN', 'annas123')));
        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($receiverUrl);
        if (! $ch) {
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

        if ($errno !== 0 || $status < 200 || $status >= 300 || ! is_string($response)) {
            error_log('[OUTBOUND_RECEIVER_DOWNLOAD_ERROR] status='.$status.' errno='.$errno.' error='.$error);

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
        header('Content-Disposition: attachment; filename="'.basename($fileName).'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.strlen($body));
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

        $url = rtrim($baseUrl, '/').'/';
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

        if (! is_string($html) || trim($html) === '') {
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

        if (! is_string($html) || trim($html) === '') {
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

        if (! $filing) {
            exit('Data filing tidak ditemukan.');
        }

        $issues = $this->model->getIssuesByFilingId($filingId);

        $filename = 'Issue_Report_'.$filing['nomor_admin'].'_'.date('Ymd_His').'.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Nomor Admin',
            'Peserta',
            'Authorize ID',
            'Issue / Berita Acara',
            'Tanggal Input',
        ]);

        foreach ($issues as $issue) {
            fputcsv($output, [
                $filing['nomor_admin'],
                $issue['participant_name'],
                $issue['authorize_id'],
                $issue['issue_text'],
                $issue['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    private function deleteEntry(int $filingId): void
    {
        if (! $this->canManageBeritaAcara()) {
            http_response_code(403);
            exit('TAD SPV tidak memiliki akses untuk menghapus data Berita Acara.');
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

            header('Location: main.php?status=deleted');
            exit;

        } catch (Exception $e) {
            exit('Error deleting: '.$e->getMessage());
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

        return ! userHasTadRole($this->pdoRun, $userId, ['TAD SPV']);
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
                            'is_finished' => $isFinished,
                        ];
                    }
                }
            }

            $clientMap = $this->model->getClientMap();

            echo json_encode([
                'testdate' => $admin ? date('Y-m-d', strtotime($admin['testdt'])) : '',
                'client_name' => $admin ? ($clientMap[$admin['client_id']] ?? '-') : '-',
                'participants' => $participants,
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

                if (! empty($filing['nomor_admin'])) {
                    $remoteFilesByCategory = $this->fetchReceiverFileList((string) $filing['nomor_admin']);
                    if (is_array($remoteFilesByCategory)) {
                        foreach ($remoteFilesByCategory as $category => $remoteFiles) {
                            if (! isset($filesByCategory[$category]) || ! is_array($filesByCategory[$category])) {
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

                if (! empty($filing['nomor_admin']) && ! empty($filing['sub_admin_id'])) {
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
                                'sisa_waktu' => 'Selesai',
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
