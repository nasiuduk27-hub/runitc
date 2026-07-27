<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$requiredConfig = __DIR__ . '/../../../config.php';
if (!file_exists($requiredConfig)) {
    die('config.php not found at: ' . $requiredConfig);
}
require_once $requiredConfig;

if (!defined('BASE_PATH')) {
    die('BASE_PATH not defined after loading config.php');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$defaultPhoto = BASE_PATH . '/assets/personal/nopicture.png';

$placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
    . '<rect width="200" height="200" fill="#f3f4f6"/>'
    . '<circle cx="100" cy="80" r="30" fill="#d1d5db"/>'
    . '<path d="M40 180 Q100 130 160 180" fill="#d1d5db"/>'
    . '</svg>';

$sendDefault = static function () use ($defaultPhoto, $placeholderSvg): void {
    if (is_file($defaultPhoto)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        readfile($defaultPhoto);
        exit;
    }

    if (function_exists('imagecreate')) {
        $img = @imagecreate(200, 200);
        if ($img) {
            $bg = @imagecolorallocate($img, 243, 244, 246);
            $fg = @imagecolorallocate($img, 209, 213, 219);
            @imagefilledellipse($img, 100, 80, 60, 60, $fg);
            $points = [40, 180, 100, 130, 160, 180];
            @imagefilledpolygon($img, $points, 3, $fg);
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            imagepng($img);
            imagedestroy($img);
            exit;
        }
    }

    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=3600');
    echo $placeholderSvg;
    exit;
};

if (empty($_SESSION['user_id'])) {
    $sendDefault();
}

$nisn = trim((string) ($_GET['nisn'] ?? ''));
$nisn = preg_replace('/[^A-Za-z0-9_-]/', '', $nisn);

$fallbackIds = [];
if (isset($_GET['id']) && is_array($_GET['id'])) {
    foreach ($_GET['id'] as $rawId) {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $rawId));
        if ($clean !== '' && $clean !== $nisn) {
            $fallbackIds[] = $clean;
        }
    }
}

$allIds = [];
if ($nisn !== '') {
    $allIds[] = $nisn;
}
foreach ($fallbackIds as $fid) {
    $allIds[] = $fid;
}

if (empty($allIds)) {
    $sendDefault();
}

$cacheDir = BASE_PATH . '/storage/uploads/participant_photos';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
];

foreach ($allIds as $candidateId) {
    foreach ($extensions as $ext) {
        $cachedFile = $cacheDir . '/' . $candidateId . '.' . $ext;
        if (is_file($cachedFile) && filesize($cachedFile) > 0) {
            header('Content-Type: ' . $mimeTypes[$ext]);
            header('Cache-Control: public, max-age=86400');
            readfile($cachedFile);
            exit;
        }
    }
}

/*
 * PRIORITAS: coba lewat public HTTP URL dulu (anti ribet FTP).
 */
if (defined('PARTICIPANT_PHOTO_PUBLIC_URL')) {
    $publicBaseUrl = rtrim(PARTICIPANT_PHOTO_PUBLIC_URL, '/') . '/nisn/';

    foreach ($allIds as $candidateId) {
        foreach ($extensions as $ext) {
            $publicUrl = $publicBaseUrl . rawurlencode($candidateId) . '.' . $ext;
            $httpHeaders = @get_headers($publicUrl, true);

            if ($httpHeaders && isset($httpHeaders[0]) && strpos($httpHeaders[0], '200') !== false) {
                $cachedFile = $cacheDir . '/' . $candidateId . '.' . $ext;

                $ch = curl_init($publicUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $imageData = curl_exec($ch);
                $curlHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curlHttpCode === 200 && $imageData !== false && strlen($imageData) > 0) {
                    @file_put_contents($cachedFile, $imageData);
                    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'image/jpeg'));
                    header('Cache-Control: public, max-age=86400');
                    echo $imageData;
                    exit;
                }
            }
        }
    }
}

$sendDefault();
