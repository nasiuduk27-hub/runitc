<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoanPayment;
use App\Models\Cooperative\CooperativeLoanPaymentAction;
use App\Models\Cooperative\CooperativeLoanPaymentAllocation;
use App\Models\Cooperative\CooperativeMember;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\LoanPaymentService;
use App\Services\Cooperative\SavingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanPaymentController extends Controller
{
    public function __construct(
        private readonly LoanPaymentService $payments,
        private readonly SavingsService $savings,
    ) {}

    /**
     * Layar entry bulanan berbasis ceklis: angsuran jatuh tempo & simpanan bulanan.
     */
    public function index(Request $request): View
    {
        $period = $this->validPeriod($request, CooperativePeriod::current());
        $keyword = trim((string) $request->query('q', ''));

        $data = $this->checklistData($period, $keyword);

        return view('cooperative.payments.index', array_merge([
            'period' => $period,
            'keyword' => $keyword,
        ], $data));
    }

    /**
     * Proses batch: ceklis angsuran & simpanan bulanan, langsung diposting.
     */
    public function store(Request $request): RedirectResponse
    {
        $period = $this->validPeriod($request, CooperativePeriod::current());
        $installmentKeys = array_values(array_filter((array) $request->input('installments', []), 'is_numeric'));
        $savingsMemberIds = array_values(array_filter((array) $request->input('savings', []), 'is_numeric'));

        $userId = $this->currentUserId($request);
        $paymentDate = $this->savings->paymentDate($period)->toDateString();

        $postedLoans = 0;
        $postedSavings = 0;
        $errors = [];

        foreach ($installmentKeys as $key) {
            $dloanRecId = (int) $key;
            if ($dloanRecId <= 0) {
                continue;
            }

            try {
                $this->postInstallment($dloanRecId, $period, $paymentDate, $userId);
                $postedLoans++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        foreach ($savingsMemberIds as $key) {
            $memberRecId = (int) $key;
            if ($memberRecId <= 0) {
                continue;
            }

            try {
                $member = CooperativeMember::query()->find($memberRecId);
                if (! $member) {
                    $errors[] = 'Anggota #'.$memberRecId.' tidak ditemukan.';
                    continue;
                }

                $this->savings->postSavings($member, $period, $userId);
                $postedSavings++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $message = $postedLoans.' angsuran & '.$postedSavings.' simpanan berhasil diposting.';

        if ($errors !== []) {
            $message .= ' '.implode(' ', array_unique($errors));
        }

        return redirect()
            ->route('cooperative.payments.index', ['period' => $period])
            ->with('success', $message);
    }

    public function detail(Request $request): View
    {
        $payment = CooperativeLoanPayment::query()->with(['actions', 'allocations'])->findOrFail((int) $request->query('id'));

        $allocationContext = DB::connection('mysql')
            ->table('icu_dloan')
            ->whereIn('rec_id', $payment->allocations->pluck('dloan_rec_id')->all())
            ->get(['rec_id', 'periode', 'seqno', 'totseqno'])
            ->keyBy('rec_id');

        return view('cooperative.payments.detail', [
            'payment' => $payment,
            'actions' => $payment->actions,
            'allocations' => $payment->allocations,
            'allocationContext' => $allocationContext,
            'service' => $this->payments,
            'currentUserId' => $this->currentUserId($request),
        ]);
    }

    public function decide(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:verify,reject,cancel'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = CooperativeLoanPayment::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);
        $isMaker = $userId === $payment->maker_user_id;

        [$targetStatus, $action] = match ((string) $data['decision']) {
            'verify' => [LoanPaymentService::STATUS_VERIFIED, CooperativeLoanPaymentAction::ACTION_VERIFIED],
            'reject' => [LoanPaymentService::STATUS_REJECTED, CooperativeLoanPaymentAction::ACTION_REJECTED],
            default => [LoanPaymentService::STATUS_CANCELLED, CooperativeLoanPaymentAction::ACTION_CANCELLED],
        };

        if ($data['decision'] === 'cancel' && ! $isMaker && $payment->status === LoanPaymentService::STATUS_SUBMITTED) {
            return back()->withErrors(['decision' => 'Hanya pembuat yang dapat membatalkan pembayaran sebelum diverifikasi.']);
        }

        try {
            if ($data['decision'] === 'verify') {
                // post() melakukan klaim status, tulis ke icu%, dan revert bila gagal.
                $this->payments->post($payment, $userId);
            } else {
                $this->payments->assertTransition($payment->status, $targetStatus);
                $payment->forceFill([
                    'status' => $targetStatus,
                    'checker_user_id' => $userId,
                    'checked_at' => now(),
                    'decision_note' => ($data['note'] ?? '') !== '' ? $data['note'] : null,
                ])->save();
            }
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        $actionNote = ($data['note'] ?? '') !== ''
            ? $data['note']
            : ($action === CooperativeLoanPaymentAction::ACTION_VERIFIED ? 'Diposting sebagai '.$payment->refresh()->icu_trnno : null);

        CooperativeLoanPaymentAction::query()->create([
            'payment_id' => $payment->id,
            'action' => $action,
            'note' => $actionNote,
            'actor_user_id' => $userId,
            'actor_name' => $this->actorName($userId),
        ]);

        $this->writeAudit($request, $action, $payment->id, [
            'to_status' => $targetStatus,
            'icu_trnno' => $payment->refresh()->icu_trnno,
        ]);

        return redirect()
            ->route('cooperative.payments.detail', ['id' => $payment->id])
            ->with('success', 'Keputusan berhasil dicatat.');
    }

    /**
     * Posting satu baris cicilan jatuh tempo periode terpilih (nilai = sisa tagihan).
     */
    private function postInstallment(int $dloanRecId, string $period, string $paymentDate, int $userId): void
    {
        $row = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.rec_id', $dloanRecId)
            ->where('d.paidst', 0)
            ->first([
                'd.rec_id as dloan_rec_id', 'd.seqno', 'd.amount', 'd.int_amt', 'd.others',
                'l.rec_id as loan_rec_id', 'l.trnno',
                'm.rec_id as member_rec_id', 'm.icuno', 'm.icunm',
            ]);

        if (! $row) {
            throw new InvalidArgumentException('Cicilan sudah tidak berstatus belum dibayar.');
        }

        $applied = (int) DB::connection('run')->table('coop_loan_payment_allocations as a')
            ->join('coop_loan_payments as p', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.status', [LoanPaymentService::STATUS_SUBMITTED, LoanPaymentService::STATUS_VERIFIED])
            ->where('a.dloan_rec_id', $dloanRecId)
            ->sum('a.amount_applied');

        $due = (int) $row->amount + (int) $row->int_amt + (int) $row->others;
        $remaining = max(0, $due - $applied);

        if ($remaining <= 0) {
            throw new InvalidArgumentException('Cicilan ini sudah lunas.');
        }

        $unpaidRow = [
            'rec_id' => $dloanRecId,
            'seqno' => (int) $row->seqno,
            'due' => $due,
            'principal' => (int) $row->amount,
            'interest' => (int) $row->int_amt,
            'others' => (int) $row->others,
            'remaining' => $remaining,
        ];

        $allocations = $this->payments->allocate($remaining, [$unpaidRow]);
        $principalPortion = array_sum(array_column($allocations, 'principal_applied'));
        $interestPortion = array_sum(array_column($allocations, 'interest_applied'));

        $paymentId = DB::connection('run')->transaction(function () use (
            $row, $period, $paymentDate, $remaining, $principalPortion, $interestPortion, $allocations, $userId
        ): int {
            $payment = CooperativeLoanPayment::query()->create([
                'loan_rec_id' => $row->loan_rec_id,
                'member_rec_id' => $row->member_rec_id,
                'member_icuno' => $row->icuno,
                'member_name' => $row->icunm,
                'payment_date' => $paymentDate,
                'amount' => $remaining,
                'method' => SavingsService::METHOD_POTONG_GAJI,
                'notes' => null,
                'status' => LoanPaymentService::STATUS_SUBMITTED,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'maker_user_id' => $userId,
            ]);

            foreach ($allocations as $allocation) {
                CooperativeLoanPaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'dloan_rec_id' => $allocation['dloan_rec_id'],
                    'seqno' => $allocation['seqno'],
                    'amount_applied' => $allocation['amount_applied'],
                    'covers_full' => $allocation['covers_full'],
                ]);
            }

            CooperativeLoanPaymentAction::query()->create([
                'payment_id' => $payment->id,
                'action' => CooperativeLoanPaymentAction::ACTION_SUBMITTED,
                'note' => null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            return (int) $payment->id;
        });

        $payment = CooperativeLoanPayment::query()->findOrFail($paymentId);

        $this->payments->post($payment, $userId, $period);

        CooperativeLoanPaymentAction::query()->create([
            'payment_id' => $payment->id,
            'action' => CooperativeLoanPaymentAction::ACTION_VERIFIED,
            'note' => 'Diposting otomatis sebagai '.$payment->icu_trnno,
            'actor_user_id' => $userId,
            'actor_name' => $this->actorName($userId),
        ]);
    }

    /**
     * @return array{grouped: list<array<string, mixed>>, totals: array<string, int>, posted_savings: list<int>}
     */
    private function checklistData(string $period, string $keyword = ''): array
    {
        $kw = $keyword !== ''
            ? '%'.str_replace('%', '\%', $keyword).'%'
            : null;

        $memberPool = DB::connection('mysql')->table('icu_member as m')
            ->where(function ($query) use ($period): void {
                $query->where('m.swajib', '>', 0)
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                        ->from('icu_dloan as d')
                        ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
                        ->whereColumn('l.icu_rec_id', '=', 'm.rec_id')
                        ->where('d.periode', $period)
                        ->where('d.paidst', 0));
            })
            ->when($kw !== null, function ($query) use ($kw): void {
                $query->where(fn ($inner) => $inner
                    ->where('m.icuno', 'like', $kw)
                    ->orWhere('m.icunm', 'like', $kw));
            })
            ->orderBy('m.icuno')
            ->get(['m.rec_id', 'm.icuno', 'm.icunm', 'm.swajib']);

        $memberIds = $memberPool->pluck('rec_id')->all();

        $dueRows = collect();
        if ($memberIds !== []) {
            $dueRows = DB::connection('mysql')->table('icu_dloan as d')
                ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
                ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
                ->whereIn('l.icu_rec_id', $memberIds)
                ->where('d.periode', $period)
                ->where('d.paidst', 0)
                ->orderBy('d.seqno')
                ->get([
                    'd.rec_id as dloan_rec_id', 'd.seqno', 'd.totseqno', 'd.amount', 'd.int_amt', 'd.others',
                    'l.rec_id as loan_rec_id', 'l.trnno',
                    'm.rec_id as member_rec_id', 'm.icuno', 'm.icunm',
                ]);
        }

        $dloanIds = $dueRows->pluck('dloan_rec_id')->all();
        $appliedByRow = [];
        if ($dloanIds !== []) {
            $appliedByRow = DB::connection('run')->table('coop_loan_payment_allocations as a')
                ->join('coop_loan_payments as p', 'p.id', '=', 'a.payment_id')
                ->whereIn('p.status', [LoanPaymentService::STATUS_SUBMITTED, LoanPaymentService::STATUS_VERIFIED])
                ->whereIn('a.dloan_rec_id', $dloanIds)
                ->groupBy('a.dloan_rec_id')
                ->get(['a.dloan_rec_id', DB::raw('SUM(a.amount_applied) AS applied')])
                ->keyBy('dloan_rec_id');
        }

        $postedSavings = $memberIds === [] ? [] : DB::connection('run')->table('coop_savings')
            ->where('pprd', $period)
            ->whereIn('member_rec_id', $memberIds)
            ->pluck('member_rec_id')
            ->all();

        $totals = $this->savings->monthlyTotals($period);

        $grouped = [];
        foreach ($memberPool as $member) {
            $installments = $dueRows
                ->where('member_rec_id', $member->rec_id)
                ->map(function ($row) use ($appliedByRow): array {
                    $due = (int) $row->amount + (int) $row->int_amt + (int) $row->others;
                    $remaining = max(0, $due - (int) ($appliedByRow[$row->dloan_rec_id]->applied ?? 0));

                    return [
                        'dloan_rec_id' => (int) $row->dloan_rec_id,
                        'loan_rec_id' => (int) $row->loan_rec_id,
                        'trnno' => $row->trnno,
                        'seqno' => (int) $row->seqno,
                        'totseqno' => (int) $row->totseqno,
                        'due' => $due,
                        'remaining' => $remaining,
                    ];
                })
                ->filter(fn (array $row): bool => $row['remaining'] > 0)
                ->values()
                ->all();

            $swajib = (int) $member->swajib;
            $savings = $swajib > 0
                ? ['amount' => $swajib, 'posted' => in_array($member->rec_id, $postedSavings, true)]
                : null;

            $grouped[] = [
                'rec_id' => (int) $member->rec_id,
                'icuno' => $member->icuno,
                'icunm' => $member->icunm,
                'installments' => $installments,
                'savings' => $savings,
                'totals' => [
                    'savings' => $totals[$member->rec_id]['savings'] ?? 0,
                    'loan_principal' => $totals[$member->rec_id]['loan_principal'] ?? 0,
                ],
            ];
        }

        $summaryTotals = [
            'members' => count($grouped),
            'installments' => (int) collect($grouped)->sum(fn (array $g): int => count($g['installments'])),
            'savings_inflow' => (int) collect($totals)->sum(fn (array $t): int => $t['savings']),
            'loan_inflow' => (int) collect($totals)->sum(fn (array $t): int => $t['loan_principal']),
        ];

        return [
            'grouped' => $grouped,
            'totals' => $summaryTotals,
            'posted_savings' => $postedSavings,
        ];
    }

    private function validPeriod(Request $request, string $fallback): string
    {
        $value = (string) $request->input('period', $request->query('period', $fallback));
        $value = str_replace('-', '', $value);

        return CooperativePeriod::isValid($value) ? $value : $fallback;
    }

    private function currentUserId(Request $request): int
    {
        return (int) auth_user_id();
    }

    private function actorName(int $userId): string
    {
        if ($userId <= 0) {
            return 'Unknown';
        }

        return (string) (DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->value('account_nm') ?: 'User-'.$userId);
    }

    private function writeAudit(Request $request, string $action, int $paymentId, array $metadata): void
    {
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => $this->currentUserId($request),
            'action' => 'cooperative.loan_payment.'.$action,
            'target_type' => 'coop_loan_payment',
            'target_id' => $paymentId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
