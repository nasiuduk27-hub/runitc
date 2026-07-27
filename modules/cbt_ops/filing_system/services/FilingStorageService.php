<?php
// File: modules/cbt_ops/filing_system/services/FilingStorageService.php

require_once BASE_PATH . '/classes/FtpStorage.php';

class FilingStorageService
{
    private FtpStorage $ftp;
    private string $localStoragePath = '';
    private bool $httpUploadEnabled = false;
    private string $httpUploadUrl = '';
    private string $httpUploadToken = '';
    private int $httpUploadTimeout = 30;

    /**
     * Root utama. Folder berikutnya otomatis menjadi:
     * filing_storage_A, filing_storage_B, ..., filing_storage_Z, filing_storage_AA, dst.
     */
    private string $storageRoot = 'filing_storage';

    /**
     * File kecil untuk menyimpan folder aktif dan pemakaian folder.
     * Lokasinya di root FTP yang sama dengan filing_storage.
     */
    private string $manifestPath = 'filing_storage_manifest.json';

    /**
     * Batas per folder. Bisa diubah dari environment/config server:
     * FILING_STORAGE_MAX_BYTES=5368709120  // 5 GB
     * FILING_STORAGE_MAX_FILES=5000
     *
     * Isi 0 untuk menonaktifkan limit tertentu.
     */
    private int $maxFolderBytes = 5368709120; // default 5 GB
    private int $maxFolderFiles = 5000;

    /**
     * @param array $ftpConfig Konfigurasi dari config.php ($ftp_config)
     */
    public function __construct(array $ftpConfig)
    {
        // Native FTP can create folders here, but file transfer fails before cURL succeeds.
        // Go straight to cURL to avoid the native FTP timeout penalty on every upload.
        $ftpConfig['upload_curl_first'] = false;
        $ftpConfig['upload_direct_curl'] = true;
        $ftpConfig['upload_direct_curl_only'] = true;
        $ftpConfig['upload_direct_curl_only_max_bytes'] = 1048576;
        $ftpConfig['upload_direct_curl_min_bytes'] = 0;
        $ftpConfig['upload_curl_first_min_bytes'] = 0;
        $ftpConfig['upload_curl_prefer_passive'] = true;
        $ftpConfig['upload_curl_epsv_first'] = false;
        $ftpConfig['upload_curl_create_missing_dirs'] = true;
        $ftpConfig['upload_curl_response_timeout'] = 300;
        $ftpConfig['upload_curl_modes'] = [
            ['active' => false, 'epsv' => false],
        ];
        $ftpConfig['upload_curl_fallback'] = $ftpConfig['upload_curl_fallback'] ?? true;
        $ftpConfig['upload_attempt_timeout'] = min(15, (int) ($ftpConfig['upload_attempt_timeout'] ?? 10));
        $ftpConfig['upload_timeout'] = max(300, (int) ($ftpConfig['upload_timeout'] ?? 300));

        $this->ftp = new FtpStorage($ftpConfig);

        $maxBytes = getenv('FILING_STORAGE_MAX_BYTES');
        if ($maxBytes !== false && is_numeric($maxBytes)) {
            $this->maxFolderBytes = max(0, (int) $maxBytes);
        }

        $maxFiles = getenv('FILING_STORAGE_MAX_FILES');
        if ($maxFiles !== false && is_numeric($maxFiles)) {
            $this->maxFolderFiles = max(0, (int) $maxFiles);
        }

        $localPath = getenv('FILING_LOCAL_STORAGE_PATH');
        if ($localPath !== false && $localPath !== '') {
            $this->localStoragePath = rtrim(str_replace('\\', '/', $localPath), '/');
        }

        $this->httpUploadEnabled = filter_var(getenv('FILING_HTTP_UPLOAD_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN);
        $this->httpUploadUrl = trim((string) (getenv('FILING_HTTP_UPLOAD_URL') ?: ''));
        $this->httpUploadToken = (string) (getenv('FILING_HTTP_UPLOAD_TOKEN') ?: '');

        $httpTimeout = getenv('FILING_HTTP_UPLOAD_TIMEOUT');
        if ($httpTimeout !== false && is_numeric($httpTimeout)) {
            $this->httpUploadTimeout = max(5, min(300, (int) $httpTimeout));
        }
    }

    /**
     * Generate kode file metadata dari rec_id (sys_filing.file_code).
     * Contoh: rec_id 125 menjadi FS-00000125.
     */
    public function generateFileCode(int $recId): string
    {
        return 'FS-' . str_pad((string) $recId, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Generate metadata untuk penyimpanan fisik.
     *
     * Path baru tidak lagi memakai folder tanggal.
     * Contoh:
     * - filing_storage/nama_file_A8F21C.zip
     * - filing_storage_A/nama_file_B91C2D.zip
     *
     * @param string $originalName Nama file asli dari user
     * @param int|null $incomingBytes Ukuran file yang akan diupload. Opsional, tapi disarankan
     *                                agar rollover folder bisa terjadi sebelum upload.
     * @return array Meta data: random_code, storage_dir, storage_name, storage_path
     */
    public function generateStorageMeta(string $originalName, ?int $incomingBytes = null): array
    {
        $randomCode = strtoupper(bin2hex(random_bytes(4)));
        $activeRoot = $this->storageRoot;

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        // Jika tidak ada ekstensi, default ke zip supaya bisa dibuka di Windows Explorer.
        if ($ext === '') {
            $ext = 'zip';
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/\s+/', '_', $baseName);
        $baseName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $baseName);
        $baseName = trim((string) $baseName, '._-');
        if ($baseName === '') {
            $baseName = 'file_' . date('Ymd_His');
        }

        // Karena sekarang semua file ada di 1 folder aktif, beri suffix unik supaya tidak bentrok.
        $storageName = $baseName . '_' . $randomCode . '.' . $ext;
        $storageDir = $activeRoot;
        $storagePath = $storageDir . '/' . $storageName;

        if ($this->isLocalStorageEnabled()) {
            $storagePath = 'local/' . $storagePath;
        }

        return [
            'random_code' => $randomCode,
            'storage_dir' => $storageDir,
            'storage_name' => $storageName,
            'storage_path' => $storagePath,
            'original_name' => $originalName,
        ];
    }

    private function isLocalStorageEnabled(): bool
    {
        return $this->localStoragePath !== '';
    }

    /**
     * Memastikan folder storage ada di FTP.
     * Catatan: FtpStorage->upload sudah memanggil makeDirectoryRecursive secara internal.
     */
    public function ensureStorageDirectory(string $storageDir): void
    {
        // Secara teknis sudah dihandle oleh FtpStorage::upload
        // Namun jika butuh verifikasi manual, bisa dikembangkan di sini
    }

    /**
     * Mengirim file dari temporary lokal ke storage FTP.
     *
     * @param string $tmpPath Path file di server lokal (hasil upload)
     * @param string $storagePath Path hasil generateStorageMeta.
     *                            Path baru sudah berisi root, contoh filing_storage/file.zip.
     *                            Path lama YYYY/MM/... tetap dibaca untuk kompatibilitas file lama.
     * @return bool
     */
    public function moveUploadedFile(string $tmpPath, string $storagePath): bool
    {
        try {
            if ($this->isLocalStorageEnabled()) {
                $localRelPath = ltrim(preg_replace('#^local/#', '', $storagePath), '/');
                $fullPath = $this->localStoragePath . '/' . $localRelPath;
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                return copy($tmpPath, $fullPath);
            }

            if ($this->httpUploadEnabled) {
                $uploadedViaHttp = $this->uploadViaHttpApi($tmpPath, $storagePath);
                if ($uploadedViaHttp) {
                    return true;
                }

                error_log('[FilingStorageService] HTTP upload dummy failed, falling back to FTP: ' . $storagePath);
            }

            $fullRemotePath = $this->resolveRemotePath($storagePath);
            $uploaded = $this->ftp->upload($tmpPath, $fullRemotePath);
            return $uploaded;
        } catch (Exception $e) {
            error_log('[FilingStorageService] Upload Error: ' . $e->getMessage());
            throw $e;
        } finally {
            $this->ftp->close();
        }
    }

    private function uploadViaHttpApi(string $tmpPath, string $storagePath): bool
    {
        if ($this->httpUploadUrl === '' || !function_exists('curl_init') || !is_file($tmpPath)) {
            return false;
        }

        $fileName = basename($storagePath);
        $mimeType = 'application/zip';
        if (function_exists('mime_content_type')) {
            $detectedMime = @mime_content_type($tmpPath);
            if (is_string($detectedMime) && $detectedMime !== '') {
                $mimeType = $detectedMime;
            }
        }

        $postFields = [
            'storage_path' => $storagePath,
            'storage_dir' => dirname($storagePath),
            'file_name' => $fileName,
            'file_size' => (string) filesize($tmpPath),
            'file' => new CURLFile($tmpPath, $mimeType, $fileName),
        ];

        $headers = ['Accept: application/json'];
        if ($this->httpUploadToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->httpUploadToken;
        }

        $startedAt = microtime(true);
        $ch = curl_init($this->httpUploadUrl);
        if (!$ch) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->httpUploadTimeout),
            CURLOPT_TIMEOUT => $this->httpUploadTimeout,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 1);
        $success = $errno === 0 && $status >= 200 && $status < 300;
        $this->writeDebugLog('[HTTP_UPLOAD_DUMMY] success=' . ($success ? '1' : '0') . " status={$status} errno={$errno} duration={$durationMs}ms path={$storagePath} error={$error}");

        if (!$success) {
            return false;
        }

        if (is_string($response) && trim($response) !== '') {
            $json = json_decode($response, true);
            if (is_array($json) && array_key_exists('success', $json)) {
                return filter_var($json['success'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return true;
    }

    private function writeDebugLog(string $message): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        @file_put_contents($dir . '/filing_upload_debug.log', date('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Membuat salinan file dari FTP ke folder temp lokal untuk keperluan inspeksi.
     * Mengembalikan path lokal atau false jika gagal.
     */
    public function createTemporaryCopy(string $storagePath): string|bool
    {
        $tempDir = sys_get_temp_dir();
        $localPath = $tempDir . '/inspect_' . time() . '_' . bin2hex(random_bytes(4)) . '.zip';

        if ($this->moveFromFtpToLocal($storagePath, $localPath)) {
            if (file_exists($localPath)) {
                return $localPath;
            }
        }

        return false;
    }

    /**
     * Mendownload file dari FTP ke path lokal.
     *
     * @param string $storagePath Relative/full path di FTP.
     * @param string $localPath Path tujuan di server lokal.
     * @return bool
     */
    public function moveFromFtpToLocal(string $storagePath, string $localPath): bool
    {
        try {
            if ($this->isLocalStorageEnabled()) {
                $localRelPath = ltrim(preg_replace('#^local/#', '', $storagePath), '/');
                $fullPath = $this->localStoragePath . '/' . $localRelPath;
                if (!file_exists($fullPath)) {
                    error_log('[FilingStorageService] Local file not found: ' . $fullPath);
                    return false;
                }
                $localDir = dirname($localPath);
                if (!is_dir($localDir)) {
                    @mkdir($localDir, 0775, true);
                }
                return copy($fullPath, $localPath);
            }

            $fullRemotePath = $this->resolveRemotePath($storagePath);
            return $this->ftp->download($fullRemotePath, $localPath);
        } catch (Exception $e) {
            error_log('[FilingStorageService] Download Error: ' . $e->getMessage());
            return $this->downloadFromPublicUrl($storagePath, $localPath);
        } finally {
            $this->ftp->close();
        }
    }

    private function downloadFromPublicUrl(string $storagePath, string $localPath): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $baseUrl = trim((string) (getenv('FTP_PUBLIC_BASE_URL') ?: ''));
        if ($baseUrl === '') {
            return false;
        }

        $path = $this->resolveRemotePath($storagePath);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');
        if (strpos($path, 'CRC/') === 0) {
            $path = substr($path, 4);
        }

        $url = rtrim($baseUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0775, true);
        }

        $fp = @fopen($localPath, 'wb');
        if (!$fp) {
            return false;
        }

        $ch = curl_init($url);
        if (!$ch) {
            fclose($fp);
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        $size = file_exists($localPath) ? filesize($localPath) : 0;
        if ($ok && $size !== false && $size > 0) {
            error_log("[FilingStorageService] HTTP fallback download success: {$url} size={$size}");
            return true;
        }

        if (file_exists($localPath)) {
            @unlink($localPath);
        }
        error_log("[FilingStorageService] HTTP fallback download failed: {$url} error={$error}");
        return false;
    }

    /**
     * Menghapus file fisik dari storage FTP.
     *
     * @param string $storagePath
     * @return bool
     */
    public function deletePhysicalFile(string $storagePath): bool
    {
        try {
            $fullRemotePath = $this->resolveRemotePath($storagePath);
            return $this->ftp->delete($fullRemotePath);
        } catch (Exception $e) {
            error_log('[FilingStorageService] Delete Error: ' . $e->getMessage());
            return false;
        } finally {
            $this->ftp->close();
        }
    }

    /**
     * Mendapatkan ukuran file remote.
     */
    public function getFileSize(string $storagePath): int
    {
        try {
            $fullRemotePath = $this->resolveRemotePath($storagePath);
            return $this->ftp->size($fullRemotePath);
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Menentukan root folder yang bisa ditulis.
     * Jika folder aktif sudah penuh, otomatis pindah ke folder berikutnya.
     */
    private function getWritableStorageRoot(?int $incomingBytes = null): string
    {
        $manifest = $this->readManifest();
        $activeRoot = (string) ($manifest['active_root'] ?? $this->storageRoot);
        $folder = $manifest['folders'][$activeRoot] ?? $this->newFolderInfo();

        if ($this->isFolderOverLimit($folder, $incomingBytes)) {
            $manifest = $this->moveManifestToNextFolder($manifest);
            $this->writeManifest($manifest);
            $activeRoot = (string) $manifest['active_root'];
        }

        return $activeRoot;
    }

    private function registerUploadInManifest(string $fullRemotePath, int $bytes): void
    {
        $folderRoot = $this->extractStorageRoot($fullRemotePath);
        if ($folderRoot === '') {
            $folderRoot = $this->storageRoot;
        }

        $manifest = $this->readManifest();
        if (!isset($manifest['folders'][$folderRoot]) || !is_array($manifest['folders'][$folderRoot])) {
            $manifest['folders'][$folderRoot] = $this->newFolderInfo();
        }

        $manifest['folders'][$folderRoot]['used_bytes'] = (int) ($manifest['folders'][$folderRoot]['used_bytes'] ?? 0) + max(0, $bytes);
        $manifest['folders'][$folderRoot]['file_count'] = (int) ($manifest['folders'][$folderRoot]['file_count'] ?? 0) + 1;
        $manifest['folders'][$folderRoot]['updated_at'] = date('c');

        // Kalau upload ini membuat folder penuh, file tetap tersimpan di folder ini,
        // lalu upload berikutnya akan memakai folder baru.
        if ($this->isFolderOverLimit($manifest['folders'][$folderRoot], null)) {
            $manifest['folders'][$folderRoot]['closed_at'] = date('c');

            $currentIndex = $this->folderIndexFromRoot($folderRoot);
            $activeIndex = (int) ($manifest['active_index'] ?? 0);
            $nextIndex = max($activeIndex, $currentIndex) + 1;
            $nextRoot = $this->storageRootFromIndex($nextIndex);

            $manifest['active_index'] = $nextIndex;
            $manifest['active_root'] = $nextRoot;
            if (!isset($manifest['folders'][$nextRoot])) {
                $manifest['folders'][$nextRoot] = $this->newFolderInfo();
            }
        } else {
            $manifest['active_index'] = $this->folderIndexFromRoot($folderRoot);
            $manifest['active_root'] = $folderRoot;
        }

        $this->writeManifest($manifest);
    }

    private function readManifest(): array
    {
        $default = [
            'version' => 1,
            'root_base' => $this->storageRoot,
            'active_index' => 0,
            'active_root' => $this->storageRoot,
            'folders' => [
                $this->storageRoot => $this->newFolderInfo(),
            ],
        ];

        $tempFile = $this->manifestTempPath('read');

        try {
            if (!$this->ftp->download($this->manifestPath, $tempFile)) {
                return $default;
            }

            $json = is_file($tempFile) ? file_get_contents($tempFile) : false;
            if ($json === false || trim($json) === '') {
                return $default;
            }

            $data = json_decode($json, true);
            if (!is_array($data)) {
                return $default;
            }

            return $this->normalizeManifest($data);
        } catch (Throwable $e) {
            return $default;
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function writeManifest(array $manifest): void
    {
        $manifest = $this->normalizeManifest($manifest);
        $manifest['updated_at'] = date('c');

        $tempFile = $this->manifestTempPath('write');
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents($tempFile, $json);

        try {
            $this->ftp->upload($tempFile, $this->manifestPath);
        } catch (Throwable $e) {
            error_log('[FilingStorageService] Manifest write failed: ' . $e->getMessage());
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function normalizeManifest(array $manifest): array
    {
        $manifest['version'] = 1;
        $manifest['root_base'] = $this->storageRoot;
        $manifest['active_index'] = isset($manifest['active_index']) ? max(0, (int) $manifest['active_index']) : 0;
        $manifest['active_root'] = isset($manifest['active_root']) && is_string($manifest['active_root'])
            ? $manifest['active_root']
            : $this->storageRootFromIndex((int) $manifest['active_index']);

        if (!isset($manifest['folders']) || !is_array($manifest['folders'])) {
            $manifest['folders'] = [];
        }

        if (!isset($manifest['folders'][$manifest['active_root']]) || !is_array($manifest['folders'][$manifest['active_root']])) {
            $manifest['folders'][$manifest['active_root']] = $this->newFolderInfo();
        }

        foreach ($manifest['folders'] as $root => $info) {
            if (!is_array($info)) {
                $info = [];
            }
            $manifest['folders'][$root] = array_merge($this->newFolderInfo(), $info, [
                'used_bytes' => isset($info['used_bytes']) ? max(0, (int) $info['used_bytes']) : 0,
                'file_count' => isset($info['file_count']) ? max(0, (int) $info['file_count']) : 0,
            ]);
        }

        return $manifest;
    }

    private function moveManifestToNextFolder(array $manifest): array
    {
        $manifest = $this->normalizeManifest($manifest);
        $currentIndex = (int) ($manifest['active_index'] ?? 0);
        $currentRoot = (string) ($manifest['active_root'] ?? $this->storageRootFromIndex($currentIndex));

        if (isset($manifest['folders'][$currentRoot])) {
            $manifest['folders'][$currentRoot]['closed_at'] = date('c');
        }

        $nextIndex = $currentIndex + 1;
        $nextRoot = $this->storageRootFromIndex($nextIndex);

        $manifest['active_index'] = $nextIndex;
        $manifest['active_root'] = $nextRoot;
        if (!isset($manifest['folders'][$nextRoot])) {
            $manifest['folders'][$nextRoot] = $this->newFolderInfo();
        }

        return $manifest;
    }

    private function newFolderInfo(): array
    {
        return [
            'used_bytes' => 0,
            'file_count' => 0,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    private function isFolderOverLimit(array $folder, ?int $incomingBytes = null): bool
    {
        $usedBytes = (int) ($folder['used_bytes'] ?? 0);
        $fileCount = (int) ($folder['file_count'] ?? 0);

        $nextBytes = $usedBytes + max(0, (int) ($incomingBytes ?? 0));
        $nextFileCount = $fileCount + ($incomingBytes !== null ? 1 : 0);

        if ($this->maxFolderBytes > 0 && $nextBytes >= $this->maxFolderBytes) {
            return true;
        }

        if ($this->maxFolderFiles > 0 && $nextFileCount >= $this->maxFolderFiles) {
            return true;
        }

        return false;
    }

    private function resolveRemotePath(string $storagePath): string
    {
        $path = str_replace('\\', '/', trim($storagePath));
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim((string) $path, '/');

        if ($path === '') {
            throw new InvalidArgumentException('Storage path kosong.');
        }

        // Format baru: filing_storage/file.zip atau filing_storage_A/file.zip.
        if ($this->isStorageRootPath($path)) {
            return $path;
        }

        // Kalau hanya nama file tanpa slash, anggap sebagai file di root utama baru.
        // Contoh: nama_file.zip => filing_storage/nama_file.zip
        if (strpos($path, '/') === false) {
            return $this->storageRoot . '/' . $path;
        }

        // Kompatibilitas data lama: dulu storage_path berisi subpath tanpa root,
        // contoh 2026/06/AB/file.zip, lalu root ditambahkan saat operasi FTP.
        return $this->storageRoot . '/' . $path;
    }

    private function isStorageRootPath(string $path): bool
    {
        return (bool) preg_match('/^' . preg_quote($this->storageRoot, '/') . '(_[A-Z]+)?\//', $path);
    }

    private function extractStorageRoot(string $fullRemotePath): string
    {
        $path = str_replace('\\', '/', trim($fullRemotePath));
        $path = ltrim((string) preg_replace('#/+#', '/', $path), '/');
        $parts = explode('/', $path, 2);
        $root = $parts[0] ?? '';

        if ($root === $this->storageRoot || preg_match('/^' . preg_quote($this->storageRoot, '/') . '(_[A-Z]+)?$/', $root)) {
            return $root;
        }

        return '';
    }

    private function folderIndexFromRoot(string $root): int
    {
        if ($root === $this->storageRoot) {
            return 0;
        }

        $prefix = $this->storageRoot . '_';
        if (strpos($root, $prefix) !== 0) {
            return 0;
        }

        $letters = substr($root, strlen($prefix));
        if ($letters === '' || !preg_match('/^[A-Z]+$/', $letters)) {
            return 0;
        }

        $index = 0;
        $chars = str_split($letters);
        foreach ($chars as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index;
    }

    private function storageRootFromIndex(int $index): string
    {
        if ($index <= 0) {
            return $this->storageRoot;
        }

        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }

        return $this->storageRoot . '_' . $letters;
    }

    private function manifestTempPath(string $prefix): string
    {
        $tempDir = BASE_PATH . '/storage/uploads/filing_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        return $tempDir . '/manifest_' . $prefix . '_' . getmypid() . '_' . bin2hex(random_bytes(4)) . '.json';
    }
}
