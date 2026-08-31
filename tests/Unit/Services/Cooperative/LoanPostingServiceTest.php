<?php

namespace Tests\Unit\Services\Cooperative;

use App\Models\Cooperative\CooperativeLoanApplication;
use App\Services\Cooperative\LoanApplicationService;
use App\Services\Cooperative\LoanPostingService;
use App\Services\Cooperative\LoanSimulationService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LoanPostingServiceTest extends TestCase
{
    private LoanPostingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanPostingService(new LoanApplicationService(new LoanSimulationService));
    }

    private function makeApplication(): CooperativeLoanApplication
    {
        $application = new CooperativeLoanApplication;
        $application->id = 1;
        $application->member_rec_id = 53;
        $application->member_icuno = 'CU-0001';
        $application->descr = 'Renovasi rumah';
        $application->principal_amount = 3_000_000;
        $application->annual_rate_percent = 6.0;

        return $application;
    }

    /**
     * @return list<array<string, int|bool|string>>
     */
    private function flatSchedule(int $principal, int $tenor): array
    {
        $result = (new LoanSimulationService)->simulate($principal, $tenor, 6.0, LoanSimulationService::METHOD_FLAT);

        return $result['schedule'];
    }

    public function test_mloan_row_follows_legacy_flat_pattern(): void
    {
        // Kasus Excel: 3jt / 12 bulan / 6% -> bunga bulanan 15.000, pokok 250.000.
        $schedule = $this->flatSchedule(3_000_000, 12);
        $row = $this->service->buildMloanRow($this->makeApplication(), $schedule, 'LON-26A-0115', '202608');

        $this->assertSame('21', $row['trncd']);
        $this->assertSame('LON-26A-0115', $row['trnno']);
        $this->assertSame(53, $row['icu_rec_id']);
        $this->assertSame(3_000_000, $row['principle']);
        $this->assertSame(180_000, $row['interamt'], 'Total bunga flat = 15.000 x 12.');
        $this->assertSame(6.0, $row['interest']);
        $this->assertSame(3_180_000, $row['totalloan'], 'Total tagihan = pokok + bunga.');
        $this->assertSame(250_000, $row['avgmon']);
        $this->assertSame(15_000, $row['avgint']);
        $this->assertSame(265_000, $row['monthly']);
        $this->assertSame(12, $row['term']);
        $this->assertSame((string) $schedule[0]['periode'], $row['startper']);
        $this->assertSame((string) end($schedule)['periode'], $row['endper']);
        $this->assertSame(0, $row['paid']);
        $this->assertSame('Posting CU-0001', $row['remarks']);
    }

    public function test_dloan_rows_reproduce_outstand_and_rounding_marker(): void
    {
        // Kasus Excel penuh: 1jt / 18 bulan / 6%.
        $application = $this->makeApplication();
        $application->principal_amount = 1_000_000;
        $schedule = $this->flatSchedule(1_000_000, 18);

        $rows = $this->service->buildDloanRows(777, $application, $schedule);

        $this->assertCount(18, $rows);
        $this->assertSame(777, $rows[0]['mst_rec_id']);

        // Cicilan pertama: pokok 55.556, sisa pokok 944.444.
        $this->assertSame(55_556, $rows[0]['amount']);
        $this->assertSame(944_444, $rows[0]['outstand']);

        // dseqno urutan mundur: baris pertama 18, terakhir 1 (pola existing).
        $this->assertSame(18, $rows[0]['dseqno']);
        $this->assertSame(1, $rows[17]['dseqno']);

        // Cicilan terakhir: koreksi pembulatan + marker Rounding + outstanding 0.
        $this->assertSame(55_548, $rows[17]['amount']);
        $this->assertSame(0, $rows[17]['outstand']);
        $this->assertSame('Rounding', $rows[17]['remarks']);
        $this->assertSame('', $rows[0]['remarks']);

        // Total pokok pada jadwal tepat sama dengan pinjaman.
        $this->assertSame(1_000_000, array_sum(array_column($rows, 'amount')));
    }

    public function test_trnno_format_follows_legacy_pattern(): void
    {
        // Huruf bulan mengikuti pola existing: A=Jan s.d. L=Des, urutan 4 digit.
        $this->assertSame('LON-26A-0001', LoanPostingService::formatTrnno(CarbonImmutable::parse('2026-01-15'), 1));
        $this->assertSame('LON-26A-0115', LoanPostingService::formatTrnno(CarbonImmutable::parse('2026-01-15'), 115));
        $this->assertSame('LON-25L-0042', LoanPostingService::formatTrnno(CarbonImmutable::parse('2025-12-28'), 42));
        $this->assertSame('LON-23I-0014', LoanPostingService::formatTrnno(CarbonImmutable::parse('2023-09-28'), 14));
    }
}
