<?php

use Illuminate\Contracts\Console\Kernel;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Error: Script ini hanya dapat dijalankan melalui CLI/Terminal.\n");
}

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$status = $kernel->call('filing:process-expired', [
    '--limit' => 100,
]);

echo $kernel->output();
exit($status);
