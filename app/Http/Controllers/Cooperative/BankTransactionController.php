<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeBankTrx;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\LoanPostingService;
use App\Services\Cooperative\MonthlyPostingService;
use App\Support\CooperativeAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transaksi bank koperasi (icu_bank_trx) — buku rekening koperasi.
 *
 * Referensi ke icu_mtrx2hrd (nomor PMT) bersifat opsional: selain penerimaan
 * potong gaji, buku ini mencatat mutasi di luar simpan-pinjam (pencairan
 * pinjaman, biaya bank, koreksi, transfer antar rekening) agar saldo rekening
 * tetap balance dengan sistem. Amount tetap bisa disesuaikan manual.
 * Admin-only (coop.admin).
 */
class BankTransactionController extends Controller
{
    private const DESCR_DEFAULT = 'Collective Debt Note CU Member';

    public function __construct(private readonly MonthlyPostingService $monthlyPosting) {}

    public function index(Request $request): View
    {
        $query = $this->baseQuery($request);

        $transactions = (clone $query)
            ->when($request->filled('dbocr'), fn ($inner) => $inner->where('dbocr', $request->query('dbocr')))
            ->orderByDesc('rec_id')
            ->paginate(15)
            ->withQueryString();

        $debitTotal = (int) (clone $query)->where('dbocr', CooperativeBankTrx::DIRECTION_DEBIT)->sum('amount');
        $creditTotal = (int) (clone $query)->where('dbocr', CooperativeBankTrx::DIRECTION_CREDIT)->sum('amount');

        return view('cooperative.bank-transactions.index', [
            'transactions' => $transactions,
            'directionLabels' => CooperativeBankTrx::DIRECTION_LABELS,
            'isCoopAdmin' => CooperativeAccess::isAdmin((int) auth_user_id()),
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'dbocr' => (string) $request->query('dbocr', ''),
                'period' => (string) $request->query('period', ''),
            ],
            'periods' => CooperativeBankTrx::query()->where('pprdk', '!=', '')->distinct()->orderByDesc('pprdk')->pluck('pprdk'),
        ]);
    }

    private function baseQuery(Request $request)
    {
        return CooperativeBankTrx::query()
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

        return view('cooperative.bank-transactions.create', [
            'references' => $references,
            'defaultTrnno' => $this->nextTrnno(),
            'defaultTrndt' => now()->toDateString(),
            'defaultPprdk' => CooperativePeriod::current(),
            'defaultDescr' => self::DESCR_DEFAULT,
            'directionLabels' => CooperativeBankTrx::DIRECTION_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $data = $request->validate([
            'req_frm_trxno' => ['nullable', 'string', 'max:12'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'dbocr' => ['required', 'string', 'in:D,C'],
            'trnno' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
            'trndt' => ['required', 'date'],
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

        if (CooperativeBankTrx::query()->where('trnno', $data['trnno'])->exists()) {
            return back()->withInput()->withErrors(['trnno' => 'Nomor transaksi '.$data['trnno'].' sudah dipakai.']);
        }

        $notes = $companyNote;

        try {
            DB::connection('mysql')->transaction(function () use ($data, $notes): void {
                $now = now();

                CooperativeBankTrx::query()->insert([
                    'pprdk' => CooperativePeriod::current(),
                    'trnno' => strtoupper(trim((string) $data['trnno'])),
                    'trndt' => (string) $data['trndt'],
                    'req_frm_trxno' => trim((string) ($data['req_frm_trxno'] ?? '')),
                    'dbocr' => $data['dbocr'],
                    'icu_rec_id' => 0,
                    'descr' => trim((string) $data['descr']),
                    'amount' => (int) $data['amount'],
                    'statrec' => 0,
                    'edit_enable' => 1,
                    'notes' => mb_substr(trim((string) ($data['notes'] ?? $notes)), 0, 50),
                    'confno' => '',
                    'confdt' => $now->toDateString(),
                    'lupd' => $now,
                    'entusr' => $this->currentUserAlias(),
                ]);
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['general' => 'Gagal menyimpan transaksi bank: '.$exception->getMessage()]);
        }

        return redirect()
            ->route('cooperative.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$data['trnno'].' ('.CooperativeBankTrx::DIRECTION_LABELS[$data['dbocr']].' Rp '.number_format((int) $data['amount']).') berhasil disimpan.');
    }

    public function postMonthly(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if (! CooperativePeriod::isValid($data['period'])) {
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
            ->route('cooperative.bank-transactions.index')
            ->with('success', $message)
            ->with('postErrors', $result['errors']);
    }

    public function edit(int $id): View
    {
        $trx = CooperativeBankTrx::query()->findOrFail($id);

        return view('cooperative.bank-transactions.create', [
            'trx' => $trx,
            'defaultTrndt' => now()->toDateString(),
            'defaultPprdk' => CooperativePeriod::current(),
            'defaultDescr' => self::DESCR_DEFAULT,
            'directionLabels' => CooperativeBankTrx::DIRECTION_LABELS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $trx = CooperativeBankTrx::query()->findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'dbocr' => ['required', 'string', 'in:D,C'],
            'trndt' => ['required', 'date'],
            'descr' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            DB::connection('mysql')->transaction(function () use ($trx, $data): void {
                CooperativeBankTrx::query()->whereKey($trx->rec_id)->update([
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

        return redirect()
            ->route('cooperative.bank-transactions.index')
            ->with('success', 'Transaksi bank '.$trx->trnno.' berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $trx = CooperativeBankTrx::query()->findOrFail($id);

        DB::connection('mysql')->transaction(function () use ($trx, $request, $userId): void {
            CooperativeBankTrx::query()->whereKey($trx->rec_id)->delete();

            DB::connection('run')->table('sys_audit_log')->insert([
                'actor_user_id' => $userId,
                'action' => 'cooperative.bank_transaction.deleted',
                'target_type' => 'icu_bank_trx',
                'target_id' => (int) $trx->rec_id,
                'metadata_json' => json_encode([
                    'trnno' => $trx->trnno,
                    'req_frm_trxno' => $trx->req_frm_trxno,
                    'amount' => (int) $trx->amount,
                    'dbocr' => $trx->dbocr,
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('cooperative.bank-transactions.index')
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
