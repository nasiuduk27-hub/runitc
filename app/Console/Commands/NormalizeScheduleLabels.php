<?php

namespace App\Console\Commands;

use App\Services\CreditUnion\LoanSkipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeScheduleLabels extends Command
{
    protected $signature = 'cu:normalize-schedule-labels
        {--apply : Tulis perubahan ke database (default: dry-run, hanya menampilkan)}';

    protected $description = 'Samakan totseqno/dseqno baris jadwal pinjaman yang tidak konsisten antar baris';

    public function handle(LoanSkipService $skips): int
    {
        $apply = (bool) $this->option('apply');

        $loanIds = DB::connection('mysql')->table('icu_dloan')
            ->select('mst_rec_id')
            ->groupBy('mst_rec_id')
            ->havingRaw('COUNT(DISTINCT totseqno) > 1')
            ->pluck('mst_rec_id');

        if ($loanIds->isEmpty()) {
            $this->info('Semua jadwal pinjaman sudah konsisten. Tidak ada yang perlu dinormalisasi.');

            return self::SUCCESS;
        }

        $this->info($loanIds->count().' pinjaman punya totseqno tidak konsisten antar baris:');
        foreach ($loanIds as $loanId) {
            $trnno = DB::connection('mysql')->table('icu_mloan')->where('rec_id', $loanId)->value('trnno');
            $this->line(' - #'.$loanId.' ('.($trnno ?: '-').')');
        }

        if (! $apply) {
            $this->warn('Dry-run: tidak ada data yang diubah. Jalankan dengan --apply untuk menormalisasi.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Normalisasi '.$loanIds->count().' pinjaman di atas?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($loanIds as $loanId) {
            $total += $skips->normalizeScheduleTotals((int) $loanId);
        }

        $this->info('Selesai: '.$total.' baris dinormalisasi pada '.$loanIds->count().' pinjaman.');

        return self::SUCCESS;
    }
}
