<?php

date_default_timezone_set('Asia/Jakarta');

/**
 * CRC / Berita Acara HTTP Upload Receiver
 *
 * Deploy ke: /www/wwwroot/cbt.toeic.or.id/docs/CBT/CRC/crc_receiver.php
 *
 * Environment variables (set via aaPanel / hosting panel):
 *   CRC_UPLOAD_TOKEN        - Bearer token untuk autentikasi
 *   CRC_STORAGE_ROOT        - Base path penyimpanan (default: __DIR__)
 *
 * Contoh test dengan curl:
 *   CRC:  curl -X POST ... -F "type=crc"       -F "admin_no=B00932" -F "file=@CRC_KUMPULAN_B00932.zip"
 *   BA:   curl -X POST ... -F "type=berita_acara" -F "admin_no=B00932" -F "file=@berita_acara.pdf"
 *   File: curl -X POST ... -F "type=filing" -F "admin_no=B00932" -F "file=@dokumen.pdf"
 *   Absensi: curl -X POST ... -F "type=attendance" -F "admin_no=B00932" -F "file=@Absensi_B00932.xlsx"
 *
 * Untuk CRC, ZIP hanya dipakai sebagai paket transfer. Isi ZIP diekstrak ke:
 *   {CRC_STORAGE_ROOT}/{admin_no}/{YYYYMMDD}/Rev-{admin_no}.CRC
 *   {CRC_STORAGE_ROOT}/{admin_no}/{YYYYMMDD}/CRC/*.CRC
 * Untuk dokumen pendukung:
 *   {CRC_STORAGE_ROOT}/{admin_no}/{YYYYMMDD}/Documents/*
 */

header('Content-Type: application/json; charset=utf-8');

function rcvrJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function rcvrSafeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim($path, '/');
    return $path;
}

function rcvrPublicUrl(string $adminNo, string $relativePath): string
{
    $baseUrl = rtrim((string) (getenv('CRC_PUBLIC_BASE_URL') ?: ''), '/');
    if ($baseUrl === '') {
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'cbt.toeic.or.id';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/docs/CBT/CRC/crc_receiver.php')), '/');
        $baseUrl = $scheme . '://' . $host . $scriptDir;
    }

    $path = $adminNo . '/' . rcvrSafeRelativePath($relativePath);
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

    return $baseUrl . '/' . $encodedPath;
}

function rcvrFilePayload(string $adminNo, string $relativePath, string $category, string $absolutePath): array
{
    $fileName = basename($relativePath);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    return [
        'file_id' => null,
        'file_name' => $fileName,
        'file_type' => $ext,
        'file_category' => $category,
        'file_path' => $adminNo . '/' . rcvrSafeRelativePath($relativePath),
        'relative_path' => rcvrSafeRelativePath($relativePath),
        'uploaded_at' => file_exists($absolutePath) ? date('Y-m-d H:i:s', filemtime($absolutePath)) : '',
        'size' => file_exists($absolutePath) ? filesize($absolutePath) : 0,
        'download_url' => rcvrPublicUrl($adminNo, $relativePath),
    ];
}

// --- Validasi method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rcvrJsonResponse(405, ['success' => false, 'message' => 'Method tidak diizinkan.']);
}

// --- Validasi token ---
$token = trim((string) (getenv('CRC_UPLOAD_TOKEN') ?: 'annas123'));
if ($token === '') {
    rcvrJsonResponse(503, ['success' => false, 'message' => 'CRC_UPLOAD_TOKEN belum dikonfigurasi.']);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || ! hash_equals($token, trim($matches[1]))) {
    rcvrJsonResponse(401, ['success' => false, 'message' => 'Token tidak valid.']);
}

// --- Tipe upload ---
$type = trim((string) ($_POST['type'] ?? 'crc'));
if (! in_array($type, ['crc', 'berita_acara', 'filing', 'attendance'], true)) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Tipe tidak dikenal. Gunakan "crc", "berita_acara", "filing", atau "attendance".']);
}

// --- Validasi admin_no ---
$adminNo = trim((string) ($_POST['admin_no'] ?? ''));
if ($adminNo === '') {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Parameter admin_no wajib diisi.']);
}

$adminNo = basename($adminNo);
$adminNo = preg_replace('/\s+/', '_', $adminNo);
$adminNo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $adminNo);
$adminNo = trim($adminNo, '._-');
if ($adminNo === '') {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'admin_no tidak valid setelah sanitasi.']);
}

$action = trim((string) ($_POST['action'] ?? 'upload'));
$storageRoot = rtrim((string) (getenv('CRC_STORAGE_ROOT') ?: __DIR__), '\\/');

$testDate = preg_replace('/[^0-9]/', '', (string) ($_POST['test_date'] ?? $_POST['tanggal'] ?? ''));
if ($testDate !== '') {
    if (strlen($testDate) === 8) {
        $dt = DateTime::createFromFormat('Ymd', $testDate);
    } else {
        $dt = DateTime::createFromFormat('Ymd', date('Ymd', strtotime((string) ($_POST['test_date'] ?? $_POST['tanggal'] ?? ''))));
    }

    $testDate = $dt instanceof DateTime ? $dt->format('Ymd') : '';
}

if ($testDate === '' && $action === 'upload') {
    $testDate = date('Ymd');
}

if ($action === 'list') {
    $adminDir = $storageRoot . '/' . $adminNo;
    $filesByCategory = [
        'crc_individual' => [],
        'crc_gabungan' => [],
        'berita_acara' => [],
        'dokumen_support' => [],
    ];

    if (is_dir($adminDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($adminDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = rcvrSafeRelativePath(substr($absolutePath, strlen($adminDir) + 1));
            $fileName = basename($relativePath);

            if (preg_match('#^(GrabCRC|[0-9]{8})/CRC/[^/]+\.CRC$#i', $relativePath)) {
                $filesByCategory['crc_individual'][] = rcvrFilePayload($adminNo, $relativePath, 'crc_individual', $absolutePath);
                continue;
            }

            if (preg_match('#^(GrabCRC|[0-9]{8})/Rev-[^/]+\.CRC$#i', $relativePath)) {
                $filesByCategory['crc_gabungan'][] = rcvrFilePayload($adminNo, $relativePath, 'crc_gabungan', $absolutePath);
                continue;
            }

            if (preg_match('#^[0-9]{8}/Documents/[^/]*BeritaAcara[^/]*\.pdf$#i', $relativePath)) {
                $filesByCategory['berita_acara'][] = rcvrFilePayload($adminNo, $relativePath, 'berita_acara', $absolutePath);
                continue;
            }

            if (preg_match('#^[0-9]{8}/Documents/[^/]+\.(xlsx|pdf|doc|docx|jpg|jpeg|png|zip|rar)$#i', $relativePath)) {
                $filesByCategory['dokumen_support'][] = rcvrFilePayload($adminNo, $relativePath, 'dokumen_support', $absolutePath);
                continue;
            }

            if (preg_match('#^Absensi/[^/]+\.(xlsx|pdf)$#i', $relativePath)) {
                $filesByCategory['dokumen_support'][] = rcvrFilePayload($adminNo, $relativePath, 'dokumen_support', $absolutePath);
                continue;
            }

            if (stripos($fileName, 'BeritaAcara') !== false && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
                $filesByCategory['berita_acara'][] = rcvrFilePayload($adminNo, $relativePath, 'berita_acara', $absolutePath);
                continue;
            }

            if (strpos($relativePath, '/') === false) {
                $filesByCategory['dokumen_support'][] = rcvrFilePayload($adminNo, $relativePath, 'dokumen_support', $absolutePath);
            }
        }
    }

    rcvrJsonResponse(200, [
        'success' => true,
        'admin_no' => $adminNo,
        'files_by_category' => $filesByCategory,
    ]);
}

if ($action === 'download_zip') {
    if (! class_exists('ZipArchive')) {
        rcvrJsonResponse(500, ['success' => false, 'message' => 'Extension ZipArchive belum aktif di server receiver.']);
    }

    $adminDir = $storageRoot . '/' . $adminNo;
    if (! is_dir($adminDir)) {
        rcvrJsonResponse(404, ['success' => false, 'message' => 'Folder admin tidak ditemukan.']);
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'admin_crc_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        rcvrJsonResponse(500, ['success' => false, 'message' => 'Gagal membuat ZIP download.']);
    }

    $fileCount = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($adminDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $absolutePath = $file->getPathname();
        $relativePath = rcvrSafeRelativePath(substr($absolutePath, strlen($adminDir) + 1));
        $zip->addFile($absolutePath, $relativePath);
        $fileCount++;
    }

    $zip->close();

    if ($fileCount === 0 || ! file_exists($zipPath) || filesize($zipPath) <= 0) {
        @unlink($zipPath);
        rcvrJsonResponse(404, ['success' => false, 'message' => 'Folder admin tidak berisi file.']);
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $adminNo . '.zip"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

// --- Validasi file ---
if (! isset($_FILES['file']) || ! is_array($_FILES['file'])) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'File belum dikirim.']);
}

$upload = $_FILES['file'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Upload file gagal. Error: ' . ($upload['error'] ?? 'unknown')]);
}

$tmpName = (string) ($upload['tmp_name'] ?? '');
if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'File upload tidak valid.']);
}

$origName = basename($upload['name'] ?? ('upload_' . $adminNo));
$origName = preg_replace('/\s+/', '_', $origName);
$origName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $origName);
$origName = trim($origName, '._-');
if ($origName === '') {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Nama file tidak valid setelah sanitasi.']);
}
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if ($type === 'crc' && $ext !== 'zip') {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Format file CRC harus .ZIP.']);
}
if ($type === 'berita_acara' && $ext !== 'pdf') {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Format file Berita Acara harus .PDF.']);
}
if ($type === 'attendance' && ! in_array($ext, ['xlsx', 'pdf'], true)) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Format file absensi harus .XLSX atau .PDF.']);
}
if ($type === 'filing') {
    $blockedExt = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'com', 'scr',
        'sh', 'bash', 'cgi', 'pl', 'py',
        'js', 'html', 'htm', 'htaccess'
    ];

    if ($ext === '' || in_array($ext, $blockedExt, true)) {
        rcvrJsonResponse(400, ['success' => false, 'message' => 'Tipe file tidak diizinkan.']);
    }
}

$fileSize = filesize($tmpName);
if ($fileSize === false || $fileSize <= 0) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'File kosong.']);
}

$maxSize = $type === 'crc' ? 50 * 1024 * 1024 : 25 * 1024 * 1024;
if ($fileSize > $maxSize) {
    rcvrJsonResponse(400, ['success' => false, 'message' => 'Ukuran file terlalu besar (max ' . ($maxSize / 1024 / 1024) . 'MB).']);
}

// --- Tentukan path tujuan ---
$storageRoot = rtrim((string) (getenv('CRC_STORAGE_ROOT') ?: __DIR__), '\\/');
$fileName    = $type === 'crc' ? ($testDate !== '' ? $testDate : 'GrabCRC') : $origName;
$targetDir   = $type === 'crc'
    ? $storageRoot . '/' . $adminNo . '/' . $fileName
    : $storageRoot . '/' . $adminNo . '/' . $testDate . '/Documents';
$targetPath  = $type === 'crc' ? $targetDir : $targetDir . '/' . $fileName;

if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
    rcvrJsonResponse(500, ['success' => false, 'message' => 'Gagal membuat folder: ' . $targetDir]);
}

if (! is_writable($targetDir)) {
    rcvrJsonResponse(500, ['success' => false, 'message' => 'Folder tujuan tidak writable.']);
}

if ($type === 'crc') {
    if (! class_exists('ZipArchive')) {
        rcvrJsonResponse(500, ['success' => false, 'message' => 'Extension ZipArchive belum aktif di server receiver.']);
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpName) !== true) {
        rcvrJsonResponse(400, ['success' => false, 'message' => 'File ZIP CRC tidak bisa dibuka.']);
    }

    $extractedFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $entry = str_replace('\\', '/', (string) $entry);
        $entry = ltrim($entry, '/');

        if ($entry === '' || str_contains($entry, '../') || str_starts_with($entry, '..')) {
            $zip->close();
            rcvrJsonResponse(400, ['success' => false, 'message' => 'ZIP berisi path tidak aman.']);
        }

        if (str_ends_with($entry, '/')) {
            continue;
        }

        if (! preg_match('#^(Rev-[A-Za-z0-9_.-]+\.CRC|CRC/[A-Za-z0-9_.-]+\.CRC)$#i', $entry)) {
            $zip->close();
            rcvrJsonResponse(400, ['success' => false, 'message' => 'ZIP berisi file tidak sesuai format CRC: ' . $entry]);
        }

        $contents = $zip->getFromIndex($i);
        if ($contents === false || $contents === '') {
            $zip->close();
            rcvrJsonResponse(400, ['success' => false, 'message' => 'File CRC dalam ZIP kosong atau gagal dibaca: ' . $entry]);
        }

        $destination = $targetDir . '/' . $entry;
        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir) && ! @mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
            $zip->close();
            rcvrJsonResponse(500, ['success' => false, 'message' => 'Gagal membuat folder: ' . $destinationDir]);
        }

        if (@file_put_contents($destination, $contents, LOCK_EX) === false) {
            $zip->close();
            rcvrJsonResponse(500, ['success' => false, 'message' => 'Gagal menyimpan file CRC: ' . $entry]);
        }

        $extractedFiles[] = $entry;
    }

    $zip->close();

    if (empty($extractedFiles)) {
        rcvrJsonResponse(400, ['success' => false, 'message' => 'ZIP tidak berisi file CRC.']);
    }

    rcvrJsonResponse(200, [
        'success' => true,
        'message' => 'CRC berhasil diekstrak ke folder tanggal ujian.',
        'type' => $type,
        'admin_no' => $adminNo,
        'file_name' => $fileName,
        'file_count' => count($extractedFiles),
        'files' => $extractedFiles,
        'target_path' => $targetPath,
    ]);
}

// --- Simpan file Berita Acara ---
if (! @move_uploaded_file($tmpName, $targetPath)) {
    rcvrJsonResponse(500, ['success' => false, 'message' => 'Gagal menyimpan file.']);
}

$savedSize = filesize($targetPath);
$savedRelativePath = $type === 'crc' ? $fileName : $testDate . '/Documents/' . $fileName;

$expectedSize = isset($_POST['file_size']) && is_numeric($_POST['file_size']) ? (int) $_POST['file_size'] : 0;
if ($expectedSize > 0 && $savedSize !== $expectedSize) {
    @unlink($targetPath);
    rcvrJsonResponse(500, ['success' => false, 'message' => 'Ukuran file tidak sesuai setelah simpan.']);
}

rcvrJsonResponse(200, [
    'success'     => true,
    'message'     => $type === 'berita_acara' ? 'Berita Acara PDF berhasil disimpan.' : ($type === 'attendance' ? 'File absensi final berhasil disimpan.' : 'File Filing System berhasil disimpan.'),
    'type'        => $type,
    'admin_no'    => $adminNo,
    'file_name'   => $fileName,
    'file_size'   => $savedSize,
    'target_path' => $targetPath,
    'relative_path' => $savedRelativePath,
    'remote_path' => $adminNo . '/' . $savedRelativePath,
    'download_url' => rcvrPublicUrl($adminNo, $savedRelativePath),
]);
