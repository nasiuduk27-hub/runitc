<?php

namespace App\Support\Legacy;

class MonitoringController
{
    private Monitoring $monitoringModel;

    private FilingSystem $filingSystem;

    private FtpStorage $ftp;

    private \PDO $pdoRun;

    private \PDO $pdoWar;

    public function __construct(\PDO $pdo, \PDO $pdoRun, \PDO $pdoWar, array $ftpConfig)
    {
        $this->monitoringModel = new Monitoring($pdo, $pdoRun, $pdoWar);
        $this->filingSystem = new FilingSystem($pdo, $pdoRun, $ftpConfig);
        $this->ftp = new FtpStorage($ftpConfig);
        $this->pdoRun = $pdoRun;
        $this->pdoWar = $pdoWar;
    }

    public function handle(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handlePost();
        }

        if (isset($_GET['ajax_realtime']) && $_GET['ajax_realtime'] == '1') {
            $this->handleRealtimeAjax();
            exit;
        }

        return $this->getPageData();
    }

    private function handlePost(): array
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'download_final_attendance_file') {
            $this->downloadFinalAttendanceFile();
            exit;
        }

        header('Content-Type: application/json');

        if ($action === 'save_berita_acara') {
            echo json_encode($this->saveBeritaAcara());
            exit;
        }

        if ($action === 'upload_berita_acara_pdf') {
            echo json_encode($this->uploadBeritaAcaraPdf());
            exit;
        }

        if ($action === 'upload_crc_zip_to_ftp') {
            echo json_encode($this->uploadCrcZipToFtp());
            exit;
        }

        if ($action === 'mark_crc_collected') {
            echo json_encode($this->markCrcCollected());
            exit;
        }

        if ($action === 'save_attendance_update') {
            echo json_encode($this->saveAttendanceUpdate());
            exit;
        }

        if ($action === 'get_final_attendance_data') {
            echo json_encode($this->getFinalAttendanceData());
            exit;
        }

        if ($action === 'upload_final_attendance_file') {
            echo json_encode($this->uploadFinalAttendanceFile());
            exit;
        }

        echo json_encode([
            'success' => false,
            'message' => 'Action tidak dikenal.',
        ]);

        exit;
    }

    private function saveBeritaAcara(): array
    {
        $itcId = auth_user_id();
        $userName = session('account_nm') ?? session('user_name') ?? 'System';
        $spvName = $this->filingSystem->getSpvName($itcId, $userName);
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return [
                'success' => false,
                'message' => 'Anda tidak ditugaskan untuk room monitoring ini.',
            ];
        }

        $issues = json_decode($_POST['issues'] ?? '[]', true);

        if (! is_array($issues)) {
            $issues = [];
        }

        return $this->filingSystem->saveBeritaAcara([
            'admin_no' => $_POST['admin_no'] ?? '',
            'admin_rec_id' => $adminRecId,
            'sub_admin_id' => $subAdminId,
            'keterangan' => $_POST['keterangan'] ?? '',
            'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
            'issues' => $issues,
            'input_by' => $userName,
            'spv_name' => $spvName,
        ]);
    }

    private function uploadBeritaAcaraPdf(): array
    {
        $this->extendUploadExecutionTime();

        $itcId = auth_user_id();
        $userName = session('account_nm') ?? session('user_name') ?? 'System';
        $spvName = $this->filingSystem->getSpvName($itcId, $userName);
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return [
                'success' => false,
                'message' => 'Anda tidak ditugaskan untuk room monitoring ini.',
            ];
        }

        return $this->filingSystem->uploadBeritaAcaraPdf([
            'admin_no' => $_POST['admin_no'] ?? '',
            'admin_rec_id' => $adminRecId,
            'sub_admin_id' => $subAdminId,
            'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
            'pdf_data' => $_POST['pdf_data'] ?? '',
            'input_by' => $userName,
            'spv_name' => $spvName,
        ]);
    }

    private function saveAttendanceUpdate(): array
    {
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return ['success' => false, 'message' => 'Anda tidak ditugaskan untuk room monitoring ini.'];
        }

        $adminNo = trim((string) ($_POST['admin_no'] ?? ''));
        $tanggal = $this->normalizeAttendanceDate((string) ($_POST['tanggal'] ?? date('Y-m-d')));
        $rows = json_decode((string) ($_POST['attendance_rows'] ?? '[]'), true);

        if ($adminNo === '') {
            return ['success' => false, 'message' => 'Nomor admin tidak valid.'];
        }

        if (! is_array($rows)) {
            return ['success' => false, 'message' => 'Data absensi tidak valid.'];
        }

        $userId = (string) auth_user_id();
        $userName = session('account_nm') ?? session('user_name') ?? 'System';
        $spvName = $this->filingSystem->getSpvName(auth_user_id(), $userName);
        $now = date('c');
        $path = $this->attendanceStorePath($adminRecId, $adminNo, $tanggal);
        $store = $this->readAttendanceStore($path);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $auth = trim((string) ($row['authcode'] ?? ''));
            if ($auth === '') {
                continue;
            }

            $store['updates'][$auth] = [
                'authcode' => $auth,
                'name' => trim((string) ($row['name'] ?? '')),
                'dob' => trim((string) ($row['dob'] ?? '')),
                'idno' => trim((string) ($row['idno'] ?? '')),
                'hpno' => trim((string) ($row['hpno'] ?? '')),
                'attendance' => trim((string) ($row['attendance'] ?? '')) ?: 'Hadir',
                'note' => trim((string) ($row['note'] ?? '')),
                'groupcd' => trim((string) ($row['groupcd'] ?? '')),
                'custom1' => trim((string) ($row['custom1'] ?? '')),
                'custom2' => trim((string) ($row['custom2'] ?? '')),
                'custom3' => trim((string) ($row['custom3'] ?? '')),
                'spv_user_id' => $userId,
                'spv_name' => $spvName,
                'updated_at' => $now,
            ];
        }

        $store['admin_rec_id'] = $adminRecId;
        $store['admin_no'] = $adminNo;
        $store['tanggal'] = $tanggal;
        $store['updated_at'] = $now;

        if (! $this->writeAttendanceStore($path, $store)) {
            return ['success' => false, 'message' => 'Gagal menyimpan update absensi.'];
        }

        return ['success' => true, 'message' => 'Update absensi tersimpan.', 'updated' => count($rows)];
    }

    private function getFinalAttendanceData(): array
    {
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return ['success' => false, 'message' => 'Anda tidak ditugaskan untuk room monitoring ini.'];
        }

        $adminNo = trim((string) ($_POST['admin_no'] ?? ''));
        $testType = trim((string) ($_POST['test_type'] ?? ''));
        $tanggal = $this->normalizeAttendanceDate((string) ($_POST['tanggal'] ?? date('Y-m-d')));

        if ($adminNo === '') {
            return ['success' => false, 'message' => 'Nomor admin tidak valid.'];
        }

        try {
            $items = $this->buildFinalAttendanceItems($adminRecId, $adminNo, $testType, $tanggal);

            return ['success' => true, 'items' => $items];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function uploadFinalAttendanceFile(): array
    {
        $this->extendUploadExecutionTime();

        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return ['success' => false, 'message' => 'Anda tidak ditugaskan untuk room monitoring ini.'];
        }

        if (empty($_FILES['attendance_file']['tmp_name']) || ! is_uploaded_file($_FILES['attendance_file']['tmp_name'])) {
            return ['success' => false, 'message' => 'File absensi tidak diterima server.'];
        }

        $tmpName = $_FILES['attendance_file']['tmp_name'];
        $fileSize = filesize($tmpName);
        if ($fileSize === false || $fileSize <= 0) {
            return ['success' => false, 'message' => 'File absensi kosong atau gagal terbaca.'];
        }

        $adminNoRaw = trim((string) ($_POST['admin_no'] ?? 'CBT'));
        $tanggal = $this->normalizeAttendanceDate((string) ($_POST['tanggal'] ?? date('Y-m-d')));
        $format = strtolower(trim((string) ($_POST['format'] ?? '')));
        $ext = $format === 'pdf' ? 'pdf' : 'xlsx';
        $safeAdminNo = $this->ftp->safeFileName($adminNoRaw !== '' ? $adminNoRaw : 'CBT');
        $safeDate = preg_replace('/[^0-9A-Za-z_-]/', '-', $tanggal) ?: date('Y-m-d');
        $uploadDateFolder = date('Ymd');
        $fileName = 'Absensi_'.$safeAdminNo.'_'.$safeDate.'.'.$ext;
        $uploadUrl = trim((string) (env('CRC_HTTP_UPLOAD_URL', '')));
        if ($uploadUrl === '') {
            return ['success' => false, 'message' => 'CRC_HTTP_UPLOAD_URL belum dikonfigurasi untuk upload absensi final ke server CBT.'];
        }

        try {
            $result = $this->uploadAttendanceViaHttp($tmpName, $safeAdminNo, $fileName, $uploadUrl, $uploadDateFolder);

            if (empty($result['success'])) {
                return $result;
            }

            $userName = session('account_nm') ?? session('user_name') ?? 'System';
            $spvName = $this->filingSystem->getSpvName(auth_user_id(), $userName);
            $remotePath = $result['remote_path'] ?? ($safeAdminNo.'/'.$uploadDateFolder.'/Documents/'.$fileName);

            $filingId = $this->filingSystem->ensureFilingId([
                'admin_no' => $safeAdminNo,
                'admin_rec_id' => $adminRecId,
                'sub_admin_id' => $subAdminId,
                'tanggal' => $tanggal,
                'keterangan' => 'Absensi Final',
                'input_by' => $userName,
                'spv_name' => $spvName,
            ]);

            $this->filingSystem->upsertFileMetadata([
                'filing_id' => $filingId,
                'file_name' => $fileName,
                'file_path' => $remotePath,
                'file_type' => $ext,
                'file_category' => 'dokumen_support',
            ]);

            return [
                'success' => true,
                'message' => 'File final absensi berhasil diupload ke server CBT dan akan tampil di Dokumen Support.',
                'file_name' => $fileName,
                'remote_path' => $remotePath,
                'download_url' => $result['download_url'] ?? $this->buildAttendanceReceiverUrl($safeAdminNo, $fileName, $uploadDateFolder),
                'file_size' => $result['file_size'] ?? $fileSize,
                'filing_id' => $filingId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'file_name' => $fileName, 'remote_path' => $safeAdminNo.'/'.$uploadDateFolder.'/Documents/'.$fileName];
        }
    }

    private function downloadFinalAttendanceFile(): void
    {
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            http_response_code(403);
            echo 'Anda tidak ditugaskan untuk room monitoring ini.';

            return;
        }

        $adminNoRaw = trim((string) ($_POST['admin_no'] ?? 'CBT'));
        $tanggal = $this->normalizeAttendanceDate((string) ($_POST['tanggal'] ?? date('Y-m-d')));
        $format = strtolower(trim((string) ($_POST['format'] ?? '')));
        $ext = $format === 'pdf' ? 'pdf' : 'xlsx';
        $safeAdminNo = $this->ftp->safeFileName($adminNoRaw !== '' ? $adminNoRaw : 'CBT');
        $safeDate = preg_replace('/[^0-9A-Za-z_-]/', '-', $tanggal) ?: date('Y-m-d');
        $fileName = 'Absensi_'.$safeAdminNo.'_'.$safeDate.'.'.$ext;
        $downloadUrl = $this->buildAttendanceReceiverUrl($safeAdminNo, $fileName, date('Ymd'));

        try {
            $binary = $this->downloadRemoteBinary($downloadUrl);
            if ($binary === '') {
                throw new \RuntimeException('File final absensi belum tersedia di server CBT. Generate terlebih dahulu.');
            }

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            header('Content-Type: '.($ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            header('Content-Length: '.strlen($binary));
            echo $binary;
        } catch (\Throwable $e) {
            http_response_code(404);
            echo $e->getMessage();
        }
    }

    private function normalizeAttendanceDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return date('Y-m-d');
        }

        $parsed = DateTime::createFromFormat('d/m/Y', $date) ?: DateTime::createFromFormat('Y-m-d', $date);
        if ($parsed instanceof DateTime) {
            return $parsed->format('Y-m-d');
        }

        $ts = strtotime($date);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function attendanceStorePath(int $adminRecId, string $adminNo, string $tanggal): string
    {
        $safeAdmin = preg_replace('/[^A-Za-z0-9_-]/', '_', $adminNo) ?: 'CBT';
        $safeDate = preg_replace('/[^0-9A-Za-z_-]/', '-', $tanggal) ?: date('Y-m-d');
        $dir = base_path().'/storage/attendance_updates';

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.'/attendance_'.$adminRecId.'_'.$safeAdmin.'_'.$safeDate.'.json';
    }

    private function readAttendanceStore(string $path): array
    {
        if (! is_file($path)) {
            return ['updates' => []];
        }

        $json = file_get_contents($path);
        $data = json_decode(is_string($json) ? $json : '', true);

        if (! is_array($data)) {
            return ['updates' => []];
        }

        if (! isset($data['updates']) || ! is_array($data['updates'])) {
            $data['updates'] = [];
        }

        return $data;
    }

    private function writeAttendanceStore(string $path, array $store): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        return file_put_contents($path, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    private function buildFinalAttendanceItems(int $adminRecId, string $adminNo, string $testType, string $tanggal): array
    {
        $items = $this->getAttendanceBaseItems($adminRecId, $adminNo, $testType);
        $path = $this->attendanceStorePath($adminRecId, $adminNo, $tanggal);
        $store = $this->readAttendanceStore($path);

        foreach (($store['updates'] ?? []) as $auth => $update) {
            if (! is_array($update)) {
                continue;
            }

            if (! isset($items[$auth])) {
                $items[$auth] = [
                    'no' => 0,
                    'authcode' => $auth,
                    'name' => '-',
                    'dob' => '-',
                    'idno' => '-',
                    'hpno' => '-',
                    'attendance' => 'Tidak Hadir',
                    'note' => '',
                    'groupcd' => '-',
                    'custom1' => '',
                    'custom2' => '',
                    'custom3' => '',
                ];
            }

            foreach (['name', 'dob', 'idno', 'hpno', 'attendance', 'note', 'groupcd', 'custom1', 'custom2', 'custom3'] as $field) {
                if (array_key_exists($field, $update)) {
                    $items[$auth][$field] = $update[$field];
                }
            }
        }

        $rows = array_values($items);
        usort($rows, fn ($a, $b) => (int) ($a['seqno'] ?? 0) <=> (int) ($b['seqno'] ?? 0));

        foreach ($rows as $idx => &$row) {
            $row['no'] = $idx + 1;
            unset($row['seqno']);
        }
        unset($row);

        return $rows;
    }

    private function getAttendanceBaseItems(int $adminRecId, string $adminNo, string $testType): array
    {
        $psyskunci = Nisn::shift(trim($adminNo), 3);

        $items = [];
        $seq = 0;
        foreach ($this->monitoringModel->getAttendanceParticipantsByAdmin($adminRecId) as $row) {
            $seq++;
            $auth = trim((string) ($row['authorize'] ?? ''));
            if ($auth === '') {
                continue;
            }

            $items[$auth] = [
                'no' => $seq,
                'seqno' => (int) ($row['seqno'] ?? $seq),
                'authcode' => $auth,
                'name' => Nisn::decrypt(trim((string) ($row['regnm'] ?? '')), $psyskunci) ?: '-',
                'dob' => Nisn::decrypt(trim((string) ($row['dob'] ?? '')), $psyskunci) ?: '-',
                'idno' => Nisn::decrypt(trim((string) ($row['idno'] ?? '')), $psyskunci) ?: '-',
                'hpno' => Nisn::decrypt(trim((string) ($row['hpno'] ?? '')), $psyskunci) ?: '-',
                'attendance' => $this->attendanceDefaultStatus((string) ($row['statrec'] ?? '')),
                'note' => '',
                'groupcd' => Nisn::decrypt(trim((string) ($row['groupcd'] ?? '')), $psyskunci) ?: '-',
                'custom1' => Nisn::decrypt(trim((string) ($row['custom1'] ?? '')), $psyskunci) ?: '',
                'custom2' => Nisn::decrypt(trim((string) ($row['custom2'] ?? '')), $psyskunci) ?: '',
                'custom3' => Nisn::decrypt(trim((string) ($row['custom3'] ?? '')), $psyskunci) ?: '',
            ];
        }

        return $items;
    }

    private function attendanceDefaultStatus(string $statrec): string
    {
        return in_array(strtolower(trim($statrec)), ['4', '5', '6', '7', '8', '9', 'c'], true) ? 'Hadir' : 'Tidak Hadir';
    }

    private function uploadAttendanceViaHttp(string $tmpPath, string $adminNo, string $fileName, string $uploadUrl, string $uploadDateFolder): array
    {
        $token = trim((string) (env('CRC_HTTP_UPLOAD_TOKEN', '')));
        $timeout = max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120));

        if (! function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL tidak tersedia di server. Tidak bisa upload absensi via HTTP.'];
        }

        $mimeType = str_ends_with(strtolower($fileName), '.pdf')
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpPath);
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
            return ['success' => false, 'message' => 'Gagal initialisasi cURL untuk upload absensi.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'type' => 'attendance',
                'admin_no' => $adminNo,
                'test_date' => $uploadDateFolder,
                'tanggal' => $uploadDateFolder,
                'file_size' => (string) filesize($tmpPath),
                'file' => new CURLFile($tmpPath, $mimeType, $fileName),
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
            return ['success' => false, 'message' => 'cURL error upload absensi: '.$error];
        }

        if ($status < 200 || $status >= 300) {
            $body = '';
            if (is_string($response) && trim($response) !== '') {
                $json = json_decode($response, true);
                $body = is_array($json) && isset($json['message']) ? $json['message'] : substr(trim(strip_tags($response)), 0, 200);
            }

            return ['success' => false, 'message' => 'Server CBT menerima error (HTTP '.$status.'): '.$body];
        }

        $result = is_string($response) && trim($response) !== '' ? json_decode($response, true) : null;
        if (! is_array($result) || empty($result['success'])) {
            return ['success' => false, 'message' => is_array($result) && isset($result['message']) ? $result['message'] : 'Respon server CBT tidak valid.'];
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'File absensi final berhasil diupload.',
            'file_name' => $result['file_name'] ?? $fileName,
            'file_size' => $result['file_size'] ?? filesize($tmpPath),
            'remote_path' => $result['remote_path'] ?? ($adminNo.'/'.$uploadDateFolder.'/Documents/'.$fileName),
            'download_url' => $result['download_url'] ?? $this->buildAttendanceReceiverUrl($adminNo, $fileName, $uploadDateFolder),
        ];
    }

    private function buildAttendanceReceiverUrl(string $adminNo, string $fileName, ?string $uploadDateFolder = null): string
    {
        $baseUrl = trim((string) env('CRC_PUBLIC_BASE_URL', ''));
        if ($baseUrl === '') {
            $uploadUrl = trim((string) env('CRC_HTTP_UPLOAD_URL', ''));
            $baseUrl = $uploadUrl !== '' ? preg_replace('#/[^/]*$#', '', $uploadUrl) : '';
        }

        if ($baseUrl === '') {
            throw new \RuntimeException('CRC_PUBLIC_BASE_URL atau CRC_HTTP_UPLOAD_URL belum dikonfigurasi.');
        }

        $uploadDateFolder = $uploadDateFolder ?: date('Ymd');

        return rtrim($baseUrl, '/').'/'.rawurlencode($adminNo).'/'.rawurlencode($uploadDateFolder).'/Documents/'.rawurlencode($fileName);
    }

    private function downloadRemoteBinary(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno !== 0) {
                throw new \RuntimeException('Gagal download file final absensi: '.$error);
            }

            if ($status < 200 || $status >= 300 || ! is_string($body)) {
                throw new \RuntimeException('File final absensi belum tersedia di server CBT.');
            }

            return $body;
        }

        $body = @file_get_contents($url);
        if (! is_string($body)) {
            throw new \RuntimeException('File final absensi belum tersedia di server CBT.');
        }

        return $body;
    }

    private function uploadCrcZipToFtp(): array
    {
        $this->extendUploadExecutionTime();

        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);

        if (! $this->canAccessRoom($adminRecId, $subAdminId)) {
            return [
                'success' => false,
                'message' => 'Anda tidak ditugaskan untuk room monitoring ini.',
            ];
        }

        if (! $this->canCollectCrcFromRoom($adminRecId, $subAdminId)) {
            return [
                'success' => false,
                'message' => 'Role TAD ADMIN/STAFF hanya dapat memonitor realtime dan tidak dapat collect data CRC.',
            ];
        }

        if (empty($_FILES['crc_zip']['tmp_name']) || ! is_uploaded_file($_FILES['crc_zip']['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'File ZIP CRC tidak diterima server.',
            ];
        }

        $tmpName = $_FILES['crc_zip']['tmp_name'];
        $fileSize = filesize($tmpName);

        if ($fileSize === false || $fileSize <= 0) {
            return [
                'success' => false,
                'message' => 'File ZIP CRC kosong atau gagal terbaca.',
            ];
        }

        $adminNoRaw = trim((string) ($_POST['admin_no'] ?? 'CBT'));
        $adminNoRaw = preg_replace('/\s+-\s+ALL$/i', '', $adminNoRaw);
        $adminNo = $this->ftp->safeFileName($adminNoRaw !== '' ? $adminNoRaw : 'CBT');
        $userName = session('account_nm') ?? session('user_name') ?? 'System';
        $spvName = $this->filingSystem->getSpvName(auth_user_id(), $userName);

        $fileName = 'CRC_KUMPULAN_'.$adminNo.'.zip';
        $testDateFolder = $this->normalizeTestDateForPath((string) ($_POST['tanggal'] ?? $_POST['test_date'] ?? date('Y-m-d')));

        $httpUploadUrl = trim((string) (env('CRC_HTTP_UPLOAD_URL', '')));
        if ($httpUploadUrl !== '') {
            $result = $this->uploadCrcViaHttp($tmpName, $adminNo, $fileName, $httpUploadUrl, $testDateFolder);
            if (! empty($result['success'])) {
                $this->filingSystem->touchFilingActivity([
                    'admin_no' => $adminNo,
                    'admin_rec_id' => $adminRecId,
                    'sub_admin_id' => $subAdminId,
                    'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
                    'keterangan' => 'Collect Data CRC',
                    'input_by' => $userName,
                    'spv_name' => $spvName,
                ]);
            }

            return $result;
        }

        $remotePath = $adminNo.'/'.$testDateFolder.'/'.$fileName;
        $normalizedRemotePath = $this->ftp->normalizePath($remotePath);

        try {
            $this->ftp->connect();
            $this->ftp->upload($tmpName, $remotePath);

            $remoteSize = $this->ftp->size($remotePath);
            if ($remoteSize <= 0) {
                throw new \RuntimeException('ZIP CRC sudah dikirim tapi ukuran di FTP tidak terbaca atau 0 byte.');
            }

            $this->ftp->close();

            $this->filingSystem->touchFilingActivity([
                'admin_no' => $adminNo,
                'admin_rec_id' => $adminRecId,
                'sub_admin_id' => $subAdminId,
                'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
                'keterangan' => 'Collect Data CRC',
                'input_by' => $userName,
                'spv_name' => $spvName,
            ]);

            return [
                'success' => true,
                'message' => 'ZIP CRC berhasil diupload ke FTP.',
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
                'file_size' => $remoteSize,
                'remote_path' => $normalizedRemotePath,
            ];
        } catch (\Throwable $e) {
            $this->ftp->close();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
                'remote_path' => $normalizedRemotePath,
            ];
        }
    }

    private function uploadCrcViaHttp(string $tmpPath, string $adminNo, string $fileName, string $uploadUrl, string $testDateFolder): array
    {
        $token = trim((string) (env('CRC_HTTP_UPLOAD_TOKEN', '')));
        $timeout = max(30, (int) env('CRC_HTTP_UPLOAD_TIMEOUT', 120));

        if (! function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'cURL tidak tersedia di server. Tidak bisa upload CRC via HTTP.',
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
            ];
        }

        $mimeType = 'application/zip';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpPath);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        $postFields = [
            'admin_no' => $adminNo,
            'test_date' => $testDateFolder,
            'file_size' => (string) filesize($tmpPath),
            'file' => new CURLFile($tmpPath, $mimeType, $fileName),
        ];

        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init($uploadUrl);
        if (! $ch) {
            return [
                'success' => false,
                'message' => 'Gagal initialisasi cURL.',
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
            ];
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
            return [
                'success' => false,
                'message' => 'cURL error: '.$error,
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
            ];
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

            return [
                'success' => false,
                'message' => 'Server CRC menerima error (HTTP '.$status.'): '.$body,
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
            ];
        }

        $result = null;
        if (is_string($response) && trim($response) !== '') {
            $result = json_decode($response, true);
        }

        if (! is_array($result) || empty($result['success'])) {
            $msg = is_array($result) && isset($result['message']) ? $result['message'] : 'Respon server CRC tidak valid.';

            return [
                'success' => false,
                'message' => $msg,
                'admin_folder' => $adminNo,
                'file_name' => $fileName,
            ];
        }

        return [
            'success' => true,
            'message' => 'CRC berhasil diupload dan diekstrak ke folder tanggal ujian server CBT.',
            'admin_folder' => $adminNo,
            'file_name' => $result['file_name'] ?? $testDateFolder,
            'file_size' => filesize($tmpPath),
            'file_count' => $result['file_count'] ?? null,
            'remote_path' => $adminNo.'/'.$testDateFolder,
        ];
    }

    private function markCrcCollected(): array
    {
        $adminRecId = (int) ($_POST['admin_rec_id'] ?? 0);
        $subAdminId = (int) ($_POST['sub_admin_id'] ?? 0);
        $selectedTakers = $_POST['selected_takers'] ?? [];

        if (! $this->canAccessRoom($adminRecId, $subAdminId) || ! $this->canCollectCrcFromRoom($adminRecId, $subAdminId)) {
            return ['success' => false, 'message' => 'Anda tidak ditugaskan untuk collect CRC room ini.'];
        }

        if (! is_array($selectedTakers) || count($selectedTakers) === 0) {
            return ['success' => false, 'message' => 'Tidak ada peserta yang dikirim untuk ditandai collected.'];
        }

        $selectedTakers = array_values(array_unique(array_map('intval', array_filter($selectedTakers, 'is_numeric'))));
        if (count($selectedTakers) === 0) {
            return ['success' => false, 'message' => 'Data peserta tidak valid.'];
        }

        $placeholders = implode(',', array_fill(0, count($selectedTakers), '?'));
        $params = array_merge([$adminRecId, $subAdminId], $selectedTakers);

        $stmtCount = $this->pdoWar->prepare("\n            SELECT COUNT(*)\n            FROM t3sTt4keR5\n            WHERE admin_id = ?\n              AND sub_adm_id = ?\n              AND rec_id IN ($placeholders)\n        ");
        $stmtCount->execute($params);
        $roomCount = (int) $stmtCount->fetchColumn();

        if ($roomCount !== count($selectedTakers)) {
            return ['success' => false, 'message' => 'Sebagian peserta tidak berada di room monitoring ini.'];
        }

        $stmtUpdate = $this->pdoWar->prepare("\n            UPDATE t3sTt4keR5\n            SET statrec = '8'\n            WHERE admin_id = ?\n              AND sub_adm_id = ?\n              AND rec_id IN ($placeholders)\n              AND statrec = '7'\n              AND COALESCE(ke_suspend, 0) <> 1\n        ");
        $stmtUpdate->execute($params);
        $updated = $stmtUpdate->rowCount();

        return [
            'success' => true,
            'message' => 'Peserta collected berhasil ditandai.',
            'selected_count' => count($selectedTakers),
            'updated_count' => $updated,
            'skipped_count' => count($selectedTakers) - $updated,
        ];
    }

    private function normalizeTestDateForPath(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return date('Ymd');
        }

        $digits = preg_replace('/[^0-9]/', '', $date);
        if (strlen($digits) === 8) {
            $dt = DateTime::createFromFormat('Ymd', $digits);
            if ($dt instanceof DateTime) {
                return $dt->format('Ymd');
            }
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Ymd', $timestamp) : date('Ymd');
    }

    private function canAccessRoom(int $adminRecId, int $subAdminId): bool
    {
        if ($adminRecId <= 0 || $subAdminId <= 0) {
            return false;
        }

        $userId = (int) auth_user_id();

        // TAD ADMIN/STAFF can access any room
        if (TadAccess::userHasRole($this->pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN'])) {
            return true;
        }

        $spvData = $this->monitoringModel->getSupervisorByUserId($userId);

        if (! $spvData) {
            return false;
        }

        return $this->monitoringModel->isSupervisorAssignedToRoom($spvData['rec_id'], $adminRecId, $subAdminId);
    }

    private function canCollectCrcFromRoom(int $adminRecId, int $subAdminId): bool
    {
        if ($adminRecId <= 0 || $subAdminId <= 0) {
            return false;
        }

        $userId = (int) auth_user_id();

        $spvData = $this->monitoringModel->getSupervisorByUserId($userId);

        if (! $spvData) {
            return TadAccess::userHasRole($this->pdoRun, $userId, ['SUPER ADMIN']);
        }

        return $this->monitoringModel->isSupervisorAssignedToRoom($spvData['rec_id'], $adminRecId, $subAdminId);
    }

    private function extendUploadExecutionTime(): void
    {
        @ini_set('max_execution_time', '180');
        @set_time_limit(180);
    }

    private function handleRealtimeAjax(): void
    {
        $adminRecId = $_GET['admin_rec_id'] ?? 0;
        $subAdminId = $_GET['sub_admin_id'] ?? 0;
        $adminNo = $_GET['admin_no'] ?? '';
        $testType = $_GET['test_type'] ?? '';
        $isFinished = ($_GET['finished'] ?? '0') == '1';
        $userId = (int) auth_user_id();

        try {
            // TAD ADMIN/STAFF can access any room
            $isAdminStaff = TadAccess::userHasRole($this->pdoRun, $userId, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);

            if (! $isAdminStaff) {
                $spvData = $this->monitoringModel->getSupervisorByUserId($userId);

                if (
                    ! $spvData ||
                    ! $this->monitoringModel->isSupervisorAssignedToRoom($spvData['rec_id'], $adminRecId, $subAdminId)
                ) {
                    http_response_code(403);
                    echo '<tr><td colspan="8" class="px-6 py-4 text-center text-red-500 font-bold">Akses monitoring ditolak untuk room ini.</td></tr>';

                    return;
                }
            }

            $result = $this->monitoringModel->getParticipants(
                $adminRecId,
                $subAdminId,
                $adminNo,
                $testType
            );

            echo ParticipantTableRenderer::render(
                $result['participants'],
                $isFinished,
                $result['total_timing']
            );
        } catch (\Exception $e) {
            echo '<tr><td colspan="8" class="px-6 py-4 text-center text-red-500 font-bold">Error Database: '.htmlspecialchars($e->getMessage()).'</td></tr>';
        }
    }

    private function getPageData(): array
    {
        $itc_id = auth_user_id();
        if (! $itc_id) {
            return $this->getDefaultPageData('Akses Ditolak: ITC ID (Account ID) tidak ditemukan dalam sesi Anda.');
        }

        try {
            // TAD ADMIN/STAFF can see all rooms without SPV record
            $isAdminStaff = TadAccess::userHasRole($this->pdoRun, $itc_id, ['TAD ADMIN', 'TAD STAFF', 'SUPER ADMIN']);
            if ($isAdminStaff) {
                return $this->getAdminStaffPageData($itc_id);
            }

            $spvData = $this->monitoringModel->getSupervisorByUserId($itc_id);
            if (! $spvData) {
                return $this->getDefaultPageData(null, $itc_id);
            }

            return $this->getSpvPageData($itc_id, $spvData);
        } catch (\PDOException $e) {
            return $this->getDefaultPageData('<b>Terjadi Kesalahan Database:</b><br>'.$e->getMessage(), $itc_id);
        }
    }

    private function getDefaultPageData(?string $error = null, $itc_id = null): array
    {
        return [
            'fatal_error' => $error,
            'itc_id' => $itc_id,
            'has_spv_access' => false,
            'filtered_admins' => [],
            'participants' => [],
            'active_dates_json' => '[]',
            'test_info' => null,
            'current_sub_admin_id' => null,
            'current_admin_rec_id' => null,
            'current_admin_no' => null,
            'current_test_type' => null,
            'total_timing' => 0,
            'spv' => [
                'id' => $itc_id,
                'name' => 'Unknown',
                'photo_url' => 'https://ui-avatars.com/api/?name=SPV',
            ],
            'is_in_room' => false,
            'is_finished' => false,
            'is_locked' => false,
            'can_collect_crc' => false,
            'selected_date' => $_GET['date'] ?? date('Y-m-d'),
            'selected_dropdown' => null,
            'monitoring_mode' => $this->resolveRequestedMonitoringMode(),
            'is_hybrid_mode' => $this->resolveRequestedMonitoringMode() === 'hybrid',
        ];
    }

    private function getSpvPageData($itc_id, array $spvData): array
    {
        $spvName = ! empty($spvData['spv_name']) ? $spvData['spv_name'] : $spvData['spv_alias'];
        $requestedMonitoringMode = $this->resolveRequestedMonitoringMode();
        $filtered_admins = $this->getFilteredAdmins($spvData['rec_id']);

        $selected_dropdown = isset($_GET['admin']) && array_key_exists($_GET['admin'], $filtered_admins)
            ? $_GET['admin']
            : null;

        $current_admin_rec_id = null;
        $current_admin_no = null;
        $current_sub_admin_id = null;
        $current_test_type = null;
        $current_monitoring_mode = $requestedMonitoringMode;
        $test_info = null;
        $participants = [];
        $total_timing = 0;

        if ($selected_dropdown) {
            $selectedAdmin = $filtered_admins[$selected_dropdown];
            $current_admin_rec_id = $selectedAdmin['admin_rec_id'];
            $current_admin_no = $selectedAdmin['admin_no'];
            $current_sub_admin_id = $selectedAdmin['sub_admin_id'];
            $current_test_type = $selectedAdmin['type'];
            // Mode tampilan: online/hybrid. Untuk menu Hybrid, pakai monitoring_mode=hybrid pada URL.
            // Jika nanti query model sudah punya kolom pembeda, isi $adminData['monitoring_mode'] akan otomatis dipakai untuk badge.
            $current_monitoring_mode = $requestedMonitoringMode === 'hybrid'
                ? 'hybrid'
                : ($selectedAdmin['monitoring_mode'] ?? $requestedMonitoringMode);

            $test_info = [
                'admin_code' => $current_admin_no,
                'test_date' => date('d/m/Y', strtotime($selectedAdmin['date'])),
                'test_type' => $current_test_type,
                'client_nm' => $selectedAdmin['client_nm'],
                'total_participants' => $selectedAdmin['qty'],
                'test_datetime_raw' => $selectedAdmin['date'],
                'test_day' => date('l', strtotime($selectedAdmin['date'])),
                'test_time' => date('H:i', strtotime($selectedAdmin['date'])),
                'monitoring_mode' => $current_monitoring_mode,
            ];

            $result = $this->monitoringModel->getParticipants(
                $current_admin_rec_id,
                $current_sub_admin_id,
                $current_admin_no,
                $current_test_type
            );

            $participants = $result['participants'];
            $total_timing = $result['total_timing'];
        }

        $is_finished = ($_GET['finished'] ?? '0') === '1';
        $is_in_room = $current_admin_rec_id !== null;
        $can_collect_crc = $is_in_room && $this->canCollectCrcFromRoom((int) $current_admin_rec_id, (int) $current_sub_admin_id);

        return [
            'fatal_error' => null,
            'itc_id' => $itc_id,
            'has_spv_access' => true,
            'filtered_admins' => $filtered_admins,
            'participants' => $participants,
            'active_dates_json' => json_encode($this->monitoringModel->getActiveDates($spvData['rec_id'])),
            'test_info' => $test_info,
            'current_sub_admin_id' => $current_sub_admin_id,
            'current_admin_rec_id' => $current_admin_rec_id,
            'current_admin_no' => $current_admin_no,
            'current_test_type' => $current_test_type,
            'total_timing' => $total_timing,
            'spv' => [
                'id' => $itc_id,
                'name' => $spvName,
                'photo_url' => ! empty($spvData['photo_path'])
                    ? $spvData['photo_path']
                    : 'https://ui-avatars.com/api/?name='.urlencode($spvName).'&background=0D8ABC&color=fff',
            ],
            'is_in_room' => $is_in_room,
            'is_finished' => $is_finished,
            'is_locked' => $is_in_room && ! $is_finished,
            'can_collect_crc' => $can_collect_crc,
            'selected_date' => $selected_dropdown ? date('Y-m-d', strtotime($filtered_admins[$selected_dropdown]['date'] ?? $filtered_admins[$selected_dropdown]['dropdown_date'])) : ($_GET['date'] ?? date('Y-m-d')),
            'selected_dropdown' => $selected_dropdown,
            'monitoring_mode' => $current_monitoring_mode,
            'is_hybrid_mode' => $current_monitoring_mode === 'hybrid',
        ];
    }

    private function getAdminStaffPageData($itc_id): array
    {
        $userName = session('account_nm') ?? session('user_name') ?? 'Admin';
        $requestedMonitoringMode = $this->resolveRequestedMonitoringMode();
        $filtered_admins = $this->getFilteredAdmins(null);

        $selected_dropdown = isset($_GET['admin']) && array_key_exists($_GET['admin'], $filtered_admins)
            ? $_GET['admin']
            : null;

        $current_admin_rec_id = null;
        $current_admin_no = null;
        $current_sub_admin_id = null;
        $current_test_type = null;
        $current_monitoring_mode = $requestedMonitoringMode;
        $test_info = null;
        $participants = [];
        $total_timing = 0;

        if ($selected_dropdown) {
            $selectedAdmin = $filtered_admins[$selected_dropdown];
            $current_admin_rec_id = $selectedAdmin['admin_rec_id'];
            $current_admin_no = $selectedAdmin['admin_no'];
            $current_sub_admin_id = $selectedAdmin['sub_admin_id'];
            $current_test_type = $selectedAdmin['type'];
            $current_monitoring_mode = $requestedMonitoringMode === 'hybrid'
                ? 'hybrid'
                : ($selectedAdmin['monitoring_mode'] ?? $requestedMonitoringMode);

            $test_info = [
                'admin_code' => $current_admin_no,
                'test_date' => date('d/m/Y', strtotime($selectedAdmin['date'])),
                'test_type' => $current_test_type,
                'client_nm' => $selectedAdmin['client_nm'],
                'total_participants' => $selectedAdmin['qty'],
                'test_datetime_raw' => $selectedAdmin['date'],
                'test_day' => date('l', strtotime($selectedAdmin['date'])),
                'test_time' => date('H:i', strtotime($selectedAdmin['date'])),
                'monitoring_mode' => $current_monitoring_mode,
            ];

            $result = $this->monitoringModel->getParticipants(
                $current_admin_rec_id,
                $current_sub_admin_id,
                $current_admin_no,
                $current_test_type
            );

            $participants = $result['participants'];
            $total_timing = $result['total_timing'];
        }

        $is_finished = ($_GET['finished'] ?? '0') === '1';
        $is_in_room = $current_admin_rec_id !== null;
        $can_collect_crc = $is_in_room && $this->canCollectCrcFromRoom((int) $current_admin_rec_id, (int) $current_sub_admin_id);

        return [
            'fatal_error' => null,
            'itc_id' => $itc_id,
            'has_spv_access' => true,
            'filtered_admins' => $filtered_admins,
            'participants' => $participants,
            'active_dates_json' => json_encode($this->monitoringModel->getActiveDates(null)),
            'test_info' => $test_info,
            'current_sub_admin_id' => $current_sub_admin_id,
            'current_admin_rec_id' => $current_admin_rec_id,
            'current_admin_no' => $current_admin_no,
            'current_test_type' => $current_test_type,
            'total_timing' => $total_timing,
            'spv' => [
                'id' => $itc_id,
                'name' => $userName,
                'photo_url' => 'https://ui-avatars.com/api/?name='.urlencode($userName).'&background=0D8ABC&color=fff',
            ],
            'is_in_room' => $is_in_room,
            'is_finished' => $is_finished,
            'is_locked' => $is_in_room && ! $is_finished,
            'can_collect_crc' => $can_collect_crc,
            'selected_date' => $selected_dropdown ? date('Y-m-d', strtotime($filtered_admins[$selected_dropdown]['date'] ?? $filtered_admins[$selected_dropdown]['dropdown_date'])) : ($_GET['date'] ?? date('Y-m-d')),
            'selected_dropdown' => $selected_dropdown,
            'monitoring_mode' => $current_monitoring_mode,
            'is_hybrid_mode' => $current_monitoring_mode === 'hybrid',
        ];
    }

    private function getFilteredAdmins($spvRecId, ?string $monitoringMode = null): array
    {
        $activeDates = $this->monitoringModel->getActiveDates($spvRecId);
        $filtered = [];

        $validDates = array_values(array_filter($activeDates, static function ($taskDate) {
            return is_string($taskDate) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $taskDate);
        }));

        foreach ($this->monitoringModel->getAdminsByDates($spvRecId, $validDates) as $key => $adminData) {
            $taskDate = ! empty($adminData['date']) ? date('Y-m-d', strtotime($adminData['date'])) : ($adminData['dropdown_date'] ?? date('Y-m-d'));
            $dropdownKey = $taskDate.'__'.$key;
            $adminData['dropdown_original_key'] = $key;
            $adminData['dropdown_date'] = $taskDate;
            $adminData['monitoring_mode'] = $this->detectMonitoringMode($adminData);

            $filtered[$dropdownKey] = $adminData;
        }

        uasort($filtered, fn ($a, $b) => strtotime($a['date'] ?? $a['dropdown_date']) <=> strtotime($b['date'] ?? $b['dropdown_date']));

        return $filtered;
    }

    private function resolveRequestedMonitoringMode(): string
    {
        $mode = strtolower(trim((string) ($_GET['monitoring_mode'] ?? 'online')));

        return $mode === 'hybrid' ? 'hybrid' : 'online';
    }

    private function detectMonitoringMode(array $adminData): string
    {
        // Pembeda resmi dari database warmesin_cbt.t3sTAdm1n:
        // conn_type=1 => Online, conn_type=2 => Hybrid.
        if (array_key_exists('conn_type', $adminData)) {
            return (int) $adminData['conn_type'] === 2 ? 'hybrid' : 'online';
        }

        // Fallback sementara jika query model belum SELECT conn_type.
        $fields = [
            $adminData['monitoring_mode'] ?? '',
            $adminData['delivery_mode'] ?? '',
            $adminData['test_mode'] ?? '',
            $adminData['exam_mode'] ?? '',
            $adminData['type'] ?? '',
            $adminData['admin_no'] ?? '',
        ];

        $haystack = strtolower(trim(implode(' ', array_map('strval', $fields))));

        if (
            strpos($haystack, 'hybrid') !== false ||
            strpos($haystack, 'hibrid') !== false ||
            strpos($haystack, 'hbd') !== false
        ) {
            return 'hybrid';
        }

        return 'online';
    }
}
