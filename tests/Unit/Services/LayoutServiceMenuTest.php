<?php

namespace Tests\Unit\Services;

use App\Services\LayoutService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LayoutServiceMenuTest extends TestCase
{
    public function test_flatten_group_children_promotes_grandchildren(): void
    {
        $service = new LayoutService;
        $method = new ReflectionMethod($service, 'flattenGroupChildren');
        $method->setAccessible(true);

        $children = [
            ['title' => 'Dashboard', 'url' => 'http://x/dashboard', 'children' => []],
            [
                'title' => 'Keanggotaan',
                'url' => '#',
                'children' => [
                    ['title' => 'Anggota', 'url' => 'http://x/members', 'children' => []],
                    ['title' => 'Simpanan', 'url' => 'http://x/savings', 'children' => []],
                ],
            ],
            ['title' => 'Pengaturan', 'url' => 'http://x/settings', 'children' => []],
        ];

        $flat = $method->invoke($service, $children);

        $this->assertSame(['Dashboard', 'Anggota', 'Simpanan', 'Pengaturan'], array_column($flat, 'title'));
        $this->assertSame('http://x/settings', $flat[3]['url']);
    }

    public function test_member_mode_shows_member_section_and_hides_admin(): void
    {
        // Admin + anggota dalam mode anggota.
        $this->assertFalse(LayoutService::isSectionVisibleForMode('credit_union_admin', true, true, true));
        $this->assertTrue(LayoutService::isSectionVisibleForMode('credit_union', true, true, true));
    }

    public function test_admin_with_member_sees_both_sections_in_admin_mode(): void
    {
        // Admin yang juga anggota: section admin dan section anggota keduanya tampil.
        $this->assertTrue(LayoutService::isSectionVisibleForMode('credit_union_admin', false, true, true));
        $this->assertTrue(LayoutService::isSectionVisibleForMode('credit_union', false, true, true));
    }

    public function test_admin_without_member_does_not_see_member_section(): void
    {
        // Admin non-anggota: halaman anggota akan kosong, jadi section-nya disembunyikan.
        $this->assertTrue(LayoutService::isSectionVisibleForMode('credit_union_admin', false, true, false));
        $this->assertFalse(LayoutService::isSectionVisibleForMode('credit_union', false, true, false));
    }

    public function test_plain_member_never_sees_admin_section(): void
    {
        // Anggota biasa: section anggota tampil, section admin tidak — meski
        // ada grant menu admin yang menempel ke role anggota.
        $this->assertTrue(LayoutService::isSectionVisibleForMode('credit_union', false, false, true));
        $this->assertFalse(LayoutService::isSectionVisibleForMode('credit_union_admin', false, false, true));
    }

    public function test_other_sections_are_never_filtered(): void
    {
        $this->assertTrue(LayoutService::isSectionVisibleForMode('main', true, true));
        $this->assertTrue(LayoutService::isSectionVisibleForMode('main', false, true));
    }
}
