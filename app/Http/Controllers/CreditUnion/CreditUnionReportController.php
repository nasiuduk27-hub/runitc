<?php

namespace App\Http\Controllers\CreditUnion;

use App\Exports\CreditUnion\CuExcel;
use App\Http\Controllers\Controller;
use App\Models\CreditUnion\CreditUnionBankTrx;
use App\Models\CreditUnion\CreditUnionBankTrxDetail;
use App\Models\CreditUnion\CreditUnionLoan;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CreditUnionReportController extends Controller
{
    private const TABS = ['savings', 'due', 'loans', 'interest', 'bank'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View|Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'savings';

        if ($request->query->has('export')) {
            return $this->export($request, $tab);
        }

        return view('credit-union.reports.index', [
            'tab' => $tab,
            'reports' => $this->reports,
            ...match ($tab) {
                'savings' => $this->savingsData($request),
                'due' => $this->dueData($request),
                'interest' => $this->interestData($request),
                'bank' => $this->bankData($request),
                default => $this->loansData($request),
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function savingsData(Request $request): array
    {
        $default = $this->reports->defaultSavingsRange();
        $from = $this->periodParam($request, 'from', $default['from']);
        $to = $this->periodParam($request, 'to', $default['to']);
        $keyword = trim((string) $request->query('q'));

        $rows = DB::connection('mysql')->table('icu_transaction as t')
            ->join('icu_member as m', 'm.rec_id', '=', 't.icu_rec_id')
            ->whereBetween('t.pprd', [$from, $to])
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $like = '%'.str_replace('%', '\%', $keyword).'%';
                $query->where(fn ($inner) => $inner
                    ->where('m.icuno', 'like', $like)
                    ->orWhere('m.icunm', 'like', $like));
            })
            ->groupBy('t.icu_rec_id', 'm.icuno', 'm.icunm')
            ->orderBy('m.icuno')
            ->get([
                't.icu_rec_id',
                'm.icuno',
                'm.icunm',
                DB::raw("SUM(CASE WHEN t.dbocr = 'D' THEN t.amount ELSE 0 END) AS debit_total"),
                DB::raw("SUM(CASE WHEN t.dbocr = 'C' THEN t.amount ELSE 0 END) AS credit_total"),
                DB::raw("SUM(CASE WHEN t.dbocr = 'D' THEN 1 ELSE 0 END) AS debit_count"),
                DB::raw("SUM(CASE WHEN t.dbocr = 'C' THEN 1 ELSE 0 END) AS credit_count"),
            ]);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'keyword' => $keyword,
            'rows' => $rows,
            'totals' => [
                'debit' => (int) $rows->sum('debit_total'),
                'credit' => (int) $rows->sum('credit_total'),
                'members' => $rows->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dueData(Request $request): array
    {
        $asOf = $this->periodParam($request, 'as_of', CreditUnionPeriod::current());
        $keyword = trim((string) $request->query('q'));

        $rows = collect(DB::connection('mysql')->table('icu_dloan as d')
            ->join('icu_mloan as l', 'l.rec_id', '=', 'd.mst_rec_id')
            ->join('icu_member as m', 'm.rec_id', '=', 'l.icu_rec_id')
            ->where('d.paidst', 0)
            ->where('d.periode', '<=', $asOf)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $like = '%'.str_replace('%', '\%', $keyword).'%';
                $query->where(fn ($inner) => $inner
                    ->where('m.icuno', 'like', $like)
                    ->orWhere('m.icunm', 'like', $like)
                    ->orWhere('l.trnno', 'like', $like));
            })
            ->orderBy('d.periode')
            ->orderBy('m.icuno')
            ->limit(2000)
            ->get([
                'd.seqno', 'd.totseqno', 'd.periode', 'd.amount', 'd.int_amt', 'd.others', 'd.paidst',
                'l.trnno', 'm.icuno', 'm.icunm',
            ])
            ->map(function ($row) use ($asOf): array {
                $due = (int) $row->amount + (int) $row->int_amt + (int) $row->others;

                return [
                    'icuno' => $row->icuno,
                    'member_name' => $row->icunm,
                    'trnno' => $row->trnno,
                    'installment' => $row->seqno.'/'.$row->totseqno,
                    'periode' => (string) $row->periode,
                    'principal' => (int) $row->amount,
                    'interest' => (int) $row->int_amt,
                    'others' => (int) $row->others,
                    'total' => $due,
                    'classification' => $this->reports->classifyInstallment((int) $row->paidst, (string) $row->periode, $asOf),
                ];
            }));

        return [
            'as_of' => $asOf,
            'keyword' => $keyword,
            'rows' => $rows,
            'totals' => [
                'overdue_count' => $rows->where('classification', ReportService::DUE_OVERDUE)->count(),
                'overdue_sum' => (int) $rows->where('classification', ReportService::DUE_OVERDUE)->sum('total'),
                'now_count' => $rows->where('classification', ReportService::DUE_NOW)->count(),
                'now_sum' => (int) $rows->where('classification', ReportService::DUE_NOW)->sum('total'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loansData(Request $request): array
    {
        $keyword = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $loans = CreditUnionLoan::query()
            ->with('member')
            ->search($keyword)
            ->statusIndicative($status)
            ->withCount(['schedules as paid_count' => fn ($query) => $query->where('paidst', 1)])
            ->withCount(['schedules as unpaid_count' => fn ($query) => $query->where('paidst', 0)])
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->limit(500)
            ->get();

        $rows = $loans->map(fn (CreditUnionLoan $loan): array => [
            'trnno' => $loan->trnno,
            'member_name' => $loan->member?->icunm ?? '-',
            'member_icuno' => $loan->member?->icuno ?? '-',
            'trndt' => $loan->trndt,
            'principle' => $loan->principle,
            'interamt' => $loan->interamt,
            'totalloan' => $loan->totalloan,
            'paid' => $loan->paid,
            'indicative_outstanding' => max(0, $loan->principle - $loan->paid),
            'term' => $loan->term,
            'period' => $loan->startper.' - '.$loan->endper,
            'paid_count' => (int) $loan->paid_count,
            'unpaid_count' => (int) $loan->unpaid_count,
            'ln_status' => $this->reports->lnStatusLabel($loan->statrec),
            'settled_indicative' => $loan->isSettledIndicative(),
        ]);

        return [
            'keyword' => $keyword,
            'status' => $status,
            'rows' => $rows,
            'totals' => [
                'count' => $rows->count(),
                'principle' => (int) $rows->sum('principle'),
                'paid' => (int) $rows->sum('paid'),
                'outstanding' => (int) $rows->sum('indicative_outstanding'),
            ],
        ];
    }

    private function export(Request $request, string $tab): Response
    {
        return match ($tab) {
            'savings' => $this->savingsExport($request),
            'due' => $this->dueExport($request),
            'interest' => $this->interestExport($request),
            'bank' => $this->bankExport($request),
            default => $this->loansExport($request),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function interestData(Request $request): array
    {
        $default = $this->reports->defaultSavingsRange();
        $from = $this->periodParam($request, 'from', $default['from']);
        $to = $this->periodParam($request, 'to', $default['to']);

        $rawRows = DB::connection('mysql')->table('icu_dloan')
            ->whereBetween('periode', [$from, $to])
            ->orderBy('periode', 'asc')
            ->get(['periode', 'paidst', 'int_amt']);

        $aggregated = $this->reports->aggregateInterestRevenue($rawRows, $from, $to);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'rows' => collect($aggregated['rows'])->map(fn (array $r): object => (object) $r),
            'totals' => $aggregated['totals'],
        ];
    }

    private function interestExport(Request $request): Response
    {
        $data = $this->interestData($request);
        $title = 'Laporan Pendapatan Bunga';
        $subtitle = 'Periode: '.CreditUnionPeriod::label($data['range']['from']).' s.d. '.CreditUnionPeriod::label($data['range']['to']);

        $columns = [
            ['label' => 'Periode', 'width' => 24, 'align' => 'left', 'format' => 'text'],
            ['label' => 'Jumlah Cicilan Terbayar', 'width' => 24, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Pendapatan Bunga (Rp)', 'width' => 24, 'align' => 'right', 'format' => 'number'],
        ];

        $dataRows = [];
        foreach ($data['rows'] as $row) {
            $dataRows[] = [
                CreditUnionPeriod::label((string) $row->periode).' ('.$row->periode.')',
                (int) $row->installment_count,
                (int) $row->interest_total,
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL',
                'values' => [
                    0 => '',
                    1 => (int) $data['totals']['installments'],
                    2 => (int) $data['totals']['interest'],
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        CuExcel::buildSheet($spreadsheet->getActiveSheet(), $title, $subtitle, $columns, $dataRows, $totalRows);

        return CuExcel::downloadResponse(
            $spreadsheet,
            'laporan_pendapatan_bunga_'.$data['range']['from'].'-'.$data['range']['to'].'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bankData(Request $request): array
    {
        $default = $this->reports->defaultSavingsRange();
        $from = $this->periodParam($request, 'from', $default['from']);
        $to = $this->periodParam($request, 'to', $default['to']);
        $account = trim((string) $request->query('account'));

        $accountOptions = CreditUnionBankTrxDetail::query()
            ->select(['bank_account_no', 'bank_account_bank', 'bank_account_name'])
            ->whereNotNull('bank_account_no')
            ->where('bank_account_no', '!=', '')
            ->distinct()
            ->orderBy('bank_account_bank')
            ->get();

        $detailRows = CreditUnionBankTrxDetail::query()
            ->when($account !== '', fn ($q) => $q->where('bank_account_no', $account))
            ->get(['bank_trx_id', 'category', 'gross_amount', 'tax_amount', 'net_amount']);

        $trxIds = $detailRows->pluck('bank_trx_id')->filter()->unique()->all();

        $rowsWithPeriod = [];
        if (! empty($trxIds)) {
            $trxMap = CreditUnionBankTrx::query()
                ->whereIn('rec_id', $trxIds)
                ->get(['rec_id', 'trndt'])
                ->keyBy('rec_id');

            foreach ($detailRows as $detail) {
                $trx = $trxMap->get($detail->bank_trx_id);
                if (! $trx || ! $trx->trndt) {
                    continue;
                }

                $periode = CarbonImmutable::parse($trx->trndt)->format('Ym');
                $rowsWithPeriod[] = [
                    'periode' => $periode,
                    'category' => $detail->category,
                    'gross_amount' => (int) $detail->gross_amount,
                    'tax_amount' => (int) $detail->tax_amount,
                    'net_amount' => (int) $detail->net_amount,
                ];
            }
        }

        $aggregated = $this->reports->aggregateBankReport($rowsWithPeriod, $from, $to);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'account' => $account,
            'accountOptions' => $accountOptions,
            'rows' => collect($aggregated['rows'])->map(fn (array $r): object => (object) $r),
            'totals' => $aggregated['totals'],
        ];
    }

    private function bankExport(Request $request): Response
    {
        $data = $this->bankData($request);
        $title = 'Laporan Bank';
        $subtitle = 'Periode: '.CreditUnionPeriod::label($data['range']['from']).' s.d. '.CreditUnionPeriod::label($data['range']['to'])
            .($data['account'] !== '' ? ' • Rekening: '.$data['account'] : ' • Semua Rekening');

        $columns = [
            ['label' => 'Periode', 'width' => 24, 'align' => 'left', 'format' => 'text'],
            ['label' => 'Biaya Admin Bank (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Bunga Bruto (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Pajak Bunga (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Bunga Neto (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Selisih Bank (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'number'],
        ];

        $dataRows = [];
        foreach ($data['rows'] as $row) {
            $dataRows[] = [
                CreditUnionPeriod::label((string) $row->periode).' ('.$row->periode.')',
                (int) $row->admin_fee,
                (int) $row->gross_interest,
                (int) $row->tax_interest,
                (int) $row->net_interest,
                (int) $row->net_difference,
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL',
                'values' => [
                    0 => '',
                    1 => (int) $data['totals']['admin_fee'],
                    2 => (int) $data['totals']['gross_interest'],
                    3 => (int) $data['totals']['tax_interest'],
                    4 => (int) $data['totals']['net_interest'],
                    5 => (int) $data['totals']['net_difference'],
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        CuExcel::buildSheet($spreadsheet->getActiveSheet(), $title, $subtitle, $columns, $dataRows, $totalRows);

        return CuExcel::downloadResponse(
            $spreadsheet,
            'laporan_bank_'.$data['range']['from'].'-'.$data['range']['to'].'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function savingsExport(Request $request): Response
    {
        $data = $this->savingsData($request);
        $title = 'Laporan Simpanan';
        $subtitle = 'Periode: '.CreditUnionPeriod::label($data['range']['from']).' s.d. '.CreditUnionPeriod::label($data['range']['to'])
            .($data['keyword'] !== '' ? ' • Filter: '.$data['keyword'] : '');

        $columns = [
            ['label' => 'No Anggota', 'width' => 14, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Nama', 'width' => 28, 'align' => 'left', 'format' => 'text'],
            ['label' => 'Setoran Debit (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Penarikan Kredit (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Neto (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Trx Debit', 'width' => 12, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Trx Kredit', 'width' => 12, 'align' => 'right', 'format' => 'number'],
        ];

        $dataRows = [];
        foreach ($data['rows'] as $row) {
            $dataRows[] = [
                $row->icuno,
                $row->icunm,
                (int) $row->debit_total,
                (int) $row->credit_total,
                (int) ($row->debit_total - $row->credit_total),
                (int) $row->debit_count,
                (int) $row->credit_count,
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL ('.$data['totals']['members'].' anggota)',
                'values' => [
                    1 => '',
                    2 => (int) $data['totals']['debit'],
                    3 => (int) $data['totals']['credit'],
                    4 => (int) ($data['totals']['debit'] - $data['totals']['credit']),
                    5 => (int) $data['rows']->sum('debit_count'),
                    6 => (int) $data['rows']->sum('credit_count'),
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        CuExcel::buildSheet($spreadsheet->getActiveSheet(), $title, $subtitle, $columns, $dataRows, $totalRows);

        return CuExcel::downloadResponse(
            $spreadsheet,
            'laporan_simpanan_'.$data['range']['from'].'-'.$data['range']['to'].'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function dueExport(Request $request): Response
    {
        $data = $this->dueData($request);
        $title = 'Laporan Pinjaman Jatuh Tempo';
        $subtitle = 'Periode Acuan: '.CreditUnionPeriod::label($data['as_of']).' ('.$data['as_of'].')'
            .($data['keyword'] !== '' ? ' • Filter: '.$data['keyword'] : '');

        $columns = [
            ['label' => 'No Anggota', 'width' => 14, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Nama', 'width' => 26, 'align' => 'left', 'format' => 'text'],
            ['label' => 'No Pinjaman', 'width' => 16, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Angsuran ke', 'width' => 14, 'align' => 'center', 'format' => 'text'],
            ['label' => 'Periode', 'width' => 12, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Pokok (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Bunga (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Lainnya (Rp)', 'width' => 14, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Total (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Klasifikasi', 'width' => 18, 'align' => 'center', 'format' => 'text'],
        ];

        $dataRows = [];
        foreach ($data['rows'] as $row) {
            $dataRows[] = [
                $row['icuno'],
                $row['member_name'],
                $row['trnno'],
                $row['installment'],
                $row['periode'],
                (int) $row['principal'],
                (int) $row['interest'],
                (int) $row['others'],
                (int) $row['total'],
                $this->reports->dueLabel($row['classification']),
            ];
        }

        $overdueRows = $data['rows']->where('classification', ReportService::DUE_OVERDUE);
        $nowRows = $data['rows']->where('classification', ReportService::DUE_NOW);

        $totalRows = [
            [
                'label' => 'SUBTOTAL TERLEWAT ('.$data['totals']['overdue_count'].' cicilan)',
                'fill' => 'FEE2E2',
                'values' => [
                    5 => (int) $overdueRows->sum('principal'),
                    6 => (int) $overdueRows->sum('interest'),
                    7 => (int) $overdueRows->sum('others'),
                    8 => (int) $data['totals']['overdue_sum'],
                ],
            ],
            [
                'label' => 'SUBTOTAL JATUH TEMPO KINI ('.$data['totals']['now_count'].' cicilan)',
                'fill' => 'E0E7FF',
                'values' => [
                    5 => (int) $nowRows->sum('principal'),
                    6 => (int) $nowRows->sum('interest'),
                    7 => (int) $nowRows->sum('others'),
                    8 => (int) $data['totals']['now_sum'],
                ],
            ],
            [
                'label' => 'GRAND TOTAL ('.$data['rows']->count().' cicilan)',
                'fill' => CuExcel::COLOR_TOTAL_FILL,
                'values' => [
                    5 => (int) $data['rows']->sum('principal'),
                    6 => (int) $data['rows']->sum('interest'),
                    7 => (int) $data['rows']->sum('others'),
                    8 => (int) $data['rows']->sum('total'),
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        CuExcel::buildSheet($spreadsheet->getActiveSheet(), $title, $subtitle, $columns, $dataRows, $totalRows);

        return CuExcel::downloadResponse(
            $spreadsheet,
            'laporan_jatuh_tempo_'.$data['as_of'].'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function loansExport(Request $request): Response
    {
        $data = $this->loansData($request);
        $title = 'Laporan Rekap Pinjaman';
        $subtitle = 'Credit Union • Rekap Portofolio Pinjaman'
            .($data['status'] !== '' ? ' • Status: '.$data['status'] : '')
            .($data['keyword'] !== '' ? ' • Filter: '.$data['keyword'] : '');

        $columns = [
            ['label' => 'No Pinjaman', 'width' => 16, 'align' => 'center', 'format' => 'code'],
            ['label' => 'No Anggota', 'width' => 14, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Nama', 'width' => 26, 'align' => 'left', 'format' => 'text'],
            ['label' => 'Tanggal', 'width' => 14, 'align' => 'center', 'format' => 'date'],
            ['label' => 'Pokok (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Bunga Awal (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Total Tagihan (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Terbayar (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Sisa Pokok Indikatif (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Tenor', 'width' => 10, 'align' => 'center', 'format' => 'number'],
            ['label' => 'Periode', 'width' => 18, 'align' => 'center', 'format' => 'text'],
            ['label' => 'Cicilan Lunas', 'width' => 14, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Cicilan Tertunggak', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Status LN', 'width' => 18, 'align' => 'center', 'format' => 'text'],
        ];

        $dataRows = [];
        foreach ($data['rows'] as $row) {
            $dataRows[] = [
                $row['trnno'],
                $row['member_icuno'],
                $row['member_name'],
                (string) $row['trndt'],
                (int) $row['principle'],
                (int) $row['interamt'],
                (int) $row['totalloan'],
                (int) $row['paid'],
                (int) $row['indicative_outstanding'],
                (int) $row['term'],
                (string) $row['period'],
                (int) $row['paid_count'],
                (int) $row['unpaid_count'],
                (string) $row['ln_status'],
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL ('.$data['totals']['count'].' pinjaman)',
                'values' => [
                    4 => (int) $data['totals']['principle'],
                    5 => (int) $data['rows']->sum('interamt'),
                    6 => (int) $data['rows']->sum('totalloan'),
                    7 => (int) $data['totals']['paid'],
                    8 => (int) $data['totals']['outstanding'],
                    11 => (int) $data['rows']->sum('paid_count'),
                    12 => (int) $data['rows']->sum('unpaid_count'),
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        CuExcel::buildSheet($spreadsheet->getActiveSheet(), $title, $subtitle, $columns, $dataRows, $totalRows);

        return CuExcel::downloadResponse(
            $spreadsheet,
            'rekap_pinjaman_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function periodParam(Request $request, string $key, string $default): string
    {
        $value = (string) $request->query($key, $default);

        return CreditUnionPeriod::isValid($value) ? $value : $default;
    }
}
