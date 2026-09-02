<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoan;
use App\Models\Cooperative\CooperativeLoanSchedule;
use App\Models\Cooperative\CooperativeMember;
use App\Models\Cooperative\CooperativeTransaction;
use App\Services\Cooperative\CooperativePeriod;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CooperativeDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) auth_user_id();

        // Super Admin / CU Admin melihat ringkasan seluruh koperasi.
        if (CooperativeAccess::isAdmin($userId)) {
            $currentPeriod = CooperativePeriod::current();

            return view('cooperative.dashboard', [
                'memberStats' => $this->memberStats(),
                'loanStats' => $this->loanStats(),
                'loanCalculation' => $this->loanCalculation(),
                'savingsSummary' => $this->savingsSummary(),
                'depositSeries' => $this->depositSeries(12),
                'dueSummary' => $this->dueSummary($currentPeriod),
                'recentTransactions' => $this->recentTransactions(),
                'currentPeriodLabel' => CooperativePeriod::label($currentPeriod),
            ]);
        }

        // CU Member / User Credit Union hanya melihat data miliknya sendiri.
        $member = CooperativeAccess::memberForUser($userId);

        return view('cooperative.dashboard-member', $this->personalViewData($member));
    }

    public function transactions(Request $request): View
    {
        $this->abortUnlessAdmin();

        $transactions = CooperativeTransaction::query()
            ->with('member')
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->paginate(25)
            ->withQueryString();

        return view('cooperative.transactions.admin', compact('transactions'));
    }

    public function myTransactions(Request $request): View
    {
        $member = CooperativeAccess::memberForUser((int) auth_user_id());

        $transactions = $member === null
            ? collect()
            : $member->transactions()
                ->orderByDesc('trndt')
                ->orderByDesc('rec_id')
                ->paginate(25)
                ->withQueryString();

        return view('cooperative.transactions.index', compact('member', 'transactions'));
    }

    public function depositDetail(Request $request, string $period): View
    {
        $this->abortUnlessAdmin();

        abort_unless(CooperativePeriod::isValid($period), 404);

        $baseQuery = CooperativeTransaction::query()
            ->where('pprd', $period)
            ->where('dbocr', CooperativeTransaction::DIRECTION_DEBIT);

        $transactions = (clone $baseQuery)
            ->with('member')
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->paginate(25)
            ->withQueryString();

        return view('cooperative.transactions.deposit-detail', [
            'periodLabel' => CooperativePeriod::longLabel($period),
            'transactions' => $transactions,
            'totalSetoran' => (int) (clone $baseQuery)->sum('amount'),
            'trxCount' => (int) (clone $baseQuery)->count(),
        ]);
    }

    public function savingsDetail(): View
    {
        $this->abortUnlessAdmin();

        return view('cooperative.savings-detail', [
            'savingsSummary' => $this->savingsSummary(),
        ]);
    }

    public function loanCalculationDetail(): View
    {
        $this->abortUnlessAdmin();

        return view('cooperative.loan-calculation-detail', [
            'loanCalculation' => $this->loanCalculation(),
        ]);
    }

    private function abortUnlessAdmin(): void
    {
        abort_unless(
            CooperativeAccess::isAdmin((int) auth_user_id()),
            403,
            'Hanya admin koperasi yang dapat mengakses halaman ini.'
        );
    }

    /**
     * @return array{total: int, regular: int, outstanding: int, non_active: int}
     */
    private function memberStats(): array
    {
        return [
            'total' => (int) CooperativeMember::query()->count(),
            'regular' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_REGULAR_MEMBER)->count(),
            'outstanding' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_OUTSTANDING_MEMBER)->count(),
            'non_active' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_NON_ACTIVE)->count(),
        ];
    }

    /**
     * @return array{total: int, running: int, indicative_outstanding: int}
     */
    private function loanStats(): array
    {
        return [
            'total' => (int) CooperativeLoan::query()->count(),
            'running' => (int) CooperativeLoan::query()->statusIndicative('running')->count(),
            // Indikatif: outstand icu_dloan terbukti = sisa pokok, jadi sisa dihitung dari principle - paid.
            'indicative_outstanding' => (int) CooperativeLoan::query()
                ->selectRaw('COALESCE(SUM(GREATEST(principle - paid, 0)), 0) AS indicative_outstanding')
                ->value('indicative_outstanding'),
        ];
    }

    /**
     * Kalkulasi pinjaman berjalan berdasarkan master dan jadwal angsuran.
     * paidst=1 diperlakukan sebagai angsuran yang sudah dibayar.
     *
     * @return array{total_pinjaman: int, total_dibayar: int, sisa_keseluruhan: int}
     */
    private function loanCalculation(): array
    {
        $runningLoanIds = CooperativeLoan::query()
            ->statusIndicative('running')
            ->pluck('rec_id');

        if ($runningLoanIds->isEmpty()) {
            return [
                'total_pinjaman' => 0,
                'total_dibayar' => 0,
                'sisa_keseluruhan' => 0,
            ];
        }

        $connection = DB::connection('mysql');
        $totalPinjaman = (int) $connection->table('icu_mloan')
            ->whereIn('rec_id', $runningLoanIds)
            ->sum('principle');
        $totalDibayar = (int) $connection->table('icu_dloan')
            ->whereIn('mst_rec_id', $runningLoanIds)
            ->where('paidst', 1)
            ->sum('amount');

        return [
            'total_pinjaman' => $totalPinjaman,
            'total_dibayar' => $totalDibayar,
            'sisa_keseluruhan' => $totalPinjaman - $totalDibayar,
        ];
    }

    /**
     * Ringkasan simpanan keseluruhan dari transaksi debit dan kredit.
     *
     * @return array{setoran: int, penarikan: int, neto: int}
     */
    private function savingsSummary(): array
    {
        $row = CooperativeTransaction::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'D' THEN amount ELSE 0 END), 0) AS setoran")
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'C' THEN amount ELSE 0 END), 0) AS penarikan")
            ->first();

        $setoran = (int) ($row->setoran ?? 0);
        $penarikan = (int) ($row->penarikan ?? 0);

        return [
            'setoran' => $setoran,
            'penarikan' => $penarikan,
            'neto' => $setoran - $penarikan,
        ];
    }

    /**
     * Seri setoran (debit) anggota untuk N periode terakhir yang memiliki transaksi.
     *
     * @return list<array{periode: string, label: string, short: string, total: int, trx_count: int, percent: float}>
     */
    private function depositSeries(int $months): array
    {
        $rows = CooperativeTransaction::query()
            ->selectRaw('pprd, SUM(amount) AS total, COUNT(*) AS trx_count')
            ->where('dbocr', 'D')
            ->groupBy('pprd')
            ->orderByDesc('pprd')
            ->limit($months)
            ->get();

        $series = $rows->map(fn ($row): array => [
            'periode' => (string) $row->pprd,
            'label' => CooperativePeriod::label((string) $row->pprd),
            'short' => CooperativePeriod::shortLabel((string) $row->pprd),
            'total' => (int) $row->total,
            'trx_count' => (int) $row->trx_count,
            'percent' => 0.0,
        ])->sortBy('periode')->values()->all();

        if ($series === []) {
            return [];
        }

        $max = max(array_column($series, 'total'));

        foreach ($series as $index => $point) {
            $percent = $max > 0 ? round($point['total'] / $max * 100, 1) : 0.0;
            $series[$index]['percent'] = $point['total'] > 0 ? max(3.0, $percent) : 0.0;
        }

        return $series;
    }

    /**
     * Tagihan jadwal pada periode berjalan menurut icu_dloan.
     *
     * @return array{count: int, total_due: int, rows: Collection<int, object>}
     */
    private function dueSummary(string $currentPeriod): array
    {
        $base = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.periode', $currentPeriod);

        $count = (clone $base)->count();
        $totalDue = (int) (clone $base)->selectRaw('COALESCE(SUM(d.amount + d.int_amt + d.others), 0) AS total_due')->value('total_due');

        $rows = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.periode', $currentPeriod)
            ->orderByDesc(DB::raw('d.amount + d.int_amt + d.others'))
            ->limit(5)
            ->get(['d.seqno', 'd.totseqno', 'd.amount', 'd.int_amt', 'd.others', 'd.paidst', 'd.payno', 'l.rec_id AS loan_rec_id', 'l.trnno', 'm.icuno', 'm.icunm']);

        return ['count' => $count, 'total_due' => $totalDue, 'rows' => $rows];
    }

    /**
     * @return Collection<int, CooperativeTransaction>
     */
    private function recentTransactions()
    {
        return CooperativeTransaction::query()
            ->with('member')
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->limit(10)
            ->get();
    }

    /**
     * Data dashboard pribadi: hanya milik anggota yang sedang login.
     *
     * Jika akun login belum ditautkan ke record anggota, kembalikan data kosong
     * sehingga halaman tetap dapat dirender dengan pesan sinkronisasi.
     *
     * @return array<string, mixed>
     */
    private function personalViewData(?CooperativeMember $member): array
    {
        $currentPeriod = CooperativePeriod::current();

        if ($member === null) {
            return [
                'member' => null,
                'savingsTotals' => ['debit' => 0, 'credit' => 0, 'count' => 0, 'debit_count' => 0, 'credit_count' => 0],
                'depositSeries' => [],
                'loanCards' => [],
                'dueSummary' => ['count' => 0, 'total_due' => 0, 'rows' => collect()],
                'recentTransactions' => collect(),
                'currentPeriodLabel' => CooperativePeriod::label($currentPeriod),
            ];
        }

        return [
            'member' => $member,
            'savingsTotals' => $this->personalSavingsTotals($member),
            'depositSeries' => $this->personalDepositSeries($member, 12),
            'loanCards' => $this->personalLoanCards($member),
            'dueSummary' => $this->personalDueSummary($member, $currentPeriod),
            'recentTransactions' => $this->personalRecentTransactions($member),
            'currentPeriodLabel' => CooperativePeriod::label($currentPeriod),
        ];
    }

    /**
     * @return array{debit: int, credit: int, count: int, debit_count: int, credit_count: int}
     */
    private function personalSavingsTotals(CooperativeMember $member): array
    {
        $row = $member->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'D' THEN amount ELSE 0 END), 0) AS debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'C' THEN amount ELSE 0 END), 0) AS credit")
            ->selectRaw("SUM(CASE WHEN dbocr = 'D' THEN 1 ELSE 0 END) AS debit_count")
            ->selectRaw("SUM(CASE WHEN dbocr = 'C' THEN 1 ELSE 0 END) AS credit_count")
            ->first();

        return [
            'debit' => (int) ($row->debit ?? 0),
            'credit' => (int) ($row->credit ?? 0),
            'count' => (int) (($row->debit_count ?? 0)) + (int) (($row->credit_count ?? 0)),
            'debit_count' => (int) ($row->debit_count ?? 0),
            'credit_count' => (int) ($row->credit_count ?? 0),
        ];
    }

    /**
     * Seri setoran pribadi anggota untuk N periode terakhir yang memiliki transaksi.
     *
     * @return list<array{periode: string, label: string, short: string, total: int, trx_count: int, percent: float}>
     */
    private function personalDepositSeries(CooperativeMember $member, int $months): array
    {
        $rows = $member->transactions()
            ->selectRaw('pprd, SUM(amount) AS total, COUNT(*) AS trx_count')
            ->where('dbocr', 'D')
            ->groupBy('pprd')
            ->orderByDesc('pprd')
            ->limit($months)
            ->get();

        $series = $rows->map(fn ($row): array => [
            'periode' => (string) $row->pprd,
            'label' => CooperativePeriod::label((string) $row->pprd),
            'short' => CooperativePeriod::shortLabel((string) $row->pprd),
            'total' => (int) $row->total,
            'trx_count' => (int) $row->trx_count,
            'percent' => 0.0,
        ])->sortBy('periode')->values()->all();

        if ($series === []) {
            return [];
        }

        $max = max(array_column($series, 'total'));

        foreach ($series as $index => $point) {
            $percent = $max > 0 ? round($point['total'] / $max * 100, 1) : 0.0;
            $series[$index]['percent'] = $point['total'] > 0 ? max(3.0, $percent) : 0.0;
        }

        return $series;
    }

    /**
     * Kartu pinjaman pribadi beserta progres pembayarannya.
     *
     * @return list<array{loan: CooperativeLoan, progress: float, remaining: int}>
     */
    private function personalLoanCards(CooperativeMember $member): array
    {
        return $member->loans()
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->get()
            ->map(fn (CooperativeLoan $loan): array => [
                'loan' => $loan,
                'progress' => $this->loanProgress($loan),
                'remaining' => max(0, $loan->principle - $loan->paid),
            ])
            ->all();
    }

    /**
     * Cicilan milik anggota pada periode berjalan menurut icu_dloan.
     *
     * @return array{count: int, total_due: int, rows: Collection<int, array<string, mixed>>}
     */
    private function personalDueSummary(CooperativeMember $member, string $currentPeriod): array
    {
        $loansById = $member->loans()->get()->keyBy('rec_id');

        if ($loansById->isEmpty()) {
            return ['count' => 0, 'total_due' => 0, 'rows' => collect()];
        }

        $rows = CooperativeLoanSchedule::query()
            ->whereIn('mst_rec_id', $loansById->keys())
            ->where('periode', $currentPeriod)
            ->orderBy('mst_rec_id')
            ->orderBy('seqno')
            ->get()
            ->map(fn (CooperativeLoanSchedule $schedule): array => [
                'trnno' => (string) ($loansById[$schedule->mst_rec_id]->trnno ?? '-'),
                'installment' => $schedule->installmentLabel(),
                'principal' => $schedule->amount,
                'interest' => $schedule->int_amt,
                'others' => $schedule->others,
                'total_due' => $schedule->totalDue(),
                'status_label' => $schedule->paymentStatusLabel(),
                'status_badge' => $schedule->paymentStatusBadgeClass(),
            ]);

        return [
            'count' => $rows->count(),
            'total_due' => (int) $rows->sum('total_due'),
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, CooperativeTransaction>
     */
    private function personalRecentTransactions(CooperativeMember $member)
    {
        return $member->transactions()
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->limit(10)
            ->get();
    }

    private function loanProgress(CooperativeLoan $loan): float
    {
        if ($loan->totalloan <= 0) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, $loan->paid / $loan->totalloan * 100)), 1);
    }
}
