<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\LoanPaymentService;
use App\Services\Cooperative\LoanPostingService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoanPaymentServiceTest extends TestCase
{
    private LoanPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanPaymentService;
    }

    /**
     * Kasus Excel: cicilan 60.556 (pokok 55.556 + bunga 5.000),
     * cicilan terakhir 60.548 (pokok 55.548 + bunga 5.000).
     *
     * @return list<array{rec_id: int, seqno: int, due: int, principal: int, interest: int, others: int, remaining: int}>
     */
    private function unpaidRows(): array
    {
        return [
            ['rec_id' => 101, 'seqno' => 1, 'due' => 60_556, 'principal' => 55_556, 'interest' => 5_000, 'others' => 0, 'remaining' => 60_556],
            ['rec_id' => 102, 'seqno' => 2, 'due' => 60_556, 'principal' => 55_556, 'interest' => 5_000, 'others' => 0, 'remaining' => 60_556],
            ['rec_id' => 103, 'seqno' => 3, 'due' => 60_548, 'principal' => 55_548, 'interest' => 5_000, 'others' => 0, 'remaining' => 60_548],
        ];
    }

    public function test_full_row_payment_marks_covers_full(): void
    {
        $allocations = $this->service->allocate(60_556, $this->unpaidRows());

        $this->assertCount(1, $allocations);
        $this->assertSame(101, $allocations[0]['dloan_rec_id']);
        $this->assertTrue($allocations[0]['covers_full']);
        // Urutan komponen bagian 8: bunga dibayar lebih dulu dari pokok.
        $this->assertSame(5_000, $allocations[0]['interest_applied']);
        $this->assertSame(55_556, $allocations[0]['principal_applied']);
    }

    public function test_partial_payment_keeps_row_incomplete(): void
    {
        $allocations = $this->service->allocate(30_000, $this->unpaidRows());

        $this->assertCount(1, $allocations);
        $this->assertFalse($allocations[0]['covers_full']);
        $this->assertSame(30_000, $allocations[0]['amount_applied']);
        // Bunga 5.000 lunas dulu, sisanya 25.000 mengurangi pokok.
        $this->assertSame(5_000, $allocations[0]['interest_applied']);
        $this->assertSame(25_000, $allocations[0]['principal_applied']);
    }

    public function test_spill_over_into_next_rows(): void
    {
        // Lunasi baris 1 penuh + parsial baris 2.
        $allocations = $this->service->allocate(80_000, $this->unpaidRows());

        $this->assertCount(2, $allocations);
        $this->assertTrue($allocations[0]['covers_full']);
        $this->assertFalse($allocations[1]['covers_full']);
        $this->assertSame(19_444, $allocations[1]['amount_applied']);
    }

    public function test_remaining_zero_rows_are_skipped(): void
    {
        $rows = $this->unpaidRows();
        $rows[0]['remaining'] = 0; // Sudah dialokasikan pembayaran parsial sebelumnya.

        $allocations = $this->service->allocate(60_556, $rows);

        $this->assertCount(1, $allocations);
        $this->assertSame(102, $allocations[0]['dloan_rec_id']);
        $this->assertTrue($allocations[0]['covers_full']);
    }

    public function test_exact_total_of_all_remaining_is_accepted(): void
    {
        $total = array_sum(array_column($this->unpaidRows(), 'remaining'));

        $allocations = $this->service->allocate($total, $this->unpaidRows());

        $this->assertCount(3, $allocations);
        $this->assertTrue(array_reduce($allocations, fn (bool $all, array $a): bool => $all && $a['covers_full'], true));
    }

    public static function invalidAllocationProvider(): array
    {
        return [
            'nominal nol' => [0],
            'nominal negatif' => [-1000],
            'melebihi total sisa' => [999_999],
        ];
    }

    #[DataProvider('invalidAllocationProvider')]
    public function test_allocation_rejects_invalid_amounts(int $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->allocate($amount, $this->unpaidRows());
    }

    public function test_allocation_rejects_when_nothing_remaining(): void
    {
        $rows = array_map(fn (array $row): array => [...$row, 'remaining' => 0], $this->unpaidRows());

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocate(60_556, $rows);
    }

    public function test_statrec_follows_ln_flow(): void
    {
        // sys_seqflow LN: 4 = Partial Payment, 5 = Loan Completed.
        $this->assertSame(4, $this->service->statrecAfterPayment(22));
        $this->assertSame(4, $this->service->statrecAfterPayment(1));
        $this->assertSame(5, $this->service->statrecAfterPayment(0));
    }

    public function test_maker_checker_blocks_self_verification(): void
    {
        $this->assertFalse($this->service->canVerify(10, 10));
        $this->assertTrue($this->service->canVerify(10, 20));
    }

    public function test_transitions_only_from_submitted(): void
    {
        foreach ([LoanPaymentService::STATUS_VERIFIED, LoanPaymentService::STATUS_REJECTED, LoanPaymentService::STATUS_CANCELLED] as $target) {
            $this->service->assertTransition(LoanPaymentService::STATUS_SUBMITTED, $target);
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->assertTransition(LoanPaymentService::STATUS_VERIFIED, LoanPaymentService::STATUS_REJECTED);
    }

    public function test_pmt_trnno_format_and_continuation_logic(): void
    {
        // Format PMT mengikuti pola legacy yang terlihat di req_frm_trxno bank_trx.
        $this->assertSame('PMT-26H-0111', LoanPostingService::formatLegacyTrnno('PMT', CarbonImmutable::parse('2026-08-26'), 111));
        $this->assertSame('RCV-24E-0749', LoanPostingService::formatLegacyTrnno('RCV', CarbonImmutable::parse('2024-05-10'), 749));
    }
}
