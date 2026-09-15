<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Services\Cooperative\LoanSimulationService;
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
        return view('cooperative.loan-simulation.index', [
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

        return $this->csv($result);
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
    private function csv(array $result): Response
    {
        /** @var array<string, int|float|string> $summary */
        $summary = $result['summary'];

        $filename = 'simulasi-kredit-'.$summary['method'].'-'.now()->format('Ymd_His').'.csv';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        $writeRow = fn (array $fields): array|false => fputcsv($handle, $fields, ',', '"', '\\');
        $writeRow(['Simulasi Kredit Koperasi']);
        $writeRow(['Jenis Kredit', $summary['method_label']]);
        $writeRow(['Jumlah Kredit (Rp)', $summary['principal']]);
        $writeRow(['Jangka Waktu (Bulan)', $summary['tenor_months']]);
        $writeRow(['Bunga per Tahun (%)', $summary['annual_rate']]);
        $writeRow(['Cicilan Pertama (Rp)', $summary['first_installment']]);
        $writeRow(['Cicilan Terakhir (Rp)', $summary['last_installment']]);
        $writeRow(['Total Bunga (Rp)', $summary['total_interest']]);
        $writeRow(['Total Pembayaran (Rp)', $summary['total_payment']]);
        $writeRow([]);
        $writeRow(['Angsuran ke', 'Periode', 'Pokok (Rp)', 'Bunga (Rp)', 'Total Cicilan (Rp)', 'Sisa Pokok (Rp)', 'Keterangan']);

        foreach ($result['schedule'] as $row) {
            $writeRow([
                $row['seqno'],
                $row['periode'],
                $row['amount'],
                $row['int_amt'],
                $row['total'],
                $row['outstand'],
                $row['rounding'] ? 'Rounding' : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
