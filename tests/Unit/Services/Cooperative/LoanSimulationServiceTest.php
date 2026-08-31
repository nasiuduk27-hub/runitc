<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\LoanSimulationService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoanSimulationServiceTest extends TestCase
{
    private LoanSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanSimulationService;
        CarbonImmutable::setTestNow('2026-12-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_flat_matches_excel_reference_case(): void
    {
        // Kasus referensi pinjam.xlsx: pokok 1jt, tenor 18, bunga 6% per tahun.
        $result = $this->service->simulate(1_000_000, 18, 6.0, LoanSimulationService::METHOD_FLAT);

        $summary = $result['summary'];
        $this->assertSame(LoanSimulationService::METHOD_FLAT, $summary['method']);
        $this->assertSame('Flat', $summary['method_label']);
        $this->assertSame(1_000_000, $summary['principal']);
        $this->assertSame(90_000, $summary['total_interest']);
        $this->assertSame(1_090_000, $summary['total_payment']);
        $this->assertSame(60_556, $summary['first_installment']);

        $schedule = $result['schedule'];
        $this->assertCount(18, $schedule);

        foreach (array_slice($schedule, 0, 17) as $index => $row) {
            $this->assertSame($index + 1, $row['seqno']);
            $this->assertSame(55_556, $row['amount'], "Cicilan pokok baris ke-{$row['seqno']} harus 55.556.");
            $this->assertSame(5_000, $row['int_amt']);
            $this->assertSame(60_556, $row['total']);
            $this->assertFalse($row['rounding']);
        }

        $first = $schedule[0];
        $this->assertSame(944_444, $first['outstand']);

        $last = $schedule[17];
        $this->assertSame(55_548, $last['amount'], 'Cicilan pokok terakhir harus menyesuaikan sisa pokok.');
        $this->assertSame(5_000, $last['int_amt']);
        $this->assertTrue($last['rounding']);
        $this->assertSame(0, $last['outstand'], 'Outstanding akhir harus tepat 0.');
    }

    public function test_effective_declines_and_settles_to_zero(): void
    {
        // Efektif: pokok bulanan tetap, bunga dihitung dari sisa pokok sehingga menurun.
        $result = $this->service->simulate(12_000_000, 12, 12.0, LoanSimulationService::METHOD_EFFECTIVE);

        $summary = $result['summary'];
        $schedule = $result['schedule'];

        $this->assertCount(12, $schedule);
        $this->assertSame(780_000, $summary['total_interest'], 'Total bunga efektif 1%/bulan dari saldo menurun.');
        $this->assertSame(12_780_000, $summary['total_payment']);
        $this->assertGreaterThan($summary['last_installment'], $summary['first_installment']);

        foreach ($schedule as $index => $row) {
            $this->assertSame(1_000_000, $row['amount']);
            $this->assertSame((12 - $index) * 10_000, $row['int_amt'], 'Bunga efektif harus menurun dari saldo sebelum angsuran.');
        }

        $this->assertSame(0, $schedule[11]['outstand']);
        $this->assertSame($summary['principal'] + $summary['total_interest'], array_sum(array_column($schedule, 'total')));
    }

    public function test_annuity_keeps_installment_constant_and_settles_to_zero(): void
    {
        $result = $this->service->simulate(50_000_000, 24, 10.5, LoanSimulationService::METHOD_ANNUITY);

        $summary = $result['summary'];
        $schedule = $result['schedule'];

        $this->assertCount(24, $schedule);
        $this->assertGreaterThan(0, $summary['total_interest']);

        // Cicilan relatif tetap: semua bulan kecuali terakhir bernilai sama.
        $midTotals = array_column(array_slice($schedule, 0, 23), 'total');
        $this->assertCount(1, array_unique($midTotals));
        $this->assertSame($midTotals[0], $summary['first_installment']);

        $this->assertSame(0, $schedule[23]['outstand']);
        $this->assertSame(
            $summary['principal'],
            array_sum(array_column($schedule, 'amount')),
            'Jumlah pokok pada jadwal harus sama dengan pokok pinjaman.'
        );
        $this->assertSame(
            $summary['total_payment'],
            array_sum(array_column($schedule, 'int_amt')) + $summary['principal']
        );
    }

    public function test_flat_rounding_cap_prevents_negative_final_installment(): void
    {
        // Pokok sangat kecil relatif terhadap tenor: ROUND bisa melebihi kapasitas,
        // cicilan normal harus diturunkan agar cicilan terakhir tidak negatif.
        $result = $this->service->simulate(11, 7, 0.0, LoanSimulationService::METHOD_FLAT);

        $schedule = $result['schedule'];

        $this->assertCount(7, $schedule);
        $this->assertSame(11, array_sum(array_column($schedule, 'amount')));
        foreach ($schedule as $row) {
            $this->assertGreaterThanOrEqual(0, $row['amount']);
            $this->assertSame(0, $row['int_amt']);
        }
        // Tanpa pembatasan kapasitas, jadwal menjadi [2,2,2,2,2,2,-1].
        // Dengan pembatasan: enam bulan pertama 1 dan bulan terakhir menanggung sisa 5.
        $this->assertSame([1, 1, 1, 1, 1, 1, 5], array_map('intval', array_column($schedule, 'amount')));
        $this->assertTrue($schedule[6]['rounding']);
        $this->assertSame(0, $schedule[6]['outstand']);
    }

    public function test_periode_follows_calendar_months_across_year(): void
    {
        // Periode YYYYMM harus benar melewati pergantian tahun: 202612 -> 202701.
        $result = $this->service->simulate(1_000, 14, 0.0, LoanSimulationService::METHOD_FLAT);

        $periodes = array_column($result['schedule'], 'periode');

        $this->assertSame('202612', $periodes[0]);
        $this->assertSame('202701', $periodes[1]);
        $this->assertSame('202801', $periodes[13]);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'pokok nol' => [0, 12, 6.0, 'flat'],
            'pokok negatif' => [-100, 12, 6.0, 'flat'],
            'tenor nol' => [1_000_000, 0, 6.0, 'flat'],
            'tenor melebihi batas' => [1_000_000, 121, 6.0, 'flat'],
            'bunga negatif' => [1_000_000, 12, -0.5, 'flat'],
            'bunga di atas 100' => [1_000_000, 12, 100.01, 'flat'],
            'metode tidak dikenal' => [1_000_000, 12, 6.0, 'bunga_jahat'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function test_it_rejects_invalid_input(int|float $principal, int $tenor, float $rate, string $method): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->simulate($principal, $tenor, $rate, $method);
    }
}
