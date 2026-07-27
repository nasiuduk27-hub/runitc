<?php

class FtpStorage
{
    private array $config;
    private $connection = null;
    private string $homeDir = '/';
    private bool $isPassiveMode = true;
    private array $knownDirectories = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        if (!function_exists('ftp_connect')) {
            throw new Exception('Extension FTP PHP belum aktif di server ini.');
        }

        $host = trim($this->config['host'] ?? '');
        $user = trim($this->config['user'] ?? '');
        $pass = (string) ($this->config['pass'] ?? '');
        $port = (int) ($this->config['port'] ?? 21);
        $timeout = (int) ($this->config['timeout'] ?? 60);
        $useSsl = (bool) ($this->config['ssl'] ?? false);

        if ($host === '' || $user === '' || $pass === '') {
            throw new Exception('Konfigurasi FTP belum lengkap.');
        }

        $conn = false;

        if ($useSsl && function_exists('ftp_ssl_connect')) {
            $conn = @ftp_ssl_connect($host, $port, $timeout);
        }

        if (!$conn) {
            $conn = @ftp_connect($host, $port, $timeout);
        }

        if (!$conn) {
            throw new Exception("Tidak dapat terhubung ke FTP {$host}:{$port}");
        }

        if (!@ftp_login($conn, $user, $pass)) {
            @ftp_close($conn);
            throw new Exception('Login FTP gagal. Periksa username/password FTP.');
        }

        @ftp_set_option($conn, FTP_TIMEOUT_SEC, $timeout);

        $autoDetectMode = filter_var($this->config['auto_detect_mode'] ?? getenv('FTP_AUTO_DETECT_MODE') ?: false, FILTER_VALIDATE_BOOLEAN);

        if ($autoDetectMode) {
            @ftp_pasv($conn, true);

            // Use a short temporary timeout (3 seconds) to test directory listing.
            @ftp_set_option($conn, FTP_TIMEOUT_SEC, 3);
            $list = @ftp_nlist($conn, '.');

            if ($list === false) {
                @ftp_pasv($conn, false);
                $this->isPassiveMode = false;
                error_log('[FTP_CONNECT] Passive mode listing failed. Switched to Active mode.');
            } else {
                $this->isPassiveMode = true;
                error_log('[FTP_CONNECT] Passive mode listing succeeded.');
            }
        } else {
            $passiveMode = array_key_exists('passive_mode', $this->config)
                ? $this->config['passive_mode']
                : (getenv('FTP_PASSIVE_MODE') !== false ? getenv('FTP_PASSIVE_MODE') : true);
            $this->isPassiveMode = filter_var($passiveMode, FILTER_VALIDATE_BOOLEAN);
            @ftp_pasv($conn, $this->isPassiveMode);
        }

        // Restore user-defined timeout
        @ftp_set_option($conn, FTP_TIMEOUT_SEC, $timeout);

        $this->connection = $conn;

        $pwd = @ftp_pwd($conn);
        $this->homeDir = $pwd ?: '/';

        error_log('[FTP_CONNECTED] pwd=' . $this->homeDir . ' mode=' . ($this->isPassiveMode ? 'passive' : 'active'));
    }

    public function upload(string $localFile, string $remotePath): bool
    {
        $uploadTimeout = $this->getUploadTimeout();
        $attemptTimeout = $this->getUploadAttemptTimeout();

        if (!is_file($localFile)) {
            throw new Exception("File lokal tidak ditemukan: {$localFile}");
        }

        if (!is_readable($localFile)) {
            throw new Exception("File lokal tidak bisa dibaca: {$localFile}");
        }

        $localSize = filesize($localFile);

        if ($localSize === false || $localSize <= 0) {
            throw new Exception("File lokal kosong atau gagal terbaca.");
        }

        $remotePath = $this->normalizePath($remotePath);
        $remoteDir = dirname($remotePath);

        if ($this->shouldUseDirectCurlUpload($localSize) && function_exists('curl_init')) {
            $stageStart = microtime(true);
            error_log('[FTP_UPLOAD] Trying direct cURL upload...');
            $uploaded = $this->uploadWithCurl($localFile, $remotePath, $localSize);
            $this->logUploadStage('direct_curl', $stageStart, $uploaded, $remotePath);

            if ($uploaded) {
                return $this->verifyUploadedSize($remotePath, $localSize);
            }

            if ($this->shouldStopAfterDirectCurlFailure($localSize)) {
                throw new Exception(
                    "Transfer file gagal ke path {$remotePath}. Upload HTTP FTP/cURL gagal sebelum fallback native FTP. " .
                    "Cek koneksi FTP atau endpoint alternatif."
                );
            }

            error_log('[FTP_UPLOAD] Direct cURL upload failed. Falling back to native FTP upload...');
        }

        $stageStart = microtime(true);
        $this->ensureConnected();
        $this->logUploadStage('connect', $stageStart, true, $remotePath);
        @ftp_set_option($this->connection, FTP_TIMEOUT_SEC, $attemptTimeout);

        $stageStart = microtime(true);
        $this->makeDirectoryRecursive($remoteDir);
        $this->logUploadStage('mkdir', $stageStart, true, $remotePath);

        if ($this->shouldUseCurlUploadFirst($localSize) && function_exists('curl_init')) {
            $stageStart = microtime(true);
            error_log('[FTP_UPLOAD] Trying cURL upload first...');
            @ftp_set_option($this->connection, FTP_TIMEOUT_SEC, $uploadTimeout);
            $uploaded = $this->uploadWithCurl($localFile, $remotePath, $localSize);
            $this->logUploadStage('curl_first', $stageStart, $uploaded, $remotePath);

            if ($uploaded) {
                return true;
            }

            error_log('[FTP_UPLOAD] cURL upload failed. Falling back to native FTP upload...');
        }

        // Ensure passive/active mode is set according to our connection detection
        @ftp_pasv($this->connection, $this->isPassiveMode);

        if (function_exists('ftp_alloc')) {
            @ftp_alloc($this->connection, $localSize);
        }

        // Try putting file using ftp_put
        $stageStart = microtime(true);
        $uploaded = @ftp_put($this->connection, $remotePath, $localFile, FTP_BINARY);
        $this->logUploadStage('ftp_put_primary', $stageStart, $uploaded, $remotePath);

        // Fallback: try using ftp_fput (using stream handler)
        if (!$uploaded) {
            $handle = @fopen($localFile, 'rb');
            if ($handle) {
                $stageStart = microtime(true);
                $uploaded = @ftp_fput($this->connection, $remotePath, $handle, FTP_BINARY);
                $this->logUploadStage('ftp_fput_primary', $stageStart, $uploaded, $remotePath);
                fclose($handle);
            }
        }

        // Fallback: toggle transfer mode once and try again.
        if (!$uploaded) {
            $fallbackPassiveMode = !$this->isPassiveMode;
            error_log('[FTP_UPLOAD] Upload failed. Trying ' . ($fallbackPassiveMode ? 'Passive' : 'Active') . ' mode fallback...');
            @ftp_pasv($this->connection, $fallbackPassiveMode);

            $stageStart = microtime(true);
            $uploaded = @ftp_put($this->connection, $remotePath, $localFile, FTP_BINARY);
            $this->logUploadStage('ftp_put_mode_fallback', $stageStart, $uploaded, $remotePath);
            if (!$uploaded) {
                $handle = @fopen($localFile, 'rb');
                if ($handle) {
                    $stageStart = microtime(true);
                    $uploaded = @ftp_fput($this->connection, $remotePath, $handle, FTP_BINARY);
                    $this->logUploadStage('ftp_fput_mode_fallback', $stageStart, $uploaded, $remotePath);
                    fclose($handle);
                }
            }
            
            // Restore configured mode
            @ftp_pasv($this->connection, $this->isPassiveMode);
        }

        if (!$uploaded) {
            error_log('[FTP_UPLOAD] Direct path upload failed. Trying chdir basename fallback...');
            $stageStart = microtime(true);
            $uploaded = $this->uploadByChdirFallback($localFile, $remotePath);
            $this->logUploadStage('chdir_fallback', $stageStart, $uploaded, $remotePath);
        }

        if (!$uploaded && function_exists('ftp_nb_put')) {
            error_log('[FTP_UPLOAD] Trying non-blocking upload fallback...');
            @ftp_pasv($this->connection, $this->isPassiveMode);
            $stageStart = microtime(true);
            $ret = @ftp_nb_put($this->connection, $remotePath, $localFile, FTP_BINARY);

            while ($ret === FTP_MOREDATA) {
                $ret = @ftp_nb_continue($this->connection);
            }

            $uploaded = ($ret === FTP_FINISHED);
            $this->logUploadStage('ftp_nb_put', $stageStart, $uploaded, $remotePath);
        }

        if (!$uploaded && $this->shouldUseCurlUploadFallback() && function_exists('curl_init')) {
            error_log('[FTP_UPLOAD] Native FTP upload failed. Trying cURL fallback...');
            @ftp_set_option($this->connection, FTP_TIMEOUT_SEC, $uploadTimeout);
            $stageStart = microtime(true);
            $uploaded = $this->uploadWithCurl($localFile, $remotePath, $localSize);
            $this->logUploadStage('curl_fallback', $stageStart, $uploaded, $remotePath);
        }

        if (!$uploaded) {
            throw new Exception(
                "Transfer file gagal ke path {$remotePath}. Folder bisa dibuat, tapi file tidak bisa dikirim. " .
                "FTP tidak merespons dalam {$uploadTimeout} detik. " .
                "Cek FTP quota, permission, passive port, firewall, atau root path FTP."
            );
        }

        return $this->verifyUploadedSize($remotePath, $localSize);
    }

    private function logUploadStage(string $stage, float $startedAt, bool $success, string $remotePath): void
    {
        $durationMs = round((microtime(true) - $startedAt) * 1000, 1);
        $message = "[FTP_UPLOAD_STAGE] stage={$stage} success=" . ($success ? '1' : '0') . " duration={$durationMs}ms path={$remotePath}";
        error_log($message);
        $this->writeFilingDebugLog($message);
    }

    private function writeFilingDebugLog(string $message): void
    {
        if (!defined('BASE_PATH')) {
            return;
        }

        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        @file_put_contents($dir . '/filing_upload_debug.log', date('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function verifyUploadedSize(string $remotePath, int $localSize): bool
    {
        $remoteSize = $this->size($remotePath);

        if ($remoteSize <= 0) {
            throw new Exception("Upload terlihat berhasil, tapi file di FTP tidak terbaca atau 0 byte: {$remotePath}");
        }

        if ($remoteSize !== $localSize) {
            throw new Exception(
                "Upload file ke FTP tidak lengkap: ukuran lokal {$localSize} bytes, " .
                "ukuran remote {$remoteSize} bytes. Path: {$remotePath}"
            );
        }

        return true;
    }

    private function uploadWithCurl(string $localFile, string $remotePath, int $localSize): bool
    {
        $host = trim((string) ($this->config['host'] ?? ''));
        $user = trim((string) ($this->config['user'] ?? ''));
        $pass = (string) ($this->config['pass'] ?? '');
        $port = (int) ($this->config['port'] ?? 21);
        $timeout = $this->getUploadTimeout();
        $useSsl = (bool) ($this->config['ssl'] ?? false);

        if ($host === '' || $user === '') {
            return false;
        }

        $host = preg_replace('#^ftps?://#i', '', $host);
        $host = rtrim($host, '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', trim($remotePath, '/'))));
        $scheme = $useSsl ? 'ftps' : 'ftp';
        $url = "{$scheme}://{$host}:{$port}/{$encodedPath}";

        $epsvFirstValue = array_key_exists('upload_curl_epsv_first', $this->config)
            ? $this->config['upload_curl_epsv_first']
            : (getenv('FTP_UPLOAD_CURL_EPSV_FIRST') !== false ? getenv('FTP_UPLOAD_CURL_EPSV_FIRST') : true);
        $epsvFirst = filter_var($epsvFirstValue, FILTER_VALIDATE_BOOLEAN);
        $passiveMode = array_key_exists('upload_curl_prefer_passive', $this->config)
            ? $this->config['upload_curl_prefer_passive']
            : null;
        if ($passiveMode === null) {
            $envCurlPassive = getenv('FTP_UPLOAD_CURL_PREFER_PASSIVE');
            $passiveMode = $envCurlPassive !== false ? $envCurlPassive : null;
        }
        if ($passiveMode === null) {
            $passiveMode = array_key_exists('passive_mode', $this->config)
            ? $this->config['passive_mode']
            : (getenv('FTP_PASSIVE_MODE') !== false ? getenv('FTP_PASSIVE_MODE') : true);
        }
        $preferPassive = filter_var($passiveMode, FILTER_VALIDATE_BOOLEAN);
        $passiveModes = $epsvFirst ? [true, false] : [false, true];
        $transferModes = [];
        if (!$preferPassive) {
            $transferModes[] = ['active' => true, 'epsv' => false];
        }
        foreach ($passiveModes as $useEpsv) {
            $transferModes[] = ['active' => false, 'epsv' => $useEpsv];
        }
        if ($preferPassive) {
            $transferModes[] = ['active' => true, 'epsv' => false];
        }

        if (isset($this->config['upload_curl_modes']) && is_array($this->config['upload_curl_modes'])) {
            $transferModes = $this->config['upload_curl_modes'];
        }

        foreach ($transferModes as $mode) {
            $useEpsv = (bool) $mode['epsv'];
            $useActive = (bool) $mode['active'];
            $curlStageStart = microtime(true);
            $fp = @fopen($localFile, 'rb');
            if (!$fp) {
                return false;
            }

            $ch = curl_init($url);
            if (!$ch) {
                fclose($fp);
                return false;
            }

            $curlTimeout = $this->getCurlUploadTimeout($localSize);

            $options = [
                CURLOPT_USERPWD => $user . ':' . $pass,
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fp,
                CURLOPT_INFILESIZE => $localSize,
                CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
                CURLOPT_TIMEOUT => $curlTimeout,
                CURLOPT_FTP_USE_EPSV => $useEpsv,
                CURLOPT_FAILONERROR => true,
            ];

            if (defined('CURLOPT_FTP_RESPONSE_TIMEOUT')) {
                $options[CURLOPT_FTP_RESPONSE_TIMEOUT] = $this->getCurlFtpResponseTimeout($localSize);
            }

            if ($useActive) {
                $options[CURLOPT_FTPPORT] = '-';
            }

            $createMissingDirs = filter_var($this->config['upload_curl_create_missing_dirs'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if ($createMissingDirs && defined('CURLOPT_FTP_CREATE_MISSING_DIRS')) {
                $options[CURLOPT_FTP_CREATE_MISSING_DIRS] = true;
            }

            if ($useSsl && defined('CURLUSESSL_ALL')) {
                $options[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
            }

            curl_setopt_array($ch, $options);

            $ok = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);

            curl_close($ch);
            fclose($fp);

            if ($ok && $errno === 0) {
                $remoteSize = $this->connection ? @ftp_size($this->connection, $remotePath) : -1;
                $sizeInfo = ($remoteSize === $localSize) ? 'verified' : 'pending_verify';
                $message = "[FTP_UPLOAD_CURL_SUCCESS] path={$remotePath} size={$localSize} mode=" . ($useActive ? 'active' : 'passive') . " epsv=" . ($useEpsv ? '1' : '0') . " duration=" . round((microtime(true) - $curlStageStart) * 1000, 1) . "ms {$sizeInfo}";
                error_log($message);
                $this->writeFilingDebugLog($message);
                return true;
            }

            $remoteSize = $this->getRemoteSizeAfterCurlAttempt($remotePath);
            if ($remoteSize === $localSize) {
                $message = "[FTP_UPLOAD_CURL_SUCCESS] path={$remotePath} size={$localSize} mode=" . ($useActive ? 'active' : 'passive') . " epsv=" . ($useEpsv ? '1' : '0') . " duration=" . round((microtime(true) - $curlStageStart) * 1000, 1) . "ms verified_after_error";
                error_log($message);
                $this->writeFilingDebugLog($message);
                return true;
            }

            $message = "[FTP_UPLOAD_CURL_FAIL] path={$remotePath} mode=" . ($useActive ? 'active' : 'passive') . " epsv=" . ($useEpsv ? '1' : '0') . " errno={$errno} timeout={$curlTimeout} duration=" . round((microtime(true) - $curlStageStart) * 1000, 1) . "ms error={$error}";
            error_log($message);
            $this->writeFilingDebugLog($message);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                continue;
            }
        }

        return false;
    }

    private function uploadByChdirFallback(string $localFile, string $remotePath): bool
    {
        $dir = trim(dirname($remotePath), '/');
        $file = basename($remotePath);

        if ($dir === '' || $dir === '.' || $file === '') {
            return false;
        }

        $originalDir = @ftp_pwd($this->connection) ?: $this->homeDir;

        if (!@ftp_chdir($this->connection, $dir)) {
            @ftp_chdir($this->connection, $originalDir);
            return false;
        }

        @ftp_pasv($this->connection, $this->isPassiveMode);
        $uploaded = @ftp_put($this->connection, $file, $localFile, FTP_BINARY);

        if (!$uploaded) {
            $handle = @fopen($localFile, 'rb');
            if ($handle) {
                $uploaded = @ftp_fput($this->connection, $file, $handle, FTP_BINARY);
                fclose($handle);
            }
        }

        if (!$uploaded) {
            @ftp_pasv($this->connection, false);
            $uploaded = @ftp_put($this->connection, $file, $localFile, FTP_BINARY);
            @ftp_pasv($this->connection, $this->isPassiveMode);
        }

        @ftp_chdir($this->connection, $originalDir);
        return $uploaded;
    }

    private function getCurlUploadTimeout(int $localSize): int
    {
        $baseTimeout = $this->getUploadTimeout();
        $sizeBasedTimeout = (int) ceil($localSize / 1024 / 64) + 90;

        return max(60, min(300, max($baseTimeout, $sizeBasedTimeout)));
    }

    private function getCurlFtpResponseTimeout(int $localSize): int
    {
        $timeout = getenv('FTP_UPLOAD_CURL_RESPONSE_TIMEOUT');
        if ($timeout === false || !is_numeric($timeout)) {
            $timeout = $this->config['upload_curl_response_timeout'] ?? 8;
        }

        $sizeBasedTimeout = (int) ceil($localSize / 1024 / 64) + 60;

        return max(30, min(300, max((int) $timeout, $sizeBasedTimeout)));
    }

    private function getRemoteSizeAfterCurlAttempt(string $remotePath): int
    {
        if ($this->connection) {
            $size = @ftp_size($this->connection, $remotePath);
            return is_int($size) ? $size : -1;
        }

        try {
            $this->ensureConnected();
            return $this->size($remotePath);
        } catch (Throwable $e) {
            return -1;
        }
    }

    public function getUploadTimeout(): int
    {
        $timeout = (int) ($this->config['upload_timeout'] ?? 55);

        return max(15, $timeout);
    }

    private function getUploadAttemptTimeout(): int
    {
        $timeout = getenv('FTP_UPLOAD_ATTEMPT_TIMEOUT');
        if ($timeout === false || !is_numeric($timeout)) {
            $timeout = $this->config['upload_attempt_timeout'] ?? 5;
        }

        return max(5, min(20, (int) $timeout));
    }

    private function shouldUseCurlUploadFallback(): bool
    {
        $value = getenv('FTP_UPLOAD_CURL_FALLBACK');
        if ($value === false) {
            $value = $this->config['upload_curl_fallback'] ?? true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function shouldUseCurlUploadFirst(int $localSize): bool
    {
        $value = getenv('FTP_UPLOAD_CURL_FIRST');
        if ($value === false) {
            $value = $this->config['upload_curl_first'] ?? false;
        }

        if (!filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $minBytes = getenv('FTP_UPLOAD_CURL_FIRST_MIN_BYTES');
        if ($minBytes === false || !is_numeric($minBytes)) {
            $minBytes = $this->config['upload_curl_first_min_bytes'] ?? 307200;
        }

        return $localSize >= max(0, (int) $minBytes);
    }

    private function shouldUseDirectCurlUpload(int $localSize): bool
    {
        $value = getenv('FTP_UPLOAD_DIRECT_CURL');
        if ($value === false) {
            $value = $this->config['upload_direct_curl'] ?? false;
        }

        if (!filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $minBytes = getenv('FTP_UPLOAD_DIRECT_CURL_MIN_BYTES');
        if ($minBytes === false || !is_numeric($minBytes)) {
            $minBytes = $this->config['upload_direct_curl_min_bytes'] ?? 0;
        }

        return $localSize >= max(0, (int) $minBytes);
    }

    private function shouldStopAfterDirectCurlFailure(int $localSize): bool
    {
        $value = getenv('FTP_UPLOAD_DIRECT_CURL_ONLY');
        if ($value === false) {
            $value = $this->config['upload_direct_curl_only'] ?? false;
        }

        if (!filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $maxBytes = getenv('FTP_UPLOAD_DIRECT_CURL_ONLY_MAX_BYTES');
        if ($maxBytes === false || !is_numeric($maxBytes)) {
            $maxBytes = $this->config['upload_direct_curl_only_max_bytes'] ?? 1048576;
        }

        return $localSize <= max(0, (int) $maxBytes);
    }

    public function download(string $remotePath, string $localFile): bool
    {
        $this->ensureConnected();

        $localDir = dirname($localFile);

        if (!is_dir($localDir)) {
            if (!@mkdir($localDir, 0775, true) && !is_dir($localDir)) {
                throw new Exception("Gagal membuat folder temporary lokal: {$localDir}");
            }
        }

        if (!is_writable($localDir)) {
            throw new Exception("Folder temporary lokal tidak writable: {$localDir}");
        }

        @ftp_pasv($this->connection, $this->isPassiveMode);

        $candidates = $this->getPathCandidates($remotePath);
        $lastDetail = '';

        foreach ($candidates as $path) {
            /*
             * Beberapa FTP server mengizinkan ftp_get/ftp_delete,
             * tetapi command SIZE bisa mengembalikan -1.
             * Jadi jangan skip download hanya karena ftp_size gagal.
             */
            $remoteSize = @ftp_size($this->connection, $path);

            if (file_exists($localFile)) {
                @unlink($localFile);
            }

            $downloaded = @ftp_get($this->connection, $localFile, $path, FTP_BINARY);

            if (!$downloaded) {
                $handle = @fopen($localFile, 'wb');
                if ($handle) {
                    $downloaded = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);
                    fclose($handle);
                }
            }

            if (!$downloaded) {
                @ftp_pasv($this->connection, false);

                $downloaded = @ftp_get($this->connection, $localFile, $path, FTP_BINARY);
                if (!$downloaded) {
                    $handle = @fopen($localFile, 'wb');
                    if ($handle) {
                        $downloaded = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);
                        fclose($handle);
                    }
                }

                @ftp_pasv($this->connection, $this->isPassiveMode);
            }

            if (!$downloaded && function_exists('ftp_nb_get')) {
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }

                $ret = @ftp_nb_get($this->connection, $localFile, $path, FTP_BINARY);

                while ($ret === FTP_MOREDATA) {
                    $ret = @ftp_nb_continue($this->connection);
                }

                $downloaded = ($ret === FTP_FINISHED);
            }

            if (!$downloaded) {
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }

                $downloaded = $this->downloadByChdirFallback($path, $localFile);
            }

            if (!$downloaded && function_exists('curl_init')) {
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }

                $downloaded = $this->downloadWithCurlFallback($path, $localFile);
            }

            if (!$downloaded) {
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }

                $downloaded = $this->downloadBySteppedChdirFallback($path, $localFile);
            }

            $downloadedSize = file_exists($localFile) ? filesize($localFile) : 0;

            if ($downloaded && $downloadedSize !== false && $downloadedSize > 0) {
                error_log("[FTP_DOWNLOAD_SUCCESS] path={$path} size={$downloadedSize}");
                return true;
            }

            if (file_exists($localFile)) {
                @unlink($localFile);
            }

            $sizeInfo = (is_int($remoteSize) && $remoteSize > 0) ? "remote size={$remoteSize}" : "remote size tidak terbaca";
            $lastDetail = "Transfer gagal atau file lokal kosong dari path: {$path} ({$sizeInfo})";
        }

        throw new Exception(
            "Gagal mengambil file dari FTP storage: " . trim((string) $remotePath, '/') .
            ". Detail terakhir: {$lastDetail}. Path dicoba: " . implode(', ', $candidates)
        );
    }

    private function downloadByChdirFallback(string $remotePath, string $localFile): bool
    {
        $dir = trim(dirname($remotePath), '/');
        $file = basename($remotePath);

        if ($dir === '' || $dir === '.' || $file === '') {
            return false;
        }

        $originalDir = @ftp_pwd($this->connection) ?: $this->homeDir;

        if (!@ftp_chdir($this->connection, $dir)) {
            return false;
        }

        @ftp_pasv($this->connection, $this->isPassiveMode);

        $downloaded = @ftp_get($this->connection, $localFile, $file, FTP_BINARY);

        if (!$downloaded) {
            $handle = @fopen($localFile, 'wb');
            if ($handle) {
                $downloaded = @ftp_fget($this->connection, $handle, $file, FTP_BINARY);
                fclose($handle);
            }
        }

        if (!$downloaded) {
            @ftp_pasv($this->connection, false);

            $downloaded = @ftp_get($this->connection, $localFile, $file, FTP_BINARY);
            if (!$downloaded) {
                $handle = @fopen($localFile, 'wb');
                if ($handle) {
                    $downloaded = @ftp_fget($this->connection, $handle, $file, FTP_BINARY);
                    fclose($handle);
                }
            }

            @ftp_pasv($this->connection, $this->isPassiveMode);
        }

        if (!@ftp_chdir($this->connection, $originalDir)) {
            @ftp_chdir($this->connection, $this->homeDir);
        }

        if ($downloaded) {
            $size = file_exists($localFile) ? filesize($localFile) : 0;
            error_log("[FTP_DOWNLOAD_CHDIR_FALLBACK] path={$remotePath} size={$size}");
        }

        return (bool) $downloaded;
    }

    private function downloadBySteppedChdirFallback(string $remotePath, string $localFile): bool
    {
        $dir = trim(dirname($remotePath), '/');
        $file = basename($remotePath);

        if ($dir === '' || $dir === '.' || $file === '') {
            return false;
        }

        $originalDir = @ftp_pwd($this->connection) ?: $this->homeDir;

        @ftp_chdir($this->connection, $this->homeDir);

        foreach (explode('/', $dir) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!@ftp_chdir($this->connection, $part)) {
                @ftp_chdir($this->connection, $originalDir);
                return false;
            }
        }

        @ftp_pasv($this->connection, $this->isPassiveMode);
        $downloaded = @ftp_get($this->connection, $localFile, $file, FTP_BINARY);

        if (!$downloaded) {
            $handle = @fopen($localFile, 'wb');
            if ($handle) {
                $downloaded = @ftp_fget($this->connection, $handle, $file, FTP_BINARY);
                fclose($handle);
            }
        }

        if (!$downloaded) {
            @ftp_pasv($this->connection, false);
            $downloaded = @ftp_get($this->connection, $localFile, $file, FTP_BINARY);

            if (!$downloaded) {
                $handle = @fopen($localFile, 'wb');
                if ($handle) {
                    $downloaded = @ftp_fget($this->connection, $handle, $file, FTP_BINARY);
                    fclose($handle);
                }
            }

            @ftp_pasv($this->connection, $this->isPassiveMode);
        }

        @ftp_chdir($this->connection, $originalDir);

        if ($downloaded) {
            $size = file_exists($localFile) ? filesize($localFile) : 0;
            error_log("[FTP_DOWNLOAD_STEPPED_CHDIR_FALLBACK] path={$remotePath} size={$size}");
        }

        return (bool) $downloaded;
    }

    private function downloadWithCurlFallback(string $remotePath, string $localFile): bool
    {
        $host = trim((string) ($this->config['host'] ?? ''));
        $user = trim((string) ($this->config['user'] ?? ''));
        $pass = (string) ($this->config['pass'] ?? '');
        $port = (int) ($this->config['port'] ?? 21);
        $timeout = (int) ($this->config['timeout'] ?? 60);
        $useSsl = (bool) ($this->config['ssl'] ?? false);

        if ($host === '' || $user === '') {
            return false;
        }

        $host = preg_replace('#^ftps?://#i', '', $host);
        $host = rtrim($host, '/');

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', trim($remotePath, '/'))));
        $scheme = $useSsl ? 'ftps' : 'ftp';
        $url = "{$scheme}://{$host}:{$port}/{$encodedPath}";

        foreach ([true, false] as $useEpsv) {
            $fp = @fopen($localFile, 'wb');
            if (!$fp) {
                return false;
            }

            $ch = curl_init($url);
            if (!$ch) {
                fclose($fp);
                return false;
            }

            curl_setopt_array($ch, [
                CURLOPT_USERPWD => $user . ':' . $pass,
                CURLOPT_FILE => $fp,
                CURLOPT_CONNECTTIMEOUT => min(15, max(5, $timeout)),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FTP_USE_EPSV => $useEpsv,
                CURLOPT_FAILONERROR => true,
            ]);

            if ($useSsl && defined('CURLUSESSL_ALL')) {
                curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            }

            $ok = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);

            curl_close($ch);
            fclose($fp);

            $size = file_exists($localFile) ? filesize($localFile) : 0;

            if ($ok && $errno === 0 && $size !== false && $size > 0) {
                error_log("[FTP_DOWNLOAD_CURL_SUCCESS] path={$remotePath} size={$size} epsv=" . ($useEpsv ? '1' : '0'));
                return true;
            }

            if (file_exists($localFile)) {
                @unlink($localFile);
            }

            error_log("[FTP_DOWNLOAD_CURL_FAIL] path={$remotePath} epsv=" . ($useEpsv ? '1' : '0') . " error={$error}");
        }

        return false;
    }

    private function getPathCandidates(string $remotePath): array
    {
        $cleanPath = trim($remotePath);
        $cleanPath = str_replace('\\\\', '/', $cleanPath);
        $cleanPath = preg_replace('#/+#', '/', $cleanPath);
        $cleanPath = preg_replace('#(^|/)\\./#', '$1', $cleanPath);
        $cleanPath = trim($cleanPath, '/');

        $basePath = $this->getBasePath();
        $candidates = [];

        $add = static function (?string $path) use (&$candidates): void {
            $path = trim((string) $path);
            $path = str_replace('\\\\', '/', $path);
            $path = preg_replace('#/+#', '/', $path);
            $path = trim($path, '/');

            if ($path !== '' && !in_array($path, $candidates, true)) {
                $candidates[] = $path;
            }
        };

        // 1. Path sesuai konfigurasi FTP_PATH sekarang.
        $add($this->normalizePath($cleanPath));

        // 2. Path yang tersimpan di database apa adanya.
        $add($cleanPath);

        // 3. Jika path sudah mengandung base seperti CRC/..., coba juga tanpa CRC/...
        if ($basePath !== '') {
            foreach ($candidates as $candidate) {
                if ($candidate === $basePath) {
                    continue;
                }

                if (strpos($candidate, $basePath . '/') === 0) {
                    $add(substr($candidate, strlen($basePath) + 1));
                }
            }
        }

        // 4. Tetap dukung record lama saat FTP root pernah berada di CBT/CRC,
        //    tanpa memaksa upload baru memakai struktur lama.
        $legacyPrefixes = ['internal_filing', 'CRC'];

        foreach ($candidates as $candidate) {
            foreach ($legacyPrefixes as $prefix) {
                if (strpos($candidate, $prefix . '/') === 0) {
                    $add(substr($candidate, strlen($prefix) + 1));
                } elseif ($basePath !== '') {
                    $add($prefix . '/' . $candidate);
                }
            }
        }

        return $candidates;
    }

    public function delete(string $remotePath): bool
    {
        $this->ensureConnected();

        $remotePath = $this->normalizePath($remotePath);

        return @ftp_delete($this->connection, $remotePath);
    }

    public function size(string $remotePath): int
    {
        $this->ensureConnected();

        $remotePath = $this->normalizePath($remotePath);

        $size = @ftp_size($this->connection, $remotePath);

        return is_int($size) ? $size : -1;
    }

    public function exists(string $remotePath): bool
    {
        return $this->size($remotePath) > 0;
    }

    public function listFiles(string $remoteDir): array
    {
        $result = $this->listFilesWithDiagnostics($remoteDir);

        return $result['files'] ?? [];
    }

    public function listFilesWithDiagnostics(string $remoteDir): array
    {
        $this->ensureConnected();

        @ftp_pasv($this->connection, $this->isPassiveMode);

        $remoteDir = trim($remoteDir);
        $remoteDir = str_replace('\\\\', '/', $remoteDir);
        $remoteDir = preg_replace('#/+#', '/', $remoteDir);
        $remoteDir = trim($remoteDir, '/');

        $candidates = [];
        $addCandidate = static function (?string $path) use (&$candidates): void {
            $path = trim((string) $path);
            $path = str_replace('\\\\', '/', $path);
            $path = preg_replace('#/+#', '/', $path);

            if ($path !== '/' && str_starts_with($path, '/')) {
                $path = '/' . trim($path, '/');
            } else {
                $path = trim($path, '/');
            }

            if ($path !== '' && !in_array($path, $candidates, true)) {
                $candidates[] = $path;
            }
        };

        $addCandidate($this->normalizePath($remoteDir));
        $addCandidate($remoteDir);

        $rootPath = trim((string) ($this->config['root_path'] ?? ''));
        if ($rootPath !== '') {
            $addCandidate(rtrim($rootPath, '/\\') . '/' . $remoteDir);
        }

        $ftpPath = trim((string) ($this->config['path'] ?? ''));
        if ($ftpPath !== '' && preg_match('#/CRC$#i', str_replace('\\\\', '/', rtrim($ftpPath, '/\\')))) {
            $addCandidate(preg_replace('#/CRC$#i', '/' . $remoteDir, str_replace('\\\\', '/', rtrim($ftpPath, '/\\'))));
        }

        if (strcasecmp($remoteDir, 'OUTBOUND') === 0) {
            $addCandidate('CBT/OUTBOUND');
            $addCandidate('docs/CBT/OUTBOUND');
            $addCandidate('cbt.toeic.or.id/docs/CBT/OUTBOUND');
            $addCandidate('/www/wwwroot/cbt.toeic.or.id/docs/CBT/OUTBOUND');
            $addCandidate('www/wwwroot/cbt.toeic.or.id/docs/CBT/OUTBOUND');
        }

        $originalDir = @ftp_pwd($this->connection) ?: $this->homeDir;
        $rootList = @ftp_nlist($this->connection, '.');
        $diagnostics = [
            'pwd' => $originalDir,
            'remote_dir' => $remoteDir,
            'root_nlist_ok' => is_array($rootList),
            'root_nlist_sample' => is_array($rootList) ? array_slice($rootList, 0, 50) : [],
            'candidates' => $candidates,
            'attempts' => [],
            'files' => [],
        ];

        foreach ($candidates as $dir) {
            $items = @ftp_nlist($this->connection, $dir);
            $attempt = [
                'path' => $dir,
                'nlist_ok' => is_array($items),
                'nlist_count' => is_array($items) ? count($items) : 0,
                'nlist_sample' => is_array($items) ? array_slice($items, 0, 20) : [],
                'chdir_ok' => false,
            ];

            if ($items === false) {
                if (@ftp_chdir($this->connection, $dir)) {
                    $attempt['chdir_ok'] = true;
                    $items = @ftp_nlist($this->connection, '.');
                    $attempt['chdir_nlist_ok'] = is_array($items);
                    $attempt['chdir_nlist_count'] = is_array($items) ? count($items) : 0;
                    $attempt['chdir_nlist_sample'] = is_array($items) ? array_slice($items, 0, 20) : [];
                    @ftp_chdir($this->connection, $originalDir);
                }
            }

            if (!is_array($items)) {
                $diagnostics['attempts'][] = $attempt;
                continue;
            }

            $files = [];
            foreach ($items as $item) {
                $name = basename(str_replace('\\\\', '/', (string) $item));
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                $files[] = $name;
            }

            if (empty($files)) {
                $attempt['files'] = [];
                $diagnostics['attempts'][] = $attempt;
                continue;
            }

            $diagnostics['attempts'][] = $attempt + ['files' => array_values(array_unique($files))];
            $diagnostics['files'] = array_values(array_unique($files));

            return $diagnostics;
        }

        return $diagnostics;
    }

    public function close(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    public function normalizePath(string $remotePath): string
    {
        $remotePath = trim($remotePath);
        $remotePath = str_replace('\\\\', '/', $remotePath);
        $remotePath = preg_replace('#/+#', '/', $remotePath);
        $remotePath = preg_replace('#(^|/)\\./#', '$1', $remotePath);
        $remotePath = ltrim($remotePath, '/');

        $basePath = $this->getBasePath();

        if ($basePath !== '') {
            // Cegah duplikasi base path, misalnya CRC/CRC/filing_system.
            if ($remotePath !== $basePath && strpos($remotePath, $basePath . '/') !== 0) {
                $remotePath = $basePath . '/' . $remotePath;
                $remotePath = preg_replace('#/+#', '/', $remotePath);
            }
        }

        return trim($remotePath, '/');
    }

    private function getBasePath(): string
    {
        $basePathRaw = trim((string) ($this->config['path'] ?? ''));

        if ($basePathRaw === '') {
            $basePathRaw = trim((string) ($this->config['root_path'] ?? ''));
        }

        $basePathRaw = str_replace('\\\\', '/', $basePathRaw);
        $basePathRaw = preg_replace('#/+#', '/', $basePathRaw);
        $basePathRaw = trim($basePathRaw, '/');

        if ($basePathRaw === '') {
            return '';
        }

        if ($basePathRaw === '.') {
            return '';
        }

        /*
         * Untuk FTP aaPanel user cbtdocs, root FTP sudah berada di:
         * /www/wwwroot/cbt.toeic.or.id/docs/CBT
         *
         * Jadi kalau .env masih terisi path absolut:
         * /www/wwwroot/cbt.toeic.or.id/docs/CBT/CRC
         *
         * yang dipakai oleh FTP cukup relatif:
         * CRC
         */
        $marker = 'docs/CBT/';
        $pos = strpos($basePathRaw, $marker);
        if ($pos !== false) {
            $afterMarker = substr($basePathRaw, $pos + strlen($marker));
            $afterMarker = trim($afterMarker, '/');
            if ($afterMarker !== '') {
                return $afterMarker;
            }
        }

        return $basePathRaw;
    }

    public function safeFileName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $name); // Keep dot for extension checks if needed, but sanitisers keep base
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '._-');

        return $name !== '' ? $name : 'file_' . date('Ymd_His');
    }

    private function ensureConnected(): void
    {
        if (!$this->connection) {
            $this->connect();
        }
    }

    private function makeDirectoryRecursive(string $dir): void
    {
        // Normalize the path recursively (it is idempotent now)
        $dir = trim($this->normalizePath($dir), '/');

        if ($dir === '' || $dir === '.') {
            return;
        }

        if (isset($this->knownDirectories[$dir])) {
            return;
        }

        $parts = explode('/', $dir);
        $path = '';

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $path = $path === '' ? $part : $path . '/' . $part;

            // Check if path is a directory we can chdir into
            if (@ftp_chdir($this->connection, $path)) {
                @ftp_chdir($this->connection, $this->homeDir);
                $this->knownDirectories[$path] = true;
                continue;
            }

            @ftp_chdir($this->connection, $this->homeDir);

            // Attempt to make directory if chdir failed
            if (!@ftp_mkdir($this->connection, $path)) {
                if (!@ftp_chdir($this->connection, $path)) {
                    throw new Exception("Gagal membuat folder FTP: {$path}");
                }
                @ftp_chdir($this->connection, $this->homeDir);
            }

            $this->knownDirectories[$path] = true;
        }

        @ftp_chdir($this->connection, $this->homeDir);
        $this->knownDirectories[$dir] = true;
    }
}
