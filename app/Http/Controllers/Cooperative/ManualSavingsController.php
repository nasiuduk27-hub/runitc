<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeMember;
use App\Services\Cooperative\ManualSavingsService;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
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
            'member_status' => ['nullable', 'in:active,inactive'],
            'trndt' => ['required', 'date'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['required', 'in:tunai,transfer'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CooperativeMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd'], (string) ($data['member_status'] ?? 'inactive'));

                return $this->service->postSavings($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], (string) $data['method'], $data['notes'] ?? null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Simpanan manual tercatat sebagai '.$trnno.'.');
    }

    public function createWithdraw(): View
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);

        return view('cooperative.manual-withdrawals.create', [
            'members' => CooperativeMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']),
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function storeWithdraw(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'member_status' => ['nullable', 'in:active,inactive'],
            'trndt' => ['required', 'date'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_no' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $bank = $this->bankSnapshot($data);
        if ($bank === null) {
            return back()->withInput()->withErrors(['bank_code' => 'Data rekening tujuan wajib lengkap jika diisi.']);
        }

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data, $bank): string {
                $member = CooperativeMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd'], (string) ($data['member_status'] ?? 'inactive'));

                return $this->service->postWithdrawal($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], $bank, $data['reason'] ?? null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Withdraw manual tercatat sebagai '.$trnno.'.');
    }

    /**
     * Snapshot rekening tujuan: wajib lengkap bila salah satu field diisi.
     *
     * @param  array<string, mixed>  $data
     * @return array{bank_account: string, bank_bnkcd: string, bank_accnm: string, bank_accno: string}|null
     */
    private function bankSnapshot(array $data): ?array
    {
        $code = trim((string) ($data['bank_code'] ?? ''));
        $name = trim((string) ($data['account_name'] ?? ''));
        $no = trim((string) ($data['account_no'] ?? ''));

        if ($code === '' && $name === '' && $no === '') {
            return null;
        }

        if ($code === '' || $name === '' || $no === '') {
            return null;
        }

        $label = (string) ($this->bankOptions()[$code] ?? $code);

        return [
            'bank_account' => mb_substr($label.' - '.$name.' ('.$no.')', 0, 120),
            'bank_bnkcd' => $code,
            'bank_accnm' => $name,
            'bank_accno' => $no,
        ];
    }

    /**
     * @return Collection<int|string, string>
     */
    private function bankOptions(): Collection
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
}