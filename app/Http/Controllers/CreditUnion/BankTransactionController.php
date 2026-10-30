<?php

namespace App\Http\Controllers\CreditUnion;

use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionBankTrx;
use App\Models\CreditUnion\CreditUnionBankTrxDetail;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\CreditUnionSettingsService;
use App\Services\CreditUnion\LoanPostingService;
use App\Services\CreditUnion\MonthlyPostingService;
use App\Services\CreditUnion\MonthlyProcessingService;
use App\Support\CreditUnionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transaksi bank credit union (icu_bank_trx) — buku rekening credit union.
 *
 * Referensi ke icu_mtrx2hrd (nomor PMT) bersifat opsional: selain penerimaan
 * potong gaji, buku ini mencatat mutasi di luar simpan-pinjam (pencairan
 * pinjaman, biaya bank, koreksi, transfer antar rekening) agar saldo rekening
 * tetap balance dengan sistem. Amount tetap bisa disesuaikan manual.
 * Admin-only (cu.admin).
 */
class BankTransactionController extends Controller
{
    private const DESCR_DEFAULT = 'Collective Debt Note CU Member';

    public function __construct(
        private readonly MonthlyPostingService $monthlyPosting,
        private readonly MonthlyProcessingService $monthlyProcessing,
    ) {}

    public function index(Request $request): View
    {
        $query = $this->baseQuery($request);

        $transactions = (clone $query)
            ->when($request->filled('dbocr'), fn ($inner) => $inner->where('dbocr', $request->query('dbocr')))
            ->orderByDesc('rec_id')
            ->paginate(15)
            ->withQueryString();

        $debitTotal = (int) (clone $query)->where('dbocr', CreditUnionBankTrx::DIRECTION_DEBIT)->sum('amount');
        $creditTotal = (int) (clone $query)->where('dbocr', CreditUnionBankTrx::DIRECTION_CREDIT)->sum('amount');

        $trxIds = $transactions->pluck('rec_id')->all();
        $details = empty($trxIds)
            ? collect()
            : CreditUnionBankTrxDetail::query()->whereIn('bank_trx_id', $trxIds)->get()->keyBy('bank_trx_id');

        return view('credit-union.bank-transactions.index', [
            'transactions' => $transactions,
            'details' => $details,
            'directionLabels' => CreditUnionBankTrx::DIRECTION_LABELS,
            'isCoopAdmin' => CreditUnionAccess::isAdmin((int) auth_user_id()),
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'dbocr' => (string) $request->query('dbocr', ''),
                'period' => (string) $request->query('period', ''),
            ],
            'periods' => CreditUnionBankTrx::query()->where('pprdk', '!=', '')->distinct()->orderByDesc('pprdk')->pluck('pprdk'),
            'periodOptions' => $this->monthlyProcessing->availablePeriods(),
        ]);
    }

    private function baseQuery(Request $request)
    {
        return CreditUnionBankTrx::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $keyword = '%'.str_replace('%', '\%', trim((string) $request->query('q'))).'%';
                $query->where(fn ($inner) => $inner
                    ->where('trnno', 'like', $keyword)
                    ->orWhere('req_frm_trxno', 'like', $keyword)
                    ->orWhere('descr', 'like', $keyword)
                    ->orWhere('notes', 'like', $keyword));
            })
            ->when($request->filled('period'), fn ($query) => $query->where('pprdk', $request->query('period')));
    }

    public function create(): View
    {
        // ponytail: referensi dirender semua (dataset kini ~22 baris);
        // ganti ke AJAX kalau icu_mtrx2hrd membesar.
        $companyNames = $this->companyNames();

        $references = DB::connection('mysql')->table('icu_mtrx2hrd')
            ->orderByDesc('trxdt')
            ->orderByDesc('rec_id')
            ->get(['trxno', 'trx_amt', 'trxdt', 'cmpcd', 'pprdk'])
            ->map(fn ($row): array => [
                'trxno' => (string) $row->trxno,
                'amount' => (int) $row->trx_amt,
                'date' => (string) $row->trxdt,
                'cmpcd' => (string) $row->cmpcd,
                'cmpnm' => $companyNames[(string) $row->cmpcd] ?? (string) $row->cmpcd,
                'pprdk' => (string) $row->pprdk,
            ])
            ->all();

        $bankAccount = CreditUnionSettingsService::bankAccount();

        return view('credit-union.bank-transactions.create', [
            'references' => $references,
            'bankAccount' => $bankAccount,
            'defaultTrnno' => $this->nextTrnno(),
            'defaultTrndt' => now()->toDateString(),
            'defaultPprdk' => CreditUnionPeriod::current(),
            'defaultDescr' => self::DESCR_DEFAULT,
            'directionLabels' => CreditUnionBankTrx::DIRECTION_LABELS,
            'categoryLabels' => CreditUnionBankTrxDetail::CATEGORY_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CreditUnionAccess::isAdmin($userId), 403);

        $category = $request->input('category');
        if (! in_array($category, CreditUnionBankTrxDetail::CATEGORIES, true)) {
            $category = null;
        }

        $bankAccount = CreditUnionSettingsService::bankAccount();

        if ($category === CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE) {
            $data = $request->validate([
                'admin_fee_amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'trnno' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
                'trndt' => ['required', 'date_format:Y-m-d'],
                'descr' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:50'],
            ]);

            $amount = (int) $data['admin_fee_amount'];
            $dbocr = CreditUnionBankTrx::DIRECTION_CREDIT;
            $gross = $amount;
            $tax = 0;
            $net = $amount;
            $req_frm_trxno = '';
            $notes = (string) ($data['notes'] ?? '');
            $pprdk = CarbonImmutable::parse($data['trndt'])->format('Ym');
        } elseif ($category === CreditUnionBankTrxDetail::CATEGORY_INTEREST) {
            $data = $request->validate([
                'gross_amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'tax_amount' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                'trnno' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
                'trndt' => ['required', 'date_format:Y-m-d'],
                'descr' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:50'],
            ]);

            $gross = (int) $data['gross_amount'];
            $tax = (int) ($data['tax_amount'] ?? 0);
            if ($tax > $gross) {
                return back()->withInput()->withErrors(['tax_amount' => 'Pajak bunga tidak boleh lebih besar dari bunga bruto.']);
            }
            $net = $gross - $tax;
            $amount = $net;
            $dbocr = CreditUnionBankTrx::DIRECTION_DEBIT;
            $req_frm_trxno = '';
            $notes = (string) ($data['notes'] ?? '');
            $pprdk = CarbonImmutable::parse($data['trndt'])->format('Ym');
        } else {
            $data = $request->validate([
                'req_frm_trxno' => ['nullable', 'string', 'max:12'],
                'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'dbocr' => ['required', 'string', 'in:D,C'],
                'trnno' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
                'trndt' => ['required', 'date_format:Y-m-d'],
                'descr' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:50'],
            ]);

            $companyNote = '';
            if (trim((string) $data['req_frm_trxno']) !== '') {
                $reference = DB::connection('mysql')->table('icu_mtrx2hrd')
                    ->where('trxno', $data['req_frm_trxno'])
                    ->first(['trxno', 'trx_amt', 'trxdt', 'cmpcd']);

                if (! $reference) {
                    return back()->withInput()->withErrors(['req_frm_trxno' => 'Nomor referensi tidak ditemukan pada tagihan HRD.']);
                }

                $companyNote = $this->companyNames()[(string) $reference->cmpcd] ?? '';
            }

            $amount = (int) $data['amount'];
            $dbocr = $data['dbocr'];
            $gross = $amount;
            $tax = 0;
            $net = $amount;
            $req_frm_trxno = trim((string) ($data['req_frm_trxno'] ?? ''));
            $notes = (string) ($data['notes'] ?? $companyNote);
            $pprdk = CreditUnionPeriod::current();
        }

        if (CreditUnionBankTrx::query()->where('trnno', $data['trnno'])->exists()) {
            return back()->withInput()->withErrors(['trnno' => 'Nomor transaksi '.$data['trnno'].' sudah dipakai.']);
        }

        try {
            $now = now();
            $insertedId = CreditUnionBankTrx::query()->insertGetId([
                'pprdk' => $pprdk,
                'trnno' => strtoupper(trim((string) $data['trnno'])),
                'trndt' => (string) $data['trndt'],
                'req_frm_trxno' => $req_frm_trxno,
                'dbocr' => $dbocr,
                'icu_rec_id' => 0,
                'descr' => trim((string) $data['descr']),
                'amount' => $amount,
                'statrec' => 0,
                'edit_enable' => 1,
                'notes' => mb_substr(trim($notes), 0, 50),
                'confno' => '',
                'confdt' => $now->toDateString(),
                'lupd' => $now,
                'entusr' => $this->currentUserAlias(),
            ]);

            if ($category !== null) {
                try {
                    CreditUnionBankTrxDetail::query()->create([
                        'bank_trx_id' => $insertedId,
                        'category' => $category,
                        'bank_account_bank' => $bankAccount['bank'] ?? null,
                        'bank_account_no' => $bankAccount['account_no'] ?? null,
                        'bank_account_name' => $bankAccount['account_name'] ?? null,
                        'gross_amount' => $gross,
                        'tax_amount' => $tax,
                        'net_amount' => $net,
                        'notes' => mb_substr(trim($notes), 0, 255),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                } catch (Throwable $detailException) {
                    CreditUnionBankTrx::query()->whereKey($insertedId)->delete();
                    throw $detailException;
                }
            }
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['general' => 'Gagal menyimpan transaksi bank: '.$exception->getMessage()]);
        }

        return redirect()
            ->route('cu.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$data['trnno'].' ('.CreditUnionBankTrx::DIRECTION_LABELS[$dbocr].' Rp '.number_format($amount, 0, ',', '.').') berhasil disimpan.');
    }

    public function postMonthly(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if (! CreditUnionPeriod::isValid($data['period'])) {
            return back()->withErrors(['period' => 'Periode harus berupa YYYYMM yang valid.']);
        }

        $result = $this->monthlyPosting->postAll($data['period'], (int) auth_user_id());

        $message = 'Posting periode '.$data['period'].' selesai: '
            .$result['savings'].' simpanan & '.$result['installments'].' angsuran diposting '
            .'(total Rp '.number_format($result['total'], 0, ',', '.').').';

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris dilewati.';
        }

        return redirect()
            ->route('cu.bank-transactions.index')
            ->with('success', $message)
            ->with('postErrors', $result['errors']);
    }

    public function edit(int $id): View
    {
        $trx = CreditUnionBankTrx::query()->findOrFail($id);
        abort_if((int) $trx->statrec !== 0 || (int) $trx->edit_enable !== 1, 403, 'Transaksi bank tidak dapat diedit.');

        $detail = CreditUnionBankTrxDetail::query()->where('bank_trx_id', $trx->rec_id)->first();
        $bankAccount = CreditUnionSettingsService::bankAccount();

        return view('credit-union.bank-transactions.edit', [
            'trx' => $trx,
            'detail' => $detail,
            'bankAccount' => $bankAccount,
            'directionLabels' => CreditUnionBankTrx::DIRECTION_LABELS,
            'categoryLabels' => CreditUnionBankTrxDetail::CATEGORY_LABELS,
            'defaultTrndt' => now()->toDateString(),
            'defaultPprdk' => CreditUnionPeriod::current(),
            'defaultDescr' => self::DESCR_DEFAULT,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CreditUnionAccess::isAdmin($userId), 403);

        $trx = CreditUnionBankTrx::query()->findOrFail($id);
        abort_if((int) $trx->statrec !== 0 || (int) $trx->edit_enable !== 1, 403, 'Transaksi bank tidak dapat diubah.');

        $detail = CreditUnionBankTrxDetail::query()->where('bank_trx_id', $trx->rec_id)->first();

        if ($detail) {
            if ($detail->category === CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE) {
                $data = $request->validate([
                    'admin_fee_amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                    'trndt' => ['required', 'date_format:Y-m-d'],
                    'descr' => ['required', 'string', 'max:100'],
                    'notes' => ['nullable', 'string', 'max:50'],
                ]);

                $amount = (int) $data['admin_fee_amount'];
                $dbocr = CreditUnionBankTrx::DIRECTION_CREDIT;
                $gross = $amount;
                $tax = 0;
                $net = $amount;
            } else {
                $data = $request->validate([
                    'gross_amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                    'tax_amount' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                    'trndt' => ['required', 'date_format:Y-m-d'],
                    'descr' => ['required', 'string', 'max:100'],
                    'notes' => ['nullable', 'string', 'max:50'],
                ]);

                $gross = (int) $data['gross_amount'];
                $tax = (int) ($data['tax_amount'] ?? 0);
                if ($tax > $gross) {
                    return back()->withInput()->withErrors(['tax_amount' => 'Pajak bunga tidak boleh lebih besar dari bunga bruto.']);
                }
                $net = $gross - $tax;
                $amount = $net;
                $dbocr = CreditUnionBankTrx::DIRECTION_DEBIT;
            }

            $pprdk = CarbonImmutable::parse($data['trndt'])->format('Ym');

            $originalDetail = [
                'gross_amount' => $detail->gross_amount,
                'tax_amount' => $detail->tax_amount,
                'net_amount' => $detail->net_amount,
                'notes' => $detail->notes,
                'updated_by' => $detail->updated_by,
            ];

            try {
                // Langkah 1: Update detail di koneksi run terlebih dahulu
                $detail->update([
                    'gross_amount' => $gross,
                    'tax_amount' => $tax,
                    'net_amount' => $net,
                    'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 255),
                    'updated_by' => $userId,
                ]);

                // Langkah 2: Update baris icu_bank_trx dalam transaksi mysql
                try {
                    DB::connection('mysql')->transaction(function () use ($trx, $data, $amount, $dbocr, $pprdk): void {
                        CreditUnionBankTrx::query()->whereKey($trx->rec_id)->update([
                            'pprdk' => $pprdk,
                            'trndt' => (string) $data['trndt'],
                            'dbocr' => $dbocr,
                            'descr' => trim((string) $data['descr']),
                            'amount' => $amount,
                            'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 50),
                            'lupd' => now(),
                        ]);
                    });
                } catch (Throwable $trxException) {
                    // Kompensasi: kembalikan detail ke nilai asal bila update induk gagal
                    try {
                        $detail->update($originalDetail);
                    } catch (Throwable) {
                        // best effort rollback
                    }
                    throw $trxException;
                }
            } catch (Throwable $exception) {
                return back()->withInput()->withErrors(['general' => 'Gagal memperbarui transaksi bank: '.$exception->getMessage()]);
            }
        } else {
            $data = $request->validate([
                'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'dbocr' => ['required', 'string', 'in:D,C'],
                'trndt' => ['required', 'date_format:Y-m-d'],
                'descr' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:50'],
            ]);

            try {
                DB::connection('mysql')->transaction(function () use ($trx, $data): void {
                    CreditUnionBankTrx::query()->whereKey($trx->rec_id)->update([
                        'trndt' => (string) $data['trndt'],
                        'dbocr' => $data['dbocr'],
                        'descr' => trim((string) $data['descr']),
                        'amount' => (int) $data['amount'],
                        'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 50),
                        'lupd' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                return back()->withInput()->withErrors(['general' => 'Gagal memperbarui transaksi bank: '.$exception->getMessage()]);
            }
        }

        return redirect()
            ->route('cu.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$trx->trnno.' berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CreditUnionAccess::isAdmin($userId), 403);

        $trx = CreditUnionBankTrx::query()->findOrFail($id);
        abort_if((int) $trx->statrec !== 0 || (int) $trx->edit_enable !== 1, 403, 'Transaksi bank tidak dapat dihapus.');

        $detail = CreditUnionBankTrxDetail::query()->where('bank_trx_id', $trx->rec_id)->first();

        try {
            // Langkah 1: Hapus icu_bank_trx dan catat audit di dalam transaksi terlebih dahulu
            DB::connection('mysql')->transaction(function () use ($trx, $request, $userId, $detail): void {
                CreditUnionBankTrx::query()->whereKey($trx->rec_id)->delete();

                DB::connection('run')->table('sys_audit_log')->insert([
                    'actor_user_id' => $userId,
                    'action' => 'cu.bank_transaction.deleted',
                    'target_type' => 'icu_bank_trx',
                    'target_id' => (int) $trx->rec_id,
                    'metadata_json' => json_encode([
                        'trnno' => $trx->trnno,
                        'req_frm_trxno' => $trx->req_frm_trxno,
                        'amount' => (int) $trx->amount,
                        'dbocr' => $trx->dbocr,
                        'category' => $detail?->category,
                        'gross_amount' => $detail?->gross_amount,
                        'tax_amount' => $detail?->tax_amount,
                        'net_amount' => $detail?->net_amount,
                    ], JSON_UNESCAPED_UNICODE),
                    'ip_address' => (string) $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['general' => 'Gagal menghapus transaksi bank: '.$exception->getMessage()]);
        }

        // Langkah 2: Hapus baris detail di koneksi run; bila gagal jangan gagalkan respons
        if ($detail) {
            try {
                $detail->delete();
            } catch (Throwable $detailEx) {
                Log::warning('Gagal menghapus detail transaksi bank (bank_trx_id: '.$trx->rec_id.'): '.$detailEx->getMessage());
            }
        }

        return redirect()
            ->route('cu.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$trx->trnno.' berhasil dihapus.');
    }

    /**
     * Nomor transaksi bank berikutnya mengikuti pola legacy RCV-{YY}{huruf bulan}-{urut}.
     */
    private function nextTrnno(): string
    {
        $sequence = LoanPostingService::nextSequence('icu_bank_trx', 'trnno', 'RCV-%');

        return LoanPostingService::formatLegacyTrnno('RCV', CarbonImmutable::now(), $sequence);
    }

    /**
     * Alias 3 huruf user aktif dari sysitc_users.acc3chrnm; fallback 'RUN'.
     */
    private function currentUserAlias(): string
    {
        $userId = (int) auth_user_id();
        if ($userId <= 0) {
            return 'RUN';
        }

        $alias = DB::connection('run')->table('sysitc_users')
            ->where('rec_id', $userId)
            ->value('acc3chrnm');

        return mb_strtoupper(mb_substr(trim((string) $alias), 0, 3)) ?: 'RUN';
    }

    /**
     * Nama perusahaan per kode cmpcd dari master sys_msttable (tbl_code 54).
     *
     * @return array<string, string>
     */
    private function companyNames(): array
    {
        return DB::connection('mysql')->table('sys_msttable')
            ->where('tbl_code', '54')
            ->get(['code', 'descr'])
            ->mapWithKeys(fn ($row): array => [(string) $row->code => trim((string) $row->descr)])
            ->all();
    }
}
