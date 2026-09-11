<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan historical simpanan & penarikan simpanan manual.
 *
 * Jalur terpisah dari setoran wajib bulanan: boleh lebih dari satu transaksi
 * per anggota per periode, langsung tercatat ke icu_transaction (trncd 19)
 * tanpa approval. Metadata disimpan di tabel sendiri agar tidak bentrok
 * dengan aturan satu-setoran-per-periode di coop_savings.
 */
class ManualSavingsService
{
    public function __construct(private readonly SavingsService $savings) {}

    /**
     * Simpanan manual: debit ke icu_transaction + metadata coop_manual_savings.
     */
    public function postSavings(CooperativeMember $member, string $period, string $trndt, int $amount, string $method, ?string $notes, int $userId): string
    {
        $trnno = DB::connection('mysql')->transaction(function () use ($member, $period, $trndt, $amount, $method, $notes): string {
            $trnno = $this->generateTrnno('SAV', $trndt, 'SAV-%');

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => SavingsService::TRNCD_SAVINGS,
                'trnno' => $trnno,
                'trndt' => $trndt,
                'icu_rec_id' => $member->rec_id,
                'empno' => (string) $member->refno,
                'descr' => mb_substr('Simpanan Manual '.$member->icuno, 0, 50),
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

            DB::connection('run')->table('coop_manual_savings')->insert([
                'member_rec_id' => $member->rec_id,
                'member_icuno' => $member->icuno,
                'member_name' => $member->icunm,
                'pprd' => $period,
                'trndt' => $trndt,
                'amount' => $amount,
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
     * coop_manual_withdrawals, langsung berstatus selesai tanpa approval.
     *
     * @param  array{bank_bnkcd?: string, bank_accnm?: string, bank_accno?: string}|null  $bank
     */
    public function postWithdrawal(CooperativeMember $member, string $period, string $trndt, int $amount, ?array $bank, ?string $reason, int $userId): string
    {
        $trnno = DB::connection('mysql')->transaction(function () use ($member, $period, $trndt, $amount, $bank, $reason): string {
            $trnno = $this->generateTrnno('WDR', $trndt, 'WDR-%');

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => SavingsService::TRNCD_SAVINGS,
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

            DB::connection('run')->table('coop_manual_withdrawals')->insert([
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

    private function generateTrnno(string $prefix, string $trndt, string $likePattern): string
    {
        $date = CarbonImmutable::parse($trndt);
        $sequence = LoanPostingService::nextSequence('icu_transaction', 'trnno', $likePattern);

        return LoanPostingService::formatLegacyTrnno($prefix, $date, $sequence);
    }
}