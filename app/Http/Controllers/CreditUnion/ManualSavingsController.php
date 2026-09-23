<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionMember;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\ManualSavingsService;
use App\Support\CreditUnionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManualSavingsController extends Controller
{
    /**
     * Kode transaksi yang tampil pada mutasi anggota:
     * 18 = simpanan sekali, 19 = simpanan bulanan, 20 = angsuran pinjaman,
     * 22 = penarikan.
     */
    private const HISTORY_TRNCDS = ['18', '19', '20', '22'];

    /** Label jenis transaksi per (trncd, dbocr). */
    private const TYPE_LABELS = [
        '18' => ['D' => 'One Time Saving', 'C' => 'One Time Saving'],
        '19' => ['D' => 'Monthly Saving', 'C' => 'Penarikan'],
        '20' => ['D' => 'Loan Payment', 'C' => 'Loan Payment'],
        '22' => ['D' => 'Withdraw Money', 'C' => 'Withdraw Money'],
    ];

    public function __construct(private readonly ManualSavingsService $service) {}

    public function create(): View
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);

        return view('credit-union.manual-transactions.create', [
            'members' => CreditUnionMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']),
            'period' => CreditUnionPeriod::current(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['required', 'integer', 'min:1'],
            'direction' => ['nullable', 'in:all,D,C'],
        ]);

        $member = CreditUnionMember::query()->findOrFail((int) $data['member_rec_id']);
        $direction = (string) ($data['direction'] ?? 'all');

        $rows = DB::connection('mysql')->table('icu_transaction')
            ->where('icu_rec_id', $member->rec_id)
            ->whereIn('trncd', self::HISTORY_TRNCDS)
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->get(['pprd', 'trncd', 'trnno', 'trndt', 'dbocr', 'descr', 'amount']);

        $mapped = $rows->map(function ($row) use ($member): array {
            $trncd = (string) $row->trncd;
            $dbocr = (string) $row->dbocr;
            $amount = (int) $row->amount;

            return [
                'pprd' => (string) $row->pprd,
                'trncd' => $trncd,
                'trnno' => (string) $row->trnno,
                'trndt' => $row->trndt ? substr((string) $row->trndt, 0, 10) : '',
                'cu_id' => $member->icuno,
                'descr' => (string) $row->descr,
                'type_label' => self::TYPE_LABELS[$trncd][$dbocr] ?? 'Lainnya',
                'debit' => $dbocr === 'D' ? $amount : 0,
                'credit' => $dbocr === 'C' ? $amount : 0,
            ];
        });

        if ($direction === 'D') {
            $mapped = $mapped->filter(fn (array $row): bool => $row['debit'] > 0)->values();
        } elseif ($direction === 'C') {
            $mapped = $mapped->filter(fn (array $row): bool => $row['credit'] > 0)->values();
        }

        $debit = (int) $mapped->sum('debit');
        $credit = (int) $mapped->sum('credit');

        return response()->json([
            'rows' => array_values($mapped->all()),
            'totals' => ['debit' => $debit, 'credit' => $credit, 'saldo' => $debit - $credit],
        ]);
    }

    public function storeSavings(Request $request): RedirectResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'trndt' => ['required', 'date_format:Y-m-d'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'saving_type' => ['nullable', 'in:monthly,one_time'],
        ]);

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CreditUnionMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd']);

                return $this->service->postSavings($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], 'tunai', null, (int) auth_user_id(), (string) ($data['saving_type'] ?? 'monthly'));
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Simpanan manual tercatat sebagai '.$trnno.'.');
    }

    public function storeLoanPayment(Request $request): RedirectResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'trndt' => ['required', 'date_format:Y-m-d'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CreditUnionMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd']);

                return $this->service->postLoanPayment($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Angsuran manual tercatat sebagai '.$trnno.'.');
    }

    public function storeWithdraw(Request $request): RedirectResponse
    {
        $currentUserId = (int) auth_user_id();
        abort_unless(CreditUnionAccess::isAdmin($currentUserId), 403);
        $data = $request->validate([
            'member_rec_id' => ['nullable', 'integer', 'min:1'],
            'member_name' => ['required', 'string', 'max:100'],
            'trndt' => ['required', 'date_format:Y-m-d'],
            'pprd' => ['required', 'regex:/^\d{6}$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);

        if (! empty($data['member_rec_id']) && CreditUnionAccess::isOwnerOrMaker($currentUserId, null, (int) $data['member_rec_id'])) {
            return back()->withInput()->withErrors(['manual' => 'Admin tidak dapat melakukan withdraw manual untuk rekening sendiri. Silakan ajukan melalui menu penarikan simpanan agar disetujui oleh admin lain.']);
        }

        try {
            $trnno = DB::connection('mysql')->transaction(function () use ($data): string {
                $member = CreditUnionMember::resolveHistorical((int) $data['member_rec_id'], (string) $data['member_name'], (string) $data['pprd']);

                return $this->service->postWithdrawal($member, (string) $data['pprd'], (string) $data['trndt'], (int) $data['amount'], null, null, (int) auth_user_id());
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }

        return back()->with('success', 'Withdraw manual tercatat sebagai '.$trnno.'.');
    }
}
