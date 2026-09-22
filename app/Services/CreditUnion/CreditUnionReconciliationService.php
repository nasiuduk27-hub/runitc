<?php

namespace App\Services\CreditUnion;

use App\Models\CreditUnion\CreditUnionMember;
use App\Models\CreditUnion\CreditUnionReconciliation;
use App\Models\CreditUnion\CreditUnionReconciliationAction;
use App\Models\CreditUnion\CreditUnionTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditUnionReconciliationService
{
    public const SCOPE_BANK = 'bank_monthly';
    public const SCOPE_SAVINGS = 'savings';
    public const SCOPE_LOAN = 'loan';

    public const SCOPES = [
        self::SCOPE_BANK => 'Bank bulanan',
        self::SCOPE_SAVINGS => 'Saldo simpanan anggota',
        self::SCOPE_LOAN => 'Outstanding pinjaman',
    ];

    public function snapshot(string $scope, ?string $period = null, ?int $memberId = null): array
    {
        return match ($scope) {
            self::SCOPE_BANK => $this->bankSnapshot($period ?: CreditUnionPeriod::current()),
            self::SCOPE_SAVINGS => $this->memberSnapshot($scope, $memberId),
            self::SCOPE_LOAN => $this->memberSnapshot($scope, $memberId),
            default => throw new InvalidArgumentException('Scope rekonsiliasi tidak valid.'),
        };
    }

    /** @return array{tagihan:int, diterima:int, belum_diterima:int, detail_posted:int} */
    public function bankSummary(string $period): array
    {
        $snapshot = $this->bankSnapshot($period);

        return [
            'tagihan' => (int) $snapshot['expected'],
            'diterima' => (int) $snapshot['actual'],
            'belum_diterima' => max(0, (int) $snapshot['expected'] - (int) $snapshot['actual']),
            'detail_posted' => (int) $snapshot['detail_posted'],
        ];
    }

    public function create(array $data, int $userId, string $actorName): CreditUnionReconciliation
    {
        $scope = (string) $data['scope'];
        $snapshot = $this->snapshot($scope, $data['period'] ?? null, $data['member_rec_id'] ?? null);
        $expected = (int) $data['expected_amount'];
        $actual = (int) $snapshot['actual'];

        if ($expected === $actual) {
            throw new InvalidArgumentException('Tidak ada selisih yang perlu direkonsiliasi.');
        }

        $member = null;
        if (! empty($data['member_rec_id'])) {
            $member = CreditUnionMember::query()->findOrFail((int) $data['member_rec_id']);
        }

        return DB::connection('run')->transaction(function () use ($data, $scope, $snapshot, $expected, $actual, $member, $userId, $actorName): CreditUnionReconciliation {
            $reconciliation = CreditUnionReconciliation::query()->create([
                'ref_no' => $this->nextRefNo(),
                'scope' => $scope,
                'period' => $data['period'] ?: null,
                'member_rec_id' => $member?->rec_id,
                'member_icuno' => $member?->icuno,
                'member_name' => $member?->icunm,
                'target_type' => $snapshot['target_type'] ?? null,
                'target_ref' => $snapshot['target_ref'] ?? null,
                'expected_amount' => $expected,
                'actual_amount' => $actual,
                'difference_amount' => $expected - $actual,
                'direction' => ($expected - $actual) > 0 ? 'D' : 'C',
                'reason' => trim((string) $data['reason']),
                'snapshot_json' => $snapshot,
                'status' => CreditUnionReconciliation::STATUS_SUBMITTED,
                'maker_user_id' => $userId,
            ]);

            $this->action($reconciliation, 'submitted', $reconciliation->reason, $userId, $actorName);
            $this->audit($reconciliation, 'cu.reconciliation.submitted', $userId);

            return $reconciliation;
        });
    }

    public function approve(CreditUnionReconciliation $reconciliation, int $userId, string $actorName, string $note): void
    {
        if ($reconciliation->status !== CreditUnionReconciliation::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Kasus ini sudah diproses.');
        }
        if ($reconciliation->maker_user_id === $userId) {
            throw new InvalidArgumentException('Maker tidak boleh menyetujui koreksinya sendiri.');
        }

        // Klaim atomik sebelum menyentuh database legacy; request kedua berhenti di sini.
        $claimed = CreditUnionReconciliation::query()
            ->whereKey($reconciliation->id)
            ->where('status', CreditUnionReconciliation::STATUS_SUBMITTED)
            ->update(['status' => CreditUnionReconciliation::STATUS_PROCESSING]);

        if ($claimed === 0) {
            throw new InvalidArgumentException('Kasus ini sedang atau sudah diproses.');
        }

        try {
            $current = $this->snapshot($reconciliation->scope, $reconciliation->period, $reconciliation->member_rec_id);
            if ((int) $current['actual'] !== (int) $reconciliation->actual_amount) {
                throw new InvalidArgumentException('Data berubah sejak pengajuan. Silakan scan dan ajukan ulang.');
            }

            $trnno = DB::connection('mysql')->transaction(function () use ($reconciliation): ?string {
                return match ($reconciliation->scope) {
                    self::SCOPE_BANK => $this->postBankCorrection($reconciliation),
                    self::SCOPE_SAVINGS => $this->postSavingsCorrection($reconciliation),
                    self::SCOPE_LOAN => $this->correctLoanCache($reconciliation),
                    default => throw new InvalidArgumentException('Scope rekonsiliasi tidak valid.'),
                };
            });
        } catch (\Throwable $exception) {
            CreditUnionReconciliation::query()
                ->whereKey($reconciliation->id)
                ->where('status', CreditUnionReconciliation::STATUS_PROCESSING)
                ->update(['status' => CreditUnionReconciliation::STATUS_SUBMITTED]);

            if ($exception instanceof InvalidArgumentException) {
                throw $exception;
            }

            throw new InvalidArgumentException('Posting koreksi gagal: '.$exception->getMessage());
        }

        $reconciliation->update([
            'status' => CreditUnionReconciliation::STATUS_APPROVED,
            'checker_user_id' => $userId,
            'checked_at' => now(),
            'decision_note' => trim($note),
            'correction_trnno' => $trnno,
        ]);
        $this->action($reconciliation, 'approved', $note, $userId, $actorName);
        $this->audit($reconciliation, 'cu.reconciliation.approved', $userId, ['correction_trnno' => $trnno]);
    }

    public function reject(CreditUnionReconciliation $reconciliation, int $userId, string $actorName, string $note): void
    {
        if ($reconciliation->status !== CreditUnionReconciliation::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Kasus ini sudah diproses.');
        }
        if ($reconciliation->maker_user_id === $userId) {
            throw new InvalidArgumentException('Maker tidak boleh menolak koreksinya sendiri.');
        }

        $reconciliation->update([
            'status' => CreditUnionReconciliation::STATUS_REJECTED,
            'checker_user_id' => $userId,
            'checked_at' => now(),
            'decision_note' => trim($note),
        ]);
        $this->action($reconciliation, 'rejected', $note, $userId, $actorName);
        $this->audit($reconciliation, 'cu.reconciliation.rejected', $userId);
    }

    private function bankSnapshot(string $period): array
    {
        $tagihan = (int) DB::connection('mysql')->table('icu_mtrx2hrd')->where('pprdk', $period)->sum('trx_amt');
        $refs = DB::connection('mysql')->table('icu_mtrx2hrd')->where('pprdk', $period)->pluck('trxno');
        $diterima = $refs->isEmpty() ? 0 : (int) DB::connection('mysql')->table('icu_bank_trx')
            ->whereIn('req_frm_trxno', $refs->all())->where('dbocr', 'D')->sum('amount');
        $posted = (int) CreditUnionTransaction::query()->where('pprd', $period)
            ->whereIn('trncd', [SavingsService::TRNCD_SAVINGS, LoanPaymentService::getInstallmentTrncd()])->sum('amount');

        return [
            'expected' => $tagihan,
            'actual' => $diterima,
            'detail_posted' => $posted,
            'target_type' => 'icu_mtrx2hrd',
            'target_ref' => $period,
        ];
    }

    private function memberSnapshot(string $scope, ?int $memberId): array
    {
        if (! $memberId) {
            throw new InvalidArgumentException('Anggota wajib dipilih untuk rekonsiliasi ini.');
        }

        $member = CreditUnionMember::query()->findOrFail($memberId);
        $actual = $scope === self::SCOPE_SAVINGS
            ? app(SavingsService::class)->balance($member->rec_id)
            : (int) $member->outstanding;

        return [
            'expected' => $actual,
            'actual' => $actual,
            'target_type' => 'icu_member',
            'target_ref' => (string) $member->rec_id,
        ];
    }

    private function postBankCorrection(CreditUnionReconciliation $reconciliation): string
    {
        $trnno = LoanPostingService::formatLegacyTrnno('RCV', CarbonImmutable::now(), LoanPostingService::nextSequence('icu_bank_trx', 'trnno', 'RCV-%'));
        DB::connection('mysql')->table('icu_bank_trx')->insert([
            'pprdk' => $reconciliation->period ?: CreditUnionPeriod::current(),
            'trnno' => $trnno,
            'trndt' => now()->toDateString(),
            'req_frm_trxno' => '',
            'dbocr' => $reconciliation->direction,
            'icu_rec_id' => 0,
            'descr' => 'Koreksi Rekonsiliasi '.$reconciliation->ref_no,
            'amount' => abs($reconciliation->difference_amount),
            'statrec' => 1,
            'edit_enable' => 0,
            'notes' => mb_substr($reconciliation->reason, 0, 50),
            'confno' => '',
            'confdt' => now()->toDateString(),
            'lupd' => now(),
            'entusr' => 'RUN',
        ]);

        return $trnno;
    }

    private function postSavingsCorrection(CreditUnionReconciliation $reconciliation): string
    {
        $trnno = LoanPostingService::formatLegacyTrnno('SAV', CarbonImmutable::now(), LoanPostingService::nextSequence('icu_transaction', 'trnno', 'SAV-%'));
        DB::connection('mysql')->table('icu_transaction')->insert([
            'pprd' => $reconciliation->period ?: CreditUnionPeriod::current(),
            'trncd' => $reconciliation->direction === 'D' ? SavingsService::TRNCD_ONE_TIME_SAVING : SavingsService::TRNCD_WITHDRAWAL,
            'trnno' => $trnno,
            'trndt' => now()->toDateString(),
            'icu_rec_id' => $reconciliation->member_rec_id,
            'empno' => '',
            'descr' => 'Koreksi Rekonsiliasi '.$reconciliation->ref_no,
            'dbocr' => $reconciliation->direction,
            'basic_amt' => abs($reconciliation->difference_amount),
            'int_amt' => 0,
            'amount' => abs($reconciliation->difference_amount),
            'notes' => mb_substr($reconciliation->reason, 0, 200),
            'entdt' => now(),
            'lupd' => now(),
            'entusr' => 'RUN',
            'refno' => '',
            'statrec' => 1,
            'statrec2' => 0,
        ]);

        return $trnno;
    }

    private function correctLoanCache(CreditUnionReconciliation $reconciliation): ?string
    {
        DB::connection('mysql')->table('icu_member')->where('rec_id', $reconciliation->member_rec_id)
            ->update(['outstanding' => $reconciliation->expected_amount, 'lupd' => now()]);

        return null;
    }

    private function nextRefNo(): string
    {
        $sequence = (int) DB::connection('run')->table('cu_reconciliations')
            ->where('ref_no', 'like', 'REC-%')
            ->lockForUpdate()
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(ref_no, '-', -1) AS UNSIGNED)), 0) AS last_seq")
            ->value('last_seq') + 1;

        return LoanPostingService::formatLegacyTrnno('REC', CarbonImmutable::now(), $sequence);
    }

    private function action(CreditUnionReconciliation $reconciliation, string $action, ?string $note, int $userId, string $actorName): void
    {
        CreditUnionReconciliationAction::query()->create([
            'reconciliation_id' => $reconciliation->id,
            'action' => $action,
            'note' => $note,
            'actor_user_id' => $userId,
            'actor_name' => $actorName,
        ]);
    }

    private function audit(CreditUnionReconciliation $reconciliation, string $action, int $userId, array $metadata = []): void
    {
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => $userId,
            'action' => $action,
            'target_type' => 'cu_reconciliations',
            'target_id' => $reconciliation->id,
            'metadata_json' => json_encode(array_merge(['ref_no' => $reconciliation->ref_no], $metadata), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
