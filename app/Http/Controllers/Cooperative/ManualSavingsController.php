<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeMember;
use App\Services\Cooperative\ManualSavingsService;
use App\Services\Cooperative\SavingsService;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManualSavingsController extends Controller
{
    public function __construct(private readonly ManualSavingsService $service) {}

    public function createSavings(): View
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);

        return view('cooperative.manual-savings.create', [
            'members' => CooperativeMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']),
        ]);
    }

    public function storeSavings(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'trndt' => ['required', 'date'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CooperativeMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd']);

                return $this->service->postSavings($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], 'tunai', null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Simpanan manual tercatat sebagai '.$trnno.'.');
    }

    public function createWithdraw(): View
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);

        $members = CooperativeMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']);

        $balances = DB::connection('mysql')->table('icu_transaction')
            ->where('trncd', SavingsService::TRNCD_SAVINGS)
            ->groupBy('icu_rec_id')
            ->selectRaw('icu_rec_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN dbocr = 'D' THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN dbocr = 'C' THEN amount ELSE 0 END), 0) AS balance")
            ->pluck('balance', 'icu_rec_id')
            ->map(fn ($value): int => (int) $value);

        return view('cooperative.manual-withdrawals.create', [
            'members' => $members,
            'memberBalances' => $balances,
        ]);
    }

    public function storeWithdraw(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'trndt' => ['required', 'date'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CooperativeMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd']);

                return $this->service->postWithdrawal($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], null, null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Withdraw manual tercatat sebagai '.$trnno.'.');
    }
}