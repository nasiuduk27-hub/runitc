<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionReconciliation;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\CreditUnionReconciliationService;
use App\Services\CreditUnion\MonthlyProcessingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreditUnionReconciliationController extends Controller
{
    public function __construct(
        private readonly CreditUnionReconciliationService $service,
        private readonly MonthlyProcessingService $processing,
    ) {}

    public function index(Request $request): View
    {
        $scope = (string) $request->query('scope', CreditUnionReconciliationService::SCOPE_BANK);
        $period = (string) $request->query('period', CreditUnionPeriod::current());
        $snapshot = null;
        $memberRows = [];
        $error = null;

        if ($request->boolean('scan')) {
            try {
                if ($scope === CreditUnionReconciliationService::SCOPE_BANK) {
                    $snapshot = $this->service->snapshot($scope, $period);
                } else {
                    $memberRows = $this->service->memberRows($scope);
                }
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('credit-union.reconciliation.index', [
            'scope' => $scope,
            'period' => $period,
            'snapshot' => $snapshot,
            'memberRows' => $memberRows,
            'error' => $error,
            'scopes' => CreditUnionReconciliationService::SCOPES,
            'periodOptions' => $this->processing->availablePeriods(),
            'pending' => CreditUnionReconciliation::query()
                ->where('status', CreditUnionReconciliation::STATUS_SUBMITTED)
                ->latest()->get(),
            'history' => CreditUnionReconciliation::query()
                ->where('status', '!=', CreditUnionReconciliation::STATUS_SUBMITTED)
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $base = $request->validate([
            'scope' => ['required', 'in:bank_monthly,savings,loan'],
            'period' => ['nullable', 'regex:/^\d{6}$/'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $userId = (int) auth_user_id();

        try {
            if ($base['scope'] === CreditUnionReconciliationService::SCOPE_BANK) {
                $extra = $request->validate([
                    'expected_amount' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
                ]);
                $case = $this->service->create($base + [
                    'member_rec_id' => null,
                    'expected_amount' => $extra['expected_amount'],
                ], $userId, $this->actorName());

                return redirect()->route('cu.reconciliation.index')
                    ->with('success', 'Kasus '.$case->ref_no.' berhasil diajukan untuk verifikasi.');
            }

            $extra = $request->validate([
                'expected' => ['required', 'array'],
                'expected.*' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            ]);
            $result = $this->service->storeBatch(
                $base['scope'],
                $base['period'] ?: null,
                $extra['expected'],
                $base['reason'],
                $userId,
                $this->actorName(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['general' => $exception->getMessage()]);
        }

        return redirect()->route('cu.reconciliation.index')
            ->with('success', $result['created'].' koreksi diajukan pada batch '.$result['batch_ref'].'.');
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required', 'string', 'max:500'],
        ]);
        $case = CreditUnionReconciliation::query()->findOrFail($id);

        try {
            if ($data['decision'] === 'approve') {
                $this->service->approve($case, (int) auth_user_id(), $this->actorName(), $data['note']);
            } else {
                $this->service->reject($case, (int) auth_user_id(), $this->actorName(), $data['note']);
            }
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['general' => $exception->getMessage()]);
        }

        return back()->with('success', 'Kasus '.$case->ref_no.' berhasil diproses.');
    }

    public function decideBatch(Request $request, string $batchRef): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $data['decision'] === 'approve'
                ? $this->service->approveBatch($batchRef, (int) auth_user_id(), $this->actorName(), $data['note'])
                : $this->service->rejectBatch($batchRef, (int) auth_user_id(), $this->actorName(), $data['note']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['general' => $exception->getMessage()]);
        }

        $message = $result['approved'].' kasus berhasil diproses pada batch '.$batchRef.'.';
        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' gagal.';
        }

        return back()->with('success', $message)->with('batchErrors', $result['errors']);
    }

    private function actorName(): string
    {
        return (string) (\DB::connection('run')->table('sysitc_users')->where('rec_id', auth_user_id())->value('account_nm') ?: 'User-'.auth_user_id());
    }
}
