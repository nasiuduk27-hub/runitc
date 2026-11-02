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

    public function test_aggregate_interest_by_member_filters_groups_and_reconciles(): void
    {
        $rows = [
            // (f) di luar rentang: diabaikan
            ['mst_rec_id' => 1, 'trnno' => 'LN-001', 'icuno' => 'CU-0002', 'icunm' => 'Budi Santoso', 'periode' => '202512', 'paidst' => 1, 'seqno' => 1, 'totseqno' => 12, 'amount' => 500000, 'int_amt' => 50000, 'others' => 99999],
            ['mst_rec_id' => 1, 'trnno' => 'LN-001', 'icuno' => 'CU-0002', 'icunm' => 'Budi Santoso', 'periode' => '202604', 'paidst' => 1, 'seqno' => 4, 'totseqno' => 12, 'amount' => 500000, 'int_amt' => 50000, 'others' => 99999],
            // (a) paidst = 0 dalam rentang: diabaikan
            ['mst_rec_id' => 1, 'trnno' => 'LN-001', 'icuno' => 'CU-0002', 'icunm' => 'Budi Santoso', 'periode' => '202601', 'paidst' => 0, 'seqno' => 2, 'totseqno' => 12, 'amount' => 500000, 'int_amt' => 50000, 'others' => 99999],
            // (b) paidst = 1, others diabaikan, (g) 2 pinjaman berbeda untuk Budi
            ['mst_rec_id' => 1, 'trnno' => 'LN-001', 'icuno' => 'CU-0002', 'icunm' => 'Budi Santoso', 'periode' => '202601', 'paidst' => 1, 'seqno' => 2, 'totseqno' => 12, 'amount' => 500000, 'int_amt' => 25000, 'others' => 12345],
            ['mst_rec_id' => 2, 'trnno' => 'LN-002', 'icuno' => 'CU-0002', 'icunm' => 'Budi Santoso', 'periode' => '202602', 'paidst' => 1, 'seqno' => 1, 'totseqno' => 6, 'amount' => 300000, 'int_amt' => 15000, 'others' => 0],
            // Anggota A: Andi (nama lebih awal dari Budi, harus urutan 1)
            ['mst_rec_id' => 3, 'trnno' => 'LN-003', 'icuno' => 'CU-0001', 'icunm' => 'Andi Wijaya', 'periode' => '202601', 'paidst' => 1, 'seqno' => 1, 'totseqno' => 10, 'amount' => 400000, 'int_amt' => 40000, 'others' => 5000],
            // (d) Baris tanpa pinjaman / tanpa anggota: masuk kelompok Anggota Tidak Tertaut, urutan terakhir
            ['mst_rec_id' => 0, 'trnno' => '', 'icuno' => '', 'icunm' => '', 'periode' => '202601', 'paidst' => 1, 'seqno' => 1, 'totseqno' => 1, 'amount' => 100000, 'int_amt' => 10000, 'others' => 0],
        ];

        $detailResult = $this->service->aggregateInterestByMember($rows, '202601', '202603');
        $revenueResult = $this->service->aggregateInterestRevenue($rows, '202601', '202603');

        // (e) Rekonsiliasi: SUM bunga dan COUNT cicilan identik antara kedua method
        $this->assertSame($revenueResult['totals']['installments'], $detailResult['totals']['installments']);
        $this->assertSame($revenueResult['totals']['interest'], $detailResult['totals']['interest']);
        $this->assertSame(4, $detailResult['totals']['installments']);
        $this->assertSame(90000, $detailResult['totals']['interest']);
        $this->assertSame(1300000, $detailResult['totals']['principal']);

        // (c) Urutan nama naik: Andi (index 0), Budi (index 1), Anggota Tidak Tertaut (index 2)
        $this->assertCount(3, $detailResult['groups']);

        // Andi
        $this->assertSame('CU-0001', $detailResult['groups'][0]['icuno']);
        $this->assertSame('Andi Wijaya', $detailResult['groups'][0]['icunm']);
        $this->assertSame(1, $detailResult['groups'][0]['loan_count']);
        $this->assertSame(1, $detailResult['groups'][0]['installment_count']);
        $this->assertSame(40000, $detailResult['groups'][0]['interest_subtotal']);
        $this->assertSame(400000, $detailResult['groups'][0]['principal_subtotal']);

        // Budi ((g) 2 pinjaman berbeda)
        $this->assertSame('CU-0002', $detailResult['groups'][1]['icuno']);
        $this->assertSame('Budi Santoso', $detailResult['groups'][1]['icunm']);
        $this->assertSame(2, $detailResult['groups'][1]['loan_count']);
        $this->assertSame(2, $detailResult['groups'][1]['installment_count']);
        $this->assertSame(40000, $detailResult['groups'][1]['interest_subtotal']);
        $this->assertSame(800000, $detailResult['groups'][1]['principal_subtotal']);

        // (d) Anggota Tidak Tertaut: urutan terakhir, is_unlinked = true, terhitung di total
        $this->assertTrue($detailResult['groups'][2]['is_unlinked']);
        $this->assertSame('-', $detailResult['groups'][2]['icuno']);
        $this->assertSame('Anggota Tidak Tertaut', $detailResult['groups'][2]['icunm']);
        $this->assertSame(1, $detailResult['groups'][2]['installment_count']);
        $this->assertSame(10000, $detailResult['groups'][2]['interest_subtotal']);

        // Flat rows
        $this->assertCount(4, $detailResult['flat_rows']);
        $this->assertSame('1/10', $detailResult['flat_rows'][0]['installment_label']);
    }
}
