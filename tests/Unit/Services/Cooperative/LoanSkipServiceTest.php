<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\LoanSkipService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoanSkipServiceTest extends TestCase
{
    private LoanSkipService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanSkipService;
    }

    /**
     * Jadwal 4 baris (semua unpaid), pola flat 55.556/5.000, terakhir 55.548.
     *
     * @return list<array{rec_id: int, seqno: int, periode: string, amount: int, int_amt: int, others: int, paidst: int}>
     */
    private function rows(): array
    {
        return [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202609', 'amount' => 55_556, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202610', 'amount' => 55_556, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202611', 'amount' => 55_556, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 4, 'seqno' => 4, 'periode' => '202612', 'amount' => 55_548, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
        ];
    }

    public function test_plan_moves_principal_and_extends_tenor_by_exact_months(): void
    {
        $plan = $this->service->plan($this->rows(), '202609', 2);

        // Baris Sep-Okt diskip; pokoknya dipindah ke ekor.
        $this->assertSame([1, 2], $plan['target_rec_ids']);
        $this->assertSame(111_112, $plan['moved_principal']);

        // Dua baris baru setelah Desember: Jan & Feb 2027 (kalender benar).
        $this->assertSame(['202701', '202702'], array_column($plan['new_rows'], 'periode'));

        // Pokok pindahan dibagi rata: 111.112 / 2 = 55.556 per baris.
        $this->assertSame([55_556, 55_556], array_column($plan['new_rows'], 'amount'));

        // Bunga bulanan flat tetap; biaya perpanjang = 2 x bunga.
        $this->assertSame(5_000, $plan['monthly_interest']);
        $this->assertSame(10_000, $plan['extra_interest']);

        // Tenor bertambah tepat sejumlah bulan skip.
        $this->assertSame(6, $plan['new_term']);
        $this->assertSame('202702', $plan['new_last_periode']);
    }

    public function test_plan_distributes_remainder_without_loss(): void
    {
        $rows = $this->rows();
        $rows[0]['amount'] = 100;
        $rows[1]['amount'] = 101;

        // Skip 2 bulan (Sep-Okt): pokok dipindah 201 ke 2 baris baru -> 101 + 100.
        $plan = $this->service->plan($rows, '202609', 2);

        $this->assertSame(201, $plan['moved_principal']);
        $amounts = array_column($plan['new_rows'], 'amount');
        $this->assertSame([101, 100], $amounts);
        $this->assertSame(201, array_sum($amounts));
        $this->assertSame(['202701', '202702'], array_column($plan['new_rows'], 'periode'));
    }

    public function test_plan_ignores_paid_rows_inside_window(): void
    {
        $rows = $this->rows();
        $rows[0]['paidst'] = 1; // Baris pertama sudah dibayar.

        $plan = $this->service->plan($rows, '202609', 2);

        // Hanya baris Okt yang bisa diskip.
        $this->assertSame([2], $plan['target_rec_ids']);
        $this->assertSame(55_556, $plan['moved_principal']);
    }

    public function test_plan_rejects_window_without_unpaid_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Semua baris berakhir 202612; window mulai 202710 tidak memotong apa pun.
        $this->service->plan($this->rows(), '202710', 2);
    }

    public static function invalidParamsProvider(): array
    {
        return [
            'bulan nol' => ['202609', 0],
            'bulan 13' => ['202609', 13],
            'periode invalid' => ['202613', 1],
            'periode huruf' => ['abcdef', 1],
        ];
    }

    #[DataProvider('invalidParamsProvider')]
    public function test_plan_rejects_invalid_parameters(string $startPeriod, int $months): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->plan($this->rows(), $startPeriod, $months);
    }

    public function test_accelerate_shortens_tenor_and_recalculates(): void
    {
        // 4 baris unpaid (Sep 2026 - Des 2026): total pokok 222.216, bunga 20.000.
        $plan = $this->service->acceleratePlan($this->rows(), 2);

        // Dua baris ekor (Nov & Des) dihapus.
        $this->assertSame([3, 4], $plan['removed_rec_ids']);
        $this->assertSame(2, $plan['removed_rows']);

        // Pokok tetap utuh tapi dikalkulasi ulang ke 2 baris sisa (222.216 / 2 = 111.108).
        $this->assertSame([111_108, 111_108], array_column($plan['remaining_rows'], 'amount'));
        $this->assertSame(222_216, array_sum(array_column($plan['remaining_rows'], 'amount')));

        // Bunga tetap ditagih penuh & dikalkulasi ulang (20.000 / 2 = 10.000 per baris).
        $this->assertSame([10_000, 10_000], array_column($plan['remaining_rows'], 'int_amt'));
        $this->assertSame(20_000, $plan['retained_interest']);

        // Tenor berkurang tepat 2 bulan: 4 -> 2; jadwal berakhir di periode baris terakhir sisa.
        $this->assertSame(2, $plan['new_term']);
        $this->assertSame('202610', $plan['new_last_periode']);
    }

    public function test_accelerate_distributes_remainder_without_loss(): void
    {
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202609', 'amount' => 10, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202610', 'amount' => 10, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202611', 'amount' => 11, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->acceleratePlan($rows, 1);

        // Pokok 31 dikalkulasi ulang ke 2 baris: 16 + 15 (sisa 1 disalurkan ke baris awal).
        $amounts = array_column($plan['remaining_rows'], 'amount');
        $this->assertSame([16, 15], $amounts);
        $this->assertSame(31, array_sum($amounts));

        // Bunga 15 dikalkulasi ulang ke 2 baris: 8 + 7.
        $interests = array_column($plan['remaining_rows'], 'int_amt');
        $this->assertSame([8, 7], $interests);
        $this->assertSame(15, array_sum($interests));
    }

    public function test_accelerate_can_start_from_selected_period(): void
    {
        $plan = $this->service->acceleratePlan($this->rows(), 1, '202610');

        $this->assertSame([4], $plan['removed_rec_ids']);
        $this->assertSame([2, 3], array_column($plan['remaining_rows'], 'rec_id'));
        $this->assertSame(3, $plan['new_term']);
    }

    public function test_accelerate_rejects_too_many_months(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // 4 baris unpaid tidak boleh dipercepat 4 bulan (harus menyisakan >= 1 baris).
        $this->service->acceleratePlan($this->rows(), 4);
    }

    public function test_accelerate_rejects_when_no_unpaid_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $rows = $this->rows();
        foreach ($rows as $index => $row) {
            $rows[$index]['paidst'] = 1;
        }

        $this->service->acceleratePlan($rows, 1);
    }

    public function test_schedule_with_status_marks_paid_rows(): void
    {
        $rows = $this->rows();
        $rows[0]['paidst'] = 1; // Januari sudah terbayar.
        $rows[0]['payno'] = 'TRX-1';

        $schedule = $this->service->scheduleWithStatus($rows);

        $this->assertSame(LoanSkipService::ROW_PAID, $schedule[0]['status']);
        $this->assertCount(4, $schedule);
    }

    public function test_after_schedule_skip_zeros_target_and_appends_new_rows(): void
    {
        $rows = $this->rows();
        $plan = $this->service->plan($rows, '202609', 2);

        $after = $this->service->afterSchedule($rows, $plan, LoanSkipService::MODE_SKIP);

        // Baris target (rec 1 & 2) menjadi skip: pokok 0, status Ajukan Refinancing.
        $this->assertSame(LoanSkipService::ROW_SKIP, $after[0]['status']);
        $this->assertSame(0, $after[0]['amount']);
        $this->assertSame(LoanSkipService::ROW_SKIP, $after[1]['status']);
        $this->assertSame(0, $after[1]['amount']);

        // Baris tak terdampak tetap.
        $this->assertSame(LoanSkipService::ROW_NOT_DUE, $after[2]['status']);
        $this->assertSame(55_556, $after[2]['amount']);

        // Dua baris baru di ekor, tanda Baru.
        $this->assertCount(6, $after);
        $this->assertSame(LoanSkipService::ROW_NEW, $after[4]['status']);
        $this->assertSame(LoanSkipService::ROW_NEW, $after[5]['status']);
        $this->assertSame('202702', $after[5]['periode']);
    }

    public function test_after_schedule_accelerate_removes_tail_and_recalculates(): void
    {
        $rows = $this->rows();
        $plan = $this->service->acceleratePlan($rows, 2);

        $after = $this->service->afterSchedule($rows, $plan, LoanSkipService::MODE_ACCELERATE);

        // Dua baris ekor (rec 3 & 4) dihapus; dua baris sisa dihitung ulang.
        $this->assertCount(2, $after);
        $this->assertSame([111_108, 111_108], array_column($after, 'amount'));
        $this->assertSame([10_000, 10_000], array_column($after, 'int_amt'));
    }

    public function test_accelerate_preserves_existing_skip_rows(): void
    {
        // Mencerminkan pinjaman yang sudah pernah di-skip: baris 2 & 3 = refinancing
        // (pokok 0), baris 4-7 = angsuran normal.
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202608', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 1, 'payno' => 'TRX-1'],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202609', 'amount' => 0, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202610', 'amount' => 0, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 4, 'seqno' => 4, 'periode' => '202611', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 5, 'seqno' => 5, 'periode' => '202612', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 6, 'seqno' => 6, 'periode' => '202701', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 7, 'seqno' => 7, 'periode' => '202702', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->acceleratePlan($rows, 2);

        // Hanya 4 baris normal yang dipercepat: dua baris ekor (rec 6 & 7) dihapus.
        $this->assertSame([6, 7], $plan['removed_rec_ids']);
        $this->assertSame(800_000, $plan['moved_principal']);
        $this->assertSame(5, $plan['new_term']);
        $this->assertSame('202612', $plan['new_last_periode']);

        // Pokok normal (800.000) dibagi ke 2 baris sisa (rec 4 & 5).
        $this->assertSame([400_000, 400_000], array_column($plan['remaining_rows'], 'amount'));

        // Jadwal sesudah: baris skip tetap "Ajukan Refinancing", pokok normal utuh.
        $after = $this->service->afterSchedule($rows, $plan, LoanSkipService::MODE_ACCELERATE);

        $this->assertCount(5, $after);
        $this->assertSame(LoanSkipService::ROW_PAID, $after[0]['status']);
        $this->assertSame(LoanSkipService::ROW_SKIP, $after[1]['status']);
        $this->assertSame(0, $after[1]['amount']);
        $this->assertSame(LoanSkipService::ROW_SKIP, $after[2]['status']);
        $this->assertSame(0, $after[2]['amount']);
        $this->assertSame(400_000, $after[3]['amount']);
        $this->assertSame(400_000, $after[4]['amount']);

        // Total pokok seluruh jadwal tetap = pokok pinjaman.
        $this->assertSame(1_000_000, array_sum(array_column($after, 'amount')));
    }

    public function test_reduce_plan_splits_savings_evenly(): void
    {
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202609', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202610', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202611', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 4, 'seqno' => 4, 'periode' => '202612', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->reducePlan($rows, 400_000);

        $this->assertSame(400_000, $plan['savings_applied']);
        $this->assertSame(4, $plan['periods']);
        $this->assertSame(100_000, $plan['deduction_per_period']);
        $this->assertSame([100_000, 100_000, 100_000, 100_000], array_column($plan['remaining_rows'], 'amount'));
        $this->assertSame([5_000, 5_000, 5_000, 5_000], array_column($plan['remaining_rows'], 'int_amt'));
        $this->assertSame(4, $plan['new_term']);
        $this->assertFalse($plan['capped']);
    }

    public function test_reduce_plan_distributes_remainder_without_loss(): void
    {
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202609', 'amount' => 100, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202610', 'amount' => 100, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202611', 'amount' => 100, 'int_amt' => 5, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->reducePlan($rows, 100);

        // Potongan 100 / 3 = 34 + 33 + 33, pokok jadi 66 + 67 + 67.
        $this->assertSame([66, 67, 67], array_column($plan['remaining_rows'], 'amount'));
        $this->assertSame(200, array_sum(array_column($plan['remaining_rows'], 'amount')));
        $this->assertSame(33, $plan['deduction_per_period']);
    }

    public function test_reduce_plan_caps_at_total_principal(): void
    {
        $plan = $this->service->reducePlan($this->rows(), 999_999);

        $this->assertSame(222_216, $plan['savings_applied']);
        $this->assertTrue($plan['capped']);
        $this->assertSame([0, 0, 0, 0], array_column($plan['remaining_rows'], 'amount'));
        $this->assertSame(0, $plan['total_principal_after']);
    }

    public function test_reduce_plan_ignores_skip_and_paid_rows(): void
    {
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202608', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 1],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202609', 'amount' => 0, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 3, 'seqno' => 3, 'periode' => '202610', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 4, 'seqno' => 4, 'periode' => '202611', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->reducePlan($rows, 200_000);

        $this->assertSame(2, $plan['periods']);
        $this->assertSame([3, 4], array_column($plan['remaining_rows'], 'rec_id'));
        $this->assertSame([100_000, 100_000], array_column($plan['remaining_rows'], 'amount'));
        $this->assertSame(4, $plan['new_term']);
    }

    public function test_reduce_plan_rejects_non_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->reducePlan($this->rows(), 0);
    }

    public function test_reduce_plan_rejects_when_no_normal_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $rows = $this->rows();
        foreach ($rows as $index => $row) {
            $rows[$index]['amount'] = 0;
        }

        $this->service->reducePlan($rows, 100_000);
    }

    public function test_after_schedule_savings_marks_reduced_rows(): void
    {
        $rows = [
            ['rec_id' => 1, 'seqno' => 1, 'periode' => '202609', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
            ['rec_id' => 2, 'seqno' => 2, 'periode' => '202610', 'amount' => 200_000, 'int_amt' => 5_000, 'others' => 0, 'paidst' => 0],
        ];

        $plan = $this->service->reducePlan($rows, 100_000);
        $after = $this->service->afterSchedule($rows, $plan, LoanSkipService::MODE_SAVINGS);

        $this->assertSame(LoanSkipService::ROW_SAVINGS, $after[0]['status']);
        $this->assertSame(LoanSkipService::ROW_SAVINGS, $after[1]['status']);
        $this->assertSame(150_000, $after[0]['amount']);
        $this->assertSame(5_000, $after[0]['int_amt']);
    }
}
