<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeBankTrx;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\LoanPostingService;
use App\Support\CooperativeAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transaksi bank koperasi (icu_bank_trx).
 *
 * Referensi nomor & amount diambil dari icu_mtrx2hrd; amount tetap bisa
 * disesuaikan manual. Admin-only (coop.admin).
 */
class BankTransactionController extends Controller
{
    public function index(): View
    {
        $transactions = CooperativeBankTrx::query()
            ->orderByDesc('rec_id')
            ->paginate(15)
            ->withQueryString();

        return view('cooperative.bank-transactions.index', [
            'transactions' => $transactions,
            'directionLabels' => CooperativeBankTrx::DIRECTION_LABELS,
            'isCoopAdmin' => CooperativeAccess::isAdmin((int) auth_user_id()),
        ]);
    }

    public function create(): View
    {
        // ponytail: referensi dirender semua (dataset kini ~22 baris);
        // ganti ke AJAX kalau icu_mtrx2hrd membesar.
        $references = DB::connection('mysql')->table('icu_mtrx2hrd')
            ->orderByDesc('trxdt')
            ->orderByDesc('rec_id')
            ->get(['trxno', 'trx_amt', 'trxdt', 'cmpcd', 'pprdk'])
            ->map(fn ($row): array => [
                'trxno' => (string) $row->trxno,
                'amount' => (int) $row->trx_amt,
                'date' => (string) $row->trxdt,
                'cmpcd' => (string) $row->cmpcd,
                'pprdk' => (string) $row->pprdk,
            ])
            ->all();

        return view('cooperative.bank-transactions.create', [
            'references' => $references,
            'defaultTrnno' => $this->nextTrnno(),
            'defaultTrndt' => now()->toDateString(),
            'defaultPprdk' => CooperativePeriod::current(),
            'directionLabels' => CooperativeBankTrx::DIRECTION_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $data = $request->validate([
            'req_frm_trxno' => ['required', 'string', 'max:12'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'dbocr' => ['required', 'string', 'in:D,C'],
            'trnno' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
            'trndt' => ['required', 'date'],
            'descr' => ['required', 'string', 'max:100'],
        ]);

        $reference = DB::connection('mysql')->table('icu_mtrx2hrd')
            ->where('trxno', $data['req_frm_trxno'])
            ->first(['trxno', 'trx_amt', 'trxdt', 'pprdk']);

        if (! $reference) {
            return back()->withInput()->withErrors(['req_frm_trxno' => 'Nomor referensi tidak ditemukan pada icu_mtrx2hrd.']);
        }

        if (CooperativeBankTrx::query()->where('trnno', $data['trnno'])->exists()) {
            return back()->withInput()->withErrors(['trnno' => 'Nomor transaksi '.$data['trnno'].' sudah dipakai.']);
        }

        try {
            DB::connection('mysql')->transaction(function () use ($data, $reference, $userId): void {
                $now = now();

                CooperativeBankTrx::query()->insert([
                    'pprdk' => (string) $reference->pprdk,
                    'trnno' => strtoupper(trim((string) $data['trnno'])),
                    'trndt' => (string) $data['trndt'],
                    'req_frm_trxno' => trim((string) $data['req_frm_trxno']),
                    'dbocr' => $data['dbocr'],
                    'icu_rec_id' => 0,
                    'descr' => trim((string) $data['descr']),
                    'amount' => (int) $data['amount'],
                    'statrec' => 0,
                    'edit_enable' => 1,
                    'notes' => '',
                    'confno' => '',
                    'confdt' => (string) $data['trndt'],
                    'lupd' => $now,
                    'entusr' => 'RUN',
                ]);
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['general' => 'Gagal menyimpan transaksi bank: '.$exception->getMessage()]);
        }

        return redirect()
            ->route('cooperative.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$data['trnno'].' ('.CooperativeBankTrx::DIRECTION_LABELS[$data['dbocr']].' Rp '.number_format((int) $data['amount']).') berhasil disimpan.');
    }

    /**
     * Nomor transaksi bank berikutnya mengikuti pola legacy RCV-{YY}{huruf bulan}-{urut}.
     */
    private function nextTrnno(): string
    {
        $sequence = LoanPostingService::nextSequence('icu_bank_trx', 'trnno', 'RCV-%');

        return LoanPostingService::formatLegacyTrnno('RCV', CarbonImmutable::now(), $sequence);
    }
}