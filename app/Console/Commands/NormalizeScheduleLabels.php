<?php

namespace App\Console\Commands;

use App\Services\CreditUnion\LoanSkipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeScheduleLabels extends Command
{
    protected $signature = 'cu:normalize-schedule-labels
        {--apply : Tulis perubahan ke database (default: dry-run, hanya menampilkan)}';

    protected $description = 'Samakan totseqno/dseqno dan label (xx/yy) di teks descr baris jadwal yang tidak konsisten';

    public function handle(LoanSkipService $skips): int
    {
        $apply = (bool) $this->option('apply');

        $loanIds = DB::connection('mysql')->table('icu_dloan')
            ->select('mst_rec_id')
            ->groupBy('mst_rec_id')
            ->havingRaw('COUNT(DISTINCT totseqno) > 1')
            ->pluck('mst_rec_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Deteksi juga label basi di teks descr: penyebut "(xx/yy)" != totseqno kolom.
        $descrRows = DB::connection('mysql')->table('icu_dloan')
            ->select('mst_rec_id', 'totseqno', 'descr')
            ->whereRaw("descr REGEXP '\\\\([0-9]+/[0-9]+\\\\)$'")
            ->get();

        $staleDescrIds = [];
        foreach ($descrRows as $row) {
            if (preg_match('/\((\d+)\/(\d+)\)$/', (string) $row->descr, $m) === 1
                && (int) $m[2] !== (int) $row->totseqno) {
                $staleDescrIds[] = (int) $row->mst_rec_id;
            }
        }

        $loanIds = collect($loanIds)->merge($staleDescrIds)->unique()->values();

        if ($loanIds->isEmpty()) {
            $this->info('Semua jadwal pinjaman sudah konsisten (totseqno dan label descr). Tidak ada yang perlu dinormalisasi.');

            return self::SUCCESS;
        }

        $this->info($loanIds->count().' pinjaman punya label jadwal tidak konsisten (totseqno / teks descr):');
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
