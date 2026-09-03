<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Services\Cooperative\CooperativeSettingsService;
use App\Services\Cooperative\LoanSimulationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CooperativeSettingsController extends Controller
{
    public function index(): View
    {
        CooperativeSettingsService::ensureDefaults();

        return view('cooperative.settings.index', [
            'defaultRate' => CooperativeSettingsService::defaultRate(),
            'defaultMethod' => CooperativeSettingsService::defaultMethod(),
            'defaultAdminFee' => CooperativeSettingsService::defaultAdminFee(),
            'minimumSavingsBalance' => CooperativeSettingsService::minimumSavingsBalance(),
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
        ]);

        $userId = (int) auth_user_id();

        try {
            CooperativeSettingsService::saveDefaultRate((float) $data['default_rate'], $userId);
            CooperativeSettingsService::saveDefaultMethod((string) $data['default_method'], $userId);
            CooperativeSettingsService::saveDefaultAdminFee((int) $data['default_admin_fee'], $userId);
            CooperativeSettingsService::saveMinimumSavingsBalance((int) $data['minimum_savings_balance'], $userId);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['default_rate' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cooperative.settings.index')
            ->with('success', 'Pengaturan default pinjaman berhasil disimpan.');
    }
}
