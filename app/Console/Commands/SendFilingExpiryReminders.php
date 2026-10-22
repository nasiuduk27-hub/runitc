<?php

namespace App\Console\Commands;

use App\Services\FilingSystem\FilingExpiryReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendFilingExpiryReminders extends Command
{
    protected $signature = 'filing:send-expiry-reminders {--limit=200 : Maximum reminder candidates to process}';

    protected $description = 'Send expiry reminders for filing system files before expiration';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->line('=============================================');
        $this->line('  RUNITC FILING SYSTEM - EXPIRY REMINDER');
        $this->line('  Waktu Mulai : '.date('Y-m-d H:i:s'));
        $this->line('=============================================');
        $this->newLine();

        try {
            $pdo = DB::connection('run')->getPdo();
            $service = new FilingExpiryReminderService($pdo);

            $stats = $service->sendReminders($limit);

            $this->line('Laporan Eksekusi:');
            $this->line('- Total Kandidat : '.$stats['total_candidates']);
            $this->line('- Total Pemilik  : '.$stats['total_owners']);
            $this->line('- Lonceng Terbuat: '.$stats['bells_created']);
            $this->line('- Email Terkirim : '.$stats['emails_sent']);
            $this->line('- Email Dilewati : '.$stats['emails_skipped']);
            $this->line('- Gagal          : '.$stats['failed']);
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
}
