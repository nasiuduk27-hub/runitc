<?php
// File: modules/cbt_ops/filing_system/cron/process_expired_files.php

// Pastikan hanya bisa dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: Script ini hanya dapat dijalankan melalui CLI/Terminal.\n");
}

// Naik 4 level folder untuk mencapai root project
$configPath = dirname(__DIR__, 4) . '/config.php';
if (!file_exists($configPath)) {
    die("Error: config.php tidak ditemukan di $configPath\n");
}

require_once $configPath;
require_once dirname(__DIR__) . '/services/FilingExpiryService.php';

if (!isset($pdo_run)) {
    die("Error: Koneksi PDO_RUN tidak tersedia.\n");
}

echo "=============================================\n";
echo "  RUNITC FILING SYSTEM - EXPIRY CRON JOB\n";
echo "  Waktu Mulai : " . date('Y-m-d H:i:s') . "\n";
echo "=============================================\n\n";

try {
    $storageService = new FilingStorageService($ftp_config);
    $expiryService = new FilingExpiryService($pdo_run, $storageService);
    
    // Batch size 100 untuk menghindari timeout/memory leak
    $limit = 100;
    $stats = $expiryService->processExpiredFiles($limit);
    $trashRetentionDays = (int) env('FILING_TRASH_RETENTION_DAYS', 30);
    $purgeStats = $expiryService->purgeOldTrash($trashRetentionDays, $limit);
    
    echo "Laporan Eksekusi:\n";
    echo "- Total Scanned   : " . $stats['total_scanned'] . "\n";
    echo "- Total Processed : " . $stats['total_processed'] . "\n";
    echo "- Total Archived  : " . $stats['total_archived'] . "\n";
    echo "- Total Trashed   : " . $stats['total_trashed'] . "\n";
    echo "- Total Expired   : " . $stats['total_expired'] . "\n";
    echo "- Total Skipped   : " . $stats['total_skipped'] . "\n";
    echo "- Total Error     : " . $stats['total_error'] . "\n";
    echo "\nAuto Purge Sampah:\n";
    echo "- Retensi Hari    : " . $purgeStats['retention_days'] . "\n";
    echo "- Total Scanned   : " . $purgeStats['total_scanned'] . "\n";
    echo "- Total Purged    : " . $purgeStats['total_purged'] . "\n";
    echo "- Total Failed    : " . $purgeStats['total_failed'] . "\n";
    
} catch (Throwable $e) {
    echo "\nFATAL ERROR:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=============================================\n";
echo "  Waktu Selesai : " . date('Y-m-d H:i:s') . "\n";
echo "=============================================\n";
