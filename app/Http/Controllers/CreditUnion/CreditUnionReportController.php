<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoan;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CooperativeReportController extends Controller
{
    private const TABS = ['savings', 'due', 'loans'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View|Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'savings';

        if ($request->query->has('export')) {
            return $this->export($request, $tab);
        }

        return view('cooperative.reports.index', [
            'tab' => $tab,
            'reports' => $this->reports,
            ...match ($tab) {
                'savings' => $this->savingsData($request),
                'due' => $this->dueData($request),
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
        $asOf = $this->periodParam($request, 'as_of', CooperativePeriod::current());
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

        $loans = CooperativeLoan::query()
            ->with('member')
            ->search($keyword)
            ->statusIndicative($status)
            ->withCount(['schedules as paid_count' => fn ($query) => $query->where('paidst', 1)])
            ->withCount(['schedules as unpaid_count' => fn ($query) => $query->where('paidst', 0)])
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->limit(500)
            ->get();

        $rows = $loans->map(fn (CooperativeLoan $loan): array => [
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
        [$headers, $rows, $filename] = match ($tab) {
            'savings' => $this->savingsExport($request),
            'due' => $this->dueExport($request),
            default => $this->loansExport($request),
        };

        return $this->csv($headers, $rows, $filename);
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|string>>, 2: string}
     */
    private function savingsExport(Request $request): array
    {
        $data = $this->savingsData($request);

        $rows = array_map(fn ($row): array => [
            $row->icuno, $row->icunm, (int) $row->debit_total, (int) $row->credit_total,
            (int) $row->debit_count, (int) $row->credit_count,
        ], $data['rows']->all());

        return [
            ['No Anggota', 'Nama', 'Setoran Debit (Rp)', 'Penarikan Kredit (Rp)', 'Trx Debit', 'Trx Kredit'],
            $rows,
            'laporan_simpanan_'.$data['range']['from'].'-'.$data['range']['to'].'_'.now()->format('Ymd_His').'.csv',
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|string>>, 2: string}
     */
    private function dueExport(Request $request): array
    {
        $data = $this->dueData($request);

        $rows = array_map(fn (array $row): array => [
            $row['icuno'], $row['member_name'], $row['trnno'], $row['installment'],
            $row['periode'], $row['principal'], $row['interest'], $row['others'],
            $row['total'], $this->reports->dueLabel($row['classification']),
        ], $data['rows']->all());

        return [
            ['No Anggota', 'Nama', 'No Pinjaman', 'Angsuran ke', 'Periode', 'Pokok (Rp)', 'Bunga (Rp)', 'Lainnya (Rp)', 'Total (Rp)', 'Klasifikasi'],
            $rows,
            'laporan_jatuh_tempo_'.$data['as_of'].'_'.now()->format('Ymd_His').'.csv',
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|string>>, 2: string}
     */
    private function loansExport(Request $request): array
    {
        $data = $this->loansData($request);

        $rows = array_map(fn (array $row): array => [
            $row['trnno'], $row['member_icuno'], $row['member_name'], (string) $row['trndt'],
            $row['principle'], $row['interamt'], $row['totalloan'], $row['paid'],
            $row['indicative_outstanding'], $row['term'], $row['period'],
            $row['paid_count'], $row['unpaid_count'], $row['ln_status'],
        ], $data['rows']->all());

        return [
            ['No Pinjaman', 'No Anggota', 'Nama', 'Tanggal', 'Pokok (Rp)', 'Bunga Awal (Rp)', 'Total Tagihan (Rp)', 'Terbayar (Rp)', 'Sisa Pokok Indikatif (Rp)', 'Tenor', 'Periode', 'Cicilan Lunas', 'Cicilan Tertunggak', 'Status LN'],
            $rows,
            'rekap_pinjaman_'.now()->format('Ymd_His').'.csv',
        ];
    }

    private function periodParam(Request $request, string $key, string $default): string
    {
        $value = (string) $request->query($key, $default);

        return CooperativePeriod::isValid($value) ? $value : $default;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<int|string>>  $rows
     */
    private function csv(array $headers, array $rows, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
