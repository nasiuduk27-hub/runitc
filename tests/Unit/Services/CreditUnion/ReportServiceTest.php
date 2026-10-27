<?php

namespace Tests\Unit\Services\CreditUnion;

use App\Services\CreditUnion\ReportService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportService;
    }

    public function test_classification_follows_period_and_paidst(): void
    {
        $asOf = '202608';

        $this->assertSame(ReportService::DUE_PAID, $this->service->classifyInstallment(1, '202401', $asOf), 'paidst=1 selalu lunas.');
        $this->assertSame(ReportService::DUE_PAID, $this->service->classifyInstallment(1, '202612', $asOf), 'Lunas meski periode di masa depan.');
        $this->assertSame(ReportService::DUE_OVERDUE, $this->service->classifyInstallment(0, '202607', $asOf));
        $this->assertSame(ReportService::DUE_NOW, $this->service->classifyInstallment(0, '202608', $asOf));
        $this->assertSame(ReportService::DUE_UPCOMING, $this->service->classifyInstallment(0, '202609', $asOf));
    }

    public function test_classification_rejects_invalid_periods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->classifyInstallment(0, '202613', '202608');
    }

    public function test_ln_status_labels_match_sys_seqflow(): void
    {
        $expected = [
            0 => 'Draft',
            3 => 'Loan Submitted',
            4 => 'Partial Payment',
            5 => 'Loan Completed',
            6 => 'Loan Rejected',
        ];

        foreach ($expected as $code => $label) {
            $this->assertSame($label, $this->service->lnStatusLabel($code));
        }

        $this->assertSame('Kode 9', $this->service->lnStatusLabel(9));
        $this->assertSame('Draft', $this->service->lnStatusLabel(null), 'Null di-cast ke 0 (Draft).');
    }

    public function test_due_label_covers_all_classifications(): void
    {
        foreach (ReportService::DUE_LABELS as $classification => $label) {
            $this->assertSame($label, $this->service->dueLabel($classification));
        }
    }

    public function test_aggregate_interest_revenue_filters_and_groups_correctly(): void
    {
        $rows = [
            // Periode sebelum rentang: diabaikan
            ['periode' => '202512', 'paidst' => 1, 'int_amt' => 50000, 'others' => 10000],
            // Periode dalam rentang tapi belum dibayar (paidst=0): diabaikan
            ['periode' => '202601', 'paidst' => 0, 'int_amt' => 30000, 'others' => 5000],
            // Periode dalam rentang, paidst=1, others diabaikan
            ['periode' => '202601', 'paidst' => 1, 'int_amt' => 25000, 'others' => 15000],
            ['periode' => '202601', 'paidst' => 1, 'int_amt' => 35000, 'others' => 2000],
            // Periode kedua dalam rentang
            ['periode' => '202603', 'paidst' => 1, 'int_amt' => 40000, 'others' => 0],
            // Periode setelah rentang: diabaikan
            ['periode' => '202604', 'paidst' => 1, 'int_amt' => 60000, 'others' => 5000],
        ];

        $result = $this->service->aggregateInterestRevenue($rows, '202601', '202603');

        $this->assertCount(2, $result['rows']);
        $this->assertSame('202601', $result['rows'][0]['periode']);
        $this->assertSame(2, $result['rows'][0]['installment_count']);
        $this->assertSame(60000, $result['rows'][0]['interest_total']);

        $this->assertSame('202603', $result['rows'][1]['periode']);
        $this->assertSame(1, $result['rows'][1]['installment_count']);
        $this->assertSame(40000, $result['rows'][1]['interest_total']);

        $this->assertSame(3, $result['totals']['installments']);
        $this->assertSame(100000, $result['totals']['interest']);
    }
}
