<?php

namespace App\Services\Cooperative;

use App\Models\Cooperative\CooperativeMember;
use App\Models\Cooperative\CooperativeSavings;
use App\Models\Cooperative\CooperativeSavingsAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Setoran simpanan bulanan anggota (Simpanan Bulanan, kode master subcode 19).
 *
 * Alur batch bulanan (admin-only, auto-post): setor dicatat langsung ke
 * icu_transaction (dbocr D, trncd 19, pprd = periode terpilih) dan ditulis
 * sebagai rekaman RUNITC coop_savings + coop_savings_actions untuk audit.
 *
 * Aturan pembayaran memakai autodebit potong gaji tanggal 28:
 * - method = potong_gaji
 * - trndt = tanggal 28 bulan terpilih
 */
class SavingsService
{
    public const TRNCD_SAVINGS = '19';

    public const METHOD_POTONG_GAJI = 'potong_gaji';

    public const STATUS_POSTED = 'posted';

    /**
     * Tanggal 28 pada periode YYYYMM (autodebit).
     */
    public function paymentDate(string $period): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Ym', $period)->day(28);
    }

    /**
     * Posting setoran simpanan bulanan. Mengembalikan nomor transaksi SAV yang dibuat.
     *
     * @throws InvalidArgumentException bila setoran sudah tercatat untuk bulan itu
     */
    public function postSavings(CooperativeMember $member, string $period, int $userId): string
    {
        if ($member->swajib <= 0) {
            throw new InvalidArgumentException('Anggota ini tidak memiliki simpanan wajib bulanan.');
        }

        $existing = CooperativeSavings::query()
            ->where('member_rec_id', $member->rec_id)
            ->where('pprd', $period)
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('Setoran simpanan bulan ini sudah tercatat.');
        }

        $amount = (int) $member->swajib;

        $trnno = DB::connection('mysql')->transaction(function () use ($member, $period, $amount): string {
            $savingsTrnno = $this->generateTrnno();

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => self::TRNCD_SAVINGS,
                'trnno' => $savingsTrnno,
                'trndt' => $this->paymentDate($period)->toDateString(),
                'icu_rec_id' => $member->rec_id,
                'empno' => (string) $member->refno,
                'descr' => mb_substr('Simpanan Bulanan '.$member->icuno, 0, 50),
                'dbocr' => 'D',
                'basic_amt' => $amount,
                'int_amt' => 0,
                'amount' => $amount,
                'notes' => '',
                'entdt' => now(),
                'lupd' => now(),
                'entusr' => 'RUN',
                'refno' => '',
                'statrec' => 1,
                'statrec2' => 0,
            ]);

            return $savingsTrnno;
        });

        $actorname = $this->actorName($userId);

        $savings = CooperativeSavings::query()->create([
            'member_rec_id' => $member->rec_id,
            'member_icuno' => $member->icuno,
            'member_name' => $member->icunm,
            'pprd' => $period,
            'amount' => $amount,
            'method' => self::METHOD_POTONG_GAJI,
            'notes' => null,
            'status' => self::STATUS_POSTED,
            'savings_trnno' => $trnno,
            'maker_user_id' => $userId,
        ]);

        CooperativeSavingsAction::query()->create([
            'savings_id' => $savings->id,
            'action' => CooperativeSavingsAction::ACTION_POSTED,
            'note' => 'Auto-post sebagai '.$trnno,
            'actor_user_id' => $userId,
            'actor_name' => $actorname,
        ]);

        return $trnno;
    }

    /**
     * Total simpanan & pokok angsuran (inflow) per anggota untuk satu periode,
     * dihitung langsung dari icu_transaction (tanpa kolom tersimpan).
     *
     * @return array<string, array{savings: int, loan_principal: int}> keyed by icu_rec_id
     */
    public function monthlyTotals(string $period): array
    {
        $rows = DB::connection('mysql')->table('icu_transaction')
            ->where('pprd', $period)
            ->whereIn('trncd', [self::TRNCD_SAVINGS, LoanPaymentService::getInstallmentTrncd()])
            ->groupBy('icu_rec_id')
            ->get([
                'icu_rec_id',
                DB::raw("SUM(CASE WHEN trncd = '".self::TRNCD_SAVINGS."' THEN amount ELSE 0 END) AS savings"),
                DB::raw("SUM(CASE WHEN trncd = '".LoanPaymentService::getInstallmentTrncd()."' THEN basic_amt ELSE 0 END) AS loan_principal"),
            ]);

        return collect($rows)->keyBy('icu_rec_id')->map(fn ($row): array => [
            'savings' => (int) $row->savings,
            'loan_principal' => (int) $row->loan_principal,
        ])->all();
    }

    private function generateTrnno(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        $sequence = LoanPostingService::nextSequence('icu_transaction', 'trnno', 'SAV-%');

        return LoanPostingService::formatLegacyTrnno('SAV', $now, $sequence);
    }

    private function actorName(int $userId): string
    {
        if ($userId <= 0) {
            return 'Unknown';
        }

        return (string) (DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->value('account_nm') ?: 'User-'.$userId);
    }
}
