<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeMember;
use App\Models\Cooperative\CooperativeLoan;
use App\Models\Cooperative\CooperativeLoanSkip;
use App\Services\Cooperative\ManualLoanService;
use App\Services\Cooperative\LoanSkipService;
use App\Support\CooperativeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ManualLoanController extends Controller
{
    public function __construct(private readonly ManualLoanService $service, private readonly LoanSkipService $skips) {}

    public function create(): View
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);

        $loanIds = \Illuminate\Support\Facades\DB::connection('run')->table('coop_manual_loan_sources')->pluck('loan_rec_id');
        $sources = \Illuminate\Support\Facades\DB::connection('run')->table('coop_manual_loan_sources')->whereIn('loan_rec_id', $loanIds)->get()->keyBy('loan_rec_id');
        $loans = CooperativeLoan::query()->with('member')->whereIn('rec_id', $loanIds)->orderByDesc('trndt')->limit(200)->get(['rec_id', 'trnno', 'trndt', 'term', 'endper', 'statrec', 'paid', 'totalloan']);
        $loans->each(function (CooperativeLoan $loan) use ($sources): void {
            $source = $sources->get($loan->rec_id);
            $loan->manual_member_name = $source?->member_name;
        });

        return view('cooperative.manual-loans.create', [
            'members' => CooperativeMember::query()->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']),
            'loans' => $loans,
            'mode' => (string) request('mode', 'loan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['member_rec_id' => ['nullable', 'integer', 'min:1'], 'member_name' => ['required', 'string', 'max:100'], 'trndt' => ['required', 'date'], 'principal' => ['required', 'integer', 'min:1', 'max:10000000000'], 'term' => ['required', 'integer', 'min:1', 'max:120'], 'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'calculation_method' => ['required', 'in:flat,effective,annuity'], 'payment_status' => ['required', 'in:paid,running']]);
        try {
            $result = $this->service->createFromMaster($data, (int) auth_user_id());
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }
        $loanId = (int) \Illuminate\Support\Facades\DB::connection('run')->table('coop_manual_loan_sources')->where('import_id', $result['import_id'])->value('loan_rec_id');
        return redirect()->route('cooperative.loans.detail', ['rec_id' => $loanId])->with('success', 'Loan manual berhasil dibuat.');
    }

    public function simulate(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['trndt' => ['required', 'date'], 'principal' => ['required', 'integer', 'min:1', 'max:10000000000'], 'term' => ['required', 'integer', 'min:1', 'max:120'], 'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'calculation_method' => ['required', 'in:flat,effective,annuity']]);
        return response()->json($this->service->simulateMaster($data));
    }

    public function simulateAdjustment(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['mode' => ['required', 'in:skip,accelerate'], 'loan_rec_id' => ['required', 'integer', 'min:1'], 'start_period' => ['required', 'regex:/^\d{6}$/'], 'months_count' => ['required', 'integer', 'min:1', 'max:12']]);
        $loan = CooperativeLoan::query()->findOrFail((int) $data['loan_rec_id']);
        $rows = $loan->schedules()->orderBy('seqno')->get()->map(fn ($row): array => ['rec_id' => $row->rec_id, 'seqno' => $row->seqno, 'periode' => $row->periode, 'amount' => $row->amount, 'int_amt' => $row->int_amt, 'others' => $row->others, 'paidst' => 0, 'payno' => ''])->all();
        try {
            $plan = $data['mode'] === LoanSkipService::MODE_ACCELERATE ? $this->skips->acceleratePlan($rows, (int) $data['months_count'], (string) $data['start_period']) : $this->skips->plan($rows, (string) $data['start_period'], (int) $data['months_count']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json(['plan' => $plan, 'rows' => $this->skips->afterSchedule($rows, $plan, (string) $data['mode'])]);
    }

    public function schedule(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['loan_rec_id' => ['required', 'integer', 'min:1']]);
        $source = \Illuminate\Support\Facades\DB::connection('run')->table('coop_manual_loan_sources')->where('loan_rec_id', (int) $data['loan_rec_id'])->first();
        $loan = CooperativeLoan::query()->where('rec_id', (int) $data['loan_rec_id'])->findOrFail((int) $data['loan_rec_id']);
        return response()->json([
            'loan' => ['trnno' => $loan->trnno, 'member' => $loan->member?->icunm ?? $source?->member_name ?? $loan->descr, 'status' => $loan->isSettledIndicative() ? 'Lunas' : 'Berjalan'],
            'rows' => $loan->schedules()->orderBy('seqno')->get(['seqno', 'periode', 'amount', 'int_amt', 'others', 'outstand', 'paidst']),
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['mode' => ['required', 'in:skip,accelerate'], 'loan_rec_id' => ['required', 'integer', 'min:1'], 'start_period' => ['required', 'regex:/^\d{6}$/'], 'months_count' => ['required', 'integer', 'min:1', 'max:12']]);
        $loan = CooperativeLoan::query()->with('member')->findOrFail((int) $data['loan_rec_id']);
        $rows = $loan->schedules()->orderBy('seqno')->get()->map(fn ($row): array => ['rec_id' => $row->rec_id, 'seqno' => $row->seqno, 'periode' => $row->periode, 'amount' => $row->amount, 'int_amt' => $row->int_amt, 'others' => $row->others, 'paidst' => 0, 'payno' => ''])->all();
        try {
            $plan = $data['mode'] === LoanSkipService::MODE_ACCELERATE ? $this->skips->acceleratePlan($rows, (int) $data['months_count'], (string) $data['start_period']) : $this->skips->plan($rows, (string) $data['start_period'], (int) $data['months_count']);
            $skip = new CooperativeLoanSkip(['mode' => $data['mode'], 'loan_rec_id' => $loan->rec_id, 'start_period' => $data['start_period'] ?? '', 'months_count' => $data['months_count']]);
            $this->skips->applyManualHistorical($skip);
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['adjustment' => $exception->getMessage()]);
        }
        return redirect()->route('cooperative.loans.detail', ['rec_id' => $loan->rec_id])->with('success', 'Penyesuaian manual berhasil diterapkan.');
    }

    public function template()
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['source_key', 'member_rec_id', 'member_name', 'trndt', 'principal', 'annual_rate', 'paid_total', 'periode', 'amount', 'int_amt', 'others', 'outstand', 'paidst', 'payno', 'remarks']], null, 'A1');
        $sheet->fromArray([['HIST-001', '', 'NAMA ANGGOTA', '2008-01-15', 1000000, 6, 0, '200801', 50000, 5000, 0, 950000, 0, '', '']], null, 'A2');
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet): void {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'template-import-loan-manual.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(CooperativeAccess::isAdmin((int) auth_user_id()), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']]);

        try {
            $rows = $this->readRows($request->file('file')->getRealPath());
            $result = $this->service->import($rows, (int) auth_user_id(), $request->file('file')->getClientOriginalName());
        } catch (\Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', $result['count'].' pinjaman berhasil diimport.');
    }

    private function readRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $records = $sheet->toArray(null, true, true, true);
        $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), array_shift($records) ?: []);
        $groups = [];
        foreach ($records as $record) {
            $row = array_combine($headers, array_values($record));
            if (! $row || trim((string) ($row['source_key'] ?? '')) === '') {
                continue;
            }
            $key = trim((string) $row['source_key']);
            $groups[$key] ??= ['source_key' => $key, 'member_rec_id' => (int) ($row['member_rec_id'] ?? 0), 'member_name' => trim((string) ($row['member_name'] ?? '')), 'trndt' => $this->date($row['trndt'] ?? ''), 'principal' => (int) $row['principal'], 'annual_rate' => (float) ($row['annual_rate'] ?? 0), 'paid_total' => (int) ($row['paid_total'] ?? 0), 'schedule' => []];
            $groups[$key]['schedule'][] = ['periode' => $this->period($row['periode'] ?? ''), 'amount' => (int) ($row['amount'] ?? 0), 'int_amt' => (int) ($row['int_amt'] ?? 0), 'others' => (int) ($row['others'] ?? 0), 'outstand' => (int) ($row['outstand'] ?? 0), 'paidst' => (int) ($row['paidst'] ?? 0), 'payno' => trim((string) ($row['payno'] ?? '')), 'remarks' => trim((string) ($row['remarks'] ?? ''))];
        }

        if ($groups === []) {
            throw new RuntimeException('Kolom atau isi Excel tidak sesuai template.');
        }
        return array_values($groups);
    }

    private function period(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{6}$/', $value)) return $value;
        $date = strtotime($value);
        return $date ? date('Ym', $date) : $value;
    }

    private function date(mixed $value): string
    {
        $date = strtotime((string) $value);
        return $date ? date('Y-m-d', $date) : date('Y-m-d');
    }
}
