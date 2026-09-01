<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoanApplication;
use App\Models\Cooperative\CooperativeLoanApplicationAction;
use App\Models\Cooperative\CooperativeMember;
use App\Services\Cooperative\LoanApplicationService;
use App\Services\Cooperative\LoanPostingService;
use App\Services\Cooperative\LoanSimulationService;
use App\Services\Cooperative\CooperativeSettingsService;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $applications,
        private readonly LoanPostingService $postings,
    ) {}

    public function index(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $isAdmin = CooperativeAccess::isAdmin($userId);

        $query = CooperativeLoanApplication::query()->with('latestAction');

        if (! $isAdmin) {
            $linkedMember = CooperativeAccess::memberForUser($userId);

            if ($linkedMember === null) {
                // Akun belum ditautkan ke record anggota: jangan tampilkan pengajuan apa pun.
                $query->whereRaw('1 = 0');
            } else {
                $query->where('member_rec_id', $linkedMember->rec_id);
            }
        }

        if ($request->filled('q')) {
            $keyword = '%'.str_replace('%', '\%', trim((string) $request->query('q'))).'%';
            $query->where(fn ($inner) => $inner
                ->where('member_icuno', 'like', $keyword)
                ->orWhere('member_name', 'like', $keyword)
                ->orWhere('descr', 'like', $keyword));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return view('cooperative.applications.index', [
            'applications' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats' => [
                'submitted' => (int) (clone $query)->where('status', LoanApplicationService::STATUS_SUBMITTED)->count(),
                'approved' => (int) (clone $query)->where('status', LoanApplicationService::STATUS_APPROVED)->count(),
            ],
            'statusLabels' => LoanApplicationService::STATUS_LABELS,
            'service' => $this->applications,
            'isAdmin' => $isAdmin,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $linkedMember = $isAdmin ? null : CooperativeAccess::memberForUser($userId);

        return view('cooperative.applications.create', [
            'memberOptions' => $isAdmin
                ? CooperativeMember::query()
                    ->whereIn('st_aktif', [CooperativeMember::STATUS_REGULAR_MEMBER, CooperativeMember::STATUS_REGULAR_NON_PAYROLL])
                    ->orderBy('icuno')
                    ->get(['rec_id', 'icuno', 'icunm'])
                : ($linkedMember !== null ? [$linkedMember] : []),
            'defaultRate' => CooperativeSettingsService::defaultRate(),
            'defaultMethod' => CooperativeSettingsService::defaultMethod(),
            'methods' => LoanSimulationService::METHODS,
            'isAdmin' => $isAdmin,
            'linkedMember' => $linkedMember,
            'defaultBank' => $this->primaryBank($userId),
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->currentUserId($request);
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $linkedMember = $isAdmin ? null : CooperativeAccess::memberForUser($userId);

        // CU Member hanya boleh mengajukan pinjaman atas dirinya sendiri.
        abort_unless($isAdmin || $linkedMember !== null, 403, 'Akun Anda belum ditautkan ke data anggota koperasi.');

        $data = $this->validated($request);

        if (! $isAdmin) {
            $data['member_rec_id'] = (string) $linkedMember->rec_id;
        }

        $member = CooperativeMember::query()->find((int) $data['member_rec_id']);
        if (! $member) {
            return back()->withInput()->withErrors(['member_rec_id' => 'Anggota tidak ditemukan.']);
        }

        if ((int) $data['admin_fee'] >= (int) $data['principal_amount']) {
            return back()->withInput()->withErrors(['admin_fee' => 'Biaya admin tidak boleh lebih besar atau sama dengan jumlah pinjaman.']);
        }

        // Bank pencairan hanya relevan untuk metode transfer.
        if ((string) $data['fund_release_method'] === 'transfer') {
            $bankSnapshot = $this->resolveBankSnapshot($data, $userId);

            if ($bankSnapshot === null) {
                return back()->withInput()->withErrors(['bank_code' => 'Bank transfer wajib diisi.']);
            }

            $data['bank_bnkcd'] = $bankSnapshot['bank_bnkcd'];
            $data['bank_accnm'] = $bankSnapshot['bank_accnm'];
            $data['bank_accno'] = $bankSnapshot['bank_accno'];
        } else {
            $data['bank_bnkcd'] = '';
            $data['bank_accnm'] = '';
            $data['bank_accno'] = '';
        }

        try {
            $result = $this->applications->simulate(
                (int) $data['principal_amount'],
                (int) $data['tenor_months'],
                (float) $data['annual_rate_percent'],
                (string) $data['calculation_method'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['principal_amount' => $exception->getMessage()]);
        }

        /** @var array<string, int|float|string> $summary */
        $summary = $result['summary'];

        $applicationId = DB::connection('run')->transaction(function () use ($request, $data, $member, $summary, $result, $userId): int {
            $application = CooperativeLoanApplication::query()->create([
                'member_rec_id' => $member->rec_id,
                'member_icuno' => $member->icuno,
                'member_name' => $member->icunm,
                'principal_amount' => (int) $data['principal_amount'],
                'tenor_months' => (int) $data['tenor_months'],
                'annual_rate_percent' => (float) $data['annual_rate_percent'],
                'calculation_method' => (string) $data['calculation_method'],
                'descr' => (string) $data['descr'],
                'status' => LoanApplicationService::STATUS_SUBMITTED,
                'monthly_installment' => (int) $summary['first_installment'],
                'total_interest' => (int) $summary['total_interest'],
                'total_payment' => (int) $summary['total_payment'],
                'schedule_json' => json_encode($result['schedule'], JSON_UNESCAPED_UNICODE),
                'applicant_user_id' => $userId,
                'fund_release_method' => (string) $data['fund_release_method'],
                'admin_fee' => (int) $data['admin_fee'],
                'bank_bnkcd' => (string) $data['bank_bnkcd'],
                'bank_accnm' => (string) $data['bank_accnm'],
                'bank_accno' => (string) $data['bank_accno'],
            ]);

            CooperativeLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => CooperativeLoanApplicationAction::ACTION_SUBMITTED,
                'note' => null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, 'submitted', $application->id, [
                'member_icuno' => $member->icuno,
                'principal' => $application->principal_amount,
                'tenor' => $application->tenor_months,
            ]);

            return (int) $application->id;
        });

        return redirect()
            ->route('cooperative.applications.detail', ['id' => $applicationId])
            ->with('success', 'Pengajuan pinjaman berhasil dibuat dan menunggu persetujuan.');
    }

    public function detail(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $isAdmin = CooperativeAccess::isAdmin($userId);

        $application = CooperativeLoanApplication::query()->with('actions')->findOrFail((int) $request->query('id'));

        // CU Member / User Credit Union hanya boleh membuka pengajuan milik dirinya sendiri.
        if (! $isAdmin) {
            $member = CooperativeAccess::memberForUser($userId);

            abort_unless(
                $member !== null && (int) $application->member_rec_id === (int) $member->rec_id,
                403,
                'Anda hanya dapat melihat pengajuan pinjaman milik Anda sendiri.'
            );
        }

        return view('cooperative.applications.detail', [
            'application' => $application,
            'schedule' => $application->schedule(),
            'actions' => $application->actions,
            'service' => $this->applications,
            'currentUserId' => $this->currentUserId($request),
            'isAdmin' => $isAdmin,
            'fundReleaseMethods' => ['cash' => 'Tunai', 'transfer' => 'Transfer Bank'],
            'bankLabels' => $this->bankOptions(),
        ]);
    }

    public function decide(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:approve,reject,cancel'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $application = CooperativeLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);
        $isMaker = $userId === $application->applicant_user_id;
        $isAdmin = CooperativeAccess::isAdmin($userId);

        // Approve/reject hanya untuk admin koperasi. Pembatalan diizinkan untuk
        // pembuat pengajuan (sebelum disetujui) atau admin koperasi.
        if ($data['decision'] !== 'cancel') {
            abort_unless($isAdmin, 403, 'Hanya admin koperasi yang dapat menyetujui atau menolak pengajuan.');
        } else {
            abort_unless($isAdmin || $isMaker, 403, 'Anda tidak dapat membatalkan pengajuan ini.');
        }

        [$targetStatus, $action] = match ((string) $data['decision']) {
            'approve' => [LoanApplicationService::STATUS_APPROVED, CooperativeLoanApplicationAction::ACTION_APPROVED],
            'reject' => [LoanApplicationService::STATUS_REJECTED, CooperativeLoanApplicationAction::ACTION_REJECTED],
            default => [LoanApplicationService::STATUS_CANCELLED, CooperativeLoanApplicationAction::ACTION_CANCELLED],
        };

        if ($data['decision'] !== 'cancel') {
            if (! $this->applications->canDecide($application->applicant_user_id, $userId)) {
                return back()->withErrors(['decision' => 'Maker tidak dapat menyetujui atau menolak pengajuannya sendiri.']);
            }
        } elseif (! $this->applications->canCancel($application->applicant_user_id, $userId, $application->status)) {
            return back()->withErrors(['decision' => $application->status === LoanApplicationService::STATUS_SUBMITTED
                ? 'Hanya pembuat pengajuan yang dapat membatalkan sebelum disetujui.'
                : 'Pengajuan yang sudah disetujui hanya dapat dibatalkan oleh selain pembuat.']);
        }

        try {
            $this->applications->assertTransition($application->status, $targetStatus);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        DB::connection('run')->transaction(function () use ($request, $application, $targetStatus, $action, $data, $userId): void {
            $application->forceFill([
                'status' => $targetStatus,
                'reviewer_user_id' => $userId,
                'reviewed_at' => now(),
                'decision_note' => $data['note'] !== null && $data['note'] !== '' ? $data['note'] : null,
            ])->save();

            CooperativeLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => $action,
                'note' => $data['note'] ?? null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, $action, $application->id, [
                'from_status' => $application->getOriginal('status'),
                'to_status' => $targetStatus,
            ]);
        });

        return redirect()
            ->route('cooperative.applications.detail', ['id' => $application->id])
            ->with('success', 'Keputusan berhasil dicatat.');
    }

    /**
     * Endpoint simulasi ulang untuk form create (JSON).
     */
    public function recalculate(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $result = $this->applications->simulate(
                (int) $data['principal_amount'],
                (int) $data['tenor_months'],
                (float) $data['annual_rate_percent'],
                (string) $data['calculation_method'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Posting pengajuan approved menjadi pinjaman aktual di icu_mloan + icu_dloan.
     */
    public function post(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $application = CooperativeLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);

        // Posting menulis data produksi: hanya admin koperasi.
        abort_unless(CooperativeAccess::isAdmin($userId), 403, 'Hanya admin koperasi yang dapat memosting pengajuan menjadi pinjaman.');

        try {
            $loanRecId = DB::connection('run')->transaction(function () use ($request, $application, $userId): int {
                $loanRecId = $this->postings->post($application, $userId);

                CooperativeLoanApplicationAction::query()->create([
                    'application_id' => $application->id,
                    'action' => 'posted',
                    'note' => 'Diposting sebagai pinjaman rec_id '.$loanRecId,
                    'actor_user_id' => $userId,
                    'actor_name' => $this->actorName($userId),
                ]);

                $this->writeAudit($request, 'posted', $application->id, [
                    'loan_rec_id' => $loanRecId,
                    'member_icuno' => $application->member_icuno,
                ]);

                return $loanRecId;
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cooperative.applications.detail', ['id' => $application->id])
            ->with('success', 'Pengajuan berhasil diposting sebagai pinjaman (rec_id '.$loanRecId.').');
    }

    /**
     * @return array{member_rec_id: string, principal_amount: string, tenor_months: string, annual_rate_percent: string, calculation_method: string, descr: string, fund_release_method: string, admin_fee: string, bank_code?: string, account_name?: string, account_no?: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'member_rec_id' => ['required', 'integer', 'min:1'],
            'principal_amount' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:120'],
            'annual_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'calculation_method' => ['required', 'string', 'in:'.implode(',', array_keys(LoanSimulationService::METHODS))],
            'descr' => ['required', 'string', 'max:100'],
            'fund_release_method' => ['required', 'string', 'in:cash,transfer'],
            'admin_fee' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_no' => ['nullable', 'string', 'max:80'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryBank(int $userId): ?object
    {
        if ($userId <= 0) {
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
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function bankOptions(): \Illuminate\Support\Collection
    {
        return DB::connection('run')->table('sys_msttable')
            ->where('tbl_code', '51')
            ->where('statrec', 1)
            ->orderBy('descr')
            ->pluck('descr', 'code');
    }

    /**
     * Resolusi bank pencairan (metode transfer) untuk user yang sedang login.
     *
     * Jika form berisi bank, simpan/update ke sysitc_userbank lalu pakai snapshot-nya.
     * Jika tidak, pakai bank default user. Mengembalikan null bila belum tersedia.
     *
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

    private function writeAudit(Request $request, string $action, int $applicationId, array $metadata): void
    {
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => $this->currentUserId($request),
            'action' => 'cooperative.loan_application.'.$action,
            'target_type' => 'coop_loan_application',
            'target_id' => $applicationId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
