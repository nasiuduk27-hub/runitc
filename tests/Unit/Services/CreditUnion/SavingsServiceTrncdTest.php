<?php

namespace Tests\Unit\Services\CreditUnion;

use App\Services\CreditUnion\SavingsService;
use PHPUnit\Framework\TestCase;

class SavingsServiceTrncdTest extends TestCase
{
    public function test_debit_codes_cover_one_time_and_monthly_savings(): void
    {
        $this->assertSame(['18', '19'], SavingsService::savingsDebitTrncds());
    }

    public function test_credit_codes_cover_monthly_and_withdrawal(): void
    {
        $this->assertSame(['19', '22'], SavingsService::savingsCreditTrncds());
    }

    public function test_all_codes_are_unique_and_merge_debit_credit(): void
    {
        $codes = SavingsService::savingsTrncds();

        $this->assertSame(['18', '19', '22'], $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
    }
}
