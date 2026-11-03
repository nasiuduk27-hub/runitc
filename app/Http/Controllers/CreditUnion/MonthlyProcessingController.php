<?php

namespace App\Http\Controllers\CreditUnion;

use App\Exports\CreditUnion\PayrollReconciliationExport;
use App\Exports\MonthlyProcessingExport;
use App\Http\Controllers\Controller;
use App\Services\CreditUnion\CreditUnionPeriod;
use App\Services\CreditUnion\MonthlyProcessingService;
use App\Services\CreditUnion\PayrollReconciliationCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyProcessingController extends Controller
{
    public function __construct(private readonly MonthlyProcessingService $processing) {}

    public function index(Request $request): View
    {
        $period = (string) $request->query('period', CreditUnionPeriod::current());
        $company = (string) $request->query('cmpcd', '');
        $result = null;

        if ($request->boolean('generate') && CreditUnionPeriod::isValid($period) && isset($this->processing->companies()[$company])) {
            $result = $this->processing->generate($period);
        }

        return view('credit-union.monthly-processing.index', [
            'period' => $period,
            'company' => $company,
            'companies' => $this->processing->companies(),
            'periodOptions' => $this->processing->availablePeriods(),
            'result' => $result,
            'recon' => CreditUnionPeriod::isValid($period) ? $this->processing->reconciliation($period) : null,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $record = $this->processing->save($data['period'], $data['cmpcd'], (int) auth_user_id());

        // save() bersifat idempotent: simpan ulang menimpa tagihan bernomor sama.
        // Sampaikan eksplisit agar admin sadar ia menimpa, bukan membuat tagihan baru.
        $message = $record->wasRecentlyCreated
            ? 'Laporan Monthly Processing berhasil disimpan ke database.'
            : 'Laporan Monthly Processing berhasil disimpan (menimpa tagihan '.$record->trxno.' yang sudah ada untuk periode ini).';

        return redirect()->route('cu.monthly-processing.index', [
            'period' => $data['period'], 'cmpcd' => $data['cmpcd'], 'generate' => 1,
        ])->with('success', $message);
    }

    public function export(Request $request)
    {
        $data = $this->validated($request);
        $result = $this->processing->generate($data['period']);

        return (new MonthlyProcessingExport($result['rows'], $result['totals'], $data['period'], $data['cmpcd']))
            ->download('monthly-processing_'.$data['period'].'_'.$data['cmpcd'].'.xlsx');
    }

    public function exportReconciliation(Request $request, PayrollReconciliationCsv $csv, PayrollReconciliationExport $export): StreamedResponse
    {
        $scope = $this->validated($request);
        $input = $request->validate([
            'asal_data' => ['required', 'string', 'max:100'],
            'anggota' => ['required', 'file', 'max:2048'],
            'bank' => ['required', 'file', 'max:2048'],
        ]);
        $period = $scope['period'];
        $company = $scope['cmpcd'];

        return $export->download($period, $company, $input['asal_data'],
            $csv->rows($input['anggota'], 'anggota', $period, $company),
            $csv->rows($input['bank'], 'bank', $period, $company),
        );
    }

    public function reconciliationTemplate(string $type): BinaryFileResponse
    {
        $filename = match ($type) {
            'anggota' => 'contoh-anggota.csv',
            'bank' => 'contoh-bank.csv',
            'excel' => 'payroll-reconciliation.xlsx',
            default => abort(404),
        };

        return response()->download(resource_path('templates/credit-union/'.$filename));
    }

    /** @return array{period: string, cmpcd: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/'],
            'cmpcd' => ['required', 'string', 'size:3'],
        ]);

        if (! CreditUnionPeriod::isValid($data['period'])) {
            abort(422, 'Periode harus berupa YYYYMM yang valid.');
        }

        abort_unless(isset($this->processing->companies()[$data['cmpcd']]), 422, 'Perusahaan tidak valid.');

        return $data;
    }
}
