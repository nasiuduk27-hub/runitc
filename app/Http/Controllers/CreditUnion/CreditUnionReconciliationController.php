<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionMember;
use App\Models\CreditUnion\CreditUnionReconciliation;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\CreditUnionReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreditUnionReconciliationController extends Controller
{
    public function __construct(private readonly CreditUnionReconciliationService $service) {}

    public function index(Request $request): View
    {
        $scope = (string) $request->query('scope', CreditUnionReconciliationService::SCOPE_BANK);
        $period = (string) $request->query('period', CreditUnionPeriod::current());
        $memberId = $request->integer('member_rec_id') ?: null;
        $snapshot = null;
        $error = null;

        if ($request->boolean('scan')) {
            try {
                $snapshot = $this->service->snapshot($scope, $period, $memberId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('credit-union.reconciliation.index', [
            'scope' => $scope,
            'period' => $period,
            'memberId' => $memberId,
            'snapshot' => $snapshot,
            'error' => $error,
            'scopes' => CreditUnionReconciliationService::SCOPES,
            'members' => CreditUnionMember::query()->where('st_aktif', '!=', CreditUnionMember::STATUS_NON_ACTIVE)->orderBy('icunm')->get(['rec_id', 'icuno', 'icunm']),
            'cases' => CreditUnionReconciliation::query()->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:bank_monthly,savings,loan'],
            'period' => ['nullable', 'regex:/^\d{6}$/'],
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'expected_amount' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $case = $this->service->create($data, (int) auth_user_id(), $this->actorName());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['general' => $exception->getMessage()]);
        }

        return redirect()->route('cu.reconciliation.index')->with('success', 'Kasus '.$case->ref_no.' berhasil diajukan untuk verifikasi.');
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

    private function actorName(): string
    {
        return (string) (\DB::connection('run')->table('sysitc_users')->where('rec_id', auth_user_id())->value('account_nm') ?: 'User-'.auth_user_id());
    }
}
