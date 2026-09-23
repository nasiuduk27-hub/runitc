<?php

namespace Tests\Unit\Support;

use App\Models\CreditUnion\CreditUnionMember;
use App\Support\CreditUnionAccess;
use PHPUnit\Framework\TestCase;

class CreditUnionAccessTest extends TestCase
{
    public function test_is_owner_or_maker_returns_true_for_maker(): void
    {
        // User 10 adalah pembuat pengajuan (maker_user_id = 10)
        $this->assertTrue(CreditUnionAccess::isOwnerOrMaker(10, 10, null));
        $this->assertFalse(CreditUnionAccess::isOwnerOrMaker(20, 10, null));
    }

    public function test_is_owner_or_maker_returns_false_for_non_positive_user(): void
    {
        $this->assertFalse(CreditUnionAccess::isOwnerOrMaker(0, 10, null));
        $this->assertFalse(CreditUnionAccess::isOwnerOrMaker(-1, null, 5));
    }
}
