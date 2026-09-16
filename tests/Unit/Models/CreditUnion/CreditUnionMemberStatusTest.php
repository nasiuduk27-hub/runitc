<?php

namespace Tests\Unit\Models\CreditUnion;

use App\Models\CreditUnion\CreditUnionMember;
use PHPUnit\Framework\TestCase;

class CreditUnionMemberStatusTest extends TestCase
{
    public function test_active_unless_status_is_non_active(): void
    {
        foreach ([0, 1, 2, 3, 4, 5] as $status) {
            $member = new CreditUnionMember;
            $member->st_aktif = $status;

            $this->assertTrue($member->isActive(), "Status {$status} seharusnya aktif.");
        }

        $nonActive = new CreditUnionMember;
        $nonActive->st_aktif = CreditUnionMember::STATUS_NON_ACTIVE;

        $this->assertFalse($nonActive->isActive());
    }
}
