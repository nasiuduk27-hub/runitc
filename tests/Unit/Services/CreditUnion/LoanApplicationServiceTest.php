<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\LoanApplicationService;
use App\Services\Cooperative\LoanSimulationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoanApplicationServiceTest extends TestCase
{
    private LoanApplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanApplicationService(new LoanSimulationService);
    }

    public function test_submitted_can_transition_to_approved_rejected_or_cancelled(): void
    {
        foreach ([LoanApplicationService::STATUS_APPROVED, LoanApplicationService::STATUS_REJECTED, LoanApplicationService::STATUS_CANCELLED] as $target) {
            $this->service->assertTransition(LoanApplicationService::STATUS_SUBMITTED, $target);
            $this->addToAssertionCount(1);
        }
    }

    public function test_approved_can_only_be_cancelled_before_posting(): void
    {
        $this->service->assertTransition(LoanApplicationService::STATUS_APPROVED, LoanApplicationService::STATUS_CANCELLED);

        $this->expectException(InvalidArgumentException::class);
        $this->service->assertTransition(LoanApplicationService::STATUS_APPROVED, LoanApplicationService::STATUS_APPROVED);
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            'submitted ke posted langsung' => [LoanApplicationService::STATUS_SUBMITTED, LoanApplicationService::STATUS_POSTED],
            'rejected ke approved' => [LoanApplicationService::STATUS_REJECTED, LoanApplicationService::STATUS_APPROVED],
            'cancelled ke submitted' => [LoanApplicationService::STATUS_CANCELLED, LoanApplicationService::STATUS_SUBMITTED],
            'posted ke apa pun' => [LoanApplicationService::STATUS_POSTED, LoanApplicationService::STATUS_REJECTED],
            'approved ke rejected' => [LoanApplicationService::STATUS_APPROVED, LoanApplicationService::STATUS_REJECTED],
        ];
    }

    #[DataProvider('invalidTransitionProvider')]
    public function test_invalid_transitions_are_blocked(string $from, string $to): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->assertTransition($from, $to);
    }

    public function test_maker_checker_blocks_self_decision(): void
    {
        $this->assertFalse($this->service->canDecide(10, 10), 'Maker tidak boleh menyetujui pengajuannya sendiri.');
        $this->assertTrue($this->service->canDecide(10, 20));
    }

    public function test_cancel_rules_depend_on_status_and_role(): void
    {
        // Submitted: hanya pembuat boleh membatalkan.
        $this->assertTrue($this->service->canCancel(10, 10, LoanApplicationService::STATUS_SUBMITTED));
        $this->assertFalse($this->service->canCancel(10, 20, LoanApplicationService::STATUS_SUBMITTED));

        // Approved: hanya orang lain (bukan pembuat) boleh membatalkan.
        $this->assertTrue($this->service->canCancel(10, 20, LoanApplicationService::STATUS_APPROVED));
        $this->assertFalse($this->service->canCancel(10, 10, LoanApplicationService::STATUS_APPROVED));

        // Status final tidak bisa dibatalkan.
        $this->assertFalse($this->service->canCancel(10, 10, LoanApplicationService::STATUS_POSTED));
        $this->assertFalse($this->service->canCancel(10, 10, LoanApplicationService::STATUS_REJECTED));
    }

    public function test_is_final_flags_terminal_statuses(): void
    {
        $this->assertTrue($this->service->isFinal(LoanApplicationService::STATUS_POSTED));
        $this->assertTrue($this->service->isFinal(LoanApplicationService::STATUS_REJECTED));
        $this->assertTrue($this->service->isFinal(LoanApplicationService::STATUS_CANCELLED));
        $this->assertFalse($this->service->isFinal(LoanApplicationService::STATUS_SUBMITTED));
        $this->assertFalse($this->service->isFinal(LoanApplicationService::STATUS_APPROVED));
    }
}
