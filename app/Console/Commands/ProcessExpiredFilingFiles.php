<?php

namespace App\Console\Commands;

use App\Services\FilingSystem\FilingExpiryService;
use App\Services\FilingSystem\FilingStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessExpiredFilingFiles extends Command
{
    protected $signature = 'filing:process-expired {--limit=100 : Maximum expired files to process per batch}';

    protected $description = 'Process expired filing system files and purge old trash';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->line('=============================================');
        $this->line('  RUNITC FILING SYSTEM - EXPIRY CRON JOB');
        $this->line('  Waktu Mulai : '.date('Y-m-d H:i:s'));
        $this->line('=============================================');
        $this->newLine();

        try {
            $storageService = new FilingStorageService($this->ftpConfig());
            $expiryService = new FilingExpiryService(DB::connection('run')->getPdo(), $storageService);

            $stats = $expiryService->processExpiredFiles($limit);
            $trashRetentionDays = (int) env('FILING_TRASH_RETENTION_DAYS', 30);
            $purgeStats = $expiryService->purgeOldTrash($trashRetentionDays, $limit);

            $this->line('Laporan Eksekusi:');
            $this->line('- Total Scanned   : '.$stats['total_scanned']);
            $this->line('- Total Processed : '.$stats['total_processed']);
            $this->line('- Total Archived  : '.$stats['total_archived']);
            $this->line('- Total Trashed   : '.$stats['total_trashed']);
            $this->line('- Total Expired   : '.$stats['total_expired']);
            $this->line('- Total Skipped   : '.$stats['total_skipped']);
            $this->line('- Total Error     : '.$stats['total_error']);
            $this->newLine();
            $this->line('Auto Purge Sampah:');
            $this->line('- Retensi Hari    : '.$purgeStats['retention_days']);
            $this->line('- Total Scanned   : '.$purgeStats['total_scanned']);
            $this->line('- Total Purged    : '.$purgeStats['total_purged']);
            $this->line('- Total Failed    : '.$purgeStats['total_failed']);
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('FATAL ERROR:');
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->newLine();
            $this->line('=============================================');
            $this->line('  Waktu Selesai : '.date('Y-m-d H:i:s'));
            $this->line('=============================================');
        }

        return self::SUCCESS;
    }

    private function ftpConfig(): array
    {
        return [
            'host' => env('FTP_HOST', ''),
            'user' => env('FTP_USER', ''),
            'pass' => env('FTP_PASS', ''),
            'port' => (int) env('FTP_PORT', 21),
            'path' => env('FTP_PATH', ''),
            'root_path' => env('FTP_ROOT_PATH', ''),
            'ssl' => filter_var(env('FTP_SSL', false), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) env('FTP_TIMEOUT', 60),
        ];
    }
}
