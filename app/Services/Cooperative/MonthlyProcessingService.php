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
     * @return array{rows: Collection, totals: array<string, int>}
     */
    public function generate(string $period): array
    {
        $savingTotals = DB::connection('run')->table('coop_savings')
            ->where('pprd', $period)
            ->where('status', 'posted')
            ->groupBy('member_rec_id')
            ->select('member_rec_id')
            ->selectRaw('SUM(amount) AS saving')
            ->get()
            ->keyBy('member_rec_id');

        $loanTotals = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->where('d.periode', $period)
            ->groupBy('l.icu_rec_id')
            ->select('l.icu_rec_id')
            ->selectRaw('SUM(d.amount) AS loan')
            ->selectRaw('SUM(d.int_amt + d.others) AS expense')
            ->selectRaw("GROUP_CONCAT(DISTINCT CASE WHEN d.totseqno > 0 THEN CONCAT(d.seqno, '/', d.totseqno) ELSE d.seqno END ORDER BY d.seqno SEPARATOR ', ') AS installments")
            ->get()
            ->keyBy('icu_rec_id');

        $memberIds = $savingTotals->keys()->merge($loanTotals->keys())->unique()->values();
        $members = CooperativeMember::query()
            ->whereIn('st_aktif', [1, 2, 3, 4, 5])
            ->whereIn('rec_id', $memberIds)
            ->orderBy('icunm')
            ->get(['rec_id', 'icuno', 'icunm']);

        $rows = $members->map(function (CooperativeMember $member) use ($savingTotals, $loanTotals): array {
            $saving = (int) ($savingTotals[$member->rec_id]->saving ?? 0);
            $loan = (int) ($loanTotals[$member->rec_id]->loan ?? 0);
            $expense = (int) ($loanTotals[$member->rec_id]->expense ?? 0);

            return [
                'member_rec_id' => (int) $member->rec_id,
                'member_icuno' => (string) $member->icuno,
                'member_name' => (string) $member->icunm,
                'saving' => $saving,
                'loan' => $loan,
                'installments' => (string) ($loanTotals[$member->rec_id]->installments ?? ''),
                'expense' => $expense,
                'total' => $saving + $loan + $expense,
            ];
        })->filter(fn (array $row): bool => $row['saving'] + $row['loan'] + $row['expense'] !== 0)->values();

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

    public function save(string $period, string $company, int $userId): CooperativeMonthlyHrdTransaction
    {
        return DB::connection('mysql')->transaction(function () use ($period, $company, $userId): CooperativeMonthlyHrdTransaction {
            $total = $this->generate($period)['totals']['total'];
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
