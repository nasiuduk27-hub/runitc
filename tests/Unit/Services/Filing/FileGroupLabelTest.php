<?php

namespace Tests\Unit\Services\Filing;

use App\Repositories\FilingSystem\FileGroup;
use PHPUnit\Framework\TestCase;

class FileGroupLabelTest extends TestCase
{
    public function test_division_with_known_code_uses_description(): void
    {
        $this->assertSame(
            'LOG - Logistik Division',
            FileGroup::formatMemberLabel('division', 'LOG', '', ['LOG' => 'Logistik Division'])
        );
    }

    public function test_division_with_unknown_code_falls_back_to_code(): void
    {
        $this->assertSame(
            'XYZ',
            FileGroup::formatMemberLabel('division', 'XYZ', '', ['LOG' => 'Logistik Division'])
        );
    }

    public function test_division_with_empty_description_falls_back_to_code(): void
    {
        $this->assertSame('ABC', FileGroup::formatMemberLabel('division', 'ABC', '', []));
    }

    public function test_user_member_uses_user_name(): void
    {
        $this->assertSame('Budi', FileGroup::formatMemberLabel('user', '5', 'Budi', []));
    }
}
