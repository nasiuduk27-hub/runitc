<?php

namespace App\Http\Controllers\CbtOps;

use App\Repositories\FilingSystem\FileSystemDrive;
use App\Repositories\FilingSystem\FilingAccess;
use App\Services\FilingSystem\FilingPermissionService;
use App\Services\FilingSystem\FilingStorageService;
use App\Services\FilingSystem\ZipValidationService;
use Exception;
use PDO;
use Throwable;
use ZipArchive;

class FilingDriveController
{
    private PDO $db;

    private FilingStorageService $storage;

    private ZipValidationService $validator;

    private FileSystemDrive $drive;

    public function __construct(PDO $pdo_run, array $ftpConfig)
    {
        $this->db = $pdo_run;
        $this->storage = new FilingStorageService($ftpConfig);
        $this->validator = new ZipValidationService;
        $this->drive = new FileSystemDrive($pdo_run);
    }

    /**
     * Aktifkan tabel baru (file_system + file_shareto) bila env flag dinyalakan.
     * Default: OFF (tetap tabel lama) agar aman untuk rollback.
     */
    public function usesNewTables(): bool
    {
        return FileSystemDrive::isEnabled();
    }

    /**
     * Handle AJAX Upload
     */
    public function handleUpload(): array
    {
        if ($this->usesNewTables()) {
            return $this->handleUploadNew();
        }

        $zipPath = null;
        $debugTiming = [];

        try {
            $debugTiming['start'] = microtime(true);

            // 1. Validasi Login
            $userId = (int) session('user_id', 0);
            if ($userId <= 0) {
                throw new Exception('Sesi berakhir. Silakan login kembali.');
            }

            if (! class_exists('ZipArchive')) {
                throw new Exception('Extension ZIP PHP belum aktif di server ini.');
            }
            $debugTiming['after_auth'] = microtime(true);

            // 2. Validasi file mentah dan buat ZIP temporary.
            $uploadedFiles = $this->normalizeUploadedFiles($_FILES['raw_files'] ?? null);
            if (empty($uploadedFiles)) {
                throw new Exception('Pilih minimal satu file untuk diupload.');
            }

            if (count($uploadedFiles) > 1) {
                throw new Exception('Upload hanya boleh satu file dalam satu kali proses.');
            }

            [$zipPath, $originalName] = $this->createZipFromUploadedFiles($uploadedFiles);
            $tmpFile = $zipPath;
            $debugTiming['after_zip'] = microtime(true);

            // 3. Deep ZIP Validation (skip jika ZIP dibuat sendiri, karena sudah divalidasi saat creation)
            $isSelfCreated = ($zipPath !== $uploadedFiles[0]['tmp_name']);
            if ($isSelfCreated) {
                $fileCount = count($uploadedFiles);
                $totalUncompressedSize = (int) ($uploadedFiles[0]['size'] ?? 0);
                $ext = strtolower(pathinfo($uploadedFiles[0]['name'], PATHINFO_EXTENSION));
                $detectedFileType = $this->mapExtensionToType($ext);
            } else {
                $validation = $this->validator->validate($tmpFile, $originalName);
                if (! $validation['success']) {
                    throw new Exception($validation['message']);
                }
                $fileCount = $validation['file_count'];
                $totalUncompressedSize = $validation['total_uncompressed_size'];
                $detectedFileType = $validation['detected_file_type'];
            }
            $debugTiming['after_validate'] = microtime(true);

            $accessMode = $_POST['access_mode'] ?? 'private';
            if (! in_array($accessMode, ['private', 'custom', 'public_internal', 'share_link'], true)) {
                $accessMode = 'private';
            }

            $securityLevel = $_POST['security_level'] ?? 'normal';
            if (! in_array($securityLevel, ['normal', 'restricted', 'confidential'], true)) {
                $securityLevel = 'normal';
            }

            $initialRules = $this->normalizeInitialAccessRules($accessMode, $securityLevel, $userId);
            $debugTiming['after_rules'] = microtime(true);

            // 4. Generate Metadata
            $zipSize = filesize($tmpFile);
            if ($zipSize === false || $zipSize <= 0) {
                throw new Exception('ZIP temporary kosong atau gagal terbaca.');
            }

            $meta = $this->storage->generateStorageMeta($originalName, $zipSize);

            // 5. Upload ke storage dulu SEBELUM DB commit
            if (getenv('FILING_LOCAL_STORAGE_PATH')) {
                $moved = $this->storage->moveUploadedFile($tmpFile, $meta['storage_path']);
                if (! $moved) {
                    throw new Exception("Local storage upload gagal: {$meta['storage_path']}");
                }
                if ($tmpFile !== null && is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
            } else {
                $this->moveToFtp($tmpFile, $meta['storage_path']);
            }
            $debugTiming['after_ftp'] = microtime(true);

            // 6. Database Transaction (PDO_RUN)
            $this->db->beginTransaction();

            $temporaryFileCode = 'PENDING-'.bin2hex(random_bytes(8));

            // A. Insert Metadata sys_filing
            $stmt = $this->db->prepare('
                INSERT INTO sys_filing (
                    file_code, display_name, original_name, 
                    storage_root, storage_dir, storage_name, storage_path,
                    zip_size, file_count, total_uncompressed_size,
                    declared_file_type, detected_file_type,
                    access_mode, security_level, uploaded_by,
                    notes, keywords, expired_at, expired_action
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $temporaryFileCode,
                pathinfo($originalName, PATHINFO_FILENAME),
                $originalName,
                'filing_storage',
                $meta['storage_dir'],
                $meta['storage_name'],
                $meta['storage_path'],
                $zipSize,
                $fileCount,
                $totalUncompressedSize,
                $detectedFileType ?: 'mixed',
                $detectedFileType,
                $accessMode,
                $securityLevel,
                $userId,
                $_POST['notes'] ?? null,
                $_POST['keywords'] ?? null,
                ! empty($_POST['expired_at']) ? $_POST['expired_at'] : null,
                $_POST['expired_action'] ?? 'trash',
            ]);

            $filingId = (int) $this->db->lastInsertId();
            $fileCode = $this->storage->generateFileCode($filingId);

            $stmtFileCode = $this->db->prepare('UPDATE sys_filing SET file_code = ? WHERE rec_id = ?');
            $stmtFileCode->execute([$fileCode, $filingId]);

            // B. Insert Access Rules (owner + optional custom initial rules)
            $accessModel = new FilingAccess($this->db);
            $accessModel->replaceRules($filingId, array_merge([[
                'access_type' => 'user',
                'access_value' => (string) $userId,
                'can_view' => 1,
                'can_download' => 1,
                'can_share' => 1,
                'can_manage' => 1,
            ]], $initialRules), $userId);

            // C. Audit Upload
            $stmtAudit = $this->db->prepare("
                INSERT INTO sys_filing_audit (
                    filing_id, user_id, action, ip_address, user_agent, notes
                ) VALUES (?, ?, 'upload', ?, ?, ?)
            ");
            $stmtAudit->execute([
                $filingId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'Files uploaded and zipped as: '.$originalName,
            ]);

            $this->db->commit();
            $debugTiming['end'] = microtime(true);

            $durations = [];
            $prev = $debugTiming['start'];
            foreach ($debugTiming as $label => $time) {
                $durations[] = $label.'='.round(($time - $prev) * 1000, 1).'ms';
                $prev = $time;
            }
            $timingMessage = '[UploadTiming] '.implode(' | ', $durations);
            error_log($timingMessage);
            $this->writeUploadDebugLog($timingMessage);

            return [
                'success' => true,
                'message' => 'Upload berhasil.',
                'file_code' => $fileCode,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($zipPath !== null && is_file($zipPath)) {
                @unlink($zipPath);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload ke tabel baru (file_system + file_shareto).
     * - Multi-file + "Jadikan ZIP" (zip_files=1) => 1 file ZIP.
     * - Multi-file tanpa ZIP => setiap file disimpan terpisah (masing-masing record).
     * - File tunggal => disimpan asli.
     * - Nomor file dari sysitc_serialno (pola GetSernoWeb) dengan row lock.
     * - Folder aktif dari sys_msttable (tbl_code='81', statrec=1).
     */
    public function handleUploadNew(): array
    {
        $tmpFile = null;
        $tempZipFiles = [];

        try {
            $userId = (int) session('user_id', 0);
            if ($userId <= 0) {
                throw new Exception('Sesi berakhir. Silakan login kembali.');
            }

            $uploadedFiles = $this->normalizeUploadedFiles($_FILES['raw_files'] ?? null);
            if (empty($uploadedFiles)) {
                throw new Exception('Pilih minimal satu file untuk diupload.');
            }

            error_log('[FilingUploadNew] files_received='.count($uploadedFiles).' zip_files='.($_POST['zip_files'] ?? '0').' names='.json_encode(array_column($uploadedFiles, 'name')));
            $this->writeUploadDebugLog('[FilingUploadNew] files_received='.count($uploadedFiles).' zip_files='.($_POST['zip_files'] ?? '0').' names='.json_encode(array_column($uploadedFiles, 'name')));

            $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'htaccess'];
            foreach ($uploadedFiles as $uf) {
                $ext = strtolower(pathinfo($uf['name'], PATHINFO_EXTENSION));
                if ($ext === '' || in_array($ext, $blockedExtensions, true)) {
                    throw new Exception('Tipe file tidak diizinkan: '.$uf['name']);
                }
            }

            $single = count($uploadedFiles) === 1;
            $zipEnabled = ! empty($_POST['zip_files']);
            $multiZip = ! $single && $zipEnabled;

            // Generate metadata + nomor + folder dalam satu transaction (row lock).
            $this->db->beginTransaction();

            $folder = $this->drive->getActiveFolder();
            if (! $folder) {
                throw new Exception('Folder penyimpanan aktif tidak ditemukan (sys_msttable tbl_code=81, statrec=1).');
            }
            $folderLoc = (string) $folder['AddiNotes'];
            $folderPath = rtrim((string) $folder['notes'], '/');

            $createdIds = [];
            $firstTrxno = '';

            if ($single || $multiZip) {
                // Satu hasil akhir (file asli / zip langsung / zip multi)
                $singleExt = strtolower(pathinfo($uploadedFiles[0]['name'], PATHINFO_EXTENSION));
                $isDirectZip = $single && $singleExt === 'zip';

                if ($multiZip) {
                    [$tmpFile, $physicalName] = $this->createZipFromUploadedFiles($uploadedFiles);
                    $tempZipFiles[] = $tmpFile;
                    $storedType = 'ZIP';
                    $totalUncompressed = array_sum(array_column($uploadedFiles, 'size'));
                } elseif ($isDirectZip) {
                    $tmpFile = $uploadedFiles[0]['tmp_name'];
                    $physicalName = $uploadedFiles[0]['name'];
                    $storedType = 'ZIP';
                    $totalUncompressed = (int) ($uploadedFiles[0]['size'] ?? 0);
                } else {
                    // File tunggal non-zip: simpan asli
                    $tmpFile = $uploadedFiles[0]['tmp_name'];
                    $physicalName = $uploadedFiles[0]['name'];
                    $storedType = strtoupper($singleExt);
                    $totalUncompressed = (int) ($uploadedFiles[0]['size'] ?? 0);
                }

                $result = $this->persistSingleUploadNew($userId, $folderLoc, $folderPath, $tmpFile, $physicalName, $storedType, $totalUncompressed);
                $createdIds[] = $result['id'];
                $firstTrxno = $result['trxno'];

                if ($multiZip && $tmpFile !== null && is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
                $tmpFile = null;
            } else {
                // Multi-file tanpa ZIP: setiap file disimpan terpisah
                foreach ($uploadedFiles as $index => $uf) {
                    $ext = strtolower(pathinfo($uf['name'], PATHINFO_EXTENSION));
                    $storedType = strtoupper($ext);
                    $result = $this->persistSingleUploadNew($userId, $folderLoc, $folderPath, $uf['tmp_name'], $uf['name'], $storedType, (int) ($uf['size'] ?? 0));
                    $createdIds[] = $result['id'];
                    if ($index === 0) {
                        $firstTrxno = $result['trxno'];
                    }
                }
            }

            $this->db->commit();

            $count = count($createdIds);
            $message = $count > 1
                ? "Upload berhasil. {$count} file disimpan terpisah."
                : 'Upload berhasil.';

            return [
                'success' => true,
                'message' => $message,
                'file_code' => $firstTrxno,
                'file_id' => $createdIds[0] ?? 0,
                'file_count' => $count,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($tmpFile !== null && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
            foreach ($tempZipFiles as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simpan satu file ke storage + insert file_system + share rules + audit.
     *
     * @return array{id: int, trxno: string}
     */
    private function persistSingleUploadNew(int $userId, string $folderLoc, string $folderPath, string $tmpFile, string $physicalName, string $storedType, int $totalUncompressed): array
    {
        if (! is_file($tmpFile)) {
            throw new Exception('File upload tidak valid.');
        }
        $zipSize = filesize($tmpFile);
        if ($zipSize <= 0) {
            throw new Exception('File hasil upload kosong atau gagal dibaca.');
        }

        $trx = $this->drive->acquireNextTrxNo($userId);
        $trxno = $trx['trxno'];
        $ext = $storedType === 'ZIP' ? 'zip' : strtolower($storedType);
        $uploadFlnm = $trxno.'.'.$ext;

        // Share To targets => menentukan access_mode display file_system
        $shareTargets = $this->normalizeInitialSharesNew();
        $hasAllShare = false;
        foreach ($shareTargets as $st) {
            if ((int) $st['share_cat'] === 4) {
                $hasAllShare = true;
                break;
            }
        }
        $accessMode = $hasAllShare ? 'public_internal' : (empty($shareTargets) ? 'private' : 'custom');

        // Upload fisik ke folder aktif
        $storagePath = $folderPath.'/'.$uploadFlnm;
        $moved = $this->storage->moveUploadedFile($tmpFile, $storagePath);
        if (! $moved) {
            throw new Exception("Gagal mengunggah file ke storage: {$storagePath}");
        }

        $displayName = pathinfo($physicalName, PATHINFO_FILENAME);
        $displayName = preg_replace('/[\/\\\\]/', '_', (string) $displayName);
        $displayName = trim((string) $displayName);
        if ($displayName === '') {
            $displayName = $trxno;
        }

        $filesysId = $this->drive->insertFile([
            'trxno' => $trxno,
            'trxdt' => date('Y-m-d'),
            'file_name' => substr($displayName, 0, 255),
            'folder_loc' => $folderLoc,
            'upload_flnm' => $uploadFlnm,
            'file_type' => $storedType,
            'client_nm' => substr((string) ($_POST['client_nm'] ?? ''), 0, 150),
            'file_notes' => substr((string) ($_POST['notes'] ?? ''), 0, 500),
            'file_size' => $totalUncompressed,
            'file_zip_size' => $zipSize,
            'userid' => $userId,
            'depcd' => (string) ($_POST['depcd'] ?? ''),
            'security_level' => in_array($_POST['security_level'] ?? 'normal', ['normal', 'restricted', 'confidential'], true) ? ($_POST['security_level'] ?? 'normal') : 'normal',
            'access_mode' => $accessMode,
            'expired_at' => ! empty($_POST['expired_at']) ? date('Y-m-d H:i:s', strtotime((string) $_POST['expired_at'])) : null,
            'expired_action' => $_POST['expired_action'] ?? 'trash',
        ]);

        // Owner rule (share_cat = 0, othercode = user id)
        $ownerRule = [[
            'share_cat' => 0,
            'othercode' => (string) $userId,
        ]];

        // Share To targets => share_cat 1 (user), 2 (department), 3 (company), 4 (all)
        foreach ($shareTargets as $share) {
            $ownerRule[] = $share;
        }

        $this->drive->replaceShares($filesysId, $ownerRule, $userId);

        // Audit upload
        $stmtAudit = $this->db->prepare("
            INSERT INTO sys_filing_audit (
                filing_id, user_id, action, ip_address, user_agent, notes
            ) VALUES (?, ?, 'upload', ?, ?, ?)
        ");
        $stmtAudit->execute([
            $filesysId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'Files uploaded: '.$physicalName,
        ]);

        return ['id' => $filesysId, 'trxno' => $trxno];
    }

    private function writeUploadDebugLog(string $message): void
    {
        $dir = base_path().'/storage/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (! is_dir($dir) || ! is_writable($dir)) {
            return;
        }

        @file_put_contents($dir.'/filing_upload_debug.log', date('c').' '.$message.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function moveToFtp(string $tmpFile, string $storagePath): void
    {
        $startedAt = microtime(true);
        $localSize = is_file($tmpFile) ? filesize($tmpFile) : -1;
        error_log("[UploadFtpStart] localSize={$localSize} path={$storagePath} tmpFile={$tmpFile}");

        try {
            $moved = $this->storage->moveUploadedFile($tmpFile, $storagePath);
            $durationMs = round((microtime(true) - $startedAt) * 1000, 1);
            error_log("[UploadFtpTiming] moved=1 duration={$durationMs}ms path={$storagePath}");

            if (! $moved) {
                throw new Exception("FTP upload gagal: {$storagePath}");
            }
        } finally {
            if ($tmpFile !== null && is_file($tmpFile)) {
                $finalSize = filesize($tmpFile);
                @unlink($tmpFile);
                error_log("[UploadFtpCleanup] deleted tmpFile size={$finalSize}");
            }
        }
    }

    /**
     * Normalisasi initial_rules dari form upload menjadi baris file_shareto.
     * access_type: user => share_cat 1, department => 2, company => 3.
     */
    private function normalizeInitialSharesNew(): array
    {
        $rawRules = isset($_POST['share_targets']) && is_string($_POST['share_targets'])
            ? json_decode($_POST['share_targets'], true)
            : [];

        if (! is_array($rawRules)) {
            return [];
        }

        $shares = [];
        $seen = [];

        foreach ($rawRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $type = (string) ($rule['type'] ?? ($rule['access_type'] ?? ''));
            $value = (string) ($rule['value'] ?? ($rule['access_value'] ?? ''));

            $cat = match ($type) {
                'user' => 1,
                'department' => 2,
                'company' => 3,
                'all' => 4,
                default => null,
            };

            if ($cat === null || $value === '') {
                continue;
            }

            $key = $cat.'|'.$value;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $shares[] = [
                'share_cat' => $cat,
                'othercode' => $value,
            ];
        }

        return $shares;
    }

    private function normalizeInitialAccessRules(string $accessMode, string $securityLevel, int $userId): array
    {
        if ($accessMode !== 'custom') {
            return [];
        }

        $rawRules = isset($_POST['initial_rules']) && is_string($_POST['initial_rules'])
            ? json_decode($_POST['initial_rules'], true)
            : [];

        if (! is_array($rawRules)) {
            return [];
        }

        $permissionService = new FilingPermissionService($this->db);
        $accessModel = new FilingAccess($this->db);
        $fileContext = [
            'security_level' => $securityLevel,
        ];
        $normalizedRules = [];
        $seenPairs = ['user_'.$userId => true];

        foreach ($rawRules as $rule) {
            $norm = $permissionService->normalizePermissionRule($rule, $fileContext, $userId);

            if (empty($norm['access_type']) || (empty($norm['access_value']) && $norm['access_value'] !== '0')) {
                continue;
            }

            if (! $accessModel->validateAccessValue((string) $norm['access_type'], (string) $norm['access_value'])) {
                throw new Exception("Nilai akses {$norm['access_type']} tidak valid atau tidak terdaftar.");
            }

            $pairKey = $norm['access_type'].'_'.$norm['access_value'];
            if (isset($seenPairs[$pairKey])) {
                continue;
            }
            $seenPairs[$pairKey] = true;
            $normalizedRules[] = $norm;
        }

        return $normalizedRules;
    }

    private function normalizeUploadedFiles(?array $files): array
    {
        if ($files === null || ! isset($files['name'])) {
            return [];
        }

        $normalized = [];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        foreach ($names as $idx => $name) {
            if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (($errors[$idx] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new Exception(
                    'File gagal diupload ke server lokal: '.basename((string) $name).
                    '. '.$this->getLocalUploadErrorMessage((int) ($errors[$idx] ?? UPLOAD_ERR_OK))
                );
            }

            $tmpName = (string) ($tmpNames[$idx] ?? '');
            if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
                throw new Exception('File upload tidak valid: '.basename((string) $name));
            }

            $normalized[] = [
                'name' => basename((string) $name),
                'tmp_name' => $tmpName,
                'size' => (int) ($sizes[$idx] ?? 0),
            ];
        }

        return $normalized;
    }

    private function getLocalUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi upload_max_filesize server ('.ini_get('upload_max_filesize').').',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas form upload.',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian. Coba ulangi upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary upload server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file upload ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension PHP.',
            default => 'Kode error upload: '.$errorCode,
        };
    }

    private function createZipFromUploadedFiles(array $files): array
    {
        $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'htaccess'];
        if (count($files) === 1 && strtolower(pathinfo($files[0]['name'], PATHINFO_EXTENSION)) === 'zip') {
            return [$files[0]['tmp_name'], $this->safeZipEntryName($files[0]['name'])];
        }

        $firstBaseName = pathinfo($files[0]['name'], PATHINFO_FILENAME);
        $zipBaseName = count($files) === 1
            ? $firstBaseName
            : $firstBaseName.'_dan_'.(count($files) - 1).'_file_lainnya';
        $zipBaseName = $this->safeZipBaseName($zipBaseName);
        $zipName = $zipBaseName.'.zip';

        $tempDir = sys_get_temp_dir();
        $zipPath = $tempDir.'/filing_zip_'.bin2hex(random_bytes(8)).'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new Exception('Gagal membuka ZIP temporary.');
        }

        $usedNames = [];
        foreach ($files as $file) {
            $originalName = $file['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext === '' || in_array($ext, $blockedExtensions, true)) {
                $zip->close();
                @unlink($zipPath);
                throw new Exception('Tipe file tidak diizinkan: '.$originalName);
            }

            $entryName = $this->safeZipEntryName($originalName);
            $entryName = $this->deduplicateZipEntryName($entryName, $usedNames);

            if (! $zip->addFile($file['tmp_name'], $entryName)) {
                $zip->close();
                @unlink($zipPath);
                throw new Exception('Gagal memasukkan file ke ZIP: '.$originalName);
            }

            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
        }

        if (! $zip->close()) {
            @unlink($zipPath);
            throw new Exception('Gagal menutup ZIP dengan benar.');
        }

        $zipSize = filesize($zipPath);
        if ($zipSize <= 0) {
            @unlink($zipPath);
            throw new Exception('ZIP temporary kosong atau gagal dibuat.');
        }

        error_log("[ZipCreate] size={$zipSize} path={$zipPath} files=".count($files));

        return [$zipPath, $zipName];
    }

    private function safeZipBaseName(string $name): string
    {
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string) $name);
        $name = trim((string) $name, '._-');

        return $name !== '' ? $name : 'file_'.date('Ymd_His');
    }

    private function safeZipEntryName(string $name): string
    {
        $name = basename($name);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = $this->safeZipBaseName($base);
        $ext = preg_replace('/[^A-Za-z0-9]/', '', (string) $ext);

        return $ext !== '' ? $base.'.'.strtolower($ext) : $base;
    }

    private function deduplicateZipEntryName(string $name, array &$usedNames): string
    {
        $candidate = $name;
        $i = 2;

        while (isset($usedNames[strtolower($candidate)])) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $candidate = $ext !== '' ? $base.'_'.$i.'.'.$ext : $base.'_'.$i;
            $i++;
        }

        $usedNames[strtolower($candidate)] = true;

        return $candidate;
    }

    private function mapExtensionToType(string $ext): string
    {
        $map = [
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
            'odt' => 'document',
            'xls' => 'spreadsheet',
            'xlsx' => 'spreadsheet',
            'csv' => 'spreadsheet',
            'ods' => 'spreadsheet',
            'txt' => 'text',
            'md' => 'text',
            'rtf' => 'text',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'webp' => 'image',
            'ppt' => 'presentation',
            'pptx' => 'presentation',
            'odp' => 'presentation',
            'zip' => 'archive',
            'rar' => 'archive',
            '7z' => 'archive',
        ];

        return $map[strtolower($ext)] ?? 'other';
    }

    /**
     * Fetch File List with Search and Pagination
     */
    public function fetchList(array $filters = []): array
    {
        if ($this->usesNewTables()) {
            return $this->fetchListNew($filters);
        }

        try {
            $userId = (int) session('user_id', 0);
            $page = max(1, (int) ($filters['page'] ?? 1));
            $limit = max(10, min(100, (int) ($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = trim($filters['search'] ?? '');
            $status = $filters['status'] ?? 'active';
            $sort = $filters['sort'] ?? 'latest';
            $permissionService = new FilingPermissionService($this->db);

            // Base Query (Permission: Owner, Public Internal, or Explicit Access)
            $allowedStatuses = ['active', 'archived', 'trashed'];
            if (! in_array($status, $allowedStatuses, true)) {
                $status = 'active';
            }
            $where = ['f.status = ?'];
            $params = [];
            $params[] = $status;

            // Permission Clause
            [, $permissionParams] = $permissionService->buildVisibleFilesWhereClause($userId, 'f');
            $where[] = $this->buildDynamicPermissionClause($permissionService, $userId, 'f');
            $params = array_merge($params, $permissionParams);

            // Search Clause
            if ($search !== '') {
                $where[] = '(
                    f.display_name LIKE ? 
                    OR f.original_name LIKE ? 
                    OR f.keywords LIKE ? 
                    OR f.notes LIKE ? 
                    OR f.file_code LIKE ?
                )';
                $searchParam = "%$search%";
                array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
            }

            foreach (['declared_file_type', 'security_level', 'access_mode'] as $field) {
                if (! empty($filters[$field])) {
                    $where[] = "f.$field = ?";
                    $params[] = $filters[$field];
                }
            }

            $orderBy = match ($sort) {
                'oldest' => 'f.created_at ASC',
                'name_asc' => 'f.display_name ASC',
                'name_desc' => 'f.display_name DESC',
                'size_largest' => 'f.zip_size DESC',
                'size_smallest' => 'f.zip_size ASC',
                default => 'f.created_at DESC',
            };

            $whereStr = implode(' AND ', $where);

            // Count Total
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM sys_filing f WHERE $whereStr");
            $countStmt->execute($params);
            $totalItems = (int) $countStmt->fetchColumn();
            $totalPages = ceil($totalItems / $limit);

            // Fetch Data
            $sql = "
                SELECT f.*, u.account_nm as owner_name,
                (CASE 
                    WHEN f.uploaded_by = ? THEN 1 
                    WHEN f.access_mode = 'public_internal' THEN 1
                    WHEN EXISTS (
                        SELECT 1 FROM sys_filing_access fa 
                        WHERE fa.filing_id = f.rec_id 
                        AND fa.access_type = 'user' 
                        AND fa.access_value = ? 
                        AND fa.can_download = 1
                    ) THEN 1
                    ELSE 0 
                END) as user_can_download
                FROM sys_filing f
                LEFT JOIN sysitc_users u ON f.uploaded_by = u.rec_id
                WHERE $whereStr
                ORDER BY $orderBy
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $this->db->prepare($sql);
            // Re-map params to include the 2 new user_id markers at the beginning
            $finalParams = array_merge([$userId, $userId], $params);
            $stmt->execute($finalParams);
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item['permissions'] = $permissionService->getPermissionResult($item, $userId);
                $item['user_can_download'] = $item['permissions']['can_download'] ? 1 : 0;
            }
            unset($item);

            return [
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'limit' => $limit,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[FilingDriveController] Fetch Error: '.$e->getMessage());

            return ['items' => [], 'pagination' => []];
        }
    }

    /**
     * Fetch File List dari tabel baru (file_system + file_shareto).
     * $filters['folder'] = my_drive | shared | department | company | trash
     */
    public function fetchListNew(array $filters = []): array
    {
        try {
            $userId = (int) session('user_id', 0);
            $page = max(1, (int) ($filters['page'] ?? 1));
            $limit = max(10, min(100, (int) ($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = trim((string) ($filters['search'] ?? ''));
            $folder = (string) ($filters['folder'] ?? 'my_drive');
            $sort = (string) ($filters['sort'] ?? 'latest');

            $permissionService = new FilingPermissionService($this->db);
            $attrs = $permissionService->resolveUserAttributes($userId);

            [$folderWhere, $folderParams] = $this->buildFolderWhereNew($folder, $userId, $attrs);
            $where = [$folderWhere];
            $params = $folderParams;

            // Search
            if ($search !== '') {
                $where[] = '(f.file_name LIKE ? OR f.file_notes LIKE ? OR f.client_nm LIKE ? OR f.trxno LIKE ?)';
                $searchParam = "%$search%";
                array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
            }

            // Advanced: Has the words
            $hasWords = trim((string) ($filters['has_words'] ?? ''));
            if ($hasWords !== '') {
                $where[] = '(f.file_name LIKE ? OR f.file_notes LIKE ? OR f.client_nm LIKE ? OR f.trxno LIKE ?)';
                $w = "%$hasWords%";
                array_push($params, $w, $w, $w, $w);
            }

            // Advanced: File format (PDF, DOC, ZIP, dst) - OR terhadap file_type
            $fileFormats = (array) ($filters['file_format'] ?? []);
            $fileFormats = array_values(array_filter(array_map(fn ($v) => strtoupper(trim((string) $v)), $fileFormats), fn ($v) => $v !== ''));
            if (! empty($fileFormats)) {
                $placeholders = implode(',', array_fill(0, count($fileFormats), '?'));
                $where[] = "f.file_type IN ($placeholders)";
                foreach ($fileFormats as $fmt) {
                    $params[] = $fmt;
                }
            }

            // Advanced: Owner
            $ownerId = (int) ($filters['owner_id'] ?? 0);
            if ($ownerId > 0) {
                $where[] = 'f.userid = ?';
                $params[] = $ownerId;
            }

            // Advanced: Location (folder_loc / AddiNotes)
            $location = trim((string) ($filters['location'] ?? ''));
            if ($location !== '') {
                $where[] = 'f.folder_loc = ?';
                $params[] = $location;
            }

            // Advanced: Date modified
            $dateModify = (string) ($filters['date_modify'] ?? 'anytime');
            $dateFromRaw = trim((string) ($filters['date_from'] ?? ''));
            $dateToRaw = trim((string) ($filters['date_to'] ?? ''));
            if ($dateModify !== 'anytime') {
                switch ($dateModify) {
                    case 'today':
                        $where[] = 'DATE(f.lupdt) = CURDATE()';
                        break;
                    case 'yesterday':
                        $where[] = 'DATE(f.lupdt) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
                        break;
                    case 'last_7':
                        $where[] = 'f.lupdt >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                        break;
                    case 'last_30':
                        $where[] = 'f.lupdt >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                        break;
                    case 'last_90':
                        $where[] = 'f.lupdt >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
                        break;
                    case 'custom':
                        if ($dateFromRaw !== '') {
                            $where[] = 'DATE(f.lupdt) >= ?';
                            $params[] = date('Y-m-d', strtotime($dateFromRaw));
                        }
                        if ($dateToRaw !== '') {
                            $where[] = 'DATE(f.lupdt) <= ?';
                            $params[] = date('Y-m-d', strtotime($dateToRaw));
                        }
                        break;
                }
            }

            // Advanced: Share to (share_cat di file_shareto)
            $shareTo = trim((string) ($filters['share_to'] ?? ''));
            if ($shareTo !== '' && in_array($shareTo, ['0', '1', '2', '3', '4'], true)) {
                $where[] = 'EXISTS (SELECT 1 FROM file_shareto st WHERE st.filesys_id = f.rec_id AND st.share_cat = ?)';
                $params[] = $shareTo;
            }

            // Filter tipe / security
            if (! empty($filters['declared_file_type'])) {
                $typeExts = $this->declaredTypeToExtensions((string) $filters['declared_file_type']);
                if (! empty($typeExts)) {
                    $placeholders = implode(',', array_fill(0, count($typeExts), '?'));
                    $where[] = "f.file_type IN ($placeholders)";
                    foreach ($typeExts as $ext) {
                        $params[] = $ext;
                    }
                }
            }
            if (! empty($filters['security_level'])) {
                $where[] = 'f.security_level = ?';
                $params[] = $filters['security_level'];
            }
            if (! empty($filters['access_mode'])) {
                $where[] = 'f.access_mode = ?';
                $params[] = $filters['access_mode'];
            }

            $orderBy = match ($sort) {
                'oldest' => 'f.create_dt ASC',
                'name_asc' => 'f.file_name ASC',
                'name_desc' => 'f.file_name DESC',
                'size_largest' => 'f.file_zip_size DESC',
                'size_smallest' => 'f.file_zip_size ASC',
                default => 'f.create_dt DESC',
            };

            $whereStr = implode(' AND ', $where);

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM file_system f WHERE $whereStr");
            $countStmt->execute($params);
            $totalItems = (int) $countStmt->fetchColumn();
            $totalPages = ceil($totalItems / $limit);

            $sql = "
                SELECT f.*, u.account_nm AS owner_name
                FROM file_system f
                LEFT JOIN sysitc_users u ON f.userid = u.rec_id
                WHERE $whereStr
                ORDER BY $orderBy
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $items = [];
            foreach ($rows as $row) {
                $legacy = $this->drive->mapToLegacyShape($row);
                $legacy['owner_name'] = $row['owner_name'] ?? '-';
                $legacy['permissions'] = $permissionService->getPermissionResult($legacy, $userId);
                $legacy['user_can_download'] = $legacy['permissions']['can_download'] ? 1 : 0;
                $items[] = $legacy;
            }

            $sharedBadges = $this->drive->getShareBadgesByFileIds(array_map(fn ($it) => (int) $it['rec_id'], $items));
            foreach ($items as &$item) {
                $item['shared_to_codes'] = $sharedBadges[(int) $item['rec_id']] ?? ['divisi' => [], 'company' => []];
            }
            unset($item);

            return [
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'limit' => $limit,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[FilingDriveController] Fetch (new) Error: '.$e->getMessage());

            return ['items' => [], 'pagination' => []];
        }
    }

    /**
     * Membangun WHERE clause untuk tiap folder sidebar.
     *
     * Folder: my_drive | shared | department | company | trash
     */
    private function buildFolderWhereNew(string $folder, int $userId, array $attrs): array
    {
        $deptCodes = array_unique(array_merge(
            (array) ($attrs['department'] ?? []),
            (array) ($attrs['custom_groups'] ?? [])
        ));
        $companyIds = (array) ($attrs['company'] ?? []);

        switch ($folder) {
            case 'trash':
                // Sampah: file milik user yang sudah di-trash
                return ['f.userid = ? AND f.status = ?', [$userId, 'trashed']];

            case 'shared':
                // Share With Me: dibagikan pribadi ke user, bukan milik user
                return [
                    "f.userid <> ? AND f.status = 'active'
                     AND EXISTS (SELECT 1 FROM file_shareto st WHERE st.filesys_id = f.rec_id AND st.share_cat = 1 AND st.othercode = ?)",
                    [$userId, (string) $userId],
                ];

            case 'department':
                // Divisi/Departemen: dibagikan ke department user (bukan milik user sendiri)
                if (empty($deptCodes)) {
                    return ['1=0', []];
                }
                $placeholders = implode(',', array_fill(0, count($deptCodes), '?'));
                $params = array_merge([$userId, 'active'], $deptCodes);

                return [
                    "f.userid <> ?
                     AND f.status = ?
                     AND EXISTS (SELECT 1 FROM file_shareto st WHERE st.filesys_id = f.rec_id AND st.share_cat = 2 AND st.othercode IN ($placeholders))",
                    $params,
                ];

            case 'company':
                // Company/Seluruh Karyawan: dibagikan ke company user atau semua user (bukan milik user sendiri)
                $ors = [];
                $params = [];
                if (! empty($companyIds)) {
                    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
                    $ors[] = "EXISTS (SELECT 1 FROM file_shareto st WHERE st.filesys_id = f.rec_id AND st.share_cat = 3 AND st.othercode IN ($placeholders))";
                    foreach ($companyIds as $code) {
                        $params[] = (string) $code;
                    }
                }
                $ors[] = 'EXISTS (SELECT 1 FROM file_shareto st WHERE st.filesys_id = f.rec_id AND st.share_cat = 4)';

                array_unshift($params, 'active');
                array_unshift($params, $userId);

                return [
                    'f.userid <> ? AND f.status = ? AND ('.implode(' OR ', $ors).')',
                    $params,
                ];

            case 'my_drive':
            default:
                // My Drive: semua file milik user (aktif + arsip)
                return ["f.userid = ? AND f.status IN ('active', 'archived')", [$userId]];
        }
    }

    /**
     * Menghitung jumlah file per folder untuk badge sidebar.
     *
     * @return array<string,int>
     */
    public function countFolderFilesNew(int $userId): array
    {
        $permissionService = new FilingPermissionService($this->db);
        $attrs = $permissionService->resolveUserAttributes($userId);

        $counts = [];
        foreach (['my_drive', 'shared', 'department', 'company', 'trash'] as $folder) {
            [$where, $params] = $this->buildFolderWhereNew($folder, $userId, $attrs);

            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM file_system f WHERE $where");
                $stmt->execute($params);
                $counts[$folder] = (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                $counts[$folder] = 0;
            }
        }

        return $counts;
    }

    /**
     * Opsi dropdown untuk advanced filter: owners, locations, formats.
     *
     * @return array{owners: array, locations: array, formats: array}
     */
    public function getFilterOptionsNew(): array
    {
        $options = ['owners' => [], 'locations' => [], 'formats' => []];

        try {
            $owners = $this->db->query("
                SELECT DISTINCT u.rec_id AS value,
                       COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', u.rec_id)) AS label
                FROM file_system f
                INNER JOIN sysitc_users u ON f.userid = u.rec_id
                LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
                ORDER BY label ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $options['owners'] = $owners;
        } catch (Throwable $e) {
            $options['owners'] = [];
        }

        try {
            $locations = $this->db->query("
                SELECT DISTINCT f.folder_loc AS value,
                       COALESCE(NULLIF(mt.AddiNotes, ''), f.folder_loc) AS label
                FROM file_system f
                LEFT JOIN sys_msttable mt
                    ON mt.tbl_code = '81' AND mt.AddiNotes = f.folder_loc
                WHERE f.folder_loc IS NOT NULL AND f.folder_loc <> ''
                ORDER BY label ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $options['locations'] = $locations;
        } catch (Throwable $e) {
            $options['locations'] = [];
        }

        try {
            $formats = $this->db->query("
                SELECT DISTINCT f.file_type AS value, f.file_type AS label
                FROM file_system f
                WHERE f.file_type IS NOT NULL AND f.file_type <> ''
                ORDER BY f.file_type ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $options['formats'] = $formats;
        } catch (Throwable $e) {
            $options['formats'] = [];
        }

        return $options;
    }

    private function declaredTypeToExtensions(string $type): array
    {
        $map = [
            'document' => ['PDF', 'DOC', 'DOCX', 'ODT', 'RTF', 'TXT', 'MD'],
            'spreadsheet' => ['XLS', 'XLSX', 'CSV', 'ODS'],
            'image' => ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'IMG'],
            'archive' => ['ZIP', 'RAR', '7Z', 'FSY'],
            'presentation' => ['PPT', 'PPTX', 'ODP'],
            'mixed' => ['ZIP', 'RAR', '7Z'],
        ];

        return $map[strtolower($type)] ?? [];
    }

    private function buildDynamicPermissionClause(FilingPermissionService $permissionService, int $userId, string $tableAlias): string
    {
        [$permissionWhere] = $permissionService->buildVisibleFilesWhereClause($userId, $tableAlias);
        $adminPrefix = "$tableAlias.status = 'active' AND $tableAlias.deleted_at IS NULL";
        $prefix = "$tableAlias.status = 'active' AND $tableAlias.deleted_at IS NULL AND ";

        if ($permissionWhere === $adminPrefix) {
            return '1=1';
        }

        if (str_starts_with($permissionWhere, $prefix)) {
            return substr($permissionWhere, strlen($prefix));
        }

        return '1=0';
    }

    /**
     * Download File with Permission Check and Audit
     */
    public function download(int $filingId): void
    {
        if ($this->usesNewTables()) {
            $this->downloadNew($filingId);

            return;
        }

        $tmpFile = null;
        try {
            $userId = (int) session('user_id', 0);
            if ($userId <= 0) {
                throw new Exception('Unauthorized.');
            }

            // 1. Get Metadata & Verify Status
            $stmt = $this->db->prepare("
                SELECT f.*
                FROM sys_filing f
                WHERE f.rec_id = ?
                AND f.status = 'active'
                AND f.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$filingId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $file) {
                http_response_code(404);
                throw new Exception('File tidak ditemukan atau status tidak aktif.');
            }

            // 2. Permission Check & Denied Logging
            $permissionService = new FilingPermissionService($this->db);
            if (! $permissionService->canDownload($file, $userId)) {
                $stmtAuditDenied = $this->db->prepare("
                    INSERT INTO sys_filing_audit (
                        filing_id, user_id, action, ip_address, user_agent, notes
                    ) VALUES (?, ?, 'download_denied', ?, ?, 'Permission denied')
                ");
                $stmtAuditDenied->execute([
                    $filingId, $userId, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                ]);
                http_response_code(403);
                throw new Exception('Anda tidak memiliki izin untuk mengunduh file ini.');
            }

            // 3. Physical Storage Verification
            $remoteSize = $this->storage->getFileSize($file['storage_path']);
            if ($remoteSize <= 0) {
                http_response_code(404);
                throw new Exception('File fisik tidak tersedia di server storage.');
            }

            // 4. Prepare Local Temporary Path (avoid OneDrive)
            $tempDir = sys_get_temp_dir();
            $tmpFile = $tempDir.'/dl_'.$userId.'_'.time().'_'.bin2hex(random_bytes(4)).'.zip';

            // 5. Download from FTP
            $downloaded = $this->storage->moveFromFtpToLocal($file['storage_path'], $tmpFile);
            if (! $downloaded || ! file_exists($tmpFile)) {
                http_response_code(500);
                throw new Exception('Gagal mengambil file dari storage.');
            }

            $actualSize = filesize($tmpFile);
            $expectedSize = (int) ($file['zip_size'] ?? 0);
            if ($expectedSize > 0 && $actualSize !== $expectedSize) {
                error_log("[Download] Size mismatch: expected={$expectedSize} actual={$actualSize} id={$filingId}");
            }

            // 5b. Verify ZIP is valid before sending
            $zipTest = new ZipArchive;
            $zipOk = $zipTest->open($tmpFile) === true;
            if ($zipOk) {
                $zipTest->close();
                error_log("[Download] ZIP valid: size={$actualSize} id={$filingId}");
            } else {
                error_log("[Download] ZIP INVALID: size={$actualSize} id={$filingId} storage_path={$file['storage_path']}");
                http_response_code(500);
                throw new Exception('File ZIP di storage tidak valid atau corrupt.');
            }

            // 6. Audit Log (Success)
            $stmtAudit = $this->db->prepare("
                INSERT INTO sys_filing_audit (
                    filing_id, user_id, action, ip_address, user_agent, notes
                ) VALUES (?, ?, 'download', ?, ?, ?)
            ");
            $stmtAudit->execute([
                $filingId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'File downloaded: '.$file['original_name'],
            ]);

            // 7. Format Download Name (display_name + .zip)
            $safeName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '_', $file['display_name']);
            $downloadName = trim($safeName).'.zip';
            $fileSize = filesize($tmpFile);

            // Clean buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$downloadName.'"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: '.$fileSize);

            readfile($tmpFile);

            // 8. Cleanup
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            exit;

        } catch (Throwable $e) {
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            error_log('[FilingDriveController] Download Error: '.$e->getMessage());

            http_response_code(500);
            exit('Gagal mengunduh file. File tidak tersedia di storage.');
        }
    }

    /**
     * Download File via validated Share Code
     */
    public function downloadViaShare(int $filingId, string $shareHash, int $userId): void
    {
        $tmpFile = null;
        try {
            // 1. Get Metadata
            $stmt = $this->db->prepare("SELECT * FROM sys_filing WHERE rec_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$filingId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $file) {
                http_response_code(404);
                throw new Exception('File tidak ditemukan.');
            }

            // 2. Physical Storage Verification
            $remoteSize = $this->storage->getFileSize($file['storage_path']);
            if ($remoteSize <= 0) {
                http_response_code(404);
                throw new Exception('File fisik tidak tersedia di server storage.');
            }

            // 3. Prepare Local Temporary Path (avoid OneDrive)
            $tempDir = sys_get_temp_dir();
            $tmpFile = $tempDir.'/dl_share_'.$userId.'_'.time().'_'.bin2hex(random_bytes(4)).'.zip';

            // 4. Download from FTP
            $downloaded = $this->storage->moveFromFtpToLocal($file['storage_path'], $tmpFile);
            if (! $downloaded || ! file_exists($tmpFile)) {
                http_response_code(500);
                throw new Exception('Gagal mengambil file dari storage.');
            }

            $actualSize = filesize($tmpFile);
            $expectedSize = (int) ($file['zip_size'] ?? 0);
            if ($expectedSize > 0 && $actualSize !== $expectedSize) {
                error_log("[ShareDownload] Size mismatch: expected={$expectedSize} actual={$actualSize} id={$filingId}");
            }

            $zipTest = new ZipArchive;
            $zipOk = $zipTest->open($tmpFile) === true;
            if ($zipOk) {
                $zipTest->close();
            } else {
                error_log("[ShareDownload] ZIP INVALID: size={$actualSize} id={$filingId} storage_path={$file['storage_path']}");
                http_response_code(500);
                throw new Exception('File ZIP di storage tidak valid atau corrupt.');
            }

            // 5. Audit Log (Share Download)
            $stmtAudit = $this->db->prepare("
                INSERT INTO sys_filing_audit (
                    filing_id, user_id, action, ip_address, user_agent, notes
                ) VALUES (?, ?, 'share_download', ?, ?, ?)
            ");
            $stmtAudit->execute([
                $filingId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'File downloaded via share hash: '.substr($shareHash, 0, 8).'...',
            ]);

            // 6. Format Download Name
            $safeName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '_', $file['display_name']);
            $downloadName = trim($safeName).'.zip';
            $fileSize = filesize($tmpFile);

            // Clean buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$downloadName.'"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: '.$fileSize);

            readfile($tmpFile);

            // 7. Cleanup
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            exit;

        } catch (Throwable $e) {
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            error_log('[FilingDriveController] Share Download Error: '.$e->getMessage());

            http_response_code(500);
            exit('Gagal mengunduh file. File tidak tersedia di storage.');
        }
    }

    /**
     * Download File dari tabel baru (file_system + file_shareto + sys_msttable).
     */
    public function downloadNew(int $filingId): void
    {
        $tmpFile = null;
        try {
            $userId = (int) session('user_id', 0);
            if ($userId <= 0) {
                throw new Exception('Unauthorized.');
            }

            $file = $this->drive->getFileById($filingId);
            if (! $file || ($file['status'] ?? 'active') !== 'active' || ! empty($file['deleted_at'])) {
                http_response_code(404);
                throw new Exception('File tidak ditemukan atau status tidak aktif.');
            }

            $permissionService = new FilingPermissionService($this->db);
            $legacy = $this->drive->mapToLegacyShape($file);
            if (! $permissionService->canDownload($legacy, $userId)) {
                http_response_code(403);
                throw new Exception('Anda tidak memiliki izin untuk mengunduh file ini.');
            }

            // Path fisik: sys_msttable.notes + upload_flnm
            $storagePath = $this->drive->buildStoragePath($file);
            if ($storagePath === '') {
                http_response_code(404);
                throw new Exception('Lokasi penyimpanan file tidak ditemukan.');
            }

            $remoteSize = $this->storage->getFileSize($storagePath);
            if ($remoteSize <= 0) {
                http_response_code(404);
                throw new Exception('File fisik tidak tersedia di server storage.');
            }

            $tempDir = sys_get_temp_dir();
            $tmpFile = $tempDir.'/dl_'.$userId.'_'.time().'_'.bin2hex(random_bytes(4)).'.tmp';

            $downloaded = $this->storage->moveFromFtpToLocal($storagePath, $tmpFile);
            if (! $downloaded || ! file_exists($tmpFile)) {
                http_response_code(500);
                throw new Exception('Gagal mengambil file dari storage.');
            }

            $fileSize = filesize($tmpFile);
            $expectedSize = (int) ($file['file_zip_size'] ?? 0);
            if ($expectedSize > 0 && $fileSize !== $expectedSize) {
                error_log("[DownloadNew] Size mismatch: expected={$expectedSize} actual={$fileSize} id={$filingId}");
            }

            // Audit
            $stmtAudit = $this->db->prepare("
                INSERT INTO sys_filing_audit (
                    filing_id, user_id, action, ip_address, user_agent, notes
                ) VALUES (?, ?, 'download', ?, ?, ?)
            ");
            $stmtAudit->execute([
                $filingId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'File downloaded: '.$file['file_name'],
            ]);

            $safeName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '_', $file['file_name'] ?? 'file');
            $downloadName = trim($safeName).'.'.($file['file_type'] !== 'ZIP' ? strtolower($file['file_type']) : 'zip');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$downloadName.'"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: '.$fileSize);

            readfile($tmpFile);

            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            exit;

        } catch (Throwable $e) {
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            error_log('[FilingDriveController] Download (new) Error: '.$e->getMessage());

            http_response_code(500);
            exit('Gagal mengunduh file. File tidak tersedia di storage.');
        }
    }

    private function buildPublicFileUrl(string $storagePath): ?string
    {
        $baseUrl = trim((string) (getenv('FTP_PUBLIC_BASE_URL') ?: ''));
        if ($baseUrl === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($storagePath));
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim($baseUrl, '/').'/'.$encodedPath;
    }
}
