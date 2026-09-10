<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeMember;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ManualLoanService
{
    public function createFromMaster(array $data, int $userId): array
    {
        $simulation = $this->simulateMaster($data);
        $start = $simulation['startper'];
        $remainingPrincipal = (int) ($data['remaining_principal'] ?? (($data['payment_status'] ?? 'running') === 'paid' ? 0 : $data['principal']));
        if ($remainingPrincipal < 0 || $remainingPrincipal > (int) $data['principal']) {
            throw new InvalidArgumentException('Sisa pokok harus antara Rp 0 dan pokok pinjaman.');
        }
        $paid = ($data['payment_status'] ?? 'running') === 'paid';
        if ($paid) {
            $remainingPrincipal = 0;
        }
        $schedule = [];
        $principalPaid = max(0, (int) $data['principal'] - $remainingPrincipal);
        $paidPrincipal = 0;
        foreach ($simulation['schedule'] as $index => $row) {
            $isPaid = $paid || ($principalPaid > 0 && $paidPrincipal + (int) $row['amount'] <= $principalPaid);
            $paidPrincipal += $isPaid ? (int) $row['amount'] : 0;
            $schedule[] = ['periode' => (string) $row['periode'], 'amount' => (int) $row['amount'], 'int_amt' => (int) $row['int_amt'], 'others' => 0, 'outstand' => (int) $row['outstand'], 'paidst' => $isPaid ? 1 : 0, 'payno' => $isPaid ? 'HIST-MANUAL' : '', 'remarks' => ''];
        }

        return $this->import([['source_key' => 'MANUAL-'.date('YmdHis'), 'member_rec_id' => (int) ($data['member_rec_id'] ?? 0), 'member_name' => trim((string) $data['member_name']), 'member_status' => (string) ($data['member_status'] ?? 'inactive'), 'trndt' => $data['trndt'], 'principal' => (int) $data['principal'], 'annual_rate' => (float) $data['annual_rate'], 'remaining_principal' => $remainingPrincipal, 'paid_total' => $paid ? (int) $simulation['summary']['total_payment'] : array_sum(array_map(fn (array $row): int => $row['paidst'] ? $row['amount'] + $row['int_amt'] + $row['others'] : 0, $schedule)), 'schedule' => $schedule]], $userId, 'Input Manual');
    }

    public function simulateMaster(array $data): array
    {
        $simulation = (new LoanSimulationService)->simulate((int) $data['principal'], (int) $data['term'], (float) $data['annual_rate'], (string) $data['calculation_method']);
        $date = \Carbon\CarbonImmutable::parse((string) $data['trndt']);
        $startDate = $date->day <= 20 ? $date->startOfMonth() : $date->startOfMonth()->addMonthNoOverflow();
        $schedule = array_map(fn (array $row): array => [...$row, 'periode' => $startDate->addMonthsNoOverflow(((int) $row['seqno']) - 1)->format('Ym')], $simulation['schedule']);

        return ['summary' => $simulation['summary'], 'schedule' => $schedule, 'startper' => $startDate->format('Ym')];
    }

    public function import(array $loans, int $userId, string $filename): array
    {
        if ($loans === []) {
            throw new InvalidArgumentException('File Excel tidak berisi data pinjaman.');
        }

        return DB::connection('mysql')->transaction(function () use ($loans, $userId, $filename): array {
            $importId = (int) DB::connection('run')->table('coop_manual_loan_imports')->insertGetId([
                'filename' => $filename,
                'row_count' => count($loans),
                'imported_count' => 0,
                'failed_count' => 0,
                'actor_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count = 0;
            foreach ($loans as $loan) {
                $rows = $loan['schedule'];
                if ($rows === []) {
                    throw new InvalidArgumentException('Jadwal pinjaman kosong.');
                }
                $memberId = (int) ($loan['member_rec_id'] ?? 0);
                if ($memberId > 0) {
                    $member = DB::connection('mysql')->table('icu_member')->where('rec_id', $memberId)->first(['rec_id', 'icuno', 'icunm']);
                    if ($member === null) {
                        throw new InvalidArgumentException('Anggota dengan rec_id '.$memberId.' tidak ditemukan.');
                    }
                    $name = trim((string) $member->icunm);
                } else {
                    $name = trim((string) ($loan['member_name'] ?? ''));
                    if ($name === '') {
                        throw new InvalidArgumentException('Nama anggota wajib diisi.');
                    }
                    $memberId = $this->createHistoricalMember($name, (string) $rows[0]['periode'], (string) ($loan['member_status'] ?? 'inactive'));
                }

                $trnno = $this->nextTrnno((string) ($loan['trndt'] ?? now()->toDateString()));
                $principal = (int) ($loan['principal'] ?? array_sum(array_column($rows, 'amount')));
                $interest = (int) array_sum(array_column($rows, 'int_amt'));
                $paid = (int) ($loan['paid_total'] ?? array_sum(array_map(fn (array $row): int => $row['paidst'] ? $row['amount'] + $row['int_amt'] + $row['others'] : 0, $rows)));
                $allPaid = collect($rows)->every(fn (array $row): bool => (int) $row['paidst'] === 1);
                $outstanding = $principal;

                $mloanId = (int) DB::connection('mysql')->table('icu_mloan')->insertGetId([
                    'pprd' => (string) ($loan['process_period'] ?? substr((string) $rows[0]['periode'], 0, 6)),
                    'trncd' => '21',
                    'trnno' => $trnno,
                    'trndt' => (string) ($loan['trndt'] ?? now()->toDateString()),
                    'icu_rec_id' => $memberId,
                    'descr' => mb_substr('PINJAMAN '.($name ?: 'HISTORIS'), 0, 50),
                    'principle' => $principal,
                    'interamt' => $interest,
                    'interest' => (float) ($loan['annual_rate'] ?? 0),
                    'int_overdue' => 0,
                    'bnk_charge' => 0,
                    'bnktrx_no' => '',
                    'totalloan' => $principal + $interest + (int) array_sum(array_column($rows, 'others')),
                    'paid' => $paid,
                    'avgmon' => (int) ($rows[0]['amount'] ?? 0),
                    'avgint' => (int) ($rows[0]['int_amt'] ?? 0),
                    'monthly' => (int) (($rows[0]['amount'] ?? 0) + ($rows[0]['int_amt'] ?? 0) + ($rows[0]['others'] ?? 0)),
                    'term' => count($rows),
                    'startper' => (string) $rows[0]['periode'],
                    'endper' => (string) end($rows)['periode'],
                    'remarks' => 'Input Manual Historis',
                    'statrec' => $allPaid ? 5 : 4,
                    'entdt' => now(),
                    'lupd' => now(),
                    'entusr' => 'RUN',
                ], 'rec_id');

                foreach ($rows as $index => $row) {
                    $seqno = $index + 1;
                    $outstanding -= (int) $row['amount'];
                    DB::connection('mysql')->table('icu_dloan')->insert([
                        'mst_rec_id' => $mloanId,
                        'periode' => $row['periode'],
                        'seqno' => $seqno,
                        'totseqno' => count($rows),
                        'descr' => mb_substr('PINJAMAN '.$name, 0, 30).' ('.str_pad((string) $seqno, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) count($rows), 2, '0', STR_PAD_LEFT).')',
                        'amount' => (int) $row['amount'],
                        'rnd_amt' => 0,
                        'int_amt' => (int) $row['int_amt'],
                        'rnd_int' => 0,
                        'others' => (int) $row['others'],
                        'outstand' => max(0, $outstanding),
                        'remarks' => (string) ($row['remarks'] ?? ''),
                        'dseqno' => count($rows) - $seqno + 1,
                        'paidst' => (int) $row['paidst'],
                        'payno' => (string) ($row['payno'] ?? ($row['paidst'] ? 'HIST-'.$trnno : '')),
                        'lupd' => now(),
                    ]);
                }

                DB::connection('run')->table('coop_manual_loan_sources')->insert([
                    'loan_rec_id' => $mloanId,
                    'import_id' => $importId,
                    'member_rec_id' => $memberId ?: null,
                    'member_name' => $name,
                    'remaining_principal' => (int) ($loan['remaining_principal'] ?? max(0, $principal - array_sum(array_map(fn (array $row): int => $row['paidst'] ? $row['amount'] : 0, $rows)))),
                    'source_key' => (string) ($loan['source_key'] ?? $trnno),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }

            DB::connection('run')->table('coop_manual_loan_imports')->where('id', $importId)->update(['imported_count' => $count]);

            return ['import_id' => $importId, 'count' => $count];
        });
    }

    /**
     * Buat anggota historis di icu_member. Join date mengikuti periode pinjaman
     * pertama (tanggal 1 bulan tersebut). Harus dipanggil di dalam transaksi mysql.
     */
    private function createHistoricalMember(string $name, string $period, string $status): int
    {
        $joindt = preg_match('/^\d{6}$/', $period)
            ? substr($period, 0, 4).'-'.substr($period, 4, 2).'-01'
            : now()->toDateString();
        $now = now();

        return (int) DB::connection('mysql')->table('icu_member')->insertGetId([
            'itc_user_id' => 0,
            'pprdk' => '',
            'icuno' => CooperativeMember::generateIcuno(),
            'icunm' => mb_substr($name, 0, 40),
            'alias_nm' => '',
            'joindt' => $joindt,
            'st_aktif' => $status === 'active' ? CooperativeMember::STATUS_REGULAR_MEMBER : CooperativeMember::STATUS_NON_ACTIVE,
            'temp_trx' => 0,
            'otvalue' => 0,
            'swajib' => 0,
            'outstanding' => 0,
            'stat_trx' => 0,
            'refno' => '',
            'entusr' => 'RUN',
            'entdt' => $now,
            'lupd' => $now,
            'koreksi' => 0,
        ], 'rec_id');
    }

    private function nextTrnno(string $date): string
    {
        $seq = (int) DB::connection('mysql')->table('icu_mloan')->where('trnno', 'like', 'LON-%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(trnno, '-', -1) AS UNSIGNED)), 0) AS last_seq")->value('last_seq') + 1;

        return LoanPostingService::formatTrnno(CarbonImmutable::parse($date), $seq);
    }
}
