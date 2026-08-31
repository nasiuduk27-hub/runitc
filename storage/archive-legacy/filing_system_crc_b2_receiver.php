<?php

date_default_timezone_set('Asia/Jakarta');

function crcB2Send(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function crcB2Root(): string
{
    $envRoot = getenv('CRC_B2_STORAGE_ROOT') ?: '';
    $collectorFinal = realpath(__DIR__.'/../../collector/final');
    $defaultRoot = $collectorFinal && is_dir($collectorFinal)
        ? $collectorFinal
        : (is_dir(__DIR__.'/final') ? __DIR__.'/final' : __DIR__);

    return rtrim(str_replace('\\', '/', (string) ($envRoot !== '' ? $envRoot : $defaultRoot)), '/');
}

function crcB2SafeAdmin(string $adminNo): string
{
    $adminNo = basename($adminNo);
    $adminNo = preg_replace('/\s+/', '_', $adminNo);
    $adminNo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $adminNo);

    return trim((string) $adminNo, '._-');
}

function crcB2AdminDir(string $adminNo): ?string
{
    $root = crcB2Root();
    $safeAdmin = crcB2SafeAdmin($adminNo);
    if ($safeAdmin === '') {
        return null;
    }

    $realRoot = realpath($root);
    $realDir = realpath($root.'/'.$safeAdmin);
    if (! $realRoot || ! $realDir) {
        return null;
    }

    $realRoot = str_replace('\\', '/', $realRoot);
    $realDir = str_replace('\\', '/', $realDir);
    if (strpos($realDir, $realRoot.'/') !== 0 || ! is_dir($realDir)) {
        return null;
    }

    return $realDir;
}

function crcB2Files(string $adminNo): array
{
    $dir = crcB2AdminDir($adminNo);
    if ($dir === null) {
        return [];
    }

    $safeAdmin = crcB2SafeAdmin($adminNo);
    $files = [];
    foreach (scandir($dir) ?: [] as $fileName) {
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'crc') {
            continue;
        }
        $path = $dir.'/'.$fileName;
        if (! is_file($path)) {
            continue;
        }
        $modifiedAt = filemtime($path) ?: null;
        $files[] = [
            'file_name' => $fileName,
            'file_type' => 'crc',
            'relative_path' => 'CRC_B2/'.$safeAdmin.'/'.$fileName,
            'modified_at_ts' => $modifiedAt,
            'uploaded_at' => $modifiedAt ? date('d M Y H:i', $modifiedAt) : '',
        ];
    }

    usort($files, static fn (array $a, array $b): int => strcasecmp($a['file_name'], $b['file_name']));

    return $files;
}

$token = (string) (getenv('CRC_B2_RECEIVER_TOKEN') ?: '');
if ($token !== '') {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || ! hash_equals($token, trim($matches[1]))) {
        crcB2Send(401, ['success' => false, 'message' => 'Token tidak valid.']);
    }
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'list_folders') {
    $root = crcB2Root();
    if (! is_dir($root) || ! is_readable($root)) {
        crcB2Send(200, ['success' => true, 'folders' => []]);
    }

    $folders = [];
    foreach (scandir($root) ?: [] as $folderName) {
        if ($folderName === '.' || $folderName === '..' || ! is_dir($root.'/'.$folderName)) {
            continue;
        }
        $files = crcB2Files($folderName);
        if (empty($files)) {
            continue;
        }
        $latest = null;
        foreach ($files as $file) {
            $ts = $file['modified_at_ts'] ?? null;
            if (is_int($ts) && ($latest === null || $ts > $latest)) {
                $latest = $ts;
            }
        }
        $folders[] = [
            'admin_no' => $folderName,
            'count' => count($files),
            'processed_at' => $latest ? date('d M Y H:i', $latest) : '',
        ];
    }

    usort($folders, static fn (array $a, array $b): int => strcasecmp($a['admin_no'], $b['admin_no']));
    crcB2Send(200, ['success' => true, 'folders' => $folders]);
}

if ($action === 'list_files') {
    crcB2Send(200, ['success' => true, 'files' => crcB2Files((string) ($_GET['admin_no'] ?? $_POST['admin_no'] ?? ''))]);
}

if ($action === 'download_zip') {
    $adminNo = crcB2SafeAdmin((string) ($_GET['admin_no'] ?? $_POST['admin_no'] ?? ''));
    $dir = crcB2AdminDir($adminNo);
    if ($dir === null) {
        crcB2Send(404, ['success' => false, 'message' => 'Folder nomor admin tidak ditemukan.']);
    }

    if (! class_exists('ZipArchive')) {
        crcB2Send(500, ['success' => false, 'message' => 'ZipArchive belum aktif.']);
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'crc_b2_');
    if ($zipPath === false) {
        crcB2Send(500, ['success' => false, 'message' => 'Gagal membuat temporary ZIP.']);
    }

    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        crcB2Send(500, ['success' => false, 'message' => 'Gagal membuat ZIP.']);
    }

    foreach (scandir($dir) ?: [] as $fileName) {
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'crc') {
            continue;
        }
        $path = $dir.'/'.$fileName;
        if (is_file($path) && filesize($path) > 0) {
            $zip->addFile($path, $fileName);
        }
    }

    $zip->close();
    $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
    if ($zipSize === false || $zipSize <= 0) {
        @unlink($zipPath);
        crcB2Send(404, ['success' => false, 'message' => 'File CRC tidak ditemukan.']);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="CRC_B2_'.$adminNo.'.zip"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: '.$zipSize);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

crcB2Send(400, ['success' => false, 'message' => 'Action tidak valid.']);
