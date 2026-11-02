<?php

namespace App\Http\Controllers\CreditUnion;

use App\Exports\CreditUnion\CuExcel;
use App\Http\Controllers\Controller;
use App\Services\CreditUnion\LoanSimulationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class LoanSimulationController extends Controller
{
    public function __construct(private readonly LoanSimulationService $simulations) {}

    public function index(): View
    {
        return view('credit-union.loan-simulation.index', [
            'defaultAnnualRate' => 6,
            'methods' => LoanSimulationService::METHODS,
            'minPrincipal' => LoanSimulationService::MIN_PRINCIPAL,
            'maxPrincipal' => LoanSimulationService::MAX_PRINCIPAL,
            'minTenor' => LoanSimulationService::MIN_TENOR_MONTHS,
            'maxTenor' => LoanSimulationService::MAX_TENOR_MONTHS,
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $result = $this->simulations->simulate(
                (int) $data['principal'],
                (int) $data['tenor'],
                (float) $data['rate'],
                (string) $data['method'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function export(Request $request): Response
    {
        $data = $this->validated($request);

        try {
            $result = $this->simulations->simulate(
                (int) $data['principal'],
                (int) $data['tenor'],
                (float) $data['rate'],
                (string) $data['method'],
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->xlsx($result);
    }

    /**
     * @return array{principal: string, tenor: string, rate: string, method: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'principal' => ['required', 'integer', 'min:'.LoanSimulationService::MIN_PRINCIPAL, 'max:'.LoanSimulationService::MAX_PRINCIPAL],
            'tenor' => ['required', 'integer', 'min:'.LoanSimulationService::MIN_TENOR_MONTHS, 'max:'.LoanSimulationService::MAX_TENOR_MONTHS],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(LoanSimulationService::METHODS))],
        ]);
    }

    /**
     * @param  array{summary: array<string, int|float|string>, schedule: list<array<string, int|bool|string>>}  $result
     */
    private function xlsx(array $result): Response
    {
        /** @var array<string, int|float|string> $summary */
        $summary = $result['summary'];

        $title = 'Simulasi Kredit Credit Union';
        $subtitle = 'Metode: '.$summary['method_label'].' • Plafon: Rp '.number_format((int) $summary['principal'], 0, ',', '.')
            .' • Tenor: '.$summary['tenor_months'].' Bulan ('.$summary['annual_rate'].'% p.a.)';

        $summaryPairs = [
            ['label' => 'Jenis Kredit', 'value' => $summary['method_label']],
            ['label' => 'Jumlah Kredit (Rp)', 'value' => (int) $summary['principal']],
            ['label' => 'Jangka Waktu (Bulan)', 'value' => (int) $summary['tenor_months']],
            ['label' => 'Bunga per Tahun (%)', 'value' => $summary['annual_rate'].'%'],
            ['label' => 'Cicilan Pertama (Rp)', 'value' => (int) $summary['first_installment']],
            ['label' => 'Cicilan Terakhir (Rp)', 'value' => (int) $summary['last_installment']],
            ['label' => 'Total Bunga (Rp)', 'value' => (int) $summary['total_interest']],
            ['label' => 'Total Pembayaran (Rp)', 'value' => (int) $summary['total_payment']],
        ];

        $columns = [
            ['label' => 'Angsuran ke', 'width' => 14, 'align' => 'center', 'format' => 'text'],
            ['label' => 'Periode', 'width' => 14, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Pokok (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Bunga (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Total Cicilan (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Sisa Pokok (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Keterangan', 'width' => 16, 'align' => 'center', 'format' => 'text'],
        ];

        $dataRows = [];
        foreach ($result['schedule'] as $row) {
            $dataRows[] = [
                $row['seqno'],
                $row['periode'],
                (int) $row['amount'],
                (int) $row['int_amt'],
                (int) $row['total'],
                (int) $row['outstand'],
                $row['rounding'] ? 'Rounding' : '',
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL',
                'values' => [
                    1 => '',
                    2 => (int) $summary['principal'],
                    3 => (int) $summary['total_interest'],
                    4 => (int) $summary['total_payment'],
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        $sheet = $spreadsheet->getActiveSheet();
        CuExcel::buildSheet(
            sheet: $sheet,
            title: $title,
            subtitle: $subtitle,
            columns: $columns,
            dataRows: $dataRows,
            totalRows: $totalRows,
            summaryPairs: $summaryPairs,
            includeNo: true
        );

        $filename = 'simulasi-kredit-'.$summary['method'].'-'.now()->format('Ymd_His').'.xlsx';

        return CuExcel::downloadResponse($spreadsheet, $filename);
    }
}
