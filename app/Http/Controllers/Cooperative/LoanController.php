<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoan;
use App\Models\Cooperative\CooperativeMember;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) auth_user_id();
        $isAdmin = CooperativeAccess::isAdmin($userId);

        // Super Admin / CU Admin melihat seluruh pinjaman anggota; CU Member /
        // User Credit Union hanya melihat pinjaman miliknya sendiri.
        $query = CooperativeLoan::query()->with('member');
        $linkedMember = null;

        if (! $isAdmin) {
            $linkedMember = CooperativeAccess::memberForUser($userId);

            if ($linkedMember === null) {
                // Akun belum ditautkan ke record anggota: jangan tampilkan pinjaman apa pun.
                $query->whereRaw('1 = 0');
            } else {
                $query->forMember($linkedMember->rec_id);
            }
        }

        $loans = (clone $query)
            ->search($request->query('q'))
            ->statusIndicative($request->query('status'))
            ->when($isAdmin, fn (Builder $q) => $q->forMember($request->query('member_id')))
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->paginate(15)
            ->withQueryString();

        $manualNames = Schema::connection('run')->hasTable('coop_manual_loan_sources')
            ? DB::connection('run')->table('coop_manual_loan_sources')
                ->whereIn('loan_rec_id', $loans->getCollection()->pluck('rec_id'))
                ->pluck('member_name', 'loan_rec_id')
            : collect();

        // Statistik ikut dibatasi sesuai scope akses (tidak mengikuti filter pencarian).
        $stats = [
            'total' => (int) (clone $query)->count(),
            'running' => (int) (clone $query)->statusIndicative('running')->count(),
            'settled' => (int) (clone $query)->statusIndicative('settled')->count(),
            // Indikatif: sisa pokok = principle - paid per pinjaman (outstand icu_dloan = sisa pokok).
            'indicative_outstanding' => (int) (clone $query)
                ->selectRaw('COALESCE(SUM(GREATEST(principle - paid, 0)), 0) AS indicative_outstanding')
                ->value('indicative_outstanding'),
        ];

        // Pilihan anggota: admin semesta, anggota biasa hanya dirinya sendiri.
        $memberOptions = $isAdmin
            ? CooperativeMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm'])
            : ($linkedMember !== null ? [$linkedMember] : []);

        return view('cooperative.loans.index', [
            'loans' => $loans,
            'stats' => $stats,
            'isAdmin' => $isAdmin,
            'memberOptions' => $memberOptions,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'member_id' => (string) ($isAdmin ? $request->query('member_id', '') : ($linkedMember?->rec_id ?? '')),
            ],
            'manualNames' => $manualNames,
        ]);
    }

    public function detail(Request $request): View
    {
        $userId = (int) auth_user_id();
        $isAdmin = CooperativeAccess::isAdmin($userId);

        $loan = CooperativeLoan::query()->with('member')->findOrFail((int) $request->query('rec_id'));

        // CU Member / User Credit Union hanya boleh membuka detail pinjaman miliknya sendiri.
        if (! $isAdmin) {
            $member = CooperativeAccess::memberForUser($userId);

            abort_unless(
                $member !== null && (int) $loan->icu_rec_id === (int) $member->rec_id,
                403,
                'Anda hanya dapat melihat pinjaman milik Anda sendiri.'
            );
        }

        $schedules = $loan->schedules()
            ->orderBy('seqno')
            ->get();

        $scheduleTotals = [
            'principal' => (int) $schedules->sum('amount'),
            'interest' => (int) $schedules->sum('int_amt'),
            'others' => (int) $schedules->sum('others'),
        ];

        return view('cooperative.loans.detail', [
            'loan' => $loan,
            'schedules' => $schedules,
            'scheduleTotals' => $scheduleTotals,
            'progressPercent' => $this->progressPercent($loan),
            'manualName' => Schema::connection('run')->hasTable('coop_manual_loan_sources')
                ? DB::connection('run')->table('coop_manual_loan_sources')->where('loan_rec_id', $loan->rec_id)->value('member_name')
                : null,
        ]);
    }

    private function progressPercent(CooperativeLoan $loan): float
    {
        if ($loan->totalloan <= 0) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, $loan->paid / $loan->totalloan * 100)), 1);
    }
}
