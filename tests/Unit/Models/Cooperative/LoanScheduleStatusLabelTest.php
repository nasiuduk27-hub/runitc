<?php

namespace Tests\Unit\Models\Cooperative;

use App\Models\Cooperative\CooperativeLoanSchedule;
use PHPUnit\Framework\TestCase;

class LoanScheduleStatusLabelTest extends TestCase
{
    public function test_unpaid_row_without_payment_number_is_belum_bayar(): void
    {
        // Kondisi seluruh data existing: paidst = 0 dan payno kosong.
        $schedule = new CooperativeLoanSchedule;
        $schedule->paidst = 0;
        $schedule->payno = '';

        $this->assertSame('Belum Bayar', $schedule->paymentStatusLabel());
        $this->assertStringContainsString('gray', $schedule->paymentStatusBadgeClass());
    }

    public function test_paidst_zero_but_payno_filled_needs_verification(): void
    {
        // Data tidak konsisten: paidst=0 tapi ada nomor pembayaran.
        $schedule = new CooperativeLoanSchedule;
        $schedule->paidst = 0;
        $schedule->payno = 'PAY-001';

        $this->assertSame('Perlu Verifikasi', $schedule->paymentStatusLabel());
        $this->assertStringContainsString('amber', $schedule->paymentStatusBadgeClass());
    }

    public function test_unknown_paidst_code_is_displayed_as_is(): void
    {
        // Nilai paidst selain 0 belum dikonfirmasi maknanya (bagian 34.3 dokumen).
        $schedule = new CooperativeLoanSchedule;
        $schedule->paidst = 1;
        $schedule->payno = '';

        $this->assertSame('Kode 1', $schedule->paymentStatusLabel());
    }

    public function test_rounding_row_detected_from_remarks(): void
    {
        $rounding = new CooperativeLoanSchedule;
        $rounding->remarks = 'Rounding';

        $normal = new CooperativeLoanSchedule;
        $normal->remarks = '';

        $this->assertTrue($rounding->isRoundingRow());
        $this->assertFalse($normal->isRoundingRow());
    }

    public function test_installment_label_uses_totseqno_when_available(): void
    {
        $withTotal = new CooperativeLoanSchedule;
        $withTotal->seqno = 3;
        $withTotal->totseqno = 24;

        $withoutTotal = new CooperativeLoanSchedule;
        $withoutTotal->seqno = 5;
        $withoutTotal->totseqno = 0;

        $this->assertSame('3/24', $withTotal->installmentLabel());
        $this->assertSame('5', $withoutTotal->installmentLabel());
    }

    public function test_total_due_sums_principal_interest_and_others(): void
    {
        $schedule = new CooperativeLoanSchedule;
        $schedule->amount = 1_500_000;
        $schedule->int_amt = 22_500;
        $schedule->others = 7_500;

        $this->assertSame(1_530_000, $schedule->totalDue());
    }
}
