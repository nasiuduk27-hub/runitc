<?php

namespace App\Services\CreditUnion;

use App\Models\CreditUnion\CreditUnionMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan historical transaksi manual: simpanan (bulanan 19 / sekali 18),
 * penarikan (22), dan angsuran (20).
 *
 * Jalur terpisah dari proses reguler: boleh lebih dari satu transaksi per
 * anggota per periode, langsung tercatat ke icu_transaction tanpa approval.
 * Metadata simpanan/penarikan disimpan di tabel RUNITC sendiri.
 */
class ManualSavingsService
{
    public const SAVING_TYPE_MONTHLY = 'monthly';

    public const SAVING_TYPE_ONE_TIME = 'one_time';

    public function __construct(private readonly SavingsService $savings) {}

    /**
     * Simpanan manual: debit ke icu_transaction + metadata cu_manual_savings.
     */
    public function postSavings(CreditUnionMember $member, string $period, string $trndt, int $amount, string $method, ?string $notes, int $userId, string $savingType = self::SAVING_TYPE_MONTHLY): string
    {
        $isOneTime = $savingType === self::SAVING_TYPE_ONE_TIME;
        $trncd = $isOneTime ? SavingsService::TRNCD_ONE_TIME_SAVING : SavingsService::TRNCD_SAVINGS;
        $label = $isOneTime ? 'Simpanan Sekali' : 'Simpanan Manual';

        $trnno = LoanPostingService::transactionWithTrnnoRetry(function () use ($member, $period, $trndt, $amount, $method, $notes, $userId, $savingType, $trncd, $label): string {
            $trnno = $this->generateTrnno('SAV', $trndt, 'SAV-%');

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => $trncd,
                'trnno' => $trnno,
                'trndt' => $trndt,
                'icu_rec_id' => $member->rec_id,
                'empno' => (string) $member->refno,
                'descr' => mb_substr($label.' '.$member->icuno, 0, 50),
                'dbocr' => 'D',
                'basic_amt' => $amount,
                'int_amt' => 0,
                'amount' => $amount,
                'notes' => mb_substr((string) ($notes ?? ''), 0, 200),
                'entdt' => now(),
                'lupd' => now(),
                'entusr' => 'RUN',
                'refno' => '',
                'statrec' => 1,
                'statrec2' => 0,
            ]);

            DB::connection('run')->table('cu_manual_savings')->insert([
                'member_rec_id' => $member->rec_id,
                'member_icuno' => $member->icuno,
                'member_name' => $member->icunm,
                'pprd' => $period,
                'trndt' => $trndt,
                'amount' => $amount,
                'saving_type' => $savingType,
                'method' => $method,
                'notes' => $notes === null ? null : mb_substr($notes, 0, 200),
                'savings_trnno' => $trnno,
                'maker_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $trnno;
        });

        return $trnno;
    }

    /**
     * Penarikan manual historical: kredit ke icu_transaction + metadata
     * cu_manual_withdrawals, langsung berstatus selesai tanpa approval.
     *
     * @param  array{bank_bnkcd?: string, bank_accnm?: string, bank_accno?: string}|null  $bank
     */
    public function postWithdrawal(CreditUnionMember $member, string $period, string $trndt, int $amount, ?array $bank, ?string $reason, int $userId): string
    {
        $trnno = LoanPostingService::transactionWithTrnnoRetry(function () use ($member, $period, $trndt, $amount, $bank, $reason, $userId): string {
            $trnno = $this->generateTrnno('WDR', $trndt, 'WDR-%');

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => SavingsService::TRNCD_WITHDRAWAL,
                'trnno' => $trnno,
                'trndt' => $trndt,
                'icu_rec_id' => $member->rec_id,
                'empno' => '',
                'descr' => mb_substr('Penarikan Manual '.$member->icuno, 0, 50),
                'dbocr' => 'C',
                'basic_amt' => $amount,
                'int_amt' => 0,
                'amount' => $amount,
                'notes' => mb_substr((string) ($reason ?? ''), 0, 200),
                'entdt' => now(),
                'lupd' => now(),
                'entusr' => 'RUN',
                'refno' => '',
                'statrec' => 1,
                'statrec2' => 0,
            ]);

            DB::connection('run')->table('cu_manual_withdrawals')->insert([
                'member_rec_id' => $member->rec_id,
                'member_icuno' => $member->icuno,
                'member_name' => $member->icunm,
                'pprd' => $period,
                'trndt' => $trndt,
                'amount' => $amount,
                'bank_account' => $bank['bank_account'] ?? null,
                'bank_bnkcd' => $bank['bank_bnkcd'] ?? null,
                'bank_accnm' => $bank['bank_accnm'] ?? null,
                'bank_accno' => $bank['bank_accno'] ?? null,
                'reason' => $reason === null ? null : mb_substr($reason, 0, 200),
                'withdrawal_trnno' => $trnno,
                'maker_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $trnno;
        });

        return $trnno;
    }

    /**
     * Pembayaran angsuran historical: hanya mencatat icu_transaction (trncd 20),
     * tanpa menyentuh jadwal icu_dloan/icu_mloan.
     */
    public function postLoanPayment(CreditUnionMember $member, string $period, string $trndt, int $amount, ?string $notes, int $userId): string
    {
        $trnno = LoanPostingService::transactionWithTrnnoRetry(function () use ($member, $period, $trndt, $amount, $notes): string {
            $trnno = $this->generateTrnno('PMT', $trndt, 'PMT-%');

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => LoanPaymentService::getInstallmentTrncd(),
                'trnno' => $trnno,
                'trndt' => $trndt,
                'icu_rec_id' => $member->rec_id,
                'empno' => (string) $member->refno,
                'descr' => mb_substr('Angsuran Manual '.$member->icuno, 0, 50),
                'dbocr' => 'D',
                'basic_amt' => $amount,
                'int_amt' => 0,
                'amount' => $amount,
                'notes' => mb_substr((string) ($notes ?? ''), 0, 200),
                'entdt' => now(),
                'lupd' => now(),
                'entusr' => 'RUN',
                'refno' => '',
                'statrec' => 1,
                'statrec2' => 0,
            ]);

            return $trnno;
        });

        return $trnno;
    }

    private function generateTrnno(string $prefix, string $trndt, string $likePattern): string
    {
        $date = CarbonImmutable::parse($trndt);
        $sequence = LoanPostingService::nextSequence('icu_transaction', 'trnno', $likePattern);

        return LoanPostingService::formatLegacyTrnno($prefix, $date, $sequence);
    }
}
