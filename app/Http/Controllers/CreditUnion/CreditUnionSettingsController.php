<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Services\CreditUnion\CreditUnionSettingsService;
use App\Services\CreditUnion\LoanSimulationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreditUnionSettingsController extends Controller
{
    public function index(): View
    {
        CreditUnionSettingsService::ensureDefaults();

        return view('credit-union.settings.index', [
            'defaultRate' => CreditUnionSettingsService::defaultRate(),
            'defaultMethod' => CreditUnionSettingsService::defaultMethod(),
            'defaultAdminFee' => CreditUnionSettingsService::defaultAdminFee(),
            'minimumSavingsBalance' => CreditUnionSettingsService::minimumSavingsBalance(),
            'bankAccountBank' => CreditUnionSettingsService::bankAccountBank(),
            'bankAccountNo' => CreditUnionSettingsService::bankAccountNo(),
            'bankAccountName' => CreditUnionSettingsService::bankAccountName(),
            'methods' => LoanSimulationService::METHODS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_method' => ['required', 'string', 'in:'.implode(',', array_keys(LoanSimulationService::METHODS))],
            'default_admin_fee' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'minimum_savings_balance' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'bank_account_bank' => ['nullable', 'string', 'max:60'],
            'bank_account_no' => ['nullable', 'string', 'max:40'],
            'bank_account_name' => ['nullable', 'string', 'max:80'],
        ]);

        $userId = (int) auth_user_id();

        try {
            CreditUnionSettingsService::saveDefaultRate((float) $data['default_rate'], $userId);
            CreditUnionSettingsService::saveDefaultMethod((string) $data['default_method'], $userId);
            CreditUnionSettingsService::saveDefaultAdminFee((int) $data['default_admin_fee'], $userId);
            CreditUnionSettingsService::saveMinimumSavingsBalance((int) $data['minimum_savings_balance'], $userId);
            CreditUnionSettingsService::saveBankAccount(
                (string) ($data['bank_account_bank'] ?? ''),
                (string) ($data['bank_account_no'] ?? ''),
                (string) ($data['bank_account_name'] ?? ''),
                $userId,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['default_rate' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cu.settings.index')
            ->with('success', 'Pengaturan default pinjaman berhasil disimpan.');
    }
}
