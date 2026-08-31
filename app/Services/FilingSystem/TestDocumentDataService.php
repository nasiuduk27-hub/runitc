<?php

namespace App\Services\FilingSystem;

use App\Support\Legacy\FilingSystemRecord;
use App\Support\Legacy\FtpStorage;
use PDO;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TestDocumentDataService
{
    private const CRC_INDIVIDUAL_MAX_FILES = 500;

    private const CRC_INDIVIDUAL_MAX_FILE_SIZE = 1048576;

    private const CRC_INDIVIDUAL_MAX_TOTAL_SIZE = 10485760;

    private FilingSystemRecord $records;

    private ?FtpStorage $ftp = null;

    private array $clientMap = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PDO $pdoRun,
        private readonly PDO $pdoWar,
        private readonly array $ftpConfig = [],
    ) {
        $this->records = new FilingSystemRecord($this->pdo, $this->pdoRun, $this->pdoWar);
    }

    public function getPageData(array $query, array $session): array
    {
        $this->records->ensureTablesExist();

        $userId = (int) ($session['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);

        $this->clientMap = $this->records->getClientMap();
        $adminClientMap = $this->records->getAdminClientMap($this->clientMap);

        $filters = [
            'search' => $query['search'] ?? '',
            'client' => $query['f_client'] ?? '',
            'spv' => $query['f_spv'] ?? '',
            'date' => $query['f_date'] ?? '',
            'admin' => $query['f_admin'] ?? '',
        ];

        $clientList = array_unique(array_values($this->clientMap));
        sort($clientList);

        $isAdminStaff = $this->userHasTadRole($userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
        $isSpvOnly = ! $isAdminStaff && $this->userHasTadRole($userId, ['TAD SPV']);
        $assignedAdmins = [];
        $spvFilter = null;

        if ($isSpvOnly && $spv['id']) {
            $assignedAdmins = $this->records->getAssignedAdminsBySupervisor($spv['id']);
            $spvFilter = [
                'spv_name' => $spv['name'],
                'assigned_admin_pairs' => array_map(static fn (array $row): array => [
                    'admin_id' => (int) ($row['admin_id'] ?? 0),
                    'sub_admin_id' => (string) ($row['sub_admin_id'] ?? ''),
                ], $assignedAdmins),
            ];
        } elseif ($isAdminStaff) {
            $assignedAdmins = $this->records->getAllAssignedAdmins();
        } elseif ($spv['id']) {
            $assignedAdmins = $this->records->getAssignedAdminsBySupervisor($spv['id']);
        }

        $queryFilters = $filters;
        $queryFilters['search'] = '';
        $dataList = $this->records->getFilteredFilingData($queryFilters, $adminClientMap, $spvFilter);
        if (empty($dataList)) {
            $dataList = $this->records->getFilingDataFromAssignments($queryFilters, $adminClientMap, $spvFilter);
        }

        return array_merge([
            'user_id' => $userId,
            'user_name' => $session['account_nm'] ?? $session['user_name'] ?? 'Guest',
            'spv_rec_id' => $spv['id'],
            'spv_name_active' => $spv['name'],
            'client_map' => $this->clientMap,
            'admin_client_map' => $adminClientMap,
            'assigned_admins' => $assignedAdmins,
            'client_list' => $clientList,
            'data_list' => $this->filterRowsBySearch($dataList, (string) $filters['search']),
        ], $filters);
    }

    public function syncAssignments(): void
    {
        $this->records->ensureTablesExist();
        $this->records->ensureFilingRecordsFromAssignments();
    }

    public function submenu(int $filingId): array
    {
        $filing = $this->records->getFilingById($filingId);

        $issues = [];
        $liveFinished = [];
        $filesByCategory = [];
        if ($filing) {
            $issues = $this->records->getIssuesByFilingId($filingId);
            $filesByCategory = $this->normalizeCrcRawCategory(
                $this->enrichFilesWithPublicUrls($this->records->getFilesByCategory($filingId))
            );

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

            $liveFinished = $this->getLiveFinishedParticipants($filing);
        }

        return [
            'issues' => $issues,
            'live' => $liveFinished,
            'files_by_category' => $filesByCategory,
        ];
    }

    public function entry(int $filingId): ?array
    {
        return $this->records->getEntryWithIssues($filingId);
    }

    public function adminInfo(string $adminValue, int $filingId = 0): array
    {
        $parts = explode('|', $adminValue);

        if (count($parts) < 2) {
            return ['error' => 'Invalid Selection'];
        }

        $adminRecId = (int) $parts[0];
        $subAdminId = (int) $parts[1];

        $admin = $this->records->getAdminById($adminRecId);
        $existingIssueAuths = $filingId > 0 ? $this->records->getIssueAuthorizeIds($filingId) : [];
        $participants = [];

        if ($admin) {
            $psyskunci = $this->shiftString(trim((string) $admin['admin_no']), 3);

            foreach ($this->records->getParticipantsBySubAdmin($adminRecId, $subAdminId) as $row) {
                $stat = (string) trim((string) $row['statrec']);
                $isFinished = in_array($stat, ['7', '8', '9', 'c', 'C'], true);
                $hasExistingIssue = in_array($row['authorize'], $existingIssueAuths, true);

                if ($isFinished || $hasExistingIssue) {
                    $participants[] = [
                        'id' => $row['authorize'],
                        'name' => $this->decryptNisnValue(trim((string) $row['regnm']), $psyskunci),
                        'status' => $stat,
                        'is_finished' => $isFinished,
                    ];
                }
            }
        }

        $clientMap = $this->records->getClientMap();

        return [
            'testdate' => $admin ? date('Y-m-d', strtotime((string) $admin['testdt'])) : '',
            'client_name' => $admin ? ($clientMap[$admin['client_id']] ?? '-') : '-',
            'participants' => $participants,
        ];
    }

    public function debugOutbound(string $adminNo): array
    {
        $adminNo = trim($adminNo);
        $receiverPayload = ['ok' => false, 'files' => [], 'skipped' => true];

        try {
            $diagnostics = $this->ftp()->listFilesWithDiagnostics($this->getOutboundFolder());
            $this->ftp()->close();
        } catch (\Throwable $e) {
            $this->ftp()->close();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ];
        }

        $httpFiles = empty($diagnostics['files'] ?? []) ? $this->fetchOutboundFileListViaHttp() : [];
        if (empty($diagnostics['files'] ?? []) && empty($httpFiles)) {
            $receiverPayload = $this->fetchOutboundReceiverPayload($adminNo);
        }

        $receiverFiles = $receiverPayload['files'] ?? [];
        $receiverFileNames = array_map(static fn (array $file): string => (string) ($file['file_name'] ?? ''), $receiverFiles);
        $allFiles = array_values(array_unique(array_merge($diagnostics['files'] ?? [], $httpFiles, $receiverFileNames)));

        $matches = [];
        foreach ($allFiles as $fileName) {
            if ($adminNo !== '' && $this->isValidOutboundFileName($adminNo, (string) $fileName)) {
                $matches[] = $fileName;
            }
        }

        return [
            'success' => true,
            'admin' => $adminNo,
            'outbound_folder' => $this->getOutboundFolder(),
            'total_files' => count($allFiles),
            'matches' => $matches,
            'http_fallback_files' => $httpFiles,
            'receiver_files' => $receiverFiles,
            'receiver_debug' => array_diff_key($receiverPayload, ['files' => true]),
            'diagnostics' => $diagnostics,
        ];
    }

    public function issueCsv(int $filingId): ?array
    {
        $filing = $this->records->getFilingById($filingId);
        if (! $filing) {
            return null;
        }

        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            return null;
        }

        fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, [
            'Nomor Admin',
            'Peserta',
            'Authorize ID',
            'Issue / Berita Acara',
            'Tanggal Input',
        ]);

        foreach ($this->records->getIssuesByFilingId($filingId) as $issue) {
            fputcsv($handle, [
                $filing['nomor_admin'],
                $issue['participant_name'],
                $issue['authorize_id'],
                $issue['issue_text'],
                $issue['created_at'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return [
            'filename' => 'Issue_Report_'.$filing['nomor_admin'].'_'.date('Ymd_His').'.csv',
            'content' => $csv === false ? '' : $csv,
        ];
    }

    public function crcRawDownloadUrl(int $filingId, string $fileName): ?string
    {
        $filing = $this->records->getFilingById($filingId);
        if (! $filing || empty($filing['nomor_admin'])) {
            return null;
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (! $this->isValidCrcRawFileName($fileName)) {
            return null;
        }

        return $this->buildPublicFileUrl($this->safeFileName((string) $filing['nomor_admin']).'/'.$fileName);
    }

    public function outboundDownload(int $filingId, string $fileName): array
    {
        $filing = $this->records->getFilingById($filingId);
        if (! $filing || empty($filing['nomor_admin'])) {
            return ['type' => 'error', 'status' => 404, 'message' => 'Data filing tidak ditemukan.'];
        }

        $fileName = basename(str_replace('\\', '/', rawurldecode($fileName)));
        if (! $this->isValidOutboundFileName((string) $filing['nomor_admin'], $fileName)) {
            return ['type' => 'error', 'status' => 400, 'message' => 'Nama file outbound tidak valid.'];
        }

        $remotePath = $this->getOutboundFolder().'/'.$fileName;
        $tempDir = storage_path('uploads');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'outbound_');
        if ($tmpFile === false) {
            return ['type' => 'error', 'status' => 500, 'message' => 'Gagal membuat file temporary lokal.'];
        }

        try {
            $this->ftp()->download($remotePath, $tmpFile);
            $this->ftp()->close();

            $fileSize = file_exists($tmpFile) ? filesize($tmpFile) : 0;
            if ($fileSize === false || $fileSize <= 0) {
                throw new \RuntimeException('File outbound kosong atau gagal terbaca.');
            }

            return [
                'type' => 'file',
                'path' => $tmpFile,
                'filename' => $fileName,
            ];
        } catch (\Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $this->ftp()->close();

            $receiver = $this->fetchOutboundReceiverDownload((string) $filing['nomor_admin'], $fileName);
            if ($receiver !== null) {
                return $receiver;
            }

            $publicUrl = $this->buildOutboundPublicUrl($fileName);
            if ($publicUrl !== null) {
                return ['type' => 'redirect', 'url' => $publicUrl];
            }

            return ['type' => 'error', 'status' => 500, 'message' => 'Gagal download outbound: '.$e->getMessage()];
        }
    }

    public function filingFileDownload(int $fileId): array
    {
        $file = $this->records->getFileById($fileId);
        if (! $file) {
            return ['type' => 'error', 'status' => 404, 'message' => 'File tidak ditemukan di database.'];
        }

        $tempDir = storage_path('uploads');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tmpFile = @tempnam(is_writable($tempDir) ? $tempDir : sys_get_temp_dir(), 'ftp_dl_');
        if ($tmpFile === false) {
            return ['type' => 'error', 'status' => 500, 'message' => 'Gagal membuat file temporary lokal.'];
        }

        try {
            $this->ftp()->download((string) $file['file_path'], $tmpFile);
            $this->ftp()->close();

            $fileSize = file_exists($tmpFile) ? filesize($tmpFile) : 0;
            if ($fileSize === false || $fileSize <= 0) {
                throw new \RuntimeException('Gagal mengambil file dari FTP storage: file kosong.');
            }

            return [
                'type' => 'file',
                'path' => $tmpFile,
                'filename' => basename((string) $file['file_name']),
            ];
        } catch (\Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $this->ftp()->close();

            $publicUrl = $this->buildPublicFileUrl((string) ($file['file_path'] ?? ''));
            if ($publicUrl !== null) {
                return ['type' => 'redirect', 'url' => $publicUrl];
            }

            return ['type' => 'error', 'status' => 500, 'message' => 'Error download: '.$e->getMessage()];
        }
    }

    public function adminFolderDownload(int $filingId): array
    {
        $filing = $this->records->getFilingById($filingId);
        if (! $filing || empty($filing['nomor_admin'])) {
            return ['type' => 'error', 'status' => 404, 'message' => 'Data filing tidak ditemukan.'];
        }

        try {
            return $this->fetchReceiverBinary('download_zip', (string) $filing['nomor_admin']);
        } catch (\Throwable $e) {
            return ['type' => 'error', 'status' => 500, 'message' => 'Gagal download folder admin: '.$e->getMessage()];
        }
    }

    public function filingAdminNo(int $filingId): ?string
    {
        $filing = $this->records->getFilingById($filingId);
        if (! $filing || empty($filing['nomor_admin'])) {
            return null;
        }

        return (string) $filing['nomor_admin'];
    }

    public function saveInput(array $input, array $session): array
    {
        if (! $this->canManageBeritaAcara((int) ($session['user_id'] ?? 0))) {
            return ['type' => 'error', 'status' => 403, 'message' => 'TAD SPV tidak memiliki akses untuk mengedit data Berita Acara.'];
        }

        $userId = (int) ($session['user_id'] ?? 0);
        $spv = $this->getSupervisorData($userId);
        $nomorAdminRaw = (string) ($input['nomor_admin'] ?? '');
        $adminId = 0;
        $subAdminId = '';
        $nomorAdmin = $nomorAdminRaw;

        if (str_contains($nomorAdminRaw, '|')) {
            [$adminId, $subAdminId] = explode('|', $nomorAdminRaw, 2);
            $adminId = (int) $adminId;
            $nomorAdmin = $this->records->getAdminNoById($adminId) ?: $nomorAdminRaw;
        }

        $participantChecked = (array) ($input['participant_checked'] ?? []);
        $participantNames = (array) ($input['participant_name'] ?? []);
        $issueTexts = (array) ($input['issue_text'] ?? []);
        $issues = [];

        foreach ($participantChecked as $authId) {
            $authId = (string) $authId;
            $issues[] = [
                'authorize_id' => $authId,
                'participant_name' => (string) ($participantNames[$authId] ?? ''),
                'issue_text' => (string) ($issueTexts[$authId] ?? ''),
            ];
        }

        $recId = $this->records->saveFiling([
            'rec_id' => (int) ($input['rec_id'] ?? 0),
            'nomor_admin' => $nomorAdmin,
            'sub_admin_id' => $subAdminId,
            'admin_id' => $adminId,
            'tanggal' => (string) ($input['tanggal'] ?? date('Y-m-d')),
            'keterangan' => (string) ($input['keterangan'] ?? ''),
            'input_by' => (string) ($session['account_nm'] ?? $session['user_name'] ?? 'Guest'),
            'spv_name' => $spv['name'],
            'issues' => $issues,
        ]);

        return ['type' => 'redirect', 'url' => url('filing-system/berita-acara?status=success'), 'rec_id' => $recId];
    }

    public function deleteEntry(int $filingId, array $session): array
    {
        if (! $this->canManageBeritaAcara((int) ($session['user_id'] ?? 0))) {
            return ['type' => 'error', 'status' => 403, 'message' => 'TAD SPV tidak memiliki akses untuk menghapus data Berita Acara.'];
        }

        try {
            $files = $this->records->deleteEntry($filingId);
            foreach ($files as $file) {
                try {
                    $this->ftp()->delete((string) $file['file_path']);
                } catch (\Throwable) {
                    // Keep legacy behavior: DB delete remains valid if FTP file is already missing.
                }
            }
            $this->ftp()->close();

            return ['type' => 'redirect', 'url' => url('filing-system/berita-acara?status=deleted')];
        } catch (\Throwable $e) {
            $this->ftp()->close();

            return ['type' => 'error', 'status' => 500, 'message' => 'Error deleting: '.$e->getMessage()];
        }
    }

    public function fileAction(array $input, array $session, array $files = []): array
    {
        $action = (string) ($input['file_action'] ?? '');

        if ($action === 'upload') {
            if (! $this->canUploadBeritaAcaraFiles((int) ($session['user_id'] ?? 0))) {
                return ['type' => 'json', 'status' => 403, 'payload' => ['status' => 'error', 'msg' => 'Anda tidak memiliki akses untuk upload file Berita Acara.']];
            }

            return $this->uploadFileAction($input, $files);
        }

        if ($action === 'list') {
            return ['type' => 'json', 'payload' => $this->records->getFilesByFilingId((int) ($input['filing_id'] ?? 0))];
        }

        if ($action === 'delete') {
            if (! $this->canManageBeritaAcara((int) ($session['user_id'] ?? 0))) {
                return ['type' => 'json', 'status' => 403, 'payload' => ['status' => 'error', 'msg' => 'TAD SPV tidak memiliki akses untuk mengubah file Berita Acara.']];
            }

            $fileId = (int) ($input['file_id'] ?? 0);
            $file = $this->records->getFileById($fileId);
            if (! $file) {
                return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'File tidak ditemukan di database.']];
            }

            try {
                if ($this->ftp()->exists((string) $file['file_path'])) {
                    $deleted = $this->ftp()->delete((string) $file['file_path']);
                    if (! $deleted) {
                        throw new \RuntimeException('Server FTP menolak permintaan penghapusan file.');
                    }
                } else {
                    error_log('[FILING_DELETE_ORPHAN] File is missing on FTP but exists in DB: '.$file['file_path']);
                }

                $this->ftp()->close();
                $this->records->deleteFileById($fileId);

                return ['type' => 'json', 'payload' => ['status' => 'success']];
            } catch (\Throwable $e) {
                $this->ftp()->close();
                error_log('[FILING_DELETE_ERROR] '.$e->getMessage());

                return ['type' => 'json', 'status' => 500, 'payload' => ['status' => 'error', 'msg' => 'Gagal menghapus file dari FTP: '.$e->getMessage()]];
            }
        }

        return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'File action tidak dikenal.']];
    }

    private function uploadFileAction(array $input, array $files): array
    {
        $filingId = (int) ($input['filing_id'] ?? 0);
        $nomorAdmin = trim((string) ($input['nomor_admin'] ?? ''));
        $customName = trim((string) ($input['custom_file_name'] ?? ''));
        $fileCategory = trim((string) ($input['file_category'] ?? ''));

        if ($filingId <= 0 || $nomorAdmin === '') {
            return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'Filing ID atau nomor admin tidak valid.']];
        }

        $nomorAdmin = $this->safeFileName($nomorAdmin);
        $uploads = $this->normalizeUploadedFiles($files);
        if (empty($uploads)) {
            return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'File belum dipilih.']];
        }

        if ($fileCategory === 'crc_individual') {
            if (count($uploads) > self::CRC_INDIVIDUAL_MAX_FILES) {
                return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'Maksimal '.self::CRC_INDIVIDUAL_MAX_FILES.' file .CRC sekali upload.']];
            }

            $totalSize = array_sum(array_map(static fn ($file): int => (int) $file->getSize(), $uploads));
            if ($totalSize > self::CRC_INDIVIDUAL_MAX_TOTAL_SIZE) {
                return ['type' => 'json', 'payload' => ['status' => 'error', 'msg' => 'Total ukuran file CRC maksimal 10MB sekali upload.']];
            }
        }

        $dateCode = $fileCategory === 'crc_individual' ? $this->getFilingDateCode($filingId) : date('Ymd');
        $uploaded = [];
        $errors = [];
        $httpUploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $crcReceiverFiles = [];

        foreach ($uploads as $index => $file) {
            $originalName = basename((string) $file->getClientOriginalName());
            if (! $file->isValid()) {
                $errors[] = ['file_name' => $originalName, 'message' => $this->getUploadErrorMessage((int) $file->getError())];

                continue;
            }

            $tmpName = $file->getRealPath() ?: $file->getPathname();
            $fileSize = (int) $file->getSize();
            if ($tmpName === '' || ! is_readable($tmpName) || $fileSize <= 0) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File kosong atau gagal terbaca.'];

                continue;
            }

            if ($fileCategory === 'crc_individual' && $fileSize > self::CRC_INDIVIDUAL_MAX_FILE_SIZE) {
                $errors[] = ['file_name' => $originalName, 'message' => 'File CRC lebih dari 1MB.'];

                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($fileCategory === 'crc_individual' && $ext !== 'crc') {
                $errors[] = ['file_name' => $originalName, 'message' => 'Kategori CRC Individu hanya menerima file .CRC.'];

                continue;
            }
            if ($fileCategory !== 'crc_individual' && $this->isBlockedUploadExtension($ext)) {
                $errors[] = ['file_name' => $originalName, 'message' => 'Tipe file ".'.$ext.'" tidak diizinkan.'];

                continue;
            }

            $displayName = $customName !== '' && count($uploads) === 1 ? $customName : pathinfo($originalName, PATHINFO_FILENAME);
            $cleanDisplayName = $this->safeFileName($displayName);
            $newName = $fileCategory === 'crc_individual'
                ? ($cleanDisplayName !== '' ? $cleanDisplayName : 'CRC_'.date('His').'_'.$index).'.CRC'
                : date('Ymd_His').'_'.$nomorAdmin.'_'.$cleanDisplayName.'.'.$ext;
            $ftpFolder = $fileCategory === 'crc_individual' ? $nomorAdmin.'/'.$dateCode.'/CRC' : $nomorAdmin.'/'.$dateCode.'/Documents';
            $ftpFilePath = $this->ftp()->normalizePath($ftpFolder.'/'.$newName);

            try {
                if ($httpUploadUrl !== '' && $fileCategory === 'crc_individual') {
                    $crcReceiverFiles[] = compact('tmpName', 'newName', 'fileSize', 'ftpFilePath');

                    continue;
                }

                if ($httpUploadUrl !== '') {
                    $this->uploadFileViaHttpReceiver($tmpName, $nomorAdmin, $newName, $fileSize, $this->getHttpReceiverType($fileCategory, $ext), $httpUploadUrl, $dateCode);
                } else {
                    $this->ftp()->upload($tmpName, $ftpFilePath);
                }

                $this->records->insertFile(['filing_id' => $filingId, 'file_name' => $newName, 'file_path' => $ftpFilePath, 'file_type' => $fileCategory === 'crc_individual' ? 'crc' : $ext, 'file_category' => $fileCategory]);
                $uploaded[] = ['file_name' => $newName, 'file_path' => $ftpFilePath];
            } catch (\Throwable $e) {
                $errors[] = ['file_name' => $originalName, 'message' => $e->getMessage()];
            }
        }

        // CRC individual receiver bulk is intentionally sent after validation, matching legacy behavior.
        foreach ($crcReceiverFiles as $file) {
            try {
                $this->uploadFileViaHttpReceiver($file['tmpName'], $nomorAdmin, $file['newName'], (int) $file['fileSize'], 'crc_individual', $httpUploadUrl, $dateCode);
                $this->records->insertFile(['filing_id' => $filingId, 'file_name' => $file['newName'], 'file_path' => $file['ftpFilePath'], 'file_type' => 'crc', 'file_category' => 'crc_individual']);
                $uploaded[] = ['file_name' => $file['newName'], 'file_path' => $file['ftpFilePath']];
            } catch (\Throwable $e) {
                $errors[] = ['file_name' => $file['newName'], 'message' => $e->getMessage()];
            }
        }

        $this->ftp()->close();

        return ['type' => 'json', 'payload' => ['status' => ! empty($uploaded) ? 'success' : 'error', 'msg' => count($uploaded).' file berhasil diupload'.(! empty($errors) ? ', '.count($errors).' gagal.' : '.'), 'uploaded_count' => count($uploaded), 'failed_count' => count($errors), 'files' => $uploaded, 'errors' => $errors]];
    }

    private function canManageBeritaAcara(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($this->userHasTadRole($userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])) {
            return true;
        }

        return ! $this->userHasTadRole($userId, ['TAD SPV']);
    }

    private function canUploadBeritaAcaraFiles(int $userId): bool
    {
        return $this->canManageBeritaAcara($userId) || $this->userHasTadRole($userId, ['TAD SPV']);
    }

    private function normalizeUploadedFiles(array $files): array
    {
        $uploads = [];
        foreach (['file', 'files'] as $key) {
            if (! isset($files[$key])) {
                continue;
            }

            $value = $files[$key];
            foreach (is_array($value) ? $value : [$value] as $file) {
                if ($file instanceof UploadedFile) {
                    $uploads[] = $file;
                }
            }
        }

        return $uploads;
    }

    private function getFilingDateCode(int $filingId): string
    {
        $filing = $this->records->getFilingById($filingId);
        $timestamp = $filing && ! empty($filing['tanggal']) ? strtotime((string) $filing['tanggal']) : false;

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

    private function uploadFileViaHttpReceiver(string $tmpName, string $nomorAdmin, string $fileName, int $fileSize, string $type, string $uploadUrl, string $uploadDateFolder): void
    {
        if (! function_exists('curl_init')) {
            throw new \RuntimeException('cURL tidak tersedia untuk upload via HTTP receiver.');
        }

        $mimeType = $type === 'berita_acara' ? 'application/pdf' : 'application/zip';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpName);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        $headers = ['Accept: application/json'];
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
            throw new \RuntimeException('Gagal initialisasi cURL untuk upload HTTP receiver.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'type' => $type,
                'admin_no' => $nomorAdmin,
                'test_date' => $uploadDateFolder,
                'tanggal' => $uploadDateFolder,
                'file_size' => (string) $fileSize,
                'file' => new \CURLFile($tmpName, $mimeType, $fileName),
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
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = is_string($response) && trim($response) !== '' ? json_decode($response, true) : null;
        if ($errno !== 0 || $status < 200 || $status >= 300 || ! is_array($result) || empty($result['success'])) {
            $message = is_array($result) && isset($result['message']) ? $result['message'] : substr(trim(strip_tags((string) $response)), 0, 200);
            throw new \RuntimeException($errno !== 0 ? 'cURL error upload HTTP receiver: '.$error : 'Server HTTP receiver error (HTTP '.$status.'): '.$message);
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
            default => 'Error code: '.$errorCode,
        };
    }

    public function userHasTadRole(int $userId, array $allowedRoles): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stmt = $this->pdoRun->prepare('
            SELECT UPPER(g.grpdesc) AS role_name
            FROM sysitc_usracc ua
            JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
            WHERE ua.user_rec_id = ?
        ');
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
            $roleName = (string) ($role['role_name'] ?? '');
            if ($roleName === 'SUPER ADMIN' && in_array('SUPER ADMIN', $allowedRoles, true)) {
                return true;
            }

            foreach ($allowedRoles as $allowedRole) {
                if ($allowedRole !== 'SUPER ADMIN' && str_contains($roleName, $allowedRole)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getSupervisorData(int $userId): array
    {
        if ($userId <= 0) {
            return ['id' => null, 'name' => '-'];
        }

        $spv = $this->records->getSupervisorByUserId($userId);

        return [
            'id' => $spv ? (int) $spv['rec_id'] : null,
            'name' => $spv['spv_name'] ?? '-',
        ];
    }

    private function getLiveFinishedParticipants(array $filing): array
    {
        if (empty($filing['nomor_admin']) || empty($filing['sub_admin_id'])) {
            return [];
        }

        $adminId = $this->records->getAdminIdByAdminNo((string) $filing['nomor_admin']);
        if (! $adminId) {
            return [];
        }

        $psyskunci = $this->shiftString(trim((string) $filing['nomor_admin']), 3);
        $liveFinished = [];
        foreach ($this->records->getFinishedParticipantsBySubAdmin($adminId, (int) $filing['sub_admin_id']) as $row) {
            $liveFinished[] = [
                'id' => $row['authorize'],
                'name' => $this->decryptNisnValue(trim((string) $row['regnm']), $psyskunci),
                'status' => (string) trim((string) $row['statrec']),
                'sisa_waktu' => 'Selesai',
            ];
        }

        return $liveFinished;
    }

    private function decryptNisnValue(string $value, string $key = '1C9o7b0a0T3e0b5ak'): string
    {
        if ($value === '') {
            return $value;
        }

        $key = strtoupper($key);
        $keyLength = strlen($key);
        if ($keyLength === 0) {
            return $value;
        }

        $maxLength = 10240;
        if (strlen($value) > $maxLength) {
            $suffix = substr($value, $maxLength);
            $value = substr($value, 0, $maxLength);
        } else {
            $suffix = '';
        }

        $decrypted = '';
        for ($i = 0; $i < strlen($value); $i++) {
            $charCode = ord($value[$i]);
            if ($charCode >= 43 && $charCode <= 255) {
                $keyChar = $key[($i + 1) % $keyLength];
                $decoded = $charCode - (ord($keyChar) % 15);
                if ($decoded < 43) {
                    $decoded = $decoded + 255 - 42;
                }
                $decrypted .= chr($decoded);
            } else {
                $decrypted .= $value[$i];
            }
        }

        return $decrypted.$suffix;
    }

    private function shiftString(string $value, int $shift): string
    {
        if ($value === '' || $shift === 0) {
            return $value;
        }

        $right = $shift > 0;
        $shift = abs($shift);
        $length = strlen($value);

        for ($i = 0; $i < $shift; $i++) {
            $value = $right
                ? substr($value, -1).substr($value, 0, $length - 1)
                : substr($value, 1, $length - 1).substr($value, 0, 1);
        }

        return $value;
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
                'admin_no' => $this->safeFileName($adminNo),
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

        return is_array($result['files_by_category']) ? $result['files_by_category'] : null;
    }

    private function safeFileName(string $value): string
    {
        if ($this->ftp === null) {
            $this->ftp = new FtpStorage($this->ftpConfig);
        }

        return $this->ftp->safeFileName($value);
    }

    private function getOutboundFilesForFiling(array $filing): array
    {
        $adminNo = trim((string) ($filing['nomor_admin'] ?? ''));
        $filingId = (int) ($filing['rec_id'] ?? 0);

        if ($adminNo === '' || $filingId <= 0) {
            return [];
        }

        try {
            $fileNames = $this->ftp()->listFiles($this->getOutboundFolder());
            $this->ftp()->close();
        } catch (\Throwable $e) {
            $this->ftp()->close();
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
            if (! $this->isValidOutboundFileName($adminNo, (string) $fileName)) {
                continue;
            }

            $fileName = basename(str_replace('\\', '/', (string) $fileName));
            $files[] = [
                'file_id' => null,
                'file_name' => $fileName,
                'file_type' => 'zip',
                'file_category' => 'outbound',
                'uploaded_at' => '',
                'download_url' => url('filing-system/berita-acara?download_outbound='.$filingId.'&file='.rawurlencode($fileName)),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

        return $files;
    }

    private function ftp(): FtpStorage
    {
        if ($this->ftp === null) {
            $this->ftp = new FtpStorage($this->ftpConfig);
        }

        return $this->ftp;
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

        return preg_match('/^'.preg_quote($adminNo, '/').'-.*-DATA\.ZIP$/i', $fileName) === 1;
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

    private function fetchOutboundFilesViaReceiver(string $adminNo, int $filingId = 0): array
    {
        return $this->fetchOutboundReceiverPayload($adminNo, $filingId)['files'] ?? [];
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
                'admin_no' => $this->safeFileName($adminNo),
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
            if (! is_array($file)) {
                continue;
            }

            $fileName = (string) ($file['file_name'] ?? '');
            if (! $this->isValidOutboundFileName($adminNo, $fileName)) {
                continue;
            }

            $file['file_id'] = null;
            $file['file_type'] = 'zip';
            $file['file_category'] = 'outbound';
            if ($filingId > 0) {
                $file['download_url'] = url('filing-system/berita-acara?download_outbound='.$filingId.'&file='.rawurlencode($fileName));
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

    private function fetchOutboundReceiverDownload(string $adminNo, string $fileName): ?array
    {
        $receiverUrl = trim((string) env('OUTBOUND_RECEIVER_URL', 'https://cbt.toeic.or.id/docs/CBT/CRC/outbound_receiver.php'));
        if ($receiverUrl === '' || ! function_exists('curl_init')) {
            return null;
        }

        $token = trim((string) env('OUTBOUND_RECEIVER_TOKEN', env('CRC_HTTP_UPLOAD_TOKEN', 'annas123')));
        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($receiverUrl);
        if (! $ch) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => 'download',
                'admin_no' => $this->safeFileName($adminNo),
                'file' => $fileName,
            ],
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

        if ($errno !== 0 || ! is_string($response) || $status < 200 || $status >= 300) {
            error_log('[OUTBOUND_RECEIVER_DOWNLOAD_ERROR] status='.$status.' errno='.$errno.' error='.$error);

            return null;
        }

        $body = substr($response, $headerSize);
        if ($body === '') {
            return null;
        }

        return [
            'type' => 'content',
            'content' => $body,
            'filename' => $fileName,
        ];
    }

    private function fetchReceiverBinary(string $action, string $adminNo): array
    {
        $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
        $token = trim((string) env('CRC_HTTP_UPLOAD_TOKEN', ''));

        if ($uploadUrl === '' || ! function_exists('curl_init')) {
            throw new \RuntimeException('Konfigurasi HTTP receiver belum tersedia.');
        }

        $headers = ['Accept: application/zip'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
            throw new \RuntimeException('Gagal initialisasi cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'action' => $action,
                'admin_no' => $this->safeFileName($adminNo),
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
            throw new \RuntimeException($error ?: 'Receiver tidak merespons.');
        }

        $body = substr($response, $headerSize);
        if ($status < 200 || $status >= 300) {
            $json = json_decode($body, true);
            $message = is_array($json) && isset($json['message']) ? $json['message'] : substr(strip_tags($body), 0, 200);

            throw new \RuntimeException($message);
        }

        return [
            'type' => 'content',
            'content' => $body,
            'filename' => $this->safeFileName($adminNo).'.zip',
        ];
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

    private function getCrcRawFilesForFiling(array $filing): array
    {
        $adminNo = trim((string) ($filing['nomor_admin'] ?? ''));
        $filingId = (int) ($filing['rec_id'] ?? 0);

        if ($adminNo === '' || $filingId <= 0) {
            return [];
        }

        $safeAdminNo = $this->safeFileName($adminNo);

        try {
            $fileNames = $this->ftp()->listFiles($safeAdminNo);
            $this->ftp()->close();
        } catch (\Throwable $e) {
            $this->ftp()->close();
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
                'download_url' => url('filing-system/berita-acara?download_crc_raw='.$filingId.'&file='.rawurlencode($fileName)),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

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

    private function buildPublicFileUrl(string $filePath): ?string
    {
        $baseUrl = trim((string) env('FTP_PUBLIC_BASE_URL', ''));
        if ($baseUrl === '') {
            $baseUrl = 'https://cbt.toeic.or.id/docs/CBT/CRC';
        }

        $filePath = trim((string) preg_replace('#/+#', '/', str_replace('\\', '/', trim($filePath))));
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

            return rtrim((string) $httpBaseUrl, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $filePath)));
        }

        if (strpos($filePath, 'CRC/') === 0) {
            $filePath = substr($filePath, 4);
        }

        return rtrim($baseUrl, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $filePath)));
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
                    $filesByCategory['crc_individual'][] = $file;

                    continue;
                }

                if (preg_match('#(^|/)(GrabCRC|[0-9]{8})/Rev-[^/]+\.CRC$#i', $relativePath)) {
                    $file['file_type'] = 'crc';
                    $file['file_category'] = 'crc_gabungan';
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
            $unique[$key !== '' ? $key : uniqid('crc_raw_', true)] = $file;
        }

        $filesByCategory['crc_raw'] = array_values($unique);

        return $filesByCategory;
    }
}
