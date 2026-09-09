<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeLoanPayment;
use App\Models\Cooperative\CooperativeLoanPaymentAction;
use App\Models\Cooperative\CooperativeLoanPaymentAllocation;
use App\Models\Cooperative\CooperativeMember;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Posting rutin bulanan potong gaji dari menu Transaksi Bank.
 *
 * Menggantikan menu "Bayar Angsuran & Simpanan" untuk alur batch bulanan:
 * seluruh simpanan wajib yang belum disetor + seluruh angsuran jatuh tempo
 * (icu_dloan, paidst=0) pada satu periode diposting otomatis. Menulis tabel
 * yang sama dengan menu lama (icu_transaction, icu_dloan, icu_mloan,
 * icu_member + rekaman RUNITC) karena memakai service yang sama.
 */
class MonthlyPostingService
{
    private const ACTIVE_STATUSES = [1, 2, 3, 4, 5];

    public function __construct(
        private readonly LoanPaymentService $payments,
        private readonly SavingsService $savings,
    ) {}

    /**
     * Posting seluruh tagihan (simpanan wajib + angsuran jatuh tempo) satu periode.
     * Idempoten: anggota/baris yang sudah tercatat dilewati.
     *
     * @return array{savings: int, installments: int, total: int, errors: list<string>}
     */
    public function postAll(string $period, int $userId): array
    {
        $now = now();

        $postedSavings = DB::connection('run')->table('coop_savings')
            ->where('pprd', $period)
            ->pluck('member_rec_id')
            ->all();

        $savingsDue = CooperativeMember::query()
            ->whereIn('st_aktif', self::ACTIVE_STATUSES)
            ->where('swajib', '>', 0)
            ->whereNotIn('rec_id', $postedSavings)
            ->get(['rec_id', 'swajib']);

        $loanRows = DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.periode', $period)
            ->where('d.paidst', 0)
            ->whereIn('m.st_aktif', self::ACTIVE_STATUSES)
            ->get([
                'd.rec_id as dloan_rec_id', 'd.seqno', 'd.amount', 'd.int_amt', 'd.others',
                'l.rec_id as loan_rec_id', 'l.trnno',
                'm.rec_id as member_rec_id', 'm.icuno', 'm.icunm',
            ]);

        $countSavings = 0;
        $countInstallments = 0;
        $total = 0;
        $errors = [];

        foreach ($savingsDue as $member) {
            try {
                $this->savings->postSavings($member, $period, $userId);
                $countSavings++;
                $total += (int) $member->swajib;
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Simpanan '.$member->icuno.': '.$e->getMessage();
            }
        }

        foreach ($loanRows as $row) {
            try {
                $this->postInstallment($row, $period, $now, $userId);
                $countInstallments++;
                $total += $row->amount + $row->int_amt + $row->others;
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Angsuran '.$row->icuno.' ('.$row->trnno.'): '.$e->getMessage();
            }
        }

        return [
            'savings' => $countSavings,
            'installments' => $countInstallments,
            'total' => $total,
            'errors' => $errors,
        ];
    }

    /**
     * Posting satu baris cicilan jatuh tempo (nilai = sisa tagihan), ekuivalen
     * dengan LoanPaymentController::postInstallment.
     */
    private function postInstallment(object $row, string $period, \DateTimeInterface $now, int $userId): void
    {
        $applied = (int) DB::connection('run')->table('coop_loan_payment_allocations as a')
            ->join('coop_loan_payments as p', 'p.id', '=', 'a.payment_id')
            ->whereIn('p.status', [LoanPaymentService::STATUS_SUBMITTED, LoanPaymentService::STATUS_VERIFIED])
            ->where('a.dloan_rec_id', $row->dloan_rec_id)
            ->sum('a.amount_applied');

        $due = (int) $row->amount + (int) $row->int_amt + (int) $row->others;
        $remaining = max(0, $due - $applied);

        if ($remaining <= 0) {
            throw new InvalidArgumentException('Cicilan ini sudah lunas.');
        }

        $unpaidRow = [
            'rec_id' => (int) $row->dloan_rec_id,
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
            $row, $now, $remaining, $principalPortion, $interestPortion, $allocations, $userId
        ): int {
            $payment = CooperativeLoanPayment::query()->create([
                'loan_rec_id' => (int) $row->loan_rec_id,
                'member_rec_id' => (int) $row->member_rec_id,
                'member_icuno' => (string) $row->icuno,
                'member_name' => (string) $row->icunm,
                'payment_date' => $now->format('Y-m-d'),
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

    private function actorName(int $userId): string
    {
        $name = DB::connection('run')->table('sysitc_users')
            ->where('rec_id', $userId)
            ->value('account_nm');

        return (string) ($name ?: 'User-'.$userId);
    }
}
