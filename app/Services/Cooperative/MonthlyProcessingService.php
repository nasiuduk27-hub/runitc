<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeMember;
use App\Models\Cooperative\CooperativeMonthlyHrdTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyProcessingService
{
    /**
     * Rekap satu periode untuk HRD: simpanan wajib (sudah maupun belum
     * diposting) + seluruh baris angsuran jatuh tempo (paidst 0 maupun 1).
     * Menampilkan rekap penuh periode; baris yang sudah dibukukan ditandai
     * agar tetap terlihat namun tidak ditagih dua kali.
     *
     * @return array{rows: Collection, totals: array<string, int>}
     */
    public function generate(string $period): array
    {
        $activeStatuses = [1, 2, 3, 4, 5];

        $postedSavings = DB::connection('run')->table('coop_savings')
            ->where('pprd', $period)
            ->where('status', 'posted')
            ->pluck('member_rec_id')
            ->all();

        $savings = CooperativeMember::query()
            ->whereIn('st_aktif', $activeStatuses)
            ->where('swajib', '>', 0)
            ->get(['rec_id', 'swajib', 'icuno', 'icunm']);

        $loanRows = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.periode', $period)
            ->whereIn('m.st_aktif', $activeStatuses)
            ->get([
                'd.seqno', 'd.totseqno', 'd.amount', 'd.int_amt', 'd.others', 'd.paidst',
                'l.icu_rec_id as member_rec_id',
            ]);

        $memberIds = $savings->pluck('rec_id')->merge($loanRows->pluck('member_rec_id'))->unique()->values();

        $members = CooperativeMember::query()
            ->whereIn('rec_id', $memberIds)
            ->orderBy('icunm')
            ->get(['rec_id', 'icuno', 'icunm']);

        $savingByMember = $savings->keyBy('rec_id');
        $loanByMember = $loanRows->groupBy('member_rec_id');

        $rows = $members->map(function (CooperativeMember $member) use ($savingByMember, $loanByMember, $postedSavings): array {
            $saving = (int) ($savingByMember[$member->rec_id]->swajib ?? 0);
            $savingPosted = in_array((int) $member->rec_id, $postedSavings, true);
            $installments = $loanByMember[$member->rec_id] ?? collect();
            $loan = (int) $installments->sum('amount');
            $expense = (int) $installments->sum(fn ($row): int => (int) $row->int_amt + (int) $row->others);
            $loanPosted = $installments->isNotEmpty()
                && $installments->every(fn ($row): bool => (int) $row->paidst === 1);

            return [
                'member_rec_id' => (int) $member->rec_id,
                'member_icuno' => (string) $member->icuno,
                'member_name' => (string) $member->icunm,
                'saving' => $saving,
                'saving_posted' => $savingPosted,
                'loan' => $loan,
                'loan_posted' => $loanPosted,
                'installments' => $installments
                    ->map(fn ($row): string => (int) $row->totseqno > 0 ? (int) $row->seqno.'/'.(int) $row->totseqno : (string) $row->seqno)
                    ->implode(', '),
                'expense' => $expense,
                'total' => $saving + $loan + $expense,
            ];
        })->filter(fn (array $row): bool => $row['total'] !== 0)->values();

        return [
            'rows' => $rows,
            'totals' => [
                'saving' => (int) $rows->sum('saving'),
                'loan' => (int) $rows->sum('loan'),
                'expense' => (int) $rows->sum('expense'),
                'total' => (int) $rows->sum('total'),
            ],
        ];
    }

    /**
     * Total tagihan yang BELUM diposting untuk satu periode (simpanan wajib
     * belum disetor + angsuran jatuh tempo paidst=0). Dipakai untuk tagihan
     * icu_mtrx2hrd agar baris yang sudah dibukukan tidak ditagih dua kali.
     */
    public function unpostedTotals(string $period): int
    {
        $activeStatuses = [1, 2, 3, 4, 5];

        $postedSavings = DB::connection('run')->table('coop_savings')
            ->where('pprd', $period)
            ->where('status', 'posted')
            ->pluck('member_rec_id')
            ->all();

        $savingTotal = (int) CooperativeMember::query()
            ->whereIn('st_aktif', $activeStatuses)
            ->where('swajib', '>', 0)
            ->whereNotIn('rec_id', $postedSavings)
            ->sum('swajib');

        $loanTotal = (int) DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.periode', $period)
            ->where('d.paidst', 0)
            ->whereIn('m.st_aktif', $activeStatuses)
            ->selectRaw('COALESCE(SUM(d.amount + d.int_amt + d.others), 0) AS total')
            ->value('total');

        return $savingTotal + (int) $loanTotal;
    }

    /**
     * Snapshot rekonsiliasi satu periode: tagihan HRD tersimpan (icu_mtrx2hrd),
     * yang sudah diterima di rekening (icu_bank_trx D mereferensikan tagihan),
     * serta detail simpanan/angsuran yang sudah dibukukan (icu_transaction 19/20).
     *
     * icu_bank_trx juga mencatat transaksi di luar simpan-pinjam (biaya bank,
     * koreksi, transfer), jadi selisih tidak harus nol selama bisa dijelaskan.
     *
     * @return array<string, int>
     */
    public function reconciliation(string $period): array
    {
        $tagihan = (int) DB::connection('mysql')->table('icu_mtrx2hrd')
            ->where('pprdk', $period)
            ->sum('trx_amt');

        $referenceNumbers = DB::connection('mysql')->table('icu_mtrx2hrd')
            ->where('pprdk', $period)
            ->pluck('trxno');

        $diterima = $referenceNumbers->isEmpty()
            ? 0
            : (int) DB::connection('mysql')->table('icu_bank_trx')
                ->whereIn('req_frm_trxno', $referenceNumbers->all())
                ->where('dbocr', 'D')
                ->sum('amount');

        $detailPosted = (int) DB::connection('mysql')->table('icu_transaction')
            ->where('pprd', $period)
            ->whereIn('trncd', [SavingsService::TRNCD_SAVINGS, LoanPaymentService::getInstallmentTrncd()])
            ->sum('amount');

        return [
            'tagihan' => $tagihan,
            'diterima' => $diterima,
            'belum_diterima' => max(0, $tagihan - $diterima),
            'detail_posted' => $detailPosted,
        ];
    }

    public function save(string $period, string $company, int $userId): CooperativeMonthlyHrdTransaction
    {
        return DB::connection('mysql')->transaction(function () use ($period, $company, $userId): CooperativeMonthlyHrdTransaction {
            $total = $this->unpostedTotals($period);
            $now = CarbonImmutable::now();
            $user = $this->userAlias($userId);
            $query = CooperativeMonthlyHrdTransaction::query()
                ->where('pprdk', $period)
                ->where('cmpcd', $company)
                ->lockForUpdate();
            $existing = $query->first();

            if ($existing) {
                $existing->update([
                    'trx_amt' => $total,
                    'trxdt' => $now->toDateString(),
                    'lupdt' => $now,
                    'entusr' => $user,
                ]);

                return $existing->refresh();
            }

            $sequence = (int) DB::connection('mysql')->table('icu_mtrx2hrd')
                ->where('trxno', 'like', 'PMT-%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(trxno, '-', -1) AS UNSIGNED)), 0) AS last_seq")
                ->lockForUpdate()
                ->value('last_seq') + 1;

            return CooperativeMonthlyHrdTransaction::query()->create([
                'pprdk' => $period,
                'trxno' => LoanPostingService::formatLegacyTrnno('PMT', $now, $sequence),
                'trxdt' => $now->toDateString(),
                'cmpcd' => $company,
                'trx_amt' => $total,
                'statrec' => 0,
                'entdt' => $now,
                'lupdt' => $now,
                'entusr' => $user,
            ]);
        });
    }

    /** @return array<string, string> */
    public function companies(): array
    {
        return DB::connection('mysql')->table('sys_msttable')
            ->where('tbl_code', '54')
            ->where('statrec', 1)
            ->orderBy('descr')
            ->get(['code', 'descr'])
            ->mapWithKeys(fn ($row): array => [(string) $row->code => trim((string) $row->descr)])
            ->all();
    }

    private function userAlias(int $userId): string
    {
        $alias = DB::connection('run')->table('sysitc_users')
            ->where('rec_id', $userId)
            ->value('acc3chrnm');

        return mb_strtoupper(mb_substr(trim((string) $alias), 0, 5)) ?: 'RUN';
    }
}
