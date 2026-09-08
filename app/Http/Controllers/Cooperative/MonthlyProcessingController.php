<?php

namespace App\Http\Controllers\Cooperative;

use App\Exports\MonthlyProcessingExport;
use App\Http\Controllers\Controller;
use App\Services\Cooperative\CooperativePeriod;
use App\Services\Cooperative\MonthlyProcessingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonthlyProcessingController extends Controller
{
    public function __construct(private readonly MonthlyProcessingService $processing) {}

    public function index(Request $request): View
    {
        $period = (string) $request->query('period', CooperativePeriod::current());
        $company = (string) $request->query('cmpcd', '');
        $result = null;

        if ($request->boolean('generate') && CooperativePeriod::isValid($period) && isset($this->processing->companies()[$company])) {
            $result = $this->processing->generate($period);
        }

        return view('cooperative.monthly-processing.index', [
            'period' => $period,
            'company' => $company,
            'companies' => $this->processing->companies(),
            'result' => $result,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->processing->save($data['period'], $data['cmpcd'], (int) auth_user_id());

        return redirect()->route('cooperative.monthly-processing.index', [
            'period' => $data['period'], 'cmpcd' => $data['cmpcd'], 'generate' => 1,
        ])->with('success', 'Laporan Monthly Processing berhasil disimpan ke database.');
    }

    public function export(Request $request)
    {
        $data = $this->validated($request);
        $result = $this->processing->generate($data['period']);

        return (new MonthlyProcessingExport($result['rows'], $result['totals']))
            ->download('monthly-processing_'.$data['period'].'_'.$data['cmpcd'].'.xlsx');
    }

    /** @return array{period: string, cmpcd: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/'],
            'cmpcd' => ['required', 'string', 'size:3'],
        ]);

        if (! CooperativePeriod::isValid($data['period'])) {
            abort(422, 'Periode harus berupa YYYYMM yang valid.');
        }

        abort_unless(isset($this->processing->companies()[$data['cmpcd']]), 422, 'Perusahaan tidak valid.');

        return $data;
    }
}
