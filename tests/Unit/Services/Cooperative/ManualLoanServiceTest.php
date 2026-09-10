<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\LoanSimulationService;
use App\Services\Cooperative\ManualLoanService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ManualLoanServiceTest extends TestCase
{
    private ManualLoanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ManualLoanService;
        CarbonImmutable::setTestNow('2026-12-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_simulate_master_adds_exclude_admin_fee_to_first_installment(): void
    {
        $result = $this->service->simulateMaster([
            'principal' => 1_000_000,
            'term' => 12,
            'annual_rate' => 6.0,
            'calculation_method' => LoanSimulationService::METHOD_FLAT,
            'trndt' => '2026-12-15',
            'admin_fee' => 50_000,
            'admin_fee_type' => 'exclude',
        ]);

        $this->assertSame(50_000, $result['summary']['admin_fee']);
        $this->assertSame('202612', $result['startper']);
        $this->assertSame(138_333, $result['schedule'][0]['total']);
        $this->assertSame(1_110_000, $result['summary']['total_payment']);
    }

    public function test_simulate_master_include_admin_fee_keeps_installment(): void
    {
        $result = $this->service->simulateMaster([
            'principal' => 1_000_000,
            'term' => 12,
            'annual_rate' => 6.0,
            'calculation_method' => LoanSimulationService::METHOD_FLAT,
            'trndt' => '2026-12-15',
            'admin_fee' => 50_000,
            'admin_fee_type' => 'include',
        ]);

        $this->assertSame(50_000, $result['summary']['admin_fee']);
        $this->assertSame(88_333, $result['schedule'][0]['total']);
        $this->assertSame(1_060_000, $result['summary']['total_payment']);
    }
}
