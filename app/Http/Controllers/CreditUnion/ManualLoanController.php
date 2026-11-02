<?php

namespace App\Http\Controllers\CreditUnion;

use App\Exports\CreditUnion\CuExcel;
use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionMember;
use App\Models\CreditUnion\CreditUnionLoan;
use App\Models\CreditUnion\CreditUnionLoanSkip;
use App\Services\CreditUnion\ManualLoanService;
use App\Services\CreditUnion\LoanSkipService;
use App\Support\CreditUnionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ManualLoanController extends Controller
{
    public function __construct(private readonly ManualLoanService $service, private readonly LoanSkipService $skips) {}

    public function create(): View
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);

        $loanIds = \Illuminate\Support\Facades\DB::connection('run')->table('cu_manual_loan_sources')->pluck('loan_rec_id');
        $sources = \Illuminate\Support\Facades\DB::connection('run')->table('cu_manual_loan_sources')->whereIn('loan_rec_id', $loanIds)->get()->keyBy('loan_rec_id');
        $loans = CreditUnionLoan::query()->with('member')->whereIn('rec_id', $loanIds)->orderByDesc('trndt')->limit(200)->get(['rec_id', 'trnno', 'trndt', 'term', 'endper', 'statrec', 'paid', 'totalloan']);
        $loans->each(function (CreditUnionLoan $loan) use ($sources): void {
            $source = $sources->get($loan->rec_id);
            $loan->manual_member_name = $source?->member_name;
        });

        return view('credit-union.manual-loans.create', [
            'members' => CreditUnionMember::query()->whereNotIn('icuno', CreditUnionMember::ICUNO_SENTINELS)->orderBy('icuno')->get(['rec_id', 'icuno', 'icunm']),
            'loans' => $loans,
            'mode' => (string) request('mode', 'loan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['member_rec_id' => ['nullable', 'integer', 'min:1'], 'member_name' => ['required', 'string', 'max:100'], 'member_status' => ['nullable', 'in:active,inactive'], 'trndt' => ['required', 'date_format:Y-m-d'], 'principal' => ['required', 'integer', 'min:1', 'max:10000000000'], 'term' => ['required', 'integer', 'min:1', 'max:120'], 'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'calculation_method' => ['required', 'in:flat,effective,annuity'], 'payment_status' => ['required', 'in:paid,running'], 'admin_fee' => ['nullable', 'integer', 'min:0', 'max:10000000000'], 'admin_fee_type' => ['required', 'in:include,exclude']]);
        try {
            $result = $this->service->createFromMaster($data, (int) auth_user_id());
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['manual' => $exception->getMessage()]);
        }
        $loanId = (int) \Illuminate\Support\Facades\DB::connection('run')->table('cu_manual_loan_sources')->where('import_id', $result['import_id'])->value('loan_rec_id');
        return redirect()->route('cu.loans.detail', ['rec_id' => $loanId])->with('success', 'Loan manual berhasil dibuat.');
    }

    public function simulate(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['trndt' => ['required', 'date_format:Y-m-d'], 'principal' => ['required', 'integer', 'min:1', 'max:10000000000'], 'term' => ['required', 'integer', 'min:1', 'max:120'], 'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'calculation_method' => ['required', 'in:flat,effective,annuity'], 'admin_fee' => ['nullable', 'integer', 'min:0', 'max:10000000000'], 'admin_fee_type' => ['required', 'in:include,exclude']]);
        return response()->json($this->service->simulateMaster($data));
    }

    public function simulateAdjustment(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['mode' => ['required', 'in:skip,accelerate'], 'loan_rec_id' => ['required', 'integer', 'min:1'], 'start_period' => ['required', 'regex:/^\d{6}$/'], 'months_count' => ['required', 'integer', 'min:1', 'max:12']]);
        $loan = CreditUnionLoan::query()->findOrFail((int) $data['loan_rec_id']);
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
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['loan_rec_id' => ['required', 'integer', 'min:1']]);
        $source = \Illuminate\Support\Facades\DB::connection('run')->table('cu_manual_loan_sources')->where('loan_rec_id', (int) $data['loan_rec_id'])->first();
        $loan = CreditUnionLoan::query()->where('rec_id', (int) $data['loan_rec_id'])->findOrFail((int) $data['loan_rec_id']);
        return response()->json([
            'loan' => ['trnno' => $loan->trnno, 'member' => $loan->member?->icunm ?? $source?->member_name ?? $loan->descr, 'status' => $loan->isSettledIndicative() ? 'Lunas' : 'Berjalan'],
            'rows' => $loan->schedules()->orderBy('seqno')->get(['seqno', 'periode', 'amount', 'int_amt', 'others', 'outstand', 'paidst']),
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $data = $request->validate(['mode' => ['required', 'in:skip,accelerate'], 'loan_rec_id' => ['required', 'integer', 'min:1'], 'start_period' => ['required', 'regex:/^\d{6}$/'], 'months_count' => ['required', 'integer', 'min:1', 'max:12']]);
        $loan = CreditUnionLoan::query()->with('member')->findOrFail((int) $data['loan_rec_id']);
        $rows = $loan->schedules()->orderBy('seqno')->get()->map(fn ($row): array => ['rec_id' => $row->rec_id, 'seqno' => $row->seqno, 'periode' => $row->periode, 'amount' => $row->amount, 'int_amt' => $row->int_amt, 'others' => $row->others, 'paidst' => 0, 'payno' => ''])->all();
        try {
            $plan = $data['mode'] === LoanSkipService::MODE_ACCELERATE ? $this->skips->acceleratePlan($rows, (int) $data['months_count'], (string) $data['start_period']) : $this->skips->plan($rows, (string) $data['start_period'], (int) $data['months_count']);
            $skip = new CreditUnionLoanSkip(['mode' => $data['mode'], 'loan_rec_id' => $loan->rec_id, 'start_period' => $data['start_period'] ?? '', 'months_count' => $data['months_count']]);
            $this->skips->applyManualHistorical($skip);
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['adjustment' => $exception->getMessage()]);
        }
        return redirect()->route('cu.loans.detail', ['rec_id' => $loan->rec_id])->with('success', 'Penyesuaian manual berhasil diterapkan.');
    }

    public function template()
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
        $spreadsheet = CuExcel::createSpreadsheet('Template Import Pinjaman Manual');

        // Sheet 1: Data Pinjaman (WAJIB sheet pertama dan aktif)
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pinjaman');

        $headers = ['source_key', 'member_rec_id', 'member_name', 'trndt', 'principal', 'annual_rate', 'paid_total', 'periode', 'amount', 'int_amt', 'others', 'outstand', 'paidst', 'payno', 'remarks'];
        $example = ['HIST-001', '', 'NAMA ANGGOTA', '2008-01-15', 1000000, 6, 0, '200801', 50000, 5000, 0, 950000, 0, '', ''];

        $sheet->fromArray([$headers], null, 'A1');
        $sheet->fromArray([$example], null, 'A2');

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $headerRange = 'A1:O1';
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF'.CuExcel::COLOR_BRAND);
        $sheet->getStyle($headerRange)->getFont()
            ->setBold(true)
            ->getColor()->setARGB('FF'.CuExcel::COLOR_WHITE);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A1:O2')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FF'.CuExcel::COLOR_BORDER);

        $sheet->freezePane('A2');

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // Sheet 2: Petunjuk Pengisian
        $guideSheet = $spreadsheet->createSheet(1);
        $guideSheet->setTitle('Petunjuk');

        $guideSheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT PINJAMAN MANUAL');
        $guideSheet->getStyle('A1')->getFont()->setSize(13)->setBold(true)->getColor()->setARGB('FF'.CuExcel::COLOR_BRAND);

        $guideHeaders = ['Nama Kolom', 'Wajib / Opsional', 'Tipe Data', 'Keterangan & Format'];
        $guideSheet->fromArray([$guideHeaders], null, 'A3');
        $guideSheet->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.CuExcel::COLOR_BRAND);
        $guideSheet->getStyle('A3:D3')->getFont()->setBold(true)->getColor()->setARGB('FF'.CuExcel::COLOR_WHITE);

        $guideRows = [
            ['source_key', 'Wajib', 'Teks', 'ID referensi unik pinjaman sumber (mis. HIST-001). Baris dengan source_key sama digabung menjadi 1 pinjaman.'],
            ['member_rec_id', 'Opsional', 'Angka', 'ID anggota di database bila sudah diketahui. Kosongkan bila mengisi member_name.'],
            ['member_name', 'Kondisional', 'Teks', 'Nama anggota (wajib bila member_rec_id kosong) untuk pencocokan otomatis ke master member.'],
            ['trndt', 'Wajib', 'Tanggal', 'Tanggal pencairan pinjaman (format: YYYY-MM-DD, contoh: 2008-01-15).'],
            ['principal', 'Wajib', 'Angka', 'Plafon / pokok pinjaman awal (contoh: 1000000).'],
            ['annual_rate', 'Wajib', 'Angka', 'Suku bunga tahunan dalam persen (contoh: 6 untuk 6%).'],
            ['paid_total', 'Opsional', 'Angka', 'Total nominal yang sudah terbayar pada saat data dicatat (default: 0).'],
            ['periode', 'Wajib', 'Teks/Angka', 'Periode cicilan (format YYYYMM, contoh: 200801).'],
            ['amount', 'Wajib', 'Angka', 'Nominal pokok cicilan pada periode ini (contoh: 50000).'],
            ['int_amt', 'Wajib', 'Angka', 'Nominal bunga cicilan pada periode ini (contoh: 5000).'],
            ['others', 'Opsional', 'Angka', 'Biaya administrasi / lainnya (default: 0).'],
            ['outstand', 'Wajib', 'Angka', 'Sisa pokok pinjaman setelah cicilan ini (contoh: 950000).'],
            ['paidst', 'Wajib', '0 atau 1', 'Status lunas cicilan ini (0 = Belum lunas / tertunggak, 1 = Lunas).'],
            ['payno', 'Opsional', 'Teks', 'Nomor bukti pembayaran (bila paidst = 1).'],
            ['remarks', 'Opsional', 'Teks', 'Catatan / keterangan tambahan per cicilan.'],
        ];

        $guideSheet->fromArray($guideRows, null, 'A4');
        $endGuideRow = 3 + count($guideRows);
        $guideSheet->getStyle('A3:D'.$endGuideRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF'.CuExcel::COLOR_BORDER);

        for ($col = 1; $col <= 4; $col++) {
            $guideSheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // Pastikan Sheet 1 tetap aktif
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet): void {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'template-import-loan-manual.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(CreditUnionAccess::isAdmin((int) auth_user_id()), 403);
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
