<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoan;
use App\Models\Cooperative\CooperativeLoanSkip;
use App\Models\Cooperative\CooperativeLoanSkipAction;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\LoanSkipService;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanSkipController extends Controller
{
    public function __construct(private readonly LoanSkipService $skips) {}

    public function index(Request $request): View
    {
        $userId = (int) auth_user_id();
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $member = CooperativeAccess::memberForUser($userId);

        return view('cooperative.skips.index', [
            'skips' => CooperativeLoanSkip::query()
                ->when(! $isAdmin, fn ($query) => $query->where('member_rec_id', $member?->rec_id ?? 0))
                ->when($request->filled('q'), function ($query) use ($request): void {
                    $keyword = '%'.str_replace('%', '\%', trim((string) $request->query('q'))).'%';
                    $query->where(fn ($inner) => $inner
                        ->where('member_icuno', 'like', $keyword)
                        ->orWhere('member_name', 'like', $keyword));
                })
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'service' => $this->skips,
            'filter' => (string) $request->query('q', ''),
            'isAdmin' => $isAdmin,
            'member' => $member,
        ]);
    }

    public function create(Request $request): View
    {
        $loanRecId = (int) $request->query('loan_rec_id');
        $mode = (string) $request->query('mode', LoanSkipService::MODE_SKIP);
        $loan = null;
        $preview = null;
        $previewError = null;
        $availablePeriods = [];
        $selectedStartPeriod = '';

        $userId = (int) auth_user_id();
        $isAdmin = CooperativeAccess::isAdmin($userId);
        $member = CooperativeAccess::memberForUser($userId);

        if ($isAdmin) {
            $loan = $loanRecId > 0
                ? CooperativeLoan::query()->with('member')->find($loanRecId)
                : null;
        } elseif ($member) {
            $loan = $loanRecId > 0
                ? CooperativeLoan::query()->with('member')->where('icu_rec_id', $member->rec_id)->find($loanRecId)
                : null;
        } else {
            $loan = null;
        }

        $scheduleRows = [];
        $afterRows = [];

        if ($loan) {
            $rows = $loan->schedules()->orderBy('seqno')->get()->map(fn ($row): array => [
                'rec_id' => $row->rec_id,
                'seqno' => $row->seqno,
                'periode' => $row->periode,
                'amount' => $row->amount,
                'int_amt' => $row->int_amt,
                'others' => $row->others,
                'paidst' => $row->paidst,
                'payno' => $row->payno,
            ])->all();

            $scheduleRows = $this->skips->scheduleWithStatus($rows);

            // Daftar bulan yang masih belum dibayar untuk dropdown "Mulai Skip".
            $availablePeriods = collect($rows)
                ->filter(fn (array $row): bool => (int) $row['paidst'] === 0)
                ->values()
                ->map(fn (array $row): array => [
                    'periode' => (string) $row['periode'],
                    'label' => CooperativePeriod::longLabel((string) $row['periode']),
                ])
                ->all();

            $requestedStart = (string) $request->query('start_period');
            $selectedStartPeriod = in_array($requestedStart, array_column($availablePeriods, 'periode'), true)
                ? $requestedStart
                : ($availablePeriods[0]['periode'] ?? '');

            // Pratinjau langsung bila parameter rentang tersedia.
            if ($mode === LoanSkipService::MODE_ACCELERATE) {
                if ($request->filled('months_count')) {
                    try {
                        $preview = $this->skips->acceleratePlan($rows, (int) $request->query('months_count'));
                        $afterRows = $this->skips->afterSchedule($rows, $preview, LoanSkipService::MODE_ACCELERATE);
                    } catch (InvalidArgumentException $exception) {
                        $preview = null;
                        $previewError = $exception->getMessage();
                    }
                }
            } elseif ($request->filled('start_period') && $request->filled('months_count')) {
                try {
                    $preview = $this->skips->plan(
                        $rows,
                        (string) $request->query('start_period'),
                        (int) $request->query('months_count'),
                    );
                    $afterRows = $this->skips->afterSchedule($rows, $preview, LoanSkipService::MODE_SKIP);
                } catch (InvalidArgumentException $exception) {
                    $preview = null;
                    $previewError = $exception->getMessage();
                }
            }
        }

        return view('cooperative.skips.create', [
            'loans' => CooperativeLoan::query()
                ->with('member')
                ->when(! $isAdmin, fn ($query) => $query->where('icu_rec_id', $member?->rec_id ?? 0))
                ->statusIndicative('running')
                ->orderByDesc('trndt')
                ->limit(200)
                ->get(['rec_id', 'trnno', 'term', 'endper']),
            'loan' => $loan,
            'preview' => $preview,
            'previewError' => $previewError,
            'scheduleRows' => $scheduleRows,
            'afterRows' => $afterRows,
            'availablePeriods' => $availablePeriods,
            'selectedStartPeriod' => $selectedStartPeriod,
            'mode' => $mode,
            'memberLinked' => (bool) $member,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:'.LoanSkipService::MODE_SKIP.','.LoanSkipService::MODE_ACCELERATE],
            'loan_rec_id' => ['required', 'integer', 'min:1'],
            'start_period' => ['nullable', 'regex:/^\d{6}$/'],
            'months_count' => ['required', 'integer', 'min:'.LoanSkipService::MIN_MONTHS, 'max:'.LoanSkipService::MAX_MONTHS],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $mode = (string) $data['mode'];
        if ($mode === LoanSkipService::MODE_SKIP && empty($data['start_period'])) {
            return back()->withInput()->withErrors(['start_period' => 'Periode mulai wajib diisi untuk mode skip pokok.']);
        }

        $loan = CooperativeLoan::query()->with('member')->find((int) $data['loan_rec_id']);
        if (! $loan || ! $loan->member) {
            return back()->withInput()->withErrors(['loan_rec_id' => 'Pinjaman tidak ditemukan.']);
        }

        $rows = $loan->schedules()->orderBy('seqno')->get()->map(fn ($row): array => [
            'rec_id' => $row->rec_id, 'seqno' => $row->seqno, 'periode' => $row->periode,
            'amount' => $row->amount, 'int_amt' => $row->int_amt, 'others' => $row->others,
            'paidst' => $row->paidst,
        ])->all();

        try {
            $plan = $mode === LoanSkipService::MODE_ACCELERATE
                ? $this->skips->acceleratePlan($rows, (int) $data['months_count'])
                : $this->skips->plan($rows, (string) $data['start_period'], (int) $data['months_count']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['months_count' => $exception->getMessage()]);
        }

        $userId = $this->currentUserId($request);

        $skipId = DB::connection('run')->transaction(function () use ($request, $data, $loan, $plan, $userId, $mode): int {
            $skip = CooperativeLoanSkip::query()->create([
                'mode' => $mode,
                'loan_rec_id' => $loan->rec_id,
                'member_rec_id' => $loan->member->rec_id,
                'member_icuno' => $loan->member->icuno,
                'member_name' => $loan->member->icunm,
                'start_period' => (string) ($data['start_period'] ?? $plan['window_end'] ?? ''),
                'months_count' => (int) $data['months_count'],
                'rows_skipped' => $plan['skipped_rows'] ?? $plan['removed_rows'],
                'principal_moved' => $plan['moved_principal'],
                'extra_interest' => $plan['extra_interest'] ?? $plan['retained_interest'],
                'new_term' => $plan['new_term'],
                'plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                'status' => LoanSkipService::STATUS_SUBMITTED,
                'reason' => $data['reason'] ?? null,
                'maker_user_id' => $userId,
            ]);

            CooperativeLoanSkipAction::query()->create([
                'skip_id' => $skip->id,
                'action' => CooperativeLoanSkipAction::ACTION_SUBMITTED,
                'note' => null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, 'submitted', (int) $skip->id, [
                'loan_trnno' => $loan->trnno,
                'mode' => $mode,
                'months' => $data['months_count'],
            ]);

            return (int) $skip->id;
        });

        $message = $mode === LoanSkipService::MODE_ACCELERATE
            ? 'Pengajuan percepatan pembayaran tercatat dan menunggu persetujuan.'
            : 'Pengajuan skip pokok tercatat dan menunggu persetujuan.';

        return redirect()
            ->route('cooperative.skips.detail', ['id' => $skipId])
            ->with('success', $message);
    }

    public function detail(Request $request): View
    {
        $skip = CooperativeLoanSkip::query()->with('actions')->findOrFail((int) $request->query('id'));

        return view('cooperative.skips.detail', [
            'skip' => $skip,
            'actions' => $skip->actions,
            'plan' => json_decode((string) $skip->plan_json, true) ?? [],
            'service' => $this->skips,
            'currentUserId' => $this->currentUserId($request),
        ]);
    }

    public function decide(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:apply,reject,cancel'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $skip = CooperativeLoanSkip::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);
        $isMaker = $userId === $skip->maker_user_id;

        [$targetStatus, $action] = match ((string) $data['decision']) {
            'apply' => [LoanSkipService::STATUS_APPLIED, CooperativeLoanSkipAction::ACTION_APPLIED],
            'reject' => [LoanSkipService::STATUS_REJECTED, CooperativeLoanSkipAction::ACTION_REJECTED],
            default => [LoanSkipService::STATUS_CANCELLED, CooperativeLoanSkipAction::ACTION_CANCELLED],
        };

        if ($data['decision'] === 'apply') {
            if (! $this->skips->canDecide($skip->maker_user_id, $userId)) {
                return back()->withErrors(['decision' => 'Pengaju tidak dapat menyetujui skip pokoknya sendiri.']);
            }
        } elseif ($data['decision'] === 'cancel' && ! $isMaker && $skip->status === LoanSkipService::STATUS_SUBMITTED) {
            return back()->withErrors(['decision' => 'Hanya pengaju yang dapat membatalkan sebelum disetujui.']);
        }

        try {
            if ($data['decision'] === 'apply') {
                $summary = $this->skips->apply($skip, $userId);
                $actionNote = ($data['note'] ?? '') !== ''
                    ? $data['note']
                    : ($skip->mode === LoanSkipService::MODE_ACCELERATE
                        ? 'Diterapkan: '.$summary['rows_removed'].' baris dihapus, tenor baru '.$summary['new_term'].' bulan, sisa '.$summary['rows_remaining'].' baris.'
                        : 'Diterapkan: '.$summary['rows_skipped'].' baris diskip, '.$summary['rows_added'].' baris baru, tenor baru '.$summary['new_term'].' bulan.');
            } else {
                $this->skips->assertTransition($skip->status, $targetStatus);
                $skip->forceFill([
                    'status' => $targetStatus,
                    'checker_user_id' => $userId,
                    'checked_at' => now(),
                    'decision_note' => ($data['note'] ?? '') !== '' ? $data['note'] : null,
                ])->save();
                $actionNote = ($data['note'] ?? '') !== '' ? $data['note'] : null;
            }
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        CooperativeLoanSkipAction::query()->create([
            'skip_id' => $skip->id,
            'action' => $action,
            'note' => $actionNote,
            'actor_user_id' => $userId,
            'actor_name' => $this->actorName($userId),
        ]);

        $this->writeAudit($request, $action, $skip->id, [
            'to_status' => $targetStatus,
            'moved_principal' => $skip->principal_moved,
        ]);

        return redirect()
            ->route('cooperative.skips.detail', ['id' => $skip->id])
            ->with('success', 'Keputusan berhasil dicatat.');
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

    private function writeAudit(Request $request, string $action, int $skipId, array $metadata): void
    {
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => $this->currentUserId($request),
            'action' => 'cooperative.loan_skip.'.$action,
            'target_type' => 'coop_loan_skip',
            'target_id' => $skipId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
