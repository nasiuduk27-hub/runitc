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

    public function test_acting_as_member_requires_admin_member_and_member_mode(): void
    {
        // Hanya admin yang sekaligus anggota, dan sedang memilih mode anggota.
        $this->assertTrue(CreditUnionAccess::resolveActingAsMember('member', true, true));
    }

    public function test_acting_as_member_is_false_for_non_admin_or_non_member(): void
    {
        $this->assertFalse(CreditUnionAccess::resolveActingAsMember('member', false, true));
        $this->assertFalse(CreditUnionAccess::resolveActingAsMember('member', true, false));
        $this->assertFalse(CreditUnionAccess::resolveActingAsMember('admin', true, true));
        $this->assertFalse(CreditUnionAccess::resolveActingAsMember(null, true, true));
    }
}
