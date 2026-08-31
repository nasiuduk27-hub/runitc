<?php

namespace App\Services\FilingSystem;

use App\Support\Legacy\FtpStorage;
use PDO;
use Throwable;

class TestDocumentCrcB2Service
{
    private FtpStorage $ftp;

    public function __construct(array $ftpConfig, private readonly ?PDO $pdoCollector = null)
    {
        $this->ftp = new FtpStorage($ftpConfig);
    }

    public function folders(array $filters = []): array
    {
        $pagination = null;
        $folders = $this->getCrcB2Folders($filters, $pagination);

        return [
            'status' => 'success',
            'folders' => $folders,
            'pagination' => $pagination,
            'diagnostics' => empty($folders) ? $this->getCrcB2FolderDiagnostics() : null,
        ];
    }

    public function files(string $adminNo): array
    {
        return [
            'status' => 'success',
            'files' => $this->getCrcB2FilesForAdmin($this->ftp->safeFileName($adminNo), false),
        ];
    }

    public function collect(string $adminNo): array
    {
        $adminNo = trim($adminNo);
        if ($adminNo === '') {
            return ['status' => 'error', 'message' => 'Nomor admin wajib diisi.'];
        }

        $files = $this->getCrcB2FilesForAdmin($adminNo, false);
        $matchedAdminNo = $adminNo;
        if (! empty($files[0]['relative_path'])) {
            $parts = explode('/', str_replace('\\', '/', (string) $files[0]['relative_path']));
            $matchedAdminNo = $parts[1] ?? $adminNo;
        }

        $summary = $this->getCrcB2Summary(['nomor_admin' => $matchedAdminNo, 'rec_id' => 0], $files);
        $summary['searched_admin_no'] = $adminNo;
        $summary['download_url'] = url('filing-system/berita-acara?download_crc_b2_admin='.rawurlencode($matchedAdminNo));
        $summary['attempted_folders'] = array_map(static fn (array $candidate): string => $candidate[0], $this->getCrcB2AdminFolderCandidates($adminNo, false));

        return [
            'status' => 'success',
            'files' => $files,
            'summary' => $summary,
        ];
    }

    public function downloadZipByAdmin(string $adminNo): array
    {
        $safeAdminNo = $this->ftp->safeFileName(trim($adminNo));
        if ($safeAdminNo === '') {
            return ['type' => 'error', 'status' => 400, 'message' => 'Nomor admin tidak valid.'];
        }

        if (! class_exists('ZipArchive')) {
            return ['type' => 'error', 'status' => 500, 'message' => 'Extension ZipArchive PHP belum aktif di server ini.'];
        }

        return $this->fetchCrcB2ReceiverZip($safeAdminNo);
    }

    private function fetchCrcB2ReceiverZip(string $safeAdminNo): array
    {
        $url = $this->getCrcB2ReceiverUrl();
        if ($url === '') {
            return ['type' => 'error', 'status' => 500, 'message' => 'Konfigurasi CRC B2 receiver belum tersedia.'];
        }

        $requestUrl = $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'action' => 'download_zip',
            'admin_no' => $safeAdminNo,
        ]);

        $headers = ['Accept: application/zip'];
        $token = trim((string) env('CRC_B2_RECEIVER_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        if (! function_exists('curl_init')) {
            return ['type' => 'redirect', 'url' => $requestUrl];
        }

        $ch = curl_init($requestUrl);
        if (! $ch) {
            return ['type' => 'redirect', 'url' => $requestUrl];
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

            return ['type' => 'redirect', 'url' => $requestUrl];
        }

        $body = substr($response, $headerSize);
        if ($body === '') {
            return ['type' => 'error', 'status' => 404, 'message' => 'File CRC B2 tidak ditemukan untuk nomor admin ini.'];
        }

        return [
            'type' => 'content',
            'content' => $body,
            'filename' => 'CRC_B2_'.$safeAdminNo.'.zip',
        ];
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
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
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

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

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

            $summary = $this->getCrcB2Summary(['nomor_admin' => $folderName, 'rec_id' => 0], $this->getCrcB2LocalFilesForAdmin($folderName));
            $summary['download_url'] = url('filing-system/berita-acara?download_crc_b2_admin='.rawurlencode($folderName));
            $folders[] = $summary;
        }

        usort($folders, static fn (array $a, array $b): int => strcasecmp((string) ($a['admin_no'] ?? ''), (string) ($b['admin_no'] ?? '')));

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

            $stmt = $this->pdoCollector->prepare("\n                SELECT\n                    admin_no,\n                    COUNT(*) AS total_files,\n                    SUM(status = 'done') AS done_files,\n                    SUM(status = 'failed') AS failed_files,\n                    SUM(status = 'processing') AS processing_files,\n                    SUM(status = 'queued') AS queued_files,\n                    MAX(updated_at) AS last_updated\n                FROM crc_upload_queue\n                {$whereSql}\n                GROUP BY admin_no\n                ORDER BY MAX(updated_at) DESC, admin_no ASC\n                LIMIT {$perPage} OFFSET {$offset}\n            ");
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
                'download_url' => url('filing-system/berita-acara?download_crc_b2_admin='.rawurlencode($adminNo)),
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

                $folder['download_url'] = url('filing-system/berita-acara?download_crc_b2_admin='.rawurlencode((string) $folder['admin_no']));
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
            $summary['download_url'] = url('filing-system/berita-acara?download_crc_b2_admin='.rawurlencode($folderName));
            $folders[] = $summary;
        }

        $this->ftp->close();
        usort($folders, static fn (array $a, array $b): int => strcasecmp((string) ($a['admin_no'] ?? ''), (string) ($b['admin_no'] ?? '')));

        return $folders;
    }

    private function getCrcB2FolderDiagnostics(): array
    {
        $diagnostics = [
            'local_root' => $this->getCrcB2LocalRoot(),
            'local_root_exists' => is_dir($this->getCrcB2LocalRoot()),
            'local_root_readable' => is_readable($this->getCrcB2LocalRoot()),
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
        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        if ($safeAdminNo === '') {
            return ['', ''];
        }

        try {
            $folders = $this->ftp->listFiles($this->getCrcB2RemoteRoot());
        } catch (Throwable $e) {
            error_log('[CRC_B2_ROOT_LIST_ERROR] '.$e->getMessage());

            return [$this->getCrcB2RemoteRoot().'/'.$safeAdminNo, $safeAdminNo];
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

        return [$this->getCrcB2RemoteRoot().'/'.$safeAdminNo, $safeAdminNo];
    }

    private function getCrcB2AdminFolderCandidates(string $adminNo, bool $includeRootScan = true): array
    {
        $candidates = [];
        $safeAdminNo = $this->ftp->safeFileName($adminNo);
        if ($safeAdminNo !== '') {
            $candidates[] = [$this->getCrcB2RemoteRoot().'/'.$safeAdminNo, $safeAdminNo];
            $upperAdminNo = strtoupper($safeAdminNo);
            if ($upperAdminNo !== $safeAdminNo) {
                $candidates[] = [$this->getCrcB2RemoteRoot().'/'.$upperAdminNo, $upperAdminNo];
            }
        }

        $numeric = preg_replace('/\D+/', '', $adminNo);
        if ($numeric !== '') {
            $numericNoZero = ltrim($numeric, '0');
            $numericNoZero = $numericNoZero !== '' ? $numericNoZero : '0';
            foreach ([4, 5, 6] as $length) {
                $candidateAdmin = 'B'.str_pad($numericNoZero, $length, '0', STR_PAD_LEFT);
                $candidates[] = [$this->getCrcB2RemoteRoot().'/'.$candidateAdmin, $candidateAdmin];
            }
            $candidates[] = [$this->getCrcB2RemoteRoot().'/B'.$numericNoZero, 'B'.$numericNoZero];
            $candidates[] = [$this->getCrcB2RemoteRoot().'/B0'.$numericNoZero, 'B0'.$numericNoZero];
        }

        if ($includeRootScan) {
            $candidates[] = $this->findCrcB2AdminFolder($adminNo);
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $unique[strtolower($candidate[0])] = $candidate;
        }

        return array_values($unique);
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

        foreach ($this->getCrcB2AdminFolderCandidates($adminNo, $includeRootScan) as [$remoteDir, $matchedAdminNo]) {
            try {
                $fileNames = $this->ftp->listFiles($remoteDir);
            } catch (Throwable) {
                continue;
            }

            $files = [];
            foreach ($fileNames as $fileName) {
                $fileName = basename(str_replace('\\', '/', (string) $fileName));
                if (! $this->isValidCrcRawFileName($fileName)) {
                    continue;
                }

                $remotePath = $remoteDir.'/'.$fileName;
                try {
                    $modifiedAt = $this->ftp->modifiedTime($remotePath);
                } catch (Throwable) {
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

            if (! empty($files)) {
                $this->ftp->close();
                usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

                return $files;
            }
        }

        $this->ftp->close();

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
            'download_url' => ! empty($filing['rec_id']) ? url('filing-system/berita-acara?download_crc_b2='.(int) $filing['rec_id']) : '',
        ];
    }

    private function isValidCrcRawFileName(string $fileName): bool
    {
        $fileName = basename(str_replace('\\', '/', $fileName));

        if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return false;
        }

        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'crc';
    }
}
