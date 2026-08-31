<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$basePath = __DIR__;
$envPath = $basePath.'/.env';

echo "RUN-ITC Deploy Check\n";
echo "====================\n";
echo 'PHP_VERSION='.PHP_VERSION."\n";
echo "BASE_PATH={$basePath}\n";
echo 'ENV_EXISTS='.(file_exists($envPath) ? 'yes' : 'no')."\n";
echo 'STORAGE_EXISTS='.(is_dir($basePath.'/storage') ? 'yes' : 'no')."\n";
echo 'STORAGE_WRITABLE='.(is_writable($basePath.'/storage') ? 'yes' : 'no')."\n";
echo 'PDO_MYSQL='.(extension_loaded('pdo_mysql') ? 'yes' : 'no')."\n";
echo 'CURL='.(extension_loaded('curl') ? 'yes' : 'no')."\n";
echo 'FTP='.(extension_loaded('ftp') ? 'yes' : 'no')."\n";
echo 'FILEINFO='.(extension_loaded('fileinfo') ? 'yes' : 'no')."\n";
echo "\n";

if (! file_exists($envPath)) {
    echo "ERROR: .env file not found.\n";
    exit;
}

$env = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim($value);
}

$checks = [
    'DB' => ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'],
    'DB_BOT' => ['DB_BOT_HOST', 'DB_BOT_NAME', 'DB_BOT_USER', 'DB_BOT_PASS'],
    'DB_RUN' => ['DB_RUN_HOST', 'DB_RUN_NAME', 'DB_RUN_USER', 'DB_RUN_PASS'],
    'DB_WAR' => ['DB_WAR_HOST', 'DB_WAR_NAME', 'DB_WAR_USER', 'DB_WAR_PASS'],
];

foreach ($checks as $label => $keys) {
    [$hostKey, $nameKey, $userKey, $passKey] = $keys;
    $host = $env[$hostKey] ?? '';
    $name = $env[$nameKey] ?? '';
    $user = $env[$userKey] ?? '';
    $pass = $env[$passKey] ?? '';

    echo "{$label}: ";

    if ($host === '' || $name === '' || $user === '') {
        echo "skip, incomplete env ({$hostKey}/{$nameKey}/{$userKey})\n";

        continue;
    }

    try {
        new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "ok host={$host} db={$name} user={$user}\n";
    } catch (Throwable $e) {
        echo "failed host={$host} db={$name} user={$user} error=".$e->getMessage()."\n";
    }
}
