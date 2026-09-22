<?php

namespace Tests\Unit\Models\CreditUnion;

use App\Models\CreditUnion\CreditUnionSavingsWithdrawal;
use PHPUnit\Framework\TestCase;

class CreditUnionSavingsWithdrawalStatusTest extends TestCase
{
    public function test_processing_status_is_defined(): void
    {
        // Status antara dipakai untuk klaim atomik anti double-approve.
        $this->assertSame('processing', CreditUnionSavingsWithdrawal::STATUS_PROCESSING);
    }

    public function test_status_label_covers_all_states(): void
    {
        $expected = [
            CreditUnionSavingsWithdrawal::STATUS_SUBMITTED => 'Menunggu Persetujuan',
            CreditUnionSavingsWithdrawal::STATUS_PROCESSING => 'Sedang Diproses',
            CreditUnionSavingsWithdrawal::STATUS_APPROVED => 'Disetujui',
            CreditUnionSavingsWithdrawal::STATUS_REJECTED => 'Ditolak',
            CreditUnionSavingsWithdrawal::STATUS_CANCELLED => 'Dibatalkan',
        ];

        foreach ($expected as $status => $label) {
            $withdrawal = new CreditUnionSavingsWithdrawal;
            $withdrawal->status = $status;

            $this->assertSame($label, $withdrawal->statusLabel(), "Status {$status} harus berlabel {$label}.");
        }
    }

    public function test_processing_badge_is_pending_style(): void
    {
        $withdrawal = new CreditUnionSavingsWithdrawal;
        $withdrawal->status = CreditUnionSavingsWithdrawal::STATUS_PROCESSING;

        $this->assertStringContainsString('amber', $withdrawal->statusBadgeClass());
    }
}
