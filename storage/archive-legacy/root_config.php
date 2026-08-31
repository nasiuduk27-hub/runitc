<?php

// File: config.php
declare(strict_types=1);

if (! defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// ==========================================
// LOAD .ENV FILE
// ==========================================
function loadEnv($path)
{
    if (! file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        // Some shared hosting panels disable putenv(); keep .env usable via $_ENV/$_SERVER.
        if (function_exists('putenv')) {
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}

if (! function_exists('env')) {
    function env($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return $value;
    }
}

loadEnv(BASE_PATH.'/.env');
// ==========================================

// ==========================================
// PENGATURAN BASE_URL & MULTI-DOMAIN AMAN
// ==========================================
// ==========================================
// PENGATURAN BASE_URL & MULTI-DOMAIN AMAN
// ==========================================

$session_domain = 'runitc.test'; // Default fallback

if (! defined('BASE_URL')) {

    $allowed_hosts = [
        'runitc.co.test',
        'runitc.toeic.or.id',
        'itc-indonesia.com',
        'toeic.co.id',
        'toeic.or.id',
        'localhost',

        // Synology local / remote
        '192.168.10.190',
        'runitc.local',
        'm2-square.synology.me',
    ];

    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    $current_host_without_port = explode(':', $current_host)[0];

    $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://';

    if (in_array($current_host_without_port, $allowed_hosts)) {

        // Local Herd / localhost / Synology port-based access
        if (
            $current_host_without_port === 'runitc.co.test' ||
            $current_host_without_port === 'runitc.toeic.or.id' ||
            $current_host_without_port === 'localhost' ||
            $current_host_without_port === '192.168.10.190' ||
            $current_host_without_port === 'runitc.local' ||
            $current_host_without_port === 'm2-square.synology.me'
        ) {
            define('BASE_URL', $protocol.$current_host.'/');
        }

        // Production domain yang project-nya berada di folder /runitc/
        else {
            define('BASE_URL', $protocol.$current_host.'/runitc/');
        }

        $session_domain = $current_host_without_port;

    } else {
        define('BASE_URL', 'https://itc-indonesia.com/runitc/');
        $session_domain = 'itc-indonesia.com';
    }
}
// ==========================================

// 1. Error Reporting
$displayErrors = env('DISPLAY_ERRORS', '0');
error_reporting(E_ALL);
ini_set('display_errors', $displayErrors);
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH.'/storage/php-error.log');

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

// 2. Session Setup
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (! filter_var($session_domain, FILTER_VALIDATE_IP)) {
        $cookieParams['domain'] = $session_domain;
    }

    session_set_cookie_params($cookieParams);
    session_start();
}

// 3. Autoload Composer (PHPMailer, dsb)
$autoloadPath = BASE_PATH.'/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// 4. Autoload Custom Classes & Models
spl_autoload_register(function ($className) {
    $directories = [
        BASE_PATH.'/app/Legacy/Classes/',
        BASE_PATH.'/app/Legacy/Models/',
        BASE_PATH.'/app/Legacy/Controllers/',
    ];

    foreach ($directories as $directory) {
        $file = $directory.$className.'.php';
        if (file_exists($file)) {
            require_once $file;

            return;
        }
    }
});

// 5. Inisialisasi Koneksi (Panggil Class Database)
$pdo = Database::getConnection(
    env('DB_HOST', '127.0.0.1'),
    env('DB_NAME', ''),
    env('DB_USER', 'root'),
    env('DB_PASS', ''),
    true
);

$pdo_bot = Database::getConnection(
    env('DB_BOT_HOST', '127.0.0.1'),
    env('DB_BOT_NAME', ''),
    env('DB_BOT_USER', 'root'),
    env('DB_BOT_PASS', '')
);

$pdo_run = Database::getConnection(
    env('DB_RUN_HOST', '127.0.0.1'),
    env('DB_RUN_NAME', ''),
    env('DB_RUN_USER', 'root'),
    env('DB_RUN_PASS', '')
);

$pdo_war = Database::getConnection(
    env('DB_WAR_HOST', '127.0.0.1'),
    env('DB_WAR_NAME', ''),
    env('DB_WAR_USER', 'root'),
    env('DB_WAR_PASS', '')
);

$pdo_collector = Database::getConnection(
    env('DB_COLLECTOR_HOST', '127.0.0.1'),
    env('DB_COLLECTOR_NAME', 'cbt_collector'),
    env('DB_COLLECTOR_USER', 'root'),
    env('DB_COLLECTOR_PASS', '')
);

// ==========================================
// FTP CONFIG UNTUK FILING SYSTEM
// ==========================================
$ftp_config = [
    'host' => env('FTP_HOST', ''),
    'user' => env('FTP_USER', ''),
    'pass' => env('FTP_PASS', ''),
    'port' => (int) env('FTP_PORT', 21),
    'path' => env('FTP_PATH', ''),
    'root_path' => env('FTP_ROOT_PATH', ''),
    'ssl' => filter_var(env('FTP_SSL', false), FILTER_VALIDATE_BOOLEAN),
    'timeout' => (int) env('FTP_TIMEOUT', 60),
    'upload_timeout' => (int) env('FTP_UPLOAD_TIMEOUT', min(55, (int) env('FTP_TIMEOUT', 60))),
    'upload_direct_curl' => filter_var(env('FTP_UPLOAD_DIRECT_CURL', true), FILTER_VALIDATE_BOOLEAN),
    'upload_direct_curl_min_bytes' => (int) env('FTP_UPLOAD_DIRECT_CURL_MIN_BYTES', 0),
    'passive_mode' => filter_var(env('FTP_PASSIVE_MODE', true), FILTER_VALIDATE_BOOLEAN),
    'auto_detect_mode' => filter_var(env('FTP_AUTO_DETECT_MODE', false), FILTER_VALIDATE_BOOLEAN),
];

// ==========================================
// FTP FOTO PESERTA CBT
// ==========================================
$participant_photo_ftp_config = [
    'host' => env('PARTICIPANT_PHOTO_FTP_HOST', env('FTP_HOST', '')),
    'user' => env('PARTICIPANT_PHOTO_FTP_USER', ''),
    'pass' => env('PARTICIPANT_PHOTO_FTP_PASS', ''),
    'port' => (int) env('PARTICIPANT_PHOTO_FTP_PORT', env('FTP_PORT', 21)),
    'path' => env('PARTICIPANT_PHOTO_FTP_PATH', '/www/wwwroot/cbt.toeic.or.id/docs/general/cbt_photos'),
    'root_path' => '',
    'ssl' => filter_var(env('PARTICIPANT_PHOTO_FTP_SSL', env('FTP_SSL', false)), FILTER_VALIDATE_BOOLEAN),
    'timeout' => (int) env('PARTICIPANT_PHOTO_FTP_TIMEOUT', 30),
    'upload_timeout' => (int) env('PARTICIPANT_PHOTO_FTP_TIMEOUT', 30),
    'passive_mode' => filter_var(env('PARTICIPANT_PHOTO_FTP_PASSIVE_MODE', env('FTP_PASSIVE_MODE', true)), FILTER_VALIDATE_BOOLEAN),
    'auto_detect_mode' => filter_var(env('PARTICIPANT_PHOTO_FTP_AUTO_DETECT_MODE', false), FILTER_VALIDATE_BOOLEAN),
];

// ==========================================
// PUBLIC URL FOTO PESERTA (biypas FTP)
// ==========================================
$participant_photo_public_url = rtrim((string) env('PARTICIPANT_PHOTO_PUBLIC_URL', ''), '/');
if ($participant_photo_public_url === '') {
    $ftpPhotoPath = $participant_photo_ftp_config['path'] ?? '';
    // FTP_PATH = /www/wwwroot/domain.com/... → https://domain.com/...
    $participant_photo_public_url = preg_replace('#^/www/wwwroot/#i', 'https://', $ftpPhotoPath);
    $participant_photo_public_url = rtrim($participant_photo_public_url, '/');
}

if (! defined('PARTICIPANT_PHOTO_PUBLIC_URL') && $participant_photo_public_url !== '') {
    define('PARTICIPANT_PHOTO_PUBLIC_URL', $participant_photo_public_url);
}

// 6. Global CSRF Protection untuk semua form POST
// Ketika CSRF_ENABLED = true, semua POST request akan divalidasi.
Csrf::verify();
