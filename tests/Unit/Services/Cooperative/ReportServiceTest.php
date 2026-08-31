<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\ReportService;
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
}
