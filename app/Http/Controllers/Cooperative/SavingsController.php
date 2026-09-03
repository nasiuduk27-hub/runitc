<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeSavingsWithdrawal;
use App\Models\Cooperative\CooperativeSavingsWithdrawalAction;
use App\Models\Cooperative\CooperativeTransaction;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\LoanPostingService;
use App\Services\Cooperative\SavingsService;
use App\Services\Cooperative\CooperativeSettingsService;
use App\Support\CooperativeAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SavingsController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $member = CooperativeAccess::memberForUser($userId);
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $showPersonalView = ! $isAdmin || $request->query('view') === 'mine';

        if ($member === null) {
            return view('cooperative.savings.index', [
                'member' => null,
                'isAdmin' => $isAdmin,
                'showPersonalView' => $showPersonalView,
                'totals' => ['debit' => 0, 'credit' => 0, 'balance' => 0, 'debit_count' => 0, 'credit_count' => 0],
                'availableBalance' => 0,
                'transactions' => collect(),
                'withdrawals' => $this->withdrawals(null, $isAdmin && ! $showPersonalView),
                'withdrawalStats' => $this->withdrawalStats(),
                'defaultBank' => $this->primaryBank($userId),
                'bankOptions' => $this->bankOptions(),
            ]);
        }

        $totals = $this->totals($member->rec_id);

        return view('cooperative.savings.index', [
            'member' => $member,
            'isAdmin' => $isAdmin,
            'showPersonalView' => $showPersonalView,
            'totals' => $totals,
            'availableBalance' => max(0, $totals['balance'] - $this->pendingWithdrawalTotal($member->rec_id) - CooperativeSettingsService::minimumSavingsBalance()),
            'transactions' => collect(),
            'withdrawals' => $this->withdrawals($member->rec_id, $isAdmin && ! $showPersonalView),
            'withdrawalStats' => $this->withdrawalStats(),
            'defaultBank' => $this->primaryBank($userId),
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function history(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $member = CooperativeAccess::memberForUser($userId);
        $tab = in_array($request->query('tab'), ['all', 'savings', 'withdraw'], true)
            ? (string) $request->query('tab')
            : 'all';

        return view('cooperative.savings.history', [
            'member' => $member,
            'tab' => $tab,
            'transactions' => $member !== null && in_array($tab, ['all', 'savings'], true)
                ? $this->transactions($member->rec_id, 25)
                : new LengthAwarePaginator([], 0, 25, 1, ['path' => $request->url(), 'pageName' => 'transactions_page']),
            'withdrawals' => $member !== null && in_array($tab, ['all', 'withdraw'], true)
                ? $this->withdrawals($member->rec_id, false, 15)
                : new LengthAwarePaginator([], 0, 15, 1, ['path' => $request->url(), 'pageName' => 'withdrawals_page']),
            'bankOptions' => $this->bankOptions(),
            'minimumSavingsBalance' => CooperativeSettingsService::minimumSavingsBalance(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $userId = $this->currentUserId($request);
        $member = CooperativeAccess::memberForUser($userId);

        if ($member === null) {
            return back()->withErrors(['swajib' => 'Akun Anda belum tersinkron ke anggota koperasi.']);
        }

        $data = $request->validate([
            'swajib' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $oldValue = (int) $member->swajib;
        $newValue = (int) $data['swajib'];

        if ($oldValue === $newValue) {
            return back()->with('success', 'Nominal simpanan wajib tidak berubah.');
        }

        DB::connection('mysql')->table('icu_member')
            ->where('rec_id', $member->rec_id)
            ->where('itc_user_id', $userId)
            ->update([
                'swajib' => $newValue,
                'lupd' => now(),
            ]);

        $this->writeAudit($request, $member->rec_id, $oldValue, $newValue);

        return back()->with('success', 'Simpanan wajib bulanan berhasil diperbarui. Nominal baru berlaku untuk setoran yang belum diposting.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $userId = $this->currentUserId($request);
        $member = CooperativeAccess::memberForUser($userId);

        if ($member === null) {
            return back()->withErrors(['amount' => 'Akun Anda belum tersinkron ke anggota koperasi.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_no' => ['nullable', 'string', 'max:80'],
        ]);

        $balance = $this->totals($member->rec_id)['balance'];
        $minimumBalance = CooperativeSettingsService::minimumSavingsBalance();
        $availableBalance = max(0, $balance - $this->pendingWithdrawalTotal($member->rec_id) - $minimumBalance);
        $amount = (int) $data['amount'];

        if ($amount > $availableBalance) {
            return back()->withInput()->withErrors(['amount' => 'Nominal penarikan melebihi saldo yang dapat ditarik.']);
        }

        $bankSnapshot = $this->resolveBankSnapshot($data, $userId);

        if ($bankSnapshot === null) {
            return back()->withInput()->withErrors(['bank_code' => 'Data rekening tujuan wajib lengkap.']);
        }

        $withdrawal = DB::connection('run')->transaction(function () use ($member, $bankSnapshot, $amount, $userId): CooperativeSavingsWithdrawal {
            $withdrawal = CooperativeSavingsWithdrawal::query()->create([
                'member_rec_id' => $member->rec_id,
                'member_icuno' => $member->icuno,
                'member_name' => $member->icunm,
                'amount' => $amount,
                'bank_account' => $this->formatBankSnapshot($bankSnapshot),
                'bank_bnkcd' => $bankSnapshot['bank_bnkcd'],
                'bank_accnm' => $bankSnapshot['bank_accnm'],
                'bank_accno' => $bankSnapshot['bank_accno'],
                'reason' => null,
                'status' => CooperativeSavingsWithdrawal::STATUS_SUBMITTED,
                'maker_user_id' => $userId,
            ]);

            CooperativeSavingsWithdrawalAction::query()->create([
                'withdrawal_id' => $withdrawal->id,
                'action' => CooperativeSavingsWithdrawalAction::ACTION_SUBMITTED,
                'note' => null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            return $withdrawal;
        });

        $this->writeEventAudit($request, 'cooperative.savings_withdrawal.submitted', 'coop_savings_withdrawal', $withdrawal->id, [
            'member_rec_id' => $member->rec_id,
            'amount' => $amount,
        ]);

        return back()->with('success', 'Pengajuan penarikan simpanan tercatat dan menunggu persetujuan admin.');
    }

    public function decideWithdrawal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:approve,reject,cancel'],
        ]);

        $userId = $this->currentUserId($request);
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $withdrawal = CooperativeSavingsWithdrawal::query()->findOrFail((int) $data['id']);

        if ($withdrawal->status !== CooperativeSavingsWithdrawal::STATUS_SUBMITTED) {
            return back()->withErrors(['decision' => 'Pengajuan ini sudah diproses.']);
        }

        if ($data['decision'] === 'cancel') {
            if ($withdrawal->maker_user_id !== $userId) {
                return back()->withErrors(['decision' => 'Hanya pengaju yang dapat membatalkan pengajuan.']);
            }
        } else {
            if (! $isAdmin) {
                return back()->withErrors(['decision' => 'Hanya admin koperasi yang dapat menyetujui atau menolak penarikan.']);
            }

            if ($data['decision'] === 'approve' && $withdrawal->maker_user_id === $userId) {
                return back()->withErrors(['decision' => 'Pengaju tidak dapat menyetujui penarikannya sendiri.']);
            }
        }

        if ($data['decision'] === 'approve') {
            $balance = $this->totals($withdrawal->member_rec_id)['balance'];
            $minimumBalance = CooperativeSettingsService::minimumSavingsBalance();
            if ($withdrawal->amount > $balance - $minimumBalance) {
                return back()->withErrors(['decision' => 'Nominal penarikan melebihi saldo yang dapat ditarik (saldo dikurangi saldo minimum mengendap).']);
            }

            $trnno = $this->postWithdrawalTransaction($withdrawal);
            $status = CooperativeSavingsWithdrawal::STATUS_APPROVED;
            $action = CooperativeSavingsWithdrawalAction::ACTION_APPROVED;
            $note = 'Diposting sebagai '.$trnno;
        } elseif ($data['decision'] === 'reject') {
            $trnno = null;
            $status = CooperativeSavingsWithdrawal::STATUS_REJECTED;
            $action = CooperativeSavingsWithdrawalAction::ACTION_REJECTED;
            $note = null;
        } else {
            $trnno = null;
            $status = CooperativeSavingsWithdrawal::STATUS_CANCELLED;
            $action = CooperativeSavingsWithdrawalAction::ACTION_CANCELLED;
            $note = null;
        }

        DB::connection('run')->transaction(function () use ($withdrawal, $status, $trnno, $userId, $note, $action): void {
            $withdrawal->forceFill([
                'status' => $status,
                'withdrawal_trnno' => $trnno,
                'checker_user_id' => $userId,
                'checked_at' => now(),
                'decision_note' => $note,
            ])->save();

            CooperativeSavingsWithdrawalAction::query()->create([
                'withdrawal_id' => $withdrawal->id,
                'action' => $action,
                'note' => $note,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);
        });

        $this->writeEventAudit($request, 'cooperative.savings_withdrawal.'.$action, 'coop_savings_withdrawal', $withdrawal->id, [
            'to_status' => $status,
            'amount' => $withdrawal->amount,
            'withdrawal_trnno' => $trnno,
        ]);

        return back()->with('success', 'Keputusan penarikan simpanan berhasil dicatat.');
    }

    /**
     * @return array{debit: int, credit: int, balance: int, debit_count: int, credit_count: int}
     */
    private function totals(int $memberRecId): array
    {
        $row = CooperativeTransaction::query()
            ->where('icu_rec_id', $memberRecId)
            ->where('trncd', SavingsService::TRNCD_SAVINGS)
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'D' THEN amount ELSE 0 END), 0) AS debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'C' THEN amount ELSE 0 END), 0) AS credit")
            ->selectRaw("SUM(CASE WHEN dbocr = 'D' THEN 1 ELSE 0 END) AS debit_count")
            ->selectRaw("SUM(CASE WHEN dbocr = 'C' THEN 1 ELSE 0 END) AS credit_count")
            ->first();

        $debit = (int) ($row->debit ?? 0);
        $credit = (int) ($row->credit ?? 0);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
            'debit_count' => (int) ($row->debit_count ?? 0),
            'credit_count' => (int) ($row->credit_count ?? 0),
        ];
    }

    private function transactions(int $memberRecId, int $perPage = 15)
    {
        return CooperativeTransaction::query()
            ->where('icu_rec_id', $memberRecId)
            ->where('trncd', SavingsService::TRNCD_SAVINGS)
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->paginate($perPage, ['*'], 'transactions_page')
            ->withQueryString();
    }

    private function withdrawals(?int $memberRecId, bool $isAdmin, int $perPage = 10)
    {
        if (! Schema::connection('run')->hasTable('coop_savings_withdrawals')) {
            return new LengthAwarePaginator([], 0, 10, 1, [
                'path' => request()->url(),
                'pageName' => 'withdrawals_page',
            ]);
        }

        $withdrawals = CooperativeSavingsWithdrawal::query()
            ->when(! $isAdmin, fn ($query) => $query->where('member_rec_id', $memberRecId ?? 0))
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'withdrawals_page')
            ->withQueryString();

        $withdrawals->getCollection()->transform(function (CooperativeSavingsWithdrawal $withdrawal): CooperativeSavingsWithdrawal {
            $balance = $this->totals($withdrawal->member_rec_id)['balance'];
            $withdrawal->setAttribute('current_balance', $balance);
            $withdrawal->setAttribute('after_withdrawal_balance', $balance - $withdrawal->amount);

            return $withdrawal;
        });

        return $withdrawals;
    }

    /**
     * @return array{pending_count: int, pending_amount: int, approved_month_amount: int, rejected_month_count: int}
     */
    private function withdrawalStats(): array
    {
        if (! Schema::connection('run')->hasTable('coop_savings_withdrawals')) {
            return ['pending_count' => 0, 'pending_amount' => 0, 'approved_month_amount' => 0, 'rejected_month_count' => 0];
        }

        $monthStart = now()->startOfMonth();

        return [
            'pending_count' => (int) CooperativeSavingsWithdrawal::query()
                ->where('status', CooperativeSavingsWithdrawal::STATUS_SUBMITTED)
                ->count(),
            'pending_amount' => (int) CooperativeSavingsWithdrawal::query()
                ->where('status', CooperativeSavingsWithdrawal::STATUS_SUBMITTED)
                ->sum('amount'),
            'approved_month_amount' => (int) CooperativeSavingsWithdrawal::query()
                ->where('status', CooperativeSavingsWithdrawal::STATUS_APPROVED)
                ->where('checked_at', '>=', $monthStart)
                ->sum('amount'),
            'rejected_month_count' => (int) CooperativeSavingsWithdrawal::query()
                ->where('status', CooperativeSavingsWithdrawal::STATUS_REJECTED)
                ->where('checked_at', '>=', $monthStart)
                ->count(),
        ];
    }

    private function pendingWithdrawalTotal(int $memberRecId): int
    {
        if (! Schema::connection('run')->hasTable('coop_savings_withdrawals')) {
            return 0;
        }

        return (int) CooperativeSavingsWithdrawal::query()
            ->where('member_rec_id', $memberRecId)
            ->where('status', CooperativeSavingsWithdrawal::STATUS_SUBMITTED)
            ->sum('amount');
    }

    private function postWithdrawalTransaction(CooperativeSavingsWithdrawal $withdrawal): string
    {
        return DB::connection('mysql')->transaction(function () use ($withdrawal): string {
            $trnno = $this->generateWithdrawalTrnno();
            $period = CooperativePeriod::current();

            DB::connection('mysql')->table('icu_transaction')->insert([
                'pprd' => $period,
                'trncd' => SavingsService::TRNCD_SAVINGS,
                'trnno' => $trnno,
                'trndt' => now()->toDateString(),
                'icu_rec_id' => $withdrawal->member_rec_id,
                'empno' => '',
                'descr' => mb_substr('Penarikan Simpanan '.$withdrawal->member_icuno, 0, 50),
                'dbocr' => 'C',
                'basic_amt' => $withdrawal->amount,
                'int_amt' => 0,
                'amount' => $withdrawal->amount,
                'notes' => '',
                'entdt' => now(),
                'lupd' => now(),
                'entusr' => 'RUN',
                'refno' => '',
                'statrec' => 1,
                'statrec2' => 0,
            ]);

            return $trnno;
        });
    }

    private function generateWithdrawalTrnno(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();
        $sequence = LoanPostingService::nextSequence('icu_transaction', 'trnno', 'WDR-%');

        return LoanPostingService::formatLegacyTrnno('WDR', $now, $sequence);
    }

    private function primaryBank(int $userId): ?object
    {
        if ($userId <= 0 || ! Schema::connection('run')->hasTable('sysitc_userbank')) {
            return null;
        }

        return DB::connection('run')->table('sysitc_userbank')
            ->where('user_recid', $userId)
            ->orderByDesc('asdefault')
            ->orderBy('bnkcd')
            ->orderBy('accno')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int|string, string>
     */
    private function bankOptions(): \Illuminate\Support\Collection
    {
        if (! Schema::connection('run')->hasTable('sys_msttable')) {
            return collect();
        }

        return DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '51')
            ->where('statrec', 1)
            ->orderBy('descr')
            ->pluck('descr', 'code');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{bank_bnkcd: string, bank_accnm: string, bank_accno: string}|null
     */
    private function resolveBankSnapshot(array $data, int $userId): ?array
    {
        $bankCode = trim((string) ($data['bank_code'] ?? ''));
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNo = trim((string) ($data['account_no'] ?? ''));

        if ($bankCode !== '' || $accountName !== '' || $accountNo !== '') {
            if ($bankCode === '' || $accountName === '' || $accountNo === '') {
                return null;
            }

            $this->saveUserBank($bankCode, $accountName, $accountNo, $userId);

            return [
                'bank_bnkcd' => $bankCode,
                'bank_accnm' => $accountName,
                'bank_accno' => $accountNo,
            ];
        }

        $defaultBank = $this->primaryBank($userId);

        if ($defaultBank === null) {
            return null;
        }

        return [
            'bank_bnkcd' => (string) $defaultBank->bnkcd,
            'bank_accnm' => (string) $defaultBank->accnm,
            'bank_accno' => (string) $defaultBank->accno,
        ];
    }

    private function saveUserBank(string $bankCode, string $accountName, string $accountNo, int $userId): void
    {
        if ($userId <= 0 || ! Schema::connection('run')->hasTable('sysitc_userbank')) {
            return;
        }

        DB::connection('run')->table('sysitc_userbank')->insert([
            'user_recid' => $userId,
            'bnkcd' => $bankCode,
            'accnm' => $accountName,
            'accno' => $accountNo,
            'asdefault' => 1,
        ]);

        DB::connection('run')->table('sysitc_userbank')->where('user_recid', $userId)->update(['asdefault' => 0]);
        DB::connection('run')->table('sysitc_userbank')
            ->where('user_recid', $userId)
            ->where('bnkcd', $bankCode)
            ->where('accnm', $accountName)
            ->where('accno', $accountNo)
            ->orderByDesc('rec_id')
            ->limit(1)
            ->update(['asdefault' => 1]);
    }

    /**
     * @param  array{bank_bnkcd: string, bank_accnm: string, bank_accno: string}  $bankSnapshot
     */
    private function formatBankSnapshot(array $bankSnapshot): string
    {
        $bankLabel = (string) ($this->bankOptions()[$bankSnapshot['bank_bnkcd']] ?? $bankSnapshot['bank_bnkcd']);

        return mb_substr($bankLabel.' - '.$bankSnapshot['bank_accnm'].' ('.$bankSnapshot['bank_accno'].')', 0, 120);
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

    private function writeAudit(Request $request, int $memberRecId, int $oldValue, int $newValue): void
    {
        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $this->currentUserId($request),
                'action' => 'cooperative.member.savings_updated',
                'target_type' => 'icu_member',
                'target_id' => $memberRecId,
                'metadata_json' => json_encode([
                    'old_swajib' => $oldValue,
                    'new_swajib' => $newValue,
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit tidak boleh menggagalkan update nominal simpanan anggota.
        }
    }

    private function writeEventAudit(Request $request, string $action, string $targetType, int $targetId, array $metadata): void
    {
        try {
            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $this->currentUserId($request),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'ip_address' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit tidak boleh menggagalkan proses simpanan.
        }
    }
}
