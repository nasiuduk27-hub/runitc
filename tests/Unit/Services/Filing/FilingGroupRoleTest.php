<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingPermissionService;
use PHPUnit\Framework\TestCase;

class FilingGroupRoleTest extends TestCase
{
    public function test_rank_orders_viewer_below_contributor_below_manager(): void
    {
        $rank = FilingPermissionService::GROUP_ROLE_RANK;

        $this->assertLessThan($rank['contributor'], $rank['viewer']);
        $this->assertLessThan($rank['manager'], $rank['contributor']);
    }

    public function test_highest_role_picks_strongest(): void
    {
        $this->assertSame('manager', FilingPermissionService::highestRole(['viewer', 'manager']));
        $this->assertSame('manager', FilingPermissionService::highestRole(['manager', 'contributor']));
        $this->assertSame('contributor', FilingPermissionService::highestRole(['viewer', 'contributor']));
        $this->assertSame('viewer', FilingPermissionService::highestRole(['viewer']));
    }

    public function test_highest_role_ignores_unknown_and_empty(): void
    {
        $this->assertNull(FilingPermissionService::highestRole([]));
        $this->assertNull(FilingPermissionService::highestRole(['bogus']));
        $this->assertSame('viewer', FilingPermissionService::highestRole(['bogus', 'viewer']));
    }
}
