<?php

namespace Tests\Unit\Services\CreditUnion;

use App\Models\CreditUnion\CreditUnionReconciliation;
use App\Services\CreditUnion\CreditUnionReconciliationService;
use PHPUnit\Framework\TestCase;

class CreditUnionReconciliationServiceTest extends TestCase
{
    public function test_reconciliation_scopes_and_processing_status_are_defined(): void
    {
        $this->assertSame([
            'bank_monthly' => 'Bank bulanan',
            'savings' => 'Saldo simpanan anggota',
            'loan' => 'Outstanding pinjaman',
        ], CreditUnionReconciliationService::SCOPES);
        $this->assertSame('processing', CreditUnionReconciliation::STATUS_PROCESSING);
    }
}
