<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionLoanApplication;
use App\Models\CreditUnion\CreditUnionLoanApplicationAction;
use App\Models\CreditUnion\CreditUnionMember;
use App\Services\CreditUnion\CreditUnionNotificationService;
use App\Services\CreditUnion\CreditUnionSettingsService;
use App\Services\CreditUnion\LoanApplicationService;
use App\Services\CreditUnion\LoanPostingService;
use App\Services\CreditUnion\LoanSimulationService;
use App\Support\CreditUnionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $applications,
        private readonly LoanPostingService $postings,
        private readonly CreditUnionNotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $userId = $this->currentUserId($request);
        // view=mine memaksa admin yang juga anggota melihat pengajuan sendiri.
        $isAdminRole = CreditUnionAccess::isAdmin($userId);
        $personal = $isAdminRole && $request->query('view') === 'mine';
        $isAdmin = $isAdminRole && ! $personal;

        $query = CreditUnionLoanApplication::query()->with('latestAction');

        if (! $isAdmin) {
            $linkedMember = CreditUnionAccess::memberForUser($userId);

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

        return view('credit-union.applications.index', [
            'applications' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats' => [
                'submitted' => (int) (clone $query)->where('status', LoanApplicationService::STATUS_SUBMITTED)->count(),
                'approved' => (int) (clone $query)->where('status', LoanApplicationService::STATUS_APPROVED)->count(),
                'transfer_processing' => (int) (clone $query)->where('status', LoanApplicationService::STATUS_TRANSFER_PROCESSING)->count(),
            ],
            'statusLabels' => LoanApplicationService::STATUS_LABELS,
            'service' => $this->applications,
            'isAdmin' => $isAdmin,
            'personal' => $personal,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        CreditUnionSettingsService::ensureDefaults();
        $userId = $this->currentUserId($request);
        $isAdminRole = CreditUnionAccess::isAdmin($userId);
        $personal = $isAdminRole && $request->query('view') === 'mine';
        $isAdmin = $isAdminRole && ! $personal;
        $linkedMember = $isAdmin ? null : CreditUnionAccess::memberForUser($userId);

        return view('credit-union.applications.create', [
            'memberOptions' => $isAdmin
                ? CreditUnionMember::query()
                    ->whereIn('st_aktif', [CreditUnionMember::STATUS_REGULAR_MEMBER, CreditUnionMember::STATUS_REGULAR_NON_PAYROLL])
                    ->orderBy('icuno')
                    ->get(['rec_id', 'icuno', 'icunm'])
                : ($linkedMember !== null ? [$linkedMember] : []),
            'defaultRate' => CreditUnionSettingsService::defaultRate(),
            'defaultMethod' => CreditUnionSettingsService::defaultMethod(),
            'defaultAdminFee' => CreditUnionSettingsService::defaultAdminFee(),
            'methods' => LoanSimulationService::METHODS,
            'isAdmin' => $isAdmin,
            'personal' => $personal,
            'linkedMember' => $linkedMember,
            'defaultBank' => $this->primaryBank($userId),
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->currentUserId($request);
        $isAdminRole = CreditUnionAccess::isAdmin($userId);
        $personal = $isAdminRole && $request->input('view') === 'mine';
        $isAdmin = $isAdminRole && ! $personal;
        $linkedMember = $isAdmin ? null : CreditUnionAccess::memberForUser($userId);

        // CU Member hanya boleh mengajukan pinjaman atas dirinya sendiri.
        abort_unless($isAdmin || $linkedMember !== null, 403, 'Akun Anda belum ditautkan ke data anggota credit union.');

        $data = $this->validated($request);
        // Nominal biaya admin mengikuti pengaturan credit union dan tidak dapat diubah dari form.
        $data['admin_fee'] = CreditUnionSettingsService::defaultAdminFee();
        $data['fund_release_method'] = 'transfer';

        if (! $isAdmin) {
            $data['member_rec_id'] = (string) $linkedMember->rec_id;
        }

        $member = CreditUnionMember::query()->find((int) $data['member_rec_id']);
        if (! $member) {
            return back()->withInput()->withErrors(['member_rec_id' => 'Anggota tidak ditemukan.']);
        }

        if ($data['admin_fee_type'] === 'include' && (int) $data['admin_fee'] >= (int) $data['principal_amount']) {
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
                (int) $data['admin_fee'],
                (string) $data['admin_fee_type'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['principal_amount' => $exception->getMessage()]);
        }

        /** @var array<string, int|float|string> $summary */
        $summary = $result['summary'];

        $applicationId = DB::connection('run')->transaction(function () use ($request, $data, $member, $summary, $result, $userId): int {
            $application = CreditUnionLoanApplication::query()->create([
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
                'admin_fee_type' => (string) $data['admin_fee_type'],
                'bank_bnkcd' => (string) $data['bank_bnkcd'],
                'bank_accnm' => (string) $data['bank_accnm'],
                'bank_accno' => (string) $data['bank_accno'],
            ]);

            CreditUnionLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => CreditUnionLoanApplicationAction::ACTION_SUBMITTED,
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

        $this->notifications->notifyAdmins(
            $userId,
            'cu.loan_application.submitted',
            'Pengajuan Pinjaman Baru',
            'Anggota '.$member->icunm.' ('.$member->icuno.') mengajukan pinjaman Rp '.number_format((int) $data['principal_amount'], 0, ',', '.').' selama '.$data['tenor_months'].' bulan.',
            route('cu.applications.detail', ['id' => $applicationId]),
            'cu_loan_application',
            $applicationId
        );

        return redirect()
            ->route('cu.applications.detail', ['id' => $applicationId] + ($personal ? ['view' => 'mine'] : []))
            ->with('success', 'Pengajuan pinjaman berhasil dibuat dan menunggu persetujuan.');
    }

    public function detail(Request $request): View
    {
        $userId = $this->currentUserId($request);
        $isAdminRole = CreditUnionAccess::isAdmin($userId);
        $personal = $isAdminRole && $request->query('view') === 'mine';
        $isAdmin = $isAdminRole && ! $personal;

        $application = CreditUnionLoanApplication::query()->with('actions')->findOrFail((int) $request->query('id'));

        // CU Member / User Credit Union hanya boleh membuka pengajuan milik dirinya sendiri.
        if (! $isAdmin) {
            $member = CreditUnionAccess::memberForUser($userId);

            abort_unless(
                $member !== null && (int) $application->member_rec_id === (int) $member->rec_id,
                403,
                'Anda hanya dapat melihat pengajuan pinjaman milik Anda sendiri.'
            );
        }

        return view('credit-union.applications.detail', [
            'application' => $application,
            'schedule' => $application->schedule(),
            'actions' => $application->actions,
            'service' => $this->applications,
            'currentUserId' => $this->currentUserId($request),
            'isAdmin' => $isAdmin,
            'personal' => $personal,
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

        $application = CreditUnionLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);
        $isOwnerOrMaker = CreditUnionAccess::isOwnerOrMaker($userId, $application->applicant_user_id, (int) $application->member_rec_id);
        $isAdmin = CreditUnionAccess::isAdmin($userId);

        // Approve/reject hanya untuk admin credit union lain (bukan maker/peminjam).
        // Pembatalan diizinkan untuk pembuat/peminjam (sebelum disetujui) atau admin lain.
        if ($data['decision'] !== 'cancel') {
            abort_unless($isAdmin, 403, 'Hanya admin credit union yang dapat menyetujui atau menolak pengajuan.');
        } else {
            abort_unless($isAdmin || $isOwnerOrMaker, 403, 'Anda tidak dapat membatalkan pengajuan ini.');
        }

        [$targetStatus, $action] = match ((string) $data['decision']) {
            'approve' => [LoanApplicationService::STATUS_APPROVED, CreditUnionLoanApplicationAction::ACTION_APPROVED],
            'reject' => [LoanApplicationService::STATUS_REJECTED, CreditUnionLoanApplicationAction::ACTION_REJECTED],
            default => [LoanApplicationService::STATUS_CANCELLED, CreditUnionLoanApplicationAction::ACTION_CANCELLED],
        };

        if ($data['decision'] !== 'cancel') {
            if (! $this->applications->canDecide($application->applicant_user_id, $userId, (int) $application->member_rec_id)) {
                return back()->withErrors(['decision' => 'Pengaju atau peminjam tidak dapat menyetujui atau menolak pengajuannya sendiri. Persetujuan harus dilakukan oleh admin lain.']);
            }
        } elseif (! $this->applications->canCancel($application->applicant_user_id, $userId, $application->status, (int) $application->member_rec_id)) {
            return back()->withErrors(['decision' => $application->status === LoanApplicationService::STATUS_SUBMITTED
                ? 'Hanya pembuat/pemilik pengajuan yang dapat membatalkan sebelum disetujui.'
                : 'Pengajuan yang sudah disetujui hanya dapat dibatalkan oleh admin lain.']);
        }

        try {
            $this->applications->assertTransition($application->status, $targetStatus);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        $fromStatus = (string) $application->status;

        // Klaim atomik: update bersyarat pada status lama. Request kedua (tab lain)
        // gagal di sini sehingga keputusan/notifikasi tidak tercatat dobel.
        $claimed = DB::connection('run')->transaction(function () use ($request, $application, $fromStatus, $targetStatus, $action, $data, $userId): bool {
            $updated = CreditUnionLoanApplication::query()
                ->whereKey($application->id)
                ->where('status', $fromStatus)
                ->update([
                    'status' => $targetStatus,
                    'reviewer_user_id' => $userId,
                    'reviewed_at' => now(),
                    'decision_note' => $data['note'] !== null && $data['note'] !== '' ? $data['note'] : null,
                ]);

            if ($updated === 0) {
                return false;
            }

            CreditUnionLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => $action,
                'note' => $data['note'] ?? null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, $action, $application->id, [
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
            ]);

            return true;
        });

        if (! $claimed) {
            return back()->withErrors(['decision' => 'Pengajuan ini sudah diproses oleh permintaan lain.']);
        }

        if ($data['decision'] === 'approve' || $data['decision'] === 'reject') {
            $isApproved = $data['decision'] === 'approve';
            $this->notifications->notifyDecision(
                (int) $application->member_rec_id,
                (int) $application->applicant_user_id,
                $userId,
                'cu.loan_application.'.($isApproved ? 'approved' : 'rejected'),
                $isApproved ? 'Pengajuan Pinjaman Disetujui' : 'Pengajuan Pinjaman Ditolak',
                'Pengajuan pinjaman Rp '.number_format((int) $application->principal_amount, 0, ',', '.').' Anda '.($isApproved ? 'telah disetujui' : 'telah ditolak').'.',
                route('cu.applications.detail', ['id' => $application->id]),
                'cu_loan_application',
                (int) $application->id
            );
        }

        return redirect()
            ->route('cu.applications.detail', ['id' => $application->id] + ($request->input('view') === 'mine' ? ['view' => 'mine'] : []))
            ->with('success', 'Keputusan berhasil dicatat.');
    }

    public function startTransfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $application = CreditUnionLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);

        abort_unless(
            CreditUnionAccess::canApproveAsAdmin($userId, $application->applicant_user_id, (int) $application->member_rec_id),
            403,
            'Hanya admin credit union lain yang dapat memproses transfer pengajuan ini.'
        );

        try {
            $this->applications->assertTransition($application->status, LoanApplicationService::STATUS_TRANSFER_PROCESSING);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        $fromStatus = (string) $application->status;
        $targetStatus = LoanApplicationService::STATUS_TRANSFER_PROCESSING;

        $claimed = DB::connection('run')->transaction(function () use ($request, $application, $fromStatus, $targetStatus, $userId): bool {
            $updated = CreditUnionLoanApplication::query()
                ->whereKey($application->id)
                ->where('status', $fromStatus)
                ->update([
                    'status' => $targetStatus,
                    'transfer_started_at' => now(),
                    'transfer_started_by' => $userId,
                ]);

            if ($updated === 0) {
                return false;
            }

            CreditUnionLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => CreditUnionLoanApplicationAction::ACTION_TRANSFER_PROCESSING,
                'note' => null,
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, 'transfer_processing', $application->id, [
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
            ]);

            return true;
        });

        if (! $claimed) {
            return back()->withErrors(['decision' => 'Pengajuan ini sudah diproses oleh permintaan lain.']);
        }

        $this->notifications->notifyMember(
            (int) $application->member_rec_id,
            (int) $application->applicant_user_id,
            $userId,
            'cu.loan_application.transfer_processing',
            'Pengajuan Pinjaman Sedang Diproses Transfer',
            'Pengajuan pinjaman Rp '.number_format((int) $application->principal_amount, 0, ',', '.').' sedang diproses transfer ke rekening Anda. Pengajuan tidak dapat dibatalkan pada tahap ini.',
            route('cu.applications.detail', ['id' => $application->id]),
            'cu_loan_application',
            (int) $application->id
        );

        return redirect()
            ->route('cu.applications.detail', ['id' => $application->id] + ($request->input('view') === 'mine' ? ['view' => 'mine'] : []))
            ->with('success', 'Proses transfer pengajuan berhasil dimulai.');
    }

    public function returnTransfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $application = CreditUnionLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);

        abort_unless(
            CreditUnionAccess::canApproveAsAdmin($userId, $application->applicant_user_id, (int) $application->member_rec_id),
            403,
            'Hanya admin credit union lain yang dapat mengembalikan status pengajuan ini.'
        );

        try {
            $this->applications->assertTransition($application->status, LoanApplicationService::STATUS_APPROVED);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        $fromStatus = (string) $application->status;
        $targetStatus = LoanApplicationService::STATUS_APPROVED;

        $claimed = DB::connection('run')->transaction(function () use ($request, $application, $fromStatus, $targetStatus, $userId, $data): bool {
            $updated = CreditUnionLoanApplication::query()
                ->whereKey($application->id)
                ->where('status', $fromStatus)
                ->update([
                    'status' => $targetStatus,
                ]);

            if ($updated === 0) {
                return false;
            }

            CreditUnionLoanApplicationAction::query()->create([
                'application_id' => $application->id,
                'action' => CreditUnionLoanApplicationAction::ACTION_TRANSFER_RETURNED,
                'note' => $data['note'],
                'actor_user_id' => $userId,
                'actor_name' => $this->actorName($userId),
            ]);

            $this->writeAudit($request, 'transfer_returned', $application->id, [
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'note' => $data['note'],
            ]);

            return true;
        });

        if (! $claimed) {
            return back()->withErrors(['decision' => 'Pengajuan ini sudah diproses oleh permintaan lain.']);
        }

        return redirect()
            ->route('cu.applications.detail', ['id' => $application->id] + ($request->input('view') === 'mine' ? ['view' => 'mine'] : []))
            ->with('success', 'Pengajuan berhasil dikembalikan ke status Disetujui.');
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
                CreditUnionSettingsService::defaultAdminFee(),
                (string) $data['admin_fee_type'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Posting pengajuan transfer_processing menjadi pinjaman aktual di icu_mloan + icu_dloan.
     */
    public function post(Request $request): RedirectResponse
    {
        $applicationId = (int) $request->input('id');
        $application = CreditUnionLoanApplication::query()->findOrFail($applicationId);
        $userId = $this->currentUserId($request);

        // Posting menulis data produksi: hanya admin credit union lain (bukan pemohon/peminjam).
        abort_unless(
            CreditUnionAccess::canApproveAsAdmin($userId, $application->applicant_user_id, (int) $application->member_rec_id),
            403,
            'Hanya admin credit union lain yang dapat memosting pengajuan menjadi pinjaman.'
        );

        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'transfer_reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'transfer_proof' => [
                $application->fund_release_method === 'transfer' ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ], [
            'transfer_proof.required' => 'Berkas bukti transfer wajib dilampirkan untuk pencairan melalui transfer bank.',
            'transfer_proof.file' => 'Berkas bukti transfer tidak valid.',
            'transfer_proof.mimes' => 'Format berkas bukti transfer harus berupa JPG, JPEG, PNG, atau PDF.',
            'transfer_proof.max' => 'Ukuran berkas bukti transfer maksimal 5 MB (5120 KB).',
        ]);

        $transferRef = isset($data['transfer_reference']) && trim((string) $data['transfer_reference']) !== ''
            ? trim((string) $data['transfer_reference'])
            : null;
        $note = isset($data['note']) && trim((string) $data['note']) !== ''
            ? trim((string) $data['note'])
            : null;

        $file = $request->file('transfer_proof');
        $storedPath = null;
        $originalName = null;
        $mime = null;
        $size = null;

        if ($file !== null) {
            $originalName = preg_replace('/[^\w\.\-\s]/u', '_', $file->getClientOriginalName()) ?: 'transfer_proof.bin';
            $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
            $size = (int) $file->getSize();
            $storedPath = $file->store('transfer-proofs/'.$application->id, 'local');
        }

        try {
            $loanRecId = DB::connection('run')->transaction(function () use ($request, $application, $userId, $transferRef, $note, $storedPath, $originalName, $mime, $size): int {
                $loanRecId = $this->postings->post($application, $userId);

                $now = now();
                $updatePayload = [
                    'disbursed_at' => $now,
                    'transfer_reference' => $transferRef,
                ];

                if ($storedPath !== null) {
                    $updatePayload['transfer_proof_path'] = $storedPath;
                    $updatePayload['transfer_proof_original_name'] = $originalName;
                    $updatePayload['transfer_proof_mime'] = $mime;
                    $updatePayload['transfer_proof_size'] = $size;
                    $updatePayload['transfer_proof_uploaded_at'] = $now;
                    $updatePayload['transfer_proof_uploaded_by'] = $userId;
                }

                CreditUnionLoanApplication::query()
                    ->whereKey($application->id)
                    ->update($updatePayload);

                $application->disbursed_at = $now;
                $application->transfer_reference = $transferRef;
                if ($storedPath !== null) {
                    $application->transfer_proof_path = $storedPath;
                    $application->transfer_proof_original_name = $originalName;
                    $application->transfer_proof_mime = $mime;
                    $application->transfer_proof_size = $size;
                    $application->transfer_proof_uploaded_at = $now;
                    $application->transfer_proof_uploaded_by = $userId;
                }

                CreditUnionLoanApplicationAction::query()->create([
                    'application_id' => $application->id,
                    'action' => CreditUnionLoanApplicationAction::ACTION_POSTED,
                    'note' => $note ?? ('Diposting sebagai pinjaman rec_id '.$loanRecId),
                    'actor_user_id' => $userId,
                    'actor_name' => $this->actorName($userId),
                ]);

                $this->writeAudit($request, 'posted', $application->id, [
                    'loan_rec_id' => $loanRecId,
                    'member_icuno' => $application->member_icuno,
                    'transfer_reference' => $transferRef,
                    'transfer_proof' => $storedPath !== null ? [
                        'original_name' => $originalName,
                        'size' => $size,
                        'uploaded_by' => $userId,
                    ] : null,
                ]);

                return $loanRecId;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            return back()->withInput()->withErrors(['decision' => $exception->getMessage()]);
        }

        $netDisbursement = $application->admin_fee_type === 'include'
            ? max(0, (int) $application->principal_amount - (int) $application->admin_fee)
            : (int) $application->principal_amount;

        $bankLabels = $this->bankOptions();
        $bankName = ($bankLabels[$application->bank_bnkcd] ?? $application->bank_bnkcd) ?: '-';
        $accNo = (string) $application->bank_accno;
        $last4 = substr($accNo, -4);
        $maskedAcc = $last4 !== '' ? '••••'.$last4 : '-';
        $bankInfo = trim("{$bankName} — {$application->bank_accnm} ({$maskedAcc})");
        $disbursedDate = $application->disbursed_at ? $application->disbursed_at->format('d M Y') : now()->format('d M Y');

        $message = "Dana pinjaman Anda telah berhasil ditransfer.\n"
            ."• Dana Diterima: Rp ".number_format($netDisbursement, 0, ',', '.')."\n"
            .($application->fund_release_method === 'transfer' ? "• Rekening Tujuan: {$bankInfo}\n" : '')
            ."• Tanggal Transfer: {$disbursedDate}\n"
            ."• Tenor: {$application->tenor_months} bulan\n"
            ."• Cicilan per Bulan: Rp ".number_format((int) $application->monthly_installment, 0, ',', '.')."\n"
            .($transferRef !== null ? "• No. Referensi: {$transferRef}\n" : '')
            ."Silakan periksa rekening Anda dan lihat detail jadwal angsuran melalui aplikasi.";

        $attachments = [];
        if ($storedPath !== null && Storage::disk('local')->exists($storedPath)) {
            $attachments[] = [
                'path' => Storage::disk('local')->path($storedPath),
                'name' => $originalName,
                'mime' => $mime,
            ];
        }

        $this->notifications->notifyMember(
            (int) $application->member_rec_id,
            (int) $application->applicant_user_id,
            $userId,
            'cu.loan_application.disbursed',
            'Dana Pinjaman Telah Ditransfer',
            $message,
            route('cu.applications.detail', ['id' => $application->id]),
            'cu_loan_application',
            (int) $application->id,
            $attachments
        );

        return redirect()
            ->route('cu.applications.detail', ['id' => $application->id] + ($request->input('view') === 'mine' ? ['view' => 'mine'] : []))
            ->with('success', 'Pengajuan berhasil diposting sebagai pinjaman (rec_id '.$loanRecId.').');
    }

    /**
     * Mengunduh / menampilkan berkas bukti transfer dengan izin terkontrol.
     */
    public function transferProof(Request $request): Response
    {
        $id = (int) $request->query('id');
        $application = CreditUnionLoanApplication::query()->findOrFail($id);
        $userId = $this->currentUserId($request);

        $isAdmin = CreditUnionAccess::isAdmin($userId);
        $isOwner = false;
        if (! $isAdmin) {
            $linkedMember = CreditUnionAccess::memberForUser($userId);
            $isOwner = $linkedMember !== null && (int) $application->member_rec_id === (int) $linkedMember->rec_id;
        }

        abort_unless($isAdmin || $isOwner, 403, 'Anda tidak berhak mengakses bukti transfer pengajuan ini.');

        $proofPath = (string) $application->transfer_proof_path;
        if ($proofPath === '' || ! Storage::disk('local')->exists($proofPath)) {
            abort(404, 'Berkas bukti transfer tidak ditemukan.');
        }

        $fullPath = Storage::disk('local')->path($proofPath);
        $originalName = $application->transfer_proof_original_name ?: basename($proofPath);
        $mime = $application->transfer_proof_mime ?: (@mime_content_type($fullPath) ?: 'application/octet-stream');

        if ($request->boolean('download')) {
            return response()->download($fullPath, $originalName, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $originalName).'"',
        ]);
    }

    /**
     * Mengganti berkas bukti transfer oleh admin credit union (hanya sesudah posted).
     */
    public function replaceTransferProof(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'transfer_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'transfer_proof.required' => 'Berkas bukti transfer baru wajib dipilih.',
            'transfer_proof.file' => 'Berkas bukti transfer tidak valid.',
            'transfer_proof.mimes' => 'Format berkas bukti transfer harus berupa JPG, JPEG, PNG, atau PDF.',
            'transfer_proof.max' => 'Ukuran berkas bukti transfer maksimal 5 MB (5120 KB).',
        ]);

        $application = CreditUnionLoanApplication::query()->findOrFail((int) $data['id']);
        $userId = $this->currentUserId($request);

        abort_unless(
            CreditUnionAccess::isAdmin($userId),
            403,
            'Hanya admin credit union yang dapat mengganti bukti transfer.'
        );

        abort_unless(
            $application->status === LoanApplicationService::STATUS_POSTED || $application->posted_loan_rec_id !== null,
            422,
            'Penggantian bukti transfer hanya dapat dilakukan untuk pengajuan yang sudah diposting.'
        );

        $file = $request->file('transfer_proof');
        $originalName = preg_replace('/[^\w\.\-\s]/u', '_', $file->getClientOriginalName()) ?: 'transfer_proof.bin';
        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $size = (int) $file->getSize();

        $oldPath = (string) $application->transfer_proof_path;
        $newPath = $file->store('transfer-proofs/'.$application->id, 'local');

        try {
            DB::connection('run')->transaction(function () use ($request, $application, $userId, $newPath, $originalName, $mime, $size): void {
                $now = now();
                $updatePayload = [
                    'transfer_proof_path' => $newPath,
                    'transfer_proof_original_name' => $originalName,
                    'transfer_proof_mime' => $mime,
                    'transfer_proof_size' => $size,
                    'transfer_proof_uploaded_at' => $now,
                    'transfer_proof_uploaded_by' => $userId,
                ];

                CreditUnionLoanApplication::query()
                    ->whereKey($application->id)
                    ->update($updatePayload);

                $application->transfer_proof_path = $newPath;
                $application->transfer_proof_original_name = $originalName;
                $application->transfer_proof_mime = $mime;
                $application->transfer_proof_size = $size;
                $application->transfer_proof_uploaded_at = $now;
                $application->transfer_proof_uploaded_by = $userId;

                CreditUnionLoanApplicationAction::query()->create([
                    'application_id' => $application->id,
                    'action' => CreditUnionLoanApplicationAction::ACTION_PROOF_REPLACED,
                    'note' => 'Bukti transfer diganti: '.$originalName.' ('.round($size / 1024, 1).' KB)',
                    'actor_user_id' => $userId,
                    'actor_name' => $this->actorName($userId),
                ]);

                $this->writeAudit($request, 'proof_replaced', $application->id, [
                    'original_name' => $originalName,
                    'size' => $size,
                ]);
            });
        } catch (\Throwable $e) {
            if ($newPath !== null && Storage::disk('local')->exists($newPath)) {
                Storage::disk('local')->delete($newPath);
            }

            return back()->withErrors(['proof' => 'Gagal mengganti bukti transfer: '.$e->getMessage()]);
        }

        // Hapus berkas lama hanya setelah berkas baru tersimpan dan baris DB terbarui
        if ($oldPath !== '' && $oldPath !== $newPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()
            ->route('cu.applications.detail', ['id' => $application->id] + ($request->input('view') === 'mine' ? ['view' => 'mine'] : []))
            ->with('success', 'Bukti transfer berhasil diperbarui.');
    }

    /**
     * @return array{member_rec_id: string, principal_amount: string, tenor_months: string, annual_rate_percent: string, calculation_method: string, descr: string, fund_release_method: string, admin_fee: string, admin_fee_type: string, bank_code?: string, account_name?: string, account_no?: string}
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
            'fund_release_method' => ['required', 'string', 'in:transfer'],
            'admin_fee' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'admin_fee_type' => ['required', 'string', 'in:include,exclude'],
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
     * @return Collection<int, string>
     */
    private function bankOptions(): Collection
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
            'action' => 'cu.loan_application.'.$action,
            'target_type' => 'cu_loan_application',
            'target_id' => $applicationId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
