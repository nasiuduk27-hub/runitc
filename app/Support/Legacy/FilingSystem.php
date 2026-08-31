<?php

namespace App\Support\Legacy;

class FilingSystem
{
    private \PDO $pdo;

    private \PDO $pdoRun;

    private FtpStorage $ftp;

    public function __construct(\PDO $pdo, \PDO $pdoRun, array $ftpConfig)
    {
        $this->pdo = $pdo;
        $this->pdoRun = $pdoRun;
        $this->ftp = new FtpStorage($ftpConfig);
    }

    public function getSpvName($itcId, string $fallbackName = 'System'): string
    {
        try {
            $stmt = $this->pdoRun->prepare('
                SELECT spv_name 
                FROM tad_supervisor 
                WHERE itc_usr_id = ? 
                LIMIT 1
            ');

            $stmt->execute([$itcId]);

            return $stmt->fetchColumn() ?: $fallbackName;
        } catch (\Exception $e) {
            return $fallbackName;
        }
    }

    public function saveBeritaAcara(array $data): array
    {
        try {
            $this->pdoRun->beginTransaction();

            $filingId = $this->findFilingId($data['admin_no'], $data['sub_admin_id']);

            if ($filingId) {
                $stmt = $this->pdoRun->prepare('
                    UPDATE runit_filing_system
                    SET keterangan = ?, tanggal = ?, spv_name = ?
                    WHERE rec_id = ?
                ');

                $stmt->execute([
                    $data['keterangan'],
                    $data['tanggal'],
                    $data['spv_name'],
                    $filingId,
                ]);
            } else {
                $stmt = $this->pdoRun->prepare('
                    INSERT INTO runit_filing_system
                    (
                        nomor_admin,
                        sub_admin_id,
                        admin_id,
                        tanggal,
                        keterangan,
                        input_by,
                        spv_name
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');

                $stmt->execute([
                    $data['admin_no'],
                    $data['sub_admin_id'],
                    $data['admin_rec_id'],
                    $data['tanggal'],
                    $data['keterangan'],
                    $data['input_by'],
                    $data['spv_name'],
                ]);

                $filingId = $this->pdoRun->lastInsertId();
            }

            $this->replaceIssues($filingId, $data['issues'] ?? []);

            $this->pdoRun->commit();

            return [
                'success' => true,
                'filing_id' => $filingId,
            ];
        } catch (\Exception $e) {
            $this->pdoRun->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function uploadBeritaAcaraPdf(array $data): array
    {
        @ini_set('max_execution_time', '180');
        @set_time_limit(180);

        $tmpFile = null;
        $ftpFilePath = null;

        try {
            $adminNo = trim((string) ($data['admin_no'] ?? ''));
            $subAdminId = (int) ($data['sub_admin_id'] ?? 0);
            $adminRecId = (int) ($data['admin_rec_id'] ?? 0);
            $tanggal = $data['tanggal'] ?? date('Y-m-d');
            $inputBy = $data['input_by'] ?? 'System';
            $spvName = $data['spv_name'] ?? 'System';
            $pdfData = trim((string) ($data['pdf_data'] ?? ''));

            // [BA_UPLOAD_START] error log when execution begins
            error_log("[BA_UPLOAD_START] admin_no={$adminNo} sub_admin_id={$subAdminId}");

            if ($adminNo === '') {
                throw new \Exception('Nomor admin tidak valid.');
            }

            if ($pdfData === '') {
                throw new \Exception('Data PDF kosong.');
            }

            // Jaga-jaga kalau data yang masuk masih berbentuk data URI lengkap.
            if (str_contains($pdfData, ',')) {
                $parts = explode(',', $pdfData, 2);
                $pdfData = $parts[1] ?? '';
            }

            $pdfBinary = base64_decode($pdfData, true);

            if ($pdfBinary === false || strlen($pdfBinary) <= 0) {
                throw new \Exception('Data PDF tidak valid atau gagal decode base64.');
            }

            // PDF Signature Check: Verify it starts with %PDF-
            if (strpos($pdfBinary, '%PDF-') !== 0) {
                throw new \Exception('Data binary yang didecode tidak memiliki signature PDF (%PDF-) yang valid.');
            }

            // Gunakan temporary directory bawaan PHP/Synology, bukan folder project.
            // File ini hanya transit sebentar sebelum dikirim ke FTP, lalu dihapus lagi.
            $baseTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
            $tempDir = $baseTempDir.DIRECTORY_SEPARATOR.'runitc_uploads';

            if (! is_dir($tempDir) && ! @mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
                // Fallback ke temp bawaan PHP kalau subfolder tidak bisa dibuat.
                $tempDir = $baseTempDir;
            }

            if (! is_dir($tempDir) || ! is_writable($tempDir)) {
                throw new \Exception('Folder temporary PHP tidak writable: '.$tempDir);
            }

            $safeAdminNo = $this->ftp->safeFileName($adminNo);
            $uploadDateFolder = date('Ymd');
            $fileName = date('Ymd_His').'_'.$safeAdminNo.'_BeritaAcara_Pelaksanaan_Tes.pdf';

            $tmpFile = $tempDir.DIRECTORY_SEPARATOR.$fileName;

            if (@file_put_contents($tmpFile, $pdfBinary) === false) {
                throw new \Exception('Gagal membuat file PDF temporary.');
            }

            if (! is_file($tmpFile) || filesize($tmpFile) <= 0) {
                throw new \Exception('File PDF temporary kosong atau tidak terbaca.');
            }

            // [BA_UPLOAD_TEMP_CREATED] log
            error_log("[BA_UPLOAD_TEMP_CREATED] path={$tmpFile} size=".filesize($tmpFile));

            $filingId = $this->findFilingId($adminNo, $subAdminId);

            if (! $filingId) {
                $stmt = $this->pdoRun->prepare('
                INSERT INTO runit_filing_system
                (
                    nomor_admin,
                    sub_admin_id,
                    admin_id,
                    tanggal,
                    keterangan,
                    input_by,
                    spv_name
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');

                $stmt->execute([
                    $adminNo,
                    $subAdminId,
                    $adminRecId,
                    $tanggal,
                    'Berita Acara Pelaksanaan Tes',
                    $inputBy,
                    $spvName,
                ]);

                $filingId = (int) $this->pdoRun->lastInsertId();
            } else {
                $stmt = $this->pdoRun->prepare("
                UPDATE runit_filing_system
                SET tanggal = ?,
                    input_by = ?,
                    spv_name = ?,
                    keterangan = CASE
                        WHEN keterangan IS NULL OR keterangan = '' THEN 'Berita Acara Pelaksanaan Tes'
                        ELSE keterangan
                    END
                WHERE rec_id = ?
            ");

                $stmt->execute([
                    $tanggal,
                    $inputBy,
                    $spvName,
                    $filingId,
                ]);
            }

            $ftpFolder = $safeAdminNo.'/'.$uploadDateFolder.'/Documents';
            $ftpFilePath = $this->ftp->normalizePath($ftpFolder.'/'.$fileName);

            $httpUploadUrl = trim((string) (env('CRC_HTTP_UPLOAD_URL', '')));
            if ($httpUploadUrl !== '') {
                $this->uploadBaViaHttp($tmpFile, $safeAdminNo, $fileName, $httpUploadUrl, $uploadDateFolder);
                $remoteSize = filesize($tmpFile);
            } else {
                $this->ftp->upload($tmpFile, $ftpFilePath);
                $remoteSize = $this->ftp->size($ftpFilePath);
                if ($remoteSize <= 0) {
                    throw new \Exception('PDF sudah dikirim tapi ukuran file di FTP tidak terbaca atau 0 byte.');
                }
            }

            // [BA_UPLOAD_FTP_SUCCESS] log
            error_log("[BA_UPLOAD_FTP_SUCCESS] remote={$ftpFilePath} size={$remoteSize}");

            $this->ftp->close();

            $stmt = $this->pdoRun->prepare('
            INSERT INTO runit_filing_files
            (
                filing_id,
                file_name,
                file_path,
                file_type,
                file_category
            )
            VALUES (?, ?, ?, ?, ?)
        ');

            $inserted = $stmt->execute([
                $filingId,
                $fileName,
                $ftpFilePath,
                'pdf',
                'berita_acara',
            ]);

            if (! $inserted) {
                throw new \Exception('Gagal memasukkan metadata file ke database (runit_filing_files).');
            }

            // [BA_UPLOAD_DB_INSERT_SUCCESS] log
            error_log("[BA_UPLOAD_DB_INSERT_SUCCESS] filing_id={$filingId} file_path={$ftpFilePath}");

            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }

            return [
                'success' => true,
                'message' => 'Berita Acara berhasil disimpan dan diupload ke FTP Filing System.',
                'filing_id' => $filingId,
                'file_name' => $fileName,
                'file_path' => $ftpFilePath,
                'size' => $remoteSize,
            ];

        } catch (\Throwable $e) {
            $this->ftp->close();

            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }

            // [BA_UPLOAD_ERROR] log
            error_log('[BA_UPLOAD_ERROR] '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'file_path' => $ftpFilePath,
            ];
        }
    }

    public function touchFilingActivity(array $data): void
    {
        $adminNo = trim((string) ($data['admin_no'] ?? ''));
        $subAdminId = (int) ($data['sub_admin_id'] ?? 0);

        if ($adminNo === '' || $subAdminId <= 0) {
            return;
        }

        $filingId = $this->findFilingId($adminNo, $subAdminId);
        $tanggal = $data['tanggal'] ?? date('Y-m-d');
        $keterangan = $data['keterangan'] ?? '';
        $inputBy = $data['input_by'] ?? 'System';
        $spvName = $data['spv_name'] ?? 'System';

        if ($filingId) {
            $stmt = $this->pdoRun->prepare("
                UPDATE runit_filing_system
                SET tanggal = ?,
                    input_by = ?,
                    spv_name = ?,
                    keterangan = CASE
                        WHEN keterangan IS NULL OR keterangan = '' THEN ?
                        ELSE keterangan
                    END
                WHERE rec_id = ?
            ");
            $stmt->execute([$tanggal, $inputBy, $spvName, $keterangan, $filingId]);

            return;
        }

        $stmt = $this->pdoRun->prepare('
            INSERT INTO runit_filing_system
            (
                nomor_admin,
                sub_admin_id,
                admin_id,
                tanggal,
                keterangan,
                input_by,
                spv_name
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $adminNo,
            $subAdminId,
            (int) ($data['admin_rec_id'] ?? 0),
            $tanggal,
            $keterangan,
            $inputBy,
            $spvName,
        ]);
    }

    public function ensureFilingId(array $data): int
    {
        $adminNo = trim((string) ($data['admin_no'] ?? ''));
        $subAdminId = (int) ($data['sub_admin_id'] ?? 0);

        if ($adminNo === '' || $subAdminId <= 0) {
            throw new \InvalidArgumentException('Nomor admin atau sub admin tidak valid.');
        }

        $filingId = $this->findFilingId($adminNo, $subAdminId);
        if ($filingId) {
            return (int) $filingId;
        }

        $stmt = $this->pdoRun->prepare('
            INSERT INTO runit_filing_system
            (
                nomor_admin,
                sub_admin_id,
                admin_id,
                tanggal,
                keterangan,
                input_by,
                spv_name
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $adminNo,
            $subAdminId,
            (int) ($data['admin_rec_id'] ?? 0),
            $data['tanggal'] ?? date('Y-m-d'),
            $data['keterangan'] ?? 'Absensi Final',
            $data['input_by'] ?? 'System',
            $data['spv_name'] ?? 'System',
        ]);

        return (int) $this->pdoRun->lastInsertId();
    }

    public function upsertFileMetadata(array $data): void
    {
        $filingId = (int) ($data['filing_id'] ?? 0);
        $fileName = trim((string) ($data['file_name'] ?? ''));
        $filePath = trim((string) ($data['file_path'] ?? ''));
        $fileType = trim((string) ($data['file_type'] ?? ''));
        $fileCategory = trim((string) ($data['file_category'] ?? ''));

        if ($filingId <= 0 || $fileName === '' || $filePath === '' || $fileType === '') {
            throw new \InvalidArgumentException('Metadata file tidak lengkap.');
        }

        $stmt = $this->pdoRun->prepare('
            SELECT file_id
            FROM runit_filing_files
            WHERE filing_id = ?
              AND file_name = ?
              AND file_category = ?
            LIMIT 1
        ');
        $stmt->execute([$filingId, $fileName, $fileCategory]);
        $fileId = $stmt->fetchColumn();

        if ($fileId) {
            $stmt = $this->pdoRun->prepare('
                UPDATE runit_filing_files
                SET file_path = ?, file_type = ?
                WHERE file_id = ?
            ');
            $stmt->execute([$filePath, $fileType, $fileId]);

            return;
        }

        $stmt = $this->pdoRun->prepare('
            INSERT INTO runit_filing_files
            (
                filing_id,
                file_name,
                file_path,
                file_type,
                file_category
            )
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$filingId, $fileName, $filePath, $fileType, $fileCategory]);
    }

    private function uploadBaViaHttp(string $tmpPath, string $adminNo, string $fileName, string $uploadUrl, string $uploadDateFolder): void
    {
        $token = trim((string) (env('CRC_HTTP_UPLOAD_TOKEN', '')));
        $timeout = max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120));

        if (! function_exists('curl_init')) {
            error_log('[BA_HTTP_UPLOAD] cURL tidak tersedia.');
            throw new \Exception('cURL tidak tersedia untuk upload Berita Acara via HTTP.');
        }

        $mimeType = 'application/pdf';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpPath);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        $postFields = [
            'type' => 'berita_acara',
            'admin_no' => $adminNo,
            'test_date' => $uploadDateFolder,
            'tanggal' => $uploadDateFolder,
            'file_size' => (string) filesize($tmpPath),
            'file' => new CURLFile($tmpPath, $mimeType, $fileName),
        ];

        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
            throw new \Exception('Gagal initialisasi cURL untuk upload Berita Acara.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
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
            throw new \Exception('cURL error upload Berita Acara: '.$error);
        }

        if ($status < 200 || $status >= 300) {
            $body = '';
            if (is_string($response) && trim($response) !== '') {
                $json = json_decode($response, true);
                if (is_array($json) && isset($json['message'])) {
                    $body = $json['message'];
                } else {
                    $body = substr(trim(strip_tags($response)), 0, 200);
                }
            }
            throw new \Exception('Server CRC error (HTTP '.$status.'): '.$body);
        }

        $result = null;
        if (is_string($response) && trim($response) !== '') {
            $result = json_decode($response, true);
        }

        if (! is_array($result) || empty($result['success'])) {
            $msg = is_array($result) && isset($result['message']) ? $result['message'] : 'Respon server CRC tidak valid.';
            throw new \Exception($msg);
        }

        error_log('[BA_HTTP_UPLOAD_SUCCESS] admin_no='.$adminNo.' file='.$fileName.' size='.filesize($tmpPath));
    }

    private function findFilingId(string $adminNo, int $subAdminId)
    {
        $stmt = $this->pdoRun->prepare('
            SELECT rec_id
            FROM runit_filing_system
            WHERE nomor_admin = ?
            ORDER BY rec_id DESC
            LIMIT 1
        ');

        $stmt->execute([$adminNo]);

        return $stmt->fetchColumn();
    }

    private function replaceIssues($filingId, array $issues): void
    {
        $this->pdoRun
            ->prepare('DELETE FROM runit_filing_issues WHERE filing_id = ?')
            ->execute([$filingId]);

        if (empty($issues)) {
            return;
        }

        $stmt = $this->pdoRun->prepare('
            INSERT INTO runit_filing_issues
            (
                filing_id,
                authorize_id,
                participant_name,
                issue_text
            )
            VALUES (?, ?, ?, ?)
        ');

        foreach ($issues as $issue) {
            $text = trim($issue['text'] ?? '');

            if ($text === '') {
                continue;
            }

            $stmt->execute([
                $filingId,
                $issue['auth_id'] ?? '',
                $issue['name'] ?? '',
                $text,
            ]);
        }
    }
}
