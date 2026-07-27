<?php

$envPath = dirname(__DIR__) . '/.env';
if (!is_file($envPath)) {
    fwrite(STDERR, "Missing .env\n");
    exit(1);
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }

    $pos = strpos($line, '=');
    if ($pos === false) {
        continue;
    }

    $env[substr($line, 0, $pos)] = substr($line, $pos + 1);
}

$token = $env['FILING_HTTP_UPLOAD_TOKEN'] ?? '';
$host = $env['FTP_HOST'] ?? '';
$user = $env['FTP_USER'] ?? '';
$pass = $env['FTP_PASS'] ?? '';
$port = (int) ($env['FTP_PORT'] ?? 21);

if ($token === '' || $host === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "Missing FTP or HTTP upload token config\n");
    exit(1);
}

$receiver = <<<'PHP'
<?php

header('Content-Type: application/json');

const FILING_HTTP_UPLOAD_TOKEN = '__TOKEN__';

function respond_upload(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function clean_upload_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim((string) $path, '/');

    $parts = [];
    foreach (explode('/', $path) as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            respond_upload(400, ['success' => false, 'message' => 'Path tidak valid.']);
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond_upload(405, ['success' => false, 'message' => 'Method tidak diizinkan.']);
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches) || !hash_equals(FILING_HTTP_UPLOAD_TOKEN, trim($matches[1]))) {
    respond_upload(401, ['success' => false, 'message' => 'Token tidak valid.']);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    respond_upload(400, ['success' => false, 'message' => 'File belum dikirim.']);
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond_upload(400, ['success' => false, 'message' => 'Upload file gagal. Error: ' . ($file['error'] ?? 'unknown')]);
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    respond_upload(400, ['success' => false, 'message' => 'File upload tidak valid.']);
}

$storagePath = clean_upload_path((string) ($_POST['storage_path'] ?? ''));
if ($storagePath === '' || strtolower(pathinfo($storagePath, PATHINFO_EXTENSION)) !== 'zip') {
    respond_upload(400, ['success' => false, 'message' => 'Storage path harus file ZIP.']);
}

$target = __DIR__ . '/' . $storagePath;
$dir = dirname($target);
if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    respond_upload(500, ['success' => false, 'message' => 'Gagal membuat folder tujuan.']);
}

if (!is_writable($dir)) {
    respond_upload(500, ['success' => false, 'message' => 'Folder tujuan tidak writable.']);
}

if (!@move_uploaded_file($tmp, $target)) {
    respond_upload(500, ['success' => false, 'message' => 'Gagal menyimpan file.']);
}

$size = filesize($target);
$expected = isset($_POST['file_size']) && is_numeric($_POST['file_size']) ? (int) $_POST['file_size'] : 0;
if ($expected > 0 && $size !== $expected) {
    @unlink($target);
    respond_upload(500, ['success' => false, 'message' => 'Ukuran file tidak sesuai.']);
}

respond_upload(200, [
    'success' => true,
    'message' => 'File berhasil diterima.',
    'storage_path' => $storagePath,
    'size' => $size,
]);
PHP;

$receiver = str_replace('__TOKEN__', addslashes($token), $receiver);

$conn = @ftp_connect($host, $port, 30);
if (!$conn) {
    fwrite(STDERR, "FTP connect failed\n");
    exit(1);
}

if (!@ftp_login($conn, $user, $pass)) {
    @ftp_close($conn);
    fwrite(STDERR, "FTP login failed\n");
    exit(1);
}

$passiveMode = filter_var($env['FTP_PASSIVE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);
@ftp_pasv($conn, $passiveMode);

$stream = fopen('php://temp', 'r+');
fwrite($stream, $receiver);
rewind($stream);

if (!@ftp_fput($conn, 'http_upload_receiver.php', $stream, FTP_ASCII)) {
    fclose($stream);
    @ftp_close($conn);
    fwrite(STDERR, "FTP upload receiver failed\n");
    exit(1);
}

fclose($stream);
@ftp_close($conn);

echo "uploaded http_upload_receiver.php\n";
